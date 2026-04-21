@extends('super-admin.layouts.app')

@section('title', 'المستأجرون')
@section('heading', 'إدارة المستأجرين')

@section('content')
    <div class="sa-card" style="margin-bottom:16px">
        <form method="GET" action="{{ route('super-admin.tenants.index') }}" style="display:flex;gap:10px;flex-wrap:wrap">
            <input type="text" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="ابحث بالاسم أو البريد..." class="form-input" style="flex:1;min-width:200px;padding:8px 12px;border:1px solid #d4d9e3;border-radius:8px;font-family:inherit">
            <select name="status" class="form-input" style="padding:8px 12px;border:1px solid #d4d9e3;border-radius:8px;font-family:inherit">
                <option value="">كل الحالات</option>
                <option value="active"    {{ ($filters['status'] ?? '') === 'active'    ? 'selected' : '' }}>نشط</option>
                <option value="suspended" {{ ($filters['status'] ?? '') === 'suspended' ? 'selected' : '' }}>موقوف</option>
                <option value="archived"  {{ ($filters['status'] ?? '') === 'archived'  ? 'selected' : '' }}>مؤرشف</option>
            </select>
            <button type="submit" class="sa-btn">تصفية</button>
        </form>
    </div>

    <div class="sa-card" style="padding:0;overflow:hidden">
        <table class="sa-table">
            <thead>
                <tr>
                    <th>الشركة</th>
                    <th>المالك</th>
                    <th>النطاق</th>
                    <th>الباقة</th>
                    <th>الحالة</th>
                    <th>التسجيل</th>
                    <th style="width:1%">إجراءات</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($tenants as $tenant)
                    <tr>
                        <td><strong>{{ $tenant->company_name }}</strong></td>
                        <td>{{ $tenant->owner_name }}<br><span style="font-size:11px;color:#8a91a4" dir="ltr">{{ $tenant->owner_email }}</span></td>
                        <td dir="ltr" style="font-size:12px;color:#485068">{{ $tenant->domains->first()?->domain ?? '—' }}</td>
                        <td>{{ $tenant->activeSubscription?->plan?->name ?? '—' }}</td>
                        <td>
                            @switch($tenant->status)
                                @case('active')    <span class="sa-badge sa-badge-green">نشط</span> @break
                                @case('suspended') <span class="sa-badge sa-badge-red">موقوف</span> @break
                                @case('archived')  <span class="sa-badge sa-badge-grey">مؤرشف</span> @break
                                @default          <span class="sa-badge sa-badge-grey">{{ $tenant->status }}</span>
                            @endswitch
                        </td>
                        <td>{{ $tenant->created_at?->diffForHumans() }}</td>
                        <td><a href="{{ route('super-admin.tenants.show', $tenant) }}" class="sa-btn sa-btn-ghost" style="padding:4px 10px">التفاصيل</a></td>
                    </tr>
                @empty
                    <tr><td colspan="7" style="text-align:center;padding:32px;color:#8a91a4">لا يوجد مستأجرون.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div style="margin-top:16px">{{ $tenants->links() }}</div>
@endsection
