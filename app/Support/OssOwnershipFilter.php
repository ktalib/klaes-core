<?php

namespace App\Support;

final class OssOwnershipFilter
{
    /** The expression must be a trusted SQL column/expression, never request input. */
    public static function noChangeSql(string $fileNumberExpression): string
    {
        return "NOT EXISTS (
            SELECT 1 FROM mls_file_no ownership_registry
            WHERE ownership_registry.full_file_number = {$fileNumberExpression}
              AND ownership_registry.system_sub_type = 'OSS'
              AND (ownership_registry.is_deleted IS NULL OR ownership_registry.is_deleted = 0)
        ) AND NOT EXISTS (
            SELECT 1 FROM oss_applications ownership_application
            WHERE ownership_application.file_no = {$fileNumberExpression}
              AND ownership_application.system_source = 'OSSOPCHANGEOFNAME'
              AND (ownership_application.is_deleted IS NULL OR ownership_application.is_deleted = 0)
        )";
    }
}
