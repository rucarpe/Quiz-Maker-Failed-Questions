(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize logging function
        function logDebug(message, data) {
            if (window.quiz_maker_fq_ajax && window.quiz_maker_fq_ajax.debug_mode) {
                console.log('[Quiz Maker FQ]', message, data || '');
                if (window.quizMakerFqLog) {
                    window.quizMakerFqLog(message, data);
                }
            }
        }
        
        logDebug('Plugin initialized');
        
        // Start test button click handler
        $('.ays-quiz-fq-start-test').on('click', function(e) {
            e.preventDefault();
            
            // Obtener ID de categoría
            var categoryId = $(this).data('category');
            
            // Mostrar indicador de carga
            $('.ays-quiz-fq-loading').show();
            
            logDebug('Navegando a URL para generar test - Categoría: ' + categoryId);
            
            // Construir URL con parámetros (enfoque directo sin AJAX)
            var currentUrl = window.location.href;
            
            // Eliminar parámetros existentes si los hay
            currentUrl = currentUrl.split('?')[0];
            
            // Añadir parámetros para generar test
            var redirectUrl = currentUrl + '?fq_action=start_test&category_id=' + categoryId;
            
            // Redirigir a la URL generada (esto cargará la página de nuevo con parámetros)
            window.location.href = redirectUrl;
        });

// Mejorar la función de notificación para que sea más visible
function showNotification(message, isError) {
    // Crear elemento de notificación si no existe
    var $notification = $('.ays-quiz-fq-notification');
    if ($notification.length === 0) {
        $notification = $('<div class="ays-quiz-fq-notification"></div>');
        $('body').append($notification);
    }
    
    // Configurar mensaje y estilo
    $notification.text(message);
    $notification.toggleClass('error', isError === true);
    
    // Mostrar notificación
    $notification.addClass('show');
    
    // Ocultar después de 5 segundos
    setTimeout(function() {
        $notification.removeClass('show');
    }, 5000);
    
    // También mostrar en consola para depuración
    if (isError) {
        console.error('[Quiz Maker FQ] ' + message);
    } else {
        console.log('[Quiz Maker FQ] ' + message);
    }
}
        
        // Listen for quiz completion events
        $(document).on('aysQuizCompleted', function(e, data) {
            logDebug('Quiz completed event detected', data);
            
            if (data && data.isFailedQuestionsQuiz) {
                logDebug('Processing failed questions quiz results');
                updateConsecutiveCorrect(data);
            }
        });
        
        // Function to update consecutive correct answers
        function updateConsecutiveCorrect(quizData) {
            if (!quizData || !quizData.questionsData) {
                logDebug('No valid quiz data found');
                return;
            }
            
            // Process each question
            Object.keys(quizData.questionsData).forEach(function(questionId) {
                var questionData = quizData.questionsData[questionId];
                logDebug('Processing question', {id: questionId, correct: questionData.isCorrect});
                
                // Send update to server
                $.ajax({
                    url: quiz_maker_fq_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'quiz_maker_fq_update_consecutive_correct',
                        security: quiz_maker_fq_ajax.consecutive_nonce,
                        question_id: questionId,
                        is_correct: questionData.isCorrect ? 1 : 0
                    },
                    success: function(response) {
                        logDebug('Update consecutive response', response);
                        
                        if (response.success) {
                            // Show feedback message
                            showNotification(response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        logDebug('Update consecutive error', {status: status, error: error});
                    }
                });
            });
        }
        
        // Function to show notification
        function showNotification(message, isError) {
            // Create notification element if it doesn't exist
            var $notification = $('.ays-quiz-fq-notification');
            if ($notification.length === 0) {
                $notification = $('<div class="ays-quiz-fq-notification"></div>');
                $('body').append($notification);
            }
            
            // Set message and error class if needed
            $notification.text(message);
            $notification.toggleClass('error', isError === true);
            
            // Show notification
            $notification.addClass('show');
            
            // Hide after 5 seconds
            setTimeout(function() {
                $notification.removeClass('show');
            }, 5000);
        }
        
        // ====== DEBUG TOOLS (FOR ADMINS ONLY) ======
        
        // Test database operation
        /$('.ays-quiz-fq-test-db').on('click', function() {
            logDebug('Database test button clicked');
            
            var $testResult = $('.ays-quiz-fq-test-result');
            var nonce = $('.ays-quiz-fq-test-form input[name="security"]').val();
            
            $testResult.html('Probando...');
            
            $.ajax({
                url: quiz_maker_fq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'quiz_maker_fq_test_db',
                    security: nonce
                },
                success: function(response) {
                    logDebug('Database test response', response);
                    
                    if (response.success) {
                        $testResult.html('<div class="success">Éxito: ' + response.data.message + 
                                        '<br>Total registros: ' + response.data.count + '</div>');
                    } else {
                        $testResult.html('<div class="error">Error: ' + (response.data || 'Desconocido') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    logDebug('Database test error', {status: status, error: error});
                    $testResult.html('<div class="error">Error en la solicitud AJAX: ' + error + '</div>');
                }
            });
        });
        
        // System verification
        $('.ays-quiz-fq-verify-system').on('click', function() {
            logDebug('System verification button clicked');
            
            var $resultDiv = $('.ays-quiz-fq-verify-system-result');
            $resultDiv.html('Ejecutando verificación completa...');
            
            $.ajax({
                url: quiz_maker_fq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'quiz_maker_fq_verify_system',
                    security: quiz_maker_fq_ajax.nonce
                },
                success: function(response) {
                    logDebug('System verification response', response);
                    
                    if (response.success) {
                        $resultDiv.html('<pre>' + response.data + '</pre>');
                    } else {
                        $resultDiv.html('<div class="error">Error: ' + (response.data || 'Desconocido') + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    logDebug('System verification error', {status: status, error: error});
                    $resultDiv.html('<div class="error">Error en la solicitud AJAX: ' + error + '</div>');
                }
            });
        });
        
        // Listen to Quiz Maker Ajax calls for debugging
        if (window.quiz_maker_fq_ajax && window.quiz_maker_fq_ajax.debug_mode) {
            // Save original jQuery ajax method
            var originalAjax = $.ajax;
            
            // Override to intercept Quiz Maker calls
            $.ajax = function(settings) {
                // Intercept only Quiz Maker AJAX calls that we know are safe to process
                if (settings && settings.data) {
                    var data = settings.data;
                    
                    // Verificar primero si es una llamada de Quiz Maker sin convertir a cadena
                    // cuando sea posible
                    var isQuizMakerCall = false;
                    
                    if (typeof data === 'string' && data.indexOf('action=ays_') !== -1) {
                        isQuizMakerCall = true;
                    } 
                    else if (typeof data === 'object' && data.action && 
                            (typeof data.action === 'string' && data.action.indexOf('ays_') === 0)) {
                        isQuizMakerCall = true;
                    }
                    
                    if (isQuizMakerCall) {
                        logDebug('Intercepted Quiz Maker AJAX call', {
                            url: settings.url,
                            type: settings.type,
                            data: settings.data
                        });
                        
                        // Capture response
                        var originalSuccess = settings.success;
                        settings.success = function(response) {
                            logDebug('Quiz Maker AJAX response', response);
                            
                            // Call original success handler
                            if (originalSuccess) {
                                originalSuccess.apply(this, arguments);
                            }
                        };
                        
                        // Capture errors
                        var originalError = settings.error;
                        settings.error = function(xhr, status, error) {
                            logDebug('Quiz Maker AJAX error', {status: status, error: error});
                            
                            // Call original error handler
                            if (originalError) {
                                originalError.apply(this, arguments);
                            }
                        };
                    }
                }
                
                // Call original ajax
                return originalAjax.apply(this, arguments);
            };
        }
    });
})(jQuery);