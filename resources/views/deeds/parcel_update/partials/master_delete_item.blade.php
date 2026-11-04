{{--
    Master Delete row-menu item for the Parcel Update listings (Super Admin only).
    Takes approved records too; disabled once anything has been commissioned.
    Needs: $mdType (subdivision|merger|separation|extension|change-of-purpose|duplex),
           $mdRecord (the row's model), optional $mdClass for the listing's own menu style.
    The handler is in partials/master_delete_script.
--}}
@if(Auth::user() && Auth::user()->assign_role === 'Supper Admin' && (int) ($mdRecord->is_deleted ?? 0) !== 1)
    @php
        $mdBlocked = \App\Services\ParcelUpdateMasterDelete::commissionedReason($mdType, $mdRecord);
        $mdRef = (string) ($mdRecord->duplex_id ?? $mdRecord->file_no ?? $mdRecord->id);
        $mdBase = $mdClass ?? 'flex items-center w-full px-4 py-2.5 text-sm gap-2';
    @endphp
    @if($mdBlocked)
        <button type="button" disabled class="{{ $mdBase }} text-slate-400 cursor-not-allowed bg-slate-50/50"
                title="{{ $mdBlocked }} Master Delete is not allowed.">
            <i data-lucide="shield-x" class="w-4 h-4 text-slate-300"></i> Master Delete
        </button>
    @else
        <button type="button"
                onclick="event.stopPropagation(); parcelMasterDelete('{{ $mdType }}', {{ (int) $mdRecord->id }}, @js($mdRef), @js((string) $mdRecord->status))"
                class="{{ $mdBase }} text-red-700 hover:bg-red-50 font-semibold">
            <i data-lucide="shield-alert" class="w-4 h-4 text-red-600"></i> Master Delete
        </button>
    @endif
@endif
