@props(['code'])
{{--
    A new component with no upstream counterpart, so it does not freeze
    anything. Rendered inside Markdown::parse(), which means every line must
    start at column 0 (4+ spaces of indent becomes a code block) and the
    markup must contain no blank line (that would terminate the HTML block
    and leave the rest as literal text).

    Table + bgcolor + explicit border because Outlook's Word engine has no
    flexbox/grid, drops border-radius (square corners there are fine), and
    only paints a cell background reliably from the bgcolor attribute.

    font-family is declared inline because the theme's
    "body *:not(html)..." rule would otherwise inject the system sans stack
    over the monospace intent — CssToInlineStyles only merges properties the
    element does not already declare inline.

    text-indent matches letter-spacing so the trailing space after the last
    digit does not push the block off-centre: with a centred line box the
    glyph run lands at exactly width/2 when indent == spacing.

    #35408e on #f2f4fb is 8.4:1 — comfortably AAA. The code is live text, not
    an image, so it stays selectable and iOS one-time-code AutoFill can still
    pick it up from the message body.
--}}
<table class="verification-code" width="100%" cellpadding="0" cellspacing="0" role="presentation" style="width: 100%; margin: 28px 0; border-collapse: separate;">
<tr>
<td align="center" bgcolor="#f2f4fb" style="background-color: #f2f4fb; border: 1px solid #d8ddf0; border-radius: 6px; padding: 26px 16px;">
<div style="font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, Courier, monospace; font-size: 38px; line-height: 44px; mso-line-height-rule: exactly; font-weight: 700; letter-spacing: 10px; text-indent: 10px; color: #35408e;">{{ $code }}</div>
</td>
</tr>
</table>
