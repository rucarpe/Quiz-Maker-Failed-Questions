<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://rucarpe.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/public
 */

class Quiz_Maker_Failed_Questions_Public
{

    /**
     * The ID of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    The ID of this plugin.
     */
    private $plugin_name;

    /**
     * The version of this plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    The current version of this plugin.
     */
    private $version;

    /**
     * Debug mode flag.
     *
     * @since    1.0.0
     * @access   private
     * @var      bool    $debug_mode    Whether debug mode is enabled.
     */
    private $debug_mode = true;

    /**
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of the plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct($plugin_name, $version)
    {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->debug_mode = (defined('WP_DEBUG') && WP_DEBUG) || (current_user_can('manage_options') && isset($_GET['quiz_fq_debug']));
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles()
    {
        wp_enqueue_style($this->plugin_name, QUIZ_MAKER_FQ_PUBLIC_URL . 'css/failed-questions-public.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts()
    {
        wp_enqueue_script($this->plugin_name, QUIZ_MAKER_FQ_PUBLIC_URL . 'js/failed-questions-public.js', array('jquery'), $this->version, false);
        wp_localize_script($this->plugin_name, 'quiz_maker_fq_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('quiz_maker_fq_ajax_nonce'),
            'consecutive_nonce' => wp_create_nonce('quiz_maker_fq_consecutive_nonce'),
            'debug_mode' => $this->debug_mode,
        ));
    }

    /**
     * Register shortcodes
     *
     * @since    1.0.0
     */
    public function register_shortcodes()
    {
        add_shortcode('quiz_maker_failed_questions', array($this, 'failed_questions_shortcode'));
    }

    /**
     * Failed questions shortcode callback
     *
     * @since    1.0.0
     * @param    array    $atts    Shortcode attributes.
     * @return   string            Shortcode output.
     */
    public function failed_questions_shortcode($atts)
    {
        $atts = shortcode_atts(array(
            'style' => 'default',
        ), $atts, 'quiz_maker_failed_questions');

        // Si no está logueado, mostrar mensaje
        if (!is_user_logged_in()) {
            return '<p>' . __('Por favor, inicia sesión para acceder a tus tests de preguntas falladas.', 'quiz-maker-failed-questions') . '</p>';
        }

        // IMPORTANTE: Comprobar si es una solicitud para generar test
        if (isset($_GET['fq_action']) && $_GET['fq_action'] === 'start_test') {
            // Obtener el category_id del parámetro GET y asegurarse de que es un entero
            $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;

            // Log para depuración
            $this->log_debug("Solicitud de test recibida con category_id: " . $category_id);

            // Generar y mostrar quiz directamente
            return $this->generate_and_display_test($category_id);
        }

        // Obtener categorías con preguntas falladas
        $categories = $this->get_user_failed_question_categories(get_current_user_id());

        // Iniciar buffer de salida
        ob_start();

        // Incluir plantilla
        include QUIZ_MAKER_FQ_DIR . 'public/partials/failed-questions-menu-display.php';

        // Devolver contenido del buffer
        return ob_get_clean();
    }

    /**
     * Genera y muestra directamente un test de preguntas falladas.
     *
     * @since    1.0.0
     * @param    int     $category_id    ID de categoría (0 para mixto).
     * @return   string                  HTML del quiz generado.
     */
    private function generate_and_display_test($category_id)
    {
        $user_id = get_current_user_id();

        // Asegurarnos que category_id es un entero
        $category_id = intval($category_id);

        $this->log_debug("Generando test de preguntas falladas - Usuario: $user_id, Categoría: $category_id");

        // Cargar generador
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-quiz-maker-failed-questions-generator.php';
        $quiz_id = Quiz_Maker_Failed_Questions_Generator::generate_quiz($category_id, $user_id);

        if ($quiz_id > 0) {
            $this->log_debug("Quiz generado con ID: $quiz_id - Mostrando directamente");

            // IMPORTANTE: Añadir filtro para marcar este quiz como de preguntas falladas
            add_filter('ays_finish_quiz_data', function ($data) {
                $data['is_failed_questions_quiz'] = true;
                return $data;
            });

            // Mostrar directamente el quiz usando do_shortcode
            return '<div class="quiz-maker-failed-questions-container">' .
                '<p><a href="' . esc_url(remove_query_arg(array('fq_action', 'category_id'))) . '" class="ays-quiz-fq-back-to-menu">' .
                __('« Volver al menú', 'quiz-maker-failed-questions') .
                '</a></p>' .
                '<div class="quiz-maker-failed-questions-test">' .
                do_shortcode('[ays_quiz id="' . $quiz_id . '"]') .
                '</div>' .
                '</div>';
        }
        else {
            $this->log_debug("Error al generar el quiz - No hay preguntas falladas");

            // Mostrar error y botón para volver
            return '<div class="ays-quiz-failed-questions-error">' .
                __('No se encontraron preguntas falladas para generar un test.', 'quiz-maker-failed-questions') .
                '</div>' .
                '<p><a href="' . esc_url(remove_query_arg(array('fq_action', 'category_id'))) . '" class="ays-quiz-fq-back-to-menu">' .
                __('« Volver al menú', 'quiz-maker-failed-questions') .
                '</a></p>';
        }
    }

