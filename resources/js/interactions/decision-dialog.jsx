import React, { useEffect, useRef, useState } from 'react';
import { createRoot } from 'react-dom/client';

function DecisionDialog() {
    const [decision, setDecision] = useState(null);
    const dialog = useRef(null);
    const cancel = useRef(null);
    const pending = useRef(null);
    const approved = useRef(new WeakSet());
    const submitting = useRef(new WeakSet());

    const finish = (confirmed) => {
        const current = pending.current;
        if (!current) return;
        pending.current = null;
        dialog.current.close();
        document.documentElement.classList.remove('decision-open');
        setDecision(null);
        if (!confirmed) {
            const trigger = current.trigger;
            const target = trigger?.isConnected && !trigger.closest('[inert]') && trigger.getClientRects().length
                ? trigger : document.querySelector('[data-workspace-toggle]');
            target?.focus({ preventScroll: true });
            return;
        }
        const { form, submitter } = current;
        if (!form.isConnected || (submitter && (submitter.form !== form || submitter.disabled))) return;
        approved.current.add(form);
        try {
            // Retain validation, CSRF, uploads, HTTP method and named button values.
            form.requestSubmit(submitter || undefined);
        } finally {
            approved.current.delete(form);
        }
    };

    useEffect(() => {
        const intercept = (event) => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-decision')) return;
            if (approved.current.has(form)) {
                approved.current.delete(form);
                submitting.current.add(form);
                queueMicrotask(() => {
                    if (event.defaultPrevented) {
                        submitting.current.delete(form);
                    } else {
                        form.setAttribute('aria-busy', 'true');
                        form.dataset.decisionSubmitting = 'true';
                        event.submitter?.setAttribute('data-decision-trigger-busy', 'true');
                    }
                });
                return;
            }
            event.preventDefault();
            event.stopImmediatePropagation();
            if (pending.current || submitting.current.has(form) || !form.reportValidity()) return;
            const submitter = event.submitter;
            const selected = ['status', 'decision', 'resolution', 'outcome'].map((name) => {
                if (submitter?.name === name && submitter.value) {
                    return submitter.textContent.trim() || submitter.value.replaceAll('_', ' ');
                }
                const field = form.elements.namedItem(name);
                if (!field?.value) return null;
                return field instanceof HTMLSelectElement ? field.selectedOptions[0]?.textContent.trim() : String(field.value).replaceAll('_', ' ');
            }).filter(Boolean).join(' · ');
            pending.current = { form, submitter, trigger: submitter || document.activeElement };
            setDecision({
                title: form.dataset.decisionTitle || 'Confirm this action?',
                message: form.dataset.decisionMessage,
                label: form.dataset.decisionLabel || 'Confirm',
                tone: form.dataset.decisionTone || 'normal',
                selected,
            });
        };
        const restore = () => {
            pending.current = null;
            approved.current = new WeakSet();
            submitting.current = new WeakSet();
            if (dialog.current?.open) dialog.current.close();
            document.documentElement.classList.remove('decision-open');
            setDecision(null);
            document.querySelectorAll('[data-decision-submitting]').forEach((form) => {
                delete form.dataset.decisionSubmitting;
                form.removeAttribute('aria-busy');
            });
            document.querySelectorAll('[data-decision-trigger-busy]').forEach((button) => button.removeAttribute('data-decision-trigger-busy'));
        };
        document.addEventListener('submit', intercept, true);
        window.addEventListener('pageshow', restore);
        window.eziDecisionReady = true;
        return () => {
            document.removeEventListener('submit', intercept, true);
            window.removeEventListener('pageshow', restore);
            window.eziDecisionReady = false;
        };
    }, []);

    useEffect(() => {
        if (!decision) return;
        dialog.current.showModal();
        document.documentElement.classList.add('decision-open');
        cancel.current.focus({ preventScroll: true });
    }, [decision]);

    return (
        <dialog ref={dialog} className="decision-dialog rounded-2xl bg-white text-slate-800" aria-labelledby="decision-title" aria-describedby="decision-description"
            onCancel={(event) => { event.preventDefault(); finish(false); }}>
            <div className="decision-content p-6 sm:p-7">
                <span className="decision-symbol" aria-hidden="true"><svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round"><path d="M12 3 21 7v5c0 5-9 9-9 9S3 17 3 12V7l9-4Z"/><path d="m8 12 3 3 5-6"/></svg></span>
                <h2 id="decision-title" className="mt-4 text-xl font-semibold tracking-tight">{decision?.title}</h2>
                <p id="decision-description" className="mt-3 text-sm leading-relaxed text-slate-600">{decision?.message}</p>
                {decision?.selected && <p className="decision-selection mt-4 rounded-lg px-3 py-2 text-sm">Selected: {decision.selected}</p>}
            </div>
            <div className="decision-actions flex flex-wrap justify-end gap-3 border-t border-slate-100 px-6 py-4 sm:px-7">
                <button ref={cancel} type="button" className="decision-cancel rounded-lg px-4 py-2 text-sm font-semibold focus-visible:outline-2 focus-visible:outline-offset-2" onClick={() => finish(false)}>Go back</button>
                <button type="button" className={`decision-confirm rounded-lg px-4 py-2 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2 ${decision?.tone === 'danger' ? 'is-danger' : ''}`} onClick={() => finish(true)}>{decision?.label || 'Confirm'}</button>
            </div>
        </dialog>
    );
}

if (typeof HTMLDialogElement !== 'undefined' && 'showModal' in HTMLDialogElement.prototype && document.querySelector('form[data-decision]')) {
    const mount = document.createElement('div');
    mount.id = 'decision-dialog-root';
    document.body.append(mount);
    createRoot(mount).render(<DecisionDialog />);
}
