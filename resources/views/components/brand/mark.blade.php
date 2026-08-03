@props([
    'size' => 28,
    /* 'duo' = arcs in currentColor + accent node | 'solid' = everything currentColor */
    'tone' => 'duo',
    'accent' => '#23C4AC',
])

{{--
    WIFIHYPER mark — "the ramp"

    Three broadcast arcs sharing one origin node. Unlike the standard WiFi
    glyph the arcs are trimmed progressively at their lower ends, so the open
    edge forms a diagonal running up and to the right. That reads as signal
    propagation and forward motion at once, and keeps the mark distinguishable
    from the system WiFi icon at any size.

    Uniform 3px stroke on a 32px grid so it stays legible down to 16px.
--}}

<svg
    {{ $attributes->merge(['class' => 'wh-logo__mark']) }}
    width="{{ $size }}"
    height="{{ $size }}"
    viewBox="0 0 32 32"
    fill="none"
    role="img"
    aria-label="WifiHyper"
    focusable="false"
>
    <g
        stroke="currentColor"
        stroke-width="3"
        stroke-linecap="round"
    >
        {{-- outer arc, trimmed most --}}
        <path d="M22.99 13.66 A21 21 0 0 0 8.92 5.20" opacity="{{ $tone === 'duo' ? '0.45' : '1' }}" />
        {{-- middle arc --}}
        <path d="M19.91 20.38 A15 15 0 0 0 8.09 11.15" opacity="{{ $tone === 'duo' ? '0.72' : '1' }}" />
        {{-- inner arc, full sweep --}}
        <path d="M14.91 24.75 A9 9 0 0 0 7.25 17.09" />
    </g>

    {{-- origin node: the hotspot itself --}}
    <circle
        cx="6"
        cy="26"
        r="2.9"
        fill="{{ $tone === 'duo' ? $accent : 'currentColor' }}"
    />
</svg>
