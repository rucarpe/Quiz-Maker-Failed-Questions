<?php
/**
 * The file that defines the core plugin class
 *
 * @link       https://rucarpe.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/includes
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
     * @since    1.0.0
     */
    public function __construct() {
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
        
        /**
         * The class for generating quizzes with failed questions
         */
        require_once plugin_dir_path(dirname(__FILE__)) . 'includes/class-quiz-maker-failed-questions-generator.php';
        
        $this->loader = new Quiz_Maker_Failed_Questions_Loader();
    }

    /**
     * Define the locale for this plugin for internationalization.
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

        // Enqueue admin styles and scripts
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        // Add menu item
        $this->loader->add_action('admin_menu', $plugin_admin, 'add_plugin_admin_menu', 150);
        
        // AJAX handler for settings
        $this->loader->add_action('wp_ajax_quiz_maker_fq_save_settings', $plugin_admin, 'save_settings');
        
        // AJAX handler for user details
        $this->loader->add_action('wp_ajax_quiz_maker_fq_get_user_details', $plugin_admin, 'get_user_failed_questions_details');
        
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
        
        // ===== DIFERENTES ENFOQUES PARA CAPTURAR PREGUNTAS FALLADAS =====
        
        // 1. Método original - procesamiento post-quiz
        $this->loader->add_filter('ays_finish_quiz', $plugin_public, 'rucarpe_process_quiz_results', 999, 2);
        
        // 2. Método de intercepción AJAX - ejecución temprana
        $this->loader->add_action('init', $plugin_public, 'rucarpe_capture_quiz_ajax');
        
        // 3. Procesamiento diferido por cron
        $this->loader->add_action('rucarpe_process_delayed_quiz_results', $plugin_public, 'rucarpe_process_delayed_quiz_results', 10, 2);
        
        // ===== HOOKS PARA GESTIONAR LOS TESTS DE PREGUNTAS FALLADAS =====
        // Generate failed questions quiz
        $this->loader->add_action('wp_ajax_quiz_maker_fq_generate_quiz', $plugin_public, 'rucarpe_generate_failed_questions_quiz');
        $this->loader->add_action('wp_ajax_nopriv_quiz_maker_fq_generate_quiz', $plugin_public, 'rucarpe_generate_failed_questions_quiz');
        
        // Update consecutive correct answers
        $this->loader->add_action('wp_ajax_quiz_maker_fq_update_consecutive_correct', $plugin_public, 'rucarpe_update_consecutive_correct');
        $this->loader->add_action('wp_ajax_nopriv_quiz_maker_fq_update_consecutive_correct', $plugin_public, 'rucarpe_update_consecutive_correct');
        
        // Hook into Quiz Maker to display our temporary quiz
        $this->loader->add_action('template_redirect', $plugin_public, 'rucarpe_load_failed_questions_quiz', 5);
        
        // ===== HOOKS PARA DEPURACIÓN =====
        $this->loader->add_action('wp_loaded', $plugin_public, 'rucarpe_setup_debug_tools');
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