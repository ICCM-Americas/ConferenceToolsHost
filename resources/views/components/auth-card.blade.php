{{-- Narrow centered card wrapping each auth page's form. --}}
@props(['title'])
<div class="iccm-card iccm-narrow-card">
    <h1>{{ $title }}</h1>
    {{ $slot }}
</div>
