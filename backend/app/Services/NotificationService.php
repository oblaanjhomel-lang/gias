<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The signed-in user's own in-app notifications (any role) and their email preference.
 */
class NotificationService
{
    public const PER_PAGE = 15;

    /**
     * @return LengthAwarePaginator<int, DatabaseNotification>
     */
    public function list(User $user, string $filter): LengthAwarePaginator
    {
        $query = $filter === 'unread' ? $user->unreadNotifications() : $user->notifications();

        // notifications() is already ordered newest first; id breaks ties within one second.
        return $query->orderByDesc('id')->paginate(self::PER_PAGE);
    }

    public function unreadCount(User $user): int
    {
        return $user->unreadNotifications()->count();
    }

    /**
     * 404 when the notification isn't the user's own.
     */
    public function markRead(User $user, string $id): DatabaseNotification
    {
        /** @var DatabaseNotification $notification */
        $notification = $user->notifications()->whereKey($id)->firstOrFail();
        $notification->markAsRead();

        return $notification;
    }

    /**
     * @return int how many were marked
     */
    public function markAllRead(User $user): int
    {
        return $user->unreadNotifications()->update(['read_at' => now()]);
    }

    public function preferences(User $user): User
    {
        return $user;
    }

    public function updatePreferences(User $user, bool $emailNotifications): User
    {
        $user->update(['email_notifications' => $emailNotifications]);

        return $user;
    }
}
