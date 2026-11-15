@if($message->unsent_at)
    <p class="conversation-unsent">{{ $ownMessage ? 'You unsent this message.' : 'This message was unsent.' }}</p>
@else
    @if($message->product_context)
        <div class="conversation-order-context conversation-product-context"><div><strong>Product enquiry: {{ $message->product_context['name'] }}</strong><span>{{ $message->product_context['price_label'] }} · advertised at enquiry time</span></div>@if($message->product_context['image_path'])<img class="conversation-product-image" src="{{ route('chat.product-image', $message) }}" alt="Product image at enquiry time" width="80" height="80" loading="lazy">@endif</div>
    @endif
    <p class="conversation-bubble" data-message-body>{{ $message->body }}</p>
    <div class="web-attachment-list">
        @foreach($message->attachments as $attachment)
            @if($attachment->unsent_at)
                <p class="conversation-unsent">Attachment unsent.</p>
            @else
                <article class="conversation-attachment" data-message-attachment="{{ $attachment->id }}">
                    <a class="web-attachment" href="{{ route('chat.attachments.show', [$attachment, 'panel' => request()->boolean('panel') ? 1 : null]) }}">
                        @if(in_array($attachment->mime_type, ['image/jpeg','image/png','image/webp']))<img src="{{ route('chat.attachments.show', [$attachment, 'preview' => 1]) }}" alt="{{ $attachment->original_name }}" loading="lazy">@endif
                        <strong>{{ $attachment->original_name }}</strong><small>{{ number_format($attachment->size / 1024, 1) }} KB · View preview</small>
                    </a>
                    <div class="conversation-message-actions">
                        <a href="{{ route('chat.attachments.show', [$attachment, 'download' => 1]) }}" download>Download</a>
                        @if($ownMessage)
                            <form method="POST" action="{{ route('chat.attachments.destroy', $attachment) }}" data-decision="attachment-unsend" data-decision-title="Unsend this file for everyone?" data-decision-message="The file will no longer be available in this conversation. Copies already downloaded cannot be removed." data-decision-label="Unsend file" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
                                @csrf @method('DELETE') @if(request()->boolean('panel'))<input type="hidden" name="panel" value="1">@endif
                                <button type="submit">Unsend file</button>
                            </form>
                        @endif
                    </div>
                </article>
            @endif
        @endforeach
    </div>
    @if($ownMessage)
        <form class="conversation-message-actions" method="POST" action="{{ route('chat.destroy', $message) }}" data-decision="message-unsend" data-decision-title="Unsend this message for everyone?" data-decision-message="The message and its files will be removed from this conversation. People may already have seen or downloaded them." data-decision-label="Unsend message" data-decision-tone="danger" onsubmit="return window.eziDecisionReady ? true : window.confirm(this.dataset.decisionMessage)">
            @csrf @method('DELETE') @if(request()->boolean('panel'))<input type="hidden" name="panel" value="1">@endif
            <button type="submit">Unsend for everyone</button>
        </form>
    @endif
@endif
