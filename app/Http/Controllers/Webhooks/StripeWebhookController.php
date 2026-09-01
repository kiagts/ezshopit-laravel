<?php

namespace App\Http\Controllers\Webhooks;

use App\Actions\Payments\HandleStripeWebhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, HandleStripeWebhook $handler): JsonResponse
    {
        $secret = config('payments.stripe.webhook_secret');

        abort_if(blank($secret), 400);

        try {
            $event = Webhook::constructEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
                (string) $secret,
            );
        } catch (UnexpectedValueException|SignatureVerificationException) {
            abort(400);
        }

        $handler->handle($event);

        return response()->json(['received' => true]);
    }
}
