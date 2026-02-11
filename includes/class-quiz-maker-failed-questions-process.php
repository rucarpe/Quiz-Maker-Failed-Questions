<?php
/**
 * Procesa los resultados de los tests de preguntas falladas.
 *
 * @link       https://rucarpe.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/includes
 */

class Quiz_Maker_Failed_Questions_Process
{

    /**
     * Inicializa los hooks.
     *
     * @since    1.0.0
     */
    public static function init()
    {
        // Procesar resultados del quiz cuando finaliza
        add_action('ays_finish_quiz', array(__CLASS__, 'process_quiz_results'), 10, 2);

        // Limpiar quizzes temporales
        add_action('quiz_maker_fq_cleanup_temp_quizzes', array(__CLASS__, 'cleanup_temp_quizzes'));

        // Añade botón para volver al menú después de completar el test
        add_filter('ays_front_end_final_result_text', array(__CLASS__, 'add_back_to_menu_button'), 10, 2);

        // Programa la limpieza
        if (!wp_next_scheduled('quiz_maker_fq_cleanup_temp_quizzes')) {
            wp_schedule_event(time(), 'daily', 'quiz_maker_fq_cleanup_temp_quizzes');
        }
    }

    /**
     * Añade un botón para volver al menú de preguntas falladas.
     *
     * @since    1.0.0
     * @param    string    $content    Contenido de los resultados.
     * @param    int       $quiz_id    ID del quiz.
     * @return   string    Contenido modificado.
     */
    public static function add_back_to_menu_button($content, $quiz_id)
    {
        global $wpdb;

        // Verificar si este es un quiz de preguntas falladas
        $table_quizzes = $wpdb->prefix . 'aysquiz_quizes';

        try {
            $is_fq_quiz = $wpdb->get_var($wpdb->prepare(
                "SELECT is_failed_questions_quiz FROM $table_quizzes WHERE id = %d",
                $quiz_id
            ));
        }
        catch (Exception $e) {
            $is_fq_quiz = 0;
        }

        // Verificamos también por el título
        $quiz_title = $wpdb->get_var($wpdb->prepare(
            "SELECT title FROM $table_quizzes WHERE id = %d",
            $quiz_id
        ));

        if (empty($is_fq_quiz) && strpos($quiz_title, 'Test de preguntas falladas') === false) {
            return $content;
        }

        // Encontrar la URL con el shortcode [quiz_maker_failed_questions]
        $return_url = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';

        // Eliminar parámetros de la URL
        $return_url = strtok($return_url, '?');

        // Añadir botón para volver al menú
        $back_button = '<div class="ays-quiz-fq-back-button-container">';
        $back_button .= '<a href="' . esc_url($return_url) . '" class="ays-quiz-fq-back-to-menu">';
        $back_button .= __('Volver al menú de preguntas falladas', 'quiz-maker-failed-questions');
        $back_button .= '</a>';
        $back_button .= '</div>';

        return $content . $back_button;
    }

