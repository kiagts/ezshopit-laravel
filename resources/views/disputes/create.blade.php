@extends($layout)
@section('title','Open a dispute')
@section($section)
<h1 class="text-2xl font-semibold">Open a dispute</h1><p class="mt-2">Order {{ $order->order_number }}</p>
@if($errors->any())<div role="alert" class="my-4 text-red-700">{{ $errors->first() }}</div>@endif
<form method="POST" enctype="multipart/form-data" action="{{ route('account.orders.dispute.store',$order) }}" class="mt-6 max-w-2xl space-y-4">@csrf
    <label class="block">Seller order<select class="mt-1 block w-full rounded border p-3" name="vendor_order_id" required>@foreach($order->vendorOrders as $vo)<option value="{{ $vo->id }}" @selected((string)old('vendor_order_id')===(string)$vo->id)>{{ $vo->vendor->display_name }} · {{ $vo->vendor_order_number }}</option>@endforeach</select></label>
    <label class="block">Related return (optional)<select class="mt-1 block w-full rounded border p-3" name="return_request_id"><option value="">None</option>@foreach($returns as $return)<option value="{{ $return->id }}" @selected((string)old('return_request_id')===(string)$return->id)>{{ $return->return_number }}</option>@endforeach</select></label>
    <label class="block">Related refund (optional)<select class="mt-1 block w-full rounded border p-3" name="refund_id"><option value="">None</option>@foreach($refunds as $refund)<option value="{{ $refund->id }}" @selected((string)old('refund_id')===(string)$refund->id)>{{ $refund->refund_number }}</option>@endforeach</select></label>
    <label class="block">Reason<select class="mt-1 block w-full rounded border p-3" name="reason" required>@foreach(\App\Enums\DisputeReason::cases() as $reason)<option value="{{ $reason->value }}" @selected(old('reason')===$reason->value)>{{ $reason->label() }}</option>@endforeach</select></label>
    <label class="block">Describe the issue<textarea name="description" required minlength="5" maxlength="5000" rows="5" class="mt-1 block w-full rounded border p-3">{{ old('description') }}</textarea></label>
    <label class="block">Evidence images<input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple class="mt-1 block w-full"></label><p class="text-sm text-neutral-600">Up to {{ config('disputes.evidence_limit',5) }} JPG, PNG or WebP images, 5 MB each. Shared only with you, the associated seller and marketplace administrators.</p>
    <x-button type="submit">Open dispute</x-button>
</form>
@endsection
