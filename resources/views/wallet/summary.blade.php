<form method="GET" class="flex items-end gap-3">
<label>Currency<select name="currency" class="mt-1 block rounded border p-3">@foreach($currencies as $code)<option value="{{ $code }}" @selected($code === $currency)>{{ $code }}</option>@endforeach</select></label>
<button class="min-h-11 rounded border px-4">View wallet</button>
</form>
<div class="rounded-xl border bg-white p-6">
<p class="text-neutral-500">Store credit balance</p>
<p class="mt-2 text-3xl font-semibold">{{ App\Support\Money::format($balance, $currency) }}</p>
<p class="mt-3">Status: {{ $wallet?->status->value ?? 'Not opened' }}</p>
<p class="mt-2 text-sm text-neutral-500">Each currency has an independent balance. Credit is never converted between wallets.</p>
</div>
