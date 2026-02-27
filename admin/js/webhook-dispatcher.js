(function($) {
    'use strict';

    $(document).ready(function() {
        $('#sad-send-to-webhook').on('click', function(e) {
            e.preventDefault();

            var $button = $(this);
            var $spinner = $('#sad-webhook-spinner');
            var $response = $('#sad-webhook-response');
            var postId = $button.data('post-id');
            var manuscriptUrl = $button.data('manuscript-url');

            if ($button.prop('disabled')) return;

            $button.prop('disabled', true);
            $spinner.addClass('is-active');
            $response.text('').css('color', 'inherit');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'sad_send_webhook',
                    post_id: postId,
                    manuscript_url: manuscriptUrl,
                    nonce: sad_webhook_vars.nonce
                },
                success: function(res) {
                    $spinner.removeClass('is-active');
                    $button.prop('disabled', false);

                    if (res.success) {
                        $response.text(res.data.message).css('color', '#46b450');
                    } else {
                        $response.text(res.data.message).css('color', '#d63638');
                    }
                },
                error: function() {
                    $spinner.removeClass('is-active');
                    $button.prop('disabled', false);
                    $response.text('An error occurred.').css('color', '#d63638');
                }
            });
        });
    });

})(jQuery);
