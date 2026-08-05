@php
    $editorId = $editorId ?? 'request-headers-editor';
    $fieldName = $fieldName ?? 'headers';
    $existing = old($fieldName);
    if (! is_array($existing)) {
        $stored = $headers ?? [];
        $existing = [];
        foreach ($stored as $name => $value) {
            $existing[] = ['name' => $name, 'value' => $value];
        }
    }
    if ($existing === []) {
        $existing = [['name' => '', 'value' => '']];
    }
@endphp

<div id="{{ $editorId }}" class="space-y-3" data-headers-editor data-field-name="{{ $fieldName }}">
    <div class="flex items-center justify-between gap-2">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">Request headers</h3>
            <p class="mt-0.5 text-xs text-slate-500">Параметри header запиту (ключ → значення)</p>
        </div>
        <button
            type="button"
            data-headers-add
            class="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-100"
        >
            Додати
        </button>
    </div>

    <div class="space-y-2" data-headers-rows>
        @foreach ($existing as $index => $row)
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center" data-headers-row>
                <input
                    type="text"
                    name="{{ $fieldName }}[{{ $index }}][name]"
                    value="{{ $row['name'] ?? '' }}"
                    placeholder="Параметр"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200 sm:w-2/5"
                >
                <input
                    type="text"
                    name="{{ $fieldName }}[{{ $index }}][value]"
                    value="{{ $row['value'] ?? '' }}"
                    placeholder="Значення"
                    class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200 sm:flex-1"
                >
                <button
                    type="button"
                    data-headers-remove
                    title="Видалити"
                    aria-label="Видалити"
                    class="inline-flex shrink-0 items-center justify-center rounded-lg border border-red-200 p-2 text-red-700 hover:bg-red-50"
                >
                    @include('partials.icons.trash')
                </button>
            </div>
        @endforeach
    </div>
</div>

@once
    <script>
        (() => {
            const initEditor = (root) => {
                if (root.dataset.headersReady === '1') return;
                root.dataset.headersReady = '1';

                const fieldName = root.dataset.fieldName || 'headers';
                const rows = root.querySelector('[data-headers-rows]');
                const addBtn = root.querySelector('[data-headers-add]');

                const reindex = () => {
                    rows.querySelectorAll('[data-headers-row]').forEach((row, index) => {
                        const nameInput = row.querySelector('input[name*="[name]"]');
                        const valueInput = row.querySelector('input[name*="[value]"]');
                        if (nameInput) nameInput.name = `${fieldName}[${index}][name]`;
                        if (valueInput) valueInput.name = `${fieldName}[${index}][value]`;
                    });
                };

                const ensureOneRow = () => {
                    if (rows.querySelectorAll('[data-headers-row]').length === 0) {
                        addRow();
                    }
                };

                const addRow = (name = '', value = '') => {
                    const row = document.createElement('div');
                    row.className = 'flex flex-col gap-2 sm:flex-row sm:items-center';
                    row.dataset.headersRow = '';
                    row.innerHTML = `
                        <input
                            type="text"
                            placeholder="Параметр"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200 sm:w-2/5"
                        >
                        <input
                            type="text"
                            placeholder="Значення"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-slate-500 focus:outline-none focus:ring-2 focus:ring-slate-200 sm:flex-1"
                        >
                        <button
                            type="button"
                            data-headers-remove
                            title="Видалити"
                            aria-label="Видалити"
                            class="inline-flex shrink-0 items-center justify-center rounded-lg border border-red-200 p-2 text-red-700 hover:bg-red-50"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                        </button>
                    `;
                    const inputs = row.querySelectorAll('input');
                    inputs[0].value = name;
                    inputs[1].value = value;
                    rows.appendChild(row);
                    reindex();
                };

                addBtn?.addEventListener('click', () => addRow());

                rows.addEventListener('click', (event) => {
                    const removeBtn = event.target.closest('[data-headers-remove]');
                    if (!removeBtn) return;
                    removeBtn.closest('[data-headers-row]')?.remove();
                    ensureOneRow();
                    reindex();
                });
            };

            document.querySelectorAll('[data-headers-editor]').forEach(initEditor);
        })();
    </script>
@endonce
