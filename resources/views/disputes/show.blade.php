@extends($layout)
@section('title',$dispute->dispute_number)
@section($section)
<a class="text-sm underline" href="{{ route($prefix.'.disputes.index') }}">All disputes</a>
<h1 class="mt-3 text-2xl font-semibold">{{ $dispute->dispute_number }}</h1>
<p class="mt-2">{{ $dispute->status->label() }} · {{ str_replace('_',' ',$dispute->reason) }}</p>
<p class="mt-2 text-sm">Order {{ $dispute->order->order_number }} · {{ $dispute->vendorOrder?->vendor?->display_name ?? 'Marketplace review' }}</p>
@if($errors->any())<div class="my-4 text-red-700" role="alert">{{ $errors->first() }}</div>@endif
@if(session('status'))<p class="my-3 text-green-700" role="status">{{ session('status') }}</p>@endif
<div class="mt-6 grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
<main class="min-w-0 space-y-5">
    @if($dispute->returnRequest)<p>Related return: <a class="underline" href="{{ route($prefix.'.returns.show',$dispute->returnRequest) }}">{{ $dispute->returnRequest->return_number }}</a> · {{ $dispute->returnRequest->status->label() }}</p>@endif
    @foreach(collect([$dispute->refund,$dispute->resolutionRefund])->filter()->unique('id') as $refund)
        <section class="rounded border bg-white p-4"><p class="font-medium">Refund {{ $refund->refund_number }} · {{ $refund->status->customerLabel() }}</p>
        @if($prefix==='vendor')<p>Seller impact: {{ \App\Support\Money::format((int)$refund->allocations->where('vendor_order_id',$dispute->vendor_order_id)->sum(fn($a)=>$a->seller_amount ?? $a->amount),$refund->currency) }}</p>
        @else<p>{{ \App\Support\Money::format($refund->amount,$refund->currency) }}</p>@endif
        @if($refund->status===\App\Enums\RefundStatus::PartiallyProcessed)<p class="text-sm">External funds are pending provider and have not been returned.</p>@endif
        @if($prefix==='admin')<a class="underline" href="{{ route('admin.refunds.show',$refund) }}">Review financial allocations</a>@endif
        </section>
    @endforeach
    @if($dispute->resolution)<section class="rounded border border-green-200 bg-white p-4"><h2 class="font-semibold">Marketplace decision</h2><p>{{ str_replace('_',' ',$dispute->resolution) }}</p><p class="mt-2 whitespace-pre-wrap break-words">{{ $dispute->resolution_note }}</p><p class="mt-2 text-xs">Recorded {{ $dispute->resolved_at?->format('M j, Y H:i') }}</p></section>@endif
    <h2 class="font-semibold">Conversation</h2>
    @foreach($messages as $message)
    <article class="rounded-lg border bg-white p-4"><p class="text-sm font-medium">{{ $message->author?->name ?? 'Former participant' }} · {{ $message->author_role ?? 'Participant' }} <time class="font-normal text-neutral-500">{{ $message->created_at->format('M j, Y H:i') }}</time></p><p class="mt-3 whitespace-pre-wrap break-words">{{ $message->body }}</p>
        @foreach($message->evidence as $evidence)<a class="mt-3 inline-block rounded border p-3 underline" href="{{ route($prefix.'.disputes.evidence',$evidence) }}">View evidence image {{ $loop->iteration }}</a>@endforeach
    </article>
    @endforeach
    {{ $messages->links() }}
    @can('message',$dispute)
    <form method="POST" enctype="multipart/form-data" action="{{ route($prefix.'.disputes.messages.store',$dispute) }}" class="space-y-3">@csrf
        <label class="block">Reply<textarea name="body" rows="4" required maxlength="5000" class="mt-1 block w-full rounded border p-3">{{ old('body') }}</textarea></label>
        <label class="block text-sm">Evidence (JPG, PNG, WebP; up to 5 MB each)<input class="mt-2 block max-w-full" type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple></label>
        <p class="text-xs">Messages and evidence are shared with all dispute participants.</p><x-button type="submit">Send reply</x-button>
    </form>
    @else<p class="rounded border p-4 text-sm">This thread is read-only.</p>@endcan
