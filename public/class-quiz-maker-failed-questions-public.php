<?php
/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://example.com
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
            'nonce' => wp_create_nonce('quiz_maker_fq_generate_quiz'),
            'consecutive_nonce' => wp_create_nonce('quiz_maker_fq_update_consecutive')
        ));
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
     * Save failed questions when a quiz is completed
     *
     * @since    1.0.0
     */
    // Añade este código a tu función quiz_maker_fq_save_failed_questions en public/class-quiz-maker-failed-questions-public.php

    function quiz_maker_fq_save_failed_questions() {
        // Crear archivo de registro
        $log_file = WP_CONTENT_DIR . '/failed-questions-debug.log';
        
        // Registrar llamada a la función
        file_put_contents($log_file, "==== Function called at " . date('Y-m-d H:i:s') . " ====\n", FILE_APPEND);
        
        // Verificar si el usuario está conectado
        $user_logged_in = is_user_logged_in();
        file_put_contents($log_file, "User logged in: " . ($user_logged_in ? 'Yes' : 'No') . "\n", FILE_APPEND);
        
        if (!$user_logged_in) {
            file_put_contents($log_file, "Early return: User not logged in\n\n", FILE_APPEND);
            return;
        }
        
        // Registrar todas las variables POST (ten cuidado, podría contener datos sensibles)
        file_put_contents($log_file, "POST keys available: " . implode(", ", array_keys($_POST)) . "\n", FILE_APPEND);
        
        // Verificar datos específicos
        $has_quiz_id = isset($_POST['quiz_id']);
        $has_questions_ids = isset($_POST['questions_ids']);
        $has_correctness = isset($_POST['correctness']);
        
        file_put_contents($log_file, "Has quiz_id: " . ($has_quiz_id ? 'Yes' : 'No') . "\n", FILE_APPEND);
        file_put_contents($log_file, "Has questions_ids: " . ($has_questions_ids ? 'Yes' : 'No') . "\n", FILE_APPEND);
        file_put_contents($log_file, "Has correctness: " . ($has_correctness ? 'Yes' : 'No') . "\n", FILE_APPEND);
        
        // Verificar si es un quiz de preguntas falladas
        $is_failed_quiz = isset($_POST['is_failed_questions_quiz']);
        file_put_contents($log_file, "Is failed questions quiz: " . ($is_failed_quiz ? 'Yes' : 'No') . "\n", FILE_APPEND);
        
        if ($is_failed_quiz) {
            file_put_contents($log_file, "Early return: Is a failed questions quiz\n\n", FILE_APPEND);
            return;
        }
        
        if (!$has_quiz_id || !$has_questions_ids || !$has_correctness) {
            file_put_contents($log_file, "Early return: Missing required POST data\n\n", FILE_APPEND);
            return;
        }
        
        // Registrar valores importantes
        $quiz_id = intval($_POST['quiz_id']);
        $questions_ids = isset($_POST['questions_ids']) ? (array)$_POST['questions_ids'] : array();
        $correctness = isset($_POST['correctness']) ? (array)$_POST['correctness'] : array();
        $user_id = get_current_user_id();
        
        file_put_contents($log_file, "Quiz ID: " . $quiz_id . "\n", FILE_APPEND);
        file_put_contents($log_file, "User ID: " . $user_id . "\n", FILE_APPEND);
        file_put_contents($log_file, "Questions IDs: " . print_r($questions_ids, true) . "\n", FILE_APPEND);
        file_put_contents($log_file, "Correctness: " . print_r($correctness, true) . "\n", FILE_APPEND);
        
        // Continuar con el resto de la función...
        // [Aquí va el código original, pero añadimos más registros]
        
        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_questions = $wpdb->prefix . 'aysquiz_questions';
        
        // Process each question
        foreach ($questions_ids as $index => $question_id) {
            if (!isset($correctness[$index])) {
                file_put_contents($log_file, "Skipping question ID $question_id - no correctness data\n", FILE_APPEND);
                continue;
            }
            
            $question_id = intval($question_id);
            $is_correct = intval($correctness[$index]);
            
            file_put_contents($log_file, "Processing question ID $question_id - Correct: " . ($is_correct ? 'Yes' : 'No') . "\n", FILE_APPEND);
            
            // Get question category
            $category_id = $wpdb->get_var($wpdb->prepare(
                "SELECT category_id FROM $table_questions WHERE id = %d",
                $question_id
            ));
            
            if (!$category_id) {
                file_put_contents($log_file, "Skipping question ID $question_id - no category found\n", FILE_APPEND);
                continue;
            }
            
            file_put_contents($log_file, "Question $question_id category: $category_id\n", FILE_APPEND);
            
            // Check if this question is already in the failed questions table
            $existing_record = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_failed_questions 
                WHERE user_id = %d AND question_id = %d AND is_active = 1",
                $user_id, $question_id
            ));
            
            file_put_contents($log_file, "Existing record: " . ($existing_record ? 'Yes' : 'No') . "\n", FILE_APPEND);
            
            if ($is_correct == 0) {
                file_put_contents($log_file, "Question $question_id answered incorrectly\n", FILE_APPEND);
                // Question was answered incorrectly - add to failed questions
                if ($existing_record) {
                    // Reset consecutive correct counter
                    $result = $wpdb->update(
                        $table_failed_questions,
                        array(
                            'consecutive_correct' => 0,
                            'last_attempt' => current_time('mysql'),
                            'is_active' => 1
                        ),
                        array(
                            'id' => $existing_record->id
                        )
                    );
                    file_put_contents($log_file, "Updated existing record: " . ($result ? 'Success' : 'Failed') . "\n", FILE_APPEND);
                } else {
                    // Add new record
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
                        )
                    );
                    file_put_contents($log_file, "Inserted new record: " . ($result ? 'Success' : 'Failed') . "\n", FILE_APPEND);
                    if (!$result) {
                        file_put_contents($log_file, "DB Error: " . $wpdb->last_error . "\n", FILE_APPEND);
                    }
                }
            } else if ($existing_record) {
                file_put_contents($log_file, "Question $question_id answered correctly\n", FILE_APPEND);
                // Question was answered correctly
                // Increment consecutive correct counter
                $new_consecutive = $existing_record->consecutive_correct + 1;
                
                // Get settings
                $settings = get_option('quiz_maker_fq_settings', array());
                $consecutive_needed = isset($settings['consecutive_correct_needed']) ? intval($settings['consecutive_correct_needed']) : 3;
                
                file_put_contents($log_file, "New consecutive: $new_consecutive / $consecutive_needed needed\n", FILE_APPEND);
                
                if ($new_consecutive >= $consecutive_needed) {
                    // Deactivate the question after meeting the consecutive correct threshold
                    $result = $wpdb->update(
                        $table_failed_questions,
                        array(
                            'is_active' => 0,
                            'last_attempt' => current_time('mysql')
                        ),
                        array(
                            'id' => $existing_record->id
                        )
                    );
                    file_put_contents($log_file, "Deactivated question: " . ($result ? 'Success' : 'Failed') . "\n", FILE_APPEND);
                } else {
                    // Update consecutive correct counter
                    $result = $wpdb->update(
                        $table_failed_questions,
                        array(
                            'consecutive_correct' => $new_consecutive,
                            'last_attempt' => current_time('mysql')
                        ),
                        array(
                            'id' => $existing_record->id
                        )
                    );
                    file_put_contents($log_file, "Updated consecutive counter: " . ($result ? 'Success' : 'Failed') . "\n", FILE_APPEND);
                }
            }
        }
        
        file_put_contents($log_file, "Function completed successfully\n\n", FILE_APPEND);
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
            wp_send_json_error('You must be logged in to take a failed questions test.');
        }
        
        $user_id = get_current_user_id();
        $category_id = isset($_POST['category_id']) ? intval($_POST['category_id']) : 0;
        
        // Get settings
        $settings = get_option('quiz_maker_fq_settings', array());
        $max_questions = isset($settings['max_questions']) ? intval($settings['max_questions']) : 20;
        
        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_questions = $wpdb->prefix . 'aysquiz_questions';
        
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
        
        $question_ids = $wpdb->get_col($query);
        
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
                    )
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
                    )
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
                )
            );
            
            wp_send_json_success('Respuesta incorrecta. Tu contador de respuestas correctas consecutivas se ha reiniciado.');
        }
    }
    
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
            return;
        }
        
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
                        )
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
                        )
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
                    )
                );
            }
        }
    }
}
