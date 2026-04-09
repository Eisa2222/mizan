<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">{{ $discussion->title }}</div>
                <div class="mz-page-sub">
                    على المستند: <a href="{{ route('documents.show', $discussion->document) }}" style="color:var(--gold);text-decoration:none">{{ $discussion->document->title }}</a>
                </div>
            </div>
            <a href="{{ route('documents.show', $discussion->document) }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة للمستند</a>
        </div>

        {{-- Original post --}}
        <div class="mz-card">
            <div class="mz-card-body">
                <div style="display:flex;gap:12px;margin-bottom:14px">
                    <div style="width:42px;height:42px;border-radius:10px;background:linear-gradient(135deg,var(--gold),var(--gl));display:grid;place-items:center;color:var(--bg);font-size:15px;font-weight:700">
                        {{ mb_substr($discussion->user?->name ?? '?', 0, 1) }}
                    </div>
                    <div style="flex:1">
                        <div style="font-size:14px;font-weight:700;color:var(--cream)">{{ $discussion->user?->name }}</div>
                        <div style="font-size:11px;color:var(--mute)">{{ $discussion->created_at->diffForHumans() }}</div>
                    </div>
                    @if ($discussion->user_id === auth()->id())
                        <form method="POST" action="{{ route('discussions.destroy', $discussion) }}"
                              onsubmit="return confirm('حذف النقاش؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background:transparent;border:none;color:var(--red);cursor:pointer;font-size:13px">🗑</button>
                        </form>
                    @endif
                </div>
                <p style="font-size:14px;color:var(--cream);line-height:1.85;white-space:pre-wrap">{{ $discussion->body }}</p>
            </div>
        </div>

        {{-- Replies --}}
        <div class="mz-card">
            <div class="mz-card-head">
                <div class="mz-card-title">الردود ({{ $discussion->replies->count() }})</div>
            </div>
            <div class="mz-card-body">
                @forelse ($discussion->replies as $reply)
                    <div style="display:flex;gap:12px;margin-bottom:14px;padding-bottom:14px;border-bottom:1px solid var(--borderl)">
                        <div style="width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,var(--blue),var(--blue-d));display:grid;place-items:center;color:#fff;font-size:13px;font-weight:700;flex-shrink:0">
                            {{ mb_substr($reply->user?->name ?? '?', 0, 1) }}
                        </div>
                        <div style="flex:1">
                            <div style="display:flex;justify-content:space-between;margin-bottom:4px">
                                <span style="font-size:13px;font-weight:700;color:var(--cream)">{{ $reply->user?->name }}</span>
                                <span style="font-size:11px;color:var(--mute)">{{ $reply->created_at->diffForHumans() }}</span>
                            </div>
                            <p style="font-size:13px;color:var(--dim);line-height:1.75;white-space:pre-wrap">{{ $reply->body }}</p>
                        </div>
                    </div>
                @empty
                    <p style="text-align:center;color:var(--mute);font-size:13px;padding:16px">لا توجد ردود بعد</p>
                @endforelse

                <form method="POST" action="{{ route('discussions.reply', $discussion) }}" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--borderl)">
                    @csrf
                    <textarea name="body" required rows="3" placeholder="اكتب ردك..." class="mz-inp mz-textarea"></textarea>
                    <button type="submit" class="mz-btn mz-btn-gold mz-btn-sm" style="margin-top:10px">إرسال الرد</button>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
