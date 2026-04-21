@props([
    'icon'    => '📭',
    'title'   => null,
    'message' => null,
    'action'  => null,
    'actionLabel' => null,
])

<div {{ $attributes->merge(['class' => 'mz-empty-state']) }}>
    <div class="mz-empty-icon" aria-hidden="true">{{ $icon }}</div>
    @if ($title)
        <div class="mz-empty-title">{{ $title }}</div>
    @endif
    @if ($message)
        <p class="mz-empty-msg">{{ $message }}</p>
    @endif
    @if ($slot->isNotEmpty())
        <div class="mz-empty-extra">{{ $slot }}</div>
    @endif
    @if ($action && $actionLabel)
        <a href="{{ $action }}" class="mz-btn mz-btn-gold mz-btn-sm mz-empty-action">{{ $actionLabel }}</a>
    @endif
</div>
