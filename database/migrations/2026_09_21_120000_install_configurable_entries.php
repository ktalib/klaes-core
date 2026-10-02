<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Approved production installation of Configurable Entries, schema only.
 * Adapted from dev's configuration, billing and schedule migrations.
 * Does not run dev's billing/customer/workflow migration or copy its data.
 * No existing rate, identifier, counter or row is updated or deleted.
 * Rollback deliberately refuses to destroy configuration or issued numbers.
 */
return new class extends Migration
{
    protected $connection = 'sqlsrv';
    private const UNIQUE_INDEX = 'UX_grouping_file_format_serial';
    private const GROUPING_COLUMNS = [
        'schedule' => 'nvarchar(50) NULL',
        'file_prefix' => 'nvarchar(50) NULL',
        'file_format' => 'nvarchar(100) NULL',
        'serial_no' => 'int NULL',
    ];
    private const TABLES = [
        'revenue_items' => "
            CREATE TABLE dbo.revenue_items (
                id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_revenue_items PRIMARY KEY,
                rate_id int NULL,
                name nvarchar(300) NOT NULL,
                revenue_code nvarchar(20) NOT NULL,
                base_rate decimal(18,2) NOT NULL CONSTRAINT DF_revenue_items_base_rate DEFAULT 0,
                is_active bit NOT NULL CONSTRAINT DF_revenue_items_is_active DEFAULT 1,
                updated_by_name nvarchar(255) NULL,
                created_at datetime2 NULL,
                updated_at datetime2 NULL
            )",

        'instrument_fee_mappings' => "
            CREATE TABLE dbo.instrument_fee_mappings (
                id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_instrument_fee_mappings PRIMARY KEY,
                instrument_type nvarchar(150) NOT NULL,
                application_revenue_item_id bigint NULL,
                registration_revenue_item_id bigint NULL,
                approval_revenue_item_id bigint NULL,
                updated_by_name nvarchar(255) NULL,
                created_at datetime2 NULL,
                updated_at datetime2 NULL
            )",

        'instrument_workflow_steps' => "
            CREATE TABLE dbo.instrument_workflow_steps (
                id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_instrument_workflow_steps PRIMARY KEY,
                step_key nvarchar(20) NOT NULL,
                label nvarchar(150) NOT NULL,
                department nvarchar(150) NOT NULL,
                sort_order int NOT NULL CONSTRAINT DF_instrument_workflow_steps_sort_order DEFAULT 0,
                is_enabled bit NOT NULL CONSTRAINT DF_instrument_workflow_steps_is_enabled DEFAULT 1,
                updated_by_name nvarchar(255) NULL,
                created_at datetime2 NULL,
                updated_at datetime2 NULL
            )",

        'instrument_workflow_action_roles' => "
            CREATE TABLE dbo.instrument_workflow_action_roles (
                id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_instrument_workflow_action_roles PRIMARY KEY,
                action nvarchar(60) NOT NULL,
                roles nvarchar(max) NOT NULL CONSTRAINT DF_instrument_workflow_action_roles_roles DEFAULT '[]',
                updated_by_name nvarchar(255) NULL,
                created_at datetime2 NULL,
                updated_at datetime2 NULL
            )",
        'billing_types' => "
            CREATE TABLE dbo.billing_types (
                id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_billing_types PRIMARY KEY,
                code nvarchar(60) NOT NULL,
                name nvarchar(200) NOT NULL,
                module nvarchar(60) NULL,
                formula nvarchar(1000) NOT NULL CONSTRAINT DF_billing_types_formula DEFAULT '',
                description nvarchar(1000) NULL,
                sort_order int NOT NULL CONSTRAINT DF_billing_types_sort_order DEFAULT 0,
                is_active bit NOT NULL CONSTRAINT DF_billing_types_is_active DEFAULT 1,
                effective_from date NULL,
                effective_to date NULL,
                updated_by_name nvarchar(255) NULL,
                created_at datetime2 NULL,
                updated_at datetime2 NULL
            )",

        // source: revenue_item (amount = the item's base rate) | amount (fixed here) | workflow (the module supplies it)
        'billing_type_items' => "
            CREATE TABLE dbo.billing_type_items (
                id bigint IDENTITY(1,1) NOT NULL CONSTRAINT PK_billing_type_items PRIMARY KEY,
                billing_type_id bigint NOT NULL CONSTRAINT FK_billing_type_items_billing_types REFERENCES dbo.billing_types (id),
                variable nvarchar(40) NOT NULL,
                label nvarchar(200) NOT NULL,
                source nvarchar(20) NOT NULL CONSTRAINT DF_billing_type_items_source DEFAULT 'revenue_item',
                revenue_item_id bigint NULL,
                amount decimal(18,2) NULL,
                sequence int NOT NULL CONSTRAINT DF_billing_type_items_sequence DEFAULT 0,
                is_active bit NOT NULL CONSTRAINT DF_billing_type_items_is_active DEFAULT 1,
                effective_from date NULL,
                effective_to date NULL,
                updated_by_name nvarchar(255) NULL,
                created_at datetime2 NULL,
                updated_at datetime2 NULL
            )",

    ];

    private const INDEXES = [
        'UX_revenue_items_revenue_code' => ['revenue_items', 'UNIQUE NONCLUSTERED INDEX {name} ON dbo.revenue_items (revenue_code)'],
        'IX_revenue_items_name' => ['revenue_items', 'NONCLUSTERED INDEX {name} ON dbo.revenue_items (name)'],
        'UX_instrument_fee_mappings_type' => ['instrument_fee_mappings', 'UNIQUE NONCLUSTERED INDEX {name} ON dbo.instrument_fee_mappings (instrument_type)'],
        'UX_instrument_workflow_steps_key' => ['instrument_workflow_steps', 'UNIQUE NONCLUSTERED INDEX {name} ON dbo.instrument_workflow_steps (step_key)'],
        'UX_instrument_workflow_action_roles_action' => ['instrument_workflow_action_roles', 'UNIQUE NONCLUSTERED INDEX {name} ON dbo.instrument_workflow_action_roles (action)'],
    ];


    public function up(): void
    {
        $db = DB::connection('sqlsrv');
        $schema = Schema::connection('sqlsrv');
        foreach (self::TABLES as $table => $ddl) {
            if (!$schema->hasTable($table)) {
                $db->statement($ddl);
            }
        }

        foreach (self::INDEXES as $name => [$table, $definition]) {
            $exists = $db->selectOne('SELECT 1 AS found FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)', [$name, 'dbo.' . $table]);
            if (!$exists) {
                $db->statement('CREATE ' . str_replace('{name}', $name, $definition));
            }
        }

        if (!$schema->hasTable('file_schedules')) {
            $schema->create('file_schedules', function ($table) {
                $table->id();
                $table->string('code', 10)->unique();
                $table->string('name', 100);
                $table->integer('sort_order')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        if (!$schema->hasTable('schedule_file_formats')) {
            $schema->create('schedule_file_formats', function ($table) {
                $table->id();
                $table->foreignId('schedule_id')->constrained('file_schedules');
                $table->string('file_prefix', 50);
                $table->string('suffix', 10)->nullable();
                $table->string('pattern', 100)->unique();
                $table->string('label', 150);
                $table->integer('sort_order')->default(0);
                $table->integer('last_serial')->default(0);
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // ALAES's 2026_09_15_140000 adds this separately; folded in here.
        if ($schema->hasTable('schedule_file_formats') && !$schema->hasColumn('schedule_file_formats', 'commissioning_start')) {
            $db->statement('ALTER TABLE dbo.schedule_file_formats ADD commissioning_start int NULL');
        }

        if ($schema->hasTable('grouping')) {
            foreach (self::GROUPING_COLUMNS as $column => $type) {
                if (!$schema->hasColumn('grouping', $column)) {
                    $db->statement("ALTER TABLE dbo.[grouping] ADD [{$column}] {$type}");
                }
            }

            if (!$this->indexExists()) {
                $db->statement(
                    'CREATE UNIQUE NONCLUSTERED INDEX ' . self::UNIQUE_INDEX . '
                     ON dbo.[grouping] ([file_format], [serial_no])
                     INCLUDE ([schedule], [awaiting_fileno], [tracking_id], [mls_fileno])
                     WHERE [file_format] IS NOT NULL'
                );
            }
        }

        foreach ([
            'UX_billing_types_code' => 'dbo.billing_types (code)',
            'UX_billing_type_items_variable' => 'dbo.billing_type_items (billing_type_id, variable)',
        ] as $name => $target) {
            if (!$db->selectOne('SELECT 1 AS present FROM sys.indexes WHERE name = ?', [$name])) {
                $db->statement("CREATE UNIQUE NONCLUSTERED INDEX {$name} ON {$target}");
            }
        }
        foreach (['page_limit' => 'int NULL', 'is_shared' => 'bit NULL'] as $column => $definition) {
            if (!$schema->hasColumn('instrument_number_vaults', $column)) {
                $db->statement("ALTER TABLE dbo.instrument_number_vaults ADD [{$column}] {$definition}");
            }
        }

    }
    public function down(): void
    {
        throw new RuntimeException('Forward-only production migration: configuration and issued file numbers must be preserved.');
    }
    private function indexExists(): bool
    {
        return DB::connection('sqlsrv')->selectOne(
            'SELECT 1 AS present FROM sys.indexes WHERE name = ? AND object_id = OBJECT_ID(?)',
            [self::UNIQUE_INDEX, 'dbo.grouping']
        ) !== null;
    }
};

