(function($) {
    'use strict';

    $(document).ready(function() {
        // Navegación por pestañas
        $('.nav-tab').on('click', function(e) {
            e.preventDefault();
            var target = $(this).attr('href');
            
            // Actualizar pestaña activa
            $('.nav-tab').removeClass('nav-tab-active');
            $(this).addClass('nav-tab-active');
            
            // Mostrar contenido de la pestaña objetivo
            $('.ays-quiz-tab-content').removeClass('ays-quiz-tab-content-active');
            $(target).addClass('ays-quiz-tab-content-active');
        });
        
        // Guardar ajustes
        $('#ays-failed-questions-settings-form').on('submit', function(e) {
            e.preventDefault();
            
            var formData = $(this).serialize();
            var saveButton = $('#ays-failed-questions-save-settings');
            
            // Deshabilitar botón durante el guardado
            saveButton.prop('disabled', true).text('Guardando...');
            
            // Solicitud AJAX para guardar ajustes
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
                    // Mostrar mensaje de éxito
                    if (response.success) {
                        var $message = $('<div class="notice notice-success is-dismissible"><p>' + 
                                         'Ajustes guardados correctamente!' + '</p></div>');
                        
                        // Insertar mensaje después del formulario
                        $('#ays-failed-questions-settings-form').after($message);
                        
                        // Auto-cerrar después de 3 segundos
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
                    
                    // Re-habilitar botón
                    saveButton.prop('disabled', false).text('Guardar Cambios');
                },
                error: function() {
                    // Mostrar mensaje de error
                    var $message = $('<div class="notice notice-error is-dismissible"><p>' + 
                                    'Ha ocurrido un error al guardar los ajustes. Por favor, inténtalo de nuevo.' + '</p></div>');
                    
                    $('#ays-failed-questions-settings-form').after($message);
                    
                    // Re-habilitar botón
                    saveButton.prop('disabled', false).text('Guardar Cambios');
                }
            });
        });
        
        // Inicializar tooltips
        if (typeof $.fn.tooltip !== 'undefined') {
            $('.ays_help').tooltip();
        }
    });

})(jQuery);