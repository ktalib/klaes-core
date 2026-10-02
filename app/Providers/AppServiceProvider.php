<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\Mailer\Transport\Dsn;
use Symfony\Component\Mailer\Transport\Smtp\EsmtpTransportFactory;
use Symfony\Component\Mailer\Transport\Smtp\Stream\SocketStream;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        // Default In-process In-transit Tracking. Shared for the whole request so
        // the commissioning register is read once per page (the list endpoint primes
        // it) instead of once per row by every caller — the location resolver and
        // the tracker decoration both ask it about the same file numbers.
        $this->app->singleton(\App\Services\FileCommissioningTrackingService::class);

        // OCR provider for Online Legal Search ID name verification. Bound to the
        // interface so the engine can be swapped from configuration — and so
        // feature tests can substitute a fake without a local Tesseract install.
        $this->app->bind(\App\Services\Ocr\OcrReader::class, function () {
            return match (config('id_verification.ocr.driver', 'tesseract')) {
                default => new \App\Services\Ocr\TesseractOcrReader(),
            };
        });

        // Roll of legal practitioners, for checking a lawyer's Call-to-Bar number.
        // No Nigerian roll API is wired up, so the null driver is the default and
        // answers "unknown" — never "rejected" — to everything.
        $this->app->bind(\App\Services\BarNumber\BarRollLookup::class, function () {
            return match (config('id_verification.bar_number.lookup_driver', 'none')) {
                default => new \App\Services\BarNumber\NullBarRollLookup(),
            };
        });
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Use custom PersonalAccessToken model so Sanctum reads tokens from sqlsrv
        Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

        $this->registerPermissionDirectives();

        // Only set default string length for MySQL connections
        if (config('database.default') === 'mysql') {
            Schema::defaultStringLength(191);
        }

        // Local-dev escape hatch: when MAIL_VERIFY_PEER=false, build the SMTP
        // transport with TLS certificate verification disabled. Laravel 9's
        // default SMTP builder offers no such option, so we override the driver.
        // Default (true) keeps full verification — production is unaffected.
        if (config('mail.mailers.smtp.verify_peer') === false) {
            Mail::extend('smtp', function (array $config) {
                $scheme = $config['scheme'] ?? (
                    (! empty($config['encryption']) && $config['encryption'] === 'tls')
                        ? (((int) ($config['port'] ?? 0) === 465) ? 'smtps' : 'smtp')
                        : ''
                );

                $transport = (new EsmtpTransportFactory)->create(new Dsn(
                    $scheme,
                    $config['host'],
                    $config['username'] ?? null,
                    $config['password'] ?? null,
                    $config['port'] ?? null,
                    $config
                ));

                $stream = $transport->getStream();
                if ($stream instanceof SocketStream) {
                    $stream->setStreamOptions(array_replace_recursive($stream->getStreamOptions(), [
                        'ssl' => [
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true,
                        ],
                    ]));
                }

                return $transport;
            });
        }
    }

    /**
     * Blade directives for the module permission gate.
     *
     *   @canDo('Survey - Records', 'create') ... @elsecanDo ... @endcanDo
     *   @canAnyDo('Survey - Records', ['create', 'edit']) ... @endcanAnyDo
     *
     * Hiding a control is only half of a permission — every place these wrap a button, the
     * matching route must also be mapped in config/module_permissions.php, or the button is
     * gone while the URL behind it still works. The two always land together.
     *
     * Named canDo/@canDo rather than reusing @can: @can routes through Laravel's Gate into
     * User::can(), which answers against the empty Spatie tables. Overloading it would make
     * two different authorization systems answer to one directive.
     */
    private function registerPermissionDirectives(): void
    {
        Blade::if('canDo', function (string $module, string $action = 'view') {
            return auth()->check() && auth()->user()->canDo($module, $action);
        });

        Blade::if('canAnyDo', function (string $module, $actions) {
            if (!auth()->check()) {
                return false;
            }

            foreach ((array) $actions as $action) {
                if (auth()->user()->canDo($module, $action)) {
                    return true;
                }
            }

            return false;
        });
    }
}
