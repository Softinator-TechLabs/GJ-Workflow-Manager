jQuery(document).ready(function ($) {
    const $modal = $('#sad-withdrawal-modal');
    const $confirmInput = $('#sad-withdrawal-confirm-input');
    const $startBtn = $('#sad-withdrawal-start-btn');
    const $initSection = $('#sad-withdrawal-init');
    const $progressSection = $('#sad-withdrawal-progress');
    const $completeSection = $('#sad-withdrawal-complete');
    const $logs = $('#sad-withdrawal-logs');

    let postId = 0;

    // Open Modal
    $('#sad-withdraw-article-btn').on('click', function () {
        postId = $(this).data('post-id');
        $modal.css('display', 'flex');
        resetModal();
    });

    // Close Modal
    $('.sad-withdraw-modal-close, .sad-withdraw-modal-close-btn').on('click', function () {
        if ($completeSection.is(':visible')) {
            window.location.reload();
        }
        $modal.hide();
    });

    // Confirm Input listener
    $confirmInput.on('input', function () {
        if ($(this).val().toUpperCase() === 'WITHDRAW') {
            $startBtn.prop('disabled', false);
        } else {
            $startBtn.prop('disabled', true);
        }
    });

    // Start Withdrawal
    $startBtn.on('click', function () {
        $initSection.hide();
        $progressSection.show();
        startWithdrawal();
    });

    function resetModal() {
        $confirmInput.val('');
        $startBtn.prop('disabled', true);
        $initSection.show();
        $progressSection.hide();
        $completeSection.hide();
        $logs.empty();
        $('.sad-progress-step').removeClass('active success error');
    }

    function addLog(message, type = 'info') {
        const color = type === 'error' ? '#ff4444' : (type === 'success' ? '#44ff44' : '#00ff00');
        const timestamp = new Date().toLocaleTimeString();
        $logs.append(`<p style="color:${color}">[${timestamp}] ${message}</p>`);
        $logs.scrollTop($logs[0].scrollHeight);
    }

    async function startWithdrawal() {
        addLog('Starting withdrawal process for Post ID: ' + postId);

        // Step 1: Cleanup Authors
        if (!await executeStep('authors', 'sad_withdraw_cleanup_authors')) return;

        // Step 2: Cleanup Files
        if (!await executeStep('files', 'sad_withdraw_cleanup_files')) return;

        // Step 3: Finalize Status
        if (!await executeStep('status', 'sad_withdraw_finalize_status')) return;

        addLog('Withdrawal process completed successfully!', 'success');
        $progressSection.hide();
        $completeSection.show();
    }

    async function executeStep(stepId, action) {
        const $step = $(`.sad-progress-step[data-step="${stepId}"]`);
        $step.addClass('active');
        addLog('Running step: ' + $step.find('.sad-step-label').text());

        try {
            const response = await $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: action,
                    post_id: postId,
                    nonce: sad_withdrawal_params.nonce
                }
            });

            if (response.success) {
                $step.removeClass('active').addClass('success');
                if (response.data.logs && response.data.logs.length) {
                    response.data.logs.forEach(msg => addLog(' - ' + msg));
                }
                addLog(response.data.message, 'success');
                return true;
            } else {
                throw new Error(response.data.message || 'Unknown error');
            }
        } catch (error) {
            $step.removeClass('active').addClass('error');
            addLog('Error in step ' + stepId + ': ' + error.message, 'error');
            alert('Withdrawal failed: ' + error.message);
            return false;
        }
    }
});
