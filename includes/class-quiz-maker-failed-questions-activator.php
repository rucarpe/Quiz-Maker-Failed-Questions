<?php
/**
 * Fired during plugin activation.
 *
 * @link       https://rucarpe.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/includes
 */

class Quiz_Maker_Failed_Questions_Activator {

    /**
     * Create database tables on plugin activation.
     *
     * @since    1.0.0
     */
    public static function activate() {
        global $wpdb;
        
        // SQL to create failed questions table
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $sql_failed_questions = "CREATE TABLE IF NOT EXISTS $table_failed_questions (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            quiz_id int(11) NOT NULL,
            question_id int(11) NOT NULL,
            category_id int(11) NOT NULL,
            consecutive_correct int(11) NOT NULL DEFAULT 0,
            last_attempt timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY quiz_id (quiz_id),
            KEY question_id (question_id),
            KEY category_id (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci";
        
        // Execute SQL statement
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_failed_questions);
        
        // Verificar que la tabla se ha creado
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_failed_questions'") == $table_failed_questions;
        if (!$table_exists) {
            // Forzar la creación directa
            $wpdb->query($sql_failed_questions);
            
            // Registrar un mensaje para depuración
            error_log("Quiz Maker Failed Questions: Tabla $table_failed_questions no creada correctamente");
        } else {
            error_log("Quiz Maker Failed Questions: Tabla $table_failed_questions creada exitosamente");
        }
        
        // Add column to quizzes table if it doesn't exist
        $table_quizzes = $wpdb->prefix . 'aysquiz_quizes';
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
            error_log("Quiz Maker Failed Questions: Columna is_failed_questions_quiz añadida a $table_quizzes");
        }
        
        // Set default settings
        $default_settings = array(
            'max_questions' => 20,
            'consecutive_correct_needed' => 3,
            'shortcode_text' => 'Test de preguntas falladas'
        );
        
        add_option('quiz_maker_fq_settings', $default_settings);
    }
}