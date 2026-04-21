@extends('super-admin.layouts.app')
@section('title', 'الإعدادات')
@section('heading', 'إعدادات النظام')

@php
    $tabs = [
        'general'        => ['label' => 'عام',             'icon' => '⚙'],
        'trial'          => ['label' => 'الفترة التجريبية', 'icon' => '🎁'],
        'moyasar'        => ['label' => 'الدفع (Moyasar)',  'icon' => '💳'],
        'mail'           => ['label' => 'البريد الإلكتروني', 'icon' => '✉'],
        'landing'        => ['label' => 'صفحة الهبوط',      'icon' => '🏠'],
        'notifications'  => ['label' => 'الإشعارات',        'icon' => '🔔'],
    ];
@endphp

@section('content')
    <div x-data="{ tab: '{{ request('tab', 'general') }}' }">
        <div class="sa-card" style="padding:0;margin-bottom:18px;overflow:hidden">
            <div style="display:flex;overflow-x:auto;border-bottom:1px solid #e3e7f0">
                @foreach ($tabs as $key => $meta)
                    <button type="button" @click="tab='{{ $key }}'"
                            :class="tab==='{{ $key }}' ? 'font-bold' : ''"
                            :style="tab==='{{ $key }}' ? 'border-bottom:3px solid #c8a94b;color:#0b1220' : 'color:#6a7289'"
                            style="padding:14px 20px;background:none;border:none;cursor:pointer;font-family:inherit;font-size:14px;white-space:nowrap;border-bottom:3px solid transparent">
                        {{ $meta['icon'] }} {{ $meta['label'] }}
                    </button>
                @endforeach
            </div>
        </div>

        @foreach ($tabs as $key => $meta)
            <div x-show="tab==='{{ $key }}'" x-cloak>
                <form method="POST" action="{{ route('super-admin.settings.update') }}" class="sa-card" style="max-width:760px">
                    @csrf @method('PUT')
                    <input type="hidden" name="group" value="{{ $key }}">

                    @foreach ($groups[$key] as $field)
                        @php
                            $val        = $values[$field] ?? '';
                            $isBool     = str_starts_with($field, 'trial_') && $field !== 'trial_days' && $field !== 'trial_warning_days'
                                          || str_starts_with($field, 'notify_') || $field === 'moyasar_test_mode';
                            $isSecret   = in_array($field, ['moyasar_secret_key', 'mail_password']);
                            $isTextarea = in_array($field, ['hero_subtitle']);
                            $isCheckboxArray = $field === 'moyasar_enabled_methods';
                        @endphp
                        <div style="margin-bottom:14px">
                            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;color:#485068">{{ $field }}</label>
                            @if ($isBool)
                                <label style="font-size:13px"><input type="checkbox" name="{{ $field }}" value="1" {{ $val ? 'checked' : '' }}> مفعّل</label>
                            @elseif ($isTextarea)
                                <textarea name="{{ $field }}" rows="3" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">{{ $val }}</textarea>
                            @elseif ($isCheckboxArray)
                                @foreach (['creditcard'=>'بطاقة ائتمان','applepay'=>'Apple Pay','stcpay'=>'STC Pay'] as $m=>$mlabel)
                                    <label style="display:inline-flex;gap:4px;margin-inline-end:12px;font-size:13px">
                                        <input type="checkbox" name="{{ $field }}[]" value="{{ $m }}"
                                               {{ is_array($val) && in_array($m, $val) ? 'checked' : '' }}> {{ $mlabel }}
                                    </label>
                                @endforeach
                            @elseif ($isSecret)
                                <input type="password" name="{{ $field }}" value="{{ $val }}" dir="ltr"
                                       style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:monospace">
                            @else
                                <input type="text" name="{{ $field }}" value="{{ $val }}" dir="ltr"
                                       style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
                            @endif
                        </div>
                    @endforeach

                    @if ($key === 'mail')
                        <div style="padding:12px;background:#f5f7fb;border-radius:6px;margin-bottom:12px;display:flex;gap:10px;align-items:center">
                            <input id="test-mail-to" placeholder="test@example.com" dir="ltr" style="flex:1;padding:6px 10px;border:1px solid #d4d9e3;border-radius:6px">
                            <button type="button" class="sa-btn sa-btn-ghost"
                                    onclick="fetch('{{ route('super-admin.settings.test-mail') }}',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'},body:JSON.stringify({to:document.getElementById('test-mail-to').value})}).then(r=>r.json()).then(j=>alert(j.message))">
                                إرسال رسالة اختبار
                            </button>
                        </div>
                    @endif

                    @if ($key === 'moyasar')
                        <div style="padding:12px;background:#f5f7fb;border-radius:6px;margin-bottom:12px">
                            <button type="button" class="sa-btn sa-btn-ghost"
                                    onclick="fetch('{{ route('super-admin.settings.test-moyasar') }}',{method:'POST',headers:{'X-CSRF-TOKEN':'{{ csrf_token() }}','Accept':'application/json'}}).then(r=>r.json()).then(j=>alert(j.message))">
                                اختبار الاتصال بـ Moyasar
                            </button>
                        </div>
                    @endif

                    <div style="display:flex;justify-content:flex-end">
                        <button type="submit" class="sa-btn">حفظ الإعدادات</button>
                    </div>
                </form>
            </div>
        @endforeach
    </div>
@endsection
