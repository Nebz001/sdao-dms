@extends('print.layout', ['title' => "Appendix {$letter}: {$title}"])

@section('content')

    <div class="h2" style="margin-top: 0; font-size: 11pt;">Appendix {{ $letter }}: {{ $title }}</div>

    @if ($file['kind'] === 'image')
        <div style="text-align: center; margin-top: 6pt;">
            <img src="{{ $file['src'] }}" style="width: {{ $file['width'] }}pt; height: {{ $file['height'] }}pt;">
        </div>
        <div class="muted-note" style="text-align: center; margin-top: 3pt;">{{ $file['filename'] }}</div>
    @elseif ($file['kind'] === 'pdf')
        <div>{{ $file['filename'] }} — the uploaded file follows on the next {{ $file['pages'] === 1 ? 'page' : $file['pages'].' pages' }}.</div>
    @elseif ($file['kind'] === 'unsupported')
        <div>{{ $file['filename'] }} — this file type cannot be printed here. It can be viewed in the system.</div>
    @else
        <div class="muted-note">File could not be loaded{{ $file['filename'] !== '' ? ': '.$file['filename'] : '' }}.</div>
    @endif

@endsection
