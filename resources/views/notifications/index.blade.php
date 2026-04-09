<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">الإشعارات</div>
                <div class="mz-page-sub">{{ $notifications->total() }} إشعار</div>
            </div>
            @if ($notifications->total() > 0)
                <form method="POST" action="{{ route('notifications.readAll') }}">
                    @csrf
                    <button type="submit" class="mz-btn mz-btn-ghost mz-btn-sm">تحديد الكل كمقروء</button>
                </form>
            @endif
        </div>

        <div class="mz-card" style="padding:0;overflow:hidden">
            @forelse ($notifications as $n)
                <form method="POST" action="{{ route('notifications.read', $n) }}">
                    @csrf
                    <button type="submit" class="mz-notif-row {{ $n->isUnread() ? 'unread' : '' }}"
                            style="width:100%;text-align:right;background:transparent;border:none;cursor:pointer;font-family:inherit;display:block">
                        <div style="display:flex;gap:12px;align-items:flex-start">
                            <div style="width:36px;height:36px;border-radius:9px;display:grid;place-items:center;font-size:16px;flex-shrink:0;
                                        background:@switch($n->type)
                                            @case('task.assigned') rgba(61,130,255,.1) @break
                                            @case('task.status_changed') rgba(224,120,48,.1) @break
                                            @case('task.commented') rgba(61,191,138,.1) @break
                                            @default rgba(200,169,75,.1)
                                        @endswitch">
                                @switch($n->type)
                                    @case('task.assigned') 👤 @break
                                    @case('task.status_changed') 🔄 @break
                                    @case('task.commented') 💬 @break
                                    @default 🔔
                                @endswitch
                            </div>
                            <div style="flex:1;min-width:0">
                                <div class="t">
                                    {{ $n->title }}
                                    @if ($n->isUnread())
                                        <span style="display:inline-block;width:6px;height:6px;border-radius:50%;background:var(--gold);margin-right:6px;vertical-align:middle"></span>
                                    @endif
                                </div>
                                @if ($n->body)<div class="b">{{ $n->body }}</div>@endif
                                <div class="ti">{{ $n->created_at->diffForHumans() }}</div>
                            </div>
                        </div>
                    </button>
                </form>
            @empty
                <div style="padding:64px;text-align:center;color:var(--mute)">
                    <div style="font-size:48px;margin-bottom:12px">🔔</div>
                    <div style="font-size:14px">لا توجد إشعارات</div>
                </div>
            @endforelse
        </div>

        @if ($notifications->hasPages())
            <div>{{ $notifications->links() }}</div>
        @endif
    </div>
</x-app-layout>
