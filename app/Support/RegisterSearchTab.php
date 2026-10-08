<?php

namespace App\Support;

class RegisterSearchTab
{
    /** Prefer an exact file match, then keep the current tab when it has results. */
    public static function resolve(array $queries, string $current, string $search): string
    {
        $ordered = array_unique(array_merge([$current], array_keys($queries)));
        foreach ([true, false] as $exact) {
            foreach ($ordered as $tab) {
                if (!isset($queries[$tab])) {
                    continue;
                }
                $query = clone $queries[$tab];
                $query->where(function ($q) use ($search, $exact, $tab) {
                    if ($exact) {
                        $q->where('file_number', $search);
                        return;
                    }
                    $q->where('file_number', 'LIKE', "%{$search}%")
                        ->orWhere('applicant_name', 'LIKE', "%{$search}%");
                    if ($tab === 'batches') {
                        $q->orWhere('batch_mother_file_no', 'LIKE', "%{$search}%")
                            ->orWhere('rofo_batch_id', 'LIKE', "%{$search}%");
                    } else {
                        $q->orWhere('location', 'LIKE', "%{$search}%");
                    }
                });
                if ($query->exists()) {
                    return $tab;
                }
            }
        }

        return $current;
    }
}
