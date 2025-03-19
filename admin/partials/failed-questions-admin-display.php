<?php
/**
 * Provide a admin area view for the plugin
 *
 * @link       https://example.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/admin/partials
 */

// Get current settings
$settings = get_option('quiz_maker_fq_settings', array());
$max_questions = isset($settings['max_questions']) ? intval($settings['max_questions']) : 20;
$consecutive_correct_needed = isset($settings['consecutive_correct_needed']) ? intval($settings['consecutive_correct_needed']) : 3;
$shortcode_text = isset($settings['shortcode_text']) ? $settings['shortcode_text'] : 'Test de preguntas falladas';

?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <div class="ays-quiz-heading-box ays-quiz-unset-float">
        <div class="ays-quiz-wordpress-user-manual-box">
            <a href="javascript:void(0)" target="_blank"><?php echo __("View Documentation", "quiz-maker-failed-questions"); ?></a>
        </div>
    </div>
    
    <div class="nav-tab-wrapper">
        <a href="#tab1" data-tab="tab1" class="nav-tab nav-tab-active">
            <?php echo __("General Settings", "quiz-maker-failed-questions"); ?>
        </a>
        <a href="#tab2" data-tab="tab2" class="nav-tab">
            <?php echo __("Reports", "quiz-maker-failed-questions"); ?>
        </a>
    </div>
    
    <div id="tab1" class="ays-quiz-tab-content ays-quiz-tab-content-active">
        <form id="ays-failed-questions-settings-form">
            <div class="form-group row">
                <div class="col-sm-3">
                    <label for="max_questions">
                        <?php echo __('Maximum Number of Questions', 'quiz-maker-failed-questions'); ?>
                        <a class="ays_help" data-toggle="tooltip" 
                           title="<?php echo __('Maximum number of questions to include in each failed questions test. If user has fewer failed questions, all will be included.', 'quiz-maker-failed-questions'); ?>">
                            <i class="ays_fa ays_fa_info_circle"></i>
                        </a>
                    </label>
                </div>
                <div class="col-sm-9">
                    <input type="number" id="max_questions" name="max_questions" class="ays-text-input" value="<?php echo esc_attr($max_questions); ?>" min="1" max="100">
                </div>
            </div>
            
            <hr>
            
            <div class="form-group row">
                <div class="col-sm-3">
                    <label for="consecutive_correct_needed">
                        <?php echo __('Consecutive Correct Answers Needed', 'quiz-maker-failed-questions'); ?>
                        <a class="ays_help" data-toggle="tooltip" 
                           title="<?php echo __('How many times a user needs to answer a question correctly in a row before it will be removed from the failed questions list.', 'quiz-maker-failed-questions'); ?>">
                            <i class="ays_fa ays_fa_info_circle"></i>
                        </a>
                    </label>
                </div>
                <div class="col-sm-9">
                    <input type="number" id="consecutive_correct_needed" name="consecutive_correct_needed" class="ays-text-input" value="<?php echo esc_attr($consecutive_correct_needed); ?>" min="1" max="10">
                </div>
            </div>
            
            <hr>
            
            <div class="form-group row">
                <div class="col-sm-3">
                    <label for="shortcode_text">
                        <?php echo __('Test Title Text', 'quiz-maker-failed-questions'); ?>
                        <a class="ays_help" data-toggle="tooltip" 
                           title="<?php echo __('Text that will be used for failed questions tests. Default: "Test de preguntas falladas"', 'quiz-maker-failed-questions'); ?>">
                            <i class="ays_fa ays_fa_info_circle"></i>
                        </a>
                    </label>
                </div>
                <div class="col-sm-9">
                    <input type="text" id="shortcode_text" name="shortcode_text" class="ays-text-input" value="<?php echo esc_attr($shortcode_text); ?>">
                </div>
            </div>
            
            <hr>
            
            <div class="form-group row">
                <div class="col-sm-3">
                    <label>
                        <?php echo __('Shortcode', 'quiz-maker-failed-questions'); ?>
                        <a class="ays_help" data-toggle="tooltip" 
                           title="<?php echo __('Use this shortcode to display the failed questions menu in any post or page.', 'quiz-maker-failed-questions'); ?>">
                            <i class="ays_fa ays_fa_info_circle"></i>
                        </a>
                    </label>
                </div>
                <div class="col-sm-9">
                    <input type="text" readonly onClick="this.select();" class="ays-text-input" value="[quiz_maker_failed_questions]">
                </div>
            </div>
            
            <hr>
            
            <button type="submit" id="ays-failed-questions-save-settings" class="button button-primary">
                <?php echo __('Save Changes', 'quiz-maker-failed-questions'); ?>
            </button>
        </form>
    </div>
    
    <div id="tab2" class="ays-quiz-tab-content">
        <div class="wrap">
            <h2><?php echo __('Failed Questions Reports', 'quiz-maker-failed-questions'); ?></h2>
            
            <div class="form-group row">
                <div class="col-sm-12">
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th><?php echo __('User', 'quiz-maker-failed-questions'); ?></th>
                                <th><?php echo __('Category', 'quiz-maker-failed-questions'); ?></th>
                                <th><?php echo __('Active Failed Questions', 'quiz-maker-failed-questions'); ?></th>
                                <th><?php echo __('Mastered Questions', 'quiz-maker-failed-questions'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            global $wpdb;
                            $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
                            $table_question_categories = $wpdb->prefix . 'aysquiz_categories';
                            
                            // Get users with failed questions
                            $users_query = $wpdb->prepare(
                                "SELECT fq.user_id, 
                                        fq.category_id, 
                                        c.title as category_name,
                                        SUM(CASE WHEN fq.is_active = 1 THEN 1 ELSE 0 END) as active_count,
                                        SUM(CASE WHEN fq.is_active = 0 THEN 1 ELSE 0 END) as mastered_count
                                FROM $table_failed_questions fq
                                JOIN $table_question_categories c ON fq.category_id = c.id
                                GROUP BY fq.user_id, fq.category_id, c.title
                                ORDER BY fq.user_id ASC, c.title ASC"
                            );
                            
                            $results = $wpdb->get_results($users_query, ARRAY_A);
                            
                            if ($results) {
                                foreach ($results as $row) {
                                    $user = get_user_by('id', $row['user_id']);
                                    $username = $user ? $user->display_name : __('Unknown User', 'quiz-maker-failed-questions');
                                    ?>
                                    <tr>
                                        <td><?php echo esc_html($username); ?></td>
                                        <td><?php echo esc_html($row['category_name']); ?></td>
                                        <td><?php echo esc_html($row['active_count']); ?></td>
                                        <td><?php echo esc_html($row['mastered_count']); ?></td>
                                    </tr>
                                    <?php
                                }
                            } else {
                                ?>
                                <tr>
                                    <td colspan="4"><?php echo __('No failed questions data found.', 'quiz-maker-failed-questions'); ?></td>
                                </tr>
                                <?php
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
