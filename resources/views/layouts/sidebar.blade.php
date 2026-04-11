@php
    $taskCount = auth()->check() && auth()->user()->org_id
        ? \App\Models\Task::where('org_id', auth()->user()->org_id)->whereIn('status', [1,2,3])->count()
        : 0;
@endphp

<aside class="mz-sidebar">
    <div class="mz-nav-section">الرئيسية</div>
    <a href="{{ route('dashboard') }}" class="mz-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <span class="mz-ni">🏠</span> لوحة التحكم
    </a>

    <div class="mz-nav-section" style="margin-top:8px">البحث والمستندات</div>
    <a href="{{ route('search') }}" class="mz-nav-item {{ request()->routeIs('search') ? 'active' : '' }}">
        <span class="mz-ni">🔍</span> البحث القانوني
    </a>
    <a href="{{ route('documents.index') }}" class="mz-nav-item {{ request()->routeIs('documents.*') ? 'active' : '' }}">
        <span class="mz-ni">📚</span> المستندات القانونية
    </a>
    <a href="{{ route('folders.index') }}" class="mz-nav-item {{ request()->routeIs('folders.*') ? 'active' : '' }}">
        <span class="mz-ni">📁</span> المجلدات
    </a>
    <a href="{{ route('watchlist.index') }}" class="mz-nav-item {{ request()->routeIs('watchlist.*') ? 'active' : '' }}">
        <span class="mz-ni">👁</span> المتابعة
    </a>

    <div class="mz-nav-section" style="margin-top:8px">الخدمات القانونية</div>
    <a href="{{ route('contract-reviews.index') }}" class="mz-nav-item {{ request()->routeIs('contract-reviews.*') ? 'active' : '' }}">
        <span class="mz-ni">📝</span> مراجعة العقود
    </a>
    <a href="{{ route('tender-reviews.index') }}" class="mz-nav-item {{ request()->routeIs('tender-reviews.*') ? 'active' : '' }}">
        <span class="mz-ni">📋</span> مراجعة الكراسات
    </a>
    <a href="{{ route('tenders.index') }}" class="mz-nav-item {{ request()->routeIs('tenders.*') ? 'active' : '' }}">
        <span class="mz-ni">✨</span> توليد كراسة ذكي
    </a>
    <a href="{{ route('memos.index') }}" class="mz-nav-item {{ request()->routeIs('memos.*') ? 'active' : '' }}">
        <span class="mz-ni">📄</span> مسودات المذكرات
    </a>
    <a href="{{ route('gpc-knowledge.index') }}" class="mz-nav-item {{ request()->routeIs('gpc-knowledge.*') ? 'active' : '' }}">
        <span class="mz-ni">📚</span> قاعدة المعرفة النظامية
    </a>

    <div class="mz-nav-section" style="margin-top:8px">الإدارة</div>
    <a href="{{ route('tasks.index') }}" class="mz-nav-item {{ request()->routeIs('tasks.*') ? 'active' : '' }}">
        <span class="mz-ni">✅</span> سير العمل والمهام
        @if ($taskCount > 0)<span class="mz-nav-count">{{ $taskCount }}</span>@endif
    </a>
    <a href="{{ route('notifications.index') }}" class="mz-nav-item {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
        <span class="mz-ni">🔔</span> الإشعارات
        @if (auth()->check())
            @php $unread = \App\Models\AppNotification::where('user_id', auth()->id())->whereNull('read_at')->count(); @endphp
            @if ($unread > 0)<span class="mz-nav-count">{{ $unread }}</span>@endif
        @endif
    </a>

    <div style="margin-top:auto;padding:12px;border-top:1px solid var(--borderl)">
        <div style="font-size:10px;color:var(--mute);letter-spacing:.08em;text-transform:uppercase;margin-bottom:6px">المؤسسة</div>
        <div style="font-size:12px;color:var(--cream);font-weight:600">{{ auth()->user()?->organization?->name_ar ?? '—' }}</div>
        <div style="font-size:10px;color:var(--mute);margin-top:8px">منصة ميزان · v1.0</div>
    </div>
</aside>
