<?php
/**
 * Hooks into Quiz Maker actions and filters.
 *
 * @link       https://rucarpe.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/includes
 */

class Quiz_Maker_Failed_Questions_Hooks {

    /**
     * Initialize the hooks.
     *
     * @since    1.0.0
     */
    public static function init() {
        // Add filter to modify quiz data when completed
        add_filter('ays_quiz_complete_results_data', array(__CLASS__, 'add_failed_questions_flag'), 10, 2);
        
        // Handle update of consecutive correct answers
        add_action('wp_ajax_quiz_maker_fq_update_consecutive_correct', array(__CLASS__, 'update_consecutive_correct'));
        add_action('wp_ajax_nopriv_quiz_maker_fq_update_consecutive_correct', array(__CLASS__, 'update_consecutive_correct'));
        
        // Clean up temporary quizzes
        add_action('quiz_maker_fq_cleanup_temp_quizzes', array(__CLASS__, 'cleanup_temp_quizzes'));
        
        // Schedule cleanup
        if (!wp_next_scheduled('quiz_maker_fq_cleanup_temp_quizzes')) {
            wp_schedule_event(time(), 'daily', 'quiz_maker_fq_cleanup_temp_quizzes');
        }
    }
    
    /**
     * Add a flag to quiz results data to identify failed questions quizzes.
     *
     * @since    1.0.0
     * @param    array     $results     Results data.
     * @param    int       $quiz_id     Quiz ID.
     * @return   array                  Modified results data.
     */
    public static function add_failed_questions_flag($results, $quiz_id) {
        global $wpdb;
        
        // Check if this is a failed questions quiz
        if (isset($_GET['is_failed_questions_quiz']) && $_GET['is_failed_questions_quiz'] == 1) {
            $results['isFailedQuestionsQuiz'] = true;
        } else {
            // Check if quiz has the is_failed_questions_quiz flag in database
            $table_quizzes = $wpdb->prefix . 'aysquiz_quizes';
            $is_fq_quiz = $wpdb->get_var($wpdb->prepare(
                "SELECT is_failed_questions_quiz FROM $table_quizzes WHERE id = %d",
                $quiz_id
            ));
            
            if ($is_fq_quiz == 1) {
                $results['isFailedQuestionsQuiz'] = true;
            }
        }
        
        return $results;
    }
    
    /**
     * Update consecutive correct answers count.
     *
     * @since    1.0.0
     */
    /*public static function update_consecutive_correct() {
        check_ajax_referer('quiz_maker_fq_consecutive_nonce', 'security');
        
        if (!is_user_logged_in()) {
            wp_send_json_error(__('Debes iniciar sesión para actualizar los resultados.', 'quiz-maker-failed-questions'));
            return;
        }
        
        $user_id = get_current_user_id();
        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $is_correct = isset($_POST['is_correct']) && $_POST['is_correct'] == 1;
        
        if ($question_id <= 0) {
            wp_send_json_error(__('ID de pregunta inválido.', 'quiz-maker-failed-questions'));
            return;
        }
        
        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        
        // Get settings
        $settings = get_option('quiz_maker_fq_settings', array());
        $consecutive_needed = isset($settings['consecutive_correct_needed']) ? intval($settings['consecutive_correct_needed']) : 3;
        
        if ($is_correct) {
            // Update consecutive correct count
            $result = $wpdb->query($wpdb->prepare(
                "UPDATE $table_failed_questions 
                SET consecutive_correct = consecutive_correct + 1,
                    last_attempt = %s
                WHERE user_id = %d AND question_id = %d AND is_active = 1",
                current_time('mysql'),
                $user_id,
                $question_id
            ));
            
            if ($result !== false) {
                // Check if question should be marked as mastered
                $current_count = $wpdb->get_var($wpdb->prepare(
                    "SELECT consecutive_correct 
                    FROM $table_failed_questions 
                    WHERE user_id = %d AND question_id = %d AND is_active = 1
                    LIMIT 1",
                    $user_id,
                    $question_id
                ));
                
                if ($current_count >= $consecutive_needed) {
                    // Mark as mastered (inactive)
                    $wpdb->update(
                        $table_failed_questions,
                        array('is_active' => 0),
                        array('user_id' => $user_id, 'question_id' => $question_id, 'is_active' => 1),
                        array('%d'),
                        array('%d', '%d', '%d')
                    );
                    
                    wp_send_json_success(__('¡Felicidades! Has dominado esta pregunta y ya no aparecerá en tus tests de preguntas falladas.', 'quiz-maker-failed-questions'));
                    return;
                }
                
                wp_send_json_success(sprintf(
                    __('Has acertado esta pregunta. Necesitas acertarla %d veces más consecutivas para dominarla.', 'quiz-maker-failed-questions'),
                    $consecutive_needed - $current_count
                ));
            }
        } else {
            // Reset consecutive correct count
            $result = $wpdb->update(
                $table_failed_questions,
                array(
                    'consecutive_correct' => 0,
                    'last_attempt' => current_time('mysql')
                ),
                array('user_id' => $user_id, 'question_id' => $question_id, 'is_active' => 1),
                array('%d', '%s'),
                array('%d', '%d', '%d')
            );
            
            if ($result !== false) {
                wp_send_json_success(__('Has fallado esta pregunta. El contador de aciertos consecutivos se ha reiniciado.', 'quiz-maker-failed-questions'));
            }
        }
        
        wp_send_json_error(__('No se pudo actualizar el estado de la pregunta.', 'quiz-maker-failed-questions'));
    }*/
    
    /**
     * Clean up temporary quizzes.
     *
     * @since    1.0.0
     */
    public static function cleanup_temp_quizzes() {
        global $wpdb;
        
        // Delete failed questions quizzes older than 1 day
        $table_quizzes = $wpdb->prefix . 'aysquiz_quizes';
        $older_than = date('Y-m-d H:i:s', strtotime('-1 day'));
        
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_quizzes 
            WHERE is_failed_questions_quiz = 1 
            AND create_date < %s",
            $older_than
        ));
    }
}