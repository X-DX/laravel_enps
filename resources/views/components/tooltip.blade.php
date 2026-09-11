@props(['text'])

{{-- A small hover tooltip. It teleports itself to <body> and positions with fixed coordinates,
     so it is never clipped by an overflow-x-auto scroll box (a plain absolute tooltip would be
     cut off on the top/bottom table rows). Instant + styled, unlike the native title bubble. --}}
<span
    x-data="{ open: false, coords: '' }"
    x-on:mouseenter="const r = $el.getBoundingClientRect(); coords = `left:${r.left + r.width / 2}px; top:${r.top - 8}px`; open = true"
    x-on:mouseleave="open = false"
    {{ $attributes->merge(['class' => 'relative inline-flex']) }}
>{{ $slot }}<template x-teleport="body"><span
            x-show="open"
            x-transition.opacity.duration.150ms
            :style="coords"
            role="tooltip"
            class="pointer-events-none fixed z-[60] max-w-xs -translate-x-1/2 -translate-y-full whitespace-normal rounded-lg bg-slate-900 px-2.5 py-1.5 text-[11px] font-medium normal-case leading-snug text-white shadow-lg dark:bg-slate-700"
        >{{ $text }}</span></template></span>
