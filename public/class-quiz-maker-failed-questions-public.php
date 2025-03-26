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

class Quiz_Maker_Failed_Questions_Public {

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
     * Initialize the class and set its properties.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       The name of the plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;

        // Hook into WordPress actions
        add_action('wp_enqueue_scripts', array($this, 'enqueue_styles'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_scripts'));

        $this->register_ajax_handlers();
    }

    /**
     * Register the stylesheets for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        wp_enqueue_style($this->plugin_name, QUIZ_MAKER_FQ_PUBLIC_URL . 'css/failed-questions-public.css', array(), $this->version, 'all');
    }

    /**
     * Register the JavaScript for the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        wp_enqueue_script($this->plugin_name, QUIZ_MAKER_FQ_PUBLIC_URL . 'js/failed-questions-public.js', array('jquery'), $this->version, false);
        wp_localize_script($this->plugin_name, 'quiz_maker_fq_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('quiz_maker_fq_ajax_nonce'),
            'consecutive_nonce' => wp_create_nonce('quiz_maker_fq_consecutive_nonce')
        ));
    }

    /**
 * Process URL parameters and show the appropriate content.
 *
 * @since    1.0.0
 * @param    string    $content    The content.
 * @return   string    Modified content if needed.
 */
public function process_content_for_failed_questions($content) {
    // Only run on single posts/pages with our shortcode
    if (!is_singular() || !has_shortcode($content, 'quiz_maker_failed_questions')) {
        return $content;
    }
    
    // Check for test parameters
    if (isset($_GET['fq_test'])) {
        $test_type = sanitize_text_field($_GET['fq_test']);
        
        if ($test_type === 'mix') {
            // Show mixed test
            return do_shortcode('[quiz_maker_failed_questions_test category_id="0"]');
        } elseif ($test_type === 'category' && isset($_GET['cat_id'])) {
            // Show category test
            $category_id = intval($_GET['cat_id']);
            return do_shortcode('[quiz_maker_failed_questions_test category_id="' . $category_id . '"]');
        }
    }
    
    return $content;
    }
    

    /**
     * Register AJAX handlers.
     *
     * @since    1.0.0
     */
    public function register_ajax_handlers() {
        add_action('wp_ajax_quiz_maker_fq_generate_quiz', array($this, 'generate_quiz'));
        add_action('wp_ajax_nopriv_quiz_maker_fq_generate_quiz', array($this, 'generate_quiz'));
    }

    /**
     * Register shortcodes
     *
     * @since    1.0.0
     */
    public function register_shortcodes() {
        add_shortcode('quiz_maker_failed_questions', array($this, 'failed_questions_shortcode'));
    }

    /**
     * Failed questions shortcode callback
     *
     * @since    1.0.0
     * @param    array    $atts    Shortcode attributes.
     * @return   string             Shortcode output.
     */
    public function failed_questions_shortcode($atts) {
        $atts = shortcode_atts(array(
            'style' => 'default',
        ), $atts, 'quiz_maker_failed_questions');
        
        // Only show to logged in users
        if (!is_user_logged_in()) {
            return '<p>' . __('Please log in to access your failed questions tests.', 'quiz-maker-failed-questions') . '</p>';
        }
        
        // Get user's failed questions categories
        $categories = $this->get_user_failed_question_categories(get_current_user_id());
        
        // Start output buffer
        ob_start();
        
        // Include template
        include QUIZ_MAKER_FQ_DIR . 'public/partials/failed-questions-menu-display.php';
        
        // Return the buffered content
        return ob_get_clean();
    }

    /**
     * Get categories where user has failed questions
     *
     * @since    1.0.0
     * @param    int       $user_id    User ID.
     * @return   array                 Categories list.
     */
    private function get_user_failed_question_categories($user_id) {
        global $wpdb;
        
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_question_categories = $wpdb->prefix . 'aysquiz_categories';
        
        $query = $wpdb->prepare(
            "SELECT DISTINCT fq.category_id, c.title,
            COUNT(fq.id) as question_count
            FROM $table_failed_questions fq
            JOIN $table_question_categories c ON fq.category_id = c.id
            WHERE fq.user_id = %d
            AND fq.is_active = 1
            GROUP BY fq.category_id, c.title
            ORDER BY c.title ASC",
            $user_id
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        return $results ? $results : array();
    }

    /**
     * Direct hook into Quiz Maker's finish quiz process
     *
     * @param array $data The data from the finished quiz
     */
    /**
     * Función para procesar y guardar las preguntas falladas
     * Esta función se ejecutará cuando un usuario termine un quiz
     */
    public function hook_quiz_finished($data) {
        // Registrar en el log para depuración
        error_log('===== CAPTURANDO QUIZ FINALIZADO =====');
        
        // Solo proceder si hay datos y el usuario está conectado
        if (!is_array($data) || !is_user_logged_in()) {
            error_log('No hay datos o usuario no conectado');
            return $data;
        }
        
        global $wpdb;
        $user_id = get_current_user_id();
        
        // Extraer el ID del quiz
        $quiz_id = isset($data['quiz_id']) ? intval($data['quiz_id']) : 0;
        if (!$quiz_id) {
            error_log('ID de quiz no encontrado');
            return $data;
        }
        
        error_log('Quiz ID: ' . $quiz_id);
        error_log('User ID: ' . $user_id);
        
        // Obtener las preguntas y respuestas
        // Debemos adaptarnos a la estructura que envía Quiz Maker
        $questions_ids = isset($data['questions_ids']) ? $data['questions_ids'] : array();
        if (empty($questions_ids) && isset($data['ays_quiz_questions'])) {
            // Intentar obtener de otro campo que Quiz Maker podría estar usando
            $questions_ids = explode(',', $data['ays_quiz_questions']);
        }
        
        // Obtener información de corrección
        $correctness = array();
        if (isset($data['correctness'])) {
            $correctness = $data['correctness'];
        } elseif (isset($data['answered']) && isset($data['answered']['correctness'])) {
            $correctness = $data['answered']['correctness'];
        }
        
        // Si no tenemos datos de corrección, intentamos reconstruirlos
        if (empty($correctness)) {
            error_log('Intentando reconstruir datos de corrección');
            $user_answered = array();
            
            // Buscar respuestas en diferentes formatos posibles
            if (isset($data['user_answered'])) {
                $user_answered = $data['user_answered'];
            } elseif (isset($data['answered']) && isset($data['answered']['user_answered'])) {
                $user_answered = $data['answered']['user_answered'];
            } elseif (isset($data['ays_questions'])) {
                // Formato alternativo
                foreach ($data['ays_questions'] as $q_key => $answer_id) {
                    if (strpos($q_key, 'ays-question-') !== false) {
                        $q_id = str_replace('ays-question-', '', $q_key);
                        $user_answered['question_id_' . $q_id] = $answer_id;
                    }
                }
            }
            
            // Para cada pregunta, determinar si fue correcta
            foreach ($questions_ids as $q_id) {
                $q_id = intval($q_id);
                $question_key = 'question_id_' . $q_id;
                
                // Verificar si la respuesta fue correcta
                $is_correct = false;
                
                // Buscar en el array de respuestas correctas
                if (isset($data['ays_answer_correct'])) {
                    // Determinar el índice correcto en el array de respuestas
                    // Esto requiere un mapeo que puede variar según cómo Quiz Maker almacene los datos
                    $index = array_search($q_id, $questions_ids);
                    if ($index !== false && isset($data['ays_answer_correct'][$index])) {
                        $is_correct = (bool)$data['ays_answer_correct'][$index];
                    }
                }
                
                $correctness[$q_id] = $is_correct;
            }
        }
        
        error_log('Questions IDs: ' . print_r($questions_ids, true));
        error_log('Correctness: ' . print_r($correctness, true));
        
        // Tablas
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_questions = $wpdb->prefix . 'aysquiz_questions';
        
        // Procesar cada pregunta
        foreach ($questions_ids as $question_id) {
            $question_id = intval($question_id);
            
            // Determinar si la pregunta fue respondida correctamente
            $is_correct = false;
            if (isset($correctness[$question_id])) {
                $is_correct = (bool)$correctness[$question_id];
            } elseif (isset($correctness['question_id_' . $question_id])) {
                $is_correct = (bool)$correctness['question_id_' . $question_id];
            }
            
            error_log('Pregunta ' . $question_id . ' - Correcta: ' . ($is_correct ? 'Sí' : 'No'));
            
            // Si la pregunta no fue respondida correctamente, la añadimos a la tabla
            if (!$is_correct) {
                // Obtener la categoría de la pregunta
                $category_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT category_id FROM $table_questions WHERE id = %d",
                    $question_id
                ));
                
                if (!$category_id) {
                    error_log('No se encontró categoría para la pregunta ' . $question_id);
                    continue;
                }
                
                // Verificar si la pregunta ya está en la tabla
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_failed_questions 
                    WHERE user_id = %d AND question_id = %d AND is_active = 1",
                    $user_id, $question_id
                ));
                
                if ($existing) {
                    // Reiniciar el contador de respuestas correctas
                    $result = $wpdb->update(
                        $table_failed_questions,
                        array(
                            'consecutive_correct' => 0,
                            'last_attempt' => current_time('mysql')
                        ),
                        array('id' => $existing->id),
                        array('%d', '%s'),
                        array('%d')
                    );
                    
                    error_log('Actualización de pregunta fallada existente: ' . ($result !== false ? 'Éxito' : 'Error: ' . $wpdb->last_error));
                } else {
                    // Añadir nueva pregunta fallada
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
                    
                    error_log('Inserción de nueva pregunta fallada: ' . ($result !== false ? 'Éxito (ID: ' . $wpdb->insert_id . ')' : 'Error: ' . $wpdb->last_error));
                }
            } else {
                // La pregunta fue respondida correctamente
                // Verificar si estaba en la lista de preguntas falladas
                $existing = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table_failed_questions 
                    WHERE user_id = %d AND question_id = %d AND is_active = 1",
                    $user_id, $question_id
                ));
                
                if ($existing) {
                    // Incrementar el contador de respuestas correctas consecutivas
                    $new_consecutive = $existing->consecutive_correct + 1;
                    
                    // Obtener el umbral de respuestas correctas necesarias
                    $settings = get_option('quiz_maker_fq_settings', array());
                    $consecutive_needed = isset($settings['consecutive_correct_needed']) ? 
                        intval($settings['consecutive_correct_needed']) : 3;
                    
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
                        
                        error_log('Pregunta dominada (desactivada): ' . ($result !== false ? 'Éxito' : 'Error: ' . $wpdb->last_error));
                    } else {
                        // Actualizar el contador
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
                        
                        error_log('Actualización de contador de respuestas correctas: ' . ($result !== false ? 'Éxito' : 'Error: ' . $wpdb->last_error));
                    }
                }
            }
        }
        
        error_log('===== FIN DE CAPTURA DE QUIZ FINALIZADO =====');
        
        return $data;
    }

    /**
     * Generate a quiz with failed questions
     *
     * @since    1.0.0
     */
    public function generate_failed_questions_quiz() {
        // Check nonce for security
        check_ajax_referer('quiz_maker_fq_generate_quiz', 'security');
        
        // Only proceed if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('Debes iniciar sesión para realizar un test de preguntas falladas.');
        }
        
        $user_id = get_current_user_id();
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        // Get settings
        $settings = get_option('quiz_maker_fq_settings', array());
        $max_questions = isset($settings['max_questions']) ? intval($settings['max_questions']) : 20;
        
        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_questions = $wpdb->prefix . 'aysquiz_questions';
        
        // Debug log
        error_log('Generando test de preguntas falladas - Usuario: ' . $user_id . ', Categoría: ' . $category_id);
        
        // Build the query based on category or mix
        if ($category_id > 0) {
            // Questions from specific category
            $query = $wpdb->prepare(
                "SELECT fq.question_id 
                FROM $table_failed_questions fq
                JOIN $table_questions q ON fq.question_id = q.id
                WHERE fq.user_id = %d 
                AND fq.category_id = %d
                AND fq.is_active = 1
                ORDER BY RAND()
                LIMIT %d",
                $user_id, $category_id, $max_questions
            );
        } else {
            // Mix of questions from all categories
            $query = $wpdb->prepare(
                "SELECT fq.question_id 
                FROM $table_failed_questions fq
                JOIN $table_questions q ON fq.question_id = q.id
                WHERE fq.user_id = %d 
                AND fq.is_active = 1
                ORDER BY RAND()
                LIMIT %d",
                $user_id, $max_questions
            );
        }
        
        error_log('SQL Query: ' . $query);
        
        $question_ids = $wpdb->get_col($query);
        error_log('Preguntas obtenidas: ' . print_r($question_ids, true));
        
        // If no failed questions found
        if (empty($question_ids)) {
            wp_send_json_error('No tienes preguntas falladas disponibles para esta categoría.');
        }
        
        // Get the first quiz ID as a template to use its settings
        $template_quiz_id = $wpdb->get_var($wpdb->prepare(
            "SELECT quiz_id FROM $table_failed_questions 
            WHERE user_id = %d
            LIMIT 1",
            $user_id
        ));
        
        if (!$template_quiz_id) {
            wp_send_json_error('No se pudo encontrar un cuestionario como plantilla.');
        }
        
        // Get category name if category_id is specified
        $category_name = '';
        if ($category_id > 0) {
            $category_name = $this->get_category_name($category_id);
        }
        
        // Create temporary quiz title
        $quiz_title = ($category_id > 0) 
            ? sprintf('Test de preguntas falladas - %s', $category_name)
            : 'Test de preguntas falladas - Mixto';
        
        // Create temporary quiz data
        $temp_quiz_data = array(
            'title' => $quiz_title,
            'description' => 'Este test contiene tus preguntas falladas anteriormente.',
            'question_ids' => $question_ids,
            'template_quiz_id' => $template_quiz_id,
            'is_failed_questions_quiz' => true
        );
        
        // Store in a transient for 1 hour
        $transient_key = 'quiz_maker_fq_' . md5(serialize($temp_quiz_data) . $user_id . time());
        set_transient($transient_key, $temp_quiz_data, HOUR_IN_SECONDS);
        
        error_log('Test generado con clave: ' . $transient_key);
        
        // Return the quiz URL
        $quiz_url = add_query_arg(
            array('fq_quiz' => $transient_key),
            get_permalink()
        );
        
        wp_send_json_success(array('redirect' => $quiz_url));
    }

    /**
     * Get category name by ID
     *
     * @since    1.0.0
     * @param    int       $category_id    Category ID.
     * @return   string                     Category name.
     */
    private function get_category_name($category_id) {
        global $wpdb;
        $table_question_categories = $wpdb->prefix . 'aysquiz_categories';
        
        return $wpdb->get_var($wpdb->prepare(
            "SELECT title FROM $table_question_categories WHERE id = %d",
            $category_id
        ));
    }

    /**
     * Update consecutive correct answers count
     *
     * @since    1.0.0
     */
    public function update_consecutive_correct() {
        // Check nonce for security
        check_ajax_referer('quiz_maker_fq_update_consecutive', 'security');
        
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
                    '¡Perfecto! Has respondido correctamente esta pregunta %d veces consecutivas. Ya no aparecerá en tus test de preguntas falladas.',
                    $consecutive_needed
                ));
            } else {
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
                    '¡Correcto! Necesitas responder correctamente esta pregunta %d vez(ces) más para dominarla.',
                    $remaining
                ));
            }
        } else {
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
            
            wp_send_json_success('Respuesta incorrecta. Tu contador de respuestas correctas consecutivas se ha reiniciado.');
        }
    }
    
    /**
     * Hook into Quiz Maker to display our temporary quiz
     *
     * @since    1.0.0
     */
    /**
     * Hook into Quiz Maker to display our temporary quiz
     *
     * @since    1.0.0
     */
    public function maybe_load_failed_questions_quiz() {
        if (!isset($_GET['fq_quiz'])) {
            return;
        }
        
        $transient_key = sanitize_key($_GET['fq_quiz']);
        $quiz_data = get_transient($transient_key);
        
        if (!$quiz_data) {
            error_log('No se encontró el test temporal con clave: ' . $transient_key);
            return;
        }
        
        error_log('Cargando test temporal: ' . print_r($quiz_data, true));
        
        // This is a temporary quiz from our addon
        add_filter('ays_filter_get_quiz_id_by_shortcode', function($quiz_id) use ($quiz_data) {
            // Use the template quiz ID to get settings
            return $quiz_data['template_quiz_id'];
        });
        
        // Modify the quiz title
        add_filter('ays_quiz_title', function($title) use ($quiz_data) {
            return $quiz_data['title'];
        });
        
        // Modify the quiz description
        add_filter('ays_quiz_description', function($description) use ($quiz_data) {
            return $quiz_data['description'];
        });
        
        // Inject our question IDs
        add_filter('ays_quiz_get_question_ids_array', function($question_ids) use ($quiz_data) {
            error_log('Inyectando IDs de preguntas: ' . print_r($quiz_data['question_ids'], true));
            return $quiz_data['question_ids'];
        });
        
        // Mark this as a failed questions quiz
        add_filter('ays_finish_quiz_data', function($data) use ($quiz_data) {
            $data['is_failed_questions_quiz'] = true;
            return $data;
        });
        
        // Update the consecutive correct counter when quiz is finished
        add_action('ays_finish_quiz', array($this, 'process_failed_questions_quiz_results'), 10, 2);
    }
    
    /**
     * Process results of a failed questions quiz
     *
     * @since    1.0.0
     * @param    array     $results     Quiz results.
     * @param    array     $data        Quiz data.
     */
    public function process_failed_questions_quiz_results($results, $data) {
        // Only proceed if this is a failed questions quiz and user is logged in
        if (!isset($data['is_failed_questions_quiz']) || !is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        $questions_ids = isset($results['questions_ids']) ? $results['questions_ids'] : array();
        $correctness = isset($results['correctness']) ? $results['correctness'] : array();
        
        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        
        // Get settings
        $settings = get_option('quiz_maker_fq_settings', array());
        $consecutive_needed = isset($settings['consecutive_correct_needed']) ? intval($settings['consecutive_correct_needed']) : 3;
        
        // Process each question
        foreach ($questions_ids as $index => $question_id) {
            if (!isset($correctness[$index])) {
                continue;
            }
            
            $question_id = intval($question_id);
            $is_correct = (bool)$correctness[$index];
            
            // Get the current record
            $record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_failed_questions 
                WHERE user_id = %d AND question_id = %d AND is_active = 1",
                $user_id, $question_id
            ));
            
            if (!$record) {
                continue;
            }
            
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
                } else {
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
                }
            } else {
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
            }
        }
    }
    
    /**
     * Debug logging function
     *
     * @since    1.0.0
     * @param    string    $message    Message to log.
     */
    private function log_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG === true) {
            error_log('[Quiz Maker FQ] ' . $message);
        }
    }

    /**
     * Hook into ays_finish_quiz filter to process completed quizzes
     *
     * @param array $results Quiz results
     * @param array $data Quiz data
     * @return array Unchanged results
     */
    public function hook_finish_quiz($results, $data) {
        $this->log_debug("Ejecutando hook_finish_quiz con datos: " . print_r($data, true));
        
        // Comprobar si tenemos los datos necesarios
        if (isset($data['quiz_id']) && isset($results['questions_ids']) && isset($results['correctness'])) {
            $_POST['quiz_id'] = $data['quiz_id'];
            $_POST['questions_ids'] = $results['questions_ids'];
            $_POST['correctness'] = $results['correctness'];
            
            // Ejecutar la función para guardar las preguntas falladas
            $this->quiz_maker_fq_save_failed_questions();
        }
        
        return $results;
    }

    /**
     * Test function for database operations
     */
    public function test_database_operations() {
        // No verificar nonce para simplificar la prueba
        
        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        
        // Comprobar si la tabla existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_failed_questions'") == $table_failed_questions;
        
        if (!$table_exists) {
            wp_send_json_error('La tabla no existe');
            return;
        }
        
        // Insertar un registro de prueba
        $user_id = get_current_user_id();
        
        if (!$user_id) {
            wp_send_json_error('Usuario no conectado');
            return;
        }
        
        // Usar datos de prueba fijos
        $test_data = array(
            'user_id' => $user_id,
            'quiz_id' => 999,             // ID de prueba
            'question_id' => 999,         // ID de prueba
            'category_id' => 1,           // ID de prueba (categoría)
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
            wp_send_json_error('Error al insertar: ' . $wpdb->last_error);
        } else {
            // Verificar que se haya insertado
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_failed_questions");
            wp_send_json_success(array(
                'message' => 'Registro de prueba insertado correctamente',
                'count' => $count,
                'id' => $wpdb->insert_id
            ));
        }
    }

    /**
     * Add a listener script to capture quiz submission events
     */
    public function add_quiz_listener_script() {
        if (!is_user_logged_in()) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Función para capturar el evento de finalización del quiz
            $(document).on('aysQuizSubmitted', function(e, data) {
                console.log('Quiz submitted data captured:', data);
                
                // Enviar datos al servidor
                $.ajax({
                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    method: 'post',
                    data: {
                        action: 'quiz_maker_fq_process_results',
                        security: "<?php echo wp_create_nonce('quiz_maker_fq_process_results'); ?>",
                        quiz_data: data
                    },
                    success: function(response) {
                        console.log('Datos procesados:', response);
                    }
                });
            });
            
            // También capturar el formulario de envío del quiz
            $(document).on('submit', 'form.ays-quiz-form', function() {
                console.log('Quiz form submitted');
                // Los datos serán procesados por el hook de ays_finish_quiz
            });
        });
        </script>
        <?php
    }

    /**
     * Save quiz data from AJAX call
     */
    public function save_quiz_data() {
        check_ajax_referer('quiz_maker_fq_save_quiz_data', 'security');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Usuario no conectado');
            return;
        }
        
        $quiz_data = isset($_POST['quiz_data']) ? $_POST['quiz_data'] : array();
        error_log('Datos del quiz recibidos por AJAX: ' . print_r($quiz_data, true));
        
        // Procesar los datos y guardar las preguntas falladas
        // ...
        
        wp_send_json_success('Datos del quiz recibidos correctamente');
    }

    /**
     * Verify entire system setup
     */
    public function verify_system_setup() {
        if (!current_user_can('manage_options')) {
            return "Solo administradores pueden ejecutar esta verificación.";
        }
        
        $output = "<h3>Verificación del sistema de Quiz Maker Failed Questions</h3>";
        
        // 1. Verificar WordPress
        $output .= "<h4>WordPress</h4>";
        $output .= "<ul>";
        $output .= "<li>Versión: " . get_bloginfo('version') . "</li>";
        $output .= "<li>Modo debug: " . (defined('WP_DEBUG') && WP_DEBUG ? 'Activado' : 'Desactivado') . "</li>";
        $output .= "</ul>";
        
        // 2. Verificar PHP
        $output .= "<h4>PHP</h4>";
        $output .= "<ul>";
        $output .= "<li>Versión: " . phpversion() . "</li>";
        $output .= "<li>Límite de memoria: " . ini_get('memory_limit') . "</li>";
        $output .= "<li>Tiempo máximo de ejecución: " . ini_get('max_execution_time') . " segundos</li>";
        $output .= "</ul>";
        
        // 3. Verificar Base de Datos
        global $wpdb;
        $output .= "<h4>Base de Datos</h4>";
        $output .= "<ul>";
        $output .= "<li>Versión MySQL: " . $wpdb->get_var("SELECT VERSION()") . "</li>";
        
        // Verificar tabla de preguntas falladas
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_failed_questions'") == $table_failed_questions;
        $output .= "<li>Tabla de preguntas falladas: " . ($table_exists ? 'Existe' : 'No existe') . "</li>";
        
        if ($table_exists) {
            $count = $wpdb->get_var("SELECT COUNT(*) FROM $table_failed_questions");
            $output .= "<li>Registros en la tabla: " . $count . "</li>";
            
            // Intentar inserción de prueba
            $result = $wpdb->insert(
                $table_failed_questions,
                array(
                    'user_id' => get_current_user_id(),
                    'quiz_id' => 999, // ID de prueba
                    'question_id' => 999, // ID de prueba
                    'category_id' => 999, // ID de prueba
                    'consecutive_correct' => 0,
                    'is_active' => 1,
                    'created_at' => current_time('mysql'),
                    'last_attempt' => current_time('mysql')
                ),
                array('%d', '%d', '%d', '%d', '%d', '%d', '%s', '%s')
            );
            
            $output .= "<li>Prueba de inserción: " . ($result !== false ? 'Éxito (ID: ' . $wpdb->insert_id . ')' : 'Error: ' . $wpdb->last_error) . "</li>";
        }
        
        $output .= "</ul>";
        
        // 4. Verificar Quiz Maker
        $output .= "<h4>Quiz Maker</h4>";
        $output .= "<ul>";
        
        if (function_exists('get_plugin_data')) {
            $plugin_data = get_plugin_data(WP_PLUGIN_DIR . '/quiz-maker/quiz-maker.php', false, false);
            $output .= "<li>Versión instalada: " . ($plugin_data ? $plugin_data['Version'] : 'No disponible') . "</li>";
        } else {
            $output .= "<li>Función get_plugin_data no disponible</li>";
        }
        
        // Verificar si las tablas de Quiz Maker existen
        $quiz_table = $wpdb->prefix . 'aysquiz_quizes';
        $questions_table = $wpdb->prefix . 'aysquiz_questions';
        $categories_table = $wpdb->prefix . 'aysquiz_categories';
        
        $quiz_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$quiz_table'") == $quiz_table;
        $questions_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$questions_table'") == $questions_table;
        $categories_table_exists = $wpdb->get_var("SHOW TABLES LIKE '$categories_table'") == $categories_table;
        
        $output .= "<li>Tabla de quizzes: " . ($quiz_table_exists ? 'Existe' : 'No existe') . "</li>";
        $output .= "<li>Tabla de preguntas: " . ($questions_table_exists ? 'Existe' : 'No existe') . "</li>";
        $output .= "<li>Tabla de categorías: " . ($categories_table_exists ? 'Existe' : 'No existe') . "</li>";
        
        $output .= "</ul>";
        
        return $output;
    }

    /**
     * AJAX handler for system verification
     */
    public function ajax_verify_system() {
        check_ajax_referer('quiz_maker_fq_test_db', 'security');
        
        $output = $this->verify_system_setup();
        wp_send_json_success($output);
    }

    /**
     * Add a script to capture Quiz Maker's AJAX submissions
     */
    public function add_ajax_capture_script() {
        ?>
        <script type="text/javascript">
        (function($) {
            if (typeof jQuery.ajax !== 'undefined') {
                // Guardar la función original
                var originalAjax = jQuery.ajax;
                
                // Reemplazar con nuestra versión que captura datos de Quiz Maker
                jQuery.ajax = function(settings) {
                    // Interceptar solo las solicitudes de finalización de quiz
                    if (settings && settings.data && 
                        (typeof settings.data === 'string' && settings.data.indexOf('action=ays_finish_quiz') !== -1) ||
                        (typeof settings.data === 'object' && settings.data.action === 'ays_finish_quiz')) {
                        
                        console.log('Interceptada solicitud de finalización de quiz:', settings);
                        
                        // Llamar a nuestra función para guardar los datos
                        var originalSuccess = settings.success;
                        settings.success = function(response) {
                            // Llamar a la función original primero
                            if (originalSuccess) {
                                originalSuccess.apply(this, arguments);
                            }
                            
                            // Enviar los datos a nuestro endpoint
                            console.log('Enviando datos a nuestro endpoint:', response);
                            jQuery.ajax({
                                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                                type: 'POST',
                                data: {
                                    action: 'quiz_maker_fq_capture_ajax',
                                    quiz_data: response,
                                    original_data: settings.data,
                                    security: '<?php echo wp_create_nonce('quiz_maker_fq_capture_ajax'); ?>'
                                }
                            });
                        };
                    }
                    
                    // Llamar a la función original de AJAX
                    return originalAjax.apply(this, arguments);
                };
            }
        })(jQuery);
        </script>
        <?php
    }

    /**
     * Process captured AJAX data
     */
    public function process_captured_ajax() {
        check_ajax_referer('quiz_maker_fq_capture_ajax', 'security');
        
        error_log('===== DATOS AJAX CAPTURADOS =====');
        error_log('Quiz data: ' . print_r($_POST['quiz_data'], true));
        error_log('Original data: ' . print_r($_POST['original_data'], true));
        
        // Procesar los datos capturados
        // ...
        
        wp_send_json_success('Datos capturados con éxito');
    }

    /**
     * Add a Quiz Maker specific monitor
     */
    public function add_quiz_maker_monitor() {
        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return;
        }
        
        global $post;
        if (!$post || !has_shortcode($post->post_content, 'ays_quiz')) {
            return;
        }
        
        ?>
        <div id="quiz-maker-monitor" style="position: fixed; bottom: 10px; right: 10px; background: #fff; border: 1px solid #ddd; padding: 10px; max-width: 300px; max-height: 300px; overflow: auto; z-index: 9999; display: none;">
            <h4>Quiz Maker Monitor (Admin)</h4>
            <pre id="quiz-maker-monitor-content" style="white-space: pre-wrap; font-size: 12px;"></pre>
            <button id="quiz-maker-monitor-toggle">Mostrar</button>
            <button id="quiz-maker-monitor-clear">Limpiar</button>
        </div>
        
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Monitor de Quiz Maker
            var $monitor = $('#quiz-maker-monitor');
            var $content = $('#quiz-maker-monitor-content');
            var $toggle = $('#quiz-maker-monitor-toggle');
            var $clear = $('#quiz-maker-monitor-clear');
            
            $toggle.on('click', function() {
                if ($monitor.width() > 300) {
                    $monitor.css({width: '300px', height: '300px'});
                    $toggle.text('Expandir');
                } else {
                    $monitor.css({width: '600px', height: '500px'});
                    $toggle.text('Reducir');
                }
            });
            
            $clear.on('click', function() {
                $content.empty();
            });
            
            // Mostrar el monitor
            $monitor.show();
            
            // Función para registrar mensajes
            window.quizMonitorLog = function(message, data) {
                var timestamp = new Date().toLocaleTimeString();
                var logMessage = timestamp + ': ' + message;
                
                if (data) {
                    if (typeof data === 'object') {
                        logMessage += '\n' + JSON.stringify(data, null, 2);
                    } else {
                        logMessage += '\n' + data;
                    }
                }
                
                $content.prepend('<div style="margin-bottom: 10px; border-bottom: 1px dashed #ccc;">' + 
                                logMessage.replace(/\n/g, '<br>') + 
                                '</div>');
            };
            
            // Monitorear eventos de Quiz Maker
            $(document).on('aysQuizStarted', function(e, data) {
                quizMonitorLog('Quiz iniciado', data);
            });
            
            $(document).on('aysQuizCompleted', function(e, data) {
                quizMonitorLog('Quiz completado', data);
            });
            
            // Monitorear solicitudes AJAX
            var originalAjax = $.ajax;
            $.ajax = function(settings) {
                // Interceptar solicitudes de Quiz Maker
                if (settings && settings.data && 
                    (typeof settings.data === 'string' && 
                    (settings.data.indexOf('action=ays_finish_quiz') !== -1 || 
                    settings.data.indexOf('ays_quiz') !== -1)) ||
                    (typeof settings.data === 'object' && 
                    (settings.data.action === 'ays_finish_quiz' || 
                    settings.data.action === 'ays_get_quiz_data'))) {
                    
                    quizMonitorLog('Solicitud AJAX de Quiz Maker', {
                        url: settings.url,
                        type: settings.type,
                        data: settings.data
                    });
                    
                    // Capturar la respuesta
                    var originalSuccess = settings.success;
                    settings.success = function(response) {
                        quizMonitorLog('Respuesta AJAX de Quiz Maker', response);
                        
                        // Llamar a la función original
                        if (originalSuccess) {
                            originalSuccess.apply(this, arguments);
                        }
                    };
                }
                
                // Llamar a la función original
                return originalAjax.apply(this, arguments);
            };
            
            quizMonitorLog('Monitor de Quiz Maker inicializado');
        });
        </script>
        <?php
    }

    /**
     * Función para procesar la solicitud AJAX de finalización del quiz
     */
    public function quiz_maker_fq_save_failed_questions() {
        // Obtener datos enviados por Quiz Maker
        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        $questions_str = isset($_POST['ays_quiz_questions']) ? sanitize_text_field($_POST['ays_quiz_questions']) : '';
        $questions_ids = explode(',', $questions_str);
        
        // Reconstruir array de respuestas
        $user_answers = array();
        $correctness = array();
        
        foreach ($_POST as $key => $value) {
            if (strpos($key, 'ays_questions') === 0 && strpos($key, 'ays-question-') !== false) {
                // Formato: ays_questions[ays-question-1234]
                preg_match('/ays_questions\[ays-question-(\d+)\]/', $key, $matches);
                if (isset($matches[1])) {
                    $question_id = intval($matches[1]);
                    $user_answers[$question_id] = sanitize_text_field($value);
                }
            }
        }
        
        // Obtener respuestas correctas
        $answer_correct = isset($_POST['ays_answer_correct']) ? $_POST['ays_answer_correct'] : array();
        
        // Construir datos para procesar
        $data = array(
            'quiz_id' => $quiz_id,
            'questions_ids' => $questions_ids,
            'user_answered' => $user_answers,
            'ays_answer_correct' => $answer_correct
        );
        
        // Usar la función principal para procesar los datos
        $this->hook_quiz_finished($data);
        
        // No interferir con la respuesta original de Quiz Maker
        return;
    }

    /**
     * Procesar resultados enviados desde el frontend
     */
    public function process_results() {
        check_ajax_referer('quiz_maker_fq_process_results', 'security');
        
        if (!is_user_logged_in()) {
            wp_send_json_error('Usuario no conectado');
            return;
        }
        
        $quiz_data = isset($_POST['quiz_data']) ? $_POST['quiz_data'] : array();
        error_log('Datos de quiz recibidos para procesar: ' . print_r($quiz_data, true));
        
        // Construir datos para procesar
        $processed_data = array(
            'quiz_id' => isset($quiz_data['quiz_id']) ? intval($quiz_data['quiz_id']) : 0,
            'questions_ids' => isset($quiz_data['questions_ids']) ? $quiz_data['questions_ids'] : array(),
            'correctness' => isset($quiz_data['correctness']) ? $quiz_data['correctness'] : array(),
        );
        
        // Usar la función principal para procesar los datos
        $this->hook_quiz_finished($processed_data);
        
        wp_send_json_success('Datos procesados correctamente');
    }

    /**
     * Generate a quiz with failed questions.
     *
     * @since    1.0.0
     */
    public function generate_quiz() {
        // Check nonce for security
        check_ajax_referer('quiz_maker_fq_ajax_nonce', 'security');
        
        // Check if user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error('Debes iniciar sesión para acceder a los tests de preguntas falladas.');
            return;
        }
        
        // Get category ID from request
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        $user_id = get_current_user_id();
        
        // Include the generator class
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-quiz-maker-failed-questions-generator.php';
        
        // Generate the quiz
        $quiz_id = Quiz_Maker_Failed_Questions_Generator::generate_quiz($category_id, $user_id);
        
        if ($quiz_id > 0) {
            // Get the quiz shortcode link
            $redirect_url = add_query_arg(array(
                'action' => 'ays_finish_quiz',
                'quiz_id' => $quiz_id,
                'is_failed_questions_quiz' => 1
            ), home_url());
            
            wp_send_json_success(array(
                'redirect' => $redirect_url
            ));
        } else {
            wp_send_json_error('No se pudo generar el test de preguntas falladas.');
        }
    }
}