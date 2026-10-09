<?php
// Файл: /includes/admin/admin-page-main.php

if (!defined('ABSPATH')) exit;

/**
 * Отображает главную страницу плагина со статистикой и инструментами обслуживания.
 */
function vo_data_main_page_html() {
    global $wpdb;

    // --- БЛОК ОБРАБОТКИ POST-ЗАПРОСОВ (остается без изменений) ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['vo_data_action'])) {
        if ($_POST['vo_data_action'] === 'toggle_subject_publication' && check_admin_referer('vo_data_toggle_publication_nonce')) {
            $subject_id = isset($_POST['subject_id']) ? intval($_POST['subject_id']) : 0;
            $year_to_toggle = isset($_POST['academic_year']) ? intval($_POST['academic_year']) : 0;
            $publish = isset($_POST['publish']) ? intval($_POST['publish']) : 0;

            if ($subject_id > 0 && $year_to_toggle > 0) {
                $comp_status_table = $wpdb->prefix . 'vo_competition_status';
                $wpdb->update(
                    $comp_status_table,
                    ['is_published' => $publish ? 1 : 0],
                    ['academic_year' => $year_to_toggle, 'subject_id' => $subject_id]
                );

                // Сбрасываем публичные кэши затронутого года.
                delete_transient('vo_data_dashboard_cache');
                delete_transient('vo_data_dashboard_cache_' . $year_to_toggle);
                update_option('vo_data_cache_version', (int) get_option('vo_data_cache_version', 1) + 1);

                // Публикация снимает/открывает данные публике — уведомляем подписчиков только при открытии.
                if ($publish && function_exists('vo_data_send_notifications')) {
                    $stage_slug = $wpdb->get_var($wpdb->prepare(
                        "SELECT st.slug FROM $comp_status_table cs JOIN {$wpdb->prefix}vo_stage_types st ON cs.current_stage_id = st.id WHERE cs.academic_year = %d AND cs.subject_id = %d",
                        $year_to_toggle, $subject_id
                    ));
                    if ($stage_slug) {
                        vo_data_send_notifications($subject_id, $stage_slug);
                    }
                }

                wp_redirect(admin_url('admin.php?page=vo-data-main&publication_updated=' . ($publish ? '1' : '0')));
                exit;
            }
        }

        if ($_POST['vo_data_action'] === 'delete_by_year_subject' && check_admin_referer('vo_data_delete_by_year_nonce')) {
            $year_to_delete = isset($_POST['year_to_delete']) ? intval($_POST['year_to_delete']) : 0;
            $subjects_to_delete = isset($_POST['subjects_to_delete']) ? array_map('intval', $_POST['subjects_to_delete']) : [];
            if ($year_to_delete && !empty($subjects_to_delete)) {
                $results_table = $wpdb->prefix . 'vo_results';
                $ids_placeholder = implode(',', array_fill(0, count($subjects_to_delete), '%d'));
                $query_results = $wpdb->prepare("DELETE FROM $results_table WHERE academic_year = %d AND subject_id IN ($ids_placeholder)", array_merge([$year_to_delete], $subjects_to_delete));
                $deleted_rows = $wpdb->query($query_results);
                $comp_status_table = $wpdb->prefix . 'vo_competition_status';
                $query_status = $wpdb->prepare("DELETE FROM $comp_status_table WHERE academic_year = %d AND subject_id IN ($ids_placeholder)", array_merge([$year_to_delete], $subjects_to_delete));
                $wpdb->query($query_status);
                wp_redirect(admin_url('admin.php?page=vo-data-main&records_deleted=' . $deleted_rows . '&year=' . $year_to_delete));
                exit;
            }
        }
    }
    
    // --- НАЧАЛО ВЫВОДА HTML ---
    ?>
    <div class="wrap">
        <h1>Статистика загрузок</h1>
        
        <?php
        // Уведомления об успешных действиях
        if (isset($_GET['publication_updated'])) {
            if ($_GET['publication_updated'] === '1') {
                echo '<div class="notice notice-success is-dismissible"><p>Предмет опубликован. Результаты доступны на сайте, подписчики уведомлены.</p></div>';
            } else {
                echo '<div class="notice notice-success is-dismissible"><p>Предмет снят с публикации. Результаты скрыты с сайта.</p></div>';
            }
        }
        if (isset($_GET['records_deleted'])) {
            $deleted_count = intval($_GET['records_deleted']);
            $year = intval($_GET['year']);
            echo '<div class="notice notice-success is-dismissible"><p>Успешно удалено ' . $deleted_count . ' записей за ' . esc_html($year-1) .'-'. esc_html($year) . ' учебный год.</p></div>';
        }
        
        // Отображение статистики
        $comp_status_table = $wpdb->prefix . 'vo_competition_status';
        $latest_year = $wpdb->get_var("SELECT MAX(academic_year) FROM $comp_status_table");

        if (!$latest_year) {
            echo '<div class="notice notice-warning"><p>Данные еще ни разу не загружались...</p></div>';
        } else {
            // ... код статистики остается без изменений ...
            $subjects_table = $wpdb->prefix . 'vo_subjects';
            $stage_types_table = $wpdb->prefix . 'vo_stage_types';
            $results_table = $wpdb->prefix . 'vo_results';
            $query = $wpdb->prepare( "SELECT s.subject_name, cs.subject_id, cs.is_published, st.name AS stage_name, (SELECT COUNT(*) FROM $results_table r WHERE r.academic_year = cs.academic_year AND r.subject_id = cs.subject_id) AS record_count FROM $comp_status_table AS cs JOIN $subjects_table AS s ON cs.subject_id = s.id JOIN $stage_types_table AS st ON cs.current_stage_id = st.id WHERE cs.academic_year = %d ORDER BY s.subject_name ASC", $latest_year);
            $stats_data = $wpdb->get_results($query);
            ?>
            <div class="card">
                <h2>Актуальные данные за <?php echo esc_html($latest_year - 1); ?> - <?php echo esc_html($latest_year); ?> учебный год</h2>
            </div>
            <?php if (!empty($stats_data)) : ?>
                <table class="wp-list-table widefat striped">
                    <thead><tr><th>№</th><th>Предмет</th><th>Последний загруженный этап</th><th>Количество записей</th><th style="width:20%;">Публикация</th></tr></thead>
                    <tbody>
                        <?php foreach ($stats_data as $index => $row) : ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><strong><?php echo esc_html($row->subject_name); ?></strong></td>
                                <td><?php echo esc_html($row->stage_name); ?></td>
                                <td><?php echo esc_html($row->record_count); ?></td>
                                <td>
                                    <?php if ((int) $row->is_published === 1) : ?>
                                        <form method="post" action="" style="display:inline;">
                                            <?php wp_nonce_field('vo_data_toggle_publication_nonce'); ?>
                                            <input type="hidden" name="vo_data_action" value="toggle_subject_publication">
                                            <input type="hidden" name="subject_id" value="<?php echo esc_attr($row->subject_id); ?>">
                                            <input type="hidden" name="academic_year" value="<?php echo esc_attr($latest_year); ?>">
                                            <input type="hidden" name="publish" value="0">
                                            <span style="color:#008a20;font-weight:600;">Опубликован</span>
                                            <button type="submit" class="button button-secondary">Снять с публикации</button>
                                        </form>
                                    <?php else : ?>
                                        <form method="post" action="" style="display:inline;">
                                            <?php wp_nonce_field('vo_data_toggle_publication_nonce'); ?>
                                            <input type="hidden" name="vo_data_action" value="toggle_subject_publication">
                                            <input type="hidden" name="subject_id" value="<?php echo esc_attr($row->subject_id); ?>">
                                            <input type="hidden" name="academic_year" value="<?php echo esc_attr($latest_year); ?>">
                                            <input type="hidden" name="publish" value="1">
                                            <span style="color:#b32d2e;font-weight:600;">Скрыт</span>
                                            <button type="submit" class="button button-primary">Опубликовать</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php } ?>
        
        <!-- Блок: Экспорт данных -->
            <div class="card" style="margin-top: 20px;">
                <h3>Экспорт победителей и призёров</h3>
                <p>Выгрузить список всех лауреатов (победителей и призёров) районного этапа в формате CSV (Excel).</p>
                
                <?php
                $results_table = $wpdb->prefix . 'vo_results';
                $available_years = $wpdb->get_col("SELECT DISTINCT academic_year FROM {$results_table} ORDER BY academic_year DESC");
                ?>

                <form method="post" action="">
                    <?php wp_nonce_field('vo_data_export_nonce'); ?>
                    <input type="hidden" name="vo_data_action" value="export_laureates">
                    
                    <label for="export_year">Учебный год:</label>
                    <select name="export_year" id="export_year">
                        <?php 
                        foreach ($available_years as $year) {
                            printf('<option value="%d">%s-%s</option>', $year, $year - 1, $year);
                        }
                        ?>
                    </select>
                    
                    <button type="submit" class="button button-primary">
                        Скачать список (.csv)
                    </button>
                </form>
                
                <?php if (isset($_GET['export_error']) && $_GET['export_error'] === 'empty'): ?>
                    <p style="color: red;">За выбранный год данных о победителях/призерах не найдено.</p>
                <?php endif; ?>
            </div>
        
        <!-- --- НАЧАЛО ОБНОВЛЕННОЙ СЕКЦИИ "ОБСЛУЖИВАНИЕ" --- -->
        <div style="margin-top: 30px;">
             <h2>Обслуживание данных</h2>
        </div>
        
        <?php
        // --- Главная проверка: есть ли вообще данные в плагине ---
        $results_table = $wpdb->prefix . 'vo_results';
        $total_records_count = $wpdb->get_var("SELECT COUNT(*) FROM $results_table");

        if ($total_records_count > 0) :
        ?>
            <!-- Удаление по предмету и году -->
            <div class="card" style="margin-top: 20px;">
                <h3>Удаление записей по предмету и году</h3>
                <?php
                $available_years = $wpdb->get_col("SELECT DISTINCT academic_year FROM {$results_table} ORDER BY academic_year DESC");
                ?>
                <form method="post" action="">
                    <?php wp_nonce_field('vo_data_show_subjects_nonce', 'show_subjects_for_year_nonce'); ?>
                    <p>Выберите учебный год, чтобы посмотреть, какие данные по предметам можно удалить.</p>
                    <label for="academic_year_to_filter">Учебный год:</label>
                    <select name="academic_year_to_filter" id="academic_year_to_filter">
                        <?php 
                        $selected_year_for_display = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['academic_year_to_filter'])) ? intval($_POST['academic_year_to_filter']) : 0;
                        foreach ($available_years as $year) {
                            printf('<option value="%d" %s>%s-%s</option>', $year, selected($selected_year_for_display, $year, false), $year - 1, $year);
                        }
                        ?>
                    </select>
                    <button type="submit" class="button button-primary">Показать предметы за этот год</button>
                </form>

                <?php
                // Если форма для показа предметов была отправлена
                if ($selected_year_for_display) {
                    $subjects_table = $wpdb->prefix . 'vo_subjects';
                    $results_for_year = $wpdb->get_results($wpdb->prepare( "SELECT s.subject_name, r.subject_id, COUNT(r.id) as record_count FROM {$results_table} r JOIN {$subjects_table} s ON r.subject_id = s.id WHERE r.academic_year = %d GROUP BY r.subject_id ORDER BY s.subject_name ASC", $selected_year_for_display));
                    
                    if (!empty($results_for_year)) {
                    ?>
                    <form method="post" action="" style="margin-top: 20px;">
                        <input type="hidden" name="vo_data_action" value="delete_by_year_subject">
                        <input type="hidden" name="year_to_delete" value="<?php echo esc_attr($selected_year_for_display); ?>">
                        <?php wp_nonce_field('vo_data_delete_by_year_nonce'); ?>
                        <p><strong>Отметьте предметы, результаты по которым нужно удалить за <?php echo esc_html($selected_year_for_display-1); ?>-<?php echo esc_html($selected_year_for_display); ?> год:</strong></p>
                        <table class="wp-list-table widefat striped">
                            <thead>
                                <tr>
                                    <th style="width: 5%;"><input type="checkbox" onchange="jQuery(this).closest('table').find('tbody input[type=checkbox]').prop('checked', this.checked);"></th>
                                    <th>Предмет</th>
                                    <th style="width: 20%;">Количество записей</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results_for_year as $row) : ?>
                                <tr>
                                    <td><input type="checkbox" name="subjects_to_delete[]" value="<?php echo esc_attr($row->subject_id); ?>"></td>
                                    <td><?php echo esc_html($row->subject_name); ?></td>
                                    <td><?php echo esc_html($row->record_count); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <p style="margin-top: 15px;">
                            <button type="submit" class="button button-danger" onclick="return confirm('ВНИМАНИЕ!\n\nВы уверены, что хотите удалить все записи для выбранных предметов? Это действие необратимо!');">
                                Удалить выбранные записи
                            </button>
                        </p>
                    </form>
                    <?php
                    }
                }
                ?>
            </div>

        <?php else : // Если $total_records_count == 0 ?>
            <div class="card">
                <p>Для начала загрузите данные. Инструменты обслуживания появятся здесь после первой загрузки.</p>
            </div>
        <?php endif; // Конец главной проверки ?>
        <!-- --- КОНЕЦ ОБНОВЛЕННОЙ СЕКЦИИ --- -->

    </div>
    <?php
}