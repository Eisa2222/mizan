@section('title', 'الملف الشخصي')

<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <h1 class="mz-page-title">الملف الشخصي</h1>
                <div class="mz-page-sub">إدارة بيانات حسابك وإعدادات الأمان</div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:16px;max-width:880px">

            {{-- Profile information --}}
            <div class="mz-card">
                <div class="mz-card-head">
                    <div class="mz-card-title">📝 المعلومات الشخصية</div>
                </div>
                <div class="mz-card-body">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            {{-- Password --}}
            <div class="mz-card">
                <div class="mz-card-head">
                    <div class="mz-card-title">🔒 كلمة المرور</div>
                </div>
                <div class="mz-card-body">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            {{-- Delete account (super-admin guard lives in the action itself) --}}
            @if (! auth()->user()->hasRole(\App\Enums\UserRole::SuperAdmin))
                <div class="mz-card" style="border-color:rgba(224,85,85,.25)">
                    <div class="mz-card-head">
                        <div class="mz-card-title" style="color:var(--red)">⚠️ منطقة الخطر</div>
                    </div>
                    <div class="mz-card-body">
                        @include('profile.partials.delete-user-form')
                    </div>
                </div>
            @endif

        </div>
    </div>
</x-app-layout>
