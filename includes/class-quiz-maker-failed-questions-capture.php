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
        add_action('ays_finish_quiz', array(__CLASS__, 'capture_failed_questions'), 99);
        
        // Hook para capturar con AJAX (solo un punto de entrada)
        add_action('wp_ajax_quiz_maker_fq_capture_ajax', array(__CLASS__, 'capture_failed_questions_ajax'));
        add_action('wp_ajax_nopriv_quiz_maker_fq_capture_ajax', array(__CLASS__, 'capture_failed_questions_ajax'));
    }

    /**
     * Capture failed questions when a user completes a quiz.
     *
     * @since    1.0.0
     * @param    array     $data      Quiz result data.
     */
    public static function capture_failed_questions($data) {
        // Verificar usuario logueado
        if (!is_user_logged_in()) {
            error_log('Quiz Failed Questions: Usuario no logueado, no se procesan preguntas falladas');
            return $data;
        }

        global $wpdb;
        $user_id = get_current_user_id();
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        
        // Verificar si la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_failed_questions'") != $table_failed_questions) {
            error_log('Quiz Failed Questions: La tabla de preguntas falladas no existe');
            return $data;
        }
        
        // Extraer ID del quiz
        $quiz_id = isset($data['quiz_id']) ? intval($data['quiz_id']) : 0;
        if (!$quiz_id) {
            error_log('Quiz Failed Questions: No se encontró ID del quiz');
            return $data;
        }
        
        // Verificar si es un quiz de preguntas falladas para evitar ciclos
        $table_quizzes = $wpdb->prefix . 'aysquiz_quizes';
        $is_fq_quiz = false;
        
        try {
            $is_fq_quiz_result = $wpdb->get_var($wpdb->prepare(
                "SELECT is_failed_questions_quiz FROM $table_quizzes WHERE id = %d",
                $quiz_id
            ));
            
            $is_fq_quiz = !empty($is_fq_quiz_result);
            
            // Verificar también por el título
            if (!$is_fq_quiz) {
                $quiz_title = $wpdb->get_var($wpdb->prepare(
                    "SELECT title FROM $table_quizzes WHERE id = %d",
                    $quiz_id
                ));
                
                $is_fq_quiz = strpos($quiz_title, 'Test de preguntas falladas') !== false;
            }
        } catch (Exception $e) {
            error_log('Quiz Failed Questions: Error al verificar tipo de quiz: ' . $e->getMessage());
        }
        
        // Si es un test de preguntas falladas, no procesamos
        if ($is_fq_quiz) {
            error_log('Quiz Failed Questions: Es un quiz de preguntas falladas, no se procesan');
            return $data;
        }
        
        // Reconstruir estructura de datos unificada, independiente del formato recibido
        $questions_ids = array();
        $correctness = array();
        
        // Extraer IDs de preguntas
        if (isset($data['questions_ids']) && !empty($data['questions_ids'])) {
            $questions_ids = $data['questions_ids'];
        } elseif (isset($data['ays_quiz_questions'])) {
            $questions_ids = explode(',', $data['ays_quiz_questions']);
        }
        
        // Extraer corrección de respuestas
        if (isset($data['correctness']) && !empty($data['correctness'])) {
            $correctness = $data['correctness'];
        } elseif (isset($data['ays_answer_correct'])) {
            $correctness = $data['ays_answer_correct'];
        }
        
        error_log('Quiz Failed Questions: Procesando quiz ID ' . $quiz_id . ' con ' . count($questions_ids) . ' preguntas');
        
        // Procesamos cada pregunta
        foreach ($questions_ids as $index => $question_id) {
            $question_id = intval($question_id);
            
            // Determinar si la pregunta fue respondida correctamente
            $is_correct = false;
            
            // Comprobar diferentes formatos de datos de corrección
            if (isset($correctness[$index])) {
                $is_correct = (bool)$correctness[$index];
            } elseif (isset($correctness[$question_id])) {
                $is_correct = (bool)$correctness[$question_id];
            } elseif (isset($correctness['question_id_' . $question_id])) {
                $is_correct = (bool)$correctness['question_id_' . $question_id];
            }
            
            error_log('Quiz Failed Questions: Pregunta ' . $question_id . ' - Correcta: ' . ($is_correct ? 'Sí' : 'No'));
            
            // Si la pregunta fue respondida incorrectamente
            if (!$is_correct) {
                self::register_failed_question($user_id, $quiz_id, $question_id);
            } else {
                self::update_consecutive_correct($user_id, $question_id);
            }
        }
        
        return $data;
    }
    
    /**
     * Registrar una pregunta como fallada
     *
     * @param int $user_id ID del usuario
     * @param int $quiz_id ID del quiz
     * @param int $question_id ID de la pregunta
     */
    private static function register_failed_question($user_id, $quiz_id, $question_id) {
        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_questions = $wpdb->prefix . 'aysquiz_questions';
        
        // Obtener la categoría de la pregunta
        $category_id = $wpdb->get_var($wpdb->prepare(
            "SELECT category_id FROM $table_questions WHERE id = %d",
            $question_id
        ));
        
        if (!$category_id) {
            $category_id = 1; // Categoría por defecto si no se encuentra
            error_log("Quiz Failed Questions: No se encontró categoría para pregunta ID $question_id, usando 1");
        }
        
        // Verificar si ya existe esta pregunta para este usuario
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_failed_questions 
            WHERE user_id = %d AND question_id = %d",
            $user_id, $question_id
        ));
        
        if ($exists > 0) {
            // Actualizar registro existente (resetear contador, asegurarse de que está activo)
            $result = $wpdb->update(
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
            
            error_log("Quiz Failed Questions: Actualización de pregunta fallada ID $question_id - " . 
                ($result !== false ? 'Éxito' : 'Error: ' . $wpdb->last_error));
        } else {
            // Insertar nuevo registro
            $result = $wpdb->insert(
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
            
            error_log("Quiz Failed Questions: Inserción de nueva pregunta fallada ID $question_id - " . 
                ($result !== false ? 'Éxito (ID: ' . $wpdb->insert_id . ')' : 'Error: ' . $wpdb->last_error));
        }
    }
    
    /**
     * Actualizar contador de respuestas correctas consecutivas
     *
     * @param int $user_id ID del usuario
     * @param int $question_id ID de la pregunta
     */
    private static function update_consecutive_correct($user_id, $question_id) {
        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        
        // Verificar si la pregunta está en la lista de falladas
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_failed_questions 
            WHERE user_id = %d AND question_id = %d AND is_active = 1",
            $user_id, $question_id
        ));
        
        if ($existing) {
            // Obtener el umbral de respuestas correctas necesarias
            $settings = get_option('quiz_maker_fq_settings', array());
            $consecutive_needed = isset($settings['consecutive_correct_needed']) ? 
                intval($settings['consecutive_correct_needed']) : 3;
            
            // Incrementar contador
            $new_consecutive = $existing->consecutive_correct + 1;
            
            if ($new_consecutive >= $consecutive_needed) {
                // Desactivar la pregunta si ha alcanzado el umbral
                $result = $wpdb->update(
                    $table_failed_questions,
                    array(
                        'consecutive_correct' => $new_consecutive,
                        'is_active' => 0,
                        'last_attempt' => current_time('mysql')
                    ),
                    array('id' => $existing->id),
                    array('%d', '%d', '%s'),
                    array('%d')
                );
                
                error_log("Quiz Failed Questions: Pregunta ID $question_id marcada como dominada - " . 
                    ($result !== false ? 'Éxito' : 'Error: ' . $wpdb->last_error));
            } else {
                // Actualizar contador
                $result = $wpdb->update(
                    $table_failed_questions,
                    array(
                        'consecutive_correct' => $new_consecutive,
                        'last_attempt' => current_time('mysql')
                    ),
                    array('id' => $existing->id),
                    array('%d', '%s'),
                    array('%d')
                );
                
                error_log("Quiz Failed Questions: Incrementado contador para pregunta ID $question_id a $new_consecutive - " . 
                    ($result !== false ? 'Éxito' : 'Error: ' . $wpdb->last_error));
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
        
        // Verificar nonce para seguridad
        check_ajax_referer('quiz_maker_fq_capture_ajax', 'security');
        
        // Obtener los datos
        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $is_correct = isset($_POST['is_correct']) && $_POST['is_correct'] == 1;
        
        if ($quiz_id <= 0 || $question_id <= 0) {
            wp_send_json_error('Invalid data');
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Procesar la pregunta
        if (!$is_correct) {
            self::register_failed_question($user_id, $quiz_id, $question_id);
            wp_send_json_success('Failed question registered');
        } else {
            self::update_consecutive_correct($user_id, $question_id);
            wp_send_json_success('Question answered correctly');
        }
    }
}