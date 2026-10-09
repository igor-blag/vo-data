<?php
// Файл: /includes/vo-data-antirating.php
// Шорткод [vo_antirating] — Индекс объективности школьного этапа.
// Режимы: один учебный год (по умолчанию, задаётся year/атрибутом или GET) и
// "весь период" (period=all) — накопительный расчёт за все годы наблюдения.

if (!defined('ABSPATH')) exit;

add_shortcode('vo_antirating', 'vo_data_render_antirating_shortcode');

function vo_data_render_antirating_shortcode($atts) {
    $atts = shortcode_atts(['subject' => '', 'year' => '', 'period' => ''], $atts, 'vo_antirating');

    // Фильтры из GET-параметров
    $selected_subject_name = isset($_GET['subject']) ? sanitize_text_field($_GET['subject']) : sanitize_text_field($atts['subject']);
    $min_participants = isset($_GET['min_participants']) ? intval($_GET['min_participants']) : 10;
    if ($min_participants < 1) $min_participants = 1;
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    if (!in_array($active_tab, ['overview', 'methodist', 'admin'])) $active_tab = 'overview';
    $selected_school_id = isset($_GET['school_id']) ? intval($_GET['school_id']) : 0;
    $detail_school  = isset($_GET['detail_school'])  ? intval($_GET['detail_school']) : 0;
    $detail_subject = isset($_GET['detail_subject']) ? sanitize_text_field($_GET['detail_subject']) : '';

    // Режим наблюдения: один учебный год или весь накопленный период
    $period = isset($_GET['period']) ? sanitize_key($_GET['period']) : sanitize_key((string)$atts['period']);
    if ($period !== 'all') $period = '';
    $is_cumulative = ($period === 'all');
    $academic_year = vo_data_resolve_academic_year($atts);

    // --- Кэширование ---
    $cache_version  = vo_data_cache_version();
    $cache_key      = ($is_cumulative ? 'all' : 'y' . $academic_year) . '_' . $active_tab . '_' . $selected_subject_name . '_' . $min_participants . '_' . $selected_school_id . '_' . $detail_school . '_' . $detail_subject;
    $transient_name = 'vo_data_antirating_v' . $cache_version . '_' . md5($cache_key);
    $cached_html    = get_transient($transient_name);
    if (false !== $cached_html) {
        return $cached_html;
    }

    global $wpdb;
    $t = vo_data_table_names();

    // --- Ограничение по году (в режиме "весь период" годы не ограничиваем) ---
    $year_sql = '';
    $year_params = [];
    if (!$is_cumulative) {
        $year_sql = 'AND r.academic_year = %d';
        $year_params[] = $academic_year;
    }

    // --- Фильтр по предмету ---
    $subject_filter_sql = '';
    $subject_params = [];
    if (!empty($selected_subject_name)) {
        $subject_filter_sql = 'AND s_subj.subject_name = %s';
        $subject_params[] = $selected_subject_name;
    }

    // --- Список предметов с данными РЭ за период ---
    $available_subjects = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT s.id, s.subject_name, s.page_id
         FROM {$t['subjects']} s
         JOIN {$t['results']} r ON s.id = r.subject_id
         WHERE r.score != '' AND r.score IS NOT NULL
         $year_sql
         ORDER BY s.subject_name",
        $year_params
    ));

    // Карта ссылок: subject_name => URL
    $subject_links = [];
    foreach ($available_subjects as $subj) {
        if (!empty($subj->page_id)) {
            $subject_links[$subj->subject_name] = get_permalink($subj->page_id);
        }
    }

    // --- Годы, где есть пары ШЭ+РЭ (для переключателя период/год) ---
    $available_years = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT r.academic_year
         FROM {$t['results']} r
         JOIN {$t['subjects']} s_subj ON r.subject_id = s_subj.id
         WHERE r.score != '' AND r.score IS NOT NULL
           AND r.school_level_score != '' AND r.school_level_score IS NOT NULL
           AND CAST(r.score AS SIGNED) > 0
           AND CAST(r.school_level_score AS SIGNED) > 0
           $subject_filter_sql
         ORDER BY r.academic_year ASC",
        $subject_params
    ));
    $available_years = array_map('intval', $available_years);

    // --- Запрос 1: Все выступления на РЭ (баллы > 0) ---
    $all_district_data = $wpdb->get_results($wpdb->prepare(
        "SELECT r.academic_year AS ay, r.participant_id, p.school_id, s_subj.subject_name,
                r.subject_id, r.class_grade, CAST(r.score AS SIGNED) AS district_score
         FROM {$t['results']} r
         JOIN {$t['participants']} p ON r.participant_id = p.id
         JOIN {$t['subjects']} s_subj ON r.subject_id = s_subj.id
         WHERE r.score != '' AND r.score IS NOT NULL
           AND CAST(r.score AS SIGNED) > 0
           $year_sql
           $subject_filter_sql",
        array_merge($year_params, $subject_params)
    ));

    // Статистика по школам: выступления и уникальные участники за период
    $school_total_entries = [];
    $school_total_pids   = [];
    foreach ($all_district_data as $row) {
        $sid = $row->school_id;
        if (!isset($school_total_entries[$sid])) $school_total_entries[$sid] = 0;
        $school_total_entries[$sid]++;
        if (!isset($school_total_pids[$sid])) $school_total_pids[$sid] = [];
        $school_total_pids[$sid][$row->participant_id] = true;
    }

    // --- Индекс нагрузки слабыми (когорты предмет + класс + год) ---
    $district_groups = [];
    foreach ($all_district_data as $row) {
        $key = $row->subject_id . '_' . $row->class_grade . '_' . (int)$row->ay;
        $district_groups[$key][] = $row;
    }

    $school_load_by_subject = [];
    foreach ($district_groups as $group) {
        $n = count($group);
        if ($n < 4) continue;

        $scores = array_map(function($r) { return $r->district_score; }, $group);
        sort($scores);
        $threshold = $scores[(int)floor($n * 0.25)];
        $subj_name = $group[0]->subject_name;

        foreach ($group as $row) {
            $sid = $row->school_id;
            if (!isset($school_load_by_subject[$sid][$subj_name])) {
                $school_load_by_subject[$sid][$subj_name] = ['weak' => 0, 'total' => $n];
            }
            if ($row->district_score <= $threshold) {
                $school_load_by_subject[$sid][$subj_name]['weak']++;
            }
        }
    }

    // --- Запрос 2: Участники с обоими баллами > 0 ---
    $raw_data = $wpdb->get_results($wpdb->prepare(
        "SELECT r.academic_year AS ay, r.participant_id, p.school_id, sch.short_name AS school_name,
                s_subj.subject_name, r.subject_id, r.class_grade,
                CAST(r.school_level_score AS SIGNED) AS school_score,
                CAST(r.score AS SIGNED) AS district_score
         FROM {$t['results']} r
         JOIN {$t['participants']} p ON r.participant_id = p.id
         JOIN {$t['schools']} sch ON p.school_id = sch.id
         JOIN {$t['subjects']} s_subj ON r.subject_id = s_subj.id
         WHERE r.score != '' AND r.score IS NOT NULL
           AND r.school_level_score != '' AND r.school_level_score IS NOT NULL
           AND CAST(r.score AS SIGNED) > 0
           AND CAST(r.school_level_score AS SIGNED) > 0
           $year_sql
           $subject_filter_sql
         ORDER BY r.academic_year, r.subject_id, r.class_grade, CAST(r.school_level_score AS SIGNED) DESC",
        array_merge($year_params, $subject_params)
    ));

    // --- Группируем по предмет+класс+год ---
    // Ранги всегда считаются ВНУТРИ одного года и класса, чтобы годы были сопоставимы.
    $groups = [];
    foreach ($raw_data as $row) {
        $key = $row->subject_id . '_' . $row->class_grade . '_' . (int)$row->ay;
        $groups[$key][] = $row;
    }

    // --- Проверяем достаточность данных (с информативным сообщением) ---
    if (empty($raw_data)) {
        return vo_antirating_insufficient_data_message($raw_data, $groups, $all_district_data, $selected_subject_name);
    }

    // --- Расчёт дельт (накапливаем с разбивкой по годам) ---
    $year_deltas                = []; // ay => sid => [delta, ...]
    $year_subject_deltas        = []; // ay => sid => subj => [delta, ...]
    $year_school_participants   = []; // ay => sid => count
    $year_subject_participants  = []; // ay => sid => subj => count
    $school_names               = [];

    foreach ($groups as $participants) {
        $n = count($participants);
        if ($n < 3) continue;

        // Ранги ШЭ
        $school_ranks = [];
        $rank = 1;
        foreach ($participants as $i => $p) {
            if ($i > 0 && $p->school_score === $participants[$i - 1]->school_score) {
                $school_ranks[$p->participant_id] = $school_ranks[$participants[$i - 1]->participant_id];
            } else {
                $school_ranks[$p->participant_id] = $rank;
            }
            $rank++;
        }

        // Ранги РЭ
        $sorted_district = $participants;
        usort($sorted_district, function($a, $b) { return $b->district_score <=> $a->district_score; });

        $district_ranks = [];
        $rank = 1;
        foreach ($sorted_district as $i => $p) {
            if ($i > 0 && $p->district_score === $sorted_district[$i - 1]->district_score) {
                $district_ranks[$p->participant_id] = $district_ranks[$sorted_district[$i - 1]->participant_id];
            } else {
                $district_ranks[$p->participant_id] = $rank;
            }
            $rank++;
        }

        // Нормализованная дельта
        foreach ($participants as $p) {
            $pid  = $p->participant_id;
            $sid  = $p->school_id;
            $subj = $p->subject_name;
            $ay   = (int)$p->ay;

            $norm_school   = ($n - $school_ranks[$pid])   / max($n - 1, 1);
            $norm_district = ($n - $district_ranks[$pid]) / max($n - 1, 1);
            $delta = $norm_school - $norm_district;

            $school_names[$sid] = $p->school_name;

            $year_deltas[$ay][$sid][]       = $delta;
            $year_subject_deltas[$ay][$sid][$subj][] = $delta;

            if (!isset($year_school_participants[$ay][$sid])) $year_school_participants[$ay][$sid] = 0;
            $year_school_participants[$ay][$sid]++;
            if (!isset($year_subject_participants[$ay][$sid][$subj])) $year_subject_participants[$ay][$sid][$subj] = 0;
            $year_subject_participants[$ay][$sid][$subj]++;
        }
    }

    // --- Повторная проверка после фильтрации малых групп ---
    if (empty($year_deltas)) {
        return vo_antirating_insufficient_data_message($raw_data, $groups, $all_district_data, $selected_subject_name);
    }

    // --- Агрегация дельт за выбранный период (все годы в накопительном режиме) ---
    $school_deltas       = [];
    $subject_deltas      = [];
    $school_participants = [];
    $subject_participants = [];

    foreach ($year_deltas as $by_school) {
        foreach ($by_school as $sid => $deltas) {
            if (!isset($school_deltas[$sid])) $school_deltas[$sid] = [];
            $school_deltas[$sid] = array_merge($school_deltas[$sid], $deltas);
            $school_participants[$sid] = ($school_participants[$sid] ?? 0) + count($deltas);
        }
    }
    foreach ($year_subject_deltas as $by_school) {
        foreach ($by_school as $sid => $by_subj) {
            foreach ($by_subj as $subj => $deltas) {
                if (!isset($subject_deltas[$sid][$subj])) $subject_deltas[$sid][$subj] = [];
                $subject_deltas[$sid][$subj] = array_merge($subject_deltas[$sid][$subj], $deltas);
                $subject_participants[$sid][$subj] = ($subject_participants[$sid][$subj] ?? 0) + count($deltas);
            }
        }
    }

    // --- Динамика по годам (для детализации) ---
    $school_year_overview = [];   // sid => [ ['year','index','counted'], ... ]
    $subject_year_overview = [];  // sid => subj => [ ['year','index','counted'], ... ]

    foreach ($year_deltas as $ay => $by_school) {
        foreach ($by_school as $sid => $deltas) {
            if (count($deltas) < 3) continue;
            $school_year_overview[$sid][] = [
                'year'    => $ay,
                'index'   => max(0, min(100, round((1 - vo_data_median($deltas)) * 50))),
                'counted' => count($deltas),
            ];
        }
    }
    foreach ($year_subject_deltas as $ay => $by_school) {
        foreach ($by_school as $sid => $by_subj) {
            foreach ($by_subj as $subj => $deltas) {
                if (count($deltas) < 3) continue;
                $subject_year_overview[$sid][$subj][] = [
                    'year'    => $ay,
                    'index'   => max(0, min(100, round((1 - vo_data_median($deltas)) * 50))),
                    'counted' => count($deltas),
                ];
            }
        }
    }

    // Годы, реально вошедшие в расчёт (есть учтённые дельты)
    $period_years = array_keys($year_deltas);
    sort($period_years);

    // --- Индексы по школам ---
    $school_ratings    = [];
    $all_subject_names = [];

    foreach ($school_deltas as $sid => $deltas) {
        $median  = vo_data_median($deltas);
        $index   = max(0, min(100, round((1 - $median) * 50)));
        $counted = $school_participants[$sid];

        // Детализация по предметам
        $subjects_detail = [];
        if (isset($subject_deltas[$sid])) {
            foreach ($subject_deltas[$sid] as $subj_name => $subj_d) {
                $subj_median = vo_data_median($subj_d);
                $subj_index  = max(0, min(100, round((1 - $subj_median) * 50)));
                $subj_link   = $subject_links[$subj_name] ?? '';

                $load_index = 0;
                $weak_count = 0;
                if (isset($school_load_by_subject[$sid][$subj_name])) {
                    $ld = $school_load_by_subject[$sid][$subj_name];
                    $weak_count = $ld['weak'];
                    $load_index = ($ld['total'] > 0) ? round($ld['weak'] / $ld['total'] * 100) : 0;
                }

                $all_subject_names[$subj_name] = true;

                $subjects_detail[] = [
                    'name'         => $subj_name,
                    'index'        => $subj_index,
                    'load_index'   => $load_index,
                    'weak_count'   => $weak_count,
                    'participants' => $subject_participants[$sid][$subj_name],
                    'link'         => $subj_link,
                    'years'        => $subject_year_overview[$sid][$subj_name] ?? [],
                ];
            }
            usort($subjects_detail, function($a, $b) { return $a['index'] <=> $b['index']; });
        }

        // Нагрузка слабыми (агрегат)
        $load_indices = [];
        if (isset($school_load_by_subject[$sid])) {
            foreach ($school_load_by_subject[$sid] as $ld) {
                if ($ld['total'] > 0) $load_indices[] = $ld['weak'] / $ld['total'] * 100;
            }
        }
        $avg_load_index = !empty($load_indices) ? round(array_sum($load_indices) / count($load_indices)) : 0;

        $school_ratings[] = [
            'school_id'       => $sid,
            'school_name'     => $school_names[$sid],
            'index'           => $index,
            'load_index'      => $avg_load_index,
            'unique_students' => count($school_total_pids[$sid] ?? []),
            'total_entries'   => $school_total_entries[$sid] ?? 0,
            'counted_entries' => $counted,
            'subjects'        => $subjects_detail,
            'years'           => $school_year_overview[$sid] ?? [],
        ];
    }

    usort($school_ratings, function($a, $b) { return $a['index'] <=> $b['index']; });

    // --- Данные для тепловой матрицы ---
    $all_subject_names = array_keys($all_subject_names);
    sort($all_subject_names);

    $heatmap_data = [];
    foreach ($school_ratings as $rating) {
        if ($rating['counted_entries'] < 5) continue;
        $subj_map = [];
        foreach ($rating['subjects'] as $sd) {
            $subj_map[$sd['name']] = [
                'index'      => $sd['index'],
                'load_index' => $sd['load_index'],
                'weak_count' => $sd['weak_count'],
            ];
        }
        $heatmap_data[] = [
            'school_name' => $rating['school_name'],
            'school_id'   => $rating['school_id'],
            'subjects'    => $subj_map,
        ];
    }

    // Список школ для фильтра
    $available_schools = [];
    foreach ($school_ratings as $r) {
        $available_schools[] = ['id' => $r['school_id'], 'name' => $r['school_name']];
    }
    usort($available_schools, function($a, $b) { return strcmp($a['name'], $b['name']); });

    // --- Рендеринг шаблона ---
    $template_data = [
        'school_ratings'     => $school_ratings,
        'academic_year'      => $academic_year,
        'is_cumulative'      => $is_cumulative,
        'available_years'    => $available_years,
        'period_years'       => $period_years,
        'available_subjects' => $available_subjects,
        'available_schools'  => $available_schools,
        'selected_subject'   => $selected_subject_name,
        'selected_school_id' => $selected_school_id,
        'min_participants'   => $min_participants,
        'active_tab'         => $active_tab,
        'detail_school'      => $detail_school,
        'detail_subject'     => $detail_subject,
        'heatmap_data'       => $heatmap_data,
        'heatmap_subjects'   => $all_subject_names,
        'subject_links'      => $subject_links,
    ];

    ob_start();
    extract($template_data);
    include plugin_dir_path(__DIR__) . 'templates/antirating-display.php';
    $html_to_cache = ob_get_clean();

    set_transient($transient_name, $html_to_cache, VO_DATA_CACHE_TTL);
    return $html_to_cache;
}
