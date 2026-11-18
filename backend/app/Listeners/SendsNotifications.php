<?php

namespace App\Listeners;

use App\Enums\UserStatus;
use App\Models\User;
use App\Notifications\GiasNotification;
use Closure;

/**
 * Shared delivery rules for the notification listeners:
 *  - never notify the person who did the action (listeners run in the acting request)
 *  - skip inactive / pending / rejected recipients, except email-only approval mails
 */
trait SendsNotifications
{
    /**
     * @param  iterable<User|null>  $recipients
     * @param  GiasNotification|Closure(User): GiasNotification  $notification
     */
    protected function send(iterable $recipients, GiasNotification|Closure $notification): void
    {
        $actorId = auth()->id();

        foreach ($recipients as $user) {
            if (! $user instanceof User || $user->id === $actorId) {
                continue;
            }

            $message = $notification instanceof Closure ? $notification($user) : $notification;

            if ($user->status !== UserStatus::Active && ! $message->mailOnly()) {
                continue;
            }

            $user->notify($message);
        }
    }
}
