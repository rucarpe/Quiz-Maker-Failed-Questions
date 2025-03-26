<?php
/**
 * Shortcode que maneja todo lo relacionado con los tests de preguntas falladas.
 *
 * @link       https://rucarpe.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/public/partials
 */

class Quiz_Maker_Failed_Questions_All_In_One {

    /**
     * El nombre de este plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $plugin_name    El ID de este plugin.
     */
    private $plugin_name;

    /**
     * La versión de este plugin.
     *
     * @since    1.0.0
     * @access   private
     * @var      string    $version    La versión actual de este plugin.
     */
    private $version;

    /**
     * Inicializa la clase y establece sus propiedades.
     *
     * @since    1.0.0
     * @param    string    $plugin_name       El nombre del plugin.
     * @param    string    $version           La versión de este plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        
        // Registra el shortcode principal
        add_shortcode('quiz_maker_failed_questions', array($this, 'render_failed_questions'));
        
        // Registra hooks para procesar los resultados
        add_action('ays_finish_quiz', array($this, 'process_quiz_results'), 10, 2);
        
        // Filtro para añadir un botón de regreso al menú
        add_filter('ays_before_start_button', array($this, 'add_return_button'), 10, 2);
    }

    /**
     * Renderiza el shortcode de preguntas falladas.
     *
     * @since    1.0.0
     * @param    array    $atts    Atributos del shortcode.
     * @return   string   Contenido del shortcode.
     */
    public function render_failed_questions($atts) {
        $atts = shortcode_atts(array(), $atts, 'quiz_maker_failed_questions');
        
        // Comprueba si es una solicitud para iniciar un test
        if (isset($_GET['fq_action']) && $_GET['fq_action'] === 'start_test') {
            return $this->generate_and_display_test();
        }
        
        // Si no, muestra el menú
        return $this->display_menu();
    }

