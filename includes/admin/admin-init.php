<?php
// Файл: /includes/admin/admin-init.php

if (!defined('ABSPATH')) exit;


/**
 * Обработчик экспорта победителей и призеров в CSV
 */

add_action('admin_init', 'vo_data_handle_export_laureates');

function vo_data_handle_export_laureates() {
    if (isset($_POST['vo_data_action']) && $_POST['vo_data_action'] === 'export_laureates') {
        if (!current_user_can('manage_options') || !check_admin_referer('vo_data_export_nonce')) {
            wp_die('Ошибка безопасности.');
        }

        global $wpdb;
        $year = isset($_POST['export_year']) ? intval($_POST['export_year']) : vo_data_academic_year();
        
        $t = vo_data_table_names();
        
        // SQL запрос для получения нужных полей
        $query = $wpdb->prepare("
            SELECT 
                p.last_name, 
                p.first_name, 
                p.gender, 
                r.class_grade, 
                s.short_name as school_name, 
                r.status, 
                sub.subject_name
            FROM {$t['results']} r
            JOIN {$t['participants']} p ON r.participant_id = p.id
            JOIN {$t['schools']} s ON p.school_id = s.id
            JOIN {$t['subjects']} sub ON r.subject_id = sub.id
            WHERE r.academic_year = %d 
              AND r.status IN ('Победитель', 'Призёр')
            ORDER BY sub.subject_name ASC, r.status DESC, r.class_grade ASC, p.last_name ASC
        ", $year);

        $results = $wpdb->get_results($query, ARRAY_A);

        if (empty($results)) {
            wp_redirect(admin_url('admin.php?page=vo-data-main&export_error=empty'));
            exit;
        }

        // Формируем имя файла
        $filename = 'voso_laureates_' . ($year-1) . '-' . $year . '_' . date('Y-m-d') . '.csv';

        // Устанавливаем заголовки для скачивания
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '";');

        // Открываем поток вывода
        $f = fopen('php://output', 'w');
        
        // Добавляем BOM для корректного открытия UTF-8 в Excel
        fprintf($f, chr(0xEF).chr(0xBB).chr(0xBF));

        // Заголовки столбцов
        fputcsv($f, ['№', 'Фамилия', 'Имя', 'Пол', 'Класс', 'Школа', 'Статус', 'Предмет'], ';');

        // Заполняем данными
        $counter = 1;
        foreach ($results as $row) {
            fputcsv($f, [
                $counter++,
                $row['last_name'],
                $row['first_name'],
                $row['gender'],
                $row['class_grade'],
                $row['school_name'],
                $row['status'],
                $row['subject_name']
            ], ';');
        }

        fclose($f);
        exit;
    }
}


/**
 * Создает все пункты меню в админ-панели для плагина.
 */
add_action('admin_menu', 'vo_data_admin_menu');
function vo_data_admin_menu() {
    // Главный пункт меню
    add_menu_page(
        'VO Data',
        'Результаты ВсОШ',
        'manage_options',
        'vo-data-main',
        'vo_data_main_page_html', // Эта функция теперь в файле admin-page-main.php
        'dashicons-analytics'
    );

    // Подпункты меню для импорта
    $hook_school = add_submenu_page(
        'vo-data-main', 'Загрузка списка прошедших на районный этап', 'Школьный этап: прошедшие',
        'manage_options', 'vo-data-school-import', 'vo_data_import_page_html' // Эта функция теперь в файле admin-page-import.php
    );
    $hook_prelim = add_submenu_page(
        'vo-data-main', 'Районный этап: предварительные результаты', 'Предварительные результаты',
        'manage_options', 'vo-data-district-prelim-import', 'vo_data_import_page_html'
    );
    $hook_final = add_submenu_page(
        'vo-data-main', 'Загрузка итогов районного этапа', 'Районный этап: итоги',
        'manage_options', 'vo-data-district-final-import', 'vo_data_import_page_html'
    );
    
    // Подпункт меню для регионального этапа
    add_submenu_page(
        'vo-data-main', 'Прошедшие на региональный этап', 'Региональный этап',
        'manage_options', 'vo-data-regional-qualifiers', 'vo_data_regional_page_html' // Эта функция теперь в файле admin-page-regional.php
    );
    
    // Подключаем скрипты только на наших страницах импорта
    add_action('admin_print_scripts-' . $hook_school, 'vo_data_enqueue_admin_scripts');
    add_action('admin_print_scripts-' . $hook_prelim, 'vo_data_enqueue_admin_scripts');
    add_action('admin_print_scripts-' . $hook_final, 'vo_data_enqueue_admin_scripts');
}

/**
 * Подключает JS-скрипты для страниц импорта.
 */
function vo_data_enqueue_admin_scripts() {
    wp_enqueue_script('sheetjs', plugin_dir_url(dirname(__FILE__, 2)) . 'js/xlsx.full.min.js', [], '0.18.5', true);
    wp_enqueue_script(
        'vo-data-admin-js', 
        plugin_dir_url(dirname(__FILE__, 2)) . 'js/vo-data-admin.js',
        ['jquery', 'sheetjs'], 
        '2.2', 
        true
        );
    
    $options = get_option('vo_data_options');

    wp_localize_script('vo-data-admin-js', 'vo_data_ajax', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce'    => wp_create_nonce('vo_data_import_nonce'),
        'settings' => $options
    ]);
}

/**
 * Обработчик для кнопки полного удаления всех данных плагина.
 */
function vo_data_handle_delete_all_data() {
    if (isset($_POST['vo_data_action']) && $_POST['vo_data_action'] === 'delete_all_tables') {
        if (!current_user_can('manage_options') || !check_admin_referer('vo_data_delete_all_nonce')) {
            wp_die('Ошибка безопасности.');
        }

        global $wpdb;
        $prefix = $wpdb->get_blog_prefix();
        $tables_to_drop = [
            $prefix . 'vo_schools', $prefix . 'vo_subjects', $prefix . 'vo_participants',
            $prefix . 'vo_results', $prefix . 'vo_stage_types', $prefix . 'vo_competition_status',
        ];

        foreach ($tables_to_drop as $table_name) {
            $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        }

        delete_option('vo_data_options');
        deactivate_plugins(plugin_basename(VO_DATA_PLUGIN_DIR . 'vo-data.php'));
        wp_redirect(admin_url('plugins.php?deactivated=true&vo_data_message=deleted'));
        exit;
    }
}

/**
 * Показывает уведомление после успешного удаления данных.
 */
function vo_data_show_deleted_notice() {
    if (isset($_GET['vo_data_message']) && $_GET['vo_data_message'] === 'deleted') {
        echo '<div class="notice notice-success is-dismissible"><p>Все данные плагина VO Data были успешно удалены, плагин деактивирован.</p></div>';
    }
}