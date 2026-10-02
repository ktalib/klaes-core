<?php

namespace App\Services\CsvImporter\Security;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Auth\Authenticatable;

class ImportAccessGuard
{
    public function assertSystemAdmin(?Authenticatable $user): void
    {
        if (!config('csv_importer.enforce_role', true)) {
            return;
        }

        if ($user === null) {
            throw new AuthorizationException('Authentication required for CSV import operations.');
        }

        $requiredRole = config('csv_importer.required_role', 'System Admin');

        if (method_exists($user, 'hasRole') && $user->hasRole($requiredRole)) {
            return;
        }

        if (method_exists($user, 'isSystemAdmin') && $user->isSystemAdmin()) {
            return;
        }

        throw new AuthorizationException(sprintf(
            'CSV import modules are restricted to %s users.',
            $requiredRole
        ));
    }
}
