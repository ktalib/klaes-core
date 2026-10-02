<div>
    <div class="flex flex-col">
        <div class="overflow-x-auto sm:-mx-6 lg:-mx-8">
            <div class="inline-block min-w-full py-2 sm:px-6 lg:px-8">
                <div class="overflow-hidden border-b border-gray-200 shadow sm:rounded-lg">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    S/N
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Buyer Name
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Unit Number
                                </th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    Unit Measurement (sqm)
                                </th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            @php
                                // Get request parameters - handle both direct params and params embedded in 'url' parameter
                                $url = request()->query('url', '');
                                $unitNumber = request()->query('unit');
                                $unitId = request()->query('unit_id');
                                
                                // Handle case where parameters are embedded in the 'url' parameter
                                if (empty($unitNumber) && !empty($url)) {
                                    // Parse 'url=unit=5&unit_id=1' format
                                    parse_str($url, $urlParams);
                                    $unitNumber = $urlParams['unit'] ?? null;
                                    $unitId = $urlParams['unit_id'] ?? null;
                                }
                                
                                // Extract application ID from URL
                                $currentUrl = request()->path();
                                $applicationId = null;
                                
                                if (preg_match('/view_memo_primary\/(\d+)/', $currentUrl, $matches)) {
                                    $applicationId = $matches[1];
                                } else {
                                    $applicationId = $memo->application_id ?? null;
                                }
                                
                                $buyers = [];
                                
                                // If specific unit and unit_id are provided in URL
                                if (!empty($unitNumber) && !empty($unitId)) {
                                    // Get buyer information directly from subapplications using the unit_id
                                    $buyerInfo = DB::connection('sqlsrv')
                                        ->table('subapplications')
                                        ->where('id', $unitId)
                                        ->first();
                                    
                                    // Improved measurement query for specific unit
                                    $unitMeasurement = DB::connection('sqlsrv')
                                        ->table('st_unit_measurements')
                                        ->where('unit_no', $unitNumber)
                                        ->where('application_id', $applicationId)
                                        ->first();
                                    
                                    // Get measurement value
                                    $measurementValue = 'N/A';
                                    if ($unitMeasurement) {
                                        $measurementValue = $unitMeasurement->measurement ?? 'N/A';
                                    }
                                    
                                    if ($buyerInfo) {
                                        // Format buyer name based on applicant type
                                        $buyerName = 'N/A'; // Default value
                                        
                                        // First check if owner_name is available
                                        if (!empty($buyerInfo->owner_name)) {
                                            $buyerName = $buyerInfo->owner_name;
                                        }
                                        // Check applicant type
                                        elseif ($buyerInfo->applicant_type == 'individual') {
                                            $firstName = $buyerInfo->first_name ?? '';
                                            $surname = $buyerInfo->surname ?? '';
                                            $title = $buyerInfo->applicant_title ?? '';
                                            
                                            if (!empty($firstName) || !empty($surname)) {
                                                $buyerName = trim("$title $firstName $surname");
                                            }
                                        } 
                                        elseif ($buyerInfo->applicant_type == 'corporate') {
                                            if (!empty($buyerInfo->corporate_name)) {
                                                $buyerName = trim($buyerInfo->corporate_name);
                                                if (!empty($buyerInfo->rc_number)) {
                                                    $buyerName .= " (RC: {$buyerInfo->rc_number})";
                                                }
                                            }
                                        }
                                        elseif ($buyerInfo->applicant_type == 'multiple' && !empty($buyerInfo->multiple_owners_names)) {
                                            $owners = json_decode($buyerInfo->multiple_owners_names, true);
                                            if (is_array($owners) && count($owners) > 0) {
                                                $buyerName = implode(', ', $owners);
                                            } else {
                                                $buyerName = 'Multiple Owners';
                                            }
                                        }
                                        
                                        $buyers[] = [
                                            'name' => $buyerName,
                                            'unit' => $unitNumber,
                                            'measurement' => $measurementValue
                                        ];
                                    } else {
                                        $buyers[] = [
                                            'name' => "No buyer data found",
                                            'unit' => $unitNumber,
                                            'measurement' => $measurementValue
                                        ];
                                    }
                                } else {
                                    // No specific unit requested, get all buyers from buyer_list
                                    
                                    // Use DISTINCT to avoid duplicates and GROUP BY
                                    $buyersList = DB::connection('sqlsrv')
                                        ->table('buyer_list as bl')
                                        ->select(DB::raw('DISTINCT bl.buyer_title, bl.buyer_name, bl.unit_no, MAX(sum.measurement) as measurement'))
                                        ->leftJoin('st_unit_measurements as sum', function($join) use ($applicationId) {
                                            $join->on('bl.unit_no', '=', 'sum.unit_no')
                                                 ->where('sum.application_id', '=', $applicationId);
                                        })
                                        ->where('bl.application_id', $applicationId)
                                        // If there's a unit number but no unit_id, filter by unit number only
                                        ->when(!empty($unitNumber), function($query) use ($unitNumber) {
                                            return $query->where('bl.unit_no', $unitNumber);
                                        })
                                        ->groupBy('bl.buyer_title', 'bl.buyer_name', 'bl.unit_no')
                                        ->get();
                                    
                                    // Process distinct buyers
                                    $processedBuyers = [];
                                    if(count($buyersList) > 0) {
                                        foreach ($buyersList as $buyer) {
                                            $buyerName = $buyer->buyer_name ?? 'N/A';
                                            if (!empty($buyer->buyer_title)) {
                                                $buyerName = $buyer->buyer_title . ' ' . $buyerName;
                                            }
                                            
                                            $key = $buyerName . '-' . $buyer->unit_no;
                                            if (!isset($processedBuyers[$key])) {
                                                $processedBuyers[$key] = true; // Mark as processed
                                                $buyers[] = [
                                                    'name' => $buyerName,
                                                    'unit' => $buyer->unit_no ?? 'N/A',
                                                    'measurement' => $buyer->measurement ?? 'N/A'
                                                ];
                                            }
                                        }
                                    } else {
                                        // Fallback: Try a different approach to get buyers
                                        
                                        // Try with a simpler join
                                        $buyersList = DB::connection('sqlsrv')
                                            ->table('buyer_list as bl')
                                            ->leftJoin('st_unit_measurements as sum', 'bl.unit_measurement_id', '=', 'sum.id')
                                            ->where('sum.application_id', $applicationId)
                                            ->select('bl.buyer_title', 'bl.buyer_name', 'bl.unit_no', 'sum.measurement')
                                            ->get();
                                            
                                        if(count($buyersList) > 0) {
                                            foreach ($buyersList as $buyer) {
                                                $buyerName = $buyer->buyer_name ?? 'N/A';
                                                if (!empty($buyer->buyer_title)) {
                                                    $buyerName = $buyer->buyer_title . ' ' . $buyerName;
                                                }
                                                
                                                $buyers[] = [
                                                    'name' => $buyerName,
                                                    'unit' => $buyer->unit_no ?? 'N/A',
                                                    'measurement' => $buyer->measurement ?? 'N/A'
                                                ];
                                            }
                                        } else {
                                            // Fallback: If no buyers found in buyer_list, try alternative queries
                                            
                                            // Try querying directly from st_unit_measurements
                                            $unitMeasurements = DB::connection('sqlsrv')
                                                ->table('st_unit_measurements as sum')
                                                ->leftJoin('subapplications as sub', function($join) {
                                                    $join->on('sum.application_id', '=', 'sub.main_application_id')
                                                        ->on('sum.unit_no', '=', 'sub.unit_number');
                                                })
                                                ->where('sum.application_id', $applicationId)
                                                ->select('sum.unit_no', 'sum.measurement', 'sub.id', 
                                                    'sub.applicant_type', 'sub.applicant_title', 'sub.first_name', 
                                                    'sub.surname', 'sub.corporate_name', 'sub.rc_number', 
                                                    'sub.multiple_owners_names')
                                                ->get();
                                            
                                            // Process unit measurements results
                                            foreach ($unitMeasurements as $measurement) {
                                                // Format buyer name based on applicant type (if available)
                                                $buyerName = 'Owner Pending';
                                                
                                                if ($measurement->id) {
                                                    if ($measurement->applicant_type == 'individual') {
                                                        $buyerName = trim(($measurement->applicant_title ?? '') . ' ' . 
                                                            ($measurement->first_name ?? '') . ' ' . 
                                                            ($measurement->surname ?? ''));
                                                    } 
                                                    elseif ($measurement->applicant_type == 'corporate') {
                                                        $buyerName = trim(($measurement->corporate_name ?? '') . 
                                                            (($measurement->rc_number) ? ' (RC: ' . $measurement->rc_number . ')' : ''));
                                                    }
                                                    elseif ($measurement->applicant_type == 'multiple' && !empty($measurement->multiple_owners_names)) {
                                                        $owners = json_decode($measurement->multiple_owners_names);
                                                        $buyerName = is_array($owners) && count($owners) > 0 ? $owners[0] . ' et al.' : 'Multiple Owners';
                                                    }
                                                    else {
                                                        $buyerName = $measurement->owner_name ?? 'N/A';
                                                    }
                                                }
                                                
                                                $buyers[] = [
                                                    'name' => $buyerName,
                                                    'unit' => $measurement->unit_no ?? 'N/A',
                                                    'measurement' => $measurement->measurement ?? 'N/A'
                                                ];
                                            }
                                        }
                                    }
                                }
                                
                                // If still no buyers, show default
                                if (count($buyers) == 0) {
                                    $buyers[] = [
                                        'name' => 'No buyer information available',
                                        'unit' => 'N/A',
                                        'measurement' => 'N/A'
                                    ];
                                }
                            @endphp
                            
                            @foreach($buyers as $buyer)
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $loop->iteration }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $buyer['name'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $buyer['unit'] }}
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                        {{ $buyer['measurement'] }}
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>