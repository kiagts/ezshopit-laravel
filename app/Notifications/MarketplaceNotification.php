<?php

namespace App\Notifications;

use App\Enums\NotificationCategory;
use App\Services\Notifications\NotificationPreferences;
use App\Services\SettingsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

class MarketplaceNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function __construct(protected string $title, protected string $message, protected ?string $actionUrl, protected string $sourceKey, protected string $severity = 'info', protected bool $sendMail = false, protected ?string $mailSubject = null)
    {
        $this->afterCommit();
    }

    public static function eventType(): string
    {
        return 'system_update';
    }

    public function via(object $notifiable): array
    {
        return app(NotificationPreferences::class)->channels($notifiable, static::eventType(), $this->sendMail);
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => static::eventType(), 'category' => NotificationCategory::forType(static::eventType())->value, 'title' => $this->translatedTitle(), 'message' => __($this->message), 'action_url' => $this->safeActionUrl($this->actionUrl), 'action_label' => __('notifications.view_details'), 'severity' => in_array($this->severity, ['info', 'success', 'warning', 'action_required'], true) ? $this->severity : 'info', 'source_key' => $this->sourceKey];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $settings = app(SettingsService::class);
        $brand = $settings->get('general.marketplace_name', config('app.name'));
        $mail = (new MailMessage)->subject(app()->getLocale() === 'en' ? __($this->mailSubject ?: $this->title) : $this->translatedTitle())->greeting(__('notifications.hello', ['name' => $notifiable->name]))->line(__($this->message));
        if ($this->safeActionUrl($this->actionUrl)) {
            $mail->action(__('notifications.view_details'), url($this->actionUrl));
        }

        return $mail->line(__('notifications.thanks', ['name' => $brand]))->view(['html' => 'emails.transactional', 'text' => 'emails.transactional-text'], ['brand' => $brand, 'supportEmail' => $settings->get('contact.support_email')]);
    }

    public function deliverySnapshot(object $user): array
    {
        return ['class' => static::class, 'arguments' => [$this->title, $this->message, $this->actionUrl, $this->sourceKey, $this->severity, $this->sendMail, $this->mailSubject], 'locale' => $user->notification_locale ?: config('app.locale')];
    }

    private function translatedTitle(): string
    {
        $key = 'notifications.titles.'.static::eventType();

        return Lang::has($key) ? __($key) : __($this->title);
    }

    public function sourceKey(): string
    {
        return $this->sourceKey;
    }

    protected function safeActionUrl(?string $url): ?string
    {
        return is_string($url) && str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! preg_match('/[\\\\\r\n]/',$url) ? $url : null;
    }
}
