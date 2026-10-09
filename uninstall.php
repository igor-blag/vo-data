<?php
// Файл: /uninstall.php

// Проверяем, что файл вызван самим WordPress, а не напрямую
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Получаем наши настройки, чтобы проверить, нужно ли удалять данные
$options = get_option('vo_data_options');

// Если флаг не установлен или его нет, НИЧЕГО НЕ ДЕЛАЕМ
if (empty($options['delete_data_on_uninstall'])) {
    return;
}

// Если флаг установлен, приступаем к удалению
global $wpdb;

// Собираем имена всех наших таблиц
$tables_to_drop = [
    $wpdb->prefix . 'vo_results',
    $wpdb->prefix . 'vo_participants',
    $wpdb->prefix . 'vo_schools',
    $wpdb->prefix . 'vo_subjects',
    $wpdb->prefix . 'vo_competition_status',
    $wpdb->prefix . 'vo_stage_types',
];

// Удаляем каждую таблицу
foreach ($tables_to_drop as $table_name) {
    $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
}

// Также удаляем наши настройки из таблицы wp_options
delete_option('vo_data_options');

// Если плагин был мультисайт-совместимым, здесь был бы цикл по всем сайтам.
// Но так как мы запретили сетевую активацию, этого достаточно.