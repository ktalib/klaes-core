<?php

namespace App\Console\Commands;

use App\Models\Laas\LaasApplicant;
use App\Models\Laas\LaasApplication;
use App\Services\Laas\LaasCommissioningAccountService;
use App\Services\Laas\LaasTransferService;
use App\Support\HolderName;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Delete the never-used commissioning accounts and rebuild LAAS Portal
 * accounts for every live Land / OSS file. docs/plans/LAAS_PORTAL_REBUILD_PLAN.md §5.
 *
 *   php artisan laas:rebuild            PLAN ONLY: reads, decides every file's
 *                                       account in memory, writes the CSVs.
 *                                       Writes nothing to the database.
 *   php artisan laas:rebuild --commit   export → delete (small chunks) → carry
 *                                       out the plan, one short transaction per
 *                                       account. Asks first.
 *
 * NO LONG TRANSACTION. The klas database has no snapshot isolation, so readers
 * wait on locked rows; one transaction around 10k files froze every LAAS read
 * (Land 12, recommendation, RoFO and commissioning hooks) for its whole run.
 * The commit run is therefore made of short transactions, and is safe to re-run
 * if interrupted: unkept accounts are deleted again and the plan redone.
 *
 * KEPT (never deleted): self-registered accounts, accounts that signed in or
 * changed their password, accounts whose holder was texted a login, accounts
 * with a portal-filed application or an uploaded document. TEST_ACCOUNTS are
 * deleted regardless. Kept accounts keep id, username, password and files.
 *
 * WHICH ACCOUNT A FILE GOES TO, in order:
 *   1. already on a kept account: stays there;
 *   2. its parent file (CoP / re-grant / subdivision / merger) has the same
 *      holder name: the parent's account;
 *   3. it has a phone: phone AND name (a kept account first), as commissioning does;
 *   4. no phone, in a multi-file batch carrying one holder name: one account per
 *      holder name across such batches (a developer commissioned in several batches);
 *   5. otherwise its own account. Import batches of 200 different people are
 *      never grouped by name.
 * Accounts without a phone get the SYS- phone and the contact-pending flag.
 *
 * No SMS. Passwords are never written anywhere: holders get them by counter
 * slip, or by staff "Update contact" with a real phone.
 */
class LaasRebuild extends Command
{
    protected $signature = 'laas:rebuild
                            {--commit : Carry the plan out (default: plan and reports only)}
                            {--force : With --commit, do not ask for confirmation}
                            {--limit= : Only the first N files (testing)}';

    protected $description = 'Delete unused LAAS Portal accounts and rebuild accounts for every live Land/OSS file';

    /** Internal test accounts: deleted even though they were used. */
    private const TEST_ACCOUNTS = ['shuaibu.ali60', 'ibrahim.aminu98'];

    private const EXPORT_TABLES = [
        'laas_applicants', 'laas_applications', 'laas_application_events',
        'laas_documents', 'laas_stage_notifications', 'laas_transfer_candidates',
    ];

    private string $dir;

