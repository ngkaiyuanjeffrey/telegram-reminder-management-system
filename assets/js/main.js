/**
 * Telegram Reminder Management System
 * Core Client-Side Logic & Dynamic Message Builder
 */

$(document).ready(function () {
    // -------------------------------------------------------------------------
    // Sidebar Toggle (Mobile / Responsive)
    // -------------------------------------------------------------------------
    $('#sidebarToggle, .sidebar-toggle').on('click', function (e) {
        e.preventDefault();
        $('#appSidebar').toggleClass('show');
        $('#sidebarBackdrop').toggleClass('show');
    });

    $('#sidebarBackdrop').on('click', function () {
        $('#appSidebar').removeClass('show');
        $('#sidebarBackdrop').removeClass('show');
    });

    // -------------------------------------------------------------------------
    // Show / Hide Password Toggle
    // -------------------------------------------------------------------------
    $('.password-toggle-btn').on('click', function () {
        const targetId = $(this).data('target');
        const input = $(targetId);
        const icon = $(this).find('i');

        if (input.attr('type') === 'password') {
            input.attr('type', 'text');
            icon.removeClass('bi-eye').addClass('bi-eye-slash');
        } else {
            input.attr('type', 'password');
            icon.removeClass('bi-eye-slash').addClass('bi-eye');
        }
    });

    // -------------------------------------------------------------------------
    // Copy to Clipboard
    // -------------------------------------------------------------------------
    $(document).on('click', '.btn-copy', function () {
        const text = $(this).data('copy') || $(this).prev('input, code, span').text() || $(this).prev('input').val();
        if (text) {
            navigator.clipboard.writeText(text).then(() => {
                const origHtml = $(this).html();
                $(this).html('<i class="bi bi-check-lg text-success"></i> Copied!');
                setTimeout(() => {
                    $(this).html(origHtml);
                }, 2000);
            }).catch(err => {
                showToast('error', 'Failed to copy text.');
            });
        }
    });

    // -------------------------------------------------------------------------
    // Dynamic Sequential Message Builder
    // -------------------------------------------------------------------------
    const messageContainer = $('#messagesContainer');

    function reindexMessages() {
        messageContainer.find('.message-item-card').each(function (index) {
            const seqNum = index + 1;
            $(this).find('.seq-num-display').text(seqNum);
            $(this).find('.message-sort-order').val(seqNum);
            $(this).find('textarea').attr('name', `messages[${index}][text]`);
            $(this).find('.message-sort-order').attr('name', `messages[${index}][sort_order]`);

            // Disable move up on first item
            $(this).find('.btn-move-up').prop('disabled', index === 0);
            // Disable move down on last item
            $(this).find('.btn-move-down').prop('disabled', index === messageContainer.find('.message-item-card').length - 1);
        });

        // Ensure at least 1 delete button is disabled if only 1 message
        const totalItems = messageContainer.find('.message-item-card').length;
        if (totalItems <= 1) {
            messageContainer.find('.btn-remove-msg').prop('disabled', true);
        } else {
            messageContainer.find('.btn-remove-msg').prop('disabled', false);
        }
    }

    // Add New Message Row
    $('#btnAddMessage').on('click', function () {
        const count = messageContainer.find('.message-item-card').length + 1;
        const template = `
        <div class="message-item-card fade-in">
            <div class="message-item-header">
                <div class="message-sequence-badge">
                    <i class="bi bi-chat-text text-primary"></i>
                    <span>Message #<span class="seq-num-display">${count}</span> in sequence</span>
                </div>
                <div class="d-flex align-items-center gap-1">
                    <button type="button" class="btn btn-sm btn-light border btn-move-up" title="Move Up"><i class="bi bi-arrow-up"></i></button>
                    <button type="button" class="btn btn-sm btn-light border btn-move-down" title="Move Down"><i class="bi bi-arrow-down"></i></button>
                    <button type="button" class="btn btn-sm btn-outline-danger btn-remove-msg" title="Remove Message"><i class="bi bi-trash"></i></button>
                </div>
            </div>
            <div class="message-item-body">
                <div class="formatting-helpers">
                    <span class="text-muted small me-1">Quick formatting:</span>
                    <button type="button" class="btn-tag" data-tag="b">&lt;b&gt;Bold&lt;/b&gt;</button>
                    <button type="button" class="btn-tag" data-tag="i">&lt;i&gt;Italic&lt;/i&gt;</button>
                    <button type="button" class="btn-tag" data-tag="code">&lt;code&gt;Code&lt;/code&gt;</button>
                    <button type="button" class="btn-tag" data-tag="a">&lt;a href=""&gt;Link&lt;/a&gt;</button>
                </div>
                <textarea class="form-control message-textarea" rows="3" placeholder="Enter message text... (Supports HTML formatting like <b>bold</b>, <i>italic</i>, etc.)" required></textarea>
                <input type="hidden" class="message-sort-order" value="${count}">
            </div>
        </div>`;
        
        messageContainer.append(template);
        reindexMessages();
    });

    // Remove Message Row
    $(document).on('click', '.btn-remove-msg', function () {
        if (messageContainer.find('.message-item-card').length > 1) {
            $(this).closest('.message-item-card').fadeOut(200, function () {
                $(this).remove();
                reindexMessages();
            });
        }
    });

    // Move Message Up
    $(document).on('click', '.btn-move-up', function () {
        const card = $(this).closest('.message-item-card');
        const prev = card.prev('.message-item-card');
        if (prev.length) {
            card.insertBefore(prev);
            reindexMessages();
        }
    });

    // Move Message Down
    $(document).on('click', '.btn-move-down', function () {
        const card = $(this).closest('.message-item-card');
        const next = card.next('.message-item-card');
        if (next.length) {
            card.insertAfter(next);
            reindexMessages();
        }
    });

    // Quick Formatting Tag Insertion
    $(document).on('click', '.btn-tag', function () {
        const tag = $(this).data('tag');
        const textarea = $(this).closest('.message-item-body').find('textarea')[0];
        if (!textarea) return;

        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const text = textarea.value;
        const selectedText = text.substring(start, end) || 'text';
        
        let replacement = '';
        if (tag === 'a') {
            replacement = `<a href="https://example.com">${selectedText}</a>`;
        } else {
            replacement = `<${tag}>${selectedText}</${tag}>`;
        }

        textarea.value = text.substring(0, start) + replacement + text.substring(end);
        textarea.focus();
        textarea.setSelectionRange(start + tag.length + 2, start + tag.length + 2 + selectedText.length);
    });

    // Initial message reindex check
    if (messageContainer.length) {
        reindexMessages();
    }

    // -------------------------------------------------------------------------
    // Recipient Selection ("Select All" & Search Filter)
    // -------------------------------------------------------------------------
    $('#selectAllRecipients').on('change', function () {
        const isChecked = $(this).is(':checked');
        $('.recipient-checkbox:visible').prop('checked', isChecked);
        updateRecipientCounter();
    });

    $(document).on('change', '.recipient-checkbox', function () {
        updateRecipientCounter();
    });

    $('#searchRecipientsInput').on('keyup', function () {
        const query = $(this).val().toLowerCase().trim();
        $('.recipient-item-row').each(function () {
            const name = $(this).data('name') ? $(this).data('name').toString().toLowerCase() : '';
            const chat = $(this).data('chat') ? $(this).data('chat').toString().toLowerCase() : '';
            const match = name.includes(query) || chat.includes(query);
            $(this).toggle(match);
        });
    });

    function updateRecipientCounter() {
        const totalChecked = $('.recipient-checkbox:checked').length;
        $('#selectedCountBadge').text(totalChecked);
    }

    // -------------------------------------------------------------------------
    // AJAX: Test Bot Connection
    // -------------------------------------------------------------------------
    $('#btnTestBotConnection').on('click', function () {
        const btn = $(this);
        const origText = btn.html();
        const tokenVal = $('#inputBotToken').length ? $('#inputBotToken').val() : '';

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Testing connection...');

        $.ajax({
            url: window.APP_BASE_URL ? `${window.APP_BASE_URL}/api/ajax.php` : '../api/ajax.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'test_bot',
                bot_token: tokenVal,
                csrf_token: window.CSRF_TOKEN || ''
            },
            success: function (res) {
                btn.prop('disabled', false).html(origText);
                if (res.success) {
                    showToast('success', `Connected! Bot: @${res.bot.username || res.bot.first_name} (ID: ${res.bot.id})`);
                    $('#botStatusBadge').html(`<span class="badge bg-success"><i class="bi bi-check-circle me-1"></i>Connected: @${res.bot.username}</span>`);
                } else {
                    showToast('error', `Connection Failed: ${res.error}`);
                    $('#botStatusBadge').html(`<span class="badge bg-danger"><i class="bi bi-x-circle me-1"></i>Error: ${res.error}</span>`);
                }
            },
            error: function (xhr) {
                btn.prop('disabled', false).html(origText);
                showToast('error', 'Server error while testing bot.');
            }
        });
    });

    // -------------------------------------------------------------------------
    // AJAX: Send Instant Test Message to Chat ID
    // -------------------------------------------------------------------------
    $(document).on('click', '.btn-test-chat', function () {
        const chatId = $(this).data('chat-id');
        const name = $(this).data('name') || 'Recipient';
        $('#testMsgChatId').val(chatId);
        $('#testMsgRecipientName').text(name);
        $('#modalTestMessage').modal('show');
    });

    $('#formSendTestMsg').on('submit', function (e) {
        e.preventDefault();
        const form = $(this);
        const submitBtn = form.find('button[type="submit"]');
        const origText = submitBtn.html();

        submitBtn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Sending...');

        $.ajax({
            url: window.APP_BASE_URL ? `${window.APP_BASE_URL}/api/ajax.php` : '../api/ajax.php',
            method: 'POST',
            dataType: 'json',
            data: form.serialize() + '&action=send_test_message',
            success: function (res) {
                submitBtn.prop('disabled', false).html(origText);
                if (res.success) {
                    $('#modalTestMessage').modal('hide');
                    showToast('success', `Message sent successfully! (Message ID: ${res.message_id})`);
                } else {
                    showToast('error', `Failed: ${res.error}`);
                }
            },
            error: function () {
                submitBtn.prop('disabled', false).html(origText);
                showToast('error', 'Network error while dispatching test message.');
            }
        });
    });

    // -------------------------------------------------------------------------
    // AJAX: Run Cron Job Instantly from Admin Dashboard
    // -------------------------------------------------------------------------
    $('#btnRunCronNow').on('click', function () {
        const btn = $(this);
        const origHtml = btn.html();

        btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Executing...');

        $.ajax({
            url: window.APP_BASE_URL ? `${window.APP_BASE_URL}/api/ajax.php` : '../api/ajax.php',
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'run_cron',
                csrf_token: window.CSRF_TOKEN || ''
            },
            success: function (res) {
                btn.prop('disabled', false).html(origHtml);
                if (res.success) {
                    showToast('success', res.message || 'Cron job executed successfully.');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showToast('warning', res.message || 'Cron job finished with warnings.');
                }
            },
            error: function () {
                btn.prop('disabled', false).html(origHtml);
                showToast('error', 'Failed to execute cron job.');
            }
        });
    });

    // -------------------------------------------------------------------------
    // Toast Notification System
    // -------------------------------------------------------------------------
    window.showToast = function (type, message) {
        let toastContainer = $('#appToastContainer');
        if (!toastContainer.length) {
            $('body').append('<div id="appToastContainer" class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 9999;"></div>');
            toastContainer = $('#appToastContainer');
        }

        const isSuccess = type === 'success';
        const bgClass = isSuccess ? 'bg-success text-white' : (type === 'warning' ? 'bg-warning text-dark' : 'bg-danger text-white');
        const icon = isSuccess ? 'bi-check-circle-fill' : (type === 'warning' ? 'bi-exclamation-circle-fill' : 'bi-x-circle-fill');

        const toastEl = $(`
            <div class="toast align-items-center ${bgClass} border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
                <div class="d-flex">
                    <div class="toast-body d-flex align-items-center gap-2">
                        <i class="bi ${icon} fs-5"></i>
                        <div>${message}</div>
                    </div>
                    <button type="button" class="btn-close ${isSuccess || type === 'error' ? 'btn-close-white' : ''} me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        `);

        toastContainer.append(toastEl);
        const bsToast = new bootstrap.Toast(toastEl[0], { delay: 4000 });
        bsToast.show();

        toastEl.on('hidden.bs.toast', function () {
            $(this).remove();
        });
    };
});
