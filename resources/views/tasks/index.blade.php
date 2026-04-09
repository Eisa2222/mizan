<x-app-layout>
    @php
        $columns = [1, 2, 3, 4]; // exclude Cancelled (5)
        $colorMap = [1=>'#4a5568', 2=>'#3d82ff', 3=>'#e07830', 4=>'#3dbf8a'];
        $priClass = [1=>'mz-pri-low', 2=>'mz-pri-low', 3=>'mz-pri-med', 4=>'mz-pri-high'];
        $statuses = \App\Models\Task::STATUSES;
    @endphp

    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">سير العمل والمهام</div>
                <div class="mz-page-sub">
                    {{ $tasks->whereIn('status', [1,2,3])->count() }} مهام نشطة ·
                    {{ $tasks->where('status', 4)->count() }} مكتملة
                </div>
            </div>
            <button onclick="document.getElementById('create-form').style.display = document.getElementById('create-form').style.display === 'none' ? 'block' : 'none'"
                    class="mz-btn mz-btn-gold">+ مهمة جديدة</button>
        </div>

        {{-- Create form (collapsed) --}}
        <form id="create-form" method="POST" action="{{ route('tasks.store') }}"
              class="mz-card" style="display:none;padding:18px">
            @csrf
            <div class="mz-form-group">
                <label class="mz-flabel">عنوان المهمة *</label>
                <input type="text" name="title" required placeholder="مثال: مراجعة عقد التوريد..." class="mz-inp">
            </div>
            <div class="mz-form-group">
                <label class="mz-flabel">الوصف</label>
                <textarea name="description" rows="2" class="mz-inp mz-textarea" placeholder="تفاصيل المهمة..."></textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                <div class="mz-form-group">
                    <label class="mz-flabel">الأولوية</label>
                    <select name="priority" class="mz-inp">
                        @foreach (\App\Models\Task::PRIORITIES as $id => $label)
                            <option value="{{ $id }}" @selected($id == 2)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="mz-form-group">
                    <label class="mz-flabel">تاريخ الاستحقاق</label>
                    <input type="date" name="due_date" class="mz-inp">
                </div>
            </div>
            <button type="submit" class="mz-btn mz-btn-gold">إنشاء المهمة</button>
        </form>

        {{-- Kanban --}}
        <div class="mz-kanban">
            @foreach ($columns as $status)
                @php $items = $tasks->where('status', $status); @endphp
                <div class="mz-kol kanban-col" data-status="{{ $status }}">
                    <div class="mz-kol-head">
                        <div class="mz-kol-title">
                            <span class="mz-kol-dot" style="background:{{ $colorMap[$status] }}"></span>
                            {{ $statuses[$status] }}
                            <span class="mz-kol-cnt">{{ $items->count() }}</span>
                        </div>
                    </div>
                    <div class="mz-kol-body">
                        @forelse ($items as $task)
                            <a href="{{ route('tasks.show', $task) }}"
                               draggable="true"
                               data-task-id="{{ $task->id }}"
                               data-current-status="{{ $task->status }}"
                               class="mz-task-card task-card"
                               style="--t-color:{{ $colorMap[$status] }}">
                                <div class="mz-tc-title">{{ $task->title }}</div>
                                @if ($task->description)
                                    <div style="font-size:11px;color:var(--mute);margin-bottom:8px;line-height:1.5">{{ Str::limit($task->description, 80) }}</div>
                                @endif
                                <div class="mz-tc-footer">
                                    <div class="mz-tc-meta">
                                        @if ($task->assignments_count > 0)
                                            <span title="مكلفون">👤 {{ $task->assignments_count }}</span>
                                        @endif
                                        @if ($task->comments_count > 0)
                                            <span title="تعليقات">💬 {{ $task->comments_count }}</span>
                                        @endif
                                    </div>
                                    <div style="display:flex;gap:6px;align-items:center">
                                        @if ($task->due_date)
                                            @php
                                                $daysLeft = now()->diffInDays($task->due_date, false);
                                                $dueClass = $daysLeft < 0 ? 'overdue' : ($daysLeft <= 3 ? 'soon' : '');
                                            @endphp
                                            <span class="mz-tc-due {{ $dueClass }}">📅 {{ $task->due_date->format('m-d') }}</span>
                                        @endif
                                        <span class="mz-tc-pri {{ $priClass[$task->priority] }}">{{ \App\Models\Task::PRIORITIES[$task->priority] }}</span>
                                    </div>
                                </div>
                                <div style="display:flex;justify-content:flex-end;margin-top:8px">
                                    <button type="button"
                                            onclick="event.preventDefault();event.stopPropagation();cancelTask({{ $task->id }}, '{{ addslashes($task->title) }}')"
                                            class="mz-tc-cancel">✕ إلغاء</button>
                                </div>
                            </a>
                        @empty
                            <p style="text-align:center;font-size:11px;color:var(--mute);padding:24px 0" class="empty-msg">لا توجد مهام</p>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('meta[name="csrf-token"]').content;
        let draggedCard = null;
        let suppressClick = false;

        function attachDnD() {
            document.querySelectorAll('.task-card').forEach(card => {
                card.addEventListener('click', e => {
                    if (suppressClick) { suppressClick = false; e.preventDefault(); }
                });
                card.addEventListener('dragstart', e => {
                    draggedCard = card;
                    suppressClick = true;
                    card.classList.add('dragging');
                    e.dataTransfer.effectAllowed = 'move';
                    e.dataTransfer.setData('text/plain', card.dataset.taskId);
                });
                card.addEventListener('dragend', () => {
                    card.classList.remove('dragging');
                    draggedCard = null;
                    document.querySelectorAll('.kanban-col').forEach(c => c.classList.remove('drag-over'));
                });
            });

            document.querySelectorAll('.kanban-col').forEach(col => {
                col.addEventListener('dragover', e => {
                    e.preventDefault();
                    e.dataTransfer.dropEffect = 'move';
                    col.classList.add('drag-over');
                });
                col.addEventListener('dragleave', () => col.classList.remove('drag-over'));
                col.addEventListener('drop', async e => {
                    e.preventDefault();
                    col.classList.remove('drag-over');
                    if (!draggedCard) return;
                    const taskId = draggedCard.dataset.taskId;
                    const newStatus = parseInt(col.dataset.status);
                    const currentStatus = parseInt(draggedCard.dataset.currentStatus);
                    if (currentStatus === newStatus) return;

                    col.querySelector('.mz-kol-body').appendChild(draggedCard);
                    draggedCard.dataset.currentStatus = newStatus;
                    col.querySelector('.empty-msg')?.remove();

                    try {
                        const res = await fetch(`/tasks/${taskId}/status`, {
                            method: 'PATCH',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json',
                            },
                            body: JSON.stringify({ status: newStatus }),
                        });
                        if (!res.ok) throw new Error('فشل');
                        location.reload();
                    } catch (err) {
                        alert('فشل نقل المهمة');
                        location.reload();
                    }
                });
            });
        }

        async function cancelTask(taskId, title) {
            if (!confirm(`هل تريد إلغاء المهمة "${title}"؟`)) return;
            const res = await fetch(`/tasks/${taskId}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ status: 5 }),
            });
            if (res.ok) location.reload();
            else alert('فشل الإلغاء');
        }

        attachDnD();
    </script>
</x-app-layout>