</main>
<aside class="min-w-0 space-y-5">
    <section class="rounded border bg-white p-4"><h2 class="font-semibold">Seller order</h2><p class="text-sm">{{ $dispute->vendorOrder?->vendor_order_number ?? 'Legacy order-level dispute' }}</p>@foreach($dispute->vendorOrder?->items ?? [] as $item)<p class="mt-2 break-words text-sm">{{ $item->product_name }} · {{ $item->quantity }}</p>@endforeach</section>
    @if($prefix==='admin')
        <p class="text-sm">Assigned to {{ $dispute->assignedAdmin?->name ?? 'Unassigned' }}</p>
        @if($dispute->status->active())<form method="POST" action="{{ route('admin.disputes.assign',$dispute) }}" class="space-y-2">@csrf<label class="block">Assign admin<select name="assigned_admin_id" class="block w-full rounded border p-2"><option value="">Unassigned</option>@foreach($admins as $admin)<option value="{{ $admin->id }}" @selected($dispute->assigned_admin_id===$admin->id)>{{ $admin->name }}</option>@endforeach</select></label><x-button type="submit" variant="secondary">Save assignment</x-button></form>@endif
        @foreach($targets as $target)<form method="POST" action="{{ route('admin.disputes.transition',$dispute) }}" class="rounded border p-3">@csrf<input type="hidden" name="target" value="{{ $target->value }}"><label class="block text-sm">Participant-visible explanation<textarea class="mt-1 block w-full rounded border p-2" name="note" maxlength="2000" @required(in_array($target,[\App\Enums\DisputeStatus::AwaitingCustomer,\App\Enums\DisputeStatus::AwaitingVendor],true))></textarea></label><x-button type="submit" variant="secondary" class="mt-2">{{ match($target){\App\Enums\DisputeStatus::UnderReview=>'Start review',\App\Enums\DisputeStatus::AwaitingCustomer=>'Request customer response',\App\Enums\DisputeStatus::AwaitingVendor=>'Request vendor response',default=>'Close dispute'} }}</x-button></form>@endforeach
        @if($dispute->status===\App\Enums\DisputeStatus::UnderReview)
        <form method="POST" action="{{ route('admin.disputes.resolve',$dispute) }}" class="space-y-3 rounded border p-4">@csrf<h2 class="font-semibold">Record decision</h2>
            <label class="block">Outcome<select class="block w-full rounded border p-2" name="resolution" required><option value="">Choose an outcome</option>@foreach(\App\Enums\DisputeResolution::cases() as $resolution)<option value="{{ $resolution->value }}">{{ $resolution->label() }}</option>@endforeach</select></label>
            <label class="block">Decision explanation<textarea class="block w-full rounded border p-2" name="note" required minlength="5" maxlength="2000"></textarea></label>
            <p class="text-xs">For a refund, enter quantities. Historical discounts, tax and funding caps apply. Linked returns must be inspected and awaiting refund. For other outcomes, leave financial fields empty.</p>
            @foreach($dispute->vendorOrder?->items ?? [] as $item)<label class="block text-sm">{{ $item->product_name }}<input type="hidden" name="items[{{ $loop->index }}][id]" value="{{ $item->id }}"><input class="block w-full rounded border p-2" type="number" min="0" max="{{ $item->quantity }}" name="items[{{ $loop->index }}][quantity]" placeholder="0"></label>@endforeach
            <label class="block text-sm">Shipping refund ({{ $dispute->order->currency }})<input class="block w-full rounded border p-2" name="shipping_amount" inputmode="decimal" placeholder="0.00"></label>
            @unless($dispute->return_request_id)<label class="block text-sm">Additional compensation ({{ $dispute->order->currency }})<input class="block w-full rounded border p-2" name="adjustment" inputmode="decimal" placeholder="0.00"></label>@endunless
            <x-button type="submit">Resolve dispute</x-button>
        </form>
        @endif
    @endif
    <section class="rounded border bg-white p-4"><h2 class="font-semibold">History</h2>@foreach($dispute->history as $entry)<div class="mt-3 border-t pt-3 text-sm"><p>{{ str_replace('_',' ',$entry->to_status) }} · {{ $entry->actor_role }}</p><p class="whitespace-pre-wrap break-words">{{ $entry->note }}</p><time class="text-xs">{{ $entry->created_at->format('M j, Y H:i') }}</time></div>@endforeach</section>
</aside>
</div>
@endsection
