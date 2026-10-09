<?php
// Файл: /includes/vo-data-shortcode.php
// Шорткоды [vo_results] и [vo_results_dashboard].
// Антирейтинг вынесен в vo-data-antirating.php.

if (!defined('ABSPATH')) exit;

add_shortcode('vo_results', 'vo_data_render_results_shortcode');

function vo_data_render_results_shortcode($atts) {
    global $wpdb;

    // --- Блок 1: Получение базовых данных и фильтров ---
    $atts = shortcode_atts(['subject' => '', 'year' => ''], $atts, 'vo_results');
    if (empty($atts['subject'])) return '<div class="vo-results-error">...</div>';

    $subject_name = sanitize_text_field($atts['subject']);
    $selected_school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
    $selected_class = isset($_GET['class']) ? intval($_GET['class']) : 0;

    $subjects_table = $wpdb->prefix . 'vo_subjects';
    $subject_id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $subjects_table WHERE subject_name = %s", $subject_name));
    if (!$subject_id) return "<div class=\"vo-results-error\">...</div>";

    $academic_year = vo_data_resolve_academic_year($atts);

    // --- Кэширование ---
    $cache_version = vo_data_cache_version();
    $show_school_score = !empty($_GET['show_school_score']) ? 1 : 0;
    $transient_name = sprintf(
        'vo_data_res_v%d_%d_%d_%d_%d_%d',
        $cache_version, $subject_id, $academic_year,
        $selected_school_id, $selected_class, $show_school_score
    );

    // Подключаем JS переключателя ШБ всегда при выводе шорткода
    // (в т.ч. когда HTML берётся из кэша).
    wp_enqueue_script('vo-data-public-js');

    $cached_html = get_transient($transient_name);
    if (false !== $cached_html) {
        return $cached_html;
    }

    ob_start();

    // --- Блок 2: Определение текущего этапа и статуса публикации ---
    $comp_status_table = $wpdb->prefix . 'vo_competition_status';
    $stage_types_table = $wpdb->prefix . 'vo_stage_types';
    $stage_row = $wpdb->get_row($wpdb->prepare("SELECT st.slug AS stage_slug, cs.is_published FROM $comp_status_table cs JOIN $stage_types_table st ON cs.current_stage_id = st.id WHERE cs.academic_year = %d AND cs.subject_id = %d", $academic_year, $subject_id));
    $current_stage_slug = $stage_row ? $stage_row->stage_slug : null;

    // Снятые с публикации предметы публике не показываем.
    if ($stage_row && isset($stage_row->is_published) && (int) $stage_row->is_published === 0) {
        ob_end_clean();
        $html_to_cache = '<div class="vo-results-notice" style="padding:16px;border:1px solid #ddd;border-radius:2px;background:#fafafa;">'
            . '<p>Результаты по предмету <strong>' . esc_html($subject_name) . '</strong> за '
            . esc_html(($academic_year - 1) . '/' . $academic_year) . ' учебный год ещё не опубликованы.</p></div>';
        set_transient($transient_name, $html_to_cache, VO_DATA_CACHE_TTL);
        return $html_to_cache;
    }

    // --- Блок 3: Получение данных для фильтров ---
    $results_table = $wpdb->prefix . 'vo_results';
    $schools_table = $wpdb->prefix . 'vo_schools';
    $participants_table = $wpdb->prefix . 'vo_participants';

    $available_schools = $wpdb->get_results($wpdb->prepare("SELECT DISTINCT s.id, s.short_name FROM $schools_table s JOIN $participants_table p ON s.id = p.school_id JOIN $results_table r ON p.id = r.participant_id WHERE r.academic_year = %d AND r.subject_id = %d ORDER BY s.short_name", $academic_year, $subject_id));
    $available_classes = $wpdb->get_col($wpdb->prepare("SELECT DISTINCT r.class_grade FROM $results_table r WHERE r.academic_year = %d AND r.subject_id = %d ORDER BY r.class_grade", $academic_year, $subject_id));

    // --- Блок 4: Формирование и выполнение основного SQL-запроса ---
    $where_clauses = ["r.subject_id = %d"];
    $query_params = [$subject_id];

    if ($selected_school_id) { $where_clauses[] = "p.school_id = %d"; $query_params[] = $selected_school_id; }
    if ($selected_class) { $where_clauses[] = "r.class_grade = %d"; $query_params[] = $selected_class; }

    $where_sql = implode(' AND ', $where_clauses);
    $results_data = [];

    if ($current_stage_slug && in_array($current_stage_slug, ['district_prelim', 'district_final', 'regional_stage'])) {
        $query_params_district = array_merge([$academic_year], $query_params);
        $where_sql_district = $where_sql . " AND r.score != '' AND r.score IS NOT NULL";
        $results_data = $wpdb->get_results($wpdb->prepare("SELECT p.last_name, p.first_name, r.class_grade, s.short_name, r.school_level_score, r.score, r.status, r.is_regional_qualifier FROM $results_table r JOIN $participants_table p ON r.participant_id = p.id JOIN $schools_table s ON p.school_id = s.id WHERE r.academic_year = %d AND $where_sql_district ORDER BY r.class_grade ASC, CAST(r.score AS SIGNED) DESC", $query_params_district));
    } elseif ($current_stage_slug) {
        $final_results_map = [];

        $where_clauses_current = ["r.subject_id = %d"];
        $query_params_current = [$subject_id];
        if ($selected_school_id) { $where_clauses_current[] = "p.school_id = %d"; $query_params_current[] = $selected_school_id; }
        if ($selected_class) { $where_clauses_current[] = "r.class_grade = %d"; $query_params_current[] = $selected_class; }
        $where_sql_current = implode(' AND ', $where_clauses_current);

        $query_params_current_year = array_merge([$academic_year], $query_params_current);
        $current_year_results = $wpdb->get_results($wpdb->prepare("SELECT p.id as participant_id, p.last_name, p.first_name, p.patronymic, r.class_grade, s.short_name, r.school_level_score FROM $results_table r JOIN $participants_table p ON r.participant_id = p.id JOIN $schools_table s ON p.school_id = s.id WHERE r.academic_year = %d AND $where_sql_current", $query_params_current_year));

        foreach ($current_year_results as $row) {
            $row->score = $row->school_level_score;
            $row->ppg_status = '';
            $final_results_map[$row->participant_id] = $row;
        }

        $where_clauses_prev = ["r.subject_id = %d", "r.status IN ('Победитель', 'Призёр')", "r.class_grade < 11"];
        $query_params_prev = [$subject_id];
        if ($selected_school_id) { $where_clauses_prev[] = "p.school_id = %d"; $query_params_prev[] = $selected_school_id; }
        if ($selected_class) { $where_clauses_prev[] = "r.class_grade = %d"; $query_params_prev[] = $selected_class - 1; }
        // Не включаем выпускников прошлого года: участник должен ещё учиться в текущем учебном году.
        $where_clauses_prev[] = "p.graduation_year >= %d";
        $query_params_prev[] = $academic_year;
        $where_sql_prev = implode(' AND ', $where_clauses_prev);

        $query_params_prev_year = array_merge([$academic_year - 1], $query_params_prev);
        $prev_year_results = $wpdb->get_results($wpdb->prepare("SELECT p.id as participant_id, p.last_name, p.first_name, p.patronymic, (r.class_grade + 1) AS class_grade, s.short_name, r.status FROM $results_table r JOIN $participants_table p ON r.participant_id = p.id JOIN $schools_table s ON p.school_id = s.id WHERE r.academic_year = %d AND $where_sql_prev", $query_params_prev_year));

        foreach ($prev_year_results as $row) {
            if (isset($final_results_map[$row->participant_id])) {
                $final_results_map[$row->participant_id]->ppg_status = $row->status;
            } else {
                $row->score = 'ППГ';
                $row->school_level_score = '';
                $row->ppg_status = $row->status;
                $final_results_map[$row->participant_id] = $row;
            }
        }

        $results_data = array_values($final_results_map);
        usort($results_data, function($a, $b) {
            if ($a->class_grade != $b->class_grade) return $a->class_grade <=> $b->class_grade;
            $a_is_ppg = !empty($a->ppg_status);
            $b_is_ppg = !empty($b->ppg_status);
            if ($a_is_ppg != $b_is_ppg) return $a_is_ppg ? -1 : 1;
            return (int)$b->score <=> (int)$a->score;
        });
    }

    $results_by_class = [];
    foreach ($results_data as $row) { $results_by_class[$row->class_grade][] = $row; }

    // --- Проходной балл на районный этап по каждой параллели (минимум среди прошедших) ---
    // Считаем по полному набору данных предмета и года, невзирая на фильтры школы/класса.
    $class_cutoffs = [];
    if ($current_stage_slug === 'school_stage') {
        $cutoff_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT r.class_grade, MIN(CAST(r.school_level_score AS SIGNED)) AS min_score
             FROM $results_table r
             WHERE r.subject_id = %d AND r.academic_year = %d
               AND r.school_level_score != '' AND r.school_level_score IS NOT NULL
             GROUP BY r.class_grade",
            $subject_id, $academic_year
        ));
        foreach ($cutoff_rows as $cutoff_row) {
            $class_cutoffs[(int) $cutoff_row->class_grade] = (int) $cutoff_row->min_score;
        }
        ksort($class_cutoffs);
    }

    // --- Блок 5: Передача данных в шаблон ---
    $has_regional_qualifiers = false;
    if ($current_stage_slug === 'regional_stage') {
        $count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$results_table} WHERE academic_year = %d AND subject_id = %d AND is_regional_qualifier = 1", $academic_year, $subject_id));
        $has_regional_qualifiers = ($count > 0);
    }

    $template_data = [
        'subject_name' => $subject_name, 'academic_year' => $academic_year, 'current_stage' => $current_stage_slug,
        'results_by_class' => $results_by_class, 'has_regional_qualifiers' => $has_regional_qualifiers,
        'class_cutoffs' => $class_cutoffs,
        'filters' => [ 'schools' => $available_schools, 'classes' => $available_classes, 'selected_school' => $selected_school_id, 'selected_class' => $selected_class ]
    ];

    extract($template_data);
    include plugin_dir_path(__DIR__) . 'templates/results-display.php';

    $html_to_cache = ob_get_clean();
    set_transient($transient_name, $html_to_cache, VO_DATA_CACHE_TTL);
    return $html_to_cache;
}

