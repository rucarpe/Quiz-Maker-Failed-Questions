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

class Quiz_Maker_Failed_Questions_Activator
{

    /**
     * Create database tables on plugin activation.
     *
     * @since    1.0.0
     */
    public static function activate()
    {
        global $wpdb;

        // SQL to create failed questions table
        $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
        $charset_collate = $wpdb->get_charset_collate();

        $sql_failed_questions = "CREATE TABLE IF NOT EXISTS $table_failed_questions (
            id int(11) NOT NULL AUTO_INCREMENT,
            user_id int(11) NOT NULL,
            quiz_id int(11) NOT NULL,
            question_id int(11) NOT NULL,
            category_id int(11) NOT NULL,
            consecutive_correct int(11) NOT NULL DEFAULT 0,
            last_attempt datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY quiz_id (quiz_id),
            KEY question_id (question_id),
            KEY category_id (category_id)
        ) $charset_collate;";

        // Execute SQL statement
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_failed_questions);

        // Log table creation status
        self::log_table_creation($table_failed_questions);

        // Set default settings if they don't exist
        if (!get_option('quiz_maker_fq_settings')) {
            $default_settings = array(
                'max_questions' => 20,
                'consecutive_correct_needed' => 3,
                'shortcode_text' => 'Test de preguntas falladas'
            );

            add_option('quiz_maker_fq_settings', $default_settings);
        }

        // Check and add column to Quiz Maker's quizzes table if needed
        self::add_column_to_quizzes_table();

        // Create temp quizzes tracking table (also created in Updater for upgrades)
        $table_temp_quizzes = $wpdb->prefix . 'aysquiz_failed_questions_quizzes';
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

        dbDelta($sql_temp_quizzes);
        self::log_table_creation($table_temp_quizzes);
    }

    /**
     * Log table creation status
     *
     * @since    1.0.0
     * @param    string    $table_name    Table name.
     */
    private static function log_table_creation($table_name)
    {
        global $wpdb;

        // Check if table exists
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;

        if (!$table_exists) {
            // Direct creation if dbDelta failed
            $wpdb->query("
                CREATE TABLE IF NOT EXISTS $table_name (
                    id int(11) NOT NULL AUTO_INCREMENT,
                    user_id int(11) NOT NULL,
                    quiz_id int(11) NOT NULL,
                    question_id int(11) NOT NULL,
                    category_id int(11) NOT NULL,
                    consecutive_correct int(11) NOT NULL DEFAULT 0,
                    last_attempt datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    is_active tinyint(1) NOT NULL DEFAULT 1,
                    created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id)
                ) {$wpdb->get_charset_collate()};
            ");

            // Check again after direct creation
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name;
            error_log("Quiz Maker Failed Questions: Table $table_name creation status: " . ($table_exists ? 'Success' : 'Failed'));
        }
        else {
            error_log("Quiz Maker Failed Questions: Table $table_name exists");
        }
    }

    /**
     * Add column to Quiz Maker's quizzes table
     *
     * @since    1.0.0
     */
    private static function add_column_to_quizzes_table()
    {
        global $wpdb;
        $table_quizzes = $wpdb->prefix . 'aysquiz_quizes';

        // Check if Quiz Maker table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_quizzes'") != $table_quizzes) {
            error_log("Quiz Maker Failed Questions: Quiz Maker table ($table_quizzes) does not exist");
            return;
        }

        // Check if column exists
        $column_exists = $wpdb->get_results($wpdb->prepare(
            "SELECT COLUMN_NAME 
            FROM INFORMATION_SCHEMA.COLUMNS 
            WHERE TABLE_SCHEMA = %s 
            AND TABLE_NAME = %s 
            AND COLUMN_NAME = 'is_failed_questions_quiz'",
            $wpdb->dbname,
            $table_quizzes
        ));

        if (empty($column_exists)) {
            // Add column
            $result = $wpdb->query("ALTER TABLE $table_quizzes ADD COLUMN is_failed_questions_quiz tinyint(1) NOT NULL DEFAULT 0");
            error_log("Quiz Maker Failed Questions: Column 'is_failed_questions_quiz' added to $table_quizzes: " . ($result !== false ? 'Success' : 'Failed'));
        }
        else {
            error_log("Quiz Maker Failed Questions: Column 'is_failed_questions_quiz' already exists in $table_quizzes");
        }
    }
}