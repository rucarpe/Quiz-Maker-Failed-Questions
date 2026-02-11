(function($) {
    'use strict';

    $(document).ready(function() {
        // Tab navigation solo dentro del contenedor de failed questions
        $('#failed-questions-admin .nav-tab').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
    
            // Actualizar pestañas activas únicamente en esta sección
            $('#failed-questions-admin .nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
    
            // Mostrar el contenido asociado solo dentro del contenedor
            $('#failed-questions-admin .ays-quiz-tab-content').removeClass('ays-quiz-tab-content-active');
            $(target).addClass('ays-quiz-tab-content-active');
        });
        
        // Save settings (sin cambios)
        $('#ays-failed-questions-settings-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            var saveButton = $('#ays-failed-questions-save-settings');
            
            saveButton.prop('disabled', true).text('Saving...');
            
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
                    if (response.success) {
                        var $message = $('<div class="notice notice-success is-dismissible"><p>' + 
                                         'Settings saved successfully!' + '</p></div>');
                        $('#ays-failed-questions-settings-form').after($message);
                        setTimeout(function() {
                            $message.fadeOut(function() { $(this).remove(); });
                        }, 3000);
                    } else {
                        var $message = $('<div class="notice notice-error is-dismissible"><p>' + 
                                         'Error: ' + response.data + '</p></div>');
                        $('#ays-failed-questions-settings-form').after($message);
                    }
                    saveButton.prop('disabled', false).text('Save Changes');
                },
                error: function() {
                    var $message = $('<div class="notice notice-error is-dismissible"><p>' + 
                                     'An error occurred while saving settings. Please try again.' + '</p></div>');
                    $('#ays-failed-questions-settings-form').after($message);
                    saveButton.prop('disabled', false).text('Save Changes');
                }
            });
        });
        
        // Inicializar tooltips
        if (typeof $.fn.tooltip !== 'undefined') {
            $('.ays_help').tooltip();
        }
    });    

})(jQuery);