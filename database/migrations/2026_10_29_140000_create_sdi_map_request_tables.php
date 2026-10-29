<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Survey → Geo-informatics → SDI (docs/KLAES Survey Module – Workflow_V02.md 4):
 * Map Request feeds the Production Unit. Ported from the ALAES (Abia) SDI map
 * requests (alaes 2026_09_15 / 2026_09_16), same table names and core columns so
 * a later port of the SDI viewer/layers can reuse them, plus the KLAES request
 * and production fields (reference, contact, formats, officer, due date).
 *
 * The MDA registry is seeded with Kano State MDAs, grouped in ALAES's seven
 * thematic categories. The list is a starting point: it is edited in the app
 * (SDI → Map Request → Registry), and seeding never overwrites an edited row.
 * Additive only: new tables, no existing table is touched.
 */
return new class extends Migration
{
    private const CATEGORIES = [
        'core_land'      => ['Core Land Administration & Administrative Boundaries', 'landmark', '#107C41'],
        'environment'    => ['Environment, Natural Resources & Agriculture', 'leaf', '#2E7D32'],
        'infrastructure' => ['Physical Infrastructure, Utilities & Public Realm', 'zap', '#0277BD'],
        'social'         => ['Social Infrastructure & Human Capital', 'users', '#6A1B9A'],
        'economic'       => ['Economic Development, Commerce & Investment', 'trending-up', '#00838F'],
        'governance'     => ['Governance, Finance, Security & Justice', 'shield', '#C62828'],
        'innovation'     => ['Innovation, Information & Creative Economy', 'lightbulb', '#AD1457'],
    ];

    /** category => [code => [name, abbreviation, is_custodian]] */
    private const MDAS = [
        'core_land' => [
            'mlpp'    => ['Ministry of Land and Physical Planning', 'MLPP', true],
            'kangis'  => ['Kano State Geographic Information Service', 'KANGIS', false],
            'knupda'  => ['Kano State Urban Planning and Development Authority', 'KNUPDA', false],
            'mlgca'   => ['Ministry of Local Government and Chieftaincy Affairs', 'MLGCA', false],
            'works'   => ['Ministry of Works and Infrastructure', 'MWI', false],
            'karma'   => ['Kano State Road Maintenance Agency', 'KARMA', false],
        ],
        'environment' => [
            'environment' => ['Ministry of Environment and Climate Change', 'MECC', false],
            'remasab'     => ['Refuse Management and Sanitation Board', 'REMASAB', false],
            'agriculture' => ['Ministry of Agriculture and Natural Resources', 'MANR', false],
            'knarda'      => ['Kano Agricultural and Rural Development Authority', 'KNARDA', false],
            'livestock'   => ['Ministry of Livestock Development', 'MLD', false],
            'sema'        => ['State Emergency Management Agency', 'SEMA', false],
        ],
        'infrastructure' => [
            'water'     => ['Ministry of Water Resources', 'MWR', false],
            'waterboard'=> ['Kano State Water Board', 'KSWB', false],
            'power'     => ['Ministry of Power and Renewable Energy', 'MPRE', false],
            'transport' => ['Ministry of Transport', 'MOT', false],
            'karota'    => ['Kano Road Traffic Agency', 'KAROTA', false],
            'kasiaa'    => ['Kano State Signage and Advertisement Agency', 'KASIAA', false],
        ],
        'social' => [
            'health'      => ['Ministry of Health', 'MOH', false],
            'phc'         => ['Kano State Primary Health Care Management Board', 'KSPHCMB', false],
            'education'   => ['Ministry of Education', 'MOE', false],
            'subeb'       => ['State Universal Basic Education Board', 'SUBEB', false],
            'higher_edu'  => ['Ministry of Higher Education', 'MOHE', false],
            'youth'       => ['Ministry of Youth and Sports', 'MYS', false],
            'women'       => ['Ministry of Women Affairs, Children and Disabled', 'MWACD', false],
            'humanitarian'=> ['Ministry of Humanitarian Affairs and Poverty Alleviation', 'MHAPA', false],
            'kaspa'       => ['Kano State Social Protection Agency', 'KASPA', false],
            'religious'   => ['Ministry of Religious Affairs', 'MRA', false],
        ],
        'economic' => [
            'commerce'  => ['Ministry of Commerce, Industry and Cooperatives', 'MCIC', false],
            'kaninvest' => ['Kano State Investment Promotion Agency', 'KANINVEST', false],
            'kasmeda'   => ['Kano State Small and Medium Enterprises Development Agency', 'KASMEDA', false],
            'rural_dev' => ['Ministry of Rural and Community Development', 'MRCD', false],
        ],
        'governance' => [
            'finance'   => ['Ministry of Finance', 'MOF', false],
            'planning'  => ['Ministry of Planning and Budget', 'MPB', false],
            'justice'   => ['Ministry of Justice', 'MOJ', false],
            'kirs'      => ['Kano State Internal Revenue Service', 'KIRS', false],
            'special'   => ['Ministry of Special Duties', 'MSD', false],
            'statistics'=> ['Kano State Bureau of Statistics', 'KBS', false],
            'fire'      => ['Kano State Fire Service', 'KSFS', false],
        ],
        'innovation' => [
            'science'     => ['Ministry of Science, Technology and Innovation', 'MSTI', false],
            'kasitda'     => ['Kano State ICT Development Agency', 'KASITDA', false],
            'information' => ['Ministry of Information and Internal Affairs', 'MIIA', false],
            'culture'     => ['Kano State History and Culture Bureau', 'HCB', false],
        ],
    ];

    private const MAP_TYPES = ['Thematic Map', 'Cadastral Map', 'Topographic Map', 'Land Use Map', 'Base Map', 'Layout Plan'];

    public function up(): void
    {
        $schema = Schema::connection('sqlsrv');

        if (! $schema->hasTable('sdi_categories')) {
            $schema->create('sdi_categories', function (Blueprint $t) {
                $t->id();
                $t->string('code', 60)->unique();
                $t->string('name', 200);
                $t->string('icon', 60)->default('layers');
                $t->string('color', 20)->default('#0277BD');
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! $schema->hasTable('sdi_mdas')) {
            $schema->create('sdi_mdas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('category_id')->index();
                $t->string('code', 60)->unique();
                $t->string('name', 300);
                $t->string('abbreviation', 40)->nullable();
                $t->boolean('is_custodian')->default(false);
                $t->string('contact_name', 200)->nullable();
                $t->string('contact_email', 255)->nullable();
                $t->string('contact_phone', 40)->nullable();
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! $schema->hasTable('sdi_map_types')) {
            $schema->create('sdi_map_types', function (Blueprint $t) {
                $t->id();
                $t->string('name', 150)->unique();
                $t->integer('sort_order')->default(0);
                $t->boolean('is_active')->default(true);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! $schema->hasTable('sdi_map_requests')) {
            $schema->create('sdi_map_requests', function (Blueprint $t) {
                $t->id();
                $t->string('request_ref', 30)->unique();
                // Requesting body: a registry MDA, or typed when not listed ("Other").
                $t->unsignedBigInteger('mda_id')->nullable()->index();
                $t->string('requester_other', 300)->nullable();
                $t->string('contact_name', 200);
                $t->string('contact_phone', 50);
                $t->string('contact_email', 255)->nullable();
                $t->unsignedBigInteger('requested_by')->nullable();
                // The map asked for.
                $t->unsignedBigInteger('map_type_id')->nullable()->index();
                $t->string('map_name', 300);
                $t->string('map_reference', 120)->nullable();
                $t->string('map_description', 2000)->nullable();
                $t->string('output_formats', 200)->nullable();
                $t->string('priority', 20)->default('medium');
                $t->date('received_date');
                $t->date('due_date')->nullable();
                $t->text('geometry_geojson')->nullable();
                $t->float('bbox_north')->nullable();
                $t->float('bbox_south')->nullable();
                $t->float('bbox_east')->nullable();
                $t->float('bbox_west')->nullable();
                // Production Unit.
                $t->string('status', 20)->default('pending')->index();
                $t->string('production_officer', 200)->nullable();
                $t->timestamp('started_at')->nullable();
                $t->string('response_note', 2000)->nullable();
                $t->string('deliverable_path', 500)->nullable();
                $t->string('deliverable_original_name', 255)->nullable();
                $t->unsignedBigInteger('actioned_by')->nullable();
                $t->timestamp('actioned_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        $this->seed();
    }

    /** Insert only what is missing, keyed by code / name: an edited row is never overwritten. */
    private function seed(): void
    {
        $db = DB::connection('sqlsrv');
        $now = now();

        $order = 0;
        foreach (self::CATEGORIES as $code => [$name, $icon, $color]) {
            $order++;
            if (! $db->table('sdi_categories')->where('code', $code)->exists()) {
                $db->table('sdi_categories')->insert(compact('code', 'name', 'icon', 'color') + ['sort_order' => $order, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
        $catIds = $db->table('sdi_categories')->pluck('id', 'code');

        foreach (self::MDAS as $cat => $mdas) {
            $order = 0;
            foreach ($mdas as $code => [$name, $abbr, $custodian]) {
                $order++;
                if (! $db->table('sdi_mdas')->where('code', $code)->exists()) {
                    $db->table('sdi_mdas')->insert([
                        'category_id' => $catIds[$cat], 'code' => $code, 'name' => $name, 'abbreviation' => $abbr,
                        'is_custodian' => $custodian ? 1 : 0, 'sort_order' => $order, 'is_active' => 1,
                        'created_at' => $now, 'updated_at' => $now,
                    ]);
                }
            }
        }

        foreach (self::MAP_TYPES as $i => $name) {
            if (! $db->table('sdi_map_types')->where('name', $name)->exists()) {
                $db->table('sdi_map_types')->insert(['name' => $name, 'sort_order' => $i, 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $schema = Schema::connection('sqlsrv');
        foreach (['sdi_map_requests', 'sdi_map_types', 'sdi_mdas', 'sdi_categories'] as $t) {
            $schema->dropIfExists($t);
        }
    }
};
