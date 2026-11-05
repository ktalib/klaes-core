<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * The private survey companies and their beacon letters, from the user's corrected list of
 * 2026-11-05. Adds only names not already in survey_companies. A dry run unless --apply.
 * Surv. Sani Dahiru is on both H and S, as listed.
 */
class SurveySeedCompanies extends Command
{
    protected $signature = 'survey:seed-companies {--apply : Add the companies; without it nothing is written}';

    protected $description = 'Load the private survey companies and their beacon letters';

    private const COMPANIES = [
        ['Peak Profile', 'G', 'Surv. Abdulsalam A.'],
        ['Suleiman Survey & Co', 'B', 'Surv. Bala Bello Suleiman'],
        ['Spherical Shape', 'D', 'Surv. Danlami Jaen'],
        ['Pace Setter Land Survey', 'C', 'Surv. A. S. Abdussalam'],
        ['Integrated Land Survey', 'H', 'Surv. Sani Dahiru'],
        ['Zenith Nadir Survey Service', 'T', 'Surv. Shehu Sangetso'],
        ['Meridian Survey', 'I', 'Surv. Muhd Idris'],
        ['SD Survey Ltd', 'S', 'Surv. Sani Dahiru'],
        ['Land Cadastral and ICT Service', 'K', 'Surv. Malandi Kura'],
        ['A U Land', 'A', 'Surv. A. U. Abubakar'],
        ['Synco Nig Ltd', 'Z', 'Surv. Shehu Zango'],
    ];

    public function handle(): int
    {
        $db = DB::connection('sqlsrv');
        $have = $db->table('survey_companies')->pluck('name')->map(fn ($n) => mb_strtoupper($n))->all();
        $new = array_filter(self::COMPANIES, fn ($c) => ! in_array(mb_strtoupper($c[0]), $have, true));

        foreach (self::COMPANIES as [$name, $letter, $surveyor]) {
            $this->line(sprintf('  %-3s %-34s %s%s', $letter, $name, $surveyor, in_array(mb_strtoupper($name), $have, true) ? '  (already there)' : ''));
        }

        if (! $this->option('apply')) {
            $this->warn('Dry run: ' . count($new) . ' to add. Use --apply to load.');

            return self::SUCCESS;
        }

        foreach ($new as [$name, $letter, $surveyor]) {
            $db->table('survey_companies')->insert([
                'name' => $name, 'beacon_letters' => $letter, 'surveyor_name' => $surveyor,
                'is_active' => 1, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        $this->info(count($new) . ' companies added.');

        return self::SUCCESS;
    }
}
