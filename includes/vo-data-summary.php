<?php
// Файл: /includes/vo-data-summary.php
// Шорткод [vo_summary] — сводка по итогам районного этапа.

if (!defined('ABSPATH')) exit;

add_shortcode('vo_summary', 'vo_data_render_summary_shortcode');

function vo_data_render_summary_shortcode($atts) {
    global $wpdb;

    $atts = shortcode_atts(['year' => ''], $atts, 'vo_summary');
    $year_from_attr = !empty($atts['year']);
    $academic_year = vo_data_resolve_academic_year($atts);

    // Скрипт вкладок нужен всегда при выводе шорткода (в т.ч. когда HTML из кэша).
    wp_enqueue_script('vo-data-public-js');

    $t = vo_data_table_names();

    // --- GET-параметры ---
    $active_tab      = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'laureates';
    if (!in_array($active_tab, ['laureates', 'regional', 'by_subject', 'by_school'], true)) {
        $active_tab = 'laureates';
    }
    $selected_school  = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
    $selected_subject = isset($_GET['subject']) ? sanitize_text_field($_GET['subject']) : '';

    // --- Кэширование ---
    $cache_version = vo_data_cache_version();
    $cache_key = sprintf(
        'vo_data_summary_v%d_%d_%s_%d_%s',
        $cache_version, $academic_year, $active_tab,
        $selected_school, md5($selected_subject)
    );
    $cached_html = get_transient($cache_key);
    if (false !== $cached_html) {
        return $cached_html;
    }

    ob_start();

    // Снятые с публикации предметы исключаем из публичной сводки.
    $pub_join  = " LEFT JOIN {$t['comp_status']} cs ON cs.subject_id = r.subject_id AND cs.academic_year = r.academic_year ";
    $pub_where = " AND (cs.is_published IS NULL OR cs.is_published = 1) ";

    // --- Общая статистика ---
    $total_participants = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*)
         FROM {$t['results']} r
         $pub_join
         WHERE r.academic_year = %d AND r.score != '' AND r.score IS NOT NULL AND CAST(r.score AS SIGNED) > 0
         $pub_where",
        $academic_year
    ));

    $total_winners = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$t['results']} r
         $pub_join
         WHERE r.academic_year = %d AND r.status = 'Победитель'
         $pub_where",
        $academic_year
    ));

    $total_prizers = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$t['results']} r
         $pub_join
         WHERE r.academic_year = %d AND r.status = 'Призёр'
         $pub_where",
        $academic_year
    ));

    $total_regional = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$t['results']} r
         $pub_join
         WHERE r.academic_year = %d AND r.is_regional_qualifier = 1
         $pub_where",
        $academic_year
    ));

    $total_subjects = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT r.subject_id)
         FROM {$t['results']} r
         $pub_join
         WHERE r.academic_year = %d AND r.score != '' AND r.score IS NOT NULL
         $pub_where",
        $academic_year
    ));

    $total_schools = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(DISTINCT p.school_id)
         FROM {$t['results']} r
         $pub_join
         JOIN {$t['participants']} p ON r.participant_id = p.id
         WHERE r.academic_year = %d AND r.score != '' AND r.score IS NOT NULL
         $pub_where",
        $academic_year
    ));

    $result_rate = $total_participants > 0
        ? round(($total_winners + $total_prizers) / $total_participants * 100, 1)
        : 0;

    // --- Списки для фильтров ---
    $available_schools = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT s.id, s.short_name
         FROM {$t['schools']} s
         JOIN {$t['participants']} p ON s.id = p.school_id
         JOIN {$t['results']} r ON p.id = r.participant_id
         $pub_join
         WHERE r.academic_year = %d AND r.status IN ('Победитель', 'Призёр')
         $pub_where
         ORDER BY s.short_name",
        $academic_year
    ));

    $available_subjects = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT sub.id, sub.subject_name, sub.page_id
         FROM {$t['subjects']} sub
         JOIN {$t['results']} r ON sub.id = r.subject_id
         $pub_join
         WHERE r.academic_year = %d AND r.status IN ('Победитель', 'Призёр')
         $pub_where
         ORDER BY sub.subject_name",
        $academic_year
    ));

    // --- Сводная таблица лауреатов ---
    $laureates_where = ["r.academic_year = %d", "r.status IN ('Победитель', 'Призёр')", "(cs.is_published IS NULL OR cs.is_published = 1)"];
    $laureates_params = [$academic_year];

    if ($selected_school) {
        $laureates_where[] = "p.school_id = %d";
        $laureates_params[] = $selected_school;
    }
    if (!empty($selected_subject)) {
        $laureates_where[] = "sub.subject_name = %s";
        $laureates_params[] = $selected_subject;
    }

    $laureates_sql = implode(' AND ', $laureates_where);
    $laureates = $wpdb->get_results($wpdb->prepare(
        "SELECT sub.subject_name, sub.page_id, r.class_grade,
                p.last_name, p.first_name, p.patronymic,
                s.short_name AS school_name,
                r.score, r.status, r.is_regional_qualifier
         FROM {$t['results']} r
         $pub_join
         JOIN {$t['participants']} p ON r.participant_id = p.id
         JOIN {$t['schools']} s ON p.school_id = s.id
         JOIN {$t['subjects']} sub ON r.subject_id = sub.id
         WHERE {$laureates_sql}
         ORDER BY sub.subject_name, r.status DESC, r.class_grade, CAST(r.score AS SIGNED) DESC",
        $laureates_params
    ));

    // --- Прошедшие на региональный этап ---
    $regional_where = ["r.academic_year = %d", "r.is_regional_qualifier = 1", "(cs.is_published IS NULL OR cs.is_published = 1)"];
    $regional_params = [$academic_year];

    if ($selected_school) {
        $regional_where[] = "p.school_id = %d";
        $regional_params[] = $selected_school;
    }
    if (!empty($selected_subject)) {
        $regional_where[] = "sub.subject_name = %s";
        $regional_params[] = $selected_subject;
    }

    $regional_sql = implode(' AND ', $regional_where);
    $regional_qualifiers = $wpdb->get_results($wpdb->prepare(
        "SELECT sub.subject_name, sub.page_id, r.class_grade,
                p.last_name, p.first_name, p.patronymic,
                s.short_name AS school_name,
                r.score, r.status
         FROM {$t['results']} r
         $pub_join
         JOIN {$t['participants']} p ON r.participant_id = p.id
         JOIN {$t['schools']} s ON p.school_id = s.id
         JOIN {$t['subjects']} sub ON r.subject_id = sub.id
         WHERE {$regional_sql}
         ORDER BY sub.subject_name, r.class_grade, CAST(r.score AS SIGNED) DESC",
        $regional_params
    ));

    // --- Статистика по предметам ---
    $by_subject = $wpdb->get_results($wpdb->prepare(
        "SELECT sub.subject_name, sub.page_id,
                COUNT(DISTINCT r.participant_id) AS participants,
                SUM(r.status = 'Победитель') AS winners,
                SUM(r.status = 'Призёр') AS prizers,
                SUM(r.is_regional_qualifier = 1) AS regional,
                ROUND(AVG(CAST(r.score AS SIGNED)), 1) AS avg_score,
                MIN(CASE WHEN r.status = 'Призёр' THEN CAST(r.score AS SIGNED) END) AS min_prizer_score
         FROM {$t['results']} r
         $pub_join
         JOIN {$t['subjects']} sub ON r.subject_id = sub.id
         WHERE r.academic_year = %d AND r.score != '' AND r.score IS NOT NULL AND CAST(r.score AS SIGNED) > 0
         $pub_where
         GROUP BY sub.id, sub.subject_name, sub.page_id
         ORDER BY sub.subject_name",
        $academic_year
    ));

    // --- Статистика по школам ---
    $by_school = $wpdb->get_results($wpdb->prepare(
        "SELECT s.id AS school_id, s.short_name,
                COUNT(DISTINCT r.participant_id) AS participants,
                SUM(r.status = 'Победитель') AS winners,
                SUM(r.status = 'Призёр') AS prizers,
                SUM(r.is_regional_qualifier = 1) AS regional,
                COUNT(DISTINCT r.subject_id) AS subjects_count
         FROM {$t['results']} r
         $pub_join
         JOIN {$t['participants']} p ON r.participant_id = p.id
         JOIN {$t['schools']} s ON p.school_id = s.id
         WHERE r.academic_year = %d AND r.score != '' AND r.score IS NOT NULL AND CAST(r.score AS SIGNED) > 0
         $pub_where
         GROUP BY s.id, s.short_name
         ORDER BY s.short_name",
        $academic_year
    ));

    // --- Передача данных в шаблон ---
    $template_data = [
        'academic_year'      => $academic_year,
        'year_from_attr'     => $year_from_attr,
        'active_tab'         => $active_tab,
        'total_participants'  => $total_participants,
        'total_winners'       => $total_winners,
        'total_prizers'       => $total_prizers,
        'total_regional'      => $total_regional,
        'total_subjects'      => $total_subjects,
        'total_schools'       => $total_schools,
        'result_rate'         => $result_rate,
        'laureates'           => $laureates,
        'regional_qualifiers' => $regional_qualifiers,
        'by_subject'          => $by_subject,
        'by_school'           => $by_school,
        'available_schools'   => $available_schools,
        'available_subjects'  => $available_subjects,
        'selected_school'     => $selected_school,
        'selected_subject'    => $selected_subject,
    ];

    extract($template_data);
    include plugin_dir_path(__DIR__) . 'templates/summary-display.php';

    $html = ob_get_clean();
    set_transient($cache_key, $html, VO_DATA_CACHE_TTL);
    return $html;
}
