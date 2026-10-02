<?php

namespace Tests;

use App\Services\BulkSmsNgService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Connection;
use Tests\Support\ReadOnlySqlServerConnection;

trait CreatesApplication
{
    /**
     * Creates the application.
     *
     * @return \Illuminate\Foundation\Application
     */
    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // Hard stop: tests run RefreshDatabase/migrate:fresh, which drops
        // every table. On 2026-09-24 phpunit.xml's sqlite override was
        // disabled with no .env.testing in place, so tests fell through to
        // the real .env and wiped the production 'klaes' database. This
        // guard makes that fail loudly instead of silently, no matter what
        // env file or override chain produced the config.
        $mysqlDb = $app['config']->get('database.connections.mysql.database');
        if (in_array($mysqlDb, ['klaes', 'klas'], true)) {
            fwrite(STDERR, "\nFATAL: tests are configured to use the production database '{$mysqlDb}'. Refusing to run — this would run migrate:fresh/RefreshDatabase against live data. Fix DB_DATABASE in .env.testing.\n\n");
            exit(1);
        }

        // The 'sqlsrv' connection has no isolated test database (see below) —
        // force every write/schema statement on it to throw during tests,
        // leaving reads intact. See tests/Support/ReadOnlySqlServerConnection.
        Connection::resolverFor('sqlsrv', function ($pdo, $database, $prefix, $config) {
            return new ReadOnlySqlServerConnection($pdo, $database, $prefix, $config);
        });

        // App\Models\User (and SmsDispatchLog) are pinned to the live 'sqlsrv'
        // connection — there is no isolated test database for them, so any
        // test that logs a real account in or resends a verification code is
        // acting on the real users table. CACHE_DRIVER is 'array' in
        // phpunit.xml, and every test boots a fresh application, so the
        // cache-backed cooldown / reuse-window / hourly-cap in LoginOtpService
        // and PhoneOtpService starts empty on every single test — none of
        // that throttling exists here. Without this binding, a test that
        // reaches either service's send path places a REAL call to the SMS
        // gateway. That is exactly what happened on 2026-09-24: an ordinary
        // test run sent 51 real "KLAES sign-in" texts to the real user 1's
        // real phone in ten minutes, one per test, uncapped. A test that
        // needs to assert on gateway behaviour still can — it overrides this
        // with its own instance() or mock, which wins over this default.
        $app->bind(BulkSmsNgService::class, function () {
            return new class extends BulkSmsNgService {
                public function sendFirstAccepted(string $phone, array $messages, ?string $sender = null): ?string
                {
                    return $messages[0] ?? null;
                }

                public function lastStatusCode(): ?string
                {
                    return '600';
                }

                public function lastFailureReason(): ?string
                {
                    return null;
                }
            };
        });

        return $app;
    }
}
