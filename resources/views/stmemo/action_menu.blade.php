@php
    // Planning Recommendation must be 'Approved'
    $planningRecommendationApproved = strtolower($PrimaryApplication->planning_recommendation_status) === 'approved';
    // Director's Approval must be 'Approved'
    $directorApprovalApproved = strtolower($PrimaryApplication->application_status) === 'approved';
    // Only allow ST Memo generation if both are approved
    $canGenerateSTMemo = $planningRecommendationApproved && $directorApprovalApproved;
    
    // Check if recommended site plan sketch is uploaded
    $recommendedSitePlanUploaded = false;
    try {
        // Check if table exists first
        $tableExists = DB::connection('sqlsrv')->select("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME = 'recommended_site_plans'");
        
        if ($tableExists) {
            $recommendedSitePlan = DB::connection('sqlsrv')->table('recommended_site_plans')
                ->where('application_id', $PrimaryApplication->id)
                ->first();
            $recommendedSitePlanUploaded = $recommendedSitePlan ? true : false;
        }
    } catch (Exception $e) {
        // If there's an error, assume not uploaded
        $recommendedSitePlanUploaded = false;
    }
@endphp
<div class="relative inline-block text-left">
    <button class="flex items-center px-2 py-1 text-xs border border-gray-200 rounded-md bg-white hover:bg-gray-50"
        onclick="toggleDropdown(event)">
        <i data-lucide="more-horizontal" class="w-4 h-4"></i>
    </button>
    <div class="dropdown-menu hidden fixed w-48 bg-white shadow-lg rounded-md z-[9999]">
        <ul class="py-2">
            <li>
                <a href="{{ route('sectionaltitling.viewrecorddetail') }}?id={{ $PrimaryApplication->id }}"
                    class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                    <i data-lucide="eye" class="w-4 h-4 text-blue-500"></i>
                    View Application
                </a>
            </li>

                <!-- Approval -->
                <li>
                <a href="{{ route('actions.recommendation', ['id' => $PrimaryApplication->id]) }}?url=phy_planning"
                    class="flex items-center gap-2 px-4 py-2 text-sm {{ $approvalStatus ? 'text-gray-400 cursor-not-allowed bg-gray-50' : 'text-gray-700 hover:bg-gray-100' }}"
                    @if ($approvalStatus) tabindex="-1" aria-disabled="true" onclick="return false;" @endif>
                    <i data-lucide="check-circle"
                        class="w-4 h-4 {{ $approvalStatus ? 'text-gray-400' : 'text-green-500' }}"></i>
                        Planning Recommendation Approval
                </a>
            </li>
            <!-- Planning Recommendation -->
            <li>
                <a href="{{ route('actions.recommendation', ['id' => $PrimaryApplication->id]) }}?url=recommendation"
                    class="flex items-center gap-2 px-4 py-2 text-">
                    <i data-lucide="file-text" class="w-4 h-4 text-yellow-500"></i>
                     View Planning Recommendation Approval
                </a>
            </li>
            <!-- ST Memo -->
            @if(!(request()->query('url') == 'memo' && request()->has('view')))
               <li>
                <a href="javascript:void(0)" 
                   onclick="@if(!$recommendedSitePlanUploaded) openRecommendedSitePlanModal({{ $PrimaryApplication->id }}) @endif"
                    class="flex items-center gap-2 px-4 py-2 text-sm {{ $recommendedSitePlanUploaded ? 'text-gray-400 cursor-not-allowed bg-gray-50' : 'text-gray-700 hover:bg-gray-100' }}"
                    @if ($recommendedSitePlanUploaded) tabindex="-1" aria-disabled="true" @endif>
                    <i data-lucide="file-plus" class="w-4 h-4 {{ $recommendedSitePlanUploaded ? 'text-gray-400' : 'text-purple-500' }}"></i>
                    Upload Recommended Site Plan Sketch
                </a>
            </li>
            @endif
            <li>
                <a href="javascript:void(0)" 
                   onclick="@if($recommendedSitePlanUploaded) openViewRecommendedSitePlanModal({{ $PrimaryApplication->id }}) @endif"
                    class="flex items-center gap-2 px-4 py-2 text-sm {{ $recommendedSitePlanUploaded ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed bg-gray-50' }}"
                    @if (!$recommendedSitePlanUploaded) tabindex="-1" aria-disabled="true" @endif>
                    <i data-lucide="eye" class="w-4 h-4 {{ $recommendedSitePlanUploaded ? 'text-green-500' : 'text-gray-400' }}"></i>
                    View Recommended Site Plan Sketch
                </a>
            </li>
            @if(request()->query('url') != 'view')
            <li>
                <a href="javascript:void(0)"
                    onclick="@if($canGenerateSTMemo && !$stMemoGenerated) generateSTMemo({{ $PrimaryApplication->id }}) @endif"
                    class="flex items-center gap-2 px-4 py-2 text-sm
                        @if(!$canGenerateSTMemo || $stMemoGenerated)
                            text-gray-400 cursor-not-allowed bg-gray-50
                        @else
                            text-gray-700 hover:bg-gray-100
                        @endif"
                    @if(!$canGenerateSTMemo || $stMemoGenerated) tabindex="-1" aria-disabled="true" onclick="return false;" @endif>
                    <i data-lucide="check"
                        class="w-4 h-4
                            @if(!$canGenerateSTMemo || $stMemoGenerated)
                                text-gray-400
                            @else
                                text-red-500
                            @endif"></i>
                    Generate Physical Planning Memo
                </a>
            </li>
            @endif
            <li>
                <a href="{{ route('stmemo.view', $PrimaryApplication->id) }}"
                    class="flex items-center gap-2 px-4 py-2 text-sm {{ $stMemoGenerated ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed bg-gray-50' }}"
                    @if (!$stMemoGenerated) tabindex="-1" aria-disabled="true" onclick="return false;" @endif>
                    <i data-lucide="eye" class="w-4 h-4 {{ $stMemoGenerated ? 'text-blue-500' : 'text-gray-400' }}"></i>
                    View Physical Planning Memo
                </a>
            </li>
            <!-- Site Plan -->
            {{-- <li>
                <a href="{{ route('stmemo.uploadSitePlan', $PrimaryApplication->id) }}"
                    class="flex items-center gap-2 px-4 py-2 text-sm {{ $sitePlanUploaded ? 'text-gray-400 cursor-not-allowed bg-gray-50' : 'text-gray-700 hover:bg-gray-100' }}"
                    @if ($sitePlanUploaded) tabindex="-1" aria-disabled="true" onclick="return false;" @endif>
                    <i data-lucide="upload"
                        class="w-4 h-4 {{ $sitePlanUploaded ? 'text-gray-400' : 'text-green-500' }}"></i>
                    Upload Site Plan
                </a>
            </li> --}}
            {{-- <li>
                <a href="{{ route('stmemo.viewSitePlan', $PrimaryApplication->id) }}"
                    class="flex items-center gap-2 px-4 py-2 text-sm {{ $sitePlanUploaded ? 'text-gray-700 hover:bg-gray-100' : 'text-gray-400 cursor-not-allowed bg-gray-50' }}"
                    @if (!$sitePlanUploaded) tabindex="-1" aria-disabled="true" onclick="return false;" @endif>
                    <i data-lucide="eye" class="w-4 h-4 {{ $sitePlanUploaded ? 'text-blue-500' : 'text-gray-400' }}"></i>
                    View
                </a>
            </li> --}}
            <!-- Recommended Site Plan Sketch -->
         
        
        </ul>
    </div>
