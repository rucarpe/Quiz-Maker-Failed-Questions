<?php
/**
 * Captures failed questions from regular Quiz Maker tests.
 *
 * @link       https://rucarpe.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/includes
 */

class Quiz_Maker_Failed_Questions_Capture {

    /**
     * Initialize the hooks.
     *
     * @since    1.0.0
     */
    public static function init() {
        // Hook into Quiz Maker's finish quiz action to capture failed questions
        add_action('ays_finish_quiz', array(__CLASS__, 'capture_failed_questions'), 5, 2);

        // Hook into the ajax action for capturing failed questions
        add_action('wp_ajax_quiz_maker_fq_capture_ajax', array(__CLASS__, 'capture_failed_questions_ajax'));
        add_action('wp_ajax_nopriv_quiz_maker_fq_capture_ajax', array(__CLASS__, 'capture_failed_questions_ajax'));
    }

    /**
     * Capture failed questions when a user completes a quiz.
     *
     * @since    1.0.0
     * @param    array     $data      Quiz result data.
     * @param    int       $quiz_id   Quiz ID.
     */
    public static function capture_failed_questions($data, $quiz_id) {
        // Si no hay usuario logueado o si es un test de preguntas falladas, no registramos nada
        if (!is_user_logged_in()) {
            return;
        }

        global $wpdb;
        
        // Verificamos si es un test de preguntas falladas, para no registrar de nuevo las preguntas
        $table_quizzes = $wpdb->prefix . 'aysquiz_quizes';
        
        try {
            $is_fq_quiz = $wpdb->get_var($wpdb->prepare(
                "SELECT is_failed_questions_quiz FROM $table_quizzes WHERE id = %d",
                $quiz_id
            ));
        } catch (Exception $e) {
            $is_fq_quiz = 0;
        }
        
        // Verificar también por el título
        $quiz_title = $wpdb->get_var($wpdb->prepare(
            "SELECT title FROM $table_quizzes WHERE id = %d",
            $quiz_id
        ));
        
        // Si es un quiz de preguntas falladas, no registramos
        if (!empty($is_fq_quiz) || strpos($quiz_title, 'Test de preguntas falladas') !== false) {
            return;
        }

        $user_id = get_current_user_id();
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        
        // Procesar cada pregunta del resultado
        if (isset($data['questions']) && is_array($data['questions'])) {
            foreach ($data['questions'] as $question) {
                $question_id = isset($question['questionId']) ? intval($question['questionId']) : 0;
                $is_correct = isset($question['correctAnswer']) && $question['correctAnswer'] === true;
                
                if ($question_id > 0 && !$is_correct) {
                    // Buscar la categoría de la pregunta
                    $table_questions = $wpdb->prefix . 'aysquiz_questions';
                    $category_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT category_id FROM $table_questions WHERE id = %d",
                        $question_id
                    ));
                    
                    if (!$category_id) {
                        $category_id = 1; // Categoría por defecto si no se encuentra
                    }
                    
                    // Comprobar si ya existe esta pregunta para este usuario
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_failed_questions 
                        WHERE user_id = %d AND question_id = %d",
                        $user_id,
                        $question_id
                    ));
                    
                    if ($exists > 0) {
                        // Actualizar registro existente (resetear contador, asegurarse de que está activo)
                        $wpdb->update(
                            $table_failed_questions,
                            array(
                                'consecutive_correct' => 0,
                                'is_active' => 1,
                                'last_attempt' => current_time('mysql')
                            ),
                            array(
                                'user_id' => $user_id,
                                'question_id' => $question_id
                            ),
                            array('%d', '%d', '%s'),
                            array('%d', '%d')
                        );
                    } else {
                        // Insertar nuevo registro
                        $wpdb->insert(
                            $table_failed_questions,
                            array(
                                'user_id' => $user_id,
                                'quiz_id' => $quiz_id,
                                'question_id' => $question_id,
                                'category_id' => $category_id,
                                'consecutive_correct' => 0,
                                'is_active' => 1,
                                'last_attempt' => current_time('mysql'),
                                'created_at' => current_time('mysql')
                            ),
                            array('%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s')
                        );
                    }
                }
            }
        }
    }

    /**
     * Capture failed questions via AJAX.
     *
     * @since    1.0.0
     */
    public static function capture_failed_questions_ajax() {
        // Verificar que el usuario esté logueado
        if (!is_user_logged_in()) {
            wp_send_json_error('User not logged in');
            return;
        }
        
        // Obtener los datos
        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $is_correct = isset($_POST['is_correct']) && $_POST['is_correct'] == 1;
        
        if ($quiz_id <= 0 || $question_id <= 0) {
            wp_send_json_error('Invalid data');
            return;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        
        // Si la respuesta es incorrecta, registramos la pregunta como fallada
        if (!$is_correct) {
            // Buscar la categoría de la pregunta
            $table_questions = $wpdb->prefix . 'aysquiz_questions';
            $category_id = $wpdb->get_var($wpdb->prepare(
                "SELECT category_id FROM $table_questions WHERE id = %d",
                $question_id
            ));
            
            if (!$category_id) {
                $category_id = 1; // Categoría por defecto si no se encuentra
            }
            
            // Comprobar si ya existe esta pregunta para este usuario
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM $table_failed_questions 
                WHERE user_id = %d AND question_id = %d",
                $user_id,
                $question_id
            ));
            
            if ($exists > 0) {
                // Actualizar registro existente (resetear contador, asegurarse de que está activo)
                $wpdb->update(
                    $table_failed_questions,
                    array(
                        'consecutive_correct' => 0,
                        'is_active' => 1,
                        'last_attempt' => current_time('mysql')
                    ),
                    array(
                        'user_id' => $user_id,
                        'question_id' => $question_id
                    ),
                    array('%d', '%d', '%s'),
                    array('%d', '%d')
                );
            } else {
                // Insertar nuevo registro
                $wpdb->insert(
                    $table_failed_questions,
                    array(
                        'user_id' => $user_id,
                        'quiz_id' => $quiz_id,
                        'question_id' => $question_id,
                        'category_id' => $category_id,
                        'consecutive_correct' => 0,
                        'is_active' => 1,
                        'last_attempt' => current_time('mysql'),
                        'created_at' => current_time('mysql')
                    ),
                    array('%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s')
                );
            }
            
            wp_send_json_success('Failed question registered');
        } else {
            wp_send_json_success('Question answered correctly');
        }
    }
}