@props([
    'columns' => [],           // list of ['key' => 'name', 'label' => 'الاسم', 'sortable' => true]
    'rows' => [],              // list of arrays whose keys match column `key`
    'searchable' => true,
    'placeholder' => 'ابحث...',
    'emptyMessage' => 'لا توجد نتائج',
    'id' => null,
])

@php
    $dtId = $id ?? 'dt_' . uniqid();
    $payload = json_encode(array_values($rows), JSON_UNESCAPED_UNICODE);
    $cols = json_encode(array_map(fn ($c) => [
        'key'      => $c['key'],
        'label'    => $c['label'],
        'sortable' => $c['sortable'] ?? true,
        'html'     => $c['html'] ?? false,
    ], $columns), JSON_UNESCAPED_UNICODE);
@endphp

<div class="mz-datatable-wrap"
     x-data="mzDatatable({{ $payload }}, {{ $cols }})">

    @if ($searchable)
        <div class="mz-datatable-toolbar">
            <div class="mz-datatable-search">
                <input type="search" x-model="q" placeholder="{{ $placeholder }}" />
            </div>
            <div class="mz-datatable-info">
                <span x-text="filtered.length"></span> من أصل <span x-text="rows.length"></span>
            </div>
        </div>
    @endif

    <table class="mz-datatable">
        <thead>
            <tr>
                <template x-for="col in cols" :key="col.key">
                    <th :class="col.sortable ? 'sortable ' + (sortKey === col.key ? (sortDir === 'asc' ? 'sort-asc' : 'sort-desc') : '') : ''"
                        @click="col.sortable && toggleSort(col.key)">
                        <span x-show="col.sortable" class="mz-sort-arrow"
                              x-text="sortKey === col.key ? (sortDir === 'asc' ? '▲' : '▼') : '⇅'"></span>
                        <span x-text="col.label"></span>
                    </th>
                </template>
            </tr>
        </thead>
        <tbody>
            <template x-for="row in filtered" :key="row._id ?? JSON.stringify(row)">
                <tr>
                    <template x-for="col in cols" :key="col.key">
                        <td>
                            <template x-if="col.html">
                                <span x-html="row[col.key] ?? ''"></span>
                            </template>
                            <template x-if="!col.html">
                                <span x-text="row[col.key] ?? ''"></span>
                            </template>
                        </td>
                    </template>
                </tr>
            </template>
            <template x-if="filtered.length === 0">
                <tr>
                    <td :colspan="cols.length" class="mz-datatable-empty">{{ $emptyMessage }}</td>
                </tr>
            </template>
        </tbody>
    </table>
</div>

@once
    <script>
        function mzDatatable(rows, cols) {
            return {
                rows: rows.map((r, i) => ({ _id: r._id ?? i, ...r })),
                cols: cols,
                q: '',
                sortKey: null,
                sortDir: 'asc',

                toggleSort(key) {
                    if (this.sortKey === key) {
                        this.sortDir = this.sortDir === 'asc' ? 'desc' : 'asc';
                    } else {
                        this.sortKey = key;
                        this.sortDir = 'asc';
                    }
                },

                get filtered() {
                    let out = this.rows;
                    const q = this.q.trim().toLowerCase();
                    if (q) {
                        out = out.filter(r =>
                            this.cols.some(c => {
                                const v = r[c.key];
                                return v != null && String(v).toLowerCase().includes(q);
                            })
                        );
                    }
                    if (this.sortKey) {
                        const key = this.sortKey, dir = this.sortDir === 'asc' ? 1 : -1;
                        out = [...out].sort((a, b) => {
                            const av = a[key], bv = b[key];
                            if (av == null && bv == null) return 0;
                            if (av == null) return 1;
                            if (bv == null) return -1;
                            if (typeof av === 'number' && typeof bv === 'number') return (av - bv) * dir;
                            return String(av).localeCompare(String(bv), 'ar') * dir;
                        });
                    }
                    return out;
                },
            };
        }
        if (window.Alpine) {
            window.Alpine.data('mzDatatable', mzDatatable);
        } else {
            document.addEventListener('alpine:init', () => window.Alpine.data('mzDatatable', mzDatatable));
        }
    </script>
@endonce