// ============================================================
// Шорткод: Дашборд по предметам
// ============================================================

add_shortcode('vo_results_dashboard', 'vo_data_render_dashboard_shortcode');

function vo_data_render_dashboard_shortcode($atts = []) {
    $atts = shortcode_atts(['year' => ''], $atts, 'vo_results_dashboard');
    $academic_year = vo_data_resolve_academic_year($atts);

    $transient_name = 'vo_data_dashboard_cache_' . $academic_year;
    $cached_html = get_transient($transient_name);
    if (false !== $cached_html) {
        return $cached_html;
    }

    global $wpdb;

    $comp_status_table = $wpdb->prefix . 'vo_competition_status';
    $subjects_table = $wpdb->prefix . 'vo_subjects';
    $stage_types_table = $wpdb->prefix . 'vo_stage_types';
    $subjects_status = $wpdb->get_results($wpdb->prepare(
        "SELECT s.subject_name, s.page_id, st.name AS stage_name, st.slug AS stage_slug
         FROM $comp_status_table AS cs
         JOIN $subjects_table AS s ON cs.subject_id = s.id
         JOIN $stage_types_table AS st ON cs.current_stage_id = st.id
         WHERE cs.academic_year = %d AND cs.is_published = 1 ORDER BY s.subject_name ASC",
        $academic_year
    ));

    foreach ($subjects_status as $subject) {
        $subject->link = ($subject->page_id) ? get_permalink($subject->page_id) : '#';
        if ($subject->page_id && $academic_year !== vo_data_academic_year()) {
            $subject->link = add_query_arg(['vo_year' => $academic_year], $subject->link);
        }
    }

    $template_data = [ 'subjects' => $subjects_status, 'academic_year' => $academic_year ];

    ob_start();
    extract($template_data);
    include plugin_dir_path(__DIR__) . 'templates/dashboard-display.php';
    $html_to_cache = ob_get_clean();

    set_transient($transient_name, $html_to_cache, VO_DATA_CACHE_TTL);
    return $html_to_cache;
}
