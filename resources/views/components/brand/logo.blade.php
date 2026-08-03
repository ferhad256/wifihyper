@props([
    'size' => 28,
    /* 'ink' = for light surfaces | 'light' = for ink/dark surfaces */
    'tone' => 'ink',
    'href' => null,
    'word' => true,
    'tagline' => null,
])

@php
    // The mark sits in currentColor, so the lockup only ever sets one colour
    // and both halves of the wordmark follow it. The accent node stays teal on
    // every surface — it is the one fixed point of the identity.
    $colour = $tone === 'light' ? '#FFFFFF' : 'var(--wh-ink-900)';
    $tag = $href ? 'a' : 'span';
@endphp

<{{ $tag }}
    @if($href) href="{{ $href }}" @endif
    {{ $attributes->merge(['class' => 'wh-logo']) }}
    style="color: {{ $colour }};"
    @if($word) aria-label="WifiHyper" @endif
>
    <x-brand.mark :size="$size" tone="duo" />

    @if($word)
        <span class="wh-logo__word" aria-hidden="true">
            <span>WIFI</span><b>HYPER</b>
        </span>
    @endif

    @if($tagline)
        <span class="wh-logo__tag">{{ $tagline }}</span>
    @endif
</{{ $tag }}>
