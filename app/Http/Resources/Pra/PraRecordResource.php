<?php

namespace App\Http\Resources\Pra;

use Illuminate\Http\Resources\Json\JsonResource;

class PraRecordResource extends JsonResource
{
    public function toArray($request): array
    {
        $resource = $this->resource ?? [];

        $houseNo = $resource['house_no']
            ?? $resource['houseNo']
            ?? $resource['property_house_no']
            ?? $resource['address_house_no']
            ?? null;

        $plotNo = $resource['plot_no']
            ?? $resource['plotNo']
            ?? $resource['plot_number']
            ?? null;

        $streetName = $resource['street_name']
            ?? $resource['streetName']
            ?? $resource['street']
            ?? $resource['street_address']
            ?? null;

        $district = $resource['district']
            ?? $resource['district_name']
            ?? $resource['districtName']
            ?? $resource['ward']
            ?? null;

        $lga = $resource['lga']
            ?? $resource['lga_name']
            ?? $resource['lgaName']
            ?? $resource['lgsaOrCity']
            ?? $resource['lgsa_or_city']
            ?? $resource['city']
            ?? null;

        $state = $resource['state']
            ?? $resource['state_name']
            ?? $resource['stateName']
            ?? null;

        $relatedFileNumber = $resource['related_file_number']
            ?? $resource['relatedFileNumber']
            ?? null;

        $layout = $resource['layout']
            ?? $resource['layout_name']
            ?? $resource['layoutName']
            ?? null;

        $schedule = $resource['schedule']
            ?? $resource['schedule_name']
            ?? $resource['scheduleName']
            ?? null;

        $tpNo = $resource['tp_no']
            ?? $resource['tpNo']
            ?? null;

        $lpknNo = $resource['lpkn_no']
            ?? $resource['lpknNo']
            ?? null;

        $approvedPlanNo = $resource['approved_plan_no']
            ?? $resource['approvedPlanNo']
            ?? null;

        $plotSize = $resource['plot_size']
            ?? $resource['plotSize']
            ?? null;

        $dateRecommended = $resource['date_recommended']
            ?? $resource['dateRecommended']
            ?? null;

        $dateApproved = $resource['date_approved']
            ?? $resource['dateApproved']
            ?? null;

        $leaseBegins = $resource['lease_begins']
            ?? $resource['leaseBegins']
            ?? null;

        $leaseExpires = $resource['lease_expires']
            ?? $resource['leaseExpires']
            ?? null;

        $metricSheet = $resource['metric_sheet']
            ?? $resource['metricSheet']
            ?? null;

        return [
            'id' => $this->resource['id'] ?? null,
            'prop_id' => $this->resource['prop_id'] ?? null,
            'mlsFNo' => $this->resource['mlsFNo'] ?? null,
            'kangisFileNo' => $this->resource['kangisFileNo'] ?? null,
            'NewKANGISFileno' => $this->resource['NewKANGISFileno'] ?? null,
            'temp_fileno' => $this->resource['temp_fileno'] ?? null,
            'transaction_type' => $this->resource['transaction_type'] ?? $this->resource['transactionType'] ?? null,
            'transaction_date' => $this->resource['transaction_date'] ?? $this->resource['transactionDate'] ?? null,
            'reg_date' => $this->resource['reg_date'] ?? $this->resource['regDate'] ?? null,
            'reg_time' => $this->resource['reg_time'] ?? $this->resource['regTime'] ?? null,
            'deeds_date' => $this->resource['deeds_date'] ?? $this->resource['deedsDate'] ?? null,
            'deeds_time' => $this->resource['deeds_time'] ?? $this->resource['deedsTime'] ?? null,
            'serialNo' => $this->resource['serialNo'] ?? $this->resource['serial_no'] ?? '0',
            'op_serial_number' => $this->resource['op_serial_number'] ?? null,
            'regNo' => $this->resource['regNo'] ?? $this->resource['reg_no'] ?? $this->resource['Prop_id'] ?? $this->resource['prop_id'] ?? null,
            'pageNo' => $this->resource['pageNo'] ?? $this->resource['page_no'] ?? '0',
            'volumeNo' => $this->resource['volumeNo'] ?? $this->resource['volume_no'] ?? '0',
            'land_use' => $this->resource['land_use'] ?? $this->resource['landUse'] ?? null,
            'source' => $this->resource['source'] ?? null,
            'purpose' => $this->resource['purpose'] ?? null,
            'location' => $this->resource['location'] ?? null,
            'property_description' => $this->resource['property_description'] ?? $this->resource['propertyDescription'] ?? null,
            'house_no' => $houseNo,
            'plot_no' => $plotNo,
            'street_name' => $streetName,
            'district' => $district,
            'lga' => $lga,
            'state' => $state,
            'related_file_number' => $relatedFileNumber,
            'layout' => $layout,
            'schedule' => $schedule,
            'tp_no' => $tpNo,
            'lpkn_no' => $lpknNo,
            'approved_plan_no' => $approvedPlanNo,
            'plot_size' => $plotSize,
            'date_recommended' => $dateRecommended,
            'date_approved' => $dateApproved,
            'lease_begins' => $leaseBegins,
            'lease_expires' => $leaseExpires,
            'metric_sheet' => $metricSheet,
            'master' => $this->resource['master'] ?? null,
            'parties' => [
                'assignor' => $this->resource['Assignor'] ?? $this->resource['assignor'] ?? null,
                'assignee' => $this->resource['Assignee'] ?? $this->resource['assignee'] ?? null,
                'mortgagor' => $this->resource['Mortgagor'] ?? $this->resource['mortgagor'] ?? null,
                'mortgagee' => $this->resource['Mortgagee'] ?? $this->resource['mortgagee'] ?? null,
                'grantor' => $this->resource['Grantor'] ?? $this->resource['grantor'] ?? null,
                'grantee' => $this->resource['Grantee'] ?? $this->resource['grantee'] ?? null,
                'lessor' => $this->resource['Lessor'] ?? $this->resource['lessor'] ?? null,
                'lessee' => $this->resource['Lessee'] ?? $this->resource['lessee'] ?? null,
                'surrenderor' => $this->resource['Surrenderor'] ?? $this->resource['surrenderor'] ?? null,
                'surrenderee' => $this->resource['Surrenderee'] ?? $this->resource['surrenderee'] ?? null,
                'surrenderor_2' => $this->resource['Surrenderor_2'] ?? $this->resource['surrenderor_2'] ?? null,
                'mortgagor_2' => $this->resource['Mortgagor_2'] ?? $this->resource['mortgagor_2'] ?? null,
                'party_1' => $this->resource['party_1'] ?? null,
                'party_2' => $this->resource['party_2'] ?? null,
                'party_3' => $this->resource['party_3'] ?? null,
            ],
            'created_at' => $this->resource['created_at'] ?? $this->resource['createdAt'] ?? null,
            'updated_at' => $this->resource['updated_at'] ?? $this->resource['updatedAt'] ?? null,
        ];
    }
}
