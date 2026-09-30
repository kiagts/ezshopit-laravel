<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Notifications\MarketplaceNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdatePaymentStatus
{
    public function __construct(private readonly MarketplaceNotifier $notifier) {}

    public function handle(Payment $payment, PaymentStatus $status): Payment
    {
        $payment = DB::transaction(function () use ($payment, $status): Payment {
            $payment = Payment::query()->lockForUpdate()->find($payment->id);

            if (! $payment) {
                throw ValidationException::withMessages(['payment' => 'Payment could not be found.']);
            }

            if (! $this->canTransition($payment->status, $status)) {
                throw ValidationException::withMessages([
                    'status' => "Payment cannot move from {$payment->status->value} to {$status->value}.",
                ]);
            }

            $timestamps = match ($status) {
                PaymentStatus::Paid => ['paid_at' => now(), 'failed_at' => null, 'cancelled_at' => null],
                PaymentStatus::Failed => ['failed_at' => now()],
                PaymentStatus::Cancelled => ['cancelled_at' => now()],
                default => [],
            };

            $payment->forceFill(array_merge(['status' => $status], $timestamps))->save();
            $payment->order()->update(['payment_status' => $status]);

            return $payment->refresh();
        });

        $this->notifier->paymentChanged($payment, $status);

        return $payment;
    }

    private function canTransition(PaymentStatus $from, PaymentStatus $to): bool
    {
        if ($from === $to) {
            return true;
        }

        if ($from === PaymentStatus::Paid) {
            return false;
        }

        return match ($to) {
            PaymentStatus::Pending => false,
            PaymentStatus::Processing => $from === PaymentStatus::Pending,
            PaymentStatus::Paid, PaymentStatus::Failed => in_array($from, [PaymentStatus::Pending, PaymentStatus::Processing, PaymentStatus::Failed], true),
            PaymentStatus::Cancelled => in_array($from, [PaymentStatus::Pending, PaymentStatus::Failed], true),
            PaymentStatus::Refunded, PaymentStatus::PartiallyRefunded => false,
        };
    }
}
