@extends('super-admin.layouts.app')
@section('title', 'مميزات صفحة الهبوط')
@section('heading', 'مميزات صفحة الهبوط')

@section('content')
    <div class="sa-card" style="margin-bottom:18px">
        <h3 class="sa-card-title">إضافة ميزة</h3>
        <form method="POST" action="{{ route('super-admin.landing.features.store') }}" style="display:grid;grid-template-columns:1fr 2fr 1fr auto;gap:10px">
            @csrf
            <input name="title" required placeholder="العنوان" style="padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            <input name="description" required placeholder="الوصف" style="padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            <input name="icon" placeholder="أيقونة (emoji أو SVG)" style="padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit">
            <button class="sa-btn">+ إضافة</button>
        </form>
    </div>

    <div class="sa-card" style="padding:0;overflow:hidden">
        <p style="padding:12px 16px;background:#f5f7fb;font-size:12px;color:#6a7289;margin:0">💡 اسحب الصفوف لإعادة الترتيب — يُحفظ تلقائياً.</p>
        <table class="sa-table">
            <thead><tr><th style="width:30px"></th><th>الأيقونة</th><th>العنوان</th><th>الوصف</th><th>الحالة</th><th></th></tr></thead>
            <tbody id="features-tbody">
                @forelse ($features as $f)
                    <tr data-id="{{ $f->id }}">
                        <td style="cursor:grab;color:#c0c6d7">⋮⋮</td>
                        <td style="font-size:20px">{!! str_starts_with(trim($f->icon ?? ''), '<') ? $f->icon : e($f->icon ?? '') !!}</td>
                        <td><strong>{{ $f->title }}</strong></td>
                        <td style="color:#6a7289;max-width:380px">{{ $f->description }}</td>
                        <td>
                            @if ($f->is_active) <span class="sa-badge sa-badge-green">نشط</span>
                            @else <span class="sa-badge sa-badge-grey">موقوف</span> @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('super-admin.landing.features.destroy', $f) }}" onsubmit="return confirm('حذف هذه الميزة؟')">
                                @csrf @method('DELETE')
                                <button class="sa-btn sa-btn-ghost" style="padding:4px 10px">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" style="text-align:center;padding:32px;color:#8a91a4">لا توجد مميزات — أضف أول واحدة من الأعلى.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
        <script>
            const tbody = document.getElementById('features-tbody');
            if (tbody && tbody.children.length > 0 && tbody.children[0].dataset.id) {
                Sortable.create(tbody, {
                    handle: 'td:first-child',
                    onEnd: () => {
                        const ids = Array.from(tbody.querySelectorAll('tr[data-id]')).map(r => parseInt(r.dataset.id));
                        fetch('{{ route('super-admin.landing.features.reorder') }}', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                            body: JSON.stringify({ ids })
                        });
                    }
                });
            }
        </script>
    @endpush
@endsection
