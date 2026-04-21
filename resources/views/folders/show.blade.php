<x-app-layout>
    <div class="mz-screen">
        <div class="mz-page-head">
            <div>
                <div class="mz-page-title">📁 {{ $folder->name }}</div>
                <div class="mz-page-sub">بواسطة {{ $folder->owner?->name }} · {{ $folder->created_at->diffForHumans() }}</div>
            </div>
            <a href="{{ route('folders.index') }}" class="mz-btn mz-btn-ghost mz-btn-sm"><span class="mz-back-arrow">←</span> العودة</a>
        </div>

        @if ($folder->description)
            <div class="mz-card" style="padding:16px">
                <p style="font-size:13px;color:var(--cream);line-height:1.7">{{ $folder->description }}</p>
            </div>
        @endif

        <div style="display:grid;grid-template-columns:1fr 320px;gap:16px">
            <div style="display:flex;flex-direction:column;gap:16px;min-width:0">

                {{-- Documents in folder --}}
                <div class="mz-card">
                    <div class="mz-card-head">
                        <div class="mz-card-title">المستندات ({{ $folder->documents->count() }})</div>
                    </div>
                    <div class="mz-card-body" style="padding:8px">
                        @forelse ($folder->documents as $doc)
                            <div style="display:flex;justify-content:space-between;align-items:center;padding:10px 12px;border-bottom:1px solid var(--borderl)">
                                <a href="{{ route('documents.show', $doc) }}" style="flex:1;min-width:0;text-decoration:none;color:inherit">
                                    <div style="font-size:13px;font-weight:700;color:var(--cream)">{{ $doc->title }}</div>
                                    <div style="font-size:11px;color:var(--mute)">{{ $doc->type_label }}</div>
                                </a>
                                @can('view', $folder)
                                    <form method="POST" action="{{ route('folders.removeDocument', [$folder, $doc->id]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background:transparent;border:none;color:var(--red);cursor:pointer;font-size:14px">✕</button>
                                    </form>
                                @endcan
                            </div>
                        @empty
                            <p style="text-align:center;padding:24px;color:var(--mute);font-size:13px">لا توجد مستندات في هذا المجلد</p>
                        @endforelse
                    </div>
                </div>

                {{-- Upload document into folder --}}
                <form method="POST" action="{{ route('folders.uploadDocument', $folder) }}" enctype="multipart/form-data" class="mz-card" style="padding:18px">
                    @csrf
                    <div style="font-size:13px;font-weight:700;color:var(--cream);margin-bottom:6px">📤 رفع مستند جديد للمجلد</div>
                    <div style="font-size:11px;color:var(--mute);margin-bottom:12px">
                        🔒 المستند سيكون خاصاً — يراه فقط رافِعه وأعضاء هذا المجلد
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">العنوان *</label>
                        <input type="text" name="title" required class="mz-inp" placeholder="عنوان المستند">
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px">
                        <div class="mz-form-group">
                            <label class="mz-flabel">النوع *</label>
                            <select name="type" required class="mz-inp">
                                @foreach (\App\Models\LegalDocument::TYPES as $id => $label)
                                    <option value="{{ $id }}">{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mz-form-group">
                            <label class="mz-flabel">الجهة المُصدِرة</label>
                            <input type="text" name="source_entity" class="mz-inp" placeholder="اختياري">
                        </div>
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">الملخص</label>
                        <textarea name="summary" rows="2" class="mz-inp mz-textarea" placeholder="اختياري"></textarea>
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">📎 إرفاق ملف (PDF / Word / TXT)</label>
                        <input type="file" name="file" accept=".pdf,.doc,.docx,.txt" class="mz-inp">
                        <p style="font-size:10px;color:var(--mute);margin-top:4px">يُستخرج النص تلقائياً للفهرسة. إذا فشل (PDF مُصوَّر) الصق النص يدوياً أدناه.</p>
                    </div>

                    <div class="mz-form-group">
                        <label class="mz-flabel">المحتوى الكامل (للبحث والتظليل)</label>
                        <textarea name="content" rows="4" class="mz-inp mz-textarea" placeholder="اختياري — اتركه فارغاً لاستخراج النص من الملف تلقائياً"></textarea>
                    </div>

                    <button type="submit" class="mz-btn mz-btn-gold mz-btn-sm">رفع المستند</button>
                </form>
            </div>

            {{-- Sidebar: members --}}
            <div style="display:flex;flex-direction:column;gap:16px">
                <div class="mz-card">
                    <div class="mz-card-head">
                        <div class="mz-card-title">الأعضاء ({{ $folder->members->count() + 1 }})</div>
                    </div>
                    <div class="mz-card-body">
                        {{-- Owner --}}
                        <div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--card2);border-radius:8px;margin-bottom:6px">
                            <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--gold),var(--gl));display:grid;place-items:center;color:var(--bg);font-size:12px;font-weight:700">
                                {{ mb_substr($folder->owner?->name ?? '?', 0, 1) }}
                            </div>
                            <div style="flex:1">
                                <div style="font-size:12.5px;font-weight:700;color:var(--cream)">{{ $folder->owner?->name }}</div>
                                <div style="font-size:10px;color:var(--gold)">المالك</div>
                            </div>
                        </div>

                        @foreach ($folder->members as $m)
                            <div style="display:flex;align-items:center;gap:10px;padding:10px;background:var(--card2);border-radius:8px;margin-bottom:6px">
                                <div style="width:32px;height:32px;border-radius:8px;background:linear-gradient(135deg,var(--blue),var(--blue-d));display:grid;place-items:center;color:#fff;font-size:12px;font-weight:700">
                                    {{ mb_substr($m->user?->name ?? '?', 0, 1) }}
                                </div>
                                <div style="flex:1">
                                    <div style="font-size:12.5px;font-weight:700;color:var(--cream)">{{ $m->user?->name }}</div>
                                    <div style="font-size:10px;color:var(--mute)">{{ \App\Models\FolderMember::ROLES[$m->role] ?? $m->role }}</div>
                                </div>
                                @can('manageMembers', $folder)
                                    <form method="POST" action="{{ route('folders.removeMember', [$folder, $m->user_id]) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" style="background:transparent;border:none;color:var(--red);cursor:pointer">✕</button>
                                    </form>
                                @endcan
                            </div>
                        @endforeach

                        @if (auth()->user()->can('manageMembers', $folder) && $orgUsers->count() > 0)
                            <form method="POST" action="{{ route('folders.addMember', $folder) }}" style="margin-top:12px;padding-top:12px;border-top:1px solid var(--borderl)">
                                @csrf
                                <select name="user_id" required class="mz-inp" style="margin-bottom:8px">
                                    <option value="">اختر مستخدماً...</option>
                                    @foreach ($orgUsers as $u)
                                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                                    @endforeach
                                </select>
                                <select name="role" required class="mz-inp" style="margin-bottom:10px">
                                    @foreach (\App\Models\FolderMember::ROLES as $key => $label)
                                        <option value="{{ $key }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <button type="submit" class="mz-btn mz-btn-gold mz-btn-sm" style="width:100%;justify-content:center">إضافة عضو</button>
                            </form>
                        @endif
                    </div>
                </div>

                @can('delete', $folder)
                    <form method="POST" action="{{ route('folders.destroy', $folder) }}"
                          onsubmit="return confirm('هل أنت متأكد من حذف هذا المجلد؟')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="mz-btn mz-btn-ghost" style="width:100%;color:var(--red);border-color:rgba(224,85,85,.25)">🗑 حذف المجلد</button>
                    </form>
                @endcan
            </div>
        </div>
    </div>
</x-app-layout>
