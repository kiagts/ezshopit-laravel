<?php

namespace App\Services\Notifications;

use App\Notifications\MarketplaceNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SendUniqueNotification
{
    public function handle(object $notifiable, MarketplaceNotification $notification): void
    {
        DB::afterCommit(function () use ($notifiable, $notification) {
            try {
                $notification->locale($notifiable->notification_locale ?: config('app.locale', 'en'));
                $created = DB::table('notification_dispatches')->insertOrIgnore(['notifiable_type' => $notifiable->getMorphClass(), 'notifiable_id' => $notifiable->id, 'notification_type' => $notification::eventType(), 'source_key' => $notification->sourceKey(), 'payload' => json_encode($notification->deliverySnapshot($notifiable)), 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);
                if ($created !== 1) {
                    return;
                }
                $this->dispatch($notifiable, $notification);
            } catch (\Throwable $e) {
                Log::warning('Notification dispatch failed.', ['recipient_id' => $notifiable->id, 'type' => $notification::eventType(), 'exception' => $e::class]);
            }
        });
    }

    public function dispatch(object $user, MarketplaceNotification $notification): void
    {
        $query = DB::table('notification_dispatches')->where('notifiable_type', $user->getMorphClass())->where('notifiable_id', $user->id)->where('source_key', $notification->sourceKey());
        try {
            $user->notify($notification);
            $query->update(['status' => 'queued', 'last_error' => null, 'updated_at' => now()]);
        } catch (\Throwable $e) {
            $query->update(['status' => 'failed', 'last_error' => $e::class, 'updated_at' => now()]);
            Log::warning('Notification queue/delivery failed.', ['recipient_id' => $user->id, 'type' => $notification::eventType(), 'exception' => $e::class]);
        }
    }
}
