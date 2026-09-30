<a class="mb-4 inline-block underline" href="{{ route(str_replace(".index", ".preferences", $indexRoute)) }}">{{ __("notifications.preferences") }}</a>
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div>
        <x-badge tone="brand">Notifications</x-badge>
        <h1 class="mt-4 text-3xl font-semibold tracking-normal text-neutral-950">Notifications</h1>
        <p class="mt-2 text-sm leading-6 text-neutral-600">Important account and marketplace updates appear here.</p>
    </div>

    @if ($unreadCount > 0)
        <form method="POST" action="{{ route($readAllRoute) }}">
            @csrf
            <x-button type="submit" variant="secondary">Mark all read</x-button>
        </form>
    @endif
</div>

<div class="mb-5 flex gap-2">
    <a href="{{ route($indexRoute) }}" @class([
        'rounded-md px-3 py-2 text-sm font-semibold',
        'bg-neutral-950 text-white' => $filter === 'all',
        'border border-neutral-200 bg-white text-neutral-600 hover:text-neutral-950' => $filter !== 'all',
    ])>All</a>
    <a href="{{ route($indexRoute, ['filter' => 'unread']) }}" @class([
        'rounded-md px-3 py-2 text-sm font-semibold',
        'bg-neutral-950 text-white' => $filter === 'unread',
        'border border-neutral-200 bg-white text-neutral-600 hover:text-neutral-950' => $filter !== 'unread',
    ])>Unread {{ $unreadCount > 0 ? '(' . $unreadCount . ')' : '' }}</a>
</div>

<div class="overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm">
    @forelse ($notifications as $notification)
        @php
            $data = $notification->data;
            $tone = match ($data['severity'] ?? 'info') {
                'success' => 'bg-emerald-500',
                'warning' => 'bg-amber-500',
                'critical' => 'bg-red-600',
                default => 'bg-brand-600',
            };
        @endphp

        <article dir="auto" @class([
            'border-b border-neutral-100 p-5 last:border-b-0',
            'bg-brand-50/40' => is_null($notification->read_at),
        ])>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
                <div class="flex min-w-0 gap-3">
                    <span class="mt-2 h-2.5 w-2.5 shrink-0 rounded-full {{ is_null($notification->read_at) ? $tone : 'bg-neutral-300' }}"></span>
                    <div class="min-w-0">
                        <h2 class="text-sm font-semibold text-neutral-950">{{ $data['title'] ?? 'Notification' }}</h2>
                        <p class="mt-1 text-sm leading-6 text-neutral-600">{{ $data['message'] ?? '' }}</p>
                        <p class="mt-2 text-xs font-medium text-neutral-400" title="{{ $notification->created_at?->format('M j, Y g:i A') }}">
                            {{ $notification->created_at?->diffForHumans() }}
                        </p>
                    </div>
                </div>

                <div class="flex shrink-0 items-center gap-2">
                    @if (! empty($data['action_url']))
                        <x-button :href="route($openRoute, $notification)" variant="secondary" size="sm">Open</x-button>
                    @endif

                    @if (is_null($notification->read_at))
                        <form method="POST" action="{{ route($readRoute, $notification) }}">
                            @csrf
                            <x-button type="submit" variant="secondary" size="sm">Mark read</x-button>
                        </form>
                    @endif
                </div>
            </div>
        </article>
    @empty
        <x-empty-state title="You're all caught up" description="New updates will appear here when marketplace activity needs your attention." class="m-5" />
    @endforelse
</div>

<div class="mt-6">{{ $notifications->links() }}</div>


