<?php
// Файл: /includes/vo-data-ajax-handler.php

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_vo_data_import', 'vo_data_handle_import');

/**
 * Создает "слаг" из названия предмета.
 * "Информатика (БПЛА)" -> "информатика-бпла"
 */
function vo_data_slugify_subject_name($name) {
    $name = mb_strtolower($name);
    $name = preg_replace('/[\s()\[\]]+/', '-', $name); // Заменяем пробелы и скобки на дефис
    $name = preg_replace('/[^a-zа-я0-9\-]/u', '', $name); // Удаляем все, что не буква, цифра или дефис
    $name = trim($name, '-');
    return $name;
}

function vo_data_handle_import() {
    check_ajax_referer('vo_data_import_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Недостаточно прав.']);
    }

    $json_data = isset($_POST['data']) ? stripslashes($_POST['data']) : '';
    if (empty($json_data)) {
        wp_send_json_error(['message' => 'Данные не получены.']);
    }
    
    $decoded_data = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        wp_send_json_error(['message' => 'Ошибка разбора JSON: ' . json_last_error_msg()]);
    }
    
    $rows = isset($decoded_data['rows']) ? $decoded_data['rows'] : [];
    $subject_name = isset($decoded_data['subject']) ? sanitize_text_field($decoded_data['subject']) : '';
    $import_type = isset($decoded_data['import_type']) ? sanitize_key($decoded_data['import_type']) : '';
    // НОВЫЙ КОД: Получаем флаг импорта за прошлый год
    $is_previous_year = isset($decoded_data['is_previous_year']) ? (bool)$decoded_data['is_previous_year'] : false;
    
    if (empty($rows) || empty($subject_name) || empty($import_type)) {
        wp_send_json_error(['message' => 'Неполные данные для импорта.']);
    }
    
    error_log('VO Data Import: Received ' . count($rows) . ' rows for subject: ' . $subject_name);

    // --- НОВАЯ ЛОГИКА ОПРЕДЕЛЕНИЯ УЧЕБНОГО ГОДА ---
    $current_month = (int)date('n');
    $current_year = (int)date('Y');

    if ($is_previous_year) {
        // Если импортируем за ПРОШЛЫЙ год
        // Если сейчас осень (сентябрь+), текущий уч. год = current_year + 1. Прошлый = current_year.
        // Если сейчас весна (до сентября), текущий уч. год = current_year. Прошлый = current_year - 1.
        $academic_year = ($current_month >= 9) ? $current_year : $current_year - 1;
    } else {
        // Стандартная логика для ТЕКУЩЕГО года
        $academic_year = ($current_month >= 9) ? $current_year + 1 : $current_year;
    }
    // --- КОНЕЦ НОВОЙ ЛОГИКИ ---
    
    $options = get_option('vo_data_options');
    if (empty($options['dashboard_page_id'])) {
        wp_send_json_error(['message' => 'Ошибка: Главная страница результатов не выбрана в настройках плагина.']);
    }
    
    try {
        global $wpdb;
        $wpdb->query('START TRANSACTION');
        
        $subject_id = vo_data_get_or_create_subject($subject_name);
        $subjects_table = $wpdb->prefix . 'vo_subjects';
        $subject_page_id = $wpdb->get_var($wpdb->prepare("SELECT page_id FROM $subjects_table WHERE id = %d", $subject_id));

        if (empty($subject_page_id)) {
            $page_slug = vo_data_slugify_subject_name($subject_name);
            $new_page_id = wp_insert_post([
                'post_title'    => $subject_name,
                'post_name'     => $page_slug,
                'post_content'  => '[vo_results subject="' . esc_attr($subject_name) . '"]',
                'post_status'   => 'publish',
                'post_type'     => 'page',
                'post_parent'   => $options['dashboard_page_id']
            ]);
            if (!is_wp_error($new_page_id)) {
                $wpdb->update($subjects_table, ['page_id' => $new_page_id], ['id' => $subject_id]);
            }
        }
        
        $stage_types_table = $wpdb->prefix . 'vo_stage_types';
        $stage_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $stage_types_table WHERE slug = %s", $import_type));

        if ($stage_id) {
            $comp_status_table = $wpdb->prefix . 'vo_competition_status';
            $wpdb->query($wpdb->prepare(
                "INSERT INTO $comp_status_table (academic_year, subject_id, current_stage_id) VALUES (%d, %d, %d)
                 ON DUPLICATE KEY UPDATE current_stage_id = %d",
                $academic_year, $subject_id, $stage_id, $stage_id
            ));
        }
        
        $stats = ['processed' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0];
        
        foreach ($rows as $row) {
            if (empty($row)) continue;
            $stats['processed']++;

            $uuid = isset($row['uuid']) ? $row['uuid'] : '';
            if (empty($uuid)) {
                $stats['skipped']++;
                continue;
            }
            
            $school_full_name = isset($row['school_full_name']) ? $row['school_full_name'] : '';
            $school_short_name = isset($row['school_short_name']) ? $row['school_short_name'] : '';
            $school_address = isset($row['school_address']) ? $row['school_address'] : '';
            $school_id = vo_data_get_or_create_school($school_full_name, $school_short_name, $school_address);

            $class_string = isset($row['class']) ? $row['class'] : '0';
            
            $participant_data = [
                'last_name' => isset($row['last_name']) ? $row['last_name'] : '',
                'first_name' => isset($row['first_name']) ? $row['first_name'] : '',
                'patronymic' => isset($row['patronymic']) ? $row['patronymic'] : '',
                'gender' => isset($row['gender']) ? mb_substr($row['gender'], 0, 1) : '',
                'class' => intval($class_string),
                'school_id' => $school_id,
                'uuid' => $uuid,
                'academic_year' => $academic_year
            ];
            $participant_id = vo_data_get_or_create_participant($participant_data);
            
            $update_payload = [];
            $class_grade_string = isset($row['class_grade']) ? $row['class_grade'] : '0';

            if ($import_type === 'school_stage') {
                $update_payload['school_level_score'] = isset($row['score']) ? $row['score'] : '0';
            } else {
                $update_payload['score'] = isset($row['score']) ? $row['score'] : '0';
            }

            if ($import_type === 'district_final') {
                $status_from_file = isset($row['status']) ? $row['status'] : '';
                if (!empty($status_from_file)) {
                     $status_normalized = mb_convert_case($status_from_file, MB_CASE_TITLE, "UTF-8");
                     if (in_array($status_normalized, ['Победитель', 'Призёр', 'Участник'])) {
                         $update_payload['status'] = $status_normalized;
                     }
                }
            }

            $result_data = [
                'participant_id' => $participant_id,
                'subject_id'     => $subject_id,
                'academic_year'  => $academic_year,
                'class_grade'    => intval($class_grade_string),
                'payload'        => $update_payload
            ];
            
            $operation = vo_data_upsert_result($result_data);
            if ($operation === 'created') $stats['created']++;
            if ($operation === 'updated') $stats['updated']++;
        }    
        
// Завершаем транзакцию
        $wpdb->query('COMMIT');
        
        // После COMMIT в vo_data_handle_import
        // Уведомляем подписчиков только если предмет опубликован.
        // Снятые с публикации предметы молчат до момента публикации.
        $is_published_now = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT is_published FROM {$wpdb->prefix}vo_competition_status WHERE academic_year = %d AND subject_id = %d",
            $academic_year, $subject_id
        ));
        if ($is_published_now === 1) {
            vo_data_send_notifications($subject_id, $import_type);
        }
        
        // <<< НАЧАЛО НОВОЙ ЛОГИКИ ИНВАЛИДАЦИИ >>>
        // 1. Очищаем кэш дашборда (для затронутого и текущего года + старый ключ)
        delete_transient('vo_data_dashboard_cache');
        delete_transient('vo_data_dashboard_cache_' . $academic_year);
        $current_academic_year = vo_data_academic_year();
        delete_transient('vo_data_dashboard_cache_' . $current_academic_year);
        // 2. Увеличиваем версию кэша для всех страниц результатов, делая старые кэши недействительными
        $current_version = get_option('vo_data_cache_version', 1);
        update_option('vo_data_cache_version', $current_version + 1);
        // <<< КОНЕЦ НОВОЙ ЛОГИКИ ИНВАЛИДАЦИИ >>>
        
        // Формируем детальное сообщение о результатах
        
        $message = sprintf( "Импорт завершен успешно!\n" . "• Обработано записей: %d\n" . "• Создано новых: %d\n" . "• Обновлено существующих: %d\n" . "• Пропущено (нет СНИЛС или данных для хеша): %d", $stats['processed'], $stats['created'], $stats['updated'], $stats['skipped'] );
        
        wp_send_json_success([ 'message' => nl2br($message), 'stats' => $stats ]);
        
    } catch (Exception $e) {
        $wpdb->query('ROLLBACK');
        error_log('VO Data Import Error: ' . $e->getMessage());
        wp_send_json_error([ 'message' => 'Ошибка импорта: ' . $e->getMessage() ]);
    }
}
/**
 * Рассылает уведомления подписчикам о появлении/обновлении данных по предмету.
 * Вызывается при импорте опубликованного предмета и при его публикации.
 *
 * @param int    $subject_id  ID предмета.
 * @param string $import_type Слаг этапа (school_stage, district_prelim, ...).
 */
