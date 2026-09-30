@props([
    'indexRoute',
    'readAllRoute',
    'openRoute',
])

@php
    $user = auth()->user();
    $unreadCount = $user?->unreadNotifications()->count() ?? 0;
    $latestNotifications = $user
        ? $user->notifications()->latest()->limit(5)->get()
        : collect();
@endphp

@auth
    <details class="relative">
        <summary
            class="flex h-10 w-10 cursor-pointer list-none items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-700 transition hover:border-brand-300 hover:text-brand-700"
            aria-label="Notifications, {{ $unreadCount }} unread"
        >
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M18 8a6 6 0 1 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M13.73 21a2 2 0 0 1-3.46 0" />
            </svg>

            @if ($unreadCount > 0)
                <span class="absolute -right-1 -top-1 min-w-5 rounded-full bg-brand-700 px-1.5 py-0.5 text-center text-[11px] font-semibold leading-4 text-white">
                    {{ $unreadCount > 99 ? '99+' : $unreadCount }}
                </span>
            @endif
        </summary>

        <div class="absolute right-0 z-40 mt-2 w-80 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-neutral-100 px-4 py-3">
                <p class="text-sm font-semibold text-neutral-950">Notifications</p>
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route($readAllRoute) }}">
                        @csrf
                        <button type="submit" class="text-xs font-semibold text-brand-700 hover:text-brand-900">Mark all read</button>
                    </form>
                @endif
            </div>

            <div class="max-h-80 overflow-y-auto">
                @forelse ($latestNotifications as $notification)
                    @php
                        $data = $notification->data;
                    @endphp
                    <a href="{{ route($openRoute, $notification) }}" class="block border-b border-neutral-100 px-4 py-3 transition hover:bg-neutral-50">
                        <div class="flex gap-3">
                            <span @class([
                                'mt-1 h-2 w-2 shrink-0 rounded-full',
                                'bg-brand-700' => is_null($notification->read_at),
                                'bg-neutral-300' => ! is_null($notification->read_at),
                            ])></span>
                            <span class="min-w-0">
                                <span class="block truncate text-sm font-semibold text-neutral-950">{{ $data['title'] ?? 'Notification' }}</span>
                                <span class="mt-1 line-clamp-2 block text-xs leading-5 text-neutral-600">{{ $data['message'] ?? '' }}</span>
                                <span class="mt-1 block text-[11px] font-medium text-neutral-400">{{ $notification->created_at?->diffForHumans() }}</span>
                            </span>
                        </div>
                    </a>
                @empty
                    <p class="px-4 py-6 text-sm text-neutral-500">You're all caught up.</p>
                @endforelse
            </div>

            <a href="{{ route($indexRoute) }}" class="block px-4 py-3 text-center text-sm font-semibold text-brand-700 hover:bg-brand-50">
                View all notifications
            </a>
        </div>
    </details>
@endauth
