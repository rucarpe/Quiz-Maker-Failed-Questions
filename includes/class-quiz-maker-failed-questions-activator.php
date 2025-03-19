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
        
        // Set default settings
        $default_settings = array(
            'max_questions' => 20,
            'consecutive_correct_needed' => 3,
            'shortcode_text' => 'Test de preguntas falladas'
        );
        
        add_option('quiz_maker_fq_settings', $default_settings);
    }
}