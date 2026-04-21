@section('title', 'إدارة الجهات')

<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">إدارة الجهات</div>
                <div class="mz-page-sub">إنشاء وإدارة الجهات الحكومية والخاصة على المنصة</div>
            </div>
            <a href="{{ route('admin.organizations.create') }}" class="mz-btn mz-btn-gold">+ إنشاء جهة جديدة</a>
        </div>

        @if (session('success'))
            <div style="background:rgba(80,200,120,.12);border:1px solid rgba(80,200,120,.4);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#5c8">
                {{ session('success') }}
            </div>
        @endif

        <div style="display:flex;flex-direction:column;gap:12px">
            @foreach ($orgs as $org)
                <div class="mz-card" style="padding:16px;display:flex;justify-content:space-between;align-items:center">
                    <div>
                        <div style="font-size:15px;font-weight:700;color:var(--cream)">{{ $org->name_ar }}</div>
                        @if ($org->name_en)
                            <div style="font-size:12px;color:var(--mute)">{{ $org->name_en }}</div>
                        @endif
                        <div style="font-size:11px;color:var(--dim);margin-top:4px">
                            {{ $org->domain }} · {{ $org->users_count }} مستخدم
                            @if ($org->phone) · {{ $org->phone }} @endif
                        </div>
                    </div>
                    <div style="display:flex;gap:8px;align-items:center">
                        <a href="{{ route('admin.users', ['org' => $org->id]) }}" class="mz-btn mz-btn-ghost mz-btn-sm">المستخدمون</a>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</x-app-layout>
