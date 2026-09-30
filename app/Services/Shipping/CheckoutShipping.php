<?php

namespace App\Services\Shipping;

use App\Models\CustomerAddress;
use App\Models\ShippingMethod;
use App\Services\Checkout\CheckoutSession;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class CheckoutShipping
{
    public function __construct(
        private readonly CheckoutSession $checkoutSession,
        private readonly ShippingCalculator $shippingCalculator,
    ) {
    }

    /**
     * @param array<string, mixed> $cartEvaluation
     * @return array<string, mixed>
     */
    public function build(Request $request, array $cartEvaluation, ?CustomerAddress $address): array
    {
        $groups = $cartEvaluation['groups'];
        $vendorIds = $groups->pluck('vendor.id')->filter()->map(fn ($id): int => (int) $id)->values();

        if (! $address) {
            $this->checkoutSession->syncShippingMethods($request, $vendorIds->all(), []);

            return [
                'shipping_groups' => $groups->map(fn (array $group): array => $this->emptyGroup($group, 'Select a delivery address to see available delivery options.')),
                'shipping_total' => 0,
                'shipping_total_formatted' => Money::format(0),
                'current_total' => $cartEvaluation['total_after_discount'],
                'current_total_formatted' => Money::format($cartEvaluation['total_after_discount']),
                'shipping_ready' => false,
                'has_shipping_blocking_issues' => true,
            ];
        }

        $methodsByVendor = $this->methodsForVendors($vendorIds->all())
            ->groupBy('vendor_id');
        $existingSelections = $this->checkoutSession->shippingMethods($request);
        $resolvedSelections = [];
        $shippingTotal = 0;

        $shippingGroups = $groups->map(function (array $group) use ($address, $methodsByVendor, $existingSelections, &$resolvedSelections, &$shippingTotal): array {
            $vendor = $group['vendor'];
            $availableMethods = $methodsByVendor
                ->get($vendor->id, collect())
                ->filter(fn (ShippingMethod $method): bool => $method->supportsCountry($address->country_code))
                ->values();

            $selectedMethod = $availableMethods->firstWhere('id', (int) ($existingSelections[$vendor->id] ?? 0))
                ?: $availableMethods->first();

            $calculations = $availableMethods->mapWithKeys(fn (ShippingMethod $method): array => [
                $method->id => $this->shippingCalculator->calculate($method, $group['subtotal']),
            ]);

            $selectedCalculation = $selectedMethod ? $calculations[$selectedMethod->id] : null;
            $shippingTotal += (int) ($selectedCalculation['effective_rate'] ?? 0);

            if ($selectedMethod) {
                $resolvedSelections[$vendor->id] = $selectedMethod->id;
            }

            return [
                'vendor' => $vendor,
                'items' => $group['items'],
                'vendor_subtotal' => $group['subtotal'],
                'vendor_subtotal_formatted' => Money::format($group['subtotal']),
                'available_shipping_methods' => $availableMethods,
                'selected_shipping_method' => $selectedMethod,
                'calculations' => $calculations,
                'shipping_charge' => (int) ($selectedCalculation['effective_rate'] ?? 0),
                'shipping_charge_formatted' => $selectedCalculation ? $this->formatCharge((int) $selectedCalculation['effective_rate']) : null,
                'shipping_blocking_issue' => ! $selectedMethod,
                'message' => $selectedMethod ? null : "Delivery isn't available from this seller to your selected country.",
            ];
        });

        $this->checkoutSession->syncShippingMethods($request, $vendorIds->all(), $resolvedSelections);

        $hasIssues = $shippingGroups->contains(fn (array $group): bool => $group['shipping_blocking_issue']);
        $currentTotal = $cartEvaluation['total_after_discount'] + $shippingTotal;

        return [
            'shipping_groups' => $shippingGroups,
            'shipping_total' => $shippingTotal,
            'shipping_total_formatted' => Money::format($shippingTotal),
            'current_total' => $currentTotal,
            'current_total_formatted' => Money::format($currentTotal),
            'shipping_ready' => ! $hasIssues,
            'has_shipping_blocking_issues' => $hasIssues,
        ];
    }

    /**
     * @param array<int, int> $vendorIds
     */
    public function methodsForVendors(array $vendorIds): Collection
    {
        if ($vendorIds === []) {
            return collect();
        }

        return ShippingMethod::query()
            ->with('countries')
            ->whereIn('vendor_id', $vendorIds)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    public function formatCharge(int $amount): string
    {
        return $amount === 0 ? 'Free' : Money::format($amount);
    }

    /**
     * @param array<string, mixed> $group
     * @return array<string, mixed>
     */
    private function emptyGroup(array $group, string $message): array
    {
        return [
            'vendor' => $group['vendor'],
            'items' => $group['items'],
            'vendor_subtotal' => $group['subtotal'],
            'vendor_subtotal_formatted' => Money::format($group['subtotal']),
            'available_shipping_methods' => collect(),
            'selected_shipping_method' => null,
            'calculations' => collect(),
            'shipping_charge' => 0,
            'shipping_charge_formatted' => null,
            'shipping_blocking_issue' => true,
            'message' => $message,
        ];
    }
}