    /**
     * Get categories where user has failed questions
     *
     * @since    1.0.0
     * @param    int       $user_id    User ID.
     * @return   array                 Categories list.
     */
    private function get_user_failed_question_categories($user_id)
    {
        global $wpdb;

        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_question_categories = $wpdb->prefix . 'aysquiz_categories';

        // LISTA DE CATEGORÍAS PERMITIDAS (Hardcoded por solicitud del usuario)
        $allowed_categories = array(2, 3, 4, 5, 6, 7, 8, 10, 11, 12, 15, 16, 17, 18, 19, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 39, 40, 41, 43, 46, 47, 48, 49, 51, 53, 60, 61, 62, 63, 64, 65, 66, 67, 68, 69, 71, 72, 73, 74, 75, 76, 77, 78, 79, 80, 81, 82, 96);
        $allowed_cats_sql = implode(',', $allowed_categories);

        $query = $wpdb->prepare(
            "SELECT DISTINCT fq.category_id, c.title,
            COUNT(fq.id) as question_count
            FROM $table_failed_questions fq
            JOIN $table_question_categories c ON fq.category_id = c.id
            WHERE fq.user_id = %d
            AND fq.is_active = 1
            AND fq.category_id IN ($allowed_cats_sql)
            GROUP BY fq.category_id, c.title
            ORDER BY c.title ASC",
            $user_id
        );

        $results = $wpdb->get_results($query, ARRAY_A);

        return $results ? $results : array();
    }