    public function handle(LaasCommissioningAccountService $accounts, LaasTransferService $transfers): int
    {
        $commit = (bool) $this->option('commit');

        $this->dir = storage_path('app/laas_rebuild/' . now()->format('Ymd_His') . ($commit ? '_commit' : '_plan'));
        @mkdir($this->dir, 0775, true);
        $this->info(($commit ? 'COMMIT RUN' : 'PLAN ONLY (no database writes)') . ' — reports in ' . $this->dir);

        $kept = $this->keptAccounts();
        $this->writeCsv('kept_accounts.csv', ['applicant_id', 'username', 'name', 'reasons'], array_map(
            fn ($k) => [$k['id'], $k['username'], $k['name'], implode('; ', $k['reasons'])], $kept
        ));
        $keptIds = array_keys($kept);

        [$plan, $counts] = $this->plan($kept);
        $this->writeCsv('plan_files.csv', ['file_number', 'holder', 'phone', 'batch_no', 'account_key', 'rule'], array_map(
            fn ($p) => [$p['file'], $p['holder'], $p['phone'] ? 'yes' : 'no', $p['batch'], $p['key'], $p['rule']], $plan
        ));
        $this->namesReview();

        $keys = array_unique(array_column($plan, 'key'));
        $newKeys = array_filter($keys, fn ($k) => !str_starts_with($k, 'a:'));
        $summary = [
            'files_live'                  => $counts['files'],
            'accounts_kept'               => count($keptIds),
            'accounts_to_delete'          => LaasApplicant::whereNotIn('id', $keptIds ?: [0])->count(),
            'applications_to_delete'      => LaasApplication::where(fn ($w) => $w->whereNull('laas_applicant_id')->orWhereNotIn('laas_applicant_id', $keptIds ?: [0]))->count(),
            'accounts_to_create'          => count($newKeys),
            'of_which_contact_pending'    => count(array_filter($newKeys, fn ($k) => !str_starts_with($k, 'p:'))),
        ] + array_diff_key($counts, ['files' => 1]);
        $this->writeCsv('summary.csv', ['measure', 'value'], array_map(null, array_keys($summary), array_values($summary)));
        $this->table(['Measure', 'Value'], array_map(null, array_keys($summary), array_values($summary)));

        if (!$commit) {
            $this->warn('PLAN ONLY — nothing was written. Review the CSVs, then run with --commit (out of hours).');

            return self::SUCCESS;
        }

        if (!$this->option('force') && !$this->confirm('Delete ' . $summary['accounts_to_delete'] . ' accounts and rebuild? This cannot be undone (the CSV export is taken first).')) {
            $this->warn('Stopped. Nothing was written.');

            return self::FAILURE;
        }

        foreach (self::EXPORT_TABLES as $table) {
            $this->line("  exported {$table}: " . $this->exportTable($table) . ' rows');
        }

        foreach ($this->deleteUnkept($keptIds) as $table => $n) {
            $this->line("  deleted {$table}: {$n}");
        }

        $done = $this->carryOut($accounts, $plan);
        $this->writeCsv('rebuild_result.csv', ['file_number', 'result', 'username'], $done);

        $found = $transfers->scanAll();
        $this->info("Done. {$found} transfer candidate(s) queued for staff. Run laas:reconcile to badge retired files.");

        return self::SUCCESS;
    }

    // ---------------------------------------------------------------- plan

