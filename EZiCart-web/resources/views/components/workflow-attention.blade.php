<div class="workflow-attention" data-workflow-attention>
    <button type="button" class="workflow-attention-trigger" data-workflow-open aria-haspopup="dialog" aria-controls="workflow-attention-dialog" aria-expanded="false">
        <x-shop-icon name="checklist" />
        <span>Need Actions</span>
        <span class="workflow-action-badge" data-workflow-total hidden>0</span>
    </button>
    <dialog id="workflow-attention-dialog" class="workflow-attention-dialog" data-workflow-dialog aria-labelledby="workflow-attention-title" aria-describedby="workflow-attention-description">
        <div class="workflow-attention-heading">
            <h2 id="workflow-attention-title">Need Actions</h2>
            <button type="button" class="dash-btn-sm dash-btn-outline" data-workflow-close autofocus>Close</button>
        </div>
        <div class="workflow-attention-content">
            <p id="workflow-attention-description" class="web-muted">Your assigned tasks and approval queues update while this page is open. Open a task to use its authorized controls.</p>
            <p class="workflow-attention-status" data-workflow-summary>Checking your current workflow...</p>
            <div role="table" aria-label="Tasks requiring action" tabindex="0">
                <div role="row" class="workflow-task-heading">
                    <strong role="columnheader">Task / reference</strong>
                    <strong role="columnheader">Status, responsibility and date</strong>
                </div>
                <ul data-workflow-items role="rowgroup"></ul>
            </div>
            <p class="web-muted" data-workflow-empty hidden>No action is currently assigned to you. Open an order to see its progress and who acts next.</p>
            <p class="web-muted" data-workflow-updated role="status" aria-live="polite"></p>
            <button type="button" class="dash-btn-sm dash-btn-outline" data-workflow-refresh>Refresh actions</button>
        </div>
    </dialog>
    <noscript><a href="{{ route('notifications.index') }}">View notifications for your next actions</a></noscript>
</div>
