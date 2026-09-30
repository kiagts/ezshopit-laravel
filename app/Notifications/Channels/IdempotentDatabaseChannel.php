<?php

namespace App\Notifications\Channels;

use App\Notifications\MarketplaceNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class IdempotentDatabaseChannel extends DatabaseChannel
{
    public function send($notifiable, Notification $notification)
    {
        if (! $notification instanceof MarketplaceNotification) {
            return parent::send($notifiable, $notification);
        }
        DB::transaction(function () use ($notifiable, $notification) {
            $d = DB::table('notification_dispatches')->where('notifiable_id', $notifiable->id)->where('notifiable_type', $notifiable->getMorphClass())->where('source_key', $notification->sourceKey())->lockForUpdate()->first();
            if ($d?->database_sent_at) {
                return;
            }
            parent::send($notifiable, $notification);
            if ($d) {
                DB::table('notification_dispatches')->where('id', $d->id)->update(['database_sent_at' => now(), 'last_error' => null]);
            }
        });
    }
}
