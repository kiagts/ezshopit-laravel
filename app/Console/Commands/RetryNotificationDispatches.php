<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\MarketplaceNotification;
use App\Services\Notifications\SendUniqueNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetryNotificationDispatches extends Command
{
    protected $signature = 'notifications:retry {--limit=100}';

    protected $description = 'Requeue pending/failed notification dispatches; sent channels are skipped safely';

    public function handle(): int
    {
        DB::table('notification_dispatches')->whereIn('status', ['pending', 'failed'])->whereNotNull('payload')->orderBy('id')->limit(max(1, min(1000, (int) $this->option('limit'))))->get()->each(function ($row) {
            $user = User::find($row->notifiable_id);
            $p = json_decode($row->payload, true);
            $class = $p['class'] ?? '';
            if (! $user || $row->notifiable_type !== $user->getMorphClass() || ! is_subclass_of($class, MarketplaceNotification::class)) {
                return;
            }
            $notification = new $class(...$p['arguments']);
            $notification->locale($p['locale'] ?? config('app.locale'));
            app(SendUniqueNotification::class)->dispatch($user, $notification);
        });
        $this->info('Eligible dispatches retried.');

        return self::SUCCESS;
    }
}
