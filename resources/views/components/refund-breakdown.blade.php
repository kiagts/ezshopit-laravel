@props(['refund'])
<section class="my-4 rounded-lg border p-4">
    <h3 class="font-semibold">{{ $refund->refund_number }} · {{ $refund->status->customerLabel() }}</h3>
    <dl class="mt-3 grid grid-cols-2 gap-2 text-sm">
        <dt>Merchandise</dt><dd>{{ \App\Support\Money::format((int)$refund->allocations->sum('merchandise_amount'),$refund->currency) }}</dd>
        <dt>Tax</dt><dd>{{ \App\Support\Money::format((int)$refund->allocations->sum('tax_amount'),$refund->currency) }}</dd>
        <dt>Shipping</dt><dd>{{ \App\Support\Money::format((int)$refund->allocations->filter(fn($a)=>$a->type->value === 'shipping')->sum('amount'),$refund->currency) }}</dd>
        <dt>Adjustment</dt><dd>{{ \App\Support\Money::format((int)$refund->allocations->filter(fn($a)=>$a->type->value === 'adjustment')->sum('amount'),$refund->currency) }}</dd>
        <dt>Total</dt><dd>{{ \App\Support\Money::format($refund->amount,$refund->currency) }}</dd>
    </dl>
    @foreach($refund->fundingAllocations as $funding)
    <p class="mt-2 text-sm">{{ $funding->source_type->label() }}: {{ \App\Support\Money::format($funding->amount,$refund->currency) }} —
        {{ $funding->status === \App\Enums\RefundFundingStatus::Completed ? 'Restored' : 'Pending' }}
    </p>
    @endforeach
    @if($refund->status === \App\Enums\RefundStatus::PartiallyProcessed)<p class="mt-3 text-sm">The original payment method portion is awaiting a provider. It has not been returned yet.</p>@endif
</section>

