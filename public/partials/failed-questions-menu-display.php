<?php
/**
 * Displays the failed questions menu for users.
 *
 * @link       https://rucarpe.com
 * @since      1.0.0
 *
 * @package    Quiz_Maker_Failed_Questions
 * @subpackage Quiz_Maker_Failed_Questions/public/partials
 */

// Get settings
$settings = get_option('quiz_maker_fq_settings', array());
$shortcode_text = isset($settings['shortcode_text']) ? $settings['shortcode_text'] : 'Test de preguntas falladas';

?>
<div class="ays-quiz-failed-questions-container">
    <h2><?php echo esc_html($shortcode_text); ?></h2>
    
    <?php if (empty($categories)) : ?>
        <div class="ays-quiz-fq-no-questions-message">
            <p><?php echo __('No tienes preguntas falladas disponibles. Realiza algunos cuestionarios para registrar tus preguntas falladas.', 'quiz-maker-failed-questions'); ?></p>
        </div>
    <?php else : ?>
        <div class="ays-quiz-fq-description">
            <p><?php echo __('Selecciona una categoría para practicar las preguntas que has fallado anteriormente, o elige la opción "Mixto" para practicar preguntas de todas las categorías.', 'quiz-maker-failed-questions'); ?></p>
        </div>
        
        <div class="ays-quiz-fq-categories-list">
            <div class="ays-quiz-fq-category-item ays-quiz-fq-category-mix">
                <h3><?php echo __('Test Mixto', 'quiz-maker-failed-questions'); ?></h3>
                <p><?php echo __('Preguntas de todas las categorías', 'quiz-maker-failed-questions'); ?></p>
                <span class="ays-quiz-fq-question-count"><?php 
                    $total_questions = 0;
                    foreach ($categories as $category) {
                        $total_questions += intval($category['question_count']);
                    }
                    echo sprintf(__('%d preguntas falladas', 'quiz-maker-failed-questions'), $total_questions); 
                ?></span>
                <button 
                    class="ays-quiz-fq-start-test" 
                    data-category="0" 
                    <?php echo ($total_questions == 0) ? 'disabled' : ''; ?>>
                    <?php echo __('Iniciar Test', 'quiz-maker-failed-questions'); ?>
                </button>
            </div>
            
            <?php foreach ($categories as $category) : ?>
                <div class="ays-quiz-fq-category-item">
                    <h3><?php echo esc_html($category['title']); ?></h3>
                    <span class="ays-quiz-fq-question-count"><?php 
                        echo sprintf(__('%d preguntas falladas', 'quiz-maker-failed-questions'), $category['question_count']); 
                    ?></span>
                    <button 
                        class="ays-quiz-fq-start-test" 
                        data-category="<?php echo esc_attr($category['category_id']); ?>"
                        <?php echo ($category['question_count'] == 0) ? 'disabled' : ''; ?>>
                        <?php echo __('Iniciar Test', 'quiz-maker-failed-questions'); ?>
                    </button>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="ays-quiz-fq-info-box">
            <h4><?php echo __('¿Cómo funciona?', 'quiz-maker-failed-questions'); ?></h4>
            <ul>
                <li><?php echo __('Las preguntas que fallas en tus cuestionarios se guardan automáticamente.', 'quiz-maker-failed-questions'); ?></li>
                <li><?php echo sprintf(__('Necesitas responder correctamente una pregunta %d veces consecutivas para considerarla dominada.', 'quiz-maker-failed-questions'), isset($settings['consecutive_correct_needed']) ? intval($settings['consecutive_correct_needed']) : 3); ?></li>
                <li><?php echo __('Si vuelves a fallar la pregunta, el contador se reinicia.', 'quiz-maker-failed-questions'); ?></li>
                <li><?php echo sprintf(__('Cada test contiene un máximo de %d preguntas falladas.', 'quiz-maker-failed-questions'), isset($settings['max_questions']) ? intval($settings['max_questions']) : 20); ?></li>
            </ul>
        </div>
        
        <!-- Loading indicator (hidden by default) -->
        <div class="ays-quiz-fq-loading" style="display: none;">
            <div class="ays-quiz-fq-loader"></div>
            <p><?php echo __('Generando tu test...', 'quiz-maker-failed-questions'); ?></p>
        </div>
    <?php endif; ?>
</div>