<?php
/**
 * Plugin Name: Quiz Maker Pro - Settings Addon
 * Plugin URI: https://rucarpe.com
 * Description: Addon para Quiz Maker Pro que añade opciones adicionales de configuración.
 * Version: 1.0.0
 * Author: Rucarpe
 * Author URI: https://rucarpe.com
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: quiz-maker-pro-settings
 * Domain Path: /languages
 */

// Si este archivo es llamado directamente, abortar.
if (!defined('WPINC')) {
    die;
}

// Definir constantes para el plugin
define('QUIZ_MAKER_PRO_SETTINGS_VERSION', '1.0.0');
define('QUIZ_MAKER_PRO_SETTINGS_PATH', plugin_dir_path(__FILE__));
define('QUIZ_MAKER_PRO_SETTINGS_URL', plugin_dir_url(__FILE__));
define('QUIZ_MAKER_PRO_SETTINGS_BASENAME', plugin_basename(__FILE__));

/**
 * Código que se ejecuta durante la activación del plugin.
 */
function activate_quiz_maker_pro_settings() {
    require_once QUIZ_MAKER_PRO_SETTINGS_PATH . 'includes/class-quiz-maker-pro-settings-activator.php';
    Quiz_Maker_Pro_Settings_Activator::activate();
}

/**
 * Código que se ejecuta durante la desactivación del plugin.
 */
function deactivate_quiz_maker_pro_settings() {
    require_once QUIZ_MAKER_PRO_SETTINGS_PATH . 'includes/class-quiz-maker-pro-settings-deactivator.php';
    Quiz_Maker_Pro_Settings_Deactivator::deactivate();
}

register_activation_hook(__FILE__, 'activate_quiz_maker_pro_settings');
register_deactivation_hook(__FILE__, 'deactivate_quiz_maker_pro_settings');

/**
 * El núcleo de la clase del plugin.
 */
require_once QUIZ_MAKER_PRO_SETTINGS_PATH . 'includes/class-quiz-maker-pro-settings.php';

/**
 * Comienza la ejecución del plugin.
 */
function run_quiz_maker_pro_settings() {
    // Verificar si Quiz Maker Pro está instalado y activado
    if (!function_exists('is_plugin_active')) {
        include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    }
    
    if (!is_plugin_active('quiz-maker/quiz-maker.php')) {
        add_action('admin_notices', 'quiz_maker_pro_settings_dependency_notice');
        return;
    }

    $plugin = new Quiz_Maker_Pro_Settings();
    $plugin->run();
}

/**
 * Muestra un aviso si Quiz Maker Pro no está activado.
 */
function quiz_maker_pro_settings_dependency_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php _e('Quiz Maker Pro - Settings Addon requiere que Quiz Maker Pro esté instalado y activado.', 'quiz-maker-pro-settings'); ?></p>
    </div>
    <?php
}

// Iniciar el plugin
run_quiz_maker_pro_settings();