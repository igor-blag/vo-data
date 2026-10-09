<?php
// Файл: /includes/vo-data-backup-handler.php

if (!defined('ABSPATH')) exit;

/**
 * Главный обработчик, который отлавливает POST-запросы со страницы настроек.
 */
function vo_data_handle_backup_restore_actions() {
    if (!current_user_can('manage_options')) return;

    // Действие 1: Создание резервной копии
    if (isset($_POST['vo_data_action']) && $_POST['vo_data_action'] === 'create_backup' && check_admin_referer('vo_data_backup_nonce')) {
        vo_data_create_backup();
    }

    // Действие 2: Восстановление из резервной копии
    if (isset($_POST['vo_data_action']) && $_POST['vo_data_action'] === 'restore_backup' && check_admin_referer('vo_data_restore_nonce')) {
        vo_data_restore_backup();
    }
}

/**
 * Создает ZIP-архив с данными таблиц и настройками плагина.
 */
function vo_data_create_backup() {
    global $wpdb;

    $backup_dir = VO_DATA_PLUGIN_DIR . 'Backups';
    // Создаем папку, если ее нет
    if (!is_dir($backup_dir)) {
        mkdir($backup_dir, 0755, true);
        // Защищаем папку от прямого доступа
        file_put_contents($backup_dir . '/.htaccess', 'deny from all');
        file_put_contents($backup_dir . '/index.php', '<?php // Silence is golden');
    }

    $tables_to_backup = [
        $wpdb->prefix . 'vo_schools', $wpdb->prefix . 'vo_subjects', $wpdb->prefix . 'vo_participants',
        $wpdb->prefix . 'vo_results', $wpdb->prefix . 'vo_stage_types', $wpdb->prefix . 'vo_competition_status',
    ];

    $export_data = [];

    // 1. Экспортируем данные из таблиц
    foreach ($tables_to_backup as $table) {
        $table_data = $wpdb->get_results("SELECT * FROM {$table}", ARRAY_A);
        // Используем имя без префикса для ключа
        $clean_name = str_replace($wpdb->prefix, '', $table);
        $export_data['tables'][$clean_name] = $table_data;
    }

    // 2. Экспортируем настройки
    $export_data['options'] = get_option('vo_data_options', []);

    // 3. Создаем ZIP-архив
    $file_name = 'vo-data-backup-' . date('Y-m-d_H-i-s') . '.zip';
    $file_path = $backup_dir . '/' . $file_name;

    $zip = new ZipArchive();
    if ($zip->open($file_path, ZipArchive::CREATE) !== TRUE) {
        wp_redirect(admin_url('options-general.php?page=vo-data-settings&backup_error=zip'));
        exit;
    }

    $zip->addFromString('backup-data.json', json_encode($export_data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $zip->close();
    
    wp_redirect(admin_url('options-general.php?page=vo-data-settings&backup_success=1'));
    exit;
}

/**
 * Восстанавливает данные из загруженного ZIP-архива.
 */
function vo_data_restore_backup() {
    global $wpdb;

    if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
        wp_redirect(admin_url('options-general.php?page=vo-data-settings&restore_error=upload'));
        exit;
    }

    $zip_path = $_FILES['backup_file']['tmp_name'];
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path) !== TRUE) {
        wp_redirect(admin_url('options-general.php?page=vo-data-settings&restore_error=zip_open'));
        exit;
    }

    $json_data = $zip->getFromName('backup-data.json');
    $zip->close();

    if ($json_data === false) {
        wp_redirect(admin_url('options-general.php?page=vo-data-settings&restore_error=no_data_file'));
        exit;
    }

    $data = json_decode($json_data, true);

    if (json_last_error() !== JSON_ERROR_NONE || !isset($data['tables']) || !isset($data['options'])) {
        wp_redirect(admin_url('options-general.php?page=vo-data-settings&restore_error=json_decode'));
        exit;
    }

    // --- НАЧИНАЕМ ПРОЦЕСС ВОССТАНОВЛЕНИЯ (С ТРАНЗАКЦИЕЙ) ---
    $wpdb->query('START TRANSACTION');

    try {
        // 1. Очищаем старые данные
        foreach (array_keys($data['tables']) as $clean_name) {
            $table_name = $wpdb->prefix . $clean_name;
            $wpdb->query("TRUNCATE TABLE {$table_name}");
        }
        
        // 2. Вставляем новые данные
        foreach ($data['tables'] as $clean_name => $rows) {
            $table_name = $wpdb->prefix . $clean_name;
            foreach ($rows as $row) {
                if ($wpdb->insert($table_name, $row) === false) {
                    throw new Exception("Ошибка вставки данных в таблицу {$table_name}");
                }
            }
        }
        
        // 3. Восстанавливаем настройки
        update_option('vo_data_options', $data['options']);

        // Если все прошло успешно
        $wpdb->query('COMMIT');
    } catch (Exception $e) {
        // Если произошла ошибка, откатываем все изменения
        $wpdb->query('ROLLBACK');
        error_log('VO Data Restore Error: ' . $e->getMessage());
        wp_redirect(admin_url('options-general.php?page=vo-data-settings&restore_error=db_insert'));
        exit;
    }

    wp_redirect(admin_url('options-general.php?page=vo-data-settings&restore_success=1'));
    exit;
}