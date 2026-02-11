<?php
/**
 * Handles the generation of failed questions tests.
 *
 * @link       https://rucarpe.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/includes
 */

class Quiz_Maker_Failed_Questions_Generator
{

    /**
     * Generate a quiz with failed questions.
     *
     * @since    1.0.0
     * @param    int     $category_id    Category ID (0 for mixed).
     * @param    int     $user_id        User ID.
     * @return   int                     Generated quiz ID or 0 on failure.
     */
    public static function generate_quiz($category_id, $user_id)
    {
        global $wpdb;

        // Debugging
        $log_debug = function ($message) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[Quiz Maker FQ] ' . $message);
            }
        };

        $log_debug("Iniciando generación de quiz - Usuario: $user_id, Categoría: $category_id");

        // Verificar tablas
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_quizzes = $wpdb->prefix . 'aysquiz_quizes';
        $table_questions_relations = $wpdb->prefix . 'aysquiz_questions_relations';

        // Verificar si la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_failed_questions'") != $table_failed_questions) {
            $log_debug("ERROR: La tabla $table_failed_questions no existe");
            return 0;
        }

        // Obtener ajustes
        $settings = get_option('quiz_maker_fq_settings', array());
        $max_questions = isset($settings['max_questions']) ? intval($settings['max_questions']) : 20;
        $title = isset($settings['shortcode_text']) ? esc_html($settings['shortcode_text']) : 'Test de preguntas falladas';

        // Añadir nombre de categoría al título si corresponde
        if ($category_id > 0) {
            $table_categories = $wpdb->prefix . 'aysquiz_categories';
            $category_name = $wpdb->get_var($wpdb->prepare(
                "SELECT title FROM $table_categories WHERE id = %d",
                $category_id
            ));

            if ($category_name) {
                $title .= ' - ' . $category_name;
            }
        }

        // SOLUCIÓN SIMPLIFICADA: Obtener preguntas falladas según el caso
        // LISTA DE CATEGORÍAS PERMITIDAS (Hardcoded por solicitud del usuario)
        $allowed_categories = array(2, 3, 4, 5, 6, 7, 8, 10, 11, 12, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 39, 40, 41, 43, 46, 47, 48, 49, 51, 53, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 96);
        $allowed_cats_sql = implode(',', $allowed_categories);

        if ($category_id > 0) {
            // Verificar si la categoría solicitada está permitida
            if (!in_array($category_id, $allowed_categories)) {
                $log_debug("ERROR: Categoría $category_id no está en la lista de permitidas");
                return 0;
            }
            // CASO 1: Categoría específica - Tomar solo preguntas de esta categoría (max 20 o todas si hay menos)
            // Primero, contar cuántas preguntas hay disponibles en esta categoría
            $count_query = $wpdb->prepare(
                "SELECT COUNT(DISTINCT question_id) 
                FROM $table_failed_questions 
                WHERE user_id = %d 
                AND is_active = 1 
                AND category_id = %d",
                $user_id, $category_id
            );

            $available_questions = $wpdb->get_var($count_query);
            $actual_max = min($available_questions, $max_questions);

            $log_debug("Categoría específica $category_id: $available_questions preguntas disponibles, usando $actual_max");

            // Obtener preguntas de esta categoría específica
            $query = $wpdb->prepare(
                "SELECT DISTINCT question_id 
                FROM $table_failed_questions 
                WHERE user_id = %d 
                AND is_active = 1 
                AND category_id = %d 
                ORDER BY RAND()
                LIMIT %d",
                $user_id, $category_id, $actual_max
            );
        }
        else {
            // CASO 2: Test mixto - Siempre 20 preguntas aleatorias de todas las categorías PERMITIDAS
            $query = $wpdb->prepare(
                "SELECT DISTINCT question_id 
                FROM $table_failed_questions 
                WHERE user_id = %d 
                AND is_active = 1 
                AND category_id IN ($allowed_cats_sql)
                ORDER BY RAND()
                LIMIT %d",
                $user_id, $max_questions
            );

            $log_debug("Test mixto: Tomando $max_questions preguntas aleatorias de todas las categorías");
        }

        $question_ids = $wpdb->get_col($query);

        if (empty($question_ids)) {
            $log_debug("ERROR: No se encontraron preguntas falladas activas");
            return 0;
        }

        // SOLUCIÓN SIMPLIFICADA: Calcular el tiempo 54 segundos por pregunta
        $num_questions = count($question_ids);
        $timer_seconds = $num_questions * 54;

        $log_debug("Test generado con $num_questions preguntas. Temporizador: " . floor($timer_seconds / 60) . " minutos (" . $timer_seconds . " seg/pregunta)");

        // Asegurarse de que existe la columna is_failed_questions_quiz
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'is_failed_questions_quiz'",
            $wpdb->dbname,
            $table_quizzes
        ));

        if (empty($column_exists)) {
            $log_debug("Añadiendo columna is_failed_questions_quiz a la tabla $table_quizzes");
            $wpdb->query("ALTER TABLE $table_quizzes ADD COLUMN is_failed_questions_quiz tinyint(1) NOT NULL DEFAULT 0");
        }

        // Copiar opciones de un quiz existente
        $template_options = $wpdb->get_var("SELECT options FROM $table_quizzes WHERE id = 41");

        if (!$template_options) {
            // Si no hay quizzes existentes, usar opciones predefinidas
            $template_options = self::get_default_quiz_options_json($timer_seconds);
            $log_debug("Usando opciones predefinidas por defecto con temporizador de $timer_seconds segundos");
        }
        else {
            $log_debug("Copiando opciones de un quiz existente");

            // Decodificar, modificar y recodificar para personalizar
            $options_array = json_decode($template_options, true);
            if (is_array($options_array)) {
                // SOLUCIÓN SIMPLIFICADA: Establecer temporizador dinámico (54 segundos por pregunta)
                $options_array['timer'] = $timer_seconds;

                // Otras opciones
                $options_array['information_form'] = 'disable';
                $options_array['form_name'] = 'off';
                $options_array['form_email'] = 'off';
                $options_array['form_phone'] = 'off';
                $options_array['enable_correction'] = 'off';
                $options_array['enable_questions_counter'] = 'on';
                $options_array['enable_questions_result'] = 'on';
                $options_array['randomize_questions'] = 'on';
                $options_array['randomize_answers'] = 'on';
                $options_array['result_text'] = 'Has completado correctamente el test de preguntas falladas.';
                $options_array['author'] = $user_id;

                $template_options = json_encode($options_array);
                $log_debug("Opciones personalizadas correctamente con temporizador de $timer_seconds segundos");
            }
        }

        // Crear quiz
        // Calcula minutos, segundos restantes y segundos por pregunta
        $minutes = floor($timer_seconds / 60);
        $seconds = $timer_seconds % 60;
        $sec_per_question = round($timer_seconds / $num_questions);

        $quiz_data = array(
            'title' => $title,
            'description' => "Test de preguntas falladas con $num_questions preguntas y temporizador de {$minutes} minutos {$seconds} segundos ({$sec_per_question} seg/pregunta)",
            'quiz_image' => '',
            'quiz_category_id' => $category_id > 0 ? $category_id : 1,
            'question_ids' => implode(',', $question_ids),
            'published' => 1,
            'options' => $template_options,
            'author_id' => $user_id,
            'create_date' => current_time('mysql'),
            'is_failed_questions_quiz' => 1
        );


        $result = $wpdb->insert(
            $table_quizzes,
            $quiz_data,
            array('%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%d')
        );

        if ($result === false) {
            $log_debug("ERROR al insertar quiz: " . $wpdb->last_error);
            return 0;
        }

        $quiz_id = $wpdb->insert_id;
        $log_debug("Quiz creado con ID: $quiz_id");

        // Asociar preguntas con el quiz
        foreach ($question_ids as $index => $question_id) {
            $wpdb->insert(
                $table_questions_relations,
                array(
                'quiz_id' => $quiz_id,
                'question_id' => $question_id,
                'ordering' => $index + 1
            ),
                array('%d', '%d', '%d')
            );
        }

        $log_debug("Preguntas asociadas correctamente. Quiz listo para usar.");

        // Registrar en tabla de tracking de quizzes temporales
        $table_temp_quizzes = $wpdb->prefix . 'aysquiz_failed_questions_quizzes';
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_temp_quizzes'") == $table_temp_quizzes) {
            $wpdb->insert(
                $table_temp_quizzes,
                array(
                'title' => $title,
                'description' => $quiz_data['description'],
                'quiz_category_id' => $category_id > 0 ? $category_id : 1,
                'question_ids' => implode(',', $question_ids),
                'options' => $template_options,
                'author_id' => $user_id,
                'create_date' => current_time('mysql'),
                'ref_id' => $quiz_id,
            ),
                array('%s', '%s', '%d', '%s', '%s', '%d', '%s', '%d')
            );
            $log_debug("Quiz registrado en tabla de tracking con ref_id: $quiz_id");
        }

        return $quiz_id;
    }

    /**
     * Obtiene opciones por defecto para el quiz en formato JSON.
     *
     * @since    1.0.0
     * @param    int       $timer_seconds   Tiempo en segundos para el temporizador.
     * @return   string                     Opciones en formato JSON.
     */
    private static function get_default_quiz_options_json($timer_seconds = 1080)
    {
        $default_options = array(
            'quiz_theme' => 'classic_light',
            'color' => '#27AE60',
            'bg_color' => '#fff',
            'text_color' => '#000',
            'timer' => $timer_seconds, // Temporizador dinámico
            'information_form' => 'disable',
            'form_name' => 'off',
            'form_email' => 'off',
            'form_phone' => 'off',
            'enable_logged_users' => 'off',
            'image_width' => '',
            'image_height' => '',
            'enable_correction' => 'on',
            'enable_questions_counter' => 'on',
            'limit_users' => 'off',
            'limitation_message' => '',
            'redirect_after_submit' => 'off',
            'redirection_delay' => 0,
            'custom_css' => '',
            'enable_results' => 'on',
            'randomize_questions' => 'on',
            'randomize_answers' => 'on',
            'enable_questions_result' => 'on',
            'enable_average' => 'on',
            'enable_next_button' => 'on',
            'enable_previous_button' => 'on',
            'enable_arrows' => 'off',
            'timer_text' => '',
            'enable_social_buttons' => 'off',
            'result_text' => 'Has completado correctamente el test de preguntas falladas.',
            'enable_pass_count' => 'on',
            'enable_rate_avg' => 'off',
            'enable_rate_comments' => 'off',
            'enable_restart_button' => 'on',
            'autofill_user_data' => 'off',
            'enable_copy_protection' => 'off',
            'enable_audio_autoplay' => 'off',
            'required_fields' => 'off',
            'enable_rtl_direction' => 'off',
            'enable_leave_page' => 'on',
            'create_date' => current_time('mysql'),
            'author' => get_current_user_id(),
            'progress_bar_style' => 'second',
        );

        return json_encode($default_options);
    }
}