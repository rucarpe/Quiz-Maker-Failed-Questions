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

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/admin
 * @author     Rucarpe <info@rucarpe.com>
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
     * @param      string    $plugin_name       The name of this plugin.
     * @param      string    $version    The version of this plugin.
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
}