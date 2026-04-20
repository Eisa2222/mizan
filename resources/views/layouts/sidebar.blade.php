{{-- $activeTaskCount and $unreadNotificationCount are injected by SidebarComposer.
     sidebarOpen (Alpine) controls the mobile drawer from layouts.app. --}}
<aside id="mz-sidebar"
       class="mz-sidebar"
       :class="{ 'mz-sidebar-open': sidebarOpen }"
       role="navigation"
       aria-label="التنقل الرئيسي">
    <div class="mz-nav-section">الرئيسية</div>
    <a href="{{ route('dashboard') }}" class="mz-nav-item {{ request()->routeIs('dashboard') ? 'active' : '' }}">
        <span class="mz-ni">🏠</span> لوحة التحكم
    </a>

    <div class="mz-nav-section" style="margin-top:8px">البحث والمستندات</div>
    @can('search.use')
        <a href="{{ route('search') }}" class="mz-nav-item {{ request()->routeIs('search') ? 'active' : '' }}">
            <span class="mz-ni">🔍</span> البحث القانوني
        </a>
    @endcan
    @can('documents.view')
        <a href="{{ route('documents.index') }}" class="mz-nav-item {{ request()->routeIs('documents.*') ? 'active' : '' }}">
            <span class="mz-ni">📚</span> المستندات القانونية
        </a>
    @endcan
    @can('folders.view')
        <a href="{{ route('folders.index') }}" class="mz-nav-item {{ request()->routeIs('folders.*') ? 'active' : '' }}">
            <span class="mz-ni">📁</span> المجلدات
        </a>
    @endcan
    @can('watchlist.manage')
        <a href="{{ route('watchlist.index') }}" class="mz-nav-item {{ request()->routeIs('watchlist.*') ? 'active' : '' }}">
            <span class="mz-ni">👁</span> المتابعة
        </a>
    @endcan

    <div class="mz-nav-section" style="margin-top:8px">الخدمات القانونية</div>
    @can('contract_reviews.view')
        <a href="{{ route('contract-reviews.index') }}" class="mz-nav-item {{ request()->routeIs('contract-reviews.*') ? 'active' : '' }}">
            <span class="mz-ni">📝</span> مراجعة العقود
        </a>
    @endcan
    @can('tender_reviews.view')
        <a href="{{ route('tender-reviews.index') }}" class="mz-nav-item {{ request()->routeIs('tender-reviews.*') ? 'active' : '' }}">
            <span class="mz-ni">📋</span> مراجعة الكراسات
        </a>
    @endcan
    @can('tenders.view')
        <a href="{{ route('tenders.index') }}" class="mz-nav-item {{ request()->routeIs('tenders.*') ? 'active' : '' }}">
            <span class="mz-ni">✨</span> توليد كراسة ذكي
        </a>
    @endcan
    @can('memos.view')
        <a href="{{ route('memos.index') }}" class="mz-nav-item {{ request()->routeIs('memos.*') ? 'active' : '' }}">
            <span class="mz-ni">📄</span> مسودات المذكرات
        </a>
    @endcan
    @can('gpc_knowledge.view')
        <a href="{{ route('gpc-knowledge.index') }}" class="mz-nav-item {{ request()->routeIs('gpc-knowledge.*') ? 'active' : '' }}">
            <span class="mz-ni">📚</span> قاعدة المعرفة النظامية
        </a>
    @endcan

    <div class="mz-nav-section" style="margin-top:8px">الإدارة</div>
    @can('tasks.view')
        <a href="{{ route('tasks.index') }}" class="mz-nav-item {{ request()->routeIs('tasks.*') ? 'active' : '' }}">
            <span class="mz-ni">✅</span> سير العمل والمهام
            @if ($activeTaskCount > 0)<span class="mz-nav-count">{{ $activeTaskCount }}</span>@endif
        </a>
    @endcan
    @can('notifications.view')
        <a href="{{ route('notifications.index') }}" class="mz-nav-item {{ request()->routeIs('notifications.*') ? 'active' : '' }}">
            <span class="mz-ni">🔔</span> الإشعارات
            @if ($unreadNotificationCount > 0)<span class="mz-nav-count">{{ $unreadNotificationCount }}</span>@endif
        </a>
    @endcan

    @can('admin.access')
        <div class="mz-nav-section" style="margin-top:8px">لوحة المدير</div>
        <a href="{{ route('admin.organizations') }}" class="mz-nav-item {{ request()->routeIs('admin.organizations*') ? 'active' : '' }}">
            <span class="mz-ni">🏢</span> إدارة الجهات
        </a>
        <a href="{{ route('admin.users') }}" class="mz-nav-item {{ request()->routeIs('admin.users*') ? 'active' : '' }}">
            <span class="mz-ni">👥</span> إدارة المستخدمين
        </a>
    @endcan

    @can('branding.update')
        <div class="mz-nav-section" style="margin-top:8px">الإعدادات</div>
        <a href="{{ route('branding.edit') }}" class="mz-nav-item {{ request()->routeIs('branding.*') ? 'active' : '' }}">
            <span class="mz-ni">🎨</span> هوية المؤسسة
        </a>
    @endcan

    <div style="margin-top:auto;padding:12px;border-top:1px solid var(--borderl)">
        <div style="font-size:10px;color:var(--mute);letter-spacing:.08em;text-transform:uppercase;margin-bottom:6px">المؤسسة</div>
        <div style="font-size:12px;color:var(--cream);font-weight:600">{{ auth()->user()?->organization?->name_ar ?? '—' }}</div>
        <div style="font-size:10px;color:var(--mute);margin-top:8px">منصة ميزان · v1.0</div>
    </div>
</aside>
