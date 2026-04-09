{{-- Floating selection toolbar + modals for annotations, discussions, tasks, notes --}}
{{-- Include in any show page that has .mz-selectable elements --}}

<div id="sel-toolbar" class="mz-sel-toolbar">
    <div id="sel-quote" class="mz-sel-quote"></div>
    <div class="mz-sel-actions">
        <button type="button" class="mz-sel-color" data-color="gold"><span class="dot" style="background:#c8a94b"></span> ذهبي</button>
        <button type="button" class="mz-sel-color" data-color="blue"><span class="dot" style="background:#3d82ff"></span> أزرق</button>
        <button type="button" class="mz-sel-color" data-color="green"><span class="dot" style="background:#3dbf8a"></span> أخضر</button>
        <button type="button" class="mz-sel-color" data-color="red"><span class="dot" style="background:#e05555"></span> أحمر</button>

        <span class="mz-sel-divider"></span>

        <select id="sel-visibility" class="mz-sel-vis" title="نطاق الرؤية">
            <option value="public">🌍 عامة</option>
            <option value="org" selected>🏢 المؤسسة</option>
            <option value="private">🔒 خاصة</option>
        </select>

        <span class="mz-sel-divider"></span>

        <button type="button" id="sel-note" class="mz-sel-action gold">📌 ملاحظة</button>
        <button type="button" id="sel-task" class="mz-sel-action green">📝 مهمة</button>
        <button type="button" id="sel-discuss" class="mz-sel-action blue">💬 نقاش</button>
        <button type="button" id="sel-copy" class="mz-sel-action dark">📋 نسخ</button>

        <button type="button" id="sel-close" class="mz-sel-close">✕</button>
    </div>
</div>

{{-- Note modal --}}
<div id="modal-note" class="mz-modal-overlay" onclick="if(event.target===this)closeModal('modal-note')">
    <div class="mz-modal" style="max-width:560px">
        <div class="mz-modal-head">
            <div class="mz-modal-title">📌 إضافة ملاحظة</div>
            <button type="button" class="mz-modal-close" onclick="closeModal('modal-note')">✕</button>
        </div>
        <form method="POST" action="{{ route('annotations.store', $document) }}">
            @csrf
            <input type="hidden" name="selected_text" id="note-selected-text">
            <div class="mz-modal-body">
                <div class="mz-form-group">
                    <label class="mz-flabel">النص المُحدَّد</label>
                    <div id="note-quote" style="font-size:12px;color:var(--dim);background:var(--card2);padding:10px 12px;border-radius:8px;line-height:1.6;max-height:80px;overflow-y:auto;border-right:3px solid var(--gold)"></div>
                </div>
                <div class="mz-form-group">
                    <label class="mz-flabel">التعليق *</label>
                    <textarea name="comment" id="note-comment" required rows="3" class="mz-inp mz-textarea" placeholder="اكتب ملاحظتك..."></textarea>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <div class="mz-form-group"><label class="mz-flabel">اللون</label><select name="color" class="mz-inp"><option value="gold">🟡 ذهبي</option><option value="blue">🔵 أزرق</option><option value="green">🟢 أخضر</option><option value="red">🔴 أحمر</option></select></div>
                    <div class="mz-form-group"><label class="mz-flabel">الرؤية</label><select name="visibility" class="mz-inp"><option value="public">🌍 عامة</option><option value="org" selected>🏢 المؤسسة</option><option value="private">🔒 خاصة</option></select></div>
                </div>
            </div>
            <div class="mz-modal-footer">
                <button type="button" class="mz-btn mz-btn-ghost" onclick="closeModal('modal-note')">إلغاء</button>
                <button type="submit" class="mz-btn mz-btn-gold">📌 حفظ</button>
            </div>
        </form>
    </div>
</div>

{{-- Discussion modal --}}
<div id="modal-discussion" class="mz-modal-overlay" onclick="if(event.target===this)closeModal('modal-discussion')">
    <div class="mz-modal">
        <div class="mz-modal-head">
            <div class="mz-modal-title">💬 فتح نقاش</div>
            <button type="button" class="mz-modal-close" onclick="closeModal('modal-discussion')">✕</button>
        </div>
        <form method="POST" action="{{ route('discussions.store', $document) }}">
            @csrf
            <div class="mz-modal-body">
                <div class="mz-form-group"><label class="mz-flabel">العنوان *</label><input type="text" name="title" required class="mz-inp"></div>
                <div class="mz-form-group"><label class="mz-flabel">المحتوى *</label><textarea name="body" id="disc-body" required rows="4" class="mz-inp mz-textarea"></textarea></div>
                <div class="mz-form-group"><label class="mz-flabel">الرؤية</label><select name="visibility" class="mz-inp"><option value="public">🌍 عامة</option><option value="org" selected>🏢 المؤسسة</option><option value="private">🔒 خاصة</option></select></div>
            </div>
            <div class="mz-modal-footer">
                <button type="button" class="mz-btn mz-btn-ghost" onclick="closeModal('modal-discussion')">إلغاء</button>
                <button type="submit" class="mz-btn mz-btn-gold">بدء النقاش</button>
            </div>
        </form>
    </div>
