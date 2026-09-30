<?php

namespace App\Providers;

use App\Events\CommerceNotificationRequested;
use App\Events\DisputeActivity;
use App\Events\FinancialNotificationRequested;
use App\Events\ReturnStatusChanged;
use App\Notifications\Channels\IdempotentDatabaseChannel;
use App\Notifications\Channels\IdempotentMailChannel;
use App\Services\Notifications\CommerceNotificationListener;
use App\Services\Notifications\LifecycleNotificationListener;
use Illuminate\Notifications\ChannelManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class TransactionalNotificationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach ([ReturnStatusChanged::class => 'returning', DisputeActivity::class => 'dispute', FinancialNotificationRequested::class => 'financial'] as $event => $method) {
            Event::listen($event, function ($e) use ($method) {
                try {
                    app(LifecycleNotificationListener::class)->$method($e);
                } catch (\Throwable $error) {
                    \Log::error('Lifecycle notification failed.', ['event' => $e::class, 'exception' => $error::class]);
                }
            });
        }
        $manager = $this->app->make(ChannelManager::class);
        $manager->extend('database', fn ($app) => $app->make(IdempotentDatabaseChannel::class));
        $manager->extend('mail', fn ($app) => $app->make(IdempotentMailChannel::class));
        Event::listen(CommerceNotificationRequested::class, function ($e) {
            try {
                app(CommerceNotificationListener::class)->{$e->operation}(...$e->arguments);
            } catch (\Throwable $error) {
                \Log::error('Commerce notification listener failed.', ['operation' => $e->operation, 'exception' => $error::class]);
            }
        });
    }
}
