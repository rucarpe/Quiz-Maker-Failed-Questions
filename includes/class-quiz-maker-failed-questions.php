<?php
/**
 * The core plugin class.
 *
 * @link       https://rucarpe.com
 * @since      1.0.0
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/includes
 */

class Quiz_Maker_Failed_Questions {

    /**
     * The loader that's responsible for maintaining and registering all hooks.
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
        
        // The class responsible for orchestrating the actions and filters of the core plugin.
        require_once QUIZ_MAKER_FQ_DIR . 'includes/class-quiz-maker-failed-questions-loader.php';
        
        // The class responsible for defining all actions that occur in the admin area.
        require_once QUIZ_MAKER_FQ_DIR . 'admin/class-quiz-maker-failed-questions-admin.php';
        
        // The class responsible for defining all actions that occur in the public-facing side of the site.
        require_once QUIZ_MAKER_FQ_DIR . 'public/class-quiz-maker-failed-questions-public.php';
        
        $this->loader = new Quiz_Maker_Failed_Questions_Loader();
    }

    /**
     * Register all of the hooks related to the admin area functionality.
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
        
        // Save settings
        $this->loader->add_action('wp_ajax_quiz_maker_fq_save_settings', $plugin_admin, 'save_settings');
        
        // Add Failed Questions tab in Quiz edit page
        $this->loader->add_action('ays_qm_quiz_page_tab', $plugin_admin, 'add_failed_questions_tab', 10, 1);
    }

    /**
     * Register all of the hooks related to the public-facing functionality.
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
        
        // Capture failed questions when quiz is finished
        $this->loader->add_action('wp_ajax_ays_finish_quiz', $plugin_public, 'save_failed_questions', 99);
        $this->loader->add_action('wp_ajax_nopriv_ays_finish_quiz', $plugin_public, 'save_failed_questions', 99);

        // Additional hooks for quiz completion
        $this->loader->add_action('ays_after_finish_quiz', $plugin_public, 'save_failed_questions');
        $this->loader->add_action('ays_finish_quiz', $plugin_public, 'save_failed_questions');
        
        // Generate failed questions quiz
        $this->loader->add_action('wp_ajax_quiz_maker_fq_generate_quiz', $plugin_public, 'generate_failed_questions_quiz');
        $this->loader->add_action('wp_ajax_nopriv_quiz_maker_fq_generate_quiz', $plugin_public, 'generate_failed_questions_quiz');
        
        // Update consecutive correct answers
        $this->loader->add_action('wp_ajax_quiz_maker_fq_update_consecutive_correct', $plugin_public, 'update_consecutive_correct');
        $this->loader->add_action('wp_ajax_nopriv_quiz_maker_fq_update_consecutive_correct', $plugin_public, 'update_consecutive_correct');
        
        // Hook into Quiz Maker to display our temporary quiz
        $this->loader->add_action('template_redirect', $plugin_public, 'maybe_load_failed_questions_quiz', 5);
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