<?php
/**
 * Plugin Name:       Quiz Maker - Failed Questions Addon
 * Plugin URI:        https://example.com/plugins/quiz-maker-failed-questions/
 * Description:       An addon for Quiz Maker that stores failed questions and allows users to take tests with only their failed questions.
 * Version:           1.0.0
 * Author:            Rucarpe
 * Author URI:        https://rucarpe.com/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       quiz-maker-failed-questions
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Define constants
define('QUIZ_MAKER_FQ_VERSION', '1.0.0');
define('QUIZ_MAKER_FQ_NAME', 'quiz-maker-failed-questions');
define('QUIZ_MAKER_FQ_DIR', plugin_dir_path(__FILE__));
define('QUIZ_MAKER_FQ_URL', plugin_dir_url(__FILE__));
define('QUIZ_MAKER_FQ_ADMIN_URL', plugin_dir_url(__FILE__) . 'admin/');
define('QUIZ_MAKER_FQ_PUBLIC_URL', plugin_dir_url(__FILE__) . 'public/');

// Check if Quiz Maker is active
function quiz_maker_fq_check_parent_plugin() {
    if (!function_exists('is_plugin_active')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    
    if (!is_plugin_active('quiz-maker/quiz-maker.php') && !is_plugin_active('quiz-maker-pro/quiz-maker-pro.php')) {
        add_action('admin_notices', 'quiz_maker_fq_admin_notice');
        deactivate_plugins(plugin_basename(__FILE__));
        if (isset($_GET['activate'])) {
            unset($_GET['activate']);
        }
    }
}
add_action('admin_init', 'quiz_maker_fq_check_parent_plugin');

// Admin notice if Quiz Maker is not active
function quiz_maker_fq_admin_notice() {
    ?>
    <div class="error">
        <p><?php esc_html_e('Quiz Maker - Failed Questions Addon requires Quiz Maker plugin to be installed and activated.', 'quiz-maker-failed-questions'); ?></p>
    </div>
    <?php
}

// Activation hook
function activate_quiz_maker_failed_questions() {
    require_once QUIZ_MAKER_FQ_DIR . 'includes/class-quiz-maker-failed-questions-activator.php';
    Quiz_Maker_Failed_Questions_Activator::activate();
}
register_activation_hook(__FILE__, 'activate_quiz_maker_failed_questions');

// Deactivation hook
function deactivate_quiz_maker_failed_questions() {
    require_once QUIZ_MAKER_FQ_DIR . 'includes/class-quiz-maker-failed-questions-deactivator.php';
    Quiz_Maker_Failed_Questions_Deactivator::deactivate();
}
register_deactivation_hook(__FILE__, 'deactivate_quiz_maker_failed_questions');

// Main plugin class
require_once QUIZ_MAKER_FQ_DIR . 'includes/class-quiz-maker-failed-questions.php';

// Run the plugin
function run_quiz_maker_failed_questions() {
    $plugin = new Quiz_Maker_Failed_Questions();
    $plugin->run();
}
run_quiz_maker_failed_questions();
