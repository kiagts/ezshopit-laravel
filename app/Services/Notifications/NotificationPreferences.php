<?php

namespace App\Services\Notifications;

use App\Enums\NotificationCategory;
use Illuminate\Support\Facades\DB;

class NotificationPreferences
{
    public function channels(object $user, string $type, bool $mail): array
    {
        $category = NotificationCategory::forType($type);
        $p = $category->required() ? null : DB::table('notification_preferences')->where('user_id', $user->id)->where('category', $category->value)->first();
        $channels = [];
        if ($category->required() || ($p->database_enabled ?? true)) {
            $channels[] = 'database';
        }
        if ($mail && filled($user->email ?? null) && ($category->required() || ($p->email_enabled ?? true))) {
            $channels[] = 'mail';
        }

        return $channels;
    }
}
