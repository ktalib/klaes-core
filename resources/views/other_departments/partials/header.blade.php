<div
     class="flex flex-col md:flex-row items-center justify-between mb-6 bg-gray-50 border border-gray-200 rounded-lg p-4 shadow-sm">
     <div class="flex-1 mb-4 md:mb-0">
         <h3 class="text-base font-semibold text-gray-800 flex items-center gap-2">
             <i data-lucide="home" class="w-5 h-5 text-blue-500"></i>
             {{ $application->land_use }} Property
         </h3>
         <div class="flex flex-wrap gap-2 mt-2 text-xs text-gray-500">
             <span class="inline-flex items-center gap-1">
                 <i data-lucide="hash" class="w-4 h-4"></i>
                 <span class="font-medium text-gray-700">
                     {{ isset($isSecondary) && $isSecondary ? 'Mother FileNo: ' . ($application->primary_fileno ?? 'N/A') : '' }}
                 </span>
             </span>
             <span class="inline-flex items-center gap-1">
                 <i data-lucide="folder" class="w-4 h-4"></i>
                 <span class="font-medium text-gray-700">

                     {{ isset($isSecondary) && $isSecondary ? 'ST FileNo: ' . ($application->fileno ?? 'N/A') : 'FileNo: ' . ($application->fileno ?? 'N/A') }}
                 </span>
             </span>

                    @if(request()->has('is') && request('is') === 'secondary')
               
                            <!-- Assignment Reg Particulars -->
                           
                                    @php
                                    $assignment = \DB::connection('sqlsrv')
                                        ->table('Sectional_title_transfer')
                                        ->where('application_id', $application->main_application_id)
                                        ->select('serial_no', 'page_no', 'volume_no')
                                        ->first();
                                    @endphp
                                   
       
                    @endif
         </div>
       </div>
     <div class="flex-1 text-right">
         <h3 class="text-base font-semibold text-gray-800">
             @if ($application->applicant_type == 'individual')
                 {{ $application->applicant_title }} {{ $application->first_name }} {{ $application->surname }}
             @elseif($application->applicant_type == 'corporate')
                 {{ $application->rc_number }} {{ $application->corporate_name }}
             @elseif($application->applicant_type == 'multiple')
                 @php
                     $ownerNames = json_decode($application->multiple_owners_names, true);
                     if(is_array($ownerNames) && count($ownerNames) > 0) {
                         if(count($ownerNames) === 1) {
                             echo $ownerNames[0];
                         } else {
                             echo '<span onclick="showAllOwners('.htmlspecialchars(json_encode($ownerNames), ENT_QUOTES, 'UTF-8'). 
                                  ')" class="cursor-pointer text-blue-600 hover:underline">'.$ownerNames[0].' + '.(count($ownerNames) - 1).' others</span>';
                         }
                     } else {
                         echo 'Multiple Owners';
                     }
                 @endphp
             @endif
         </h3>
         <span
             class="inline-flex items-center px-3 py-1 mt-2 rounded-full text-xs font-semibold bg-green-100 text-green-700 border border-green-200">
             <i data-lucide="map-pin" class="w-4 h-4 mr-1"></i>
             {{ $application->land_use }}
         </span>
     </div>
 </div>

 <script>
     function showAllOwners(owners) {
         let ownersList = '';
         owners.forEach((owner, index) => {
             ownersList += `<div class="py-2 px-4 ${index % 2 === 0 ? 'bg-gray-50' : 'bg-white'} rounded">
                              <div class="flex items-center">
                                  <span class="font-medium text-gray-700">${index + 1}.</span>
                                  <span class="ml-2">${owner}</span>
                              </div>
                           </div>`;
         });

         Swal.fire({
             title: 'All Property Owners',
             html: `<div class="max-h-60 overflow-y-auto mt-4 divide-y divide-gray-200">${ownersList}</div>`,
             width: '500px',
             showCloseButton: true,
             showConfirmButton: false,
             focusConfirm: false
         });
     }
 </script>
