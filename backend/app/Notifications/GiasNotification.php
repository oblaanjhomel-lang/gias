<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Every GIAS notification: an in-app row (database channel) plus an email when the recipient
 * wants email (users.email_notifications) or the notification is mandatory. Queued on
 * the "notifications" queue.
 *
 * Only plain strings/arrays are stored, so queued payloads stay small and stable.
 */
abstract class GiasNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const CATEGORIES = ['approval', 'deadline', 'deployment', 'evaluation', 'system'];

    /**
     * @param  string  $category  approval | deadline | deployment | evaluation | system
     * @param  string  $message  one plain sentence
     * @param  string  $actionPath  frontend path, e.g. /student/deployment
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $category,
        public readonly string $message,
        public readonly string $actionPath,
        public readonly string $actionLabel,
        public readonly array $meta = [],
        public readonly string $subject = 'GIAS notification',
    ) {
        $this->onQueue('notifications');
    }

    /** Email only, no in-app row (e.g. the recipient can't sign in). Implies email. */
    public function mailOnly(): bool
    {
        return false;
    }

    /** Email even when the recipient turned email notifications off. */
    public function mandatoryMail(): bool
    {
        return false;
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $channels = $this->mailOnly() ? [] : ['database'];

        if ($this->mailOnly() || $this->mandatoryMail() || ($notifiable->email_notifications ?? true)) {
            $channels[] = 'mail';
        }

        return $channels;
    }

    /**
     * Stored in notifications.data (database channel).
     *
     * @return array{category: string, message: string, action_path: string, action_label: string, meta: array<string, mixed>}
     */
    public function toArray(object $notifiable): array
    {
        return [
            'category' => $this->category,
            'message' => $this->message,
            'action_path' => $this->actionPath,
            'action_label' => $this->actionLabel,
            'meta' => $this->meta,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = trim((string) ($notifiable->name ?? ''));
        $first = Str::before($name, ' ') ?: $name;

        return (new MailMessage)
            ->subject($this->subject)
            ->greeting("Hi {$first},")
            ->line($this->message)
            ->action($this->actionLabel, rtrim((string) config('app.frontend_url'), '/').$this->actionPath)
            ->salutation('— GIAS, Davao del Norte State College');
    }

    /** "1 report" / "3 reports" */
    protected static function plural(int $count, string $word): string
    {
        return $count.' '.Str::plural($word, $count);
    }

    /** 40 → "40", 39.5 → "39.5" */
    protected static function hours(float|string $hours): string
    {
        return rtrim(rtrim(number_format((float) $hours, 2, '.', ''), '0'), '.');
    }
}
