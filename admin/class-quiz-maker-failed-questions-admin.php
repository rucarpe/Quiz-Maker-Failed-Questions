<?php
/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://rucarpe.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/admin
 */

class Quiz_Maker_Failed_Questions_Admin {

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
     * @param    string    $plugin_name       The name of this plugin.
     * @param    string    $version           The version of this plugin.
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Register the stylesheets for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_styles() {
        $screen = get_current_screen();
        
        if (isset($screen->id) && 
            (strpos($screen->id, 'quiz-maker-failed-questions') !== false || 
             strpos($screen->id, 'quiz-maker') !== false)) {
            wp_enqueue_style($this->plugin_name, QUIZ_MAKER_FQ_ADMIN_URL . 'css/failed-questions-admin.css', array(), $this->version, 'all');
        }
    }

    /**
     * Register the JavaScript for the admin area.
     *
     * @since    1.0.0
     */
    public function enqueue_scripts() {
        $screen = get_current_screen();
        
        if (isset($screen->id) && 
            (strpos($screen->id, 'quiz-maker-failed-questions') !== false || 
             strpos($screen->id, 'quiz-maker') !== false)) {
            wp_enqueue_script($this->plugin_name, QUIZ_MAKER_FQ_ADMIN_URL . 'js/failed-questions-admin.js', array('jquery'), $this->version, false);
            wp_localize_script($this->plugin_name, 'quiz_maker_fq_ajax', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('quiz_maker_fq_save_settings')
            ));
        }
    }

    /**
     * Add menu item to Quiz Maker menu
     *
     * @since    1.0.0
     */
    public function add_plugin_admin_menu() {
        add_submenu_page(
            'quiz-maker',
            __('Failed Questions', 'quiz-maker-failed-questions'),
            __('Failed Questions', 'quiz-maker-failed-questions'),
            'manage_options',
            'quiz-maker-failed-questions',
            array($this, 'display_plugin_admin_page')
        );
    }

    /**
     * Render the admin page
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_page() {
        include_once QUIZ_MAKER_FQ_DIR . 'admin/partials/failed-questions-admin-display.php';
    }

    /**
     * Save settings via AJAX
     *
     * @since    1.0.0
     */
    public function save_settings() {
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        check_ajax_referer('quiz_maker_fq_save_settings', 'security');

        $settings = array();
        
        // Sanitize and save each setting
        if (isset($_POST['max_questions'])) {
            $settings['max_questions'] = intval($_POST['max_questions']);
        }
        
        if (isset($_POST['consecutive_correct_needed'])) {
            $settings['consecutive_correct_needed'] = intval($_POST['consecutive_correct_needed']);
        }
        
        if (isset($_POST['shortcode_text'])) {
            $settings['shortcode_text'] = sanitize_text_field($_POST['shortcode_text']);
        }
        
        update_option('quiz_maker_fq_settings', $settings);
        
        wp_send_json_success('Settings saved successfully');
    }
    
    /**
     * Add Failed Questions tab to quiz edit page
     *
     * @since    1.0.0
     */
    public function add_failed_questions_tab($args) {
        $quiz_id = isset($args['id']) ? intval($args['id']) : 0;
        
        if ($quiz_id > 0) {
            // Include the tab content
            include QUIZ_MAKER_FQ_DIR . 'admin/partials/failed-questions-quiz-tab.php';
        }
    }

    /**
     * AJAX handler for getting user's failed questions details
     */
    public function get_user_failed_questions_details() {
        check_ajax_referer('quiz_maker_fq_user_details', 'security');
        
        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }
        
        $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
        $quiz_id = isset($_POST['quiz_id']) ? intval($_POST['quiz_id']) : 0;
        
        if (!$user_id || !$quiz_id) {
            wp_send_json_error('Invalid parameters');
        }
        
        global $wpdb;
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $table_questions = $wpdb->prefix . 'aysquiz_questions';
        
        // Get user details
        $user = get_userdata($user_id);
        if (!$user) {
            wp_send_json_error('User not found');
        }
        
        // Get user's failed questions
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT fq.*, q.question as question_text
            FROM $table_failed_questions fq
            JOIN $table_questions q ON fq.question_id = q.id
            WHERE fq.user_id = %d AND fq.quiz_id = %d
            ORDER BY fq.last_attempt DESC",
            $user_id, $quiz_id
        ));
        
        // Get user statistics
        $stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_questions,
                SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_questions,
                SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as mastered_questions,
                MAX(last_attempt) as last_attempt,
                MIN(created_at) as first_attempt
            FROM $table_failed_questions
            WHERE user_id = %d AND quiz_id = %d",
            $user_id, $quiz_id
        ));
        
        // Calculate success rate
        $success_rate = 0;
        if ($stats->total_questions > 0) {
            $success_rate = round(($stats->mastered_questions / $stats->total_questions) * 100);
        }
        
        // Prepare HTML output
        $output = '<div class="ays-quiz-fq-user-detail-container">';
        
        // User info section
        $output .= '<div class="ays-quiz-fq-user-info">';
        $output .= '<h4>' . esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')</h4>';
        $output .= '<div class="ays-quiz-fq-user-stats">';
        $output .= '<span class="ays-quiz-fq-stat-badge blue">Total: ' . esc_html($stats->total_questions) . '</span>';
        $output .= '<span class="ays-quiz-fq-stat-badge red">Activas: ' . esc_html($stats->active_questions) . '</span>';
        $output .= '<span class="ays-quiz-fq-stat-badge green">Dominadas: ' . esc_html($stats->mastered_questions) . '</span>';
        $output .= '<span class="ays-quiz-fq-stat-badge blue">Tasa de éxito: ' . esc_html($success_rate) . '%</span>';
        $output .= '</div>';
        
        // Date info
        $output .= '<p>';
        $output .= '<strong>Primera vez: </strong>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($stats->first_attempt))) . '<br>';
        $output .= '<strong>Última vez: </strong>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($stats->last_attempt)));
        $output .= '</p>';
        $output .= '</div>';
        
        // Questions list
        $output .= '<div class="ays-quiz-fq-questions-list">';
        $output .= '<h4>Preguntas Falladas</h4>';
        
        if (count($questions) > 0) {
            $output .= '<table class="wp-list-table widefat fixed striped" style="width:100%;">';
            $output .= '<thead><tr>';
            $output .= '<th>Pregunta</th>';
            $output .= '<th>Estado</th>';
            $output .= '<th>Aciertos Consecutivos</th>';
            $output .= '<th>Último Intento</th>';
            $output .= '</tr></thead>';
            $output .= '<tbody>';
            
            foreach ($questions as $question) {
                $output .= '<tr>';
                
                // Question text
                $q_text = wp_strip_all_tags($question->question_text);
                $q_text = strlen($q_text) > 100 ? substr($q_text, 0, 100) . '...' : $q_text;
                $output .= '<td>' . esc_html($q_text) . '</td>';
                
                // Status
                $status_class = $question->is_active ? 'red' : 'green';
                $status_text = $question->is_active ? 'Activa' : 'Dominada';
                $output .= '<td><span class="ays-quiz-fq-stat-badge ' . $status_class . '">' . $status_text . '</span></td>';
                
                // Consecutive correct
                $output .= '<td>' . esc_html($question->consecutive_correct) . '</td>';
                
                // Last attempt
                $output .= '<td>' . esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($question->last_attempt))) . '</td>';
                
                $output .= '</tr>';
            }
            
            $output .= '</tbody></table>';
        } else {
            $output .= '<p>No se encontraron preguntas falladas para este usuario.</p>';
        }
        
        $output .= '</div>';
        
        $output .= '</div>';
        
        wp_send_json_success($output);
    }
}
