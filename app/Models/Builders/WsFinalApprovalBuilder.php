<?php

namespace App\Models\Builders;

use App\Services\WsFinalApprovalService;
use Illuminate\Database\Eloquent\Builder;

class WsFinalApprovalBuilder extends Builder
{
    public function update(array $values)
    {
        $rejected = $this->isRejectionUpdate($values);
        $sources = $rejected ? $this->get() : collect();
        $updated = parent::update($values);

        if ($updated && $rejected) {
            $sources->each(function ($source) {
                WsFinalApprovalService::rejectParameter($source);
            });
        }

        return $updated;
    }

    private function isRejectionUpdate(array $values): bool
    {
        foreach (['lhps', 'is_approve', 'is_approved'] as $column) {
            if (array_key_exists($column, $values) && (int) $values[$column] === 0) {
                return true;
            }
        }

        return false;
    }
}
