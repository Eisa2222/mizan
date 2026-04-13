<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">إدارة المستخدمين</div>
                <div class="mz-page-sub">عرض وإدارة صلاحيات المستخدمين في جميع الجهات</div>
            </div>
            <a href="{{ route('admin.users.create') }}" class="mz-btn mz-btn-gold">+ إضافة مستخدم</a>
        </div>

        @if (session('success'))
            <div style="background:rgba(80,200,120,.12);border:1px solid rgba(80,200,120,.4);border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:13px;color:#5c8">
                {{ session('success') }}
            </div>
        @endif

        {{-- Filter by org --}}
        <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
            <a href="{{ route('admin.users') }}" class="mz-chip {{ !request('org') ? 'active' : '' }}">الكل</a>
            @foreach ($orgs as $id => $name)
                <a href="{{ route('admin.users', ['org' => $id]) }}" class="mz-chip {{ request('org') == $id ? 'active' : '' }}">{{ $name }}</a>
            @endforeach
        </div>

        <div class="mz-card" style="padding:0;overflow:hidden">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead>
                    <tr style="background:var(--card2);border-bottom:1px solid var(--borderl)">
                        <th style="padding:10px 14px;text-align:right;color:var(--mute)">الاسم</th>
                        <th style="padding:10px 14px;text-align:right;color:var(--mute)">البريد</th>
                        <th style="padding:10px 14px;text-align:right;color:var(--mute)">الجهة</th>
                        <th style="padding:10px 14px;text-align:right;color:var(--mute)">الصلاحية</th>
                        <th style="padding:10px 14px;text-align:center;color:var(--mute);width:200px">تغيير الصلاحية</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $u)
                        <tr style="border-bottom:1px solid var(--borderl)">
                            <td style="padding:10px 14px;color:var(--cream);font-weight:600">{{ $u->name }}</td>
                            <td style="padding:10px 14px;color:var(--dim)" dir="ltr">{{ $u->email }}</td>
                            <td style="padding:10px 14px;color:var(--dim)">{{ $u->organization?->name_ar ?? '—' }}</td>
                            <td style="padding:10px 14px">
                                <span style="background:rgba(200,169,75,.15);color:var(--gold);padding:3px 8px;border-radius:6px;font-size:11px;font-weight:700">
                                    {{ $u->role?->label() ?? '—' }}
                                </span>
                            </td>
                            <td style="padding:6px 14px;text-align:center">
                                @if ($u->role !== \App\Enums\UserRole::SuperAdmin)
                                    <form method="POST" action="{{ route('admin.users.update-role', $u) }}" style="display:flex;gap:6px;justify-content:center">
                                        @csrf @method('PATCH')
                                        <select name="role" class="mz-inp" style="font-size:11px;padding:4px 8px;width:130px">
                                            @foreach (\App\Enums\UserRole::cases() as $role)
                                                @if ($role !== \App\Enums\UserRole::SuperAdmin)
                                                    <option value="{{ $role->value }}" {{ $u->role === $role ? 'selected' : '' }}>{{ $role->label() }}</option>
                                                @endif
                                            @endforeach
                                        </select>
                                        <button type="submit" class="mz-btn mz-btn-ghost mz-btn-sm" style="font-size:11px;padding:4px 8px">تحديث</button>
                                    </form>
                                @else
                                    <span style="font-size:11px;color:var(--mute)">مدير عام</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px">{{ $users->links() }}</div>
    </div>
</x-app-layout>
