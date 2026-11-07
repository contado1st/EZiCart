@php($process = app(\App\Services\OrderProgressService::class)->forViewer($order, auth()->user()))
<section class="order-process" data-order-progress-id="{{ $order->id }}" data-process-status="{{ $process['status'] }}" data-process-owner="{{ $process['owner'] }}" aria-label="Parcel progress">
    <div class="order-process-heading"><strong>{{ $order->order_number }} progress</strong><span data-process-owner>{{ $process['requires_action'] ? 'Your action is required' : ($process['owner'] ? 'Waiting for '.$process['owner_label'] : 'No further action required') }}</span></div>
    <x-parcel-weight :order="$order" />
    <ol class="order-process-steps">@foreach(\App\Services\OrderProgressService::STEPS as $step)<li data-process-step="{{ $loop->index }}" @class(['is-current' => $process['stage'] === $loop->index, 'is-complete' => $process['stage'] !== null && $process['stage'] > $loop->index]) @if($process['stage'] === $loop->index) aria-current="step" @endif>{{ $step }}</li>@endforeach</ol>
    <p data-process-message role="status" aria-live="polite">{{ $process['message'] }}</p>
    <button class="dash-btn-sm dash-btn-outline" type="button" data-process-reload hidden>Workflow updated: refresh to load current controls</button>
</section>
