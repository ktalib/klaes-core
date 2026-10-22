<?php

namespace App\Services\CadastralDept;

use App\Models\CadastralDept\CadastralCase;
use App\Services\AuditService;
use App\Services\InstrumentWorkflow\WorkflowGuardException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The main file's page count on its trip to Land (recording, 2026-10-18).
 *
 * When Registry sends the file to Land (detaching the correspondence file, if it has one) it
 * records how many pages the main file has; DMS's count for the file is offered, and Registry
 * corrects it if the folio differs. When the file comes back (attaching the correspondence
 * file) Registry enters the new count. More pages than went out triggers the DMS update
 * (EdmsUpdate) for the new pages; the same count or fewer means nothing to update.
 *
 * Steps ask for it with 'page_count' => 'out' | 'back' in config cadastral_dept.
 */
class PageCounts
{
    public const TABLE = 'cadastral_page_counts';
    private const CONN = 'sqlsrv';

    public function __construct(private WorkflowDefinition $def, private AuditService $audit)
    {
    }

    public static function installed(): bool
    {
        try {
            return Schema::connection(self::CONN)->hasTable(self::TABLE);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** out | back: what the current step asks for, or null. */
    public function direction(CadastralCase $case): ?string
    {
        if (!self::installed() || !$case->isOpen() || !$this->def->hasStep($case->workflow, $case->current_step)) {
            return null;
        }
        $dir = $this->def->step($case->workflow, $case->current_step)['page_count'] ?? null;

        return in_array($dir, ['out', 'back'], true) ? $dir : null;
    }

    /** Pages DMS holds for the file: page-typed pages, or uploaded scans when none are typed. */
    public function dmsPages(CadastralCase $case): ?int
    {
        $id = (int) $case->file_indexing_id;
        if (!$id) {
            return null;
        }
        $db = DB::connection(self::CONN);
        $typed = $db->table('pagetypings')->where('file_indexing_id', $id)->whereNull('deleted_at')->count();

        return $typed ?: $db->table('scannings')->where('file_indexing_id', $id)->count();
    }

    /** The count recorded at the current step since the file arrived there. */
    public function atStep(CadastralCase $case): ?object
    {
        if (!self::installed()) {
            return null;
        }
        $since = app(EdmsUpdate::class)->since($case);

        return DB::connection(self::CONN)->table(self::TABLE)
            ->where('cadastral_case_id', $case->id)->where('step', $case->current_step)
            ->when($since, fn ($q) => $q->where('recorded_at', '>=', $since))
            ->orderByDesc('id')->first();
    }

    /** The last count taken when the file went out to Land. */
    public function lastOut(CadastralCase $case): ?object
    {
        return self::installed()
            ? DB::connection(self::CONN)->table(self::TABLE)->where('cadastral_case_id', $case->id)
                ->where('direction', 'out')->orderByDesc('id')->first()
            : null;
    }

    /**
     * Where the counts stand for the current step.
     *
     * @return array{direction:?string, out:?object, back:?object, recorded:?object, dms_pages:?int, new_pages:?int, triggered:?bool}
     *         triggered: true = more pages came back (or no out count to compare with), false = none, null = not entered yet
     */
    public function state(CadastralCase $case): array
    {
        $dir = $this->direction($case);
        $recorded = $dir ? $this->atStep($case) : null;
        $out = $dir === 'back' ? $this->lastOut($case) : ($dir === 'out' ? $recorded : null);
        $back = $dir === 'back' ? $recorded : null;
        $new = $back && $out ? (int) $back->pages - (int) $out->pages : null;

        return [
            'direction' => $dir,
            'out' => $out,
            'back' => $back,
            'recorded' => $recorded,
            'dms_pages' => $dir ? $this->dmsPages($case) : null,
            'new_pages' => $new,
            'triggered' => $back ? ($out ? $new > 0 : true) : null,
        ];
    }

    /**
     * Why the correspondence file cannot be detached / attached yet, or null (client, 2026-10-18):
     * the pages are counted before the detach, and DMS is updated before the attach.
     */
    public function custodyBlocker(CadastralCase $case, string $action): ?string
    {
        $dir = $this->direction($case);
        if (!$dir) {
            return null;
        }
        $state = $this->state($case);
        if (!$state['recorded']) {
            return $dir === 'out' ? "Count the main file's pages first." : 'Enter the page count it came back with first.';
        }
        if ($action === 'attach' && $dir === 'back' && $state['triggered']) {
            $done = \App\Models\CadastralDept\CadastralCaseTask::query()->where('cadastral_case_id', $case->id)
                ->where('step', $case->current_step)->where('task_key', EdmsUpdate::TASK)->exists();
            if (!$done) {
                return 'Update DMS first' . ($state['new_pages'] > 0 ? " with the {$state['new_pages']} new page(s)" : '') . '.';
            }
        }

        return null;
    }

    public function record(CadastralCase $case, int $pages, ?int $userId): string
    {
        if (!self::installed()) {
            throw WorkflowGuardException::because('The page count table has not been created yet.');
        }
        if ($pages < 1 || $pages > 100000) {
            throw WorkflowGuardException::because('Enter the number of pages in the main file.');
        }

        return DB::connection(self::CONN)->transaction(function () use ($case, $pages, $userId) {
            $case = CadastralCase::query()->lockForUpdate()->findOrFail($case->id);
            $dir = $this->direction($case);
            if (!$dir) {
                throw WorkflowGuardException::because("{$case->case_ref} is not at a step that counts the main file's pages.");
            }
            $db = DB::connection(self::CONN);
            $now = now();
            $row = ['pages' => $pages, 'dms_pages' => $this->dmsPages($case), 'recorded_by' => $userId, 'recorded_at' => $now, 'updated_at' => $now];
            if ($existing = $this->atStep($case)) {
                $db->table(self::TABLE)->where('id', $existing->id)->update($row);
            } else {
                $db->table(self::TABLE)->insert($row + ['cadastral_case_id' => $case->id, 'step' => $case->current_step, 'direction' => $dir, 'created_at' => $now]);
            }

            $msg = $dir === 'out'
                ? "Main file page count recorded: {$pages} page(s) going to Land."
                : $this->backMessage($case, $pages);
            $this->audit->logAction('Cadastral Page Count', 'CadastralCase', $case->id, $existing ? ['pages' => $existing->pages] : null,
                ['direction' => $dir, 'pages' => $pages, 'step' => $case->current_step], "{$case->case_ref}: {$msg}");

            return $msg;
        });
    }

    private function backMessage(CadastralCase $case, int $pages): string
    {
        $out = $this->lastOut($case);
        if (!$out) {
            return "Main file page count recorded: {$pages} page(s). No count was taken when it went to Land, so update DMS with the new pages.";
        }
        $new = $pages - (int) $out->pages;

        return $new > 0
            ? "{$new} new page(s): {$out->pages} went to Land, {$pages} came back. Update DMS with the new pages."
            : "No new pages ({$out->pages} went to Land, {$pages} came back), so DMS needs no update.";
    }
}
