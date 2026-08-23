@props(['code'])
{{--
    Required, not optional. Mailable::buildMarkdownView() renders the body
    twice — once through Markdown::render() against <path>/html and once
    through Markdown::renderText() against <path>/text. Without this file the
    text pass throws "View [mail::verification-code] not found" and the send
    fails outright.
--}}
{{ $code }}
