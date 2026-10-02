<?php

namespace App\Console\Commands;

use App\Services\Cadastral\CadastralSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Post-deployment check for the Cadastral Module.
 *
 * IT REPORTS; IT DOES NOT CREATE TABLES. This box is live production with no
 * backup infrastructure, and the migration ledger already disagrees with the
 * schema in places, so a command that silently created things would be the wrong
 * tool. The one thing it does write is the four additive nullable columns on
 * cadastral_officers, each guarded, because the module cannot route work without
 * post_code and adding a nullable column to a two-row lookup is safe.
 *
 * Idempotent: shaped on InstallTdp and InstallLandRegistration::ensureColumn().
 */
class InstallCadastralModule extends Command
{
    protected $signature = 'cadastral-module:install {--dry-run : Report only, change nothing}';

    protected $description = 'Verify the Cadastral Module schema, permissions map and configuration';

    private const CONN = 'sqlsrv';

    private const TABLES = [
        'cadastral_file_receipts',
        'cadastral_charts',
        'cadastral_chart_coordinates',
        'cadastral_index_cards',
        'cadastral_file_status_events',
        'cadastral_surveyors',
        'cadastral_survey_jobs',
        'cadastral_reports',
        'cadastral_report_steps',
        'cadastral_site_inspections',
        'cadastral_plan_descriptions',
        'cadastral_pillars',
        'cadastral_bills',
    ];

    public function handle(): int
    {
        $this->info('Cadastral Module — installation check');
        $this->line(str_repeat('-', 62));

        $problems = 0;

        $problems += $this->checkTables();
        $this->reportSettingsTables();
        $problems += $this->ensureOfficerColumns();
        $problems += $this->checkPermissionMap();
        $problems += $this->checkRole();
        $this->reportPosts();
        $problems += $this->checkJobNumberFormat();

        $this->line(str_repeat('-', 62));

        if ($problems === 0) {
            $this->info('All checks passed.');

            return self::SUCCESS;
        }

        $this->warn("{$problems} item(s) need attention. Nothing was created — see above.");

        return self::FAILURE;
    }

    private function checkTables(): int
    {
        $this->line('');
        $this->line('<comment>Schema</comment>');

        $missing = [];

        foreach (self::TABLES as $table) {
            if (Schema::connection(self::CONN)->hasTable($table)) {
                $count = DB::connection(self::CONN)->table($table)->count();
                $this->line(sprintf('  <info>ok</info>      %-32s %s row(s)', $table, number_format($count)));
            } else {
                $missing[] = $table;
                $this->line(sprintf('  <error>missing</error> %s', $table));
            }
        }

        if ($missing !== []) {
            $this->line('');
            $this->warn('  Run the migration by path — never a bare migrate, which would run 27 unrelated pending migrations:');
            $this->line('    php artisan migrate --database=sqlsrv \\');
            $this->line('      --path=database/migrations/2026_09_28_100000_create_cadastral_module_tables.php --force');

            return 1;
        }

        return 0;
    }

    /**
     * The Configurable Entries -> Cadastral tables (2026_10_01_100000).
     *
     * REPORT ONLY, and never counted as a problem: until they exist
     * CadastralSettings serves config/cadastral_module.php and the module works
     * the same, only without an edit screen. Says which source each part is
     * actually being read from, which is the thing worth knowing.
     */
    private function reportSettingsTables(): void
    {
        $this->line('');
        $this->line('<comment>Settings (Configurable Entries -> Cadastral)</comment>');

        $settings = new CadastralSettings();
        $missing = false;

        foreach ([CadastralSettings::TABLE_SETTINGS, CadastralSettings::TABLE_SCHEDULE, CadastralSettings::TABLE_BANDS] as $table) {
            if ($settings->hasTable($table)) {
                $count = DB::connection(self::CONN)->table($table)->count();
                $this->line(sprintf('  <info>ok</info>      %-32s %s row(s)', $table, number_format($count)));
            } else {
                $missing = true;
                $this->line(sprintf('  <comment>absent</comment>  %s', $table));
            }
        }

        if ($missing) {
            $this->line('  Values come from config/cadastral_module.php and the tab is hidden. To make them editable:');
            $this->line('    php artisan migrate --database=sqlsrv \\');
            $this->line('      --path=database/migrations/2026_10_01_100000_create_cadastral_settings_tables.php --force');
            $this->line('    php artisan db:seed --database=sqlsrv --class="Database\\Seeders\\CadastralSettingsSeeder" --force');

            return;
        }

        $saved = DB::connection(self::CONN)->table(CadastralSettings::TABLE_SETTINGS)->whereNotNull('value')->pluck('key')->all();
        $unsaved = array_diff(array_keys(CadastralSettings::DEFINITIONS), $saved);
        $this->line(sprintf('  Settings: %d of %d saved; %d read from config.', count(CadastralSettings::DEFINITIONS) - count($unsaved), count(CadastralSettings::DEFINITIONS), count($unsaved)));
        $this->line('  Area schedule read from: ' . $settings->areaScheduleSource() . ' · transport bands read from: ' . $settings->transportBandsSource());

        if (! $settings->get('fee_sheet.between_rows_confirmed')) {
            $this->warn('  Between-rows rule (' . $settings->betweenRowsRule() . ') is unconfirmed.');
        }
        if (! $settings->get('fee_sheet.transport_bands_confirmed')) {
            $this->warn('  Transport bands are unconfirmed.');
        }
    }

