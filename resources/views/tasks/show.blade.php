<x-app-layout>
    @php
        $statuses = \App\Models\Task::STATUSES;
        $statusBadgeClass = [1=>'mz-badge-grey', 2=>'mz-badge-blue', 3=>'mz-badge-orange', 4=>'mz-badge-green', 5=>'mz-badge-red'];
        $priClass = [1=>'mz-pri-low', 2=>'mz-pri-low', 3=>'mz-pri-med', 4=>'mz-pri-high'];
    @endphp

    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">{{ $task->title }}</div>
                <div class="mz-page-sub">أنشأها {{ $task->creator?->name }} · {{ $task->created_at->diffForHumans() }}</div>
            </div>
            <a href="{{ route('tasks.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm">← العودة للكانبان</a>
        </div>

        <div style="display:grid;grid-template-columns:1fr 320px;gap:16px">
            {{-- Main column --}}
            <div style="display:flex;flex-direction:column;gap:16px;min-width:0">

                {{-- Task details --}}
                <div class="mz-card">
                    <div class="mz-card-body">
                        <div style="display:flex;gap:8px;align-items:center;margin-bottom:14px;flex-wrap:wrap">
                            <span class="mz-badge {{ $statusBadgeClass[$task->status] }}">{{ $statuses[$task->status] }}</span>
                            <span class="mz-tc-pri {{ $priClass[$task->priority] }}">أولوية {{ \App\Models\Task::PRIORITIES[$task->priority] }}</span>
                            @if ($task->due_date)
                                <span style="font-size:11px;color:var(--mute)">📅 {{ $task->due_date->format('Y-m-d') }}</span>
                            @endif
                        </div>

                        @if ($task->description)
                            <p style="font-size:14px;color:var(--cream);line-height:1.85;white-space:pre-wrap">{{ $task->description }}</p>
                        @else
                            <p style="font-size:13px;color:var(--mute);font-style:italic">لا يوجد وصف</p>
                        @endif
                    </div>
                </div>

                {{-- Comments --}}
                <div class="mz-card">
                    <div class="mz-card-head">
                        <div class="mz-card-title">التعليقات ({{ $task->comments->count() }})</div>
                    </div>
                    <div class="mz-card-body">
                        @forelse ($task->comments as $comment)
                            <div style="display:flex;gap:12px;margin-bottom:14px">
                                <div style="width:36px;height:36px;border-radius:9px;background:linear-gradient(135deg,var(--blue),var(--blue-d));display:grid;place-items:center;color:#fff;font-size:13px;font-weight:700;flex-shrink:0">
                                    {{ mb_substr($comment->user?->name ?? '?', 0, 1) }}
                                </div>
                                <div style="flex:1;background:var(--card2);border:1px solid var(--borderl);border-radius:10px;padding:11px 14px">
                                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                                        <span style="font-size:13px;font-weight:700;color:var(--cream)">{{ $comment->user?->name }}</span>
                                        <span style="font-size:11px;color:var(--mute)">{{ $comment->created_at->diffForHumans() }}</span>
                                    </div>
                                    <p style="font-size:13px;color:var(--dim);line-height:1.75;white-space:pre-wrap">{{ $comment->body }}</p>
                                </div>
                            </div>
                        @empty
                            <p style="text-align:center;padding:24px;color:var(--mute);font-size:13px">لا توجد تعليقات بعد</p>
                        @endforelse

                        <form method="POST" action="{{ route('tasks.comment', $task) }}" style="margin-top:16px;padding-top:16px;border-top:1px solid var(--borderl)">
                            @csrf
                            <textarea name="body" required rows="3" placeholder="اكتب تعليقاً..." class="mz-inp mz-textarea"></textarea>
                            <button type="submit" class="mz-btn mz-btn-gold mz-btn-sm" style="margin-top:10px">إضافة تعليق</button>
                        </form>
                    </div>
                </div>

                {{-- Activity log --}}
                <div class="mz-card">
                    <div class="mz-card-head">
                        <div class="mz-card-title">سجل النشاط</div>
                    </div>
                    <div class="mz-card-body">
                        @foreach ($task->activities as $act)
                            <div style="display:flex;gap:12px;padding-bottom:14px;position:relative">
                                <div style="width:24px;height:24px;border-radius:50%;background:var(--card2);border:2px solid var(--gold);display:grid;place-items:center;font-size:11px;color:var(--gold);flex-shrink:0">•</div>
                                <div style="flex:1">
                                    <div style="font-size:13px;color:var(--cream);line-height:1.5">
                                        <strong>{{ $act->user?->name }}</strong>
                                        @switch($act->action)
                                            @case('task.created') أنشأ المهمة @break
                                            @case('task.status_changed') نقلها من <b style="color:var(--gold)">{{ $act->metadata['from'] ?? '' }}</b> إلى <b style="color:var(--gold)">{{ $act->metadata['to'] ?? '' }}</b> @break
                                            @case('task.assigned') كلّف مستخدماً @break
                                            @case('task.unassigned') ألغى تكليفاً @break
                                            @case('task.commented') أضاف تعليقاً @break
                                            @default {{ $act->action }}
                                        @endswitch
                                    </div>
                                    <div style="font-size:11px;color:var(--mute);margin-top:2px">{{ $act->created_at->diffForHumans() }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div style="display:flex;flex-direction:column;gap:16px">

                {{-- Assignees --}}
                <div class="mz-card">
                    <div class="mz-card-head">
                        <div class="mz-card-title">المكلفون ({{ $task->assignments->count() }})</div>
                    </div>
                    <div class="mz-card-body">
                        @forelse ($task->assignments as $a)
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px;background:var(--card2);border-radius:8px;margin-bottom:6px">
                                <div style="display:flex;gap:10px;align-items:center">
                                    <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--blue),var(--blue-d));display:grid;place-items:center;color:#fff;font-size:12px;font-weight:700">
                                        {{ mb_substr($a->user?->name ?? '?', 0, 1) }}
                                    </div>
                                    <div>
                                        <div style="font-size:12.5px;font-weight:700;color:var(--cream)">{{ $a->user?->name }}</div>
                                        <div style="font-size:10px;color:var(--mute)">{{ \App\Models\TaskAssignment::ROLES[$a->role] ?? '' }}</div>
                                    </div>
                                </div>
                                <form method="POST" action="{{ route('tasks.unassign', [$task, $a->user_id]) }}">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" style="background:transparent;border:none;color:var(--red);cursor:pointer;font-size:14px">✕</button>
                                </form>
                            </div>
                        @empty
                            <p style="font-size:12px;color:var(--mute);text-align:center;padding:12px">لا يوجد مكلفون</p>
                        @endforelse

                        @if ($availableUsers->count() > 0)
                            <form method="POST" action="{{ route('tasks.assign', $task) }}" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--borderl)">
                                @csrf
                                <select name="user_id" required class="mz-inp" style="margin-bottom:8px">
                                    <option value="">اختر مستخدماً...</option>
                                    @foreach ($availableUsers as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                                <select name="role" required class="mz-inp" style="margin-bottom:10px">
                                    @foreach (\App\Models\TaskAssignment::ROLES as $id => $label)
                                        <option value="{{ $id }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="mz-btn mz-btn-gold mz-btn-sm" style="width:100%;justify-content:center">تكليف</button>
                            </form>
                        @endif
                    </div>
                </div>

                {{-- Status quick change --}}
                <div class="mz-card">
                    <div class="mz-card-head">
                        <div class="mz-card-title">تغيير الحالة</div>
                    </div>
                    <div class="mz-card-body" style="display:flex;flex-direction:column;gap:8px">
                        @foreach ([1,2,3,4,5] as $st)
                            @if ($st !== $task->status)
                                <form method="POST" action="{{ route('tasks.status', $task) }}">
                                    @csrf
                                    @method('PATCH')
                                    <input type="hidden" name="status" value="{{ $st }}">
                                    <button type="submit" class="mz-btn mz-btn-ghost mz-btn-sm" style="width:100%;justify-content:flex-start">
                                        → {{ $statuses[$st] }}
                                    </button>
                                </form>
                            @endif
                        @endforeach

                        <form method="POST" action="{{ route('tasks.destroy', $task) }}" style="margin-top:8px;padding-top:8px;border-top:1px solid var(--borderl)"
                              onsubmit="return confirm('هل أنت متأكد من حذف هذه المهمة؟')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" style="background:transparent;border:none;color:var(--red);font-size:12px;cursor:pointer;font-family:inherit">🗑 حذف المهمة</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