    /**
     * Decide every file's account, in memory.
     *
     * @return array{0:array<int,array>,1:array<string,int>}
     */
    private function plan(array $kept): array
    {
        $db = DB::connection('sqlsrv');

        $files = $db->table('mls_file_no')
            ->where(fn ($q) => $q->whereNull('is_deleted')->orWhere('is_deleted', 0))
            ->orderBy('id')
            ->when($this->option('limit'), fn ($q, $n) => $q->limit((int) $n))
            ->get(['id', 'full_file_number', 'file_name', 'phone_no', 'email', 'batch_no']);

        // Files already on a kept account, and kept accounts by phone+name / email.
        $keptIds = array_keys($kept) ?: [0];
        $keptFiles = [];
        foreach (LaasApplication::current()->whereIn('laas_applicant_id', $keptIds)->get(['file_number', 'laas_applicant_id', 'applicant_name']) as $a) {
            $keptFiles[strtoupper(trim((string) $a->file_number))] = ['key' => 'a:' . $a->laas_applicant_id, 'name' => HolderName::normalise((string) $a->applicant_name)];
        }
        $keptByPhone = [];
        $keptByEmail = [];
        foreach (LaasApplicant::whereIn('id', $keptIds)->get(['id', 'name', 'phone', 'email']) as $a) {
            if ($p = LaasApplicant::normalizePhone((string) $a->phone)) {
                $keptByPhone[$p . '|' . HolderName::normalise((string) $a->name)] = 'a:' . $a->id;
            }
            $keptByEmail[mb_strtolower((string) $a->email)] = 'a:' . $a->id;
        }

        $batchNames = [];
        $batchSize = [];
        foreach ($files as $f) {
            if (trim((string) $f->batch_no) !== '') {
                $batchNames[$f->batch_no][HolderName::normalise((string) $f->file_name)] = true;
                $batchSize[$f->batch_no] = ($batchSize[$f->batch_no] ?? 0) + 1;
            }
        }

        $parents = $this->parentMap();
        $assigned = $keptFiles; // file (upper) => [key, name]
        $plan = [];
        $counts = ['files' => $files->count(), 'rule_kept' => 0, 'rule_parent' => 0, 'rule_phone' => 0, 'rule_batch' => 0, 'rule_own' => 0];

        foreach ($files as $f) {
            $file = trim((string) $f->full_file_number);
            $upper = strtoupper($file);
            $name = HolderName::normalise((string) $f->file_name);
            $phone = LaasApplicant::normalizePhone((string) $f->phone_no);
            $email = filter_var(mb_strtolower(trim((string) $f->email)), FILTER_VALIDATE_EMAIL) ?: null;
            $row = ['file' => $file, 'holder' => $f->file_name, 'phone' => (bool) $phone, 'batch' => $f->batch_no, 'attach' => false];

            if (isset($keptFiles[$upper])) {
                $plan[] = $row + ['key' => $keptFiles[$upper]['key'], 'rule' => 'already on a kept account', 'skip' => true];
                $counts['rule_kept']++;
                continue;
            }

            $parentKey = null;
            foreach ($parents[$upper] ?? [] as $parent) {
                $p = $assigned[strtoupper($parent)] ?? null;
                if ($p && $name !== '' && $p['name'] === $name) {
                    $parentKey = $p['key'];
                    break;
                }
            }

            if ($parentKey) {
                [$key, $rule, $attach] = [$parentKey, 'same holder as parent file', true];
                $counts['rule_parent']++;
            } elseif ($email && isset($keptByEmail[$email])) {
                [$key, $rule, $attach] = [$keptByEmail[$email], 'email of a kept account', true];
                $counts['rule_phone']++;
            } elseif ($phone) {
                $key = $keptByPhone[$phone . '|' . $name] ?? ('p:' . $phone . '|' . $name);
                [$rule, $attach] = ['phone and name', str_starts_with($key, 'a:')];
                $counts['rule_phone']++;
            } elseif (trim((string) $f->batch_no) !== '' && count($batchNames[$f->batch_no] ?? []) === 1 && ($batchSize[$f->batch_no] ?? 0) > 1) {
                [$key, $rule, $attach] = ['b:' . $name, 'one-holder batch', false];
                $counts['rule_batch']++;
            } else {
                [$key, $rule, $attach] = ['f:' . $upper, 'own account (no phone)', false];
                $counts['rule_own']++;
            }

            $assigned[$upper] = ['key' => $key, 'name' => $name];
            $row['attach'] = $attach;
            $plan[] = $row + ['key' => $key, 'rule' => $rule, 'skip' => false];
        }

        return [$plan, $counts];
    }

    // ------------------------------------------------------------- execute

    /** Carry the plan out, one account at a time. */
    private function carryOut(LaasCommissioningAccountService $accounts, array $plan): array
    {
        $groups = [];
        foreach ($plan as $p) {
            if (!$p['skip']) {
                $groups[$p['key']][] = $p;
            }
        }

        $results = [];
        $keyAccount = []; // plan key => LaasApplicant
        $bar = $this->output->createProgressBar(count($groups));

        foreach ($groups as $key => $members) {
            $bar->advance();
            try {
                $account = str_starts_with($key, 'a:') ? LaasApplicant::find((int) substr($key, 2)) : ($keyAccount[$key] ?? null);

                $primary = array_values(array_filter($members, fn ($m) => !$m['attach']));
                $attached = array_values(array_filter($members, fn ($m) => $m['attach']));

                if (!$account && $primary) {
                    $report = $accounts->provisionForFiles(array_column($primary, 'file'), ['send_sms' => false, 'require_phone' => false]);
                    $first = $report[0] ?? null;
                    if (!$first || ($first['status'] ?? '') === 'failed') {
                        throw new \RuntimeException($first['error'] ?? 'not provisioned');
                    }
                    $account = LaasApplicant::find($first['applicant_id']);
                    $keyAccount[$key] = $account;
                    foreach ($primary as $m) {
                        $results[] = [$m['file'], $first['status'], $account->username];
                    }
                } elseif ($account && $primary) {
                    $accounts->provisionOntoAccount($account, array_column($primary, 'file'));
                    foreach ($primary as $m) {
                        $results[] = [$m['file'], 'added to existing account', $account->username];
                    }
                }

                if ($attached) {
                    if (!$account) {
                        throw new \RuntimeException('parent account not built yet');
                    }
                    $accounts->provisionOntoAccount($account, array_column($attached, 'file'));
                    foreach ($attached as $m) {
                        $results[] = [$m['file'], 'joined ' . $m['rule'], $account->username];
                    }
                }
            } catch (\Throwable $e) {
                foreach ($members as $m) {
                    $results[] = [$m['file'], 'FAILED: ' . $e->getMessage(), ''];
                }
            }
        }
        $bar->finish();
        $this->newLine();

        return $results;
    }

