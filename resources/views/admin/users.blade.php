@section('title', 'إدارة المستخدمين')

<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">إدارة المستخدمين</div>
                <div class="mz-page-sub">عرض وإدارة صلاحيات المستخدمين في جميع الجهات</div>
            </div>
            <a href="{{ route('admin.users.create') }}" class="mz-btn mz-btn-gold">+ إضافة مستخدم</a>
        </div>

        {{-- Filter by org --}}
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
            <a href="{{ route('admin.users') }}" class="mz-chip {{ !request('org') ? 'active' : '' }}">الكل</a>
            @foreach ($orgs as $id => $name)
                <a href="{{ route('admin.users', ['org' => $id]) }}" class="mz-chip {{ request('org') == $id ? 'active' : '' }}">{{ $name }}</a>
            @endforeach
        </div>

        <div class="mz-datatable-wrap" x-data="{ q: '' }">
            <div class="mz-datatable-toolbar">
                <div class="mz-datatable-search">
                    <input type="search" x-model="q" placeholder="ابحث عن مستخدم بالاسم أو البريد..." />
                </div>
                <div class="mz-datatable-info">
                    {{ $users->total() }} مستخدم · عرض {{ $users->count() }} في الصفحة الحالية
                </div>
            </div>

            <table class="mz-datatable">
                <thead>
                    <tr>
                        <th>الاسم</th>
                        <th>البريد</th>
                        <th>الجهة</th>
                        <th>الصلاحية</th>
                        <th style="text-align:center;width:220px">تغيير الصلاحية</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($users as $user)
                        <tr x-show="!q || {{ json_encode(mb_strtolower($user->name . ' ' . $user->email . ' ' . ($user->organization?->name_ar ?? '')), JSON_UNESCAPED_UNICODE) }}.includes(q.toLowerCase())">
                            <td style="font-weight:600">{{ $user->name }}</td>
                            <td dir="ltr" style="color:var(--dim)">{{ $user->email }}</td>
                            <td style="color:var(--dim)">{{ $user->organization?->name_ar ?? '—' }}</td>
                            <td>
                                <span style="background:rgba(200,169,75,.15);color:var(--gold);padding:3px 10px;border-radius:8px;font-size:11px;font-weight:700">
                                    {{ $user->role?->label() ?? '—' }}
                                </span>
                            </td>
                            <td style="text-align:center">
                                @if ($user->role !== \App\Enums\UserRole::SuperAdmin)
                                    <form method="POST" action="{{ route('admin.users.update-role', $user) }}" style="display:flex;gap:6px;justify-content:center;align-items:center">
                                        @csrf @method('PATCH')
                                        <select name="role" class="mz-inp" style="font-size:12px;padding:6px 28px 6px 10px;width:140px">
                                            @foreach (\App\Enums\UserRole::cases() as $role)
                                                @if ($role !== \App\Enums\UserRole::SuperAdmin)
                                                    <option value="{{ $role->value }}" {{ $user->role === $role ? 'selected' : '' }}>{{ $role->label() }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <button type="submit" class="mz-btn mz-btn-ghost mz-btn-sm">تحديث</button>
                                    </form>
                                @else
                                    <span style="font-size:11px;color:var(--mute)">مدير عام</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="mz-datatable-empty">لا يوجد مستخدمون</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <x-pagination :paginator="$users" />
    </div>
</x-app-layout>
