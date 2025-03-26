<?php
/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://rucarpe.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/includes
 * @author     Rucarpe <info@rucarpe.com>
 */
class Quiz_Maker_Failed_Questions {

    /**
     * The loader that's responsible for maintaining and registering all hooks that power
     * the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      Quiz_Maker_Failed_Questions_Loader    $loader    Maintains and registers all hooks for the plugin.
     */
    protected $loader;

    /**
     * The unique identifier of this plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $plugin_name    The string used to uniquely identify this plugin.
     */
    protected $plugin_name;

    /**
     * The current version of the plugin.
     *
     * @since    1.0.0
     * @access   protected
     * @var      string    $version    The current version of the plugin.
     */
    protected $version;

    /**
     * Define the core functionality of the plugin.
     *
     * Set the plugin name and the plugin version that can be used throughout the plugin.
     * Load the dependencies, define the locale, and set the hooks for the admin area and
     * the public-facing side of the site.
     *
     * @since    1.0.0
     */
    public function __construct() {
        if (defined('QUIZ_MAKER_FQ_VERSION')) {
            $this->version = QUIZ_MAKER_FQ_VERSION;
        } else {
            $this->version = '1.0.0';
        }
        $this->plugin_name = 'quiz-maker-failed-questions';
        $this->version = QUIZ_MAKER_FQ_VERSION;

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
    }

