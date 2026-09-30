<section class="space-y-4"><h2 class="text-lg font-semibold">Transaction history</h2>
@forelse($transactions ?? [] as $transaction)
<article class="rounded border bg-white p-4">
<div class="flex flex-wrap justify-between gap-2"><strong>{{ ucfirst($transaction->type->value) }} · {{ App\Support\Money::format($transaction->amount, $transaction->currency_code) }}</strong><time>{{ $transaction->created_at->format('M j, Y H:i') }}</time></div>
<p class="mt-2 break-words">{{ $transaction->description }}</p>
@if($transaction->reference_type)<p class="mt-2 text-sm text-neutral-500">{{ ucfirst($transaction->reference_type) }} #{{ $transaction->reference_id }}</p>@endif
@if(auth()->user()->isAdmin())<p class="text-sm text-neutral-500">Actor ID: {{ $transaction->actor_id ?? 'System' }} · Entry #{{ $transaction->id }}</p>@endif
</article>
@empty<p class="rounded border p-5 text-neutral-500">No transactions in this currency yet.</p>@endforelse
@if($transactions){{ $transactions->links() }}@endif
</section>
