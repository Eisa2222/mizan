@extends('super-admin.layouts.app')
@section('title', 'الأسئلة الشائعة')
@section('heading', 'الأسئلة الشائعة')

@section('content')
    <div class="sa-card" style="margin-bottom:18px">
        <h3 class="sa-card-title">إضافة سؤال</h3>
        <form method="POST" action="{{ route('super-admin.landing.faqs.store') }}">
            @csrf
            <input name="question" required placeholder="السؤال" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit;margin-bottom:8px">
            <textarea name="answer" required rows="3" placeholder="الإجابة" style="width:100%;padding:8px 12px;border:1px solid #d4d9e3;border-radius:6px;font-family:inherit;margin-bottom:8px"></textarea>
            <button class="sa-btn">+ إضافة</button>
        </form>
    </div>

    <div class="sa-card" style="padding:0;overflow:hidden">
        <p style="padding:12px 16px;background:#f5f7fb;font-size:12px;color:#6a7289;margin:0">💡 اسحب لإعادة الترتيب.</p>
        <table class="sa-table">
            <thead><tr><th style="width:30px"></th><th>السؤال</th><th>الحالة</th><th></th></tr></thead>
            <tbody id="faqs-tbody">
                @forelse ($faqs as $faq)
                    <tr data-id="{{ $faq->id }}">
                        <td style="cursor:grab;color:#c0c6d7">⋮⋮</td>
                        <td>
                            <strong>{{ $faq->question }}</strong>
                            <div style="color:#6a7289;font-size:12px;margin-top:4px;line-height:1.6">{{ \Illuminate\Support\Str::limit($faq->answer, 140) }}</div>
                        </td>
                        <td>
                            @if ($faq->is_active) <span class="sa-badge sa-badge-green">نشط</span>
                            @else <span class="sa-badge sa-badge-grey">موقوف</span> @endif
                        </td>
                        <td>
                            <form method="POST" action="{{ route('super-admin.landing.faqs.destroy', $faq) }}" onsubmit="return confirm('حذف السؤال؟')">
                                @csrf @method('DELETE')
                                <button class="sa-btn sa-btn-ghost" style="padding:4px 10px">حذف</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" style="text-align:center;padding:32px;color:#8a91a4">لا توجد أسئلة — أضف أول سؤال من الأعلى.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.0/Sortable.min.js"></script>
        <script>
            const tbody = document.getElementById('faqs-tbody');
            if (tbody && tbody.children.length > 0 && tbody.children[0].dataset.id) {
                Sortable.create(tbody, {
                    handle: 'td:first-child',
                    onEnd: () => {
                        const ids = Array.from(tbody.querySelectorAll('tr[data-id]')).map(r => parseInt(r.dataset.id));
                        fetch('{{ route('super-admin.landing.faqs.reorder') }}', {
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