    /**
     * Procesa los resultados del quiz para actualizar el estado de las preguntas falladas.
     *
     * @since    1.0.0
     * @param    array     $data        Datos de resultados.
     * @param    int       $quiz_id     ID del quiz.
     */
    public static function process_quiz_results($data, $quiz_id)
    {
        global $wpdb;

        self::log_debug("Iniciando procesamiento de resultados. Quiz ID recibido: " . print_r($quiz_id, true));

        // Robustez: Asegurar quiz_id
        if (empty($quiz_id) || !is_numeric($quiz_id)) {
            if (isset($data['quiz_id'])) {
                $quiz_id = intval($data['quiz_id']);
                self::log_debug("Quiz ID extraído de data: $quiz_id");
            }
        }

        if (empty($quiz_id)) {
            self::log_debug("ERROR: No se pudo determinar el ID del quiz");
            return;
        }

        // Verificar si este es un quiz de preguntas falladas
        $table_quizzes = $wpdb->prefix . 'aysquiz_quizes';

        // Intentar obtener el campo is_failed_questions_quiz
        $is_fq_quiz = 0;

        try {
            $is_fq_quiz_col = $wpdb->get_results($wpdb->prepare(
                "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s AND COLUMN_NAME = 'is_failed_questions_quiz'",
                $wpdb->dbname, $table_quizzes
            ));

            if (!empty($is_fq_quiz_col)) {
                $is_fq_quiz = $wpdb->get_var($wpdb->prepare(
                    "SELECT is_failed_questions_quiz FROM $table_quizzes WHERE id = %d",
                    $quiz_id
                ));
            }
        }
        catch (Exception $e) {
            // Si falla, asumimos 0
            self::log_debug("Excepción al verificar flag: " . $e->getMessage());
        }

        // Verificamos si es un quiz de preguntas falladas también por el título
        $quiz_title = $wpdb->get_var($wpdb->prepare(
            "SELECT title FROM $table_quizzes WHERE id = %d",
            $quiz_id
        ));

        $is_fq_by_title = (strpos($quiz_title, 'Test de preguntas falladas') !== false);

        self::log_debug("Diagnóstico Quiz: ID=$quiz_id, Flag DB=$is_fq_quiz, Título='$quiz_title', Es FQ por título=" . ($is_fq_by_title ? 'SI' : 'NO'));

        // Si no es un quiz de preguntas falladas, salimos
        if (empty($is_fq_quiz) && !$is_fq_by_title) {
            self::log_debug("No es un test de preguntas falladas. Ignorando.");
            return;
        }

        // Procesamos solo si el usuario está logueado
        if (!is_user_logged_in()) {
            self::log_debug("Usuario no logueado. Saliendo.");
            return;
        }

        $user_id = get_current_user_id();

        // Obtenemos ajustes
        $settings = get_option('quiz_maker_fq_settings', array());
        $consecutive_needed = isset($settings['consecutive_correct_needed']) ? intval($settings['consecutive_correct_needed']) : 3;

        // Procesamos cada pregunta del resultado
        // Lógica actualizada para coincidir con Capture.php
        $questions_ids = array();
        $correctness = array();

        // Extraer IDs de preguntas
        // Extraer IDs de preguntas
        if (isset($data['questions_ids']) && !empty($data['questions_ids'])) {
            $questions_ids = $data['questions_ids'];
            self::log_debug("IDs de preguntas encontrados en 'questions_ids'");
        }
        elseif (isset($data['ays_quiz_questions'])) {
            $questions_ids = explode(',', $data['ays_quiz_questions']);
            self::log_debug("IDs de preguntas encontrados en 'ays_quiz_questions'");
        }
        elseif (isset($data['data_quiz_questions_ids']) && !empty($data['data_quiz_questions_ids'])) {
            $questions_ids = $data['data_quiz_questions_ids'];
            self::log_debug("IDs de preguntas encontrados en 'data_quiz_questions_ids'");
        }
        elseif (isset($data['data_all_questions_id_arr']) && !empty($data['data_all_questions_id_arr'])) {
            $questions_ids = $data['data_all_questions_id_arr'];
            self::log_debug("IDs de preguntas encontrados en 'data_all_questions_id_arr'");
        }
        else {
            self::log_debug("No se encontraron IDs de preguntas en data. Keys disponibles: " . implode(', ', array_keys($data)));
        }

        // Extraer corrección de respuestas
        if (isset($data['correctness']) && !empty($data['correctness'])) {
            $correctness = $data['correctness'];
            self::log_debug("Corrección encontrada en 'correctness'");
        }
        elseif (isset($data['ays_answer_correct'])) {
            $correctness = $data['ays_answer_correct'];
            self::log_debug("Corrección encontrada en 'ays_answer_correct'");
        }
        elseif (isset($data['data_correctness']) && !empty($data['data_correctness'])) {
            $correctness = $data['data_correctness'];
            self::log_debug("Corrección encontrada en 'data_correctness'");
        }
        elseif (isset($data['data_correctness_results']) && !empty($data['data_correctness_results'])) {
            $correctness = $data['data_correctness_results'];
            self::log_debug("Corrección encontrada en 'data_correctness_results'");
        }

        if (!empty($questions_ids)) {
            self::log_debug("Procesando " . count($questions_ids) . " preguntas...");
            $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';

            foreach ($questions_ids as $index => $question_id) {
                $question_id = intval($question_id);
                $is_correct = false;

                // Determinar si es correcta usando la misma lógica que Capture.php
                if (isset($correctness[$index])) {
                    $is_correct = (bool)$correctness[$index];
                }
                elseif (isset($correctness[$question_id])) {
                    $is_correct = (bool)$correctness[$question_id];
                }
                elseif (isset($correctness['question_id_' . $question_id])) {
                    $is_correct = (bool)$correctness['question_id_' . $question_id];
                }

                self::log_debug("Pregunta $question_id: Index=$index, Correcta=" . ($is_correct ? 'SI' : 'NO'));

                if ($question_id > 0) {
                    if ($is_correct) {
                        try {
                            // Incrementar contador de respuestas correctas
                            $result_update = $wpdb->query($wpdb->prepare(
                                "UPDATE $table_failed_questions 
                            SET consecutive_correct = consecutive_correct + 1,
                                last_attempt = %s
                            WHERE user_id = %d AND question_id = %d AND is_active = 1",
                                current_time('mysql'),
                                $user_id,
                                $question_id
                            ));
                            self::log_debug("Actualizando contador pregunta $question_id: " . ($result_update !== false ? "OK" : "ERROR DB"));
                        }
                        catch (Exception $e) {
                            self::log_debug("Excepción DB al actualizar: " . $e->getMessage());
                        }

                        // Verificar si la pregunta debe marcarse como dominada
                        $current_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT consecutive_correct 
                            FROM $table_failed_questions 
                            WHERE user_id = %d AND question_id = %d AND is_active = 1
                            LIMIT 1",
                            $user_id,
                            $question_id
                        ));

                        self::log_debug("Contador actual pregunta $question_id: $current_count (Necesarios: $consecutive_needed)");

                        if ($current_count >= $consecutive_needed) {
                            // Marcar como dominada (inactiva)
                            $wpdb->update(
                                $table_failed_questions,
                                array('is_active' => 0),
                                array('user_id' => $user_id, 'question_id' => $question_id, 'is_active' => 1),
                                array('%d'),
                                array('%d', '%d', '%d')
                            );
                            self::log_debug("Pregunta $question_id MARCADA COMO DOMINADA");
                        }
                    }
                    else {
                        // Resetear contador de respuestas correctas
                        $wpdb->update(
                            $table_failed_questions,
                            array(
                            'consecutive_correct' => 0,
                            'last_attempt' => current_time('mysql')
                        ),
                            array('user_id' => $user_id, 'question_id' => $question_id, 'is_active' => 1),
                            array('%d', '%s'),
                            array('%d', '%d', '%d')
                        );
                        self::log_debug("Pregunta $question_id fallada. Contador reseteado.");
                    }
                }
            }
        }
        else {
            self::log_debug("ADVERTENCIA: Array de IDs de preguntas vacío.");
        }
    }

    /**
     * Limpia los quizzes temporales.
     *
     * @since    1.0.0
     */
    public static function cleanup_temp_quizzes()
    {
        global $wpdb;

        // Eliminar quizzes de preguntas falladas más antiguos que 1 día
        $table_quizzes = $wpdb->prefix . 'aysquiz_quizes';
        $older_than = date('Y-m-d H:i:s', strtotime('-1 day'));

        try {
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_quizzes 
                WHERE (is_failed_questions_quiz = 1 OR title LIKE %s)
                AND create_date < %s",
                '%Test de preguntas falladas%',
                $older_than
            ));
        }
        catch (Exception $e) {
            // Si el campo no existe, solo usamos el título
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_quizzes 
                WHERE title LIKE %s
                AND create_date < %s",
                '%Test de preguntas falladas%',
                $older_than
            ));
        }
    }

    /**
     * Log debug
     *
     * @since    1.0.0
     * @param    string    $message    Message.
     */
    private static function log_debug($message)
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Quiz FQ Process] ' . $message);
        }
    }
}