<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'filter' => ['nullable', 'string', 'in:all,unread'],
        ]);

        $filter = $validated['filter'] ?? 'all';
        $notifications = $request->user()
            ->notifications()
            ->when($filter === 'unread', fn ($query) => $query->whereNull('read_at'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view($this->viewName($request), [
            'notifications' => $notifications,
            'filter' => $filter,
            'unreadCount' => $request->user()->unreadNotifications()->count(),
        ]);
    }

    public function open(Request $request, string $notification): RedirectResponse
    {
        $notification = $this->notificationFor($request, $notification);
        $notification->markAsRead();

        $url = $notification->data['action_url'] ?? null;

        if (! $this->isSafeInternalUrl($url)) {
            return redirect()->route($this->indexRouteName($request));
        }

        return redirect($url);
    }

    public function read(Request $request, string $notification): RedirectResponse
    {
        $this->notificationFor($request, $notification)->markAsRead();

        return back()->with('status', 'Notification marked read.');
    }

    public function readAll(Request $request): RedirectResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('status', 'All notifications marked read.');
    }

    private function notificationFor(Request $request, string $id): DatabaseNotification
    {
        return $request->user()->notifications()->whereKey($id)->firstOrFail();
    }

    private function viewName(Request $request): string
    {
        return match (true) {
            $request->routeIs('admin.*') => 'admin.notifications.index',
            $request->routeIs('vendor.*') => 'vendor.notifications.index',
            default => 'account.notifications.index',
        };
    }

    private function indexRouteName(Request $request): string
    {
        return match (true) {
            $request->routeIs('admin.*') => 'admin.notifications.index',
            $request->routeIs('vendor.*') => 'vendor.notifications.index',
            default => 'account.notifications.index',
        };
    }

    private function isSafeInternalUrl(mixed $url): bool
    {
        return is_string($url)
            && $url !== ''
            && str_starts_with($url, '/')
            && ! str_starts_with($url, '//')
            && ! str_contains($url, "\n")
            && ! str_contains($url, "\r")
            && ! str_contains($url, '\\');
    }
}