    /**
     * Muestra el menú de preguntas falladas.
     *
     * @since    1.0.0
     * @return   string   HTML del menú.
     */
    private function display_menu() {
        // Obtener opciones
        $settings = get_option('quiz_maker_fq_settings', array());
        $shortcode_text = isset($settings['shortcode_text']) ? esc_attr($settings['shortcode_text']) : 'Test de preguntas falladas';
        $max_questions = isset($settings['max_questions']) ? intval($settings['max_questions']) : 20;
        $consecutive_correct_needed = isset($settings['consecutive_correct_needed']) ? intval($settings['consecutive_correct_needed']) : 3;
        
        // Obtener categorías con preguntas falladas para el usuario actual
        $categories = array();
        
        if (is_user_logged_in()) {
            global $wpdb;
            $user_id = get_current_user_id();
            $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
            $table_categories = $wpdb->prefix . 'aysquiz_categories';
            
            $query = $wpdb->prepare(
                "SELECT c.id as category_id, c.title, COUNT(DISTINCT fq.question_id) as question_count
                 FROM $table_failed_questions fq
                 JOIN $table_categories c ON fq.category_id = c.id
                 WHERE fq.user_id = %d AND fq.is_active = 1
                 GROUP BY c.id, c.title
                 ORDER BY c.title ASC",
                $user_id
            );
            
            $categories = $wpdb->get_results($query, ARRAY_A);
        }
        
        // Preparar la salida
        ob_start();
        ?>
        <div class="ays-quiz-failed-questions-container">
            <h2><?php echo esc_html($shortcode_text); ?></h2>
            
            <?php if (!is_user_logged_in()): ?>
                <div class="ays-quiz-fq-no-questions-message">
                    <p><?php echo esc_html__('Debes iniciar sesión para acceder a los tests de preguntas falladas.', 'quiz-maker-failed-questions'); ?></p>
                </div>
            <?php elseif (empty($categories)): ?>
                <div class="ays-quiz-fq-no-questions-message">
                    <p><?php echo esc_html__('No tienes preguntas falladas disponibles. Realiza algunos cuestionarios para registrar tus preguntas falladas.', 'quiz-maker-failed-questions'); ?></p>
                </div>
            <?php else: ?>
                <div class="ays-quiz-fq-description">
                    <p><?php echo esc_html__('Selecciona una categoría para practicar las preguntas que has fallado anteriormente, o elige la opción "Mixto" para practicar preguntas de todas las categorías.', 'quiz-maker-failed-questions'); ?></p>
                </div>
                
                <div class="ays-quiz-fq-categories-list">
                    <div class="ays-quiz-fq-category-item ays-quiz-fq-category-mix">
                        <h3><?php echo esc_html__('Test Mixto', 'quiz-maker-failed-questions'); ?></h3>
                        <p><?php echo esc_html__('Preguntas de todas las categorías', 'quiz-maker-failed-questions'); ?></p>
                        <span class="ays-quiz-fq-question-count"><?php 
                            $total_questions = 0;
                            foreach ($categories as $category) {
                                $total_questions += intval($category['question_count']);
                            }
                            echo sprintf(esc_html__('%d preguntas falladas', 'quiz-maker-failed-questions'), $total_questions); 
                        ?></span>
                        <a 
                            href="<?php echo esc_url(add_query_arg(array('fq_action' => 'start_test', 'category_id' => 0), get_permalink())); ?>" 
                            class="ays-quiz-fq-start-test" 
                            <?php echo ($total_questions == 0) ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>>
                            <?php echo esc_html__('Iniciar Test', 'quiz-maker-failed-questions'); ?>
                        </a>
                    </div>
                    
                    <?php foreach ($categories as $category): ?>
                        <div class="ays-quiz-fq-category-item">
                            <h3><?php echo esc_html($category['title']); ?></h3>
                            <span class="ays-quiz-fq-question-count"><?php 
                                echo sprintf(esc_html__('%d preguntas falladas', 'quiz-maker-failed-questions'), intval($category['question_count'])); 
                            ?></span>
                            <a 
                                href="<?php echo esc_url(add_query_arg(array('fq_action' => 'start_test', 'category_id' => $category['category_id']), get_permalink())); ?>" 
                                class="ays-quiz-fq-start-test" 
                                <?php echo (intval($category['question_count']) == 0) ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>>
                                <?php echo esc_html__('Iniciar Test', 'quiz-maker-failed-questions'); ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="ays-quiz-fq-info-box">
                    <h4><?php echo esc_html__('¿Cómo funciona?', 'quiz-maker-failed-questions'); ?></h4>
                    <ul>
                        <li><?php echo esc_html__('Las preguntas que fallas en tus cuestionarios se guardan automáticamente.', 'quiz-maker-failed-questions'); ?></li>
                        <li><?php echo sprintf(esc_html__('Necesitas responder correctamente una pregunta %d veces consecutivas para considerarla dominada.', 'quiz-maker-failed-questions'), $consecutive_correct_needed); ?></li>
                        <li><?php echo esc_html__('Si vuelves a fallar la pregunta, el contador se reinicia.', 'quiz-maker-failed-questions'); ?></li>
                        <li><?php echo sprintf(esc_html__('Cada test contiene un máximo de %d preguntas falladas.', 'quiz-maker-failed-questions'), $max_questions); ?></li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Genera y muestra un test de preguntas falladas.
     *
     * @since    1.0.0
     * @return   string   HTML con el quiz generado.
     */
    private function generate_and_display_test() {
        if (!is_user_logged_in()) {
            return '<div class="ays-quiz-failed-questions-error">' . 
                __('Debes iniciar sesión para acceder a los tests de preguntas falladas.', 'quiz-maker-failed-questions') . 
                '</div>';
        }
        
        $user_id = get_current_user_id();
        $category_id = isset($_GET['category_id']) ? intval($_GET['category_id']) : 0;
        
        // Generar el quiz
        $quiz_id = $this->generate_quiz($category_id, $user_id);
        
        if ($quiz_id > 0) {
            // Mostrar el quiz
            return do_shortcode('[ays_quiz id="' . $quiz_id . '"]');
        } else {
            return '<div class="ays-quiz-failed-questions-error">' . 
                __('No se encontraron preguntas falladas para generar un test.', 'quiz-maker-failed-questions') . 
                '</div><p><a href="' . esc_url(remove_query_arg(array('fq_action', 'category_id'), get_permalink())) . '" class="ays-quiz-fq-back-to-menu">' . 
                __('Volver al menú', 'quiz-maker-failed-questions') . 
                '</a></p>';
        }
    }

    /**
     * Genera un quiz con preguntas falladas.
     *
     * @since    1.0.0
     * @param    int     $category_id    ID de categoría (0 para mixto).
     * @param    int     $user_id        ID de usuario.
     * @return   int                     ID del quiz generado o 0 en caso de error.
     */
    private function generate_quiz($category_id, $user_id) {
        global $wpdb;
        
        // Obtener opciones
        $settings = get_option('quiz_maker_fq_settings', array());
        $max_questions = isset($settings['max_questions']) ? intval($settings['max_questions']) : 20;
        $title = isset($settings['shortcode_text']) ? esc_html($settings['shortcode_text']) : 'Test de preguntas falladas';
        
        // Determinar qué categoría usar
        $category_condition = "";
        if ($category_id > 0) {
            $category_condition = $wpdb->prepare("AND category_id = %d", $category_id);
            
            // Obtener nombre de categoría
            $table_categories = $wpdb->prefix . 'aysquiz_categories';
            $category_name = $wpdb->get_var($wpdb->prepare(
                "SELECT title FROM $table_categories WHERE id = %d",
                $category_id
            ));
            
            if ($category_name) {
                $title .= ' - ' . $category_name;
            }
        }
        
        // Obtener preguntas falladas para este usuario
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        
        // Verificar que la tabla existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_failed_questions'") == $table_failed_questions;
        if (!$table_exists) {
            return 0;
        }
        
        $query = $wpdb->prepare(
            "SELECT DISTINCT question_id 
            FROM $table_failed_questions 
            WHERE user_id = %d 
            AND is_active = 1 
            $category_condition
            ORDER BY RAND()
            LIMIT %d",
            $user_id,
            $max_questions
        );
        
        $question_ids = $wpdb->get_col($query);
        
        if (empty($question_ids)) {
            return 0; // No se encontraron preguntas
        }
        
        // Crear un quiz temporal
        $table_quizzes = $wpdb->prefix . 'aysquiz_quizes';
        
        // Obtener opciones por defecto de un quiz existente
        $default_options_query = "SELECT options FROM $table_quizzes WHERE published = 1 LIMIT 1";
        $default_options = $wpdb->get_var($default_options_query);
        
        if (!$default_options) {
            // Si no hay ningún quiz, usar opciones básicas
            $default_options = $this->get_default_quiz_options();
        }
        
        // Intentar verificar y añadir columna si es necesario
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'is_failed_questions_quiz'",
            $wpdb->dbname,
            $table_quizzes
        ));
        
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_quizzes ADD COLUMN is_failed_questions_quiz tinyint(1) NOT NULL DEFAULT 0");
        }
        
        // Preparar datos del quiz
        $quiz_data = array(
            'title' => $title,
            'description' => 'Test de preguntas falladas generado automáticamente.',
            'quiz_image' => '',
            'quiz_category_id' => $category_id > 0 ? $category_id : 1, // Categoría por defecto si es mixto
            'question_ids' => implode(',', $question_ids),
            'published' => 1,
            'options' => $default_options,
            'author_id' => $user_id,
            'create_date' => current_time('mysql'),
            'is_failed_questions_quiz' => 1
        );
        
        // Insertar el quiz
        $result = $wpdb->insert(
            $table_quizzes,
            $quiz_data,
            array('%s', '%s', '%s', '%d', '%s', '%d', '%s', '%d', '%s', '%d')
        );
        
        if ($result === false) {
            return 0; // Error al crear el quiz
        }
        
        $quiz_id = $wpdb->insert_id;
        
        // Asociar preguntas al quiz
        $table_quiz_questions = $wpdb->prefix . 'aysquiz_questions_relations';
        
        foreach ($question_ids as $index => $question_id) {
            $wpdb->insert(
                $table_quiz_questions,
                array(
                    'quiz_id' => $quiz_id,
                    'question_id' => $question_id,
                    'ordering' => $index + 1
                ),
                array('%d', '%d', '%d')
            );
        }
        
        return $quiz_id;
    }

    /**
     * Obtiene opciones por defecto para el quiz.
     *
     * @since    1.0.0
     * @return   string   Opciones en formato JSON.
     */
    private function get_default_quiz_options() {
        $default_options = array(
            'quiz_theme' => 'classic_light',
            'color' => '#27AE60',
            'bg_color' => '#fff',
            'text_color' => '#000',
            'timer' => 0,
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
            'autofill_user_data' => 'on',
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

    /**
     * Procesa los resultados del quiz y actualiza el estado de las preguntas falladas.
     *
     * @since    1.0.0
     * @param    array    $data      Datos del resultado.
     * @param    int      $quiz_id   ID del quiz.
     */
    public function process_quiz_results($data, $quiz_id) {
        global $wpdb;
        
        // Verificar si es un quiz de preguntas falladas
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
        
        // Si no es un quiz de preguntas falladas, salimos
        if (empty($is_fq_quiz) && strpos($quiz_title, 'Test de preguntas falladas') === false) {
            return;
        }
        
        // Procesar solo si el usuario está logueado
        if (!is_user_logged_in()) {
            return;
        }
        
        $user_id = get_current_user_id();
        
        // Obtener ajustes
        $settings = get_option('quiz_maker_fq_settings', array());
        $consecutive_needed = isset($settings['consecutive_correct_needed']) ? intval($settings['consecutive_correct_needed']) : 3;
        
        // Procesar cada pregunta del resultado
        if (isset($data['questions']) && is_array($data['questions'])) {
            $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
            
            foreach ($data['questions'] as $question) {
                $question_id = isset($question['questionId']) ? intval($question['questionId']) : 0;
                $is_correct = isset($question['correctAnswer']) && $question['correctAnswer'] === true;
                
                if ($question_id > 0) {
                    if ($is_correct) {
                        // Incrementar contador de respuestas correctas
                        $wpdb->query($wpdb->prepare(
                            "UPDATE $table_failed_questions 
                            SET consecutive_correct = consecutive_correct + 1,
                                last_attempt = %s
                            WHERE user_id = %d AND question_id = %d AND is_active = 1",
                            current_time('mysql'),
                            $user_id,
                            $question_id
                        ));
                        
                        // Verificar si la pregunta debe marcarse como dominada
                        $current_count = $wpdb->get_var($wpdb->prepare(
                            "SELECT consecutive_correct 
                            FROM $table_failed_questions 
                            WHERE user_id = %d AND question_id = %d AND is_active = 1
                            LIMIT 1",
                            $user_id,
                            $question_id
                        ));
                        
                        if ($current_count >= $consecutive_needed) {
                            // Marcar como dominada (inactiva)
                            $wpdb->update(
                                $table_failed_questions,
                                array('is_active' => 0),
                                array('user_id' => $user_id, 'question_id' => $question_id, 'is_active' => 1),
                                array('%d'),
                                array('%d', '%d', '%d')
                            );
                        }
                    } else {
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
                    }
                }
            }
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
    public function add_return_button($content, $quiz_id) {
        global $wpdb;
        
        // Verificar si es un quiz de preguntas falladas
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
}