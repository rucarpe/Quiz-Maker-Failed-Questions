<?php
/**
 * Actualización a versión con tablas temporales
 *
 * @link       https://rucarpe.com
 * @since      1.1.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/includes
 */

/**
 * Ejecuta los procesos de actualización necesarios al actualizar el plugin
 */
class Quiz_Maker_Failed_Questions_Updater {

    /**
     * Ejecuta la actualización
     *
     * @return void
     */
    public static function update() {
        // Obtener versión anterior
        $previous_version = get_option('quiz_maker_fq_version', '0.0.0');
        
        // Si ya estamos actualizados, no hacer nada
        if (version_compare($previous_version, QUIZ_MAKER_FQ_VERSION, '>=')) {
            return;
        }
        
        // Log para depuración
        self::log_debug("Actualizando de versión $previous_version a " . QUIZ_MAKER_FQ_VERSION);
        
        // Crear tabla temporal si no existe
        self::create_temp_table();
        
        // Actualizar opciones
        self::update_options();
        
        // Limpiar tests antiguos
        self::cleanup_old_tests();
        
        // Actualizar versión
        update_option('quiz_maker_fq_version', QUIZ_MAKER_FQ_VERSION);
        
        self::log_debug("Actualización completada a versión " . QUIZ_MAKER_FQ_VERSION);
    }
    
    /**
     * Crea la tabla temporal si no existe
     */
    private static function create_temp_table() {
        global $wpdb;
        
        $table_temp_quizzes = $wpdb->prefix . 'aysquiz_failed_questions_quizzes';
        $charset_collate = $wpdb->get_charset_collate();
        
        // Verificar si la tabla existe
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_temp_quizzes'") == $table_temp_quizzes) {
            self::log_debug("La tabla $table_temp_quizzes ya existe");
            return;
        }
        
        // Crear tabla de quizzes temporales
        $sql_temp_quizzes = "CREATE TABLE IF NOT EXISTS $table_temp_quizzes (
            id int(11) NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL,
            description text,
            quiz_image text,
            quiz_category_id int(11) NOT NULL DEFAULT 1,
            question_ids text,
            published tinyint(1) NOT NULL DEFAULT 1,
            options longtext,
            author_id int(11) NOT NULL DEFAULT 0,
            create_date datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            completed tinyint(1) NOT NULL DEFAULT 0,
            completed_date datetime DEFAULT NULL,
            ref_id int(11) DEFAULT NULL,
            PRIMARY KEY (id),
            KEY quiz_category_id (quiz_category_id),
            KEY author_id (author_id)
        ) $charset_collate;";
        
        // Ejecutar SQL
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_temp_quizzes);
        
        // Verificar si se creó correctamente
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_temp_quizzes'") == $table_temp_quizzes) {
            self::log_debug("Tabla $table_temp_quizzes creada correctamente");
        } else {
            self::log_debug("Error al crear tabla $table_temp_quizzes");
        }
    }
    
    /**
     * Actualiza las opciones del plugin para incluir las nuevas opciones
     */
    private static function update_options() {
        $settings = get_option('quiz_maker_fq_settings', array());
        
        $default_settings = array(
            'max_questions' => 20,
            'consecutive_correct_needed' => 3,
            'shortcode_text' => 'Test de preguntas falladas',
            'delete_tests_after_completion' => 1, // Nueva opción para borrar tests después de completarlos
            'temp_tests_enabled' => 1 // Nueva opción para activar/desactivar el uso de tests temporales
        );
        
        // Añadir nuevas opciones si no existen
        $updated = false;
        foreach ($default_settings as $key => $value) {
            if (!isset($settings[$key])) {
                $settings[$key] = $value;
                $updated = true;
            }
        }
        
        if ($updated) {
            update_option('quiz_maker_fq_settings', $settings);
            self::log_debug("Opciones actualizadas");
        }
    }
    
    /**
     * Limpia los tests antiguos generados antes de la actualización
     */
    private static function cleanup_old_tests() {
        global $wpdb;
        
        $table_quizzes = $wpdb->prefix . 'aysquiz_quizes';
        
        // Eliminar tests marcados como de preguntas falladas
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_quizzes WHERE is_failed_questions_quiz = 1 OR title LIKE %s",
            '%Test de preguntas falladas%'
        ));
        
        self::log_debug("Se eliminaron $deleted tests antiguos");
    }
    
    /**
     * Log para depuración
     */
    private static function log_debug($message) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[Quiz Maker FQ Updater] ' . $message);
        }
    }
}