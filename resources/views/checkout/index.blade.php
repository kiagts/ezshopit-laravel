@extends('layouts.checkout')

@php
    use App\Enums\PaymentMethod;
    use App\Support\Money;

    $usesCardPayment = $selected_payment_method?->method === PaymentMethod::Card;
@endphp

@section('title', 'Checkout')

@section('content')
    <x-container class="py-8 sm:py-12">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <x-badge tone="brand">Checkout</x-badge>
                <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Checkout</h1>
                <p class="mt-2 text-sm leading-6 text-neutral-600">{{ number_format($item_quantity) }} {{ str('item')->plural($item_quantity) }} ready for delivery review.</p>
            </div>
            <x-button :href="route('cart.index')" variant="secondary">Edit cart</x-button>
        </div>

        <div class="mb-8 grid gap-3 sm:grid-cols-5">
            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-normal text-brand-800">Step 1</p>
                <p class="mt-1 font-semibold text-neutral-950">Delivery</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Step 2</p>
                <p class="mt-1 font-semibold text-neutral-950">Shipping</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Step 3</p>
                <p class="mt-1 font-semibold text-neutral-950">Payment</p>
            </div>
            <div class="rounded-lg border border-neutral-200 bg-white p-4">
                <p class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Step 4</p>
                <p class="mt-1 font-semibold text-neutral-950">Review</p>
            </div>
            <div class="rounded-lg border border-brand-200 bg-brand-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-normal text-brand-800">Step 5</p>
                <p class="mt-1 font-semibold text-neutral-950">Place Order</p>
            </div>
        </div>

        <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_22rem] lg:items-start">
            <div class="space-y-8">
                <section class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-neutral-950">Delivery Address</h2>
                            <p class="mt-2 text-sm leading-6 text-neutral-600">Where should we deliver your order?</p>
                        </div>
                        @if ($selectedAddress)
                            <p class="rounded-sm bg-emerald-50 px-2 py-1 text-xs font-medium text-emerald-700">Delivery address selected</p>
                        @endif
                    </div>

                    @if ($addresses->isEmpty())
                        <div class="mt-6">
                            @include('account.addresses._form', [
                                'action' => route('checkout.addresses.store'),
                                'countries' => $countries,
                                'submitLabel' => 'Save and use this address',
                            ])
                        </div>
                    @else
                        <form method="POST" action="{{ route('checkout.address.update') }}" class="mt-6 space-y-4">
                            @csrf
                            @method('PUT')
                            @foreach ($addresses as $address)
                                <x-account.address-card :address="$address" selectable :selected="$selectedAddress?->id === $address->id" />
                            @endforeach
                            @error('address_id')
                                <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                            @enderror
                            <x-button type="submit">Use selected address</x-button>
                        </form>

                        <details class="mt-6 rounded-lg border border-neutral-200 bg-neutral-50 p-5">
                            <summary class="cursor-pointer text-sm font-semibold text-neutral-950">Add another address</summary>
                            <div class="mt-5">
                                @include('account.addresses._form', [
                                    'action' => route('checkout.addresses.store'),
                                    'countries' => $countries,
                                    'submitLabel' => 'Save and use this address',
                                ])
                            </div>
                        </details>
                    @endif

                </section>

                <section class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
                    <div>
                        <h2 class="text-xl font-semibold text-neutral-950">Delivery Options</h2>
                        <p class="mt-2 text-sm leading-6 text-neutral-600">Choose one delivery method for each seller group.</p>
                    </div>

                    @if (! $selectedAddress)
                        <div class="mt-6 rounded-md border border-dashed border-neutral-300 bg-neutral-50 p-4 text-sm leading-6 text-neutral-600">
                            Select a delivery address to see available delivery options.
                        </div>
                    @else
                        <form method="POST" action="{{ route('checkout.shipping.update') }}" class="mt-6 space-y-5">
                            @csrf
                            @method('PUT')

                            @foreach ($shipping_groups as $shippingGroup)
                                <section class="rounded-lg border border-neutral-200">
                                    <div class="border-b border-neutral-100 px-4 py-3">
                                        <p class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Sold by</p>
                                        <p class="mt-1 font-semibold text-neutral-950">{{ $shippingGroup['vendor']->display_name }}</p>
                                    </div>

                                    @if ($shippingGroup['available_shipping_methods']->isEmpty())
                                        <div class="p-4">
                                            <p class="rounded-md bg-amber-50 p-3 text-sm leading-6 text-amber-900">{{ $shippingGroup['message'] }}</p>
                                            <div class="mt-3 flex flex-wrap gap-3">
                                                <x-button :href="route('account.addresses.index')" variant="secondary" size="sm">Change delivery address</x-button>
                                                <x-button :href="route('cart.index')" variant="ghost" size="sm">Edit cart</x-button>
                                            </div>
                                        </div>
                                    @else
                                        <div class="grid gap-3 p-4">
                                            @foreach ($shippingGroup['available_shipping_methods'] as $method)
                                                @php
                                                    $calculation = $shippingGroup['calculations'][$method->id];
                                                @endphp
                                                <label class="flex cursor-pointer gap-4 rounded-lg border p-4 transition {{ $shippingGroup['selected_shipping_method']?->id === $method->id ? 'border-brand-700 bg-brand-50 ring-2 ring-brand-100' : 'border-neutral-200 bg-white hover:border-neutral-300' }}">
                                                    <input
                                                        type="radio"
                                                        name="shipping_methods[{{ $shippingGroup['vendor']->id }}]"
                                                        value="{{ $method->id }}"
                                                        class="mt-1 h-4 w-4 border-neutral-300 text-brand-700 focus:ring-brand-600"
                                                        @checked($shippingGroup['selected_shipping_method']?->id === $method->id)
                                                    >
                                                    <span class="min-w-0 flex-1">
                                                        <span class="flex flex-wrap items-center justify-between gap-3">
                                                            <span class="font-semibold text-neutral-950">{{ $method->name }}</span>
                                                            <span class="font-semibold {{ $calculation['effective_rate'] === 0 ? 'text-emerald-700' : 'text-neutral-950' }}">
                                                                {{ $calculation['effective_rate'] === 0 ? 'Free' : Money::format($calculation['effective_rate']) }}
                                                            </span>
                                                        </span>
                                                        @if ($method->description)
                                                            <span class="mt-1 block text-sm leading-6 text-neutral-600">{{ $method->description }}</span>
                                                        @endif
                                                        @if ($calculation['delivery_estimate'])
                                                            <span class="mt-1 block text-sm text-neutral-500">{{ $calculation['delivery_estimate'] }}</span>
                                                        @endif
                                                    </span>
                                                </label>
                                            @endforeach
                                        </div>
                                    @endif

                                    @error('shipping_methods.'.$shippingGroup['vendor']->id)
                                        <p class="px-4 pb-4 text-sm font-medium text-red-700">{{ $message }}</p>
                                    @enderror
                                </section>
                            @endforeach

                            @error('shipping_methods')
                                <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                            @enderror

                            <button
                                type="submit"
                                class="inline-flex h-10 items-center justify-center gap-2 rounded-md border border-brand-700 bg-brand-700 px-4 text-sm font-medium text-white shadow-sm transition hover:bg-brand-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700 disabled:pointer-events-none disabled:opacity-50"
                                @disabled($has_shipping_blocking_issues)
                            >
                                Update Delivery Options
                            </button>
                        </form>
                    @endif
                </section>

                <section class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
                    <div>
                        <h2 class="text-xl font-semibold text-neutral-950">Payment Method</h2>
                        <p class="mt-2 text-sm leading-6 text-neutral-600">Choose how you will pay for this order.</p>
                    </div>

                    @if ($payment_methods->isEmpty())
                        <div class="mt-6 rounded-md border border-dashed border-neutral-300 bg-neutral-50 p-4 text-sm leading-6 text-neutral-600">
                            No payment methods are available right now.
                        </div>
                    @else
                        <form method="POST" action="{{ route('checkout.payment.update') }}" class="mt-6 space-y-4">
                            @csrf
                            @method('PUT')
                            <div class="grid gap-3">
                                @foreach ($payment_methods as $method)
                                    <label class="flex cursor-pointer gap-4 rounded-lg border p-4 transition {{ $selected_payment_method?->code() === $method->code() ? 'border-brand-700 bg-brand-50 ring-2 ring-brand-100' : 'border-neutral-200 bg-white hover:border-neutral-300' }}">
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value="{{ $method->code() }}"
                                            class="mt-1 h-4 w-4 border-neutral-300 text-brand-700 focus:ring-brand-600"
                                            @checked($selected_payment_method?->code() === $method->code())
                                        >
                                        <span>
                                            <span class="font-semibold text-neutral-950">{{ $method->label }}</span>
                                            @if ($method->description)
                                                <span class="mt-1 block text-sm leading-6 text-neutral-600">{{ $method->description }}</span>
                                            @endif
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                            @error('payment_method')
                                <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                            @enderror
                            <x-button type="submit" variant="secondary">Update Payment Method</x-button>
                        </form>
                    @endif
                </section>

                <section class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <h2 class="text-xl font-semibold text-neutral-950">Your Items</h2>
                            <p class="mt-2 text-sm leading-6 text-neutral-600">Review the exact variants currently in your cart.</p>
                        </div>
                        <x-button :href="route('cart.index')" variant="secondary" size="sm">Edit cart</x-button>
                    </div>

                    <div class="mt-6 space-y-5">
                        @foreach ($groups as $group)
                            <section class="rounded-lg border border-neutral-200">
                                <div class="border-b border-neutral-100 px-4 py-3">
                                    <p class="text-xs font-semibold uppercase tracking-normal text-neutral-500">Sold by</p>
                                    <p class="mt-1 font-semibold text-neutral-950">{{ $group['vendor']->display_name }}</p>
                                </div>
                                <div class="divide-y divide-neutral-100">
                                    @foreach ($group['items'] as $row)
                                        @php
                                            $product = $row['product'];
                                            $image = $row['image'];
                                        @endphp
                                        <article class="grid gap-4 p-4 sm:grid-cols-[5rem_1fr_auto] sm:items-start">
                                            <div class="h-20 w-20 overflow-hidden rounded-md bg-neutral-100">
                                                @if ($image)
                                                    <img src="{{ Storage::url($image->path) }}" alt="{{ $image->alt_text ?: $product->name }}" class="h-full w-full object-cover">
                                                @else
                                                    <x-storefront.image-placeholder :label="$product->name" class="h-20 w-20" />
                                                @endif
                                            </div>
                                            <div>
                                                <p class="font-semibold text-neutral-950">{{ $product->name }}</p>
                                                @if ($row['variant_label'])
                                                    <p class="mt-1 text-sm text-neutral-600">{{ $row['variant_label'] }}</p>
                                                @endif
                                                <p class="mt-1 text-xs text-neutral-500">SKU: {{ $row['variant']->sku }}</p>
                                                <p class="mt-2 text-sm text-neutral-600">Qty {{ $row['quantity'] }}</p>
                                            </div>
                                            <div class="sm:text-right">
                                                <p class="text-sm text-neutral-500">{{ $row['unit_price_formatted'] }} each</p>
                                                <p class="mt-1 font-semibold text-neutral-950">{{ $row['line_total_formatted'] }}</p>
                                            </div>
                                        </article>
                                    @endforeach
                                </div>
                                <div class="border-t border-neutral-100 px-4 py-3 text-sm text-neutral-600">
                                    Delivery for this seller group is calculated independently.
                                </div>
                            </section>
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="rounded-lg border border-neutral-200 bg-white p-6 shadow-soft lg:sticky lg:top-8">
                <h2 class="text-lg font-semibold text-neutral-950">Summary</h2>
                <div class="mt-5 space-y-4 text-sm">
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-neutral-600">Items subtotal</span>
                        <span class="font-semibold text-neutral-950">{{ $subtotal_formatted }}</span>
                    </div>
                    @if ($selectedAddress)
                        <div class="space-y-2 border-t border-neutral-100 pt-4">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-neutral-600">Shipping</span>
                                <span class="font-semibold text-neutral-950">{{ $shipping_total === 0 ? 'Free' : $shipping_total_formatted }}</span>
                            </div>
                            @foreach ($shipping_groups as $shippingGroup)
                                <div class="flex items-center justify-between gap-4 text-xs text-neutral-500">
                                    <span>{{ $shippingGroup['vendor']->display_name }}</span>
                                    <span>{{ $shippingGroup['shipping_charge_formatted'] ?? '-' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="flex items-center justify-between gap-4 border-t border-neutral-100 pt-4">
                            <span class="text-neutral-600">Shipping</span>
                            <span class="font-semibold text-neutral-500">--</span>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-4">
                        <span class="text-neutral-600">Current total</span>
                        <span class="text-lg font-semibold text-neutral-950">{{ $current_total_formatted }}</span>
                    </div>
                    <div class="flex items-start justify-between gap-4 border-t border-neutral-100 pt-4">
                        <span class="text-neutral-600">Payment</span>
                        <span class="text-right font-semibold text-neutral-950">{{ $selected_payment_method?->label ?? '-' }}</span>
                    </div>
                    <p class="rounded-md bg-neutral-50 p-4 leading-6 text-neutral-600">
                        @if ($usesCardPayment)
                            Card payment is collected securely by Stripe after the order is created and stock is reserved.
                        @else
                            Current total includes items and selected shipping only. Payment is collected on delivery.
                        @endif
                    </p>
                    @if ($checkoutReady)
                        <form
                            method="POST"
                            action="{{ route('checkout.place-order') }}"
                            class="space-y-3"
                            @if ($usesCardPayment)
                                data-stripe-checkout
                                data-stripe-key="{{ config('payments.stripe.public_key') }}"
                            @endif
                        >
                            @csrf
                            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">
                            <button
                                type="submit"
                                data-stripe-submit
                                class="inline-flex h-11 w-full items-center justify-center rounded-md border border-brand-700 bg-brand-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-brand-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-700"
                            >
                                {{ $usesCardPayment ? 'Place Order & Pay' : 'Place Order' }}
                            </button>
                            <div class="hidden space-y-3" data-stripe-panel>
                                <div class="rounded-md border border-neutral-200 bg-white p-3">
                                    <div data-stripe-payment-element></div>
                                </div>
                                <button
                                    type="button"
                                    data-stripe-confirm
                                    class="inline-flex h-11 w-full items-center justify-center rounded-md border border-neutral-950 bg-neutral-950 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-neutral-800 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-neutral-950"
                                >
                                    Complete Card Payment
                                </button>
                            </div>
                            <p class="text-xs leading-5 text-neutral-500" data-stripe-message>
                                @if ($usesCardPayment)
                                    Your card details are entered only in Stripe's secure payment element.
                                @else
                                    This creates a pending order and reserves stock for cash collection on delivery.
                                @endif
                            </p>
                        </form>
                    @elseif ($selectedAddress)
                        <p class="rounded-md bg-amber-50 p-3 text-amber-900">Resolve delivery and payment selections before placing your order.</p>
                    @endif
                    @foreach (['cart', 'address', 'shipping', 'payment_method', 'idempotency_key'] as $errorKey)
                        @error($errorKey)
                            <p class="text-sm font-medium text-red-700">{{ $message }}</p>
                        @enderror
                    @endforeach
                </div>
            </aside>
        </div>
    </x-container>
@endsection

@if ($usesCardPayment)
    @push('scripts')
        <script src="https://js.stripe.com/v3/"></script>
        <script>
            (() => {
                const form = document.querySelector('[data-stripe-checkout]');

                if (! form || ! window.Stripe) {
                    return;
                }

                const submit = form.querySelector('[data-stripe-submit]');
                const panel = form.querySelector('[data-stripe-panel]');
                const elementTarget = form.querySelector('[data-stripe-payment-element]');
                const confirmButton = form.querySelector('[data-stripe-confirm]');
                const message = form.querySelector('[data-stripe-message]');
                let stripe = null;
                let elements = null;

                const setMessage = (text, isError = false) => {
                    message.textContent = text;
                    message.classList.toggle('text-red-700', isError);
                    message.classList.toggle('text-neutral-500', ! isError);
                };

                form.addEventListener('submit', async (event) => {
                    event.preventDefault();

                    submit.disabled = true;
                    setMessage('Preparing secure card payment...');

                    try {
                        const response = await fetch(form.action, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                            },
                            body: new FormData(form),
                        });
                        const data = await response.json();

                        if (! response.ok || ! data.client_secret) {
                            throw new Error(data.message || 'Card payment could not be prepared. Open your order to retry.');
                        }

                        stripe = Stripe(data.publishable_key || form.dataset.stripeKey);
                        elements = stripe.elements({ clientSecret: data.client_secret });
                        elements.create('payment').mount(elementTarget);
                        panel.classList.remove('hidden');
                        submit.classList.add('hidden');
                        setMessage('Enter your card details securely, then complete payment.');

                        confirmButton.onclick = async () => {
                            confirmButton.disabled = true;
                            setMessage('Confirming payment with Stripe...');

                            const result = await stripe.confirmPayment({
                                elements,
                                confirmParams: { return_url: data.return_url },
                            });

                            if (result.error) {
                                confirmButton.disabled = false;
                                setMessage(result.error.message || 'Payment was not completed.', true);
                            }
                        };
                    } catch (error) {
                        submit.disabled = false;
                        setMessage(error.message || 'Card payment could not be prepared.', true);
                    }
                });
            })();
        </script>
    @endpush
@endif