</div>
<script>
    function toggleDropdown(event) {
        event.stopPropagation();
        const button = event.currentTarget;
        const dropdownMenu = button.nextElementSibling;

        // Hide all other dropdowns first
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            if (menu !== dropdownMenu) menu.classList.add('hidden');
        });

        // Toggle visibility of this dropdown
        const isHidden = dropdownMenu.classList.contains('hidden');
        
        if (isHidden) {
            // Show dropdown
            dropdownMenu.classList.remove('hidden');
            
            // Position dropdown relative to button
            const buttonRect = button.getBoundingClientRect();
            const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
            const scrollLeft = window.pageXOffset || document.documentElement.scrollLeft;
            
            // Start with positioning to the right of button
            let left = buttonRect.left;
            let top = buttonRect.bottom + scrollTop;
            
            // Get viewport and dropdown dimensions
            const viewportWidth = window.innerWidth;
            const viewportHeight = window.innerHeight;
            const dropdownWidth = dropdownMenu.offsetWidth;
            const dropdownHeight = dropdownMenu.offsetHeight;
            
            // Adjust horizontal position if dropdown would go off right edge
            if (left + dropdownWidth > viewportWidth - 10) {
                left = Math.max(10, buttonRect.right - dropdownWidth);
            }
            
            // Adjust vertical position if dropdown would go off bottom edge
            if (top + dropdownHeight > scrollTop + viewportHeight - 10) {
                top = buttonRect.top + scrollTop - dropdownHeight;
            }
            
            // Apply calculated position
            dropdownMenu.style.left = `${left}px`;
            dropdownMenu.style.top = `${top}px`;
        } else {
            // Hide dropdown
            dropdownMenu.classList.add('hidden');
        }
    }

    // Close all dropdowns when clicking outside
    document.addEventListener('click', () => {
        document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.add('hidden'));
    });
    
    // Close dropdowns on window resize (responsive behavior)
    window.addEventListener('resize', () => {
        document.querySelectorAll('.dropdown-menu').forEach(menu => menu.classList.add('hidden'));
    });
    
    // Prevent clicks inside dropdown from closing it
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
            menu.addEventListener('click', (e) => {
                if (e.target.tagName !== 'A') {
                    e.stopPropagation();
                }
            });
        });
    });
</script>