    /**
     * The four additive columns the report workflow routes on. Nullable, so they
     * cannot disturb SurveyReportController's Land 12 officer dropdown — the
     * table's only other reader.
     */
    private function ensureOfficerColumns(): int
    {
        $this->line('');
        $this->line('<comment>cadastral_officers</comment>');

        if (! Schema::connection(self::CONN)->hasTable('cadastral_officers')) {
            $this->line('  <error>missing</error> cadastral_officers does not exist');

            return 1;
        }

        $wanted = [
            'user_id'    => 'BIGINT NULL',
            'post_code'  => 'NVARCHAR(30) NULL',
            'department' => 'NVARCHAR(100) NULL',
            'is_active'  => 'BIT NULL',
        ];

        $added = 0;

        foreach ($wanted as $column => $type) {
            if (Schema::connection(self::CONN)->hasColumn('cadastral_officers', $column)) {
                $this->line("  <info>ok</info>      {$column}");

                continue;
            }

            if ($this->option('dry-run')) {
                $this->line("  <comment>would add</comment> {$column} {$type}");

                continue;
            }

            DB::connection(self::CONN)->statement("ALTER TABLE [cadastral_officers] ADD [{$column}] {$type}");
            $this->line("  <info>added</info>   {$column} {$type}");
            $added++;
        }

        if ($added > 0) {
            $this->line("  {$added} column(s) added.");
        }

        return 0;
    }

    private function checkPermissionMap(): int
    {
        $this->line('');
        $this->line('<comment>Permission map</comment>');

        $modules = config('module_permissions.modules', []);

        if (! array_key_exists('cadastral-module.*', $modules)) {
            $this->line('  <error>missing</error> config/module_permissions.php has no cadastral-module.* entry.');
            $this->warn("  Without it the routes are unmapped. They pass today because strict_routes is off,");
            $this->warn('  but every mutating route would 403 the moment someone turns it on.');

            return 1;
        }

        $this->line("  <info>ok</info>      cadastral-module.* => {$modules['cadastral-module.*']}");

        return 0;
    }

    private function checkRole(): int
    {
        $this->line('');
        $this->line('<comment>Role</comment>');

        $module = config('module_permissions.modules')['cadastral-module.*'] ?? 'Cad - Records';

        if (! Schema::connection(self::CONN)->hasTable('user_roles')) {
            $this->line('  <comment>skipped</comment> user_roles is not present on this connection.');

            return 0;
        }

        $exists = DB::connection(self::CONN)->table('user_roles')->where('name', $module)->exists();

        if (! $exists) {
            $this->line("  <error>missing</error> user_roles has no row named \"{$module}\".");

            return 1;
        }

        $holders = DB::connection(self::CONN)->table('users')
            ->where('assign_role', 'like', '%' . $module . '%')
            ->count();

        $this->line("  <info>ok</info>      \"{$module}\" exists · {$holders} user(s) hold it");

        if ($holders === 0) {
            $this->warn('  Nobody holds it, so the module is invisible. View is read from assign_role alone.');
        }

        return 0;
    }

    /**
     * Which of the twelve posts are filled. No personal data is seeded — who
     * holds which post is the department's to say.
     */
    private function reportPosts(): void
    {
        $this->line('');
        $this->line('<comment>Job posts</comment>');

        if (! Schema::connection(self::CONN)->hasColumn('cadastral_officers', 'post_code')) {
            $this->line('  <comment>skipped</comment> post_code has not been added yet.');

            return;
        }

        $filled = DB::connection(self::CONN)->table('cadastral_officers')
            ->whereNotNull('post_code')
            ->pluck('post_code')
            ->unique()
            ->all();

        $unfilled = [];

        foreach (config('cadastral_module.posts', []) as $code => $label) {
            if (in_array($code, $filled, true)) {
                $this->line(sprintf('  <info>filled</info>  %-18s %s', $code, $label));
            } else {
                $unfilled[] = $label;
            }
        }

        if ($unfilled !== []) {
            $this->line('  <comment>unfilled</comment>');
            foreach ($unfilled as $label) {
                $this->line("    - {$label}");
            }
            $this->warn('  A report step naming an unfilled post cannot be completed by anyone.');
        }
    }

    private function checkJobNumberFormat(): int
    {
        $this->line('');
        $this->line('<comment>Survey job numbers</comment>');

        // Through CadastralSettings, so a format saved in Configurable Entries is
        // the one reported.
        $job = (new CadastralSettings())->jobNumberFormat();
        $format = $job['format'];

        $this->line("  Format: {$format}");

        if ($job['is_placeholder']) {
            $this->line('');
            $this->error('  THE JOB NUMBER FORMAT IS AN UNCONFIRMED PLACEHOLDER.');
            $this->warn('  The concept note asks for SURCON compliance and nobody in this codebase knows');
            $this->warn('  the pattern SURCON mandates. Confirm it with the Surveyor-General before');
            $this->warn('  issuing in bulk, then switch the placeholder flag off in Configurable Entries -> Cadastral');
            $this->warn('  (or is_placeholder => false in config/cadastral_module.php before the settings migration).');

            $issued = Schema::connection(self::CONN)->hasTable('cadastral_survey_jobs')
                ? DB::connection(self::CONN)->table('cadastral_survey_jobs')->count()
                : 0;

            if ($issued > 0) {
                $this->error("  {$issued} number(s) have already been issued in this format.");
            }

            return 1;
        }

        $this->line('  <info>ok</info>      Format is confirmed.');

        return 0;
    }
}