</div>

{{-- Task modal --}}
<div id="modal-task" class="mz-modal-overlay" onclick="if(event.target===this)closeModal('modal-task')">
    <div class="mz-modal">
        <div class="mz-modal-head">
            <div class="mz-modal-title">📝 إنشاء مهمة</div>
            <button type="button" class="mz-modal-close" onclick="closeModal('modal-task')">✕</button>
        </div>
        <form method="POST" action="{{ route('tasks.store') }}">
            @csrf
            <input type="hidden" name="document_id" value="{{ $document->id }}">
            <div class="mz-modal-body">
                <div class="mz-form-group"><label class="mz-flabel">العنوان *</label><input type="text" name="title" required class="mz-inp"></div>
                <div class="mz-form-group"><label class="mz-flabel">الوصف</label><textarea name="description" id="task-desc" rows="3" class="mz-inp mz-textarea"></textarea></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px">
                    <div class="mz-form-group"><label class="mz-flabel">الأولوية</label><select name="priority" class="mz-inp">@foreach(\App\Models\Task::PRIORITIES as $id=>$label)<option value="{{ $id }}" @selected($id==2)>{{ $label }}</option>@endforeach</select></div>
                    <div class="mz-form-group"><label class="mz-flabel">تاريخ الاستحقاق</label><input type="date" name="due_date" class="mz-inp"></div>
                </div>
            </div>
            <div class="mz-modal-footer">
                <button type="button" class="mz-btn mz-btn-ghost" onclick="closeModal('modal-task')">إلغاء</button>
                <button type="submit" class="mz-btn mz-btn-gold">إنشاء</button>
            </div>
        </form>
    </div>
</div>

{{-- Hidden annotation form (for quick color highlights) --}}
<form id="annot-form" method="POST" action="{{ route('annotations.store', $document) }}" style="display:none">
    @csrf
    <input type="hidden" name="selected_text" id="annot-text">
    <input type="hidden" name="color" id="annot-color">
    <input type="hidden" name="visibility" id="annot-visibility" value="org">
</form>

<div id="note-popover" class="mz-note-popover" style="display:none">
    <div id="note-popover-comment" class="mz-note-popover-body"></div>
    <div id="note-popover-meta" class="mz-note-popover-meta"></div>
</div>