    /**
     * Process quiz results using direct database access
     *
     * @since    1.0.2
     * @param    array     $results   Quiz results.
     * @param    array     $data      Additional data.
     * @return   array                Modified results for Quiz Maker.
     */
    public function rucarpe_process_quiz_results($results, $data = array())
    {
        $this->log_debug("==== INICIO PROCESAMIENTO QUIZ RESULTS ====");

        if (!is_user_logged_in()) {
            return $results;
        }

        // Extraer información básica
        $quiz_id = isset($results['quiz_id']) ? intval($results['quiz_id']) : 0;
        if (!$quiz_id && isset($data['quiz_id'])) {
            $quiz_id = intval($data['quiz_id']);
        }

        // Si todavía no tenemos quiz_id, intentar obtenerlo del POST
        if (!$quiz_id && isset($_POST['quiz_id'])) {
            $quiz_id = intval($_POST['quiz_id']);
        }

        if (!$quiz_id) {
            $this->log_debug("No se pudo determinar el ID del quiz");
            return $results;
        }

        $user_id = get_current_user_id();
        $this->log_debug("Procesando quiz ID: $quiz_id para usuario ID: $user_id");

        // Obtener el último informe para este usuario y quiz
        global $wpdb;
        $reports_table = $wpdb->prefix . 'aysquiz_reports';

        $report = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reports_table 
            WHERE quiz_id = %d AND user_id = %d 
            ORDER BY id DESC LIMIT 1",
            $quiz_id, $user_id
        ));

        if (!$report) {
            $this->log_debug("No se encontró informe para el quiz");
            return $results;
        }

        $this->log_debug("Informe encontrado con ID: " . $report->id);

        // Extraer IDs de preguntas del quiz
        $questions_table = $wpdb->prefix . 'aysquiz_questions';
        $questions_relations_table = $wpdb->prefix . 'aysquiz_questions_relations';

        $question_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT DISTINCT qq.question_id
            FROM $questions_relations_table qq
            JOIN $questions_table q ON q.id = qq.question_id
            WHERE qq.quiz_id = %d AND q.published = 1
            ORDER BY qq.ordering ASC",
            $quiz_id
        ));

        if (empty($question_ids)) {
            $this->log_debug("No se encontraron preguntas para el quiz");
            return $results;
        }

        $this->log_debug("Preguntas encontradas: " . implode(", ", $question_ids));

        // Decodificar opciones del informe para obtener respuestas y corrección
        $options = json_decode($report->options, true);
        if (!$options || !isset($options['correctness']) || !isset($options['user_answered'])) {
            $this->log_debug("El informe no contiene datos válidos: " . print_r($options, true));
            return $results;
        }

        $correctness = $options['correctness'];
        $user_answered = $options['user_answered'];

        $this->log_debug("Datos de corrección: " . print_r($correctness, true));
        $this->log_debug("Respuestas del usuario: " . print_r($user_answered, true));

        // Procesar cada pregunta
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $processed_count = 0;
        $failed_count = 0;

        foreach ($correctness as $question_key => $is_correct) {
            // Extraer ID de pregunta (formato question_id_XXX)
            if (preg_match('/question_id_(\d+)/', $question_key, $matches)) {
                $question_id = intval($matches[1]);
                $processed_count++;

                // Verificar si fue respondida
                $was_answered = isset($user_answered[$question_key]) && !empty($user_answered[$question_key]);

                $this->log_debug("Pregunta $question_id - Respondida: " . ($was_answered ? "Sí" : "No") . " - Correcta: " . ($is_correct ? "Sí" : "No"));

                // Procesar solo preguntas incorrectas o no respondidas
                if (!$is_correct || !$was_answered) {
                    $failed_count++;

                    // Obtener categoría de la pregunta
                    $category_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT category_id FROM $questions_table WHERE id = %d",
                        $question_id
                    ));

                    if (!$category_id) {
                        $category_id = 1; // Categoría por defecto
                    }

                    // Verificar si ya existe en la tabla
                    $existing = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $table_failed_questions 
                        WHERE user_id = %d AND question_id = %d",
                        $user_id, $question_id
                    ));

                    if ($existing) {
                        // Actualizar registro existente
                        $result = $wpdb->update(
                            $table_failed_questions,
                            array(
                            'consecutive_correct' => 0,
                            'is_active' => 1,
                            'last_attempt' => current_time('mysql')
                        ),
                            array('id' => $existing->id),
                            array('%d', '%d', '%s'),
                            array('%d')
                        );

                        $this->log_debug("Pregunta fallada actualizada: " . ($result !== false ? "Éxito" : "Error: " . $wpdb->last_error));
                    }
                    else {
                        // Insertar nueva pregunta fallada
                        $result = $wpdb->insert(
                            $table_failed_questions,
                            array(
                            'user_id' => $user_id,
                            'quiz_id' => $quiz_id,
                            'question_id' => $question_id,
                            'category_id' => $category_id,
                            'consecutive_correct' => 0,
                            'is_active' => 1,
                            'created_at' => current_time('mysql'),
                            'last_attempt' => current_time('mysql')
                        ),
                            array('%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s')
                        );

                        $this->log_debug("Nueva pregunta fallada insertada: " . ($result !== false ? "Éxito (ID: " . $wpdb->insert_id . ")" : "Error: " . $wpdb->last_error));
                    }
                }
                else if ($is_correct) {
                    // Si la pregunta fue correcta, actualizar contador si ya existe
                    $existing = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $table_failed_questions 
                        WHERE user_id = %d AND question_id = %d AND is_active = 1",
                        $user_id, $question_id
                    ));

                    if ($existing) {
                        $new_consecutive = $existing->consecutive_correct + 1;

                        // Obtener umbral de respuestas correctas necesarias
                        $settings = get_option('quiz_maker_fq_settings', array());
                        $consecutive_needed = isset($settings['consecutive_correct_needed']) ? 
                            intval($settings['consecutive_correct_needed']) : 3;

                        if ($new_consecutive >= $consecutive_needed) {
                            // Desactivar la pregunta
                            $wpdb->update(
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

                            $this->log_debug("Pregunta dominada (desactivada)");
                        }
                        else {
                            // Actualizar contador
                            $wpdb->update(
                                $table_failed_questions,
                                array(
                                'consecutive_correct' => $new_consecutive,
                                'last_attempt' => current_time('mysql')
                            ),
                                array('id' => $existing->id),
                                array('%d', '%s'),
                                array('%d')
                            );

                            $this->log_debug("Contador actualizado: $new_consecutive/$consecutive_needed");
                        }
                    }
                }
            }
        }

        $this->log_debug("Procesadas $processed_count preguntas, $failed_count falladas");
        $this->log_debug("==== FIN PROCESAMIENTO QUIZ RESULTS ====");

        return $results;
    }

    /**
     * Capture data directly from the form submission
     *
     * @since    1.0.2
     * @param    int       $quiz_id    Quiz ID.
     * @param    int       $user_id    User ID.
     */
    private function capture_and_process_form_data($quiz_id, $user_id)
    {
        $this->log_debug("Capturando datos directamente del formulario");

        if (!isset($_POST['ays_quiz_questions'])) {
            $this->log_debug("No se encontraron preguntas en el formulario");
            return;
        }

        // Obtener IDs de preguntas
        $questions_str = sanitize_text_field($_POST['ays_quiz_questions']);
        $questions_ids = explode(',', $questions_str);

        if (empty($questions_ids)) {
            $this->log_debug("Lista de IDs de preguntas vacía");
            return;
        }

        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_questions = $wpdb->prefix . 'aysquiz_questions';
        $table_answers = $wpdb->prefix . 'aysquiz_answers';

        // Procesar cada pregunta
        foreach ($questions_ids as $question_id) {
            $question_id = intval($question_id);

            // Verificar si la pregunta fue respondida
            $answer_key = 'ays_questions[ays-question-' . $question_id . ']';
            $was_answered = false;
            $user_answer = '';
            $is_correct = false;

            // Buscar la respuesta en diferentes formatos posibles
            if (isset($_POST['ays_questions']) && is_array($_POST['ays_questions']) &&
            isset($_POST['ays_questions']['ays-question-' . $question_id])) {
                $was_answered = true;
                $user_answer = $_POST['ays_questions']['ays-question-' . $question_id];
            }
            elseif (isset($_POST[$answer_key])) {
                $was_answered = true;
                $user_answer = $_POST[$answer_key];
            }

            // Si hay información de corrección disponible, usarla
            if (isset($_POST['ays_answer_correct']) && isset($_POST['ays_quiz_questions'])) {
                $questions = explode(',', $_POST['ays_quiz_questions']);
                $index = array_search($question_id, $questions);
                if ($index !== false && isset($_POST['ays_answer_correct'][$index])) {
                    $is_correct = (bool)$_POST['ays_answer_correct'][$index];
                }
            }
            // Si no hay información de corrección pero tenemos la respuesta, verificarla contra la BD
            elseif ($was_answered && !empty($user_answer)) {
                // Obtener respuestas correctas para esta pregunta
                $correct_answers = $wpdb->get_col($wpdb->prepare(
                    "SELECT id FROM $table_answers 
                    WHERE question_id = %d AND correct = 1",
                    $question_id
                ));

                $is_correct = !empty($correct_answers) && in_array($user_answer, $correct_answers);
            }

            $this->log_debug("Pregunta $question_id - Respondida: " . ($was_answered ? "Sí" : "No") . " - Correcta: " . ($is_correct ? "Sí" : "No"));

            // Solo procesar preguntas incorrectas o no contestadas
            if (!$is_correct || !$was_answered) {
                $this->process_failed_question($quiz_id, $user_id, $question_id);
            }
            else {
                $this->process_correct_question($user_id, $question_id);
            }
        }
    }

    /**
     * Capture data from the latest report
     *
     * @since    1.0.2
     * @param    int       $quiz_id    Quiz ID.
     * @param    int       $user_id    User ID.
     */
    private function capture_from_latest_report($quiz_id, $user_id)
    {
        $this->log_debug("Intentando capturar datos del informe más reciente");

        global $wpdb;
        $reports_table = $wpdb->prefix . 'aysquiz_reports';

        // Obtener el informe más reciente
        $report = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reports_table 
            WHERE quiz_id = %d AND user_id = %d 
            ORDER BY id DESC LIMIT 1",
            $quiz_id, $user_id
        ));

        if (!$report) {
            $this->log_debug("No se encontró ningún informe reciente");
            return;
        }

        $this->log_debug("Informe encontrado con ID: " . $report->id);

        // Decodificar las opciones
        $options = json_decode($report->options, true);
        if (!$options || !isset($options['correctness'])) {
            $this->log_debug("Opciones inválidas en el informe: " . $report->options);
            return;
        }

        // Procesar los datos del informe
        $correctness = $options['correctness'];
        $user_answered = isset($options['user_answered']) ? $options['user_answered'] : array();

        foreach ($correctness as $question_key => $is_correct) {
            // Extraer ID de pregunta
            if (preg_match('/question_id_(\d+)/', $question_key, $matches)) {
                $question_id = intval($matches[1]);

                // Verificar si fue respondida
                $was_answered = isset($user_answered[$question_key]) && !empty($user_answered[$question_key]);

                $this->log_debug("Del informe - Pregunta $question_id - Respondida: " . ($was_answered ? "Sí" : "No") . " - Correcta: " . ($is_correct ? "Sí" : "No"));

                // Procesar según el resultado
                if (!$is_correct || !$was_answered) {
                    $this->process_failed_question($quiz_id, $user_id, $question_id);
                }
                else {
                    $this->process_correct_question($user_id, $question_id);
                }
            }
        }
    }


    /**
     * Schedule a delayed capture to ensure we get the report data
     *
     * @since    1.0.2
     * @param    int       $quiz_id    Quiz ID.
     * @param    int       $user_id    User ID.
     */
    private function schedule_delayed_capture($quiz_id, $user_id)
    {
    // No programamos nada aquí por ahora, pero podríamos hacerlo en el futuro si es necesario
    }

    /**
     * Process a failed or unanswered question
     *
     * @since    1.0.2
     * @param    int       $quiz_id      Quiz ID.
     * @param    int       $user_id      User ID.
     * @param    int       $question_id  Question ID.
     */
    private function process_failed_question($quiz_id, $user_id, $question_id)
    {
        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_questions = $wpdb->prefix . 'aysquiz_questions';

        // Obtener categoría de la pregunta
        $category_id = $wpdb->get_var($wpdb->prepare(
            "SELECT category_id FROM $table_questions WHERE id = %d",
            $question_id
        ));

        if (!$category_id) {
            $this->log_debug("No se encontró categoría para la pregunta $question_id");
            $category_id = 1; // Valor por defecto
        }

        // Verificar si ya existe
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_failed_questions 
            WHERE user_id = %d AND question_id = %d",
            $user_id, $question_id
        ));

        if ($existing) {
            // Actualizar registro existente
            $result = $wpdb->update(
                $table_failed_questions,
                array(
                'consecutive_correct' => 0,
                'is_active' => 1,
                'last_attempt' => current_time('mysql')
            ),
                array('id' => $existing->id),
                array('%d', '%d', '%s'),
                array('%d')
            );

            $this->log_debug("Pregunta fallada actualizada: " . ($result !== false ? "Éxito" : "Error: " . $wpdb->last_error));
        }
        else {
            // Insertar nueva pregunta fallada
            $result = $wpdb->insert(
                $table_failed_questions,
                array(
                'user_id' => $user_id,
                'quiz_id' => $quiz_id,
                'question_id' => $question_id,
                'category_id' => $category_id,
                'consecutive_correct' => 0,
                'is_active' => 1,
                'created_at' => current_time('mysql'),
                'last_attempt' => current_time('mysql')
            ),
                array('%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s')
            );

            $this->log_debug("Nueva pregunta fallada insertada: " . ($result !== false ? "Éxito (ID: " . $wpdb->insert_id . ")" : "Error: " . $wpdb->last_error));
        }
    }

    /**
     * Process a correctly answered question
     *
     * @since    1.0.2
     * @param    int       $user_id      User ID.
     * @param    int       $question_id  Question ID.
     */
    private function process_correct_question($user_id, $question_id)
    {
        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';

        // Verificar si la pregunta ya estaba en la lista de falladas
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_failed_questions 
            WHERE user_id = %d AND question_id = %d AND is_active = 1",
            $user_id, $question_id
        ));

        if ($existing) {
            // Incrementar contador de aciertos consecutivos
            $new_consecutive = $existing->consecutive_correct + 1;

            // Obtener configuración
            $settings = get_option('quiz_maker_fq_settings', array());
            $consecutive_needed = isset($settings['consecutive_correct_needed']) ? 
                intval($settings['consecutive_correct_needed']) : 3;

            if ($new_consecutive >= $consecutive_needed) {
                // Marcar como dominada
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

                $this->log_debug("Pregunta dominada (desactivada): " . ($result !== false ? "Éxito" : "Error: " . $wpdb->last_error));
            }
            else {
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

                $this->log_debug("Contador de aciertos actualizado: " . ($result !== false ? "Éxito ($new_consecutive/$consecutive_needed)" : "Error: " . $wpdb->last_error));
            }
        }
    }

    /**
     * Capture Quiz Maker's AJAX submissions
     */
    public function rucarpe_capture_quiz_ajax()
    {
        add_action('wp_ajax_ays_finish_quiz', array($this, 'rucarpe_intercept_finish_quiz'), 1);
        add_action('wp_ajax_nopriv_ays_finish_quiz', array($this, 'rucarpe_intercept_finish_quiz'), 1);
    }

    /**
     * Intercept the Quiz Maker finish quiz AJAX call
     */
    public function rucarpe_intercept_finish_quiz()
    {
        $this->log_debug("Interceptando llamada AJAX de finalización de quiz");

        // No interferir con el flujo normal del plugin
        if (!is_user_logged_in()) {
            return;
        }

        $user_id = get_current_user_id();
        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;

        if (!$quiz_id) {
            $this->log_debug("No se encontró ID del quiz en la solicitud AJAX");
            return;
        }

        $this->log_debug("Quiz ID: $quiz_id, User ID: $user_id");

        // Programar procesamiento diferido para asegurarnos de que Quiz Maker guarde el informe primero
        wp_schedule_single_event(time() + 5, 'rucarpe_process_delayed_quiz_results', array($quiz_id, $user_id));
    }

    /**
     * Process quiz results after a delay
     * 
     * @param int $quiz_id The quiz ID
     * @param int $user_id The user ID
     */
    public function rucarpe_process_delayed_quiz_results($quiz_id, $user_id)
    {
        $this->log_debug("Ejecutando procesamiento diferido para quiz ID: $quiz_id, usuario ID: $user_id");

        global $wpdb;
        $reports_table = $wpdb->prefix . 'aysquiz_reports';

        // Obtener el informe más reciente
        $report = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $reports_table 
            WHERE quiz_id = %d AND user_id = %d 
            ORDER BY id DESC LIMIT 1",
            $quiz_id, $user_id
        ));

        if (!$report) {
            $this->log_debug("No se encontró informe en el procesamiento diferido");
            return;
        }

        $this->log_debug("Informe encontrado con ID: " . $report->id);

        // Decodificar opciones del informe
        $options = json_decode($report->options, true);
        if (!$options || !isset($options['correctness'])) {
            $this->log_debug("El informe no contiene datos de corrección válidos");
            return;
        }

        $correctness = $options['correctness'];
        $user_answered = isset($options['user_answered']) ? $options['user_answered'] : array();

        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_questions = $wpdb->prefix . 'aysquiz_questions';

        foreach ($correctness as $question_key => $is_correct) {
            // Extraer ID de pregunta
            if (preg_match('/question_id_(\d+)/', $question_key, $matches)) {
                $question_id = intval($matches[1]);

                // Verificar si fue respondida
                $was_answered = isset($user_answered[$question_key]) && !empty($user_answered[$question_key]);

                // Solo procesar preguntas incorrectas o no respondidas
                if (!$is_correct || !$was_answered) {
                    // Obtener categoría de la pregunta
                    $category_id = $wpdb->get_var($wpdb->prepare(
                        "SELECT category_id FROM $table_questions WHERE id = %d",
                        $question_id
                    ));

                    if (!$category_id) {
                        $category_id = 1; // Categoría por defecto
                    }

                    // Verificar si ya existe en la tabla
                    $existing = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM $table_failed_questions 
                        WHERE user_id = %d AND question_id = %d",
                        $user_id, $question_id
                    ));

                    if ($existing) {
                        // Actualizar registro existente
                        $wpdb->update(
                            $table_failed_questions,
                            array(
                            'consecutive_correct' => 0,
                            'is_active' => 1,
                            'last_attempt' => current_time('mysql')
                        ),
                            array('id' => $existing->id),
                            array('%d', '%d', '%s'),
                            array('%d')
                        );
                    }
                    else {
                        // Insertar nueva pregunta fallada
                        $wpdb->insert(
                            $table_failed_questions,
                            array(
                            'user_id' => $user_id,
                            'quiz_id' => $quiz_id,
                            'question_id' => $question_id,
                            'category_id' => $category_id,
                            'consecutive_correct' => 0,
                            'is_active' => 1,
                            'created_at' => current_time('mysql'),
                            'last_attempt' => current_time('mysql')
                        ),
                            array('%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s')
                        );
                    }
                }
            }
        }

        $this->log_debug("Procesamiento diferido completado");
    }


    /**
     * Generate a quiz with failed questions
     *
     * @since    1.0.0
     */
    public function rucarpe_generate_failed_questions_quiz()
    {
        // Verificar nonce para seguridad
        check_ajax_referer('quiz_maker_fq_ajax_nonce', 'security');

        // Solo proceder si el usuario está autenticado
        if (!is_user_logged_in()) {
            $this->log_debug("ERROR: Usuario no autenticado intentando generar quiz");
            wp_send_json_error(__('Debes iniciar sesión para acceder a los tests de preguntas falladas.', 'quiz-maker-failed-questions'));
            return;
        }

        $user_id = get_current_user_id();
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;

        $this->log_debug("Generando quiz - Usuario: $user_id, Categoría: $category_id");

        // Verificar que existan preguntas falladas
        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';

        $count_query = $wpdb->prepare(
            "SELECT COUNT(DISTINCT question_id) 
             FROM $table_failed_questions 
             WHERE user_id = %d 
             AND is_active = 1" .
            ($category_id > 0 ? " AND category_id = %d" : ""),
            $category_id > 0 ? array($user_id, $category_id) : array($user_id)
        );

        $question_count = $wpdb->get_var($count_query);
        $this->log_debug("Preguntas falladas disponibles: $question_count");

        if (empty($question_count) || $question_count == 0) {
            $this->log_debug("ERROR: No hay preguntas falladas disponibles");
            wp_send_json_error(__('No hay preguntas falladas disponibles para esta categoría.', 'quiz-maker-failed-questions'));
            return;
        }

        // Generar el quiz
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-quiz-maker-failed-questions-generator.php';
        $quiz_id = Quiz_Maker_Failed_Questions_Generator::generate_quiz($category_id, $user_id);

        if ($quiz_id > 0) {
            $this->log_debug("Quiz generado exitosamente con ID: $quiz_id");

            // Obtener URL de la página actual
            $current_page = isset($_POST['current_page']) ? esc_url_raw($_POST['current_page']) : '';
            if (empty($current_page)) {
                $current_page = home_url();
            }

            // Generar token único para el transient
            $transient_key = 'fq_quiz_' . md5($user_id . $quiz_id . time());

            // Guardar SOLO lo necesario en el transient
            $quiz_data = array(
                'quiz_id' => $quiz_id,
                'user_id' => $user_id
            );

            $set_result = set_transient($transient_key, $quiz_data, 30 * MINUTE_IN_SECONDS);
            $this->log_debug("Transient guardado: " . ($set_result ? "Éxito" : "Fallo"));

            // Crear URL para mostrar el quiz
            $redirect_url = add_query_arg(array(
                'fq_quiz' => $transient_key
            ), $current_page);

            $this->log_debug("Redirigiendo a: $redirect_url");
            wp_send_json_success(array('redirect' => $redirect_url));
        }
        else {
            $this->log_debug("ERROR: No se pudo generar el quiz");
            wp_send_json_error(__('Error al crear el test. Por favor, inténtalo de nuevo.', 'quiz-maker-failed-questions'));
        }
    }

    /**
     * Añade un botón para volver al menú principal.
     *
     * @since    1.0.0
     * @param    string    $content   Contenido del botón de inicio.
     * @param    int       $quiz_id   ID del quiz.
     * @return   string               Contenido modificado.
     */
    public function add_return_button($content, $quiz_id)
    {
        global $wpdb;

        // Verificar si es un quiz de preguntas falladas
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

        // Verificar también por el título
        $quiz_title = $wpdb->get_var($wpdb->prepare(
            "SELECT title FROM $table_quizzes WHERE id = %d",
            $quiz_id
        ));

        // Si no es un quiz de preguntas falladas, salimos
        if (empty($is_fq_quiz) && strpos($quiz_title, 'Test de preguntas falladas') === false) {
            return $content;
        }

        // Añadir botón para volver al menú
        $return_url = remove_query_arg(array('fq_action', 'category_id'));
        $return_button = '<a href="' . esc_url($return_url) . '" class="ays-quiz-fq-back-to-menu">' .
            __('« Volver al menú', 'quiz-maker-failed-questions') .
            '</a>';

        return $return_button . ' ' . $content;
    }

    /**
     * Update consecutive correct answers count
     *
     * @since    1.0.0
     */
    public function rucarpe_update_consecutive_correct()
    {
        // Check nonce for security
        check_ajax_referer('quiz_maker_fq_consecutive_nonce', 'security');

        // Only proceed if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('You must be logged in to update your progress.');
        }

        $user_id = get_current_user_id();
        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $is_correct = isset($_POST['is_correct']) ? intval($_POST['is_correct']) : 0;

        if (!$question_id) {
            wp_send_json_error('Invalid question ID.');
        }

        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';

        // Get the current record
        $record = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_failed_questions 
            WHERE user_id = %d AND question_id = %d AND is_active = 1",
            $user_id, $question_id
        ));

        if (!$record) {
            wp_send_json_error('Question not found in your failed questions list.');
        }

        // Get settings
        $settings = get_option('quiz_maker_fq_settings', array());
        $consecutive_needed = isset($settings['consecutive_correct_needed']) ? intval($settings['consecutive_correct_needed']) : 3;

        if ($is_correct) {
            // Question was answered correctly
            $new_consecutive = $record->consecutive_correct + 1;

            if ($new_consecutive >= $consecutive_needed) {
                // Deactivate the question after meeting the consecutive correct threshold
                $wpdb->update(
                    $table_failed_questions,
                    array(
                    'is_active' => 0,
                    'consecutive_correct' => $new_consecutive,
                    'last_attempt' => current_time('mysql')
                ),
                    array(
                    'id' => $record->id
                ),
                    array('%d', '%d', '%s'),
                    array('%d')
                );

                wp_send_json_success(sprintf(
                    __('Perfect! You have correctly answered this question %d consecutive times. It will no longer appear in your failed questions tests.', 'quiz-maker-failed-questions'),
                    $consecutive_needed
                ));
            }
            else {
                // Update consecutive correct counter
                $wpdb->update(
                    $table_failed_questions,
                    array(
                    'consecutive_correct' => $new_consecutive,
                    'last_attempt' => current_time('mysql')
                ),
                    array(
                    'id' => $record->id
                ),
                    array('%d', '%s'),
                    array('%d')
                );

                $remaining = $consecutive_needed - $new_consecutive;
                wp_send_json_success(sprintf(
                    __('Correct! You need to answer this question correctly %d more time(s) to master it.', 'quiz-maker-failed-questions'),
                    $remaining
                ));
            }
        }
        else {
            // Question was answered incorrectly - reset counter
            $wpdb->update(
                $table_failed_questions,
                array(
                'consecutive_correct' => 0,
                'last_attempt' => current_time('mysql')
            ),
                array(
                'id' => $record->id
            ),
                array('%d', '%s'),
                array('%d')
            );

            wp_send_json_success(__('Incorrect answer. Your consecutive correct answers counter has been reset.', 'quiz-maker-failed-questions'));
        }
    }

    /**
     * Hook into Quiz Maker to display our temporary quiz
     *
     * @since    1.0.0
     */
    public function rucarpe_load_failed_questions_quiz()
    {
        if (!isset($_GET['fq_quiz'])) {
            return;
        }

        $transient_key = sanitize_key($_GET['fq_quiz']);
        $quiz_data = get_transient($transient_key);

        if (!$quiz_data || !isset($quiz_data['quiz_id'])) {
            $this->log_debug('No se encontró el test temporal con clave: ' . $transient_key);
            return;
        }

        $quiz_id = $quiz_data['quiz_id'];
        $this->log_debug('Cargando test con ID: ' . $quiz_id);

        // Verificar que el quiz existe en la base de datos
        global $wpdb;
        $table_quizzes = $wpdb->prefix . 'aysquiz_quizes';
        $quiz_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_quizzes WHERE id = %d",
            $quiz_id
        ));

        if (!$quiz_exists) {
            $this->log_debug('El quiz con ID ' . $quiz_id . ' no existe en la base de datos');
            return;
        }

        // SOLUCIÓN: Insertar el shortcode del quiz en el contenido de la página
        add_filter('the_content', function ($content) use ($quiz_id) {
            // Añadir un div para identificación
            $quiz_content = '<div class="quiz-maker-failed-questions-test">';
            $quiz_content .= do_shortcode('[ays_quiz id="' . $quiz_id . '"]');
            $quiz_content .= '</div>';

            return $content . $quiz_content;
        });

        // Marcar este como un quiz de preguntas falladas para el procesamiento posterior
        add_filter('ays_finish_quiz_data', function ($data) {
            $data['is_failed_questions_quiz'] = true;
            return $data;
        });

        $this->log_debug('Filtros añadidos para mostrar el quiz ' . $quiz_id);
    }

    /**
     * Add admin debug tools in footer
     * Only visible to administrators and when debug mode is active
     *
     * @since    1.0.0
     */
    public function rucarpe_add_admin_debug_tools()
    {
        if (!current_user_can('manage_options') || !$this->debug_mode) {
            return;
        }

?>
        <div id="quiz-maker-fq-debug-panel" style="position: fixed; bottom: 20px; right: 20px; width: 300px; max-height: 400px; background: #fff; border: 1px solid #ddd; box-shadow: 0 0 10px rgba(0,0,0,0.2); z-index: 999999; overflow: auto; padding: 10px; font-family: monospace; font-size: 12px;">
            <h4 style="margin: 0 0 10px; padding-bottom: 5px; border-bottom: 1px solid #ddd;">Quiz Maker Failed Questions Debug</h4>
            <div id="quiz-maker-fq-debug-log" style="max-height: 300px; overflow: auto;"></div>
            <div style="margin-top: 10px; border-top: 1px solid #ddd; padding-top: 5px;">
                <button id="quiz-maker-fq-debug-clear" style="background: #f7f7f7; border: 1px solid #ccc; padding: 3px 8px; cursor: pointer;">Clear Log</button>
                <button id="quiz-maker-fq-debug-test" style="background: #f7f7f7; border: 1px solid #ccc; padding: 3px 8px; cursor: pointer; margin-left: 5px;">Test DB</button>
                <button id="quiz-maker-fq-debug-verify" style="background: #f7f7f7; border: 1px solid #ccc; padding: 3px 8px; cursor: pointer; margin-left: 5px;">Verify System</button>
            </div>
        </div>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Inicializar panel de debug
            window.quizMakerFqLog = function(message, data) {
                var $log = $('#quiz-maker-fq-debug-log');
                var time = new Date().toLocaleTimeString();
                var html = '<div style="margin-bottom: 5px; padding-bottom: 5px; border-bottom: 1px dotted #eee;">';
                html += '<span style="color: #999;">[' + time + ']</span> ';
                html += '<span>' + message + '</span>';
                
                if (data) {
                    if (typeof data === 'object') {
                        html += '<pre style="margin: 5px 0 0 10px; padding: 5px; background: #f7f7f7; border-left: 3px solid #ddd; white-space: pre-wrap;">' + 
                                JSON.stringify(data, null, 2) + '</pre>';
                    } else {
                        html += '<pre style="margin: 5px 0 0 10px; padding: 5px; background: #f7f7f7; border-left: 3px solid #ddd; white-space: pre-wrap;">' + 
                                data + '</pre>';
                    }
                }
                
                html += '</div>';
                $log.append(html);
                $log.scrollTop($log[0].scrollHeight);
            };
            
            // Botones de control
            $('#quiz-maker-fq-debug-clear').on('click', function() {
                $('#quiz-maker-fq-debug-log').empty();
                quizMakerFqLog('Log cleared');
            });
            
            $('#quiz-maker-fq-debug-test').on('click', function() {
                quizMakerFqLog('Testing database connection...');
                $.ajax({
                    url: quiz_maker_fq_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'quiz_maker_fq_test_db',
                        security: quiz_maker_fq_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            quizMakerFqLog('Database test successful', response.data);
                        } else {
                            quizMakerFqLog('Database test failed', response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        quizMakerFqLog('AJAX error', {status: status, error: error});
                    }
                });
            });
            
            $('#quiz-maker-fq-debug-verify').on('click', function() {
                quizMakerFqLog('Verifying system...');
                $.ajax({
                    url: quiz_maker_fq_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'quiz_maker_fq_verify_system',
                        security: quiz_maker_fq_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            quizMakerFqLog('System verification complete', response.data);
                        } else {
                            quizMakerFqLog('System verification failed', response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        quizMakerFqLog('AJAX error', {status: status, error: error});
                    }
                });
            });
            
            // Iniciar log
            quizMakerFqLog('Debug panel initialized');
            
            // Capturar eventos Quiz Maker
            $(document).on('aysQuizStarted', function(e, data) {
                quizMakerFqLog('Quiz started', data);
            });
            
            $(document).on('aysQuizCompleted', function(e, data) {
                quizMakerFqLog('Quiz completed', data);
            });
        });
        </script>
        <?php
    }

    /**
     * Test database operations
     * Only for admin debugging purposes
     *
     * @since    1.0.0
     */
    public function rucarpe_test_database_operations()
    {
        check_ajax_referer('quiz_maker_fq_ajax_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';

        // Comprobar si la tabla existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_failed_questions'") == $table_failed_questions;

        if (!$table_exists) {
            wp_send_json_error('Table does not exist');
        }

        // Insertar un registro de prueba
        $user_id = get_current_user_id();

        if (!$user_id) {
            wp_send_json_error('User not logged in');
        }

        // Usar datos de prueba fijos
        $test_data = array(
            'user_id' => $user_id,
            'quiz_id' => 999,
            'question_id' => 999,
            'category_id' => 1,
            'consecutive_correct' => 0,
            'is_active' => 1,
            'created_at' => current_time('mysql'),
            'last_attempt' => current_time('mysql')
        );

        // Intentar la inserción
        $result = $wpdb->insert(
            $table_failed_questions,
            $test_data,
            array('%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s')
        );

        if ($result === false) {
            wp_send_json_error('Insert error: ' . $wpdb->last_error);
        }
        else {
            // Verificar que se haya insertado
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_failed_questions");
            wp_send_json_success(array(
                'message' => 'Test record inserted successfully',
                'count' => $count,
                'id' => $wpdb->insert_id
            ));
        }
    }

    /**
     * Verify system configuration
     * Only for admin debugging purposes
     *
     * @since    1.0.0
     */
    public function rucarpe_verify_system()
    {
        check_ajax_referer('quiz_maker_fq_ajax_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $output = "=== Quiz Maker Failed Questions System Verification ===\n\n";

        // WordPress info
        $output .= "WordPress Version: " . get_bloginfo('version') . "\n";
        $output .= "PHP Version: " . phpversion() . "\n";
        $output .= "MySQL Version: " . $this->get_mysql_version() . "\n\n";

        // Plugin info
        $output .= "Plugin Version: " . QUIZ_MAKER_FQ_VERSION . "\n";
        $output .= "Debug Mode: " . ($this->debug_mode ? 'Enabled' : 'Disabled') . "\n\n";

        // Database tables
        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_failed_questions'") == $table_failed_questions;

        $output .= "Database Tables:\n";
        $output .= "- Failed Questions Table: " . ($table_exists ? 'Exists' : 'Missing') . "\n";

        if ($table_exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_failed_questions");
            $output .= "  Total Records: " . $count . "\n";

            // Verificar estructura de la tabla
            $output .= "  Table Structure:\n";
            $columns = $wpdb->get_results("DESCRIBE $table_failed_questions");
            foreach ($columns as $column) {
                $output .= "    - " . $column->Field . " (" . $column->Type . ")\n";
            }
        }

        // Check Quiz Maker
        $quiz_maker_active = false;
        if (function_exists('is_plugin_active')) {
            $quiz_maker_active = is_plugin_active('quiz-maker/quiz-maker.php') || is_plugin_active('quiz-maker-pro/quiz-maker-pro.php');
        }

        $output .= "\nQuiz Maker Plugin: " . ($quiz_maker_active ? 'Active' : 'Not Active') . "\n";

        // Hooks check
        $output .= "\nHooks Verification:\n";

        // Check if our hooks are registered
        global $wp_filter;
        $our_hooks = array(
            'wp_ajax_ays_finish_quiz' => false,
            'wp_ajax_nopriv_ays_finish_quiz' => false,
            'ays_finish_quiz' => false
        );

        foreach ($our_hooks as $hook => $status) {
            if (isset($wp_filter[$hook])) {
                $found = false;
                foreach ($wp_filter[$hook]->callbacks as $priority => $callbacks) {
                    foreach ($callbacks as $cb) {
                        if (is_array($cb['function']) && is_object($cb['function'][0]) &&
                        get_class($cb['function'][0]) === 'Quiz_Maker_Failed_Questions_Public') {
                            $found = true;
                            $output .= "- $hook: Registered at priority $priority\n";
                            break 2;
                        }
                    }
                }
                if (!$found) {
                    $output .= "- $hook: Hook exists but our callback is not registered\n";
                }
            }
            else {
                $output .= "- $hook: Hook not registered in WordPress\n";
            }
        }

        // Settings check
        $settings = get_option('quiz_maker_fq_settings', array());
        $output .= "\nPlugin Settings:\n";
        foreach ($settings as $key => $value) {
            $output .= "- $key: $value\n";
        }

        wp_send_json_success($output);
    }

    /**
     * Get MySQL version
     *
     * @since    1.0.0
     * @return   string    MySQL version.
     */
    private function get_mysql_version()
    {
        global $wpdb;
        $version = $wpdb->get_var("SELECT VERSION()");
        return $version ? $version : 'Unknown';
    }

    /**
     * Debug logging function
     *
     * @since    1.0.0
     * @param    string    $message    Message to log.
     */
    private function log_debug($message)
    {
        if ($this->debug_mode) {
            error_log('[Quiz Maker FQ] ' . $message);
        }
    }

    /**
     * Setup debug tools only after WordPress is fully loaded
     * This fixes the timing issue with current_user_can()
     *
     * @since    1.0.1
     */
    public function rucarpe_setup_debug_tools()
    {
        // Ahora es seguro usar current_user_can() porque WordPress ya está completamente cargado
        if (current_user_can('manage_options')) {
            add_action('wp_ajax_quiz_maker_fq_test_db', array($this, 'rucarpe_test_database_operations'));
            add_action('wp_ajax_quiz_maker_fq_verify_system', array($this, 'rucarpe_verify_system'));
            add_action('wp_footer', array($this, 'rucarpe_add_admin_debug_tools'), 999);
        }
    }
}