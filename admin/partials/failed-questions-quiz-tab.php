<?php
/**
 * Provides a tab for quiz edit page.
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/admin/partials
 */

// Get quiz ID
$quiz_id = isset($args['id']) ? intval($args['id']) : 0;

// Get settings
$settings = get_option('quiz_maker_fq_settings', array());
$max_questions = isset($settings['max_questions']) ? intval($settings['max_questions']) : 20;
$consecutive_correct_needed = isset($settings['consecutive_correct_needed']) ? intval($settings['consecutive_correct_needed']) : 3;

// Get failed questions statistics for this quiz
global $wpdb;
$table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';

$stats_query = $wpdb->prepare(
    "SELECT COUNT(DISTINCT question_id) as total_failed_questions,
    COUNT(DISTINCT user_id) as total_users
    FROM $table_failed_questions
    WHERE quiz_id = %d",
    $quiz_id
);

$stats = $wpdb->get_row($stats_query);

// Get top failed questions for this quiz
$top_questions_query = $wpdb->prepare(
    "SELECT fq.question_id, 
            q.question,
            COUNT(DISTINCT fq.user_id) as user_count
    FROM $table_failed_questions fq
    JOIN {$wpdb->prefix}aysquiz_questions q ON fq.question_id = q.id
    WHERE fq.quiz_id = %d
    GROUP BY fq.question_id, q.question
    ORDER BY user_count DESC
    LIMIT 10",
    $quiz_id
);

$top_questions = $wpdb->get_results($top_questions_query);
?>

<div id="tab-failed-questions" class="ays-quiz-tab-content">
    <h2><?php echo __('Failed Questions Statistics', 'quiz-maker-failed-questions'); ?></h2>
    
    <div class="ays-quiz-fq-stats-container">
        <div class="ays-quiz-fq-stat-box">
            <div class="ays-quiz-fq-stat-box-title"><?php echo __('Total Failed Questions', 'quiz-maker-failed-questions'); ?></div>
            <div class="ays-quiz-fq-stat-box-value"><?php echo esc_html($stats->total_failed_questions); ?></div>
        </div>
        
        <div class="ays-quiz-fq-stat-box">
            <div class="ays-quiz-fq-stat-box-title"><?php echo __('Users with Failed Questions', 'quiz-maker-failed-questions'); ?></div>
            <div class="ays-quiz-fq-stat-box-value"><?php echo esc_html($stats->total_users); ?></div>
        </div>
    </div>
    
    <div class="ays-quiz-fq-failed-questions-container">
        <h3><?php echo __('Most Failed Questions', 'quiz-maker-failed-questions'); ?></h3>
        
        <?php if (!empty($top_questions)) : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php echo __('Question', 'quiz-maker-failed-questions'); ?></th>
                        <th width="100"><?php echo __('User Count', 'quiz-maker-failed-questions'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($top_questions as $question) : ?>
                        <tr>
                            <td><?php echo esc_html(wp_trim_words($question->question, 20)); ?></td>
                            <td><?php echo esc_html($question->user_count); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else : ?>
            <p><?php echo __('No failed questions data available for this quiz.', 'quiz-maker-failed-questions'); ?></p>
        <?php endif; ?>
    </div>
    
    <div class="ays-quiz-fq-settings-container">
        <h3><?php echo __('Failed Questions Settings', 'quiz-maker-failed-questions'); ?></h3>
        
        <p><?php echo __('These settings apply globally to all failed questions tests:', 'quiz-maker-failed-questions'); ?></p>
        
        <ul>
            <li><?php echo sprintf(__('Maximum questions per test: %d', 'quiz-maker-failed-questions'), $max_questions); ?></li>
            <li><?php echo sprintf(__('Consecutive correct answers needed: %d', 'quiz-maker-failed-questions'), $consecutive_correct_needed); ?></li>
        </ul>
        
        <p>
            <?php echo __('You can change these settings from the Failed Questions settings page.', 'quiz-maker-failed-questions'); ?>
            <a href="<?php echo admin_url('admin.php?page=quiz-maker-failed-questions'); ?>" class="button button-secondary">
                <?php echo __('Go to Settings', 'quiz-maker-failed-questions'); ?>
            </a>
        </p>
    </div>
</div>
