<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Kano State MDAs: the lookup behind the External sender on File Movement
 * (Department) for a "Not a regular File/Mail" entry. The page adds an "Other"
 * option for anything not listed.
 *
 * The Ministry of Land and Physical Planning and its own agencies are internal
 * senders and are not listed. New table only, seeded once; a name already
 * present (any case) is never inserted again. Nothing existing is changed.
 */
return new class extends Migration {
    private const MDAS = [
        'Office' => [
            'Government House (Office of the Governor)',
            'Office of the Deputy Governor',
            'Office of the Secretary to the State Government',
            'Office of the Head of Civil Service',
            'Office of the Accountant-General',
            'Office of the State Auditor-General',
            'Office of the Auditor-General for Local Governments',
            'Kano State House of Assembly',
            'Kano State Judiciary (High Court of Justice)',
            'Sharia Court of Appeal',
        ],
        'Ministry' => [
            'Ministry of Agriculture and Natural Resources',
            'Ministry of Commerce, Industry and Cooperatives',
            'Ministry of Culture and Tourism',
            'Ministry of Education',
            'Ministry of Environment and Climate Change',
            'Ministry of Finance',
            'Ministry of Health',
            'Ministry of Higher Education',
            'Ministry of Housing and Transport',
            'Ministry of Humanitarian Affairs and Poverty Alleviation',
            'Ministry of Information and Internal Affairs',
            'Ministry of Justice',
            'Ministry of Local Government and Chieftaincy Affairs',
            'Ministry of Planning and Budget',
            'Ministry of Power and Renewable Energy',
            'Ministry of Project Monitoring and Evaluation',
            'Ministry of Religious Affairs',
            'Ministry of Rural and Community Development',
            'Ministry of Science, Technology and Innovation',
            'Ministry of Special Duties',
            'Ministry of Water Resources',
            'Ministry of Women Affairs, Children and Disabled',
            'Ministry of Works and Infrastructure',
            'Ministry of Youth and Sports',
        ],
        'Agency' => [
            'Kano State Civil Service Commission',
            'Kano State Local Government Service Commission',
            'Kano State Judicial Service Commission',
            'Kano State Independent Electoral Commission (KANSIEC)',
            'Kano State Public Complaints and Anti-Corruption Commission (PCACC)',
            'Kano State Internal Revenue Service (KIRS)',
            'Kano State Urban Planning and Development Authority (KNUPDA)',
            'Kano Road Traffic Agency (KAROTA)',
            'Kano State Water Board',
            'Kano State Rural Water Supply and Sanitation Agency (RUWASA)',
            'Refuse Management and Sanitation Board (REMASAB)',
            'Kano State Emergency Management Agency (SEMA)',
            'Kano State Fire Service',
            'Kano State Hisbah Board',
            'Kano State Sharia Commission',
            'Kano State Zakat and Hubusi Commission',
            'Kano State Pilgrims Welfare Board',
            'Kano State Universal Basic Education Board (SUBEB)',
            'Kano State Senior Secondary Schools Management Board',
            'Kano State Science and Technical Schools Board',
            'Kano State Scholarship Board',
            'Kano State Agency for Mass Education',
            'Kano State Library Board',
            'Kano State Hospitals Management Board',
            'Kano State Primary Health Care Management Board',
            'Kano State Contributory Healthcare Management Agency (KSCHMA)',
            'Kano State Drugs and Medical Consumables Supply Agency',
            'Kano Agricultural and Rural Development Authority (KNARDA)',
            'Kano State Housing Corporation',
            'Kano State Investment Promotion Agency (KANINVEST)',
            'Kano State Bureau of Statistics',
            'Kano State Bureau of Public Procurement',
            'Kano State Pension Fund Board',
            'Kano State History and Culture Bureau',
            'Kano State Censorship Board',
            'Kano State Sports Commission',
            'Radio Kano (Kano State Radio Corporation)',
            'Abubakar Rimi Television (ARTV)',
            'Local Government Council',
        ],
    ];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');
        if (!$schema->hasTable('kano_mdas')) {
            $schema->create('kano_mdas', function (Blueprint $table) {
                $table->id();
                $table->string('name', 255)->unique();
                $table->string('category', 50)->nullable();   // Office, Ministry, Agency
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        $db = DB::connection('sqlsrv');
        $have = $db->table('kano_mdas')->pluck('name')->map(fn ($n) => mb_strtolower(trim($n)))->flip();
        $order = 0;
        $rows = [];
        foreach (self::MDAS as $category => $names) {
            foreach ($names as $name) {
                $order += 10;
                if (isset($have[mb_strtolower($name)])) {
                    continue;
                }
                $rows[] = ['name' => $name, 'category' => $category, 'sort_order' => $order, 'is_active' => 1,
                    'created_at' => now(), 'updated_at' => now()];
            }
        }
        foreach (array_chunk($rows, 50) as $chunk) {
            $db->table('kano_mdas')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::connection('sqlsrv')->dropIfExists('kano_mdas');
    }
};
