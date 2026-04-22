@extends('super-admin.layouts.app')

@section('title', 'سجل التدقيق')
@section('heading', 'سجل التدقيق')

@section('content')
    {{-- Filters + export --}}
    <form method="GET" action="{{ route('super-admin.audit.index') }}" class="sa-card" style="margin-bottom:16px">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
            <div style="flex:1;min-width:160px">
                <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">الإجراء</label>
                <input name="action" value="{{ $filters['action'] ?? '' }}" placeholder="مثال: tenant."
                       style="width:100%;padding:6px 10px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
            <div style="min-width:140px">
                <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">من</label>
                <input type="date" name="from" value="{{ $filters['from'] ?? '' }}"
                       style="padding:6px 10px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
            <div style="min-width:140px">
                <label style="display:block;font-size:11px;font-weight:600;margin-bottom:3px">إلى</label>
                <input type="date" name="to" value="{{ $filters['to'] ?? '' }}"
                       style="padding:6px 10px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            </div>
            <button type="submit" class="sa-btn">تصفية</button>
            <a href="{{ route('super-admin.audit.export', $filters) }}" class="sa-btn sa-btn-ghost">⬇ CSV</a>
        </div>
    </form>

    @if ($logs->isEmpty())
        <div class="sa-card" style="text-align:center;color:#8a91a4;padding:40px">لا توجد عمليات مطابقة.</div>
    @else
        <div class="sa-card" style="padding:0;overflow:hidden">
            <table style="width:100%;border-collapse:collapse;font-size:13px">
                <thead style="background:#f7f8fc">
                    <tr>
                        <th style="text-align:right;padding:10px 14px;font-weight:600;color:#485068">متى</th>
                        <th style="text-align:right;padding:10px 14px;font-weight:600;color:#485068">الفاعل</th>
                        <th style="text-align:right;padding:10px 14px;font-weight:600;color:#485068">الإجراء</th>
                        <th style="text-align:right;padding:10px 14px;font-weight:600;color:#485068">الهدف</th>
                        <th style="text-align:right;padding:10px 14px;font-weight:600;color:#485068">السبب</th>
                        <th style="text-align:right;padding:10px 14px;font-weight:600;color:#485068">IP</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($logs as $log)
                        <tr style="border-top:1px solid #eef1f6">
                            <td style="padding:10px 14px;color:#6a7289;font-size:12px;direction:ltr">
                                {{ $log->created_at?->format('Y-m-d H:i') }}
                            </td>
                            <td style="padding:10px 14px">
                                @if ($log->superAdmin)
                                    {{ $log->superAdmin->name }}
                                    <div style="font-size:11px;color:#8a91a4" dir="ltr">{{ $log->superAdmin->email }}</div>
                                @else
                                    <span style="color:#8a91a4">{{ $log->actor_type }}</span>
                                @endif
                            </td>
                            <td style="padding:10px 14px">
                                <span style="background:#eef1f6;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:600">
                                    {{ $log->actionLabel() }}
                                </span>
                            </td>
                            <td style="padding:10px 14px;font-size:12px;color:#485068">
                                @if ($log->target_type)
                                    <code style="background:#f5f7fb;padding:1px 6px;border-radius:4px;font-size:11px;direction:ltr">
                                        {{ class_basename($log->target_type) }}#{{ $log->target_id }}
                                    </code>
                                @else —
                                @endif
                            </td>
                            <td style="padding:10px 14px;color:#485068;max-width:220px">{{ $log->reason ?? '—' }}</td>
                            <td style="padding:10px 14px;color:#8a91a4;font-size:11px;direction:ltr">{{ $log->ip_address ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div style="margin-top:16px">{{ $logs->links() }}</div>
    @endif
@endsection
