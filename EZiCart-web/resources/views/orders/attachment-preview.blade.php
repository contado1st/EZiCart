@extends('layouts.communications')
@section('title', 'File preview | EZiCart')
@section('communications-title', 'File preview')
@section('communications-description', 'Private file shared in your conversation.')
@section('communications-content')
<section class="attachment-preview" aria-labelledby="attachment-name">
    <header class="attachment-preview-heading">
        <div><h2 id="attachment-name">{{ $attachment->original_name }}</h2><p>{{ number_format($attachment->size / 1024, 1) }} KB</p></div>
        <div class="web-actions"><a href="{{ route('chat.show', [$attachment->message->conversation_id, 'panel' => request()->boolean('panel') ? 1 : null]) }}">Back to conversation</a><a class="attachment-download" href="{{ route('chat.attachments.show', [$attachment, 'download' => 1]) }}" download>Download file</a></div>
    </header>
    @php($inlineUrl = route('chat.attachments.show', [$attachment, 'preview' => 1, '__tab' => request()->query('__tab')]))
    @if($preview['kind'] === 'image')
        <img class="attachment-preview-image" src="{{ $inlineUrl }}" alt="{{ $attachment->original_name }}">
    @elseif($preview['kind'] === 'pdf')
        <iframe class="attachment-preview-pdf" src="{{ $inlineUrl }}" title="PDF preview: {{ $attachment->original_name }}" referrerpolicy="no-referrer"></iframe>
        <p class="web-muted">If this browser cannot display the PDF, use Download file to open it with a PDF reader.</p>
    @elseif($preview['kind'] === 'text')
        @if($preview['office'] ?? false)<p class="web-muted">Text preview of this Word document. Layout, images and formatting may differ from the original.</p>@endif
        <pre class="attachment-preview-text">{{ $preview['text'] }}</pre>
    @elseif($preview['kind'] === 'spreadsheet')
        <p class="web-muted">Read-only cell preview. Formulas are not executed; saved values and cell references are shown. Download for original formatting and complete workbook data.</p>
        @foreach($preview['sheets'] as $sheet)
            <h3>{{ $sheet['name'] }}</h3>
            <div class="attachment-preview-table"><table><caption>Saved cells in {{ $sheet['name'] }}</caption><tbody>@foreach($sheet['rows'] as $row)<tr>@foreach($row as $cell)<td><small>{{ $cell['reference'] }}</small>{{ $cell['value'] }}</td>@endforeach</tr>@endforeach</tbody></table></div>
        @endforeach
    @else
        <p>This file format cannot be previewed here. Use Download file to open it in a compatible application.</p>
    @endif
    @if($preview['limited'] ?? false)<p class="web-muted">This is a limited preview. Download the original file to see all content.</p>@endif
</section>
@endsection
