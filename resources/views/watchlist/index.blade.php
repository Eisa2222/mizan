@section('title', 'المتابعة')

<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">قائمة المتابعة</div>
                <div class="mz-page-sub">{{ $items->total() }} مستند تتابعه · ستصلك إشعارات بأي تحديث</div>
            </div>
        </div>

        <div style="display:flex;flex-direction:column;gap:12px">
            @forelse ($items as $item)
                @if ($item->document)
                    <div class="mz-card" style="padding:18px 20px;display:flex;align-items:center;gap:14px">
                        <div style="width:42px;height:42px;border-radius:11px;background:rgba(200,169,75,.1);display:grid;place-items:center;font-size:19px">👁</div>
                        <a href="{{ route('documents.show', $item->document) }}" style="flex:1;text-decoration:none;color:inherit">
                            <div style="font-size:14px;font-weight:700;color:var(--cream)">{{ $item->document->title }}</div>
                            <div style="font-size:11px;color:var(--mute);margin-top:3px">
                                {{ $item->document->type_label }} · بدأت المتابعة {{ $item->created_at->diffForHumans() }}
                            </div>
                        </a>
                        <form method="POST" action="{{ route('watchlist.toggle', $item->document) }}">
                            @csrf
                            <button type="submit" class="mz-btn mz-btn-ghost mz-btn-xs" style="color:var(--red);border-color:rgba(224,85,85,.25)">إلغاء المتابعة</button>
                        </form>
                    </div>
                @endif
            @empty
                <x-empty-state
                    icon="👁"
                    title="لا تتابع أي مستند بعد"
                    :action="route('documents.index')"
                    actionLabel="تصفّح المستندات" />
            @endforelse
        </div>

        <x-pagination :paginator="$items" />
    </div>
</x-app-layout>
