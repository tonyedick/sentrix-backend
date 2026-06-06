<?php

declare(strict_types=1);

namespace App\Domains\Notification\Http\Controllers;

use App\Domains\Notification\Http\Resources\NotificationResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * The authenticated user's in-app notification feed (the `database` channel).
 * Everything is scoped to the caller via their notifications relation, so one
 * user can never read or mutate another's notifications.
 */
final class NotificationController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $notifications = $request->user()
            ->notifications()
            ->when($request->boolean('unread'), fn ($query) => $query->whereNull('read_at'))
            ->paginate($this->perPage($request));

        return NotificationResource::collection($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'data' => ['count' => $request->user()->unreadNotifications()->count()],
        ]);
    }

    public function markAsRead(Request $request, string $notification): NotificationResource
    {
        $model = $request->user()->notifications()->findOrFail($notification);
        $model->markAsRead();

        return NotificationResource::make($model->refresh());
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read.']);
    }

    public function destroy(Request $request, string $notification): JsonResponse
    {
        $request->user()->notifications()->findOrFail($notification)->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
