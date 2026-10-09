<?php
// Файл: /includes/vo-data-api.php
if (!defined('ABSPATH')) exit;

/**
 * Регистрация всех маршрутов API
 */
add_action('rest_api_init', function () {
    
    // 1. Список предметов
    register_rest_route('vo-data/v1', '/subjects', [
        'methods'  => 'GET',
        'callback' => 'vo_data_api_get_subjects',
        'permission_callback' => '__return_true',
    ]);

    // 2. Результаты
    register_rest_route('vo-data/v1', '/results/(?P<id>\d+)', [
        'methods'  => 'GET',
        'callback' => 'vo_data_api_get_results',
        'permission_callback' => '__return_true',
    ]);

    // 3. Подписка (Звездочка)
    register_rest_route('vo-data/v1', '/subscribe', [
        'methods'  => 'POST',
        'callback' => 'vo_data_api_handle_subscription',
        'permission_callback' => '__return_true',
    ]);

    // 4. Мои подписки
    register_rest_route('vo-data/v1', '/my-subscriptions', [
        'methods'  => 'GET',
        'callback' => 'vo_data_api_get_my_subscriptions',
        'permission_callback' => '__return_true',
    ]);
});

/**
 * Вспомогательная функция: Извлечение ID и создание таблицы
 */
function vo_data_get_user_id_from_init_data($init_data) {
    if (empty($init_data)) return false;

    // Декодируем дважды (специфика MAX)
    $decoded = urldecode($init_data);
    if (strpos($decoded, '%') !== false) {
        $decoded = urldecode($decoded);
    }

    parse_str($decoded, $data);
    
    if (isset($data['user'])) {
        $user_json = stripslashes($data['user']);
        $user_obj = json_decode($user_json, true);
        if (isset($user_obj['id'])) {
            return (string)$user_obj['id']; // Возвращаем как строку для БД
        }
    }

    if (isset($data['userId'])) return (string)$data['userId'];
    return false;
}

/**
 * Гарантированное создание таблицы подписок (защита от 500 ошибки)
 */
function vo_data_ensure_subscription_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'vo_subscriptions';
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
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
    return $table_name;
}

/**
 * Колбэки
 */

function vo_data_api_get_subjects() {
    global $wpdb;
    $academic_year = (int)date('n') >= 9 ? (int)date('Y') + 1 : (int)date('Y');
    $results = $wpdb->get_results($wpdb->prepare("
        SELECT s.id, s.subject_name, st.name as stage_name, st.slug as stage_slug 
        FROM {$wpdb->prefix}vo_competition_status cs
        JOIN {$wpdb->prefix}vo_subjects s ON cs.subject_id = s.id
        JOIN {$wpdb->prefix}vo_stage_types st ON cs.current_stage_id = st.id
        WHERE cs.academic_year = %d AND cs.is_published = 1 ORDER BY s.subject_name ASC", $academic_year));
    return rest_ensure_response($results);
}

function vo_data_api_get_results($request) {
    global $wpdb;
    $subject_id = intval($request['id']);
    $academic_year = (int)date('n') >= 9 ? (int)date('Y') + 1 : (int)date('Y');

    $stage_slug = $wpdb->get_var($wpdb->prepare("
        SELECT st.slug FROM {$wpdb->prefix}vo_competition_status cs 
        JOIN {$wpdb->prefix}vo_stage_types st ON cs.current_stage_id = st.id 
        WHERE cs.academic_year = %d AND cs.subject_id = %d", $academic_year, $subject_id));

    // Снятые с публикации предметы приложению не отдаём.
    $is_published = (int) $wpdb->get_var($wpdb->prepare("
        SELECT is_published FROM {$wpdb->prefix}vo_competition_status
        WHERE academic_year = %d AND subject_id = %d", $academic_year, $subject_id));
    if ($is_published === 0) {
        return rest_ensure_response(['stage' => null, 'results' => [], 'year' => $academic_year, 'published' => false]);
    }

    $results = $wpdb->get_results($wpdb->prepare("
        SELECT p.last_name, p.first_name, r.class_grade, s.short_name as school_name,
               r.score, r.school_level_score, r.status, r.is_regional_qualifier
        FROM {$wpdb->prefix}vo_results r
        JOIN {$wpdb->prefix}vo_participants p ON r.participant_id = p.id
        JOIN {$wpdb->prefix}vo_schools s ON p.school_id = s.id
        WHERE r.subject_id = %d AND r.academic_year = %d
        ORDER BY r.class_grade ASC, CAST(NULLIF(r.score, '') AS SIGNED) DESC", $subject_id, $academic_year));

    return rest_ensure_response(['stage' => $stage_slug, 'results' => $results, 'year' => $academic_year]);
}

function vo_data_api_handle_subscription($request) {
    global $wpdb;
    $params = $request->get_json_params();
    
    // Пытаемся получить ID
    $user_id = vo_data_get_user_id_from_init_data($params['initData'] ?? '');
    
    if (!$user_id) {
        return new WP_Error('auth_error', 'Не удалось определить пользователя', ['status' => 403]);
    }

    $table = vo_data_ensure_subscription_table();
    $subject_id = intval($params['subject_id']);
    $action = $params['action'];

    if ($action === 'subscribe') {
        $result = $wpdb->replace($table, [
            'max_user_id' => $user_id,
            'subject_id'  => $subject_id
        ]);
    } else {
        $result = $wpdb->delete($table, [
            'max_user_id' => $user_id,
            'subject_id'  => $subject_id
        ]);
    }

    if ($result === false) {
        return new WP_Error('db_error', 'Ошибка базы данных', ['status' => 500]);
    }

    return rest_ensure_response(['success' => true]);
}

function vo_data_api_get_my_subscriptions($request) {
    global $wpdb;
    $user_id = vo_data_get_user_id_from_init_data($request->get_param('initData'));
    
    if (!$user_id) return rest_ensure_response([]);

    $table = vo_data_ensure_subscription_table();
    $subs = $wpdb->get_col($wpdb->prepare("SELECT subject_id FROM $table WHERE max_user_id = %s", $user_id));
    
    return rest_ensure_response(array_map('intval', $subs ?: []));
}