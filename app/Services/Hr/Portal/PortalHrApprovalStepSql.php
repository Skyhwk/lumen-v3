<?php

namespace App\Services\Hr\Portal;

final class PortalHrApprovalStepSql
{
    public static function scalar(string $requestTableAlias, string $step, string $state, string $column): string
    {
        return "(SELECT s.{$column} FROM hr_approval_step s
            WHERE s.request_id = {$requestTableAlias}.id AND s.step = '{$step}' AND s.state = '{$state}'
            ORDER BY s.id DESC LIMIT 1)";
    }
}