function vo_data_send_notifications($subject_id, $import_type) {
    global $wpdb;

    // 1. Берем токен из твоего интерфейса MAX
    $bot_token = 'f9LHodD0cOIO2XBXU8aYrY424wY3vR8sdBs1hHOdVUS0Tuh1TaQVivO0Rb5_NlItokoAEzx2Obkz3uFKj0q1';

    // 2. Данные для текста сообщения
    $subject_name = $wpdb->get_var($wpdb->prepare("SELECT subject_name FROM {$wpdb->prefix}vo_subjects WHERE id = %d", $subject_id));
    $stages = [
        'school_stage' => 'Список участников',
        'district_prelim' => 'Предварительные результаты',
        'district_final' => 'Итоговые результаты',
        'regional_stage' => 'Список прошедших на регион'
    ];
    $stage_text = isset($stages[$import_type]) ? $stages[$import_type] : 'Обновление данных';

    // 3. Получаем список ID всех подписчиков на этот предмет
    $subscribers = $wpdb->get_col($wpdb->prepare(
        "SELECT max_user_id FROM {$wpdb->prefix}vo_subscriptions WHERE subject_id = %d",
        $subject_id
    ));

    if (empty($subscribers)) return;

    // 4. Отправляем сообщение каждому
    $api_url = 'https://api.max.ru/v1/bot/send_message'; // Уточни точный URL в документации MAX

    foreach ($subscribers as $user_id) {
        wp_remote_post($api_url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $bot_token,
                'Content-Type'  => 'application/json'
            ],
            'body' => json_encode([
                'user_id' => (int)$user_id,
                'text'    => "🔔 {$subject_name}: {$stage_text}. Откройте приложение, чтобы посмотреть"
            ])
        ]);
    }
}

