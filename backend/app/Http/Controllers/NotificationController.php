<?php

namespace App\Http\Controllers;

use App\Http\Requests\NotificationListRequest;
use App\Http\Requests\UpdateNotificationPreferencesRequest;
use App\Http\Resources\NotificationPreferencesResource;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The signed-in user's own notifications (any role).
 */
class NotificationController extends Controller
{
    public function __construct(private readonly NotificationService $notificationService) {}

    public function index(NotificationListRequest $request): AnonymousResourceCollection
    {
        return NotificationResource::collection(
            $this->notificationService->list($request->user(), $request->validated('filter') ?? 'all'),
        );
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json(['data' => ['count' => $this->notificationService->unreadCount($request->user())]]);
    }

    public function markRead(Request $request, string $notification): NotificationResource
    {
        return new NotificationResource($this->notificationService->markRead($request->user(), $notification));
    }

    public function markAllRead(Request $request): JsonResponse
    {
        return response()->json(['data' => ['marked' => $this->notificationService->markAllRead($request->user())]]);
    }

    public function preferences(Request $request): NotificationPreferencesResource
    {
        return new NotificationPreferencesResource($this->notificationService->preferences($request->user()));
    }

    public function updatePreferences(UpdateNotificationPreferencesRequest $request): NotificationPreferencesResource
    {
        return new NotificationPreferencesResource(
            $this->notificationService->updatePreferences($request->user(), $request->boolean('email_notifications')),
        );
    }
}
