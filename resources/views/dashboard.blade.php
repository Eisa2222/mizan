@section('title', 'لوحة التحكم')

<x-app-layout>
    @php
        $statusLabels = \App\Models\Task::STATUSES;
        $typeLabels = \App\Models\LegalDocument::TYPES;
        $priorityClasses = [1=>'mz-pri-low', 2=>'mz-pri-low', 3=>'mz-pri-med', 4=>'mz-pri-high'];
        $statusBadgeClass = [1=>'mz-badge-grey', 2=>'mz-badge-blue', 3=>'mz-badge-orange', 4=>'mz-badge-green', 5=>'mz-badge-red'];
    @endphp

    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <h1 class="mz-page-title">مرحباً، {{ auth()->user()->name }} 👋</h1>
                <div class="mz-page-sub">{{ now()->locale('ar')->isoFormat('dddd، D MMMM YYYY') }}</div>
            </div>
            <a href="{{ route('search') }}" class="mz-btn mz-btn-gold" aria-label="ابدأ بحثاً قانونياً جديداً">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                بحث قانوني جديد
            </a>
        </div>

        {{-- Primary stat cards --}}
        <div class="mz-g4" role="list" aria-label="إحصائيات رئيسية">
            <a href="{{ route('documents.index') }}" class="mz-stat" style="--accent:var(--gold);text-decoration:none"
               role="listitem"
               aria-label="{{ number_format($documentsCount) }} مستند قانوني — اضغط للعرض">
                <div class="mz-stat-icon" aria-hidden="true">📚</div>
                <div class="mz-stat-val" aria-hidden="true">{{ number_format($documentsCount) }}</div>
                <div class="mz-stat-label" aria-hidden="true">المستندات القانونية</div>
            </a>
            <a href="{{ route('tasks.index') }}" class="mz-stat" style="--accent:var(--blue);text-decoration:none"
               role="listitem"
               aria-label="{{ number_format($tasksCount) }} مهمة في الجهة — اضغط للعرض">
                <div class="mz-stat-icon" aria-hidden="true">✅</div>
                <div class="mz-stat-val" aria-hidden="true">{{ number_format($tasksCount) }}</div>
                <div class="mz-stat-label" aria-hidden="true">إجمالي مهام الجهة</div>
            </a>
            <a href="{{ route('tasks.index') }}" class="mz-stat" style="--accent:var(--green);text-decoration:none"
               role="listitem"
               aria-label="{{ number_format($tasksByStatus[2] ?? 0) }} مهمة قيد التنفيذ">
                <div class="mz-stat-icon" aria-hidden="true">⏳</div>
                <div class="mz-stat-val" aria-hidden="true">{{ number_format($tasksByStatus[2] ?? 0) }}</div>
                <div class="mz-stat-label" aria-hidden="true">قيد التنفيذ</div>
            </a>
            <a href="{{ route('tasks.index') }}" class="mz-stat" style="--accent:var(--red);text-decoration:none"
               role="listitem"
               aria-label="{{ number_format($overdueTasks) }} مهمة متأخرة تحتاج متابعة">
                <div class="mz-stat-icon" aria-hidden="true">⚠️</div>
                <div class="mz-stat-val" aria-hidden="true">{{ number_format($overdueTasks) }}</div>
                <div class="mz-stat-label" aria-hidden="true">مهام متأخرة</div>
            </a>
        </div>

        {{-- Secondary stats --}}
        <div class="mz-g4" style="margin-top:14px" role="list" aria-label="إحصائيات الخدمات القانونية">
            <a href="{{ route('contract-reviews.index') }}" class="mz-stat" style="--accent:#c8a94b;text-decoration:none"
               role="listitem"
               aria-label="{{ number_format($contractReviewsCount ?? 0) }} عقد تمت مراجعته">
                <div class="mz-stat-icon" aria-hidden="true">📝</div>
                <div class="mz-stat-val" aria-hidden="true">{{ number_format($contractReviewsCount ?? 0) }}</div>
                <div class="mz-stat-label" aria-hidden="true">عقود تمت مراجعتها</div>
            </a>
            <a href="{{ route('tenders.index') }}" class="mz-stat" style="--accent:#b88bff;text-decoration:none"
               role="listitem"
               aria-label="{{ number_format($tendersCount ?? 0) }} كراسة مولّدة">
                <div class="mz-stat-icon" aria-hidden="true">✨</div>
                <div class="mz-stat-val" aria-hidden="true">{{ number_format($tendersCount ?? 0) }}</div>
                <div class="mz-stat-label" aria-hidden="true">كراسات مولّدة</div>
            </a>
            <a href="{{ route('memos.index') }}" class="mz-stat" style="--accent:#7dd3c0;text-decoration:none"
               role="listitem"
               aria-label="{{ number_format($memosCount ?? 0) }} مذكرة محللة">
                <div class="mz-stat-icon" aria-hidden="true">📄</div>
                <div class="mz-stat-val" aria-hidden="true">{{ number_format($memosCount ?? 0) }}</div>
                <div class="mz-stat-label" aria-hidden="true">مذكرات محللة</div>
            </a>
            <a href="{{ route('notifications.index') }}" class="mz-stat" style="--accent:#f5a26b;text-decoration:none"
               role="listitem"
               aria-label="{{ number_format($unreadNotificationsCount ?? 0) }} إشعار غير مقروء">
                <div class="mz-stat-icon" aria-hidden="true">🔔</div>
                <div class="mz-stat-val" aria-hidden="true">{{ number_format($unreadNotificationsCount ?? 0) }}</div>
                <div class="mz-stat-label" aria-hidden="true">إشعارات غير مقروءة</div>
            </a>
        </div>

        {{-- Quick actions + activity --}}
        <div class="mz-g2">
            <div>
                <div style="font-size:13px;font-weight:700;margin-bottom:12px;color:var(--dim)">الإجراءات السريعة</div>
                <div class="mz-quick-actions">
                    <a href="{{ route('documents.create') }}" class="mz-qa">
                        <div class="mz-qa-icon">📤</div>
                        <div class="mz-qa-label">رفع مستند</div>
                        <div class="mz-qa-sub">PDF / Word</div>
                    </a>
                    <a href="{{ route('tasks.index') }}" class="mz-qa">
                        <div class="mz-qa-icon">➕</div>
                        <div class="mz-qa-label">مهمة جديدة</div>
                        <div class="mz-qa-sub">Kanban</div>
                    </a>
                    <a href="{{ route('documents.index') }}" class="mz-qa">
                        <div class="mz-qa-icon">🔍</div>
                        <div class="mz-qa-label">بحث متقدم</div>
                        <div class="mz-qa-sub">في المستندات</div>
                    </a>
                    <a href="{{ route('notifications.index') }}" class="mz-qa">
                        <div class="mz-qa-icon">🔔</div>
                        <div class="mz-qa-label">الإشعارات</div>
                        <div class="mz-qa-sub">{{ $unreadNotificationsCount ?? 0 }} جديدة</div>
                    </a>
                </div>

                <div style="font-size:13px;font-weight:700;margin:18px 0 12px;color:var(--dim)">آخر النشاطات</div>
                <div class="mz-card">
                    <div class="mz-card-body">
                        <div class="mz-activity-list">
                            @forelse ($recentActivities as $act)
                                <div class="mz-act-item">
                                    <div class="mz-act-icon @switch($act->action) @case('task.created') gold @break @case('task.status_changed') blue @break @case('task.commented') green @break @default orange @endswitch">
                                        @switch($act->action)
                                            @case('task.created') 📝 @break
                                            @case('task.status_changed') 🔄 @break
                                            @case('task.commented') 💬 @break
                                            @case('task.assigned') 👤 @break
                                            @default •
                                        @endswitch
                                    </div>
                                    <div style="flex:1;min-width:0">
                                        <div class="mz-act-txt">
                                            <strong>{{ $act->user?->name ?? '—' }}</strong>
                                            @switch($act->action)
                                                @case('task.created') أنشأ مهمة @break
                                                @case('task.status_changed') غيّر حالة مهمة @break
                                                @case('task.commented') أضاف تعليقاً @break
                                                @case('task.assigned') كلّف مستخدماً @break
                                                @default {{ $act->action }}
                                            @endswitch
                                            — <span style="color:var(--dim)">{{ Str::limit($act->task?->title, 50) }}</span>
                                        </div>
                                        @if ($act->action === 'task.status_changed' && $act->metadata)
                                            <div style="font-size:11px;color:var(--mute);margin-top:2px">
                                                {{ $act->metadata['from'] ?? '' }} ← {{ $act->metadata['to'] ?? '' }}
                                            </div>
                                        @endif
                                        <div class="mz-act-time">{{ $act->created_at->diffForHumans() }}</div>
                                    </div>
                                </div>
                            @empty
                                <div style="text-align:center;padding:32px;color:var(--mute);font-size:13px">لا توجد نشاطات بعد</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div>
                {{-- Tasks by status --}}
                <div style="font-size:13px;font-weight:700;margin-bottom:12px;color:var(--dim)">توزيع المهام</div>
                <div class="mz-card">
                    <div class="mz-card-body">
                        @if ($tasksCount === 0)
                            <p style="text-align:center;padding:24px;color:var(--mute);font-size:13px">لا توجد مهام بعد</p>
                        @else
                            @foreach ([1,2,3,4,5] as $st)
                                @php $cnt = $tasksByStatus[$st] ?? 0; $pct = $tasksCount > 0 ? round($cnt*100/$tasksCount) : 0; @endphp
                                <div style="margin-bottom:12px">
                                    <div style="display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px">
                                        <span style="color:var(--cream);font-weight:600">{{ $statusLabels[$st] }}</span>
                                        <span style="color:var(--mute)">{{ $cnt }} ({{ $pct }}%)</span>
                                    </div>
                                    <div style="width:100%;height:6px;background:var(--card2);border-radius:3px;overflow:hidden">
                                        <div style="height:100%;width:{{ $pct }}%;background:{{ ['1'=>'#4a5568','2'=>'#3d82ff','3'=>'#e07830','4'=>'#3dbf8a','5'=>'#e05555'][$st] }};border-radius:3px"></div>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                </div>

                {{-- My open tasks --}}
                <div style="font-size:13px;font-weight:700;margin:18px 0 12px;color:var(--dim)">مهامي المفتوحة</div>
                <div class="mz-card">
                    <div class="mz-card-body" style="padding:8px">
                        @forelse ($myOpenTasks as $task)
                            <a href="{{ route('tasks.show', $task) }}" style="display:block;padding:10px 12px;border-radius:8px;text-decoration:none;color:inherit;border-bottom:1px solid var(--borderl)">
                                <div style="font-size:13px;font-weight:600;color:var(--cream);margin-bottom:4px">{{ $task->title }}</div>
                                <div style="display:flex;gap:8px;align-items:center;font-size:11px">
                                    <span class="mz-badge {{ $statusBadgeClass[$task->status] }}">{{ $statusLabels[$task->status] }}</span>
                                    <span class="mz-tc-pri {{ $priorityClasses[$task->priority] }}">{{ \App\Models\Task::PRIORITIES[$task->priority] }}</span>
                                    @if ($task->due_date)
                                        <span style="color:var(--mute)">📅 {{ $task->due_date->format('Y-m-d') }}</span>
                                    @endif
                                </div>
                            </a>
                        @empty
                            <p style="text-align:center;padding:24px;color:var(--mute);font-size:13px">لا توجد مهام مفتوحة 🎉</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent documents --}}
        <div>
            <div style="font-size:13px;font-weight:700;margin-bottom:12px;color:var(--dim)">أحدث المستندات</div>
            <div class="mz-card">
                <div class="mz-card-body" style="padding:8px">
                    @forelse ($recentDocuments as $doc)
                        <a href="{{ route('documents.show', $doc) }}"
                           style="display:flex;justify-content:space-between;align-items:center;padding:12px 14px;border-radius:8px;text-decoration:none;color:inherit;border-bottom:1px solid var(--borderl)">
                            <div style="flex:1;min-width:0">
                                <div style="font-size:13px;font-weight:700;color:var(--cream)">{{ $doc->title }}</div>
                                @if ($doc->title_en)
                                    <div style="font-size:11px;color:var(--mute);direction:ltr;text-align:right">{{ $doc->title_en }}</div>
                                @endif
                            </div>
                            <span class="mz-badge mz-badge-gold">{{ $doc->type_label }}</span>
                        </a>
                    @empty
                        <p style="text-align:center;padding:24px;color:var(--mute);font-size:13px">لا توجد مستندات بعد</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- Workflow surfacing (Layer E10): recent article updates + unread notifications --}}
        <div class="mz-g2">
            <div>
                <div style="font-size:13px;font-weight:700;margin-bottom:12px;color:var(--dim)">آخر التحديثات على المواد</div>
                <div class="mz-card">
                    <div class="mz-card-body" style="padding:8px">
                        @forelse ($recentArticleUpdates as $u)
                            <a href="{{ route('documents.show', $u->document_id) . '#tab-timeline' }}"
                               style="display:block;padding:10px 14px;border-bottom:1px solid var(--borderl);text-decoration:none;color:inherit">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:4px">
                                    <span style="font-size:12px;font-weight:700;color:var(--gold)">🔁 {{ $u->article_label }}</span>
                                    <span style="font-size:11px;color:var(--mute)">{{ $u->update_date->format('Y-m-d') }}</span>
                                </div>
                                <div style="font-size:11px;color:var(--dim);margin-bottom:4px">{{ Str::limit($u->document?->title ?? '—', 60) }}</div>
                                <div style="font-size:12px;color:var(--cream);line-height:1.6">{{ Str::limit($u->body, 120) }}</div>
                                @if ($u->auto_generated)
                                    <span class="mz-badge mz-badge-grey" style="margin-top:4px;font-size:9px">🤖 تلقائي</span>
                                @endif
                            </a>
                        @empty
                            <p style="text-align:center;padding:24px;color:var(--mute);font-size:13px">لا توجد تحديثات على المواد بعد</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <div>
                <div style="font-size:13px;font-weight:700;margin-bottom:12px;color:var(--dim)">إشعارات غير مقروءة</div>
                <div class="mz-card">
                    <div class="mz-card-body" style="padding:8px">
                        @forelse ($unreadNotifications as $n)
                            <a href="{{ ($n->data['document_id'] ?? null) ? route('documents.show', $n->data['document_id']) : route('notifications.index') }}"
                               style="display:block;padding:10px 14px;border-bottom:1px solid var(--borderl);text-decoration:none;color:inherit">
                                <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;margin-bottom:4px">
                                    <span style="font-size:12px;font-weight:700;color:var(--cream)">{{ $n->title }}</span>
                                    <span style="font-size:11px;color:var(--mute)">{{ $n->created_at->diffForHumans() }}</span>
                                </div>
                                @if ($n->body)
                                    <div style="font-size:12px;color:var(--dim);line-height:1.6">{{ Str::limit($n->body, 140) }}</div>
                                @endif
                            </a>
                        @empty
                            <p style="text-align:center;padding:24px;color:var(--mute);font-size:13px">لا توجد إشعارات جديدة 🎉</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