// --- ВСПОМОГАТЕЛЬНЫЕ ФУНКЦИИ (УБЕДИТЕСЬ, ЧТО ОНИ ЕСТЬ) ---

function vo_data_get_or_create_subject($name) {
    global $wpdb;
    $table = $wpdb->prefix . 'vo_subjects';
    $subject = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE subject_name = %s", $name));
    if ($subject) {
        return $subject->id;
    }
    $wpdb->insert($table, ['subject_name' => $name]);
    return $wpdb->insert_id;
}

function vo_data_get_or_create_school($full_name, $short_name, $address) {
    global $wpdb;
    $table = $wpdb->prefix . 'vo_schools';
    if (empty($full_name)) return 0;
    $school = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE full_name = %s", $full_name));
    if ($school) {
        return $school->id;
    }
    $wpdb->insert($table, ['full_name' => $full_name, 'short_name' => $short_name, 'address' => $address]);
    return $wpdb->insert_id;
}

function vo_data_get_or_create_participant($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'vo_participants';
    $participant = $wpdb->get_row($wpdb->prepare("SELECT id FROM $table WHERE uuid = %s", $data['uuid']));
    
    $graduation_year = $data['academic_year'] + (11 - $data['class']);

    if ($participant) {
        $wpdb->update($table, 
            ['school_id' => $data['school_id'], 'graduation_year' => $graduation_year],
            ['id' => $participant->id]
        );
        return $participant->id;
    } else {
        $wpdb->insert($table, [
            'last_name' => $data['last_name'], 
            'first_name' => $data['first_name'],
            'patronymic' => $data['patronymic'], 
            'gender' => $data['gender'],
            'uuid' => $data['uuid'], 
            'graduation_year' => $graduation_year,
            'school_id' => $data['school_id'], 
            'notes' => ''
        ]);
        return $wpdb->insert_id;
    }
}