    /**
     * Load the required dependencies for this plugin.
     *
     * Include the following files that make up the plugin:
     *
     * - Quiz_Maker_Failed_Questions_Loader. Orchestrates the hooks of the plugin.
     * - Quiz_Maker_Failed_Questions_i18n. Defines internationalization functionality.
     * - Quiz_Maker_Failed_Questions_Admin. Defines all hooks for the admin area.
     * - Quiz_Maker_Failed_Questions_Public. Defines all hooks for the public side of the site.
     *
     * Create an instance of the loader which will be used to register the hooks
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function load_dependencies() {

        /**
         * The class responsible for orchestrating the actions and filters of the
         * core plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-quiz-maker-failed-questions-loader.php';

        /**
         * The class responsible for defining internationalization functionality
         * of the plugin.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-quiz-maker-failed-questions-i18n.php';

        /**
         * The class responsible for defining all actions that occur in the admin area.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'admin/class-quiz-maker-failed-questions-admin.php';

        /**
         * The class responsible for defining all actions that occur in the public-facing
         * side of the site.
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'public/class-quiz-maker-failed-questions-public.php';

        $this->loader = new Quiz_Maker_Failed_Questions_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
     *
     * Uses the Quiz_Maker_Failed_Questions_i18n class in order to set the domain and to register the hook
     * with WordPress.
     *
     * @since    1.0.0
     * @access   private
     */
    private function set_locale() {
        $plugin_i18n = new Quiz_Maker_Failed_Questions_i18n();

        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_admin_hooks() {
        $plugin_admin = new Quiz_Maker_Failed_Questions_Admin($this->get_plugin_name(), $this->get_version());

        // Add admin menu item
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu', 150);
        
        // Enqueue admin styles and scripts
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        // Add menu item
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu');
        
        // AJAX handler for settings
        $this->loader->add_action('wp_ajax_quiz_maker_fq_save_settings', $plugin_admin, 'save_settings');
        
        // Add Failed Questions tab in Quiz edit page
        $this->loader->add_action('ays_qm_quiz_page_tab', $plugin_admin, 'add_failed_questions_tab', 10, 1);
    }

    /**
     * Register all of the hooks related to the public-facing functionality
     * of the plugin.
     *
     * @since    1.0.0
     * @access   private
     */
    private function define_public_hooks() {
        $plugin_public = new Quiz_Maker_Failed_Questions_Public($this->get_plugin_name(), $this->get_version());

        // Enqueue public styles and scripts
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        
        // Register shortcodes
        $this->loader->add_action('init', $plugin_public, 'register_shortcodes');
        
        // Capture failed questions when quiz is finished - key hooks
        $this->loader->add_action('wp_ajax_ays_finish_quiz', $plugin_public, 'quiz_maker_fq_save_failed_questions', 10);
        $this->loader->add_action('wp_ajax_nopriv_ays_finish_quiz', $plugin_public, 'quiz_maker_fq_save_failed_questions', 10);

        // Añadir más hooks para capturar en diferentes contextos
        $this->loader->add_action('ays_finish_quiz', $plugin_public, 'quiz_maker_fq_save_failed_questions', 10);
        $this->loader->add_action('ays_quiz_completed', $plugin_public, 'quiz_maker_fq_save_failed_questions', 10);
        $this->loader->add_action('ays_submit_quiz', $plugin_public, 'quiz_maker_fq_save_failed_questions', 10);
        
        // Generate failed questions quiz
        $this->loader->add_action('wp_ajax_quiz_maker_fq_generate_quiz', $plugin_public, 'generate_failed_questions_quiz');
        $this->loader->add_action('wp_ajax_nopriv_quiz_maker_fq_generate_quiz', $plugin_public, 'generate_failed_questions_quiz');
        
        // Update consecutive correct answers
        $this->loader->add_action('wp_ajax_quiz_maker_fq_update_consecutive_correct', $plugin_public, 'update_consecutive_correct');
        $this->loader->add_action('wp_ajax_nopriv_quiz_maker_fq_update_consecutive_correct', $plugin_public, 'update_consecutive_correct');
        
        // Hook into Quiz Maker to display our temporary quiz
        $this->loader->add_action('template_redirect', $plugin_public, 'maybe_load_failed_questions_quiz', 5);

        // Test database operations
        $this->loader->add_action('wp_ajax_quiz_maker_fq_test_db', $plugin_public, 'test_database_operations');

        // Hook para depuración directa durante la resolución de problemas
        $this->loader->add_action('wp_footer', $plugin_public, 'add_quiz_listener_script', 100);

        $this->loader->add_action('wp_ajax_quiz_maker_fq_save_quiz_data', $plugin_public, 'save_quiz_data');
        
        $this->loader->add_action('wp_ajax_quiz_maker_fq_verify_system', $plugin_public, 'ajax_verify_system');
        // Capturar failed questions cuando quiz es finished - key hooks
        $this->loader->add_action('wp_ajax_ays_finish_quiz', $plugin_public, 'quiz_maker_fq_save_failed_questions', 9); // Prioridad 9 para que se ejecute antes
        $this->loader->add_action('wp_ajax_nopriv_ays_finish_quiz', $plugin_public, 'quiz_maker_fq_save_failed_questions', 9);

        // Hook para capturar después de que el quiz ha sido procesado
        $this->loader->add_filter('ays_finish_quiz', $plugin_public, 'hook_quiz_finished', 10, 1);
        $this->loader->add_filter('ays_after_finish_quiz', $plugin_public, 'hook_quiz_finished', 10, 1);

        // Scripts para capturar datos AJAX
        $this->loader->add_action('wp_footer', $plugin_public, 'add_ajax_capture_script', 999);
        $this->loader->add_action('wp_ajax_quiz_maker_fq_capture_ajax', $plugin_public, 'process_captured_ajax');
        $this->loader->add_action('wp_ajax_nopriv_quiz_maker_fq_capture_ajax', $plugin_public, 'process_captured_ajax');

        // Monitor de Quiz Maker (solo para administradores)
        $this->loader->add_action('wp_footer', $plugin_public, 'add_quiz_maker_monitor', 999);

        $this->loader->add_action('wp_ajax_quiz_maker_fq_process_results', $plugin_public, 'process_results');
        $this->loader->add_action('wp_ajax_nopriv_quiz_maker_fq_process_results', $plugin_public, 'process_results');
    }

    /**
     * Run the loader to execute all of the hooks with WordPress.
     *
     * @since    1.0.0
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * The name of the plugin used to uniquely identify it within the context of
     * WordPress and to define internationalization functionality.
     *
     * @since     1.0.0
     * @return    string    The name of the plugin.
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * The reference to the class that orchestrates the hooks with the plugin.
     *
     * @since     1.0.0
     * @return    Quiz_Maker_Failed_Questions_Loader    Orchestrates the hooks of the plugin.
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Retrieve the version number of the plugin.
     *
     * @since     1.0.0
     * @return    string    The version number of the plugin.
     */
    public function get_version() {
        return $this->version;
    }
}