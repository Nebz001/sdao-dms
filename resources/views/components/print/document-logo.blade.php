{{-- Shared NU Lipa header logo (seal + wordmark) for the Calendar of Activities,
     Activity Proposal and After Activity Report. Single asset, read from the
     project at render time (no URL). Fixed 6250:1570 ratio — width and height
     are set together so dompdf never stretches it. Not used by the Application
     Form or Renewal. --}}
<div style="margin: 0 0 4mm 0;">
    <img src="{{ public_path('images/logo/nulp-logo-light-bg.svg') }}" alt="NU Lipa" style="display: block; width: 55mm; height: 13.82mm;">
</div>
