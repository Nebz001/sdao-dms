@props(['url'])
{{--
    Shadows Illuminate\Mail\resources\views\html\header.blade.php. The upstream
    file only emits an <img> when the slot is literally the string "Laravel",
    so with APP_NAME="SDAO-DMS" every one of this app's transactional emails
    led with unbranded plain text. This override swaps in the NU Lipa wordmark
    for all of them, deliberately — see tests/Feature/Mail/
    EmailVerificationCodeMailTest.php, which pins the behaviour across every
    Mailable so the blast radius stays visible.

    This is the ONLY framework mail view this app shadows. Re-diff it against
    the vendor copy on major Laravel upgrades; everything else under
    resources/views/vendor/mail is a new component with no upstream twin.

    The <img> carries width/height attributes as well as inline styles because
    Outlook's Word engine honours the attributes and ignores much of the CSS.
    Source is the ready-made public/images/logo/nulp-logo-light-bg.png
    (6250x1570, ~3.98:1 — the same ratio as the SVG this file used to
    rasterize on the fly, so the 200x50 display box below still holds). alt
    falls back to the slot, so a client that blocks remote images renders
    exactly what this header showed before the redesign.
--}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; color: #35408e; font-size: 20px; font-weight: bold; text-decoration: none;">
<img src="{{ config('mail.logo.url') ?: asset('images/logo/nulp-logo-light-bg.png') }}" alt="{{ trim($slot) }}" width="{{ config('mail.logo.width') }}" height="{{ config('mail.logo.height') }}" style="width: {{ config('mail.logo.width') }}px; height: {{ config('mail.logo.height') }}px; max-width: {{ config('mail.logo.width') }}px; border: 0; outline: none; display: block; text-decoration: none;">
</a>
</td>
</tr>