    // --------------------------------------------------------------- kept

    /** @return array<int,array{id:int,username:?string,name:string,reasons:array<int,string>}> */
    private function keptAccounts(): array
    {
        $db = DB::connection('sqlsrv');
        $kept = [];
        $add = function ($ids, string $reason) use (&$kept) {
            foreach ($ids as $id) {
                $kept[(int) $id][] = $reason;
            }
        };

        $add(LaasApplicant::whereNull('account_origin')->orWhere('account_origin', '<>', LaasApplicant::ORIGIN_COMMISSIONING)->pluck('id'), 'self-registered');
        $add(LaasApplicant::where('must_change_password', 0)->pluck('id'), 'changed password');
        $add(LaasApplicant::whereNotNull('last_login_at')->pluck('id'), 'signed in');
        $add($db->table('laas_application_events as e')->join('laas_applications as a', 'a.id', '=', 'e.laas_application_id')
            ->where('e.sms_status', 'sent')->distinct()->pluck('a.laas_applicant_id'), 'texted a login');
        $add(LaasApplication::where(fn ($q) => $q->whereNull('origin')->orWhere('origin', '<>', LaasApplication::ORIGIN_COMMISSIONING))
            ->whereNotNull('laas_applicant_id')->distinct()->pluck('laas_applicant_id'), 'portal-filed application');
        $add($db->table('laas_documents as d')->join('laas_applications as a', 'a.id', '=', 'd.laas_application_id')
            ->distinct()->pluck('a.laas_applicant_id'), 'uploaded document');

        $rows = LaasApplicant::whereIn('id', array_keys($kept) ?: [0])->get(['id', 'username', 'name'])->keyBy('id');
        $out = [];
        foreach ($kept as $id => $reasons) {
            $row = $rows[$id] ?? null;
            if (!$row || in_array((string) $row->username, self::TEST_ACCOUNTS, true)) {
                continue;
            }
            $out[$id] = ['id' => $id, 'username' => $row->username, 'name' => (string) $row->name, 'reasons' => array_values(array_unique($reasons))];
        }

        return $out;
    }

    /**
     * Delete every unkept account and its applications, 500 applications at a
     * time, each chunk its own short transaction.
     *
     * @return array<string,int>
     */
    private function deleteUnkept(array $keptIds): array
    {
        $db = DB::connection('sqlsrv');
        $keep = $keptIds ?: [0];
        $counts = ['laas_application_events' => 0, 'laas_stage_notifications' => 0, 'laas_documents' => 0, 'laas_applications' => 0, 'laas_applicants' => 0];

        $counts['laas_transfer_candidates'] = $db->table('laas_transfer_candidates')->where('status', 'pending')->delete();

        while (true) {
            $ids = $db->table('laas_applications')
                ->where(fn ($w) => $w->whereNull('laas_applicant_id')->orWhereNotIn('laas_applicant_id', $keep))
                ->orderBy('id')->limit(500)->pluck('id')->all();
            if (!$ids) {
                break;
            }
            $db->transaction(function () use ($db, $ids, &$counts) {
                foreach (['laas_application_events', 'laas_stage_notifications', 'laas_documents'] as $child) {
                    $counts[$child] += $db->table($child)->whereIn('laas_application_id', $ids)->delete();
                }
                $counts['laas_applications'] += $db->table('laas_applications')->whereIn('id', $ids)->delete();
            });
        }

        while (true) {
            $ids = $db->table('laas_applicants')->whereNotIn('id', $keep)->orderBy('id')->limit(500)->pluck('id')->all();
            if (!$ids) {
                break;
            }
            $counts['laas_applicants'] += $db->table('laas_applicants')->whereIn('id', $ids)->delete();
        }

        return $counts;
    }

    // ------------------------------------------------------------- helpers

