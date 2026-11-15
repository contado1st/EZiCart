(() => {
    const frame = document.querySelector('[data-message-updates]');
    const form = document.querySelector('[data-message-composer]');
    const history = document.querySelector('[data-conversation-history]');

    // 1. File Upload with "X" (Remove) Button
    if (form) {
        const input = form.querySelector('[data-message-input]');
        const count = form.querySelector('[data-message-count]');
        const button = form.querySelector('[data-message-send]');
        const files = form.querySelector('[data-message-files]');
        const preview = form.querySelector('[data-message-file-preview]');
        const feedback = form.querySelector('[data-message-upload-feedback]');
        let previewUrls = [];

        const renderPreview = () => {
            previewUrls.forEach(url => URL.revokeObjectURL(url));
            previewUrls = [];
            preview.replaceChildren();
            input.setCustomValidity('');

            const selected = files?.files ? [...files.files] : [];
            const maxBytes = Number(files?.dataset?.maxBytes) || 2097152;
            const allowedExts = ['jpg','jpeg','png','webp','pdf','doc','docx','xls','xlsx','txt'];

            const hasInvalidType = selected.some(file => {
                const ext = file.name.split('.').pop()?.toLowerCase();
                return !ext || !allowedExts.includes(ext);
            });

            if (selected.length > 3) {
                files.setCustomValidity('Choose at most three attachments.');
            } else if (hasInvalidType) {
                files.setCustomValidity('Allowed file types: JPG, PNG, WEBP, PDF, DOC, DOCX, XLS, XLSX, TXT.');
            } else if (selected.some(file => file.size > maxBytes)) {
                files.setCustomValidity(`Each attachment must be ${(maxBytes / 1024 / 1024).toFixed(1)} MB or smaller.`);
            } else {
                files.setCustomValidity('');
            }

            selected.forEach((file, index) => {
                const item = document.createElement('div');
                item.className = 'web-attachment';

                // "X" button to remove this specific file
                const removeBtn = document.createElement('button');
                removeBtn.type = 'button';
                removeBtn.className = 'web-attachment-remove';
                removeBtn.setAttribute('aria-label', `Remove ${file.name}`);
                removeBtn.title = `Remove ${file.name}`;
                removeBtn.innerHTML = '&times;';
                removeBtn.addEventListener('click', (e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    removeFileAt(index);
                });
                item.append(removeBtn);

                if (['image/jpeg','image/png','image/webp'].includes(file.type)) {
                    const img = document.createElement('img');
                    img.src = URL.createObjectURL(file);
                    previewUrls.push(img.src);
                    img.alt = 'Selected image preview';
                    item.append(img);
                }

                const name = document.createElement('span');
                name.textContent = `${file.name} (${(file.size / 1024).toFixed(1)} KB)`;
                item.append(name);

                preview.append(item);
            });

            if (feedback) {
                feedback.textContent = selected.length ? `${selected.length} attachment(s) ready to send.` : 'Images and business documents are shared only with this conversation.';
            }
        };

        const removeFileAt = (indexToRemove) => {
            if (!files || !files.files) return;
            const dt = new DataTransfer();
            const current = [...files.files];
            current.forEach((file, idx) => {
                if (idx !== indexToRemove) {
                    dt.items.add(file);
                }
            });
            files.files = dt.files;
            renderPreview();
        };

        files?.addEventListener('change', renderPreview);

        const update = () => { if (count && input) count.textContent = input.value.length.toLocaleString(); };
        input?.addEventListener('input', update);
        input?.addEventListener('input', () => input.setCustomValidity(''));
        input?.addEventListener('keydown', event => {
            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                form.requestSubmit();
            }
        });

        form.addEventListener('submit', event => {
            if (!input.value.trim() && !files?.files?.length) {
                event.preventDefault();
                input.setCustomValidity('Enter a message or choose a file before sending.');
                input.reportValidity();
                return;
            }
            if (event.defaultPrevented) return;
            if (feedback) feedback.textContent = 'Uploading and sending. Please keep this panel open.';
            if (button) {
                button.disabled = true;
                button.textContent = 'Sending…';
            }
        });

        window.addEventListener('pageshow', () => {
            if (button) {
                button.disabled = false;
                button.textContent = 'Send message';
            }
        });
        update();
    }

    if (history && !new URL(window.location.href).searchParams.has('messages_page')) {
        history.scrollTop = history.scrollHeight;
    }

    // 2. Real-Time Message Synchronization / Polling (Connecting Mobile & Web)
    const updatesUrl = frame?.dataset?.messageUpdates;
    if (updatesUrl && history) {
        let highestMessageId = 0;
        const findHighestId = () => {
            const articles = history.querySelectorAll('[data-direct-message]');
            articles.forEach(art => {
                const id = Number(art.dataset.directMessage);
                if (id > highestMessageId) highestMessageId = id;
            });
        };
        findHighestId();

        let polling = false;
        const pollUpdates = async () => {
            if (polling || document.hidden) return;
            polling = true;
            try {
                const url = new URL(updatesUrl, window.location.href);
                url.searchParams.set('after_id', String(highestMessageId));

                const res = await fetch(url.href, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store'
                });

                if (!res.ok) return;
                const data = await res.json();

                // If contact restrictions changed
                if (data.can_send === false && form) {
                    form.hidden = true;
                    if (!document.querySelector('.conversation-list-empty-disabled')) {
                        const note = document.createElement('p');
                        note.className = 'conversation-list-empty conversation-list-empty-disabled';
                        note.textContent = 'Messaging is unavailable because this contact is no longer active or related to your account. Your history remains available.';
                        form.parentNode.insertBefore(note, form);
                    }
                } else if (data.can_send === true && form) {
                    form.hidden = false;
                    document.querySelector('.conversation-list-empty-disabled')?.remove();
                }

                if (Array.isArray(data.messages) && data.messages.length > 0) {
                    const firstMsgBox = history.querySelector('.conversation-first-message');
                    if (firstMsgBox) firstMsgBox.remove();

                    const isNearBottom = history.scrollHeight - history.scrollTop - history.clientHeight < 120;
                    let hasNew = false;

                    data.messages.forEach(msg => {
                        if (history.querySelector(`[data-direct-message="${msg.id}"]`)) return;
                        if (msg.id > highestMessageId) highestMessageId = msg.id;
                        hasNew = true;

                        const art = document.createElement('article');
                        art.className = `conversation-message${msg.is_own ? ' is-own' : ''}`;
                        art.dataset.directMessage = String(msg.id);
                        art.dataset.ownMessage = msg.is_own ? '1' : '0';

                        // Meta
                        const meta = document.createElement('div');
                        meta.className = 'conversation-message-meta';
                        meta.innerHTML = `<strong>${msg.sender_name || (msg.is_own ? 'You' : 'Contact')}</strong>`;
                        art.append(meta);

                        // Content
                        const content = document.createElement('div');
                        content.className = 'conversation-message-content';
                        content.dataset.messageContent = '1';

                        if (msg.is_unsent) {
                            const unsentP = document.createElement('p');
                            unsentP.className = 'conversation-unsent';
                            unsentP.textContent = msg.is_own ? 'You unsent this message.' : 'This message was unsent.';
                            content.append(unsentP);
                        } else {
                            if (msg.product_context) {
                                const prodBox = document.createElement('div');
                                prodBox.className = 'conversation-order-context conversation-product-context';
                                prodBox.innerHTML = `<div><strong>Product enquiry: ${msg.product_context.name || ''}</strong><span>${msg.product_context.price_label || ''} · advertised at enquiry time</span></div>`;
                                if (msg.product_context.image_url) {
                                    const prodImg = document.createElement('img');
                                    prodImg.className = 'conversation-product-image';
                                    prodImg.src = msg.product_context.image_url;
                                    prodImg.alt = 'Product image at enquiry time';
                                    prodImg.width = 80;
                                    prodImg.height = 80;
                                    prodImg.loading = 'lazy';
                                    prodBox.append(prodImg);
                                }
                                content.append(prodBox);
                            }

                            if (msg.body) {
                                const bubble = document.createElement('p');
                                bubble.className = 'conversation-bubble';
                                bubble.dataset.messageBody = '1';
                                bubble.textContent = msg.body;
                                content.append(bubble);
                            }

                            if (Array.isArray(msg.attachments) && msg.attachments.length > 0) {
                                const attList = document.createElement('div');
                                attList.className = 'web-attachment-list';
                                msg.attachments.forEach(att => {
                                    if (att.is_unsent) {
                                        const unAtt = document.createElement('p');
                                        unAtt.className = 'conversation-unsent';
                                        unAtt.textContent = 'Attachment unsent.';
                                        attList.append(unAtt);
                                    } else {
                                        const attArt = document.createElement('article');
                                        attArt.className = 'conversation-attachment';
                                        attArt.dataset.messageAttachment = String(att.id);

                                        const link = document.createElement('a');
                                        link.className = 'web-attachment';
                                        link.href = att.view_url || '#';
                                        if (att.is_image && att.preview_url) {
                                            const img = document.createElement('img');
                                            img.src = att.preview_url;
                                            img.alt = att.name;
                                            img.loading = 'lazy';
                                            link.append(img);
                                        }
                                        const attName = document.createElement('strong');
                                        attName.textContent = att.name;
                                        const attMeta = document.createElement('small');
                                        attMeta.textContent = `${att.size_formatted} · View preview`;
                                        link.append(attName, attMeta);
                                        attArt.append(link);

                                        const actions = document.createElement('div');
                                        actions.className = 'conversation-message-actions';
                                        const dl = document.createElement('a');
                                        dl.href = att.download_url || '#';
                                        dl.download = true;
                                        dl.textContent = 'Download';
                                        actions.append(dl);
                                        attArt.append(actions);

                                        attList.append(attArt);
                                    }
                                });
                                content.append(attList);
                            }
                        }
                        art.append(content);

                        // Timestamp
                        const time = document.createElement('time');
                        time.dateTime = msg.created_at_iso;
                        time.textContent = msg.created_at_formatted;
                        art.append(time);

                        history.append(art);
                    });

                    if (hasNew && (isNearBottom || data.messages.some(m => m.is_own))) {
                        history.scrollTop = history.scrollHeight;
                    }

                    document.dispatchEvent(new Event('ezicart:message-panel-loaded'));
                }
            } catch (_) {
                // Network glitch or background tab: keep existing UI
            } finally {
                polling = false;
            }
        };

        const interval = setInterval(pollUpdates, 3000);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) pollUpdates(); });
        window.addEventListener('pagehide', () => clearInterval(interval), { once: true });
    }
})();
