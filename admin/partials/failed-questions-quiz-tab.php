<?php
/**
 * Provides a tab for quiz edit page with collapsible sections.
 *
 * @link       https://rucarpe.com
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
$table_questions = $wpdb->prefix . 'aysquiz_questions';
$table_users = $wpdb->prefix . 'users';

// Get summary statistics
$stats_query = $wpdb->prepare(
    "SELECT COUNT(DISTINCT fq.question_id) as total_failed_questions,
    COUNT(DISTINCT fq.user_id) as total_users,
    MAX(fq.last_attempt) as last_activity,
    SUM(CASE WHEN fq.is_active = 1 THEN 1 ELSE 0 END) as active_failed,
    SUM(CASE WHEN fq.is_active = 0 THEN 1 ELSE 0 END) as mastered
    FROM $table_failed_questions fq
    WHERE fq.quiz_id = %d",
    $quiz_id
);

$stats = $wpdb->get_row($stats_query);

// Get top failed questions for this quiz with actual question content
$top_questions_query = $wpdb->prepare(
    "SELECT fq.question_id, 
            q.question as question_text,
            COUNT(DISTINCT fq.user_id) as user_count,
            SUM(CASE WHEN fq.is_active = 1 THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN fq.is_active = 0 THEN 1 ELSE 0 END) as mastered_count,
            MAX(fq.created_at) as first_added,
            MAX(fq.last_attempt) as last_attempt
    FROM $table_failed_questions fq
    JOIN $table_questions q ON fq.question_id = q.id
    WHERE fq.quiz_id = %d
    GROUP BY fq.question_id, q.question
    ORDER BY user_count DESC, last_attempt DESC
    LIMIT 20",
    $quiz_id
);

$top_questions = $wpdb->get_results($top_questions_query);

// Get users with failed questions
$users_with_failed_questions = $wpdb->prepare(
    "SELECT u.ID, 
            u.display_name,
            COUNT(fq.id) as failed_count,
            SUM(CASE WHEN fq.is_active = 1 THEN 1 ELSE 0 END) as active_count,
            SUM(CASE WHEN fq.is_active = 0 THEN 1 ELSE 0 END) as mastered_count,
            MAX(fq.created_at) as first_added,
            MAX(fq.last_attempt) as last_attempt
    FROM $table_failed_questions fq
    JOIN $table_users u ON fq.user_id = u.ID
    WHERE fq.quiz_id = %d
    GROUP BY u.ID, u.display_name
    ORDER BY failed_count DESC",
    $quiz_id
);

$top_users = $wpdb->get_results($users_with_failed_questions);

// CSS for collapsible sections
?>
<style>
    .ays-quiz-fq-collapsible {
        cursor: pointer;
        background-color: #f8f8f8;
    }
    .ays-quiz-fq-collapsible:hover {
        background-color: #f0f0f0;
    }
    .ays-quiz-fq-collapsible:after {
        content: "\f140";
        font-family: dashicons;
        display: inline-block;
        margin-left: 5px;
        vertical-align: middle;
    }
    .ays-quiz-fq-collapsible.active:after {
        content: "\f142";
    }
    .ays-quiz-fq-content {
        display: none;
        padding: 0 18px;
        overflow: hidden;
        background-color: #fff;
    }
    .ays-quiz-fq-user-detail {
        margin-bottom: 15px;
        padding: 10px;
        border: 1px solid #eee;
    }
    .ays-quiz-fq-stat-badge {
        display: inline-block;
        padding: 3px 8px;
        border-radius: 3px;
        font-size: 12px;
        margin-right: 5px;
    }
    .ays-quiz-fq-stat-badge.green {
        background-color: #dff0d8;
        color: #3c763d;
    }
    .ays-quiz-fq-stat-badge.red {
        background-color: #f2dede;
        color: #a94442;
    }
    .ays-quiz-fq-stat-badge.blue {
        background-color: #d9edf7;
        color: #31708f;
    }
</style>

<div id="tab-failed-questions" class="ays-quiz-tab-content">
    <h2><?php echo __('Failed Questions Report', 'quiz-maker-failed-questions'); ?></h2>
    
    <div class="ays-quiz-fq-stats-container">
        <div class="ays-quiz-fq-stat-box">
            <div class="ays-quiz-fq-stat-box-title"><?php echo __('Total Failed Questions', 'quiz-maker-failed-questions'); ?></div>
            <div class="ays-quiz-fq-stat-box-value"><?php echo esc_html($stats->total_failed_questions); ?></div>
        </div>
        
        <div class="ays-quiz-fq-stat-box">
            <div class="ays-quiz-fq-stat-box-title"><?php echo __('Users with Failed Questions', 'quiz-maker-failed-questions'); ?></div>
            <div class="ays-quiz-fq-stat-box-value"><?php echo esc_html($stats->total_users); ?></div>
        </div>
        
        <div class="ays-quiz-fq-stat-box">
            <div class="ays-quiz-fq-stat-box-title"><?php echo __('Active Failed Questions', 'quiz-maker-failed-questions'); ?></div>
            <div class="ays-quiz-fq-stat-box-value"><?php echo esc_html($stats->active_failed); ?></div>
        </div>
        
        <div class="ays-quiz-fq-stat-box">
            <div class="ays-quiz-fq-stat-box-title"><?php echo __('Mastered Questions', 'quiz-maker-failed-questions'); ?></div>
            <div class="ays-quiz-fq-stat-box-value"><?php echo esc_html($stats->mastered); ?></div>
        </div>
        
        <div class="ays-quiz-fq-stat-box">
            <div class="ays-quiz-fq-stat-box-title"><?php echo __('Last Activity', 'quiz-maker-failed-questions'); ?></div>
            <div class="ays-quiz-fq-stat-box-value">
                <?php echo !empty($stats->last_activity) ? esc_html(date_i18n(get_option('date_format'), strtotime($stats->last_activity))) : 'N/A'; ?>
            </div>
        </div>
    </div>
    
    <!-- Most Failed Questions -->
    <div class="ays-quiz-fq-failed-questions-container">
        <h3 class="ays-quiz-fq-collapsible"><?php echo __('Most Failed Questions', 'quiz-maker-failed-questions'); ?></h3>
        
        <div class="ays-quiz-fq-content" style="display:block;">
            <?php if (!empty($top_questions)) : ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th width="35%"><?php echo __('Question', 'quiz-maker-failed-questions'); ?></th>
                            <th><?php echo __('Users', 'quiz-maker-failed-questions'); ?></th>
                            <th><?php echo __('Failed', 'quiz-maker-failed-questions'); ?></th>
                            <th><?php echo __('Mastered', 'quiz-maker-failed-questions'); ?></th>
                            <th><?php echo __('First Added', 'quiz-maker-failed-questions'); ?></th>
                            <th><?php echo __('Last Attempt', 'quiz-maker-failed-questions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_questions as $question) : ?>
                            <tr>
                                <td>
                                    <?php 
                                        // Strip tags and trim length for display
                                        $q_text = wp_strip_all_tags($question->question_text);
                                        echo esc_html(strlen($q_text) > 100 ? substr($q_text, 0, 100) . '...' : $q_text);
                                    ?>
                                </td>
                                <td><?php echo esc_html($question->user_count); ?></td>
                                <td><?php echo esc_html($question->active_count); ?></td>
                                <td><?php echo esc_html($question->mastered_count); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($question->first_added))); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($question->last_attempt))); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php echo __('No failed questions data available for this quiz.', 'quiz-maker-failed-questions'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Users with Failed Questions - Collapsible by default -->
    <div class="ays-quiz-fq-users-container">
        <h3 class="ays-quiz-fq-collapsible"><?php echo __('Users with Failed Questions', 'quiz-maker-failed-questions'); ?></h3>
        
        <div class="ays-quiz-fq-content">
            <?php if (!empty($top_users)) : ?>
                <table class="wp-list-table widefat fixed striped ays-quiz-fq-users-table">
                    <thead>
                        <tr>
                            <th><?php echo __('User', 'quiz-maker-failed-questions'); ?></th>
                            <th><?php echo __('Failed', 'quiz-maker-failed-questions'); ?></th>
                            <th><?php echo __('Active', 'quiz-maker-failed-questions'); ?></th>
                            <th><?php echo __('Mastered', 'quiz-maker-failed-questions'); ?></th>
                            <th><?php echo __('Progress', 'quiz-maker-failed-questions'); ?></th>
                            <th><?php echo __('First Added', 'quiz-maker-failed-questions'); ?></th>
                            <th><?php echo __('Last Attempt', 'quiz-maker-failed-questions'); ?></th>
                            <th><?php echo __('Actions', 'quiz-maker-failed-questions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_users as $user) : 
                            $total = $user->active_count + $user->mastered_count;
                            $progress = $total > 0 ? round(($user->mastered_count / $total) * 100) : 0;
                        ?>
                            <tr>
                                <td><?php echo esc_html($user->display_name); ?></td>
                                <td><?php echo esc_html($user->failed_count); ?></td>
                                <td><?php echo esc_html($user->active_count); ?></td>
                                <td><?php echo esc_html($user->mastered_count); ?></td>
                                <td>
                                    <div class="ays-quiz-fq-progress" style="width:100%; background:#eee; height:20px; position:relative;">
                                        <div style="width:<?php echo esc_attr($progress); ?>%; background:#4CAF50; height:100%;"></div>
                                        <span style="position:absolute; top:0; left:50%; transform:translateX(-50%); color:#000; font-size:12px; line-height:20px;"><?php echo esc_html($progress); ?>%</span>
                                    </div>
                                </td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($user->first_added))); ?></td>
                                <td><?php echo esc_html(date_i18n(get_option('date_format'), strtotime($user->last_attempt))); ?></td>
                                <td>
                                    <button class="button button-small ays-quiz-fq-user-details" data-user-id="<?php echo esc_attr($user->ID); ?>">
                                        <?php echo __('Details', 'quiz-maker-failed-questions'); ?>
                                    </button>
                                </td>
                            </tr>
                            <tr class="user-details-row user-<?php echo esc_attr($user->ID); ?>-details" style="display:none;">
                                <td colspan="8">
                                    <div class="ays-quiz-fq-user-details-container" id="user-<?php echo esc_attr($user->ID); ?>-details-content">
                                        <div class="ays-quiz-fq-user-detail">
                                            <p><strong><?php echo __('Loading user details...', 'quiz-maker-failed-questions'); ?></strong></p>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p><?php echo __('No users with failed questions for this quiz.', 'quiz-maker-failed-questions'); ?></p>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="ays-quiz-fq-settings-container">
        <h3 class="ays-quiz-fq-collapsible"><?php echo __('Failed Questions Settings', 'quiz-maker-failed-questions'); ?></h3>
        
        <div class="ays-quiz-fq-content">
            <p><?php echo __('These settings apply globally to all failed questions tests:', 'quiz-maker-failed-questions'); ?></p>
            
            <ul>
                <li><?php echo sprintf(__('Maximum questions per test: %d', 'quiz-maker-failed-questions'), $max_questions); ?></li>
                <li><?php echo sprintf(__('Consecutive correct answers needed: %d', 'quiz-maker-failed-questions'), $consecutive_needed); ?></li>
            </ul>
            
            <p>
                <?php echo __('You can change these settings from the Failed Questions settings page.', 'quiz-maker-failed-questions'); ?>
                <a href="<?php echo admin_url('admin.php?page=quiz-maker-failed-questions'); ?>" class="button button-secondary">
                    <?php echo __('Go to Settings', 'quiz-maker-failed-questions'); ?>
                </a>
            </p>
        </div>
    </div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    // Collapsible sections
    $(".ays-quiz-fq-collapsible").click(function() {
        $(this).toggleClass("active");
        var content = $(this).next(".ays-quiz-fq-content");
        if (content.is(":visible")) {
            content.slideUp();
        } else {
            content.slideDown();
        }
    });
    
    // Show details button functionality
    $(".ays-quiz-fq-user-details").click(function(e) {
        e.preventDefault();
        var userId = $(this).data("user-id");
        var detailsRow = $(".user-" + userId + "-details");
        
        if (detailsRow.is(":visible")) {
            detailsRow.hide();
            return;
        }
        
        // Hide all other details rows
        $(".user-details-row").hide();
        
        // Show this details row
        detailsRow.show();
        
        // Load user details if not already loaded
        var detailsContainer = $("#user-" + userId + "-details-content");
        if (detailsContainer.data("loaded") !== true) {
            detailsContainer.html('<p><strong><?php echo __("Loading user details...", "quiz-maker-failed-questions"); ?></strong></p>');
            
            // AJAX to get user's failed questions details
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'quiz_maker_fq_get_user_details',
                    user_id: userId,
                    quiz_id: <?php echo esc_js($quiz_id); ?>,
                    security: '<?php echo wp_create_nonce('quiz_maker_fq_user_details'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        detailsContainer.html(response.data);
                        detailsContainer.data("loaded", true);
                    } else {
                        detailsContainer.html('<p><strong><?php echo __("Error loading details", "quiz-maker-failed-questions"); ?></strong></p>');
                    }
                },
                error: function() {
                    detailsContainer.html('<p><strong><?php echo __("Error loading details", "quiz-maker-failed-questions"); ?></strong></p>');
                }
            });
        }
    });
});
</script>