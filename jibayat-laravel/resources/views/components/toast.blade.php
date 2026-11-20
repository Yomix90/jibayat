{{-- Messages : flash de session (« toast ») et événements Livewire « notify » ({ message, type }). --}}
<div x-data="{
        items: [],
        push(message, type = 'success') {
            if (! message) return;
            const id = Date.now() + Math.random();
            this.items.push({ id, message, type });
            setTimeout(() => this.items = this.items.filter(i => i.id !== id), 4500);
        },
     }"
     x-init="@if (session('toast')) push(@js(session('toast')), @js(session('toast_type', 'success'))) @endif"
     x-on:notify.window="push($event.detail.message, $event.detail.type ?? 'success')"
     class="pointer-events-none fixed inset-x-0 bottom-4 z-[70] flex flex-col items-center gap-2 px-4" aria-live="polite">
    <template x-for="item in items" :key="item.id">
        <div x-transition:enter="enter" x-transition:enter-start="sheet-from" x-transition:enter-end="enter-to"
             x-transition:leave="enter" x-transition:leave-start="enter-to" x-transition:leave-end="sheet-from"
             class="pointer-events-auto flex max-w-md items-center gap-2 rounded-2xl px-4 py-3 text-sm font-semibold tracking-[.005em] text-white"
             style="box-shadow: var(--shadow-lg)" role="status"
             :class="item.type === 'error' ? 'bg-red-600' : (item.type === 'warning' ? 'bg-[#8a5a12]' : 'material-dark')">
            <span x-text="item.message"></span>
        </div>
    </template>
</div>
