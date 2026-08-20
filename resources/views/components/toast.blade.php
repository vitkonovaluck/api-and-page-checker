<div
    id="server-toast-messages"
    data-messages='@json($messages, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT)'
    hidden
></div>

<div
    id="toast-viewport"
    wire:ignore
    x-cloak
    x-data="{
        toasts: [],
        typeClasses: @js($typeClasses),
        durationMs: {{ (int) $durationMs }},
        add(type, message) {
            const text = typeof message === 'string' ? message.trim() : '';
            if (text === '') {
                return;
            }
            const id = Date.now() + '-' + Math.random().toString(16).slice(2);
            this.toasts.push({ id, type: type || 'info', message: text });
            setTimeout(() => this.dismiss(id), this.durationMs);
        },
        dismiss(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
        payload(value) {
            if (Array.isArray(value)) {
                return value[0] || {};
            }
            return value || {};
        },
        consume() {
            const root = document.getElementById('server-toast-messages');
            if (!root) {
                return;
            }
            const raw = root.dataset.messages;
            if (!raw || raw === '[]') {
                return;
            }
            let messages = [];
            try {
                messages = JSON.parse(raw);
            } catch (e) {
                return;
            }
            root.dataset.messages = '[]';
            if (!Array.isArray(messages)) {
                return;
            }
            messages.forEach((item) => this.add(item?.type, item?.message));
        },
        listen() {
            const onToast = (event) => {
                const detail = this.payload(event.detail);
                this.add(detail.type, detail.message);
            };
            const onLivewireToast = (...args) => {
                const detail = this.payload(args.length === 1 ? args[0] : args);
                this.add(detail.type, detail.message);
            };
            window.addEventListener('toast', onToast);
            document.addEventListener('livewire:navigated', () => this.consume());
            if (window.Livewire) {
                window.Livewire.on('toast', onLivewireToast);
                return;
            }
            document.addEventListener('livewire:init', () => {
                window.Livewire.on('toast', onLivewireToast);
            }, { once: true });
        },
    }"
    x-init="consume(); listen()"
    class="pointer-events-none fixed inset-x-4 top-4 z-50 flex flex-col items-end gap-2 sm:inset-x-auto sm:right-4"
    role="region"
    aria-label="{{ $regionLabel }}"
    aria-live="polite"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            class="pointer-events-auto w-full max-w-sm rounded-lg px-4 py-3 text-sm shadow-lg"
            :class="typeClasses[toast.type] || typeClasses.info"
            role="status"
        >
            <div class="flex items-start gap-3">
                <p class="min-w-0 flex-1 leading-5" x-text="toast.message"></p>
                <button
                    type="button"
                    class="rounded-md p-1 text-current/70 hover:bg-black/5 hover:text-current focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-400"
                    aria-label="{{ $closeLabel }}"
                    @click="dismiss(toast.id)"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="h-4 w-4" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </template>
</div>
