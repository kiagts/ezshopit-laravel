<?php

namespace App\Notifications\Channels;

use App\Notifications\MarketplaceNotification;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class IdempotentMailChannel extends MailChannel
{
    public function send($notifiable, Notification $notification)
    {
        if (! $notification instanceof MarketplaceNotification) {
            return parent::send($notifiable, $notification);
        }
        try {
            return DB::transaction(function () use ($notifiable, $notification) {
                $d = DB::table('notification_dispatches')->where('notifiable_id', $notifiable->id)->where('notifiable_type', $notifiable->getMorphClass())->where('source_key', $notification->sourceKey())->lockForUpdate()->first();
                if ($d?->mail_sent_at) {
                    return null;
                }
                $sent = parent::send($notifiable, $notification);
                if ($d) {
                    DB::table('notification_dispatches')->where('id', $d->id)->update(['mail_sent_at' => now(), 'last_error' => null]);
                }

                return $sent;
            });
        } catch (\Throwable $e) {
            DB::table('notification_dispatches')->where('notifiable_id', $notifiable->id)->where('source_key', $notification->sourceKey())->update(['status' => 'failed', 'last_error' => $e::class]);
            throw $e;
        }
    }
}
