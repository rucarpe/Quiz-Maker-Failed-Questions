(function($) {
    'use strict';

    /**
     * All of the code for your public-facing JavaScript source
     * should reside in this file.
     */

    $(document).ready(function() {
        // Start test button click handler
        $('.ays-quiz-fq-start-test').on('click', function() {
            // Get category ID
            var categoryId = $(this).data('category');
            
            // Show loading indicator
            $('.ays-quiz-fq-loading').show();
            
            // AJAX request to generate quiz
            $.ajax({
                url: quiz_maker_fq_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'quiz_maker_fq_generate_quiz',
                    security: quiz_maker_fq_ajax.nonce,
                    category_id: categoryId
                },
                success: function(response) {
                    if (response.success && response.data.redirect) {
                        // Redirect to generated quiz
                        window.location.href = response.data.redirect;
                    } else {
                        // Hide loading and show error message
                        $('.ays-quiz-fq-loading').hide();
                        showNotification(response.data, true);
                    }
                },
                error: function() {
                    // Hide loading and show error message
                    $('.ays-quiz-fq-loading').hide();
                    showNotification('Error al generar el test. Por favor, inténtalo de nuevo.', true);
                }
            });
        });
        
        // Listen for quiz completion events
        $(document).on('aysQuizCompleted', function(e, data) {
            if (data && data.isFailedQuestionsQuiz) {
                // Process results for the failed questions quiz
                updateConsecutiveCorrect(data);
            }
        });
        
        // Function to update consecutive correct answers
        function updateConsecutiveCorrect(quizData) {
            if (!quizData || !quizData.questionsData) {
                return;
            }
            
            // Process each question
            Object.keys(quizData.questionsData).forEach(function(questionId) {
                var questionData = quizData.questionsData[questionId];
                
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
                        if (response.success) {
                            // Show feedback message
                            showNotification(response.data);
                        }
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
    });

})(jQuery);
