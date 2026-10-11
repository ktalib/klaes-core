{{-- "Created By" for a parcel-update row: the creator's name, opening the shared
     profile card (components/user-profile-card, js/user-profile-card.js) on click.
     Params: userId (captured_by), optional name when the page already resolved it.
     Names are looked up once per user per request. --}}
@php
    $cardUserId = (int) ($userId ?? 0);
    $cardName = trim((string) ($name ?? ''));
    if ($cardName === '' && $cardUserId > 0) {
        $cardName = \Illuminate\Support\Facades\Cache::store('array')->rememberForever(
            'pu-created-by-' . $cardUserId,
            function () use ($cardUserId) {
                $u = \App\Models\User::find($cardUserId, ['id', 'first_name', 'last_name']);
                return $u ? trim(($u->first_name ?? '') . ' ' . ($u->last_name ?? '')) : '';
            }
        );
    }
@endphp
@if($cardUserId > 0 && $cardName !== '')
    <span class="upc-trigger" data-user-card data-user-id="{{ $cardUserId }}" title="{{ __('View profile') }}">{{ \App\Support\PersonName::display($cardName) }}</span>
@else
    <span class="text-slate-400">&mdash;</span>
@endif