/**
 * Универсальная функция для обновления/вставки результата.
 * Теперь она принимает массив 'payload' с данными.
 */
 
function vo_data_upsert_result($data) {
    global $wpdb;
    $table = $wpdb->prefix . 'vo_results';
    
    $existing_result_id = $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM $table WHERE participant_id = %d AND subject_id = %d AND academic_year = %d",
        $data['participant_id'], $data['subject_id'], $data['academic_year']
    ));

    $payload = $data['payload']; // Массив данных для обновления/вставки

    if (empty($payload)) {
        return 'skipped'; // Нечего обновлять
    }

    if ($existing_result_id) {
        $wpdb->update($table, $payload, ['id' => $existing_result_id]);
        return 'updated';
    } else {
        $insert_data = array_merge([
            'participant_id' => $data['participant_id'],
            'subject_id'     => $data['subject_id'],
            'academic_year'  => $data['academic_year'],
            'class_grade'    => $data['class_grade']
        ], $payload);
        
        $wpdb->insert($table, $insert_data);
        return 'created';
    }
}

/**
 * НОВЫЙ ОБРАБОТЧИК ДЛЯ ПРЕДВАРИТЕЛЬНОЙ ПРОВЕРКИ ПЕРЕД ИМПОРТОМ
 */
add_action('wp_ajax_vo_data_pre_import_check', 'vo_data_handle_pre_import_check');
function vo_data_handle_pre_import_check() {
    check_ajax_referer('vo_data_import_nonce', 'nonce');
    if (!current_user_can('manage_options')) {
        wp_send_json_error(['message' => 'Недостаточно прав.']);
    }

    $subject_name = isset($_POST['subject']) ? sanitize_text_field($_POST['subject']) : '';
    $import_type = isset($_POST['import_type']) ? sanitize_key($_POST['import_type']) : '';
    $is_previous_year = isset($_POST['is_previous_year']) ? rest_sanitize_boolean($_POST['is_previous_year']) : false;

    if (empty($subject_name) || empty($import_type)) {
        wp_send_json_error(['message' => 'Недостаточно данных для проверки.']);
    }

    global $wpdb;
    
    // Определяем текущий учебный год
    $current_month = (int)date('n');
    $current_academic_year = ($current_month >= 9) ? (int)date('Y') + 1 : (int)date('Y');

    // Находим ID предмета
    $subjects_table = $wpdb->prefix . 'vo_subjects';
    $subject_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $subjects_table WHERE subject_name = %s", $subject_name));

    // Находим текущий этап для этого предмета в ТЕКУЩЕМ году
    $current_stage_id = 0;
    if ($subject_id) {
        $comp_status_table = $wpdb->prefix . 'vo_competition_status';
        $current_stage_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT current_stage_id FROM $comp_status_table WHERE subject_id = %d AND academic_year = %d",
            $subject_id, $current_academic_year
        ));
    }

    // --- НАЧАЛО ЛОГИКИ ПРОВЕРОК ---

    // Сценарий 1: Пользователь грузит ИТОГИ текущего года, но база пустая или там только школьный этап.
    if ($import_type === 'district_final' && !$is_previous_year && $current_stage_id < 2) {
        wp_send_json_error([
            'confirm' => true, // Говорим JS, что нужно спросить подтверждение
            'message' => 'ВНИМАНИЕ!Для этого предмета еще не были загружены предварительные результаты районного этапа. Вы уверены, что хотите сразу загрузить ИТОГИ?',
        ]);
    }

    // Сценарий 2: Пользователь пытается загрузить данные за ПРОШЛЫЙ год, но для этого предмета уже есть данные в ТЕКУЩЕМ году.
    if ($is_previous_year && $current_stage_id > 0) {
        wp_send_json_error([
            'confirm' => true, // Говорим JS, что нужно спросить подтверждение
            'message' => 'Для этого предмета уже начат импорт данных в ТЕКУЩЕМ учебном году. Убедитесь, что вы ничего не перепутали.',
        ]);
    }

    // Если ни одна из проверок не сработала, разрешаем импорт
    wp_send_json_success();
}