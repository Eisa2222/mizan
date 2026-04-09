@php
    $unreadCount = auth()->check()
        ? \App\Models\AppNotification::where('user_id', auth()->id())->whereNull('read_at')->count()
        : 0;
    $latestNotifs = auth()->check()
        ? \App\Models\AppNotification::where('user_id', auth()->id())->latest()->take(5)->get()
        : collect();
@endphp

<header class="mz-topbar">
    <div class="mz-tb-logo">
        <div class="mz-tb-mark">⚖️</div>
        <div>
            <div class="mz-tb-name">ميزان</div>
            <div class="mz-tb-tag">Legal Research</div>
        </div>
    </div>

    <div class="mz-tb-search">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
        <form method="GET" action="{{ route('search') }}" style="flex:1;display:flex">
            <input name="q" placeholder="ابحث في المستندات القانونية..." value="{{ request('q') }}">
        </form>
    </div>

    <div class="mz-topbar-r">
        @auth
            {{-- Notifications bell --}}
            <div x-data="{ open: false }" style="position:relative">
                <button @click="open = !open" class="mz-tb-btn" title="الإشعارات">
                    🔔
                    @if ($unreadCount > 0)
                        <span class="mz-notif-badge">{{ $unreadCount > 9 ? '9+' : $unreadCount }}</span>
                    @endif
                </button>

                <div x-show="open" @click.outside="open = false" x-cloak class="mz-dropdown">
                    <div class="mz-dropdown-head">
                        الإشعارات
                        @if ($unreadCount > 0)
                            <span style="float:left;font-size:11px;color:var(--gold);font-weight:600">{{ $unreadCount }} غير مقروءة</span>
                        @endif
                    </div>
                    <div class="mz-dropdown-body">
                        @forelse ($latestNotifs as $n)
                            <a href="{{ $n->data['link'] ?? route('notifications.index') }}"
                               class="mz-notif-row {{ $n->isUnread() ? 'unread' : '' }}">
                                <div class="t">{{ $n->title }}</div>
                                @if ($n->body)<div class="b">{{ Str::limit($n->body, 60) }}</div>@endif
                                <div class="ti">{{ $n->created_at->diffForHumans() }}</div>
                            </a>
                        @empty
                            <div style="padding:32px 14px;text-align:center;color:var(--mute);font-size:12px">لا توجد إشعارات</div>
                        @endforelse
                    </div>
                    <div style="padding:10px 14px;border-top:1px solid var(--borderl);text-align:center">
                        <a href="{{ route('notifications.index') }}" style="font-size:12px;color:var(--gold);text-decoration:none">عرض كل الإشعارات ←</a>
                    </div>
                </div>
            </div>

            {{-- User --}}
            <div x-data="{ open: false }" style="position:relative">
                <div @click="open = !open" class="mz-tb-user">
                    <div class="mz-tb-avatar">{{ mb_substr(auth()->user()->name, 0, 1) }}</div>
                    <div>
                        <div class="mz-tb-uname">{{ auth()->user()->name }}</div>
                        <div class="mz-tb-urole">{{ auth()->user()->role?->label() ?? '—' }}</div>
                    </div>
                </div>

                <div x-show="open" @click.outside="open = false" x-cloak class="mz-user-dropdown">
                    <div class="head">
                        <div style="font-size:13px;font-weight:700;color:var(--cream)">{{ auth()->user()->name }}</div>
                        <div style="font-size:11px;color:var(--mute);margin-top:2px">{{ auth()->user()->email }}</div>
                    </div>
                    <a href="{{ route('profile.edit') }}">الملف الشخصي</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="danger">تسجيل الخروج</button>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</header>
