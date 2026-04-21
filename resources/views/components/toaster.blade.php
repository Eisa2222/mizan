{{-- Floating toast notifications for session flash messages.
     Reads session('success'), session('error'), and $errors->first(), renders
     them as auto-dismissing toasts at the top-left (RTL layout). --}}
@php
    $toasts = [];
    if (session('success')) {
        $toasts[] = ['type' => 'success', 'message' => session('success')];
    }
    if (session('status')) {
        $toasts[] = ['type' => 'info', 'message' => session('status')];
    }
    if (session('error')) {
        $toasts[] = ['type' => 'error', 'message' => session('error')];
    }
    if ($errors->any()) {
        $toasts[] = ['type' => 'error', 'message' => $errors->first()];
    }
@endphp

@if (! empty($toasts))
    <div
        x-data="{ toasts: @js($toasts) }"
        x-init="$nextTick(() => setTimeout(() => toasts = [], 5000))"
        class="mz-toaster"
        aria-live="polite"
        aria-atomic="true"
    >
        <template x-for="(toast, index) in toasts" :key="index">
            <div
                :class="'mz-toast mz-toast-' + toast.type"
                x-show="true"
                x-transition:enter="mz-toast-enter"
                x-transition:leave="mz-toast-leave"
            >
                <span class="mz-toast-icon" x-text="toast.type === 'success' ? '✓' : toast.type === 'error' ? '✕' : 'ℹ'"></span>
                <span class="mz-toast-msg" x-text="toast.message"></span>
                <button type="button" @click="toasts.splice(index, 1)" class="mz-toast-close" aria-label="إغلاق">×</button>
            </div>
        </template>
    </div>
@endif
