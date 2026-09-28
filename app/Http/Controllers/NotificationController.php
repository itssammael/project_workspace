<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\NotificationService;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = NotificationService::getNotifications($user);

        return response()->json($data);
    }

    /**
     * Mark a single notification as read.
     */
    public function markRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_key' => 'required|string',
        ]);

        NotificationService::markAsRead($request->user(), $validated['notification_key']);

        return response()->json(['success' => true]);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllRead(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'notification_keys' => 'required|array',
            'notification_keys.*' => 'string',
        ]);

        NotificationService::markAllAsRead($request->user(), $validated['notification_keys']);

        return response()->json(['success' => true]);
    }
}
