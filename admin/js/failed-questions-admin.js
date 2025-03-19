(function($) {
    'use strict';

    /**
     * All of the code for your admin-facing JavaScript source
     * should reside in this file.
     */

    $(document).ready(function() {
        // Tab navigation
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            // Update active tab
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Show target tab content
            $('.ays-quiz-tab-content').removeClass('ays-quiz-tab-content-active');
            $(target).addClass('ays-quiz-tab-content-active');
        });
        
        // Save settings
        $('#ays-failed-questions-settings-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            var saveButton = $('#ays-failed-questions-save-settings');
            
            // Disable button during save
            saveButton.prop('disabled', true).text('Saving...');
            
            // AJAX request to save settings
            $.ajax({
                url: quiz_maker_fq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'quiz_maker_fq_save_settings',
                    security: quiz_maker_fq_ajax.nonce,
                    max_questions: $('#max_questions').val(),
                    consecutive_correct_needed: $('#consecutive_correct_needed').val(),
                    shortcode_text: $('#shortcode_text').val()
                },
                success: function(response) {
                    // Show success message
                    if (response.success) {
                        var $message = $('<div class="notice notice-success is-dismissible"><p>' + 
                                         'Settings saved successfully!' + '</p></div>');
                        
                        // Insert message after form
                        $('#ays-failed-questions-settings-form').after($message);
                        
                        // Auto dismiss after 3 seconds
                        setTimeout(function() {
                            $message.fadeOut(function() {
                                $(this).remove();
                            });
                        }, 3000);
                    } else {
                        var $message = $('<div class="notice notice-error is-dismissible"><p>' + 
                                        'Error: ' + response.data + '</p></div>');
                        
                        $('#ays-failed-questions-settings-form').after($message);
                    }
                    
                    // Re-enable button
                    saveButton.prop('disabled', false).text('Save Changes');
                },
                error: function() {
                    // Show error message
                    var $message = $('<div class="notice notice-error is-dismissible"><p>' + 
                                    'An error occurred while saving settings. Please try again.' + '</p></div>');
                    
                    $('#ays-failed-questions-settings-form').after($message);
                    
                    // Re-enable button
                    saveButton.prop('disabled', false).text('Save Changes');
                }
            });
        });
        
        // Initialize tooltips
        if (typeof $.fn.tooltip !== 'undefined') {
            $('.ays_help').tooltip();
        }
        
    });

})(jQuery);
