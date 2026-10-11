@props(['pageBreak' => false])
@php
    // Embedded as a base64 data URI (dompdf remote fetching stays off) and
    // memoised so a multi-page form reads the file once.
    static $logoSrc = null;
    $logoSrc ??= 'data:image/png;base64,'.base64_encode((string) file_get_contents(public_path('print/nulp-logo-light-bg.png')));
@endphp
{{-- Shared NU Lipa header logo (seal + wordmark) for every printed form:
     Registration/Renewal, Activity Request Form + Proposal, After Activity
     Report and Calendar of Activities. PNG rasterised from the light-background
     SVG by `npm run images:print-logo` (dompdf's SVG support is weak). Fixed
     6250:1570 ratio — width and height are set together so dompdf never
     stretches it. Same 55mm width on portrait and landscape pages. --}}
<div style="margin: 0 0 4mm 0;{{ $pageBreak ? ' page-break-before: always;' : '' }}">
    <img src="{{ $logoSrc }}" alt="NU Lipa" style="display: block; width: 55mm; height: 13.82mm;">
</div>
