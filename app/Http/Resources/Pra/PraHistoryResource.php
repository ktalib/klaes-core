<?php

namespace App\Http\Resources\Pra;

use Illuminate\Http\Resources\Json\JsonResource;

class PraHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'prop_id' => $this->resource['prop_id'] ?? null,
            'mlsFNo' => $this->resource['mlsFNo'] ?? $this->resource['mlsfno'] ?? null,
            'transaction_type' => $this->resource['transaction_type'] ?? $this->resource['transactionType'] ?? null,
            'transaction_date' => $this->resource['transaction_date'] ?? $this->resource['transactionDate'] ?? null,
            'reg_date' => $this->resource['reg_date'] ?? $this->resource['regDate'] ?? null,
            'reg_time' => $this->resource['reg_time'] ?? $this->resource['regTime'] ?? null,
            'serialNo' => $this->resource['serialNo'] ?? $this->resource['serial_no'] ?? '0',
            'pageNo' => $this->resource['pageNo'] ?? $this->resource['page_no'] ?? '0',
            'volumeNo' => $this->resource['volumeNo'] ?? $this->resource['volume_no'] ?? '0',
            'land_use' => $this->resource['land_use'] ?? $this->resource['landUse'] ?? null,
            'location' => $this->resource['location'] ?? null,
            'parties' => [
                'assignor' => $this->resource['Assignor'] ?? $this->resource['assignor'] ?? null,
                'assignee' => $this->resource['Assignee'] ?? $this->resource['assignee'] ?? null,
                'mortgagor' => $this->resource['Mortgagor'] ?? $this->resource['mortgagor'] ?? null,
                'mortgagee' => $this->resource['Mortgagee'] ?? $this->resource['mortgagee'] ?? null,
                'grantor' => $this->resource['Grantor'] ?? $this->resource['grantor'] ?? null,
                'grantee' => $this->resource['Grantee'] ?? $this->resource['grantee'] ?? null,
            ],
        ];
    }
}