<style>
    /* Inline annotation marks */
    .mz-note-mark {
        background: rgba(200,169,75,.2);
        border-bottom: 2px solid var(--gold);
        cursor: pointer;
        position: relative;
        border-radius: 2px;
        transition: background .15s;
    }
    .mz-note-mark:hover { background: rgba(200,169,75,.35); }
    .mz-note-mark[data-color="blue"]  { background: rgba(61,130,255,.15); border-bottom-color: #3d82ff; }
    .mz-note-mark[data-color="blue"]:hover { background: rgba(61,130,255,.3); }
    .mz-note-mark[data-color="green"] { background: rgba(61,191,138,.15); border-bottom-color: #3dbf8a; }
    .mz-note-mark[data-color="green"]:hover { background: rgba(61,191,138,.3); }
    .mz-note-mark[data-color="red"]   { background: rgba(224,85,85,.15); border-bottom-color: #e05555; }
    .mz-note-mark[data-color="red"]:hover { background: rgba(224,85,85,.3); }
    .mz-note-mark .mz-note-icon {
        display: inline-block;
        font-size: 10px;
        vertical-align: super;
        margin-right: 2px;
        opacity: .7;
    }

    /* Note popover (appears on click) */
    .mz-note-popover {
        position: fixed;
        z-index: 400;
        background: var(--surf);
        border: 1px solid var(--gold);
        border-radius: 10px;
        box-shadow: 0 8px 30px rgba(0,0,0,.5);
        padding: 12px 14px;
        max-width: 360px;
        min-width: 200px;
    }
    .mz-note-popover-body {
        font-size: 13px;
        color: var(--cream);
        line-height: 1.7;
        white-space: pre-wrap;
        margin-bottom: 6px;
    }
    .mz-note-popover-meta {
        font-size: 10px;
        color: var(--mute);
    }
</style>

<script>
    // Inline annotation highlights: mark annotated text in .mz-selectable areas
    (function() {
        const annotations = @json($annotations ?? collect());
        const popover = document.getElementById('note-popover');
        const popBody = document.getElementById('note-popover-comment');
        const popMeta = document.getElementById('note-popover-meta');

        function applyInlineAnnotations() {
            if (!annotations || !annotations.length) return;
            document.querySelectorAll('.mz-selectable').forEach(area => {
                annotations.forEach(ann => {
                    if (!ann.selected_text || ann.selected_text.length < 2) return;
                    const escaped = ann.selected_text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
                    const comment = (ann.comment || '').replace(/'/g, '&#39;').replace(/"/g, '&quot;');
                    const user = (ann.user?.name || '—').replace(/'/g, '&#39;');
                    const time = ann.created_at || '';
                    const vis = ann.visibility === 'private' ? '🔒' : ann.visibility === 'public' ? '🌍' : '🏢';
                    try {
                        area.innerHTML = area.innerHTML.replace(
                            escaped,
                            `<span class="mz-note-mark" data-color="${ann.color || 'gold'}" data-comment="${comment}" data-user="${user}" data-time="${time}" data-vis="${vis}"><span class="mz-note-icon">📌</span>${ann.selected_text}</span>`
                        );
                    } catch(e) {}
                });
            });
        }

        // Run after a short delay to let Alpine.js render tabs
        setTimeout(applyInlineAnnotations, 300);

        // Show popover on click
        document.addEventListener('click', function(e) {
            const mark = e.target.closest('.mz-note-mark');
            if (!mark) { popover.style.display = 'none'; return; }

            const comment = mark.dataset.comment;
            const user = mark.dataset.user;
            const vis = mark.dataset.vis;
            if (!comment && !user) { popover.style.display = 'none'; return; }

            popBody.textContent = comment || '(بدون تعليق)';
            popMeta.textContent = `${vis} ${user}`;

            // Position near the mark
            const rect = mark.getBoundingClientRect();
            let top = rect.bottom + 8;
            let left = rect.left + (rect.width / 2) - 120;
            if (left < 12) left = 12;
            if (left + 360 > window.innerWidth) left = window.innerWidth - 372;
            if (top + 150 > window.innerHeight) top = rect.top - 120;

            popover.style.top = top + 'px';
            popover.style.left = left + 'px';
            popover.style.display = 'block';
        });

        // Hide on escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') popover.style.display = 'none';
        });
    })();

    // Selection toolbar logic
    (function() {
        const toolbar = document.getElementById('sel-toolbar');
        const quoteEl = document.getElementById('sel-quote');
        if (!toolbar) return;

        function hideToolbar() { toolbar.classList.remove('open'); lastSelection = ''; }
        function showToolbar(rect, text) {
            quoteEl.textContent = '«' + text + '»';
            toolbar.classList.add('open');
            const tw = toolbar.offsetWidth, th = toolbar.offsetHeight;
            let top = rect.top - th - 10, left = rect.left + (rect.width / 2) - (tw / 2);
            if (left < 12) left = 12;
            if (left + tw > window.innerWidth - 12) left = window.innerWidth - tw - 12;
            if (top < 12) top = rect.bottom + 10;
            toolbar.style.top = top + 'px';
            toolbar.style.left = left + 'px';
        }

        document.addEventListener('mouseup', e => {
            if (toolbar.contains(e.target)) return;
            const sel = window.getSelection();
            const text = sel ? sel.toString().trim() : '';
            if (!text) { hideToolbar(); return; }
            const node = sel.anchorNode;
            if (!(node && node.parentElement?.closest('.mz-selectable'))) { hideToolbar(); return; }
            lastSelection = text;
            showToolbar(sel.getRangeAt(0).getBoundingClientRect(), text);
        });

        document.getElementById('sel-close').addEventListener('click', hideToolbar);

        // Color buttons
        document.querySelectorAll('.mz-sel-color').forEach(btn => btn.addEventListener('click', () => {
            if (!lastSelection) return;
            document.getElementById('annot-text').value = lastSelection;
            document.getElementById('annot-color').value = btn.dataset.color;
            document.getElementById('annot-visibility').value = document.getElementById('sel-visibility').value;
            document.getElementById('annot-form').submit();
        }));

        // Note button
        document.getElementById('sel-note').addEventListener('click', () => {
            if (!lastSelection) return;
            document.getElementById('note-selected-text').value = lastSelection;
            document.getElementById('note-quote').textContent = '«' + lastSelection + '»';
            document.getElementById('note-comment').value = '';
            openModal('modal-note');
        });

        // Copy
        document.getElementById('sel-copy').addEventListener('click', async () => {
            if (!lastSelection) return;
            try { await navigator.clipboard.writeText('«' + lastSelection + '»\n— المصدر: ' + docTitle); showToast('تم النسخ'); } catch { showToast('فشل'); }
            hideToolbar();
        });

        // Discussion
        document.getElementById('sel-discuss').addEventListener('click', () => {
            if (!lastSelection) return;
            document.getElementById('disc-body').value = '«' + lastSelection + '»\n\n';
            openModal('modal-discussion');
        });

        // Task
        document.getElementById('sel-task').addEventListener('click', () => {
            if (!lastSelection) return;
            document.getElementById('task-desc').value = 'مرتبط بالنص:\n«' + lastSelection + '»';
            openModal('modal-task');
        });

        window.openModal = function(id) { document.getElementById(id)?.classList.add('open'); hideToolbar(); };
        window.closeModal = function(id) { document.getElementById(id)?.classList.remove('open'); };
        document.addEventListener('keydown', e => { if (e.key === 'Escape') { document.querySelectorAll('.mz-modal-overlay.open').forEach(m => m.classList.remove('open')); hideToolbar(); } });

        window.showToast = function(msg) { const t = document.createElement('div'); t.className = 'mz-toast'; t.textContent = '✓ ' + msg; document.body.appendChild(t); setTimeout(() => t.remove(), 2500); };
    })();
</script>
