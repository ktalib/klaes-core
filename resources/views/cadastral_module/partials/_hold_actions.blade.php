{{--
    Hold / clear-hold for one receipt, as a small disclosure form in an actions
    cell. Include only when the hold columns exist (ReceiptHolds::available()).
    Placing needs a reason and clearing needs a remark; the controller refuses
    either without one, and audits both.
--}}
@php
    $closed = in_array($receipt->status, \App\Services\Cadastral\CadastralRegistryLookup::CLOSED_RECEIPT_STATUSES, true);
@endphp

@if (! $closed)
    @canDo('Cad - Records', 'edit')
        @if ($receipt->isOnHold())
            <details class="hold-action" style="display:inline-block;position:relative;">
                <summary title="Clear the hold" style="list-style:none;cursor:pointer;display:inline;">
                    <i class="fas fa-lock-open" style="color:var(--secondary-dark);"></i>
                </summary>
                <form method="POST" action="{{ route('cadastral-module.registry.receipts.mark-cleared', $receipt) }}"
                      style="position:absolute;right:0;z-index:20;background:#fff;border:1px solid var(--gray-300);border-radius:var(--radius-sm);padding:10px;width:280px;box-shadow:0 4px 12px rgba(0,0,0,.12);text-align:left;">
                    @csrf
                    <div style="font-size:12px;color:var(--gray-600);margin-bottom:6px;">
                        Held: {{ Str::limit($receipt->hold_reason, 120) }}
                    </div>
                    <textarea name="hold_clear_note" rows="3" required minlength="3" maxlength="1000"
                              placeholder="Why the hold can be cleared (required)"
                              style="width:100%;font-size:13px;padding:6px;border:1px solid var(--gray-300);border-radius:4px;"></textarea>
                    <button type="submit" class="btn btn-success btn-xs" style="margin-top:6px;">Clear hold</button>
                </form>
            </details>
        @else
            <details class="hold-action" style="display:inline-block;position:relative;">
                <summary title="Put on hold for investigation" style="list-style:none;cursor:pointer;display:inline;">
                    <i class="fas fa-hand" style="color:var(--danger);"></i>
                </summary>
                <form method="POST" action="{{ route('cadastral-module.registry.receipts.mark-held', $receipt) }}"
                      style="position:absolute;right:0;z-index:20;background:#fff;border:1px solid var(--gray-300);border-radius:var(--radius-sm);padding:10px;width:280px;box-shadow:0 4px 12px rgba(0,0,0,.12);text-align:left;">
                    @csrf
                    <textarea name="hold_reason" rows="3" required minlength="3" maxlength="1000"
                              placeholder="Reason for the hold (required)"
                              style="width:100%;font-size:13px;padding:6px;border:1px solid var(--gray-300);border-radius:4px;"></textarea>
                    <button type="submit" class="btn btn-danger btn-xs" style="margin-top:6px;">Put on hold</button>
                </form>
            </details>
        @endif
    @endcanDo
@endif
