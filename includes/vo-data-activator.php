<?php
// Файл: /includes/vo-data-activator.php

if (!defined('ABSPATH')) exit;

/**
 * Главная функция, вызываемая при активации.
 * ПРОВЕРЯЕТ, не является ли это сетевой активацией, и запрещает ее.
 */
function vo_data_on_activation() {
    // Если пользователь находится в админке сети, прерываем активацию
    if (is_network_admin()) {
        // Останавливаем выполнение и выводим сообщение
        wp_die(
            '<strong>Плагин VO Data не поддерживает сетевую активацию.</strong><br>Пожалуйста, активируйте этот плагин для каждого сайта индивидуально через его собственную панель управления в разделе "Плагины".',
            'Сетевая активация запрещена',
            ['back_link' => true]
        );
    }

    // Если проверка пройдена (это обычная, одиночная активация),
    // то просто запускаем создание таблиц для ТЕКУЩЕГО сайта.
    vo_data_create_all_tables();
}

/**
 * Функция, которая содержит SQL-код и создает все наши таблицы.
 * (Ее код остается таким же, как в прошлый раз)
 */
function vo_data_create_all_tables() {
    global $wpdb;
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    $charset_collate = $wpdb->get_charset_collate();

    // Таблица 1: Школы
    $table_schools = $wpdb->prefix . 'vo_schools';
    $sql_schools = "CREATE TABLE $table_schools (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        full_name TEXT NOT NULL,
        short_name VARCHAR(255) DEFAULT '' NOT NULL,
        school_number VARCHAR(20) DEFAULT '' NOT NULL,
        address TEXT,
        PRIMARY KEY (id)
    ) $charset_collate;";
    dbDelta($sql_schools);

    // ... (и так далее для всех 4 таблиц)
    
    // Таблица 2: Предметы
    $table_subjects = $wpdb->prefix . 'vo_subjects';
    $sql_subjects = "CREATE TABLE $table_subjects (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        subject_name VARCHAR(255) NOT NULL,
        short_subject_name VARCHAR(50) DEFAULT '' NOT NULL,
        description LONGTEXT,
        page_id BIGINT(20) UNSIGNED DEFAULT 0 NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY subject_name (subject_name)
    ) $charset_collate;";
    dbDelta($sql_subjects);

    // Таблица 3: Участники
    $table_participants = $wpdb->prefix . 'vo_participants';
    $sql_participants = "CREATE TABLE $table_participants (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        last_name VARCHAR(100) NOT NULL,
        first_name VARCHAR(100) NOT NULL,
        patronymic VARCHAR(100) DEFAULT '' NOT NULL,
        gender ENUM('М', 'Ж') NOT NULL,
        uuid CHAR(64) NOT NULL,
        graduation_year SMALLINT UNSIGNED NOT NULL,
        school_id BIGINT(20) UNSIGNED NOT NULL,
        notes TEXT,
        PRIMARY KEY (id),
        UNIQUE KEY uuid (uuid),
        KEY school_id (school_id)
    ) $charset_collate;";
    dbDelta($sql_participants);

    // Таблица 4: Результаты
    $table_results = $wpdb->prefix . 'vo_results';
    $sql_results = "CREATE TABLE $table_results (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        participant_id BIGINT(20) UNSIGNED NOT NULL,
        subject_id BIGINT(20) UNSIGNED NOT NULL,
        academic_year SMALLINT UNSIGNED NOT NULL,
        class_grade TINYINT UNSIGNED NOT NULL,
        school_level_score VARCHAR(20) DEFAULT '' NOT NULL,
        score VARCHAR(20) DEFAULT '' NOT NULL,
        status ENUM('Участник', 'Призёр', 'Победитель') DEFAULT 'Участник' NOT NULL,
        is_regional_qualifier TINYINT(1) DEFAULT 0 NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY participant_subject_year (participant_id, subject_id, academic_year),
        KEY academic_year (academic_year)
    ) $charset_collate;";
    dbDelta($sql_results);
    // --- 5. НОВЫЙ КОД: Таблица-справочник для ЭТАПОВ ---
    $table_stage_types = $wpdb->prefix . 'vo_stage_types';
    $sql_stage_types = "CREATE TABLE $table_stage_types (
        id TINYINT UNSIGNED NOT NULL AUTO_INCREMENT,
        name VARCHAR(100) NOT NULL,
        slug VARCHAR(50) NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY slug (slug)
    ) $charset_collate;";
    dbDelta($sql_stage_types);
    
    // --- 6. НОВЫЙ КОД: Таблица для отслеживания СТАТУСА ОЛИМПИАДЫ ---
    $table_comp_status = $wpdb->prefix . 'vo_competition_status';
    $sql_comp_status = "CREATE TABLE $table_comp_status (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        academic_year SMALLINT UNSIGNED NOT NULL,
        subject_id BIGINT(20) UNSIGNED NOT NULL,
        current_stage_id TINYINT UNSIGNED NOT NULL,
        is_published TINYINT(1) NOT NULL DEFAULT 1,
        PRIMARY KEY (id),
        UNIQUE KEY competition (academic_year, subject_id)
    ) $charset_collate;";
    dbDelta($sql_comp_status);

// --- НОВЫЙ КОД: Заполняем справочник этапов, если он пуст ---
    $is_stages_empty = $wpdb->get_var("SELECT COUNT(*) FROM $table_stage_types") == 0;
    if ($is_stages_empty) {
        $wpdb->insert($table_stage_types, ['name' => 'Список участников', 'slug' => 'school_stage']);
        $wpdb->insert($table_stage_types, ['name' => 'Предварительные результаты', 'slug' => 'district_prelim']);
        $wpdb->insert($table_stage_types, ['name' => 'Итоги', 'slug' => 'district_final']);
        $wpdb->insert($table_stage_types, ['name' => 'Итоги и прошедшие на региональный этап', 'slug' => 'regional_stage']);
    }
}

/**
 * Миграция схемы для уже активных сайтов.
 * Хук активации повторно не срабатывает, поэтому колонку is_published
 * добавляем отдельно на plugins_loaded.
 */
function vo_data_maybe_upgrade_schema() {
    global $wpdb;
    $table_comp_status = $wpdb->prefix . 'vo_competition_status';

    $table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table_comp_status));
    if (!$table_exists) {
        return;
    }

    $column_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM $table_comp_status LIKE %s", 'is_published'));
    if (!$column_exists) {
        $wpdb->query("ALTER TABLE $table_comp_status ADD COLUMN is_published TINYINT(1) NOT NULL DEFAULT 1");
    }
}

function vo_data_create_subscription_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vo_subscriptions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        max_user_id varchar(100) NOT NULL,
        subject_id bigint(20) NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY user_subject (max_user_id, subject_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
// Вызовите это при активации или просто один раз