    /**
     * child file (upper) => parent file numbers, from real decommissionings
     * (successor_file_no CSV) and file_indexings.parent_prop_id.
     */
    private function parentMap(): array
    {
        $db = DB::connection('sqlsrv');
        $map = [];

        $db->table('decommissioned_files')
            ->whereNotNull('successor_file_no')
            ->where(fn ($q) => $q->whereNull('false_decommissioning')->orWhere('false_decommissioning', '<>', \App\Support\DecommissionScope::FALSE_DECOMMISSIONING))
            ->orderBy('id')
            ->chunk(2000, function ($rows) use (&$map) {
                foreach ($rows as $r) {
                    foreach (array_filter(array_map('trim', explode(',', (string) $r->successor_file_no))) as $child) {
                        $map[strtoupper($child)][] = trim((string) ($r->mls_file_no ?: $r->file_no));
                    }
                }
            });

        $propToFile = [];
        $childProps = [];
        $db->table('file_indexings')->whereNull('deleted_at')
            ->select(['id', 'file_number', 'prop_id', 'parent_prop_id'])
            ->orderBy('id')
            ->chunk(5000, function ($rows) use (&$propToFile, &$childProps) {
                foreach ($rows as $r) {
                    if (trim((string) $r->prop_id) !== '') {
                        $propToFile[trim((string) $r->prop_id)][] = trim((string) $r->file_number);
                    }
                    if (trim((string) $r->parent_prop_id) !== '') {
                        $childProps[strtoupper(trim((string) $r->file_number))] = array_filter(array_map('trim', explode(',', (string) $r->parent_prop_id)));
                    }
                }
            });
        foreach ($childProps as $child => $props) {
            foreach ($props as $prop) {
                foreach ($propToFile[$prop] ?? [] as $parent) {
                    if (strcasecmp($parent, $child) !== 0) {
                        $map[$child][] = $parent;
                    }
                }
            }
        }

        return array_map(fn ($p) => array_values(array_unique(array_filter($p))), $map);
    }

    /** The commissioning name vs the indexing holder, where they really differ. */
    private function namesReview(): void
    {
        $rows = [];
        DB::connection('sqlsrv')->table('mls_file_no as m')
            ->join('file_indexings as i', 'i.file_number', '=', 'm.full_file_number')
            ->where(fn ($q) => $q->whereNull('m.is_deleted')->orWhere('m.is_deleted', 0))
            ->whereNotNull('i.current_holder')
            ->select(['m.id', 'm.full_file_number', 'm.file_name', 'i.current_holder', 'm.source', 'm.sub_source'])
            ->orderBy('m.id')
            ->chunk(2000, function ($chunk) use (&$rows) {
                foreach ($chunk as $r) {
                    $holder = json_decode((string) $r->current_holder, true);
                    $holder = is_string($holder) ? $holder : (string) $r->current_holder;
                    if (HolderName::normalise($holder) !== '' && HolderName::normalise($holder) !== HolderName::normalise((string) $r->file_name)) {
                        $rows[] = [$r->full_file_number, $r->file_name, $holder, $r->source, $r->sub_source];
                    }
                }
            });

        $this->writeCsv('names_review.csv', ['file_number', 'portal_holder (commissioning)', 'indexing_current_holder', 'source', 'sub_source'], $rows);
        $this->line('  names to review: ' . count($rows));
    }

    private function exportTable(string $table): int
    {
        if (!\Illuminate\Support\Facades\Schema::connection('sqlsrv')->hasTable($table)) {
            return 0;
        }

        $fh = fopen($this->dir . "/export_{$table}.csv", 'w');
        $n = 0;
        $header = false;
        DB::connection('sqlsrv')->table($table)->orderBy('id')->chunk(2000, function ($rows) use ($fh, &$n, &$header) {
            foreach ($rows as $row) {
                $row = (array) $row;
                if (!$header) {
                    fputcsv($fh, array_keys($row));
                    $header = true;
                }
                fputcsv($fh, array_map(fn ($v) => $v === null ? '' : (string) $v, $row));
                $n++;
            }
        });
        fclose($fh);

        return $n;
    }

    private function writeCsv(string $name, array $header, array $rows): void
    {
        $fh = fopen($this->dir . '/' . $name, 'w');
        fputcsv($fh, $header);
        foreach ($rows as $row) {
            fputcsv($fh, array_map(fn ($v) => $v === null ? '' : (string) $v, (array) $row));
        }
        fclose($fh);
    }
}
