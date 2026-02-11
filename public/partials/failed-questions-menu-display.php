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
$shortcode_text = isset($settings['shortcode_text']) ? esc_attr($settings['shortcode_text']) : 'Test de preguntas falladas';
$max_questions = isset($settings['max_questions']) ? intval($settings['max_questions']) : 20;
$consecutive_correct_needed = isset($settings['consecutive_correct_needed']) ? intval($settings['consecutive_correct_needed']) : 3;

?>
<div class="ays-quiz-failed-questions-container">
    <h2><?php echo esc_html($shortcode_text); ?></h2>

    <?php if (empty($categories)) : ?>
        <div class="ays-quiz-fq-no-questions-message">
            <p><?php echo esc_html__('No tienes preguntas falladas disponibles. Realiza algunos cuestionarios para registrar tus preguntas falladas.', 'quiz-maker-failed-questions'); ?></p>
            
            <?php if (current_user_can('manage_options')): // Solo mostrar a administradores ?>
                <div class="ays-quiz-fq-debug-info">
                    <h4><?php echo esc_html__('Información de depuración (solo admin)', 'quiz-maker-failed-questions'); ?></h4>
                    <?php
                    global $wpdb;
                    $table_failed_questions = $wpdb->prefix . 'aysquiz_failed_questions';
                    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_failed_questions'") == $table_failed_questions;
                    ?>
                    <ul>
                        <li>Usuario ID: <?php echo get_current_user_id(); ?></li>
                        <li>Tabla de preguntas falladas existe: <?php echo $table_exists ? 'Sí' : 'No'; ?></li>
                        <?php if ($table_exists): ?>
                            <li>Estructura de tabla: 
                                <pre><?php 
                                    $structure = $wpdb->get_results("DESCRIBE $table_failed_questions");
                                    print_r($structure);
                                ?></pre>
                            </li>
                            <li>Total registros en la tabla: <?php echo $wpdb->get_var("SELECT COUNT(*) FROM $table_failed_questions"); ?></li>
                            
                            <?php 
                            // Intentar mostrar los últimos errores de la base de datos
                            $wpdb->show_errors();
                            $recent_errors = $wpdb->last_error ? $wpdb->last_error : 'Ninguno';
                            ?>
                            <li>Últimos errores de BD: <?php echo $recent_errors; ?></li>
                        <?php endif; ?>
                    </ul>
                    
                    <form class="ays-quiz-fq-test-form">
                        <input type="hidden" name="security" value="<?php echo wp_create_nonce('quiz_maker_fq_test_db'); ?>">
                        <button type="button" class="ays-quiz-fq-test-db">Probar inserción en base de datos</button>
                    </form>
                    <div class="ays-quiz-fq-test-result"></div>
                </div>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="ays-quiz-fq-description">
            <p><?php echo esc_html__('Selecciona una categoría para practicar las preguntas que has fallado anteriormente, o elige la opción "Mixto" para practicar preguntas de todas las categorías.', 'quiz-maker-failed-questions'); ?></p>
        </div>
        
        <div class="ays-quiz-fq-categories-list">
            <?php
            // Calcular el total de preguntas falladas para la opción mixta
            $total_questions = 0;
            foreach ($categories as $category) {
                $total_questions += intval($category['question_count']);
            }
            
            // Determinar el número real de preguntas para el test mixto (limitado por max_questions)
            $actual_mixed_questions = min($total_questions, $max_questions);
            ?>
            
            <div class="ays-quiz-fq-category-item ays-quiz-fq-category-mix">
                <h3><?php echo esc_html__('Test Mixto', 'quiz-maker-failed-questions'); ?></h3>
                <p><?php echo esc_html__('Preguntas de todas las categorías', 'quiz-maker-failed-questions'); ?></p>
                <span class="ays-quiz-fq-question-count">
                    <?php echo sprintf(
                        esc_html__('%d preguntas falladas (se usarán máximo %d)', 'quiz-maker-failed-questions'), 
                        $total_questions,
                        $actual_mixed_questions
                    ); ?>
                </span>
                <!-- IMPORTANTE: Aseguramos que category_id es 0 para el test mixto -->
                <a href="<?php echo esc_url(add_query_arg(array('fq_action' => 'start_test', 'category_id' => 0), get_permalink())); ?>"
                class="ays-quiz-fq-start-test"
                <?php echo ($total_questions == 0) ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>>
                    <?php echo esc_html__('Iniciar Test', 'quiz-maker-failed-questions'); ?>
                </a>
            </div>
            
            <?php foreach ($categories as $category) : 
                // Determinar el número real de preguntas para esta categoría
                $category_id = intval($category['category_id']);
                $actual_category_questions = min(intval($category['question_count']), $max_questions);
            ?>
                <div class="ays-quiz-fq-category-item">
                    <h3><?php echo esc_html($category['title']); ?></h3>
                    <span class="ays-quiz-fq-question-count">
                        <?php 
                        echo sprintf(
                            esc_html__('%d preguntas falladas (test de %d preguntas)', 'quiz-maker-failed-questions'), 
                            intval($category['question_count']), 
                            $actual_category_questions
                        ); 
                        ?>
                    </span>
                    <!-- IMPORTANTE: Aseguramos que category_id se pasa correctamente -->
                    <a href="<?php echo esc_url(add_query_arg(array('fq_action' => 'start_test', 'category_id' => $category_id), get_permalink())); ?>"
                    class="ays-quiz-fq-start-test"
                    data-category="<?php echo $category_id; ?>"
                    <?php echo (intval($category['question_count']) == 0) ? 'style="pointer-events: none; opacity: 0.5;"' : ''; ?>>
                        <?php echo esc_html__('Iniciar Test', 'quiz-maker-failed-questions'); ?>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="ays-quiz-fq-info-box">
            <h4><?php echo esc_html__('¿Cómo funciona?', 'quiz-maker-failed-questions'); ?></h4>
            <ul>
                <li><?php echo esc_html__('Las preguntas que fallas en tus cuestionarios se guardan automáticamente.', 'quiz-maker-failed-questions'); ?></li>
                <li><?php echo sprintf(esc_html__('Necesitas responder correctamente una pregunta %d veces consecutivas para considerarla dominada.', 'quiz-maker-failed-questions'), $consecutive_correct_needed); ?></li>
                <li><?php echo esc_html__('Si vuelves a fallar la pregunta, el contador se reinicia.', 'quiz-maker-failed-questions'); ?></li>
                <li><?php echo sprintf(esc_html__('Cada test contiene un máximo de %d preguntas falladas.', 'quiz-maker-failed-questions'), $max_questions); ?></li>
                <li><?php echo esc_html__('Para cada test, tendrás 54 segundos por pregunta para completarlo.', 'quiz-maker-failed-questions'); ?></li>
            </ul>
        </div>
        
        <!-- Loading indicator (hidden by default) -->
        <div class="ays-quiz-fq-loading" style="display: none;">
            <div class="ays-quiz-fq-loader"></div>
            <p><?php echo esc_html__('Generando tu test...', 'quiz-maker-failed-questions'); ?></p>
        </div>
    <?php endif; ?>
        
    <?php if (current_user_can('manage_options')): ?>
        <div class="ays-quiz-fq-system-verification">
            <h4><?php echo esc_html__('Verificación del sistema', 'quiz-maker-failed-questions'); ?></h4>
            <button class="ays-quiz-fq-verify-system">Ejecutar verificación completa</button>
            <div class="ays-quiz-fq-verify-system-result"></div>
        </div>
    <?php endif; ?>
</div>