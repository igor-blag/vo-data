<?php
// Файл: /includes/admin/admin-page-regional.php

if (!defined('ABSPATH')) exit;

/**
 * Отображает и обрабатывает страницу для отбора участников на региональный этап.
 */
function vo_data_regional_page_html() {
    global $wpdb;
    echo '<div class="wrap">';
    echo '<h1>Отбор на региональный этап</h1>';

    // --- ОБРАБОТКА POST-ЗАПРОСА ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_regional_qualifiers_nonce'])) {
        if (check_admin_referer('vo_data_update_regional_nonce', 'update_regional_qualifiers_nonce')) {
            
            // Определяем, с какой формы пришли данные
            $is_no_qualifiers_form = isset($_POST['no_qualifiers_action']);

            $subject_id_to_update = $is_no_qualifiers_form 
                ? (isset($_POST['subject_id_for_no_qualifiers']) ? intval($_POST['subject_id_for_no_qualifiers']) : 0)
                : (isset($_POST['subject_id_hidden']) ? intval($_POST['subject_id_hidden']) : 0);
                
            if (isset($_POST['academic_year_hidden']) && !empty($_POST['academic_year_hidden'])) {
                            $academic_year_to_update = intval($_POST['academic_year_hidden']);
                        } else {
                            // Если год не был передан, вычисляем его по умолчанию
                            $academic_year_to_update = (int)date('n') >= 9 ? (int)date('Y') + 1 : (int)date('Y');
                        }
                        
            if ($subject_id_to_update > 0 && $academic_year_to_update > 0) {
                
                $stage_types_table = $wpdb->prefix . 'vo_stage_types';
                $regional_stage_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $stage_types_table WHERE slug = %s", 'regional_stage'));
                
                // Сценарий 1: Отмечен чекбокс "Нет прошедших" (с любой из форм)
                if (isset($_POST['no_qualifiers'])) {
                    if ($regional_stage_id) {
                        $comp_status_table = $wpdb->prefix . 'vo_competition_status';
                        $wpdb->query($wpdb->prepare("INSERT INTO $comp_status_table (academic_year, subject_id, current_stage_id) VALUES (%d, %d, %d) ON DUPLICATE KEY UPDATE current_stage_id = %d", $academic_year_to_update, $subject_id_to_update, $regional_stage_id, $regional_stage_id));
                    }
                    $results_table = $wpdb->prefix . 'vo_results';
                    $wpdb->update($results_table, ['is_regional_qualifier' => 0], ['subject_id' => $subject_id_to_update, 'academic_year' => $academic_year_to_update]);
                    
                    echo '<div class="notice notice-success is-dismissible"><p>Статус обновлен. Указано, что по данному предмету нет прошедших на региональный этап.</p></div>';

                } else { // Сценарий 2: Выбраны участники
                    $selected_ids = isset($_POST['selected_qualifiers']) ? array_map('intval', $_POST['selected_qualifiers']) : [];
                    $displayed_ids_str = isset($_POST['displayed_qualifiers']) ? $_POST['displayed_qualifiers'] : '';
                    $displayed_ids = !empty($displayed_ids_str) ? array_map('intval', explode(',', $displayed_ids_str)) : [];

                    if (!empty($displayed_ids)) {
                        if ($regional_stage_id) {
                            $comp_status_table = $wpdb->prefix . 'vo_competition_status';
                            $wpdb->query($wpdb->prepare("INSERT INTO $comp_status_table (academic_year, subject_id, current_stage_id) VALUES (%d, %d, %d) ON DUPLICATE KEY UPDATE current_stage_id = %d", $academic_year_to_update, $subject_id_to_update, $regional_stage_id, $regional_stage_id));
                        }
                        $results_table = $wpdb->prefix . 'vo_results';
                        $ids_to_clear = array_diff($displayed_ids, $selected_ids);
                        $updated_count = 0; $cleared_count = 0;
                        if (!empty($selected_ids)) {
                            $ids_placeholder = implode(',', array_fill(0, count($selected_ids), '%d'));
                            $updated_count = $wpdb->query($wpdb->prepare("UPDATE $results_table SET is_regional_qualifier = 1 WHERE id IN ($ids_placeholder)", $selected_ids));
                        }
                        if (!empty($ids_to_clear)) {
                            $ids_placeholder = implode(',', array_fill(0, count($ids_to_clear), '%d'));
                            $cleared_count = $wpdb->query($wpdb->prepare("UPDATE $results_table SET is_regional_qualifier = 0 WHERE id IN ($ids_placeholder)", $ids_to_clear));
                        }
                        echo '<div class="notice notice-success is-dismissible"><p>Данные успешно обновлены. Отмечено: ' . intval($updated_count) . '. Снято отметок: '. intval($cleared_count) .'.</p></div>';
                    }
                }
                delete_transient('vo_data_dashboard_cache');
                delete_transient('vo_data_dashboard_cache_' . $academic_year_to_update);
            }
        }
    }

    // --- ОТОБРАЖЕНИЕ ФОРМЫ ВЫБОРА ---
    $current_month = (int)date('n');
    $academic_year = ($current_month >= 9) ? (int)date('Y') + 1 : (int)date('Y');
    
    $subjects_table = $wpdb->prefix . 'vo_subjects';
    $results_table = $wpdb->prefix . 'vo_results';
    $subjects = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT s.id, s.subject_name FROM $subjects_table s JOIN $results_table r ON s.id = r.subject_id WHERE r.academic_year = %d ORDER BY s.subject_name ASC", $academic_year));
    ?>
    <div class="card">
        <!-- Форма GET для формирования списков -->
        <form method="get" action="" id="form-generate-lists">
            <input type="hidden" name="page" value="vo-data-regional-qualifiers">
            <table class="form-table">
                 <tbody>
                    <tr>
                        <th scope="row"><label for="subject_id">Предмет</label></th>
                        <td>
                            <select name="subject_id" id="subject_id" required>
                                <option value="">-- Выберите предмет --</option>
                                <?php
                                $current_subject_id = isset($_GET['subject_id']) ? intval($_GET['subject_id']) : 0;
                                foreach ($subjects as $subject) {
                                    printf('<option value="%d" %s>%s</option>', $subject->id, selected($current_subject_id, $subject->id, false), esc_html($subject->subject_name));
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="limit_per_class">Количество записей для класса</label></th>
                        <td><input name="limit_per_class" type="number" id="limit_per_class" value="<?php echo isset($_GET['limit_per_class']) ? intval($_GET['limit_per_class']) : 5; ?>" class="small-text"></td>
                    </tr>
                </tbody>
            </table>
            <p class="submit"><input type="submit" id="button-generate-lists" class="button button-primary" value="Сформировать списки"></p>
        </form>

        <!-- Форма POST для отправки статуса "Нет прошедших" -->
        <form method="post" action="" id="form-no-qualifiers" style="margin-top: 20px; padding-top: 20px; border-top: 1px dashed #ccc;">
             <?php wp_nonce_field('vo_data_update_regional_nonce', 'update_regional_qualifiers_nonce'); ?>
             <input type="hidden" name="no_qualifiers_action" value="1">
             <input type="hidden" name="subject_id_for_no_qualifiers" id="subject_id_for_no_qualifiers_hidden" value="">
             <input type="hidden" name="academic_year_hidden" value="<?php echo esc_attr($academic_year); ?>">
             <p>
                <label>
                    <input type="checkbox" name="no_qualifiers" id="no-qualifiers-checkbox-main">
                    <strong>По данному предмету нет участников, прошедших на региональный этап.</strong>
                </label>
             </p>
             <p class="submit">
                <button type="submit" id="button-no-qualifiers" class="button button-secondary" disabled>Отправить статус</button>
             </p>
        </form>
    </div>
    
    <?php
    // --- ОТОБРАЖЕНИЕ СПИСКОВ (если был GET-запрос) ---
    if (isset($_GET['subject_id']) && !empty($_GET['subject_id'])) {
        // ... (этот блок кода остается почти без изменений) ...
        $subject_id = intval($_GET['subject_id']);
        $limit_per_class = isset($_GET['limit_per_class']) ? intval($_GET['limit_per_class']) : 5;
        $participants_table = $wpdb->prefix . 'vo_participants';
        $query = $wpdb->prepare( "SELECT id, participant_id, class_grade, score, is_regional_qualifier, last_name, first_name, patronymic FROM ( SELECT r.id, r.participant_id, r.class_grade, r.score, r.is_regional_qualifier, p.last_name, p.first_name, p.patronymic, ROW_NUMBER() OVER (PARTITION BY r.class_grade ORDER BY CAST(r.score AS SIGNED) DESC) as rn FROM {$results_table} r JOIN {$participants_table} p ON r.participant_id = p.id WHERE r.subject_id = %d AND r.academic_year = %d AND r.score != '' ) as ranked_results WHERE rn <= %d ORDER BY class_grade ASC, CAST(score AS SIGNED) DESC", $subject_id, $academic_year, $limit_per_class);
        $results = $wpdb->get_results($query);

        if (empty($results)) {
            echo '<div class="notice notice-warning"><p>Нет данных для отображения по выбранному предмету за текущий учебный год.</p></div>';
        } else {
            $grouped_results = [];
            foreach ($results as $result) { $grouped_results[$result->class_grade][] = $result; }
            $all_displayed_ids = array_map(function($r) { return $r->id; }, $results);
            ?>
            <hr>
            <form method="post" action="">
                <input type="hidden" name="displayed_qualifiers" value="<?php echo esc_attr(implode(',', $all_displayed_ids)); ?>">
                <input type="hidden" name="subject_id_hidden" value="<?php echo esc_attr($subject_id); ?>">
                <input type="hidden" name="academic_year_hidden" value="<?php echo esc_attr($academic_year); ?>">
                <?php wp_nonce_field('vo_data_update_regional_nonce', 'update_regional_qualifiers_nonce'); ?>
                
                <div id="participants-lists">
                    <?php foreach ($grouped_results as $class_grade => $participants) : ?>
                        <div class="card" style="margin-top:20px;">
                            <h2>Класс: <?php echo esc_html($class_grade); ?></h2>
                            <table class="wp-list-table widefat striped">
                                <thead>
                                    <tr>
                                        <th style="width: 5%;"><input type="checkbox" class="select-all-in-class"></th>
                                        <th>ФИО</th>
                                        <th style="width: 15%;">Балл</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($participants as $p) : ?>
                                        <tr>
                                            <td><input type="checkbox" name="selected_qualifiers[]" value="<?php echo $p->id; ?>" class="participant-checkbox" <?php checked(in_array($p->is_regional_qualifier, [1, -1])); ?>></td>
                                            <td><?php echo esc_html(trim($p->last_name . ' ' . $p->first_name . ' ' . $p->patronymic)); ?></td>
                                            <td><?php echo esc_html($p->score); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <p class="submit">
                    <button type="submit" id="submit-regional-qualifiers" class="button button-primary button-hero" disabled>Отправить</button>
                </p>
            </form>
            <?php
        }
    }
    ?>
    <!-- <<< ОБНОВЛЕННЫЙ СКРИПТ >>> -->
    <script>
    jQuery(document).ready(function($) {
        // --- Логика для верхней формы (быстрое обновление статуса) ---
        const subjectSelect = $('#subject_id');
        const noQualifiersCheckboxMain = $('#no-qualifiers-checkbox-main');
        const buttonNoQualifiers = $('#button-no-qualifiers');
        const buttonGenerateLists = $('#button-generate-lists');
        const hiddenSubjectIdInput = $('#subject_id_for_no_qualifiers_hidden');

        function updateMainFormState() {
            const subjectSelected = subjectSelect.val() !== '';
            const checkboxChecked = noQualifiersCheckboxMain.is(':checked');

            // Обновляем скрытое поле с ID предмета
            hiddenSubjectIdInput.val(subjectSelect.val());

            // Кнопка "Отправить статус" активна, если выбран предмет И стоит галочка
            buttonNoQualifiers.prop('disabled', !subjectSelected || !checkboxChecked);

            // Кнопка "Сформировать списки" НЕактивна, если стоит галочка
            buttonGenerateLists.prop('disabled', checkboxChecked);
        }

        subjectSelect.on('change', updateMainFormState);
        noQualifiersCheckboxMain.on('change', updateMainFormState);

        // --- Логика для нижней формы (выбор участников) ---
        const submitBtnBottom = $('#submit-regional-qualifiers');
        const participantCheckboxes = $('.participant-checkbox');

        function updateBottomFormState() {
            const anyParticipantChecked = participantCheckboxes.filter(':checked').length > 0;
            submitBtnBottom.prop('disabled', !anyParticipantChecked);
        }

        participantCheckboxes.on('change', updateBottomFormState);
        
        $('.select-all-in-class').on('change', function() {
            $(this).closest('table').find('tbody .participant-checkbox').prop('checked', this.checked).trigger('change');
        });

        // Первоначальная проверка для обеих форм
        updateMainFormState();
        if (submitBtnBottom.length) { // Проверяем, существует ли нижняя кнопка
            updateBottomFormState();
        }
    });
    </script>
    <?php
    echo '</div>'; // close .wrap
}