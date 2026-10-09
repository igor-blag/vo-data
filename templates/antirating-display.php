<?php
// Файл: /templates/antirating-display.php
// Шаблон отображения индекса объективности школьного этапа (чистая разметка).
if (!defined('ABSPATH')) exit;

$page_id  = get_the_ID();
$base_url = get_permalink($page_id);

// Базовые URL-параметры: сохраняем выбранный период наблюдения
$url_params_base = [];
if ($is_cumulative) {
    $url_params_base['period'] = 'all';
    $url_params_base['vo_year'] = '';
} elseif ($academic_year !== vo_data_academic_year()) {
    $url_params_base['vo_year'] = $academic_year;
}

// Уровни точности
$precision_levels = [
    1  => ['label' => 'Очень грубо', 'desc' => 'Все школы, включая те, где данных совсем мало. Высокая вероятность случайных отклонений.'],
    5  => ['label' => 'Грубо',       'desc' => 'Показаны школы с 5+ выступлениями. Результат приблизительный, но основные тенденции видны.'],
    10 => ['label' => 'Нормально',   'desc' => 'Школы с 10+ выступлениями. Достаточная выборка для большинства выводов.'],
    20 => ['label' => 'Точно',       'desc' => 'Только школы с 20+ выступлениями. Максимально надёжный результат.'],
];

// Вспомогательные функции цвета (с защитой от повторного объявления)
if (!function_exists('vo_antirating_color_class')) {
    function vo_antirating_color_class($index) {
        if ($index >= 80) return 'vo-index-green';
        if ($index >= 50) return 'vo-index-yellow';
        return 'vo-index-red';
    }
}
if (!function_exists('vo_antirating_idx_color')) {
    function vo_antirating_idx_color($index) {
        if ($index >= 80) return '#2e7d32';
        if ($index >= 50) return '#f57f17';
        return '#c62828';
    }
}
?>

<?php include __DIR__ . '/antirating-styles.php'; ?>

<div class="vo-antirating-wrapper">
    <h2>Индекс объективности школьного этапа</h2>

    <?php if ($is_cumulative) :
        $py_labels = [];
        foreach ($period_years as $py) { $py_labels[] = ($py - 1) . '/' . $py; }
    ?>
        <h3>За весь период наблюдения</h3>
        <p class="vo-meta">Накопительный индекс по всем годам наблюдения<?php if (!empty($py_labels)) echo ': ' . implode(', ', $py_labels); ?>.
        Ранги считаются отдельно в каждом учебном году и классе, поэтому результаты разных лет сопоставимы: чем больше лет, тем надёжнее выводы.</p>
    <?php else : ?>
        <h3>Данные за <?php echo ($academic_year - 1) . '/' . $academic_year; ?> учебный год</h3>
    <?php endif; ?>

    <?php if (!empty($available_years)) : ?>
    <?php $period_chip_args = array_merge($url_params_base, ['detail_school' => '', 'detail_subject' => '']); ?>
    <div class="vo-period-bar">
        <span class="vo-period-label">Период:</span>
        <a class="vo-period-btn<?php echo $is_cumulative ? ' active' : ''; ?>"
           href="<?php echo esc_url(add_query_arg(array_merge($period_chip_args, ['period' => 'all', 'vo_year' => '']), $base_url)); ?>">Весь период (<?php echo count($available_years); ?>)</a>
        <?php foreach ($available_years as $ay) : ?>
            <a class="vo-period-btn<?php echo (!$is_cumulative && $academic_year === $ay) ? ' active' : ''; ?>"
               href="<?php echo esc_url(add_query_arg(array_merge($period_chip_args, ['period' => '', 'vo_year' => $ay]), $base_url)); ?>"><?php echo ($ay - 1) . '/' . $ay; ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Вкладки -->
    <div class="vo-tabs">
        <a class="vo-tab-btn <?php echo $active_tab === 'overview' ? 'active' : ''; ?>"
           href="<?php echo esc_url(add_query_arg(array_merge($url_params_base, ['tab' => 'overview']), $base_url)); ?>">Общая информация</a>
        <a class="vo-tab-btn <?php echo $active_tab === 'methodist' ? 'active' : ''; ?>"
           href="<?php echo esc_url(add_query_arg(array_merge($url_params_base, ['tab' => 'methodist']), $base_url)); ?>">Методистам</a>
        <a class="vo-tab-btn <?php echo $active_tab === 'admin' ? 'active' : ''; ?>"
           href="<?php echo esc_url(add_query_arg(array_merge($url_params_base, ['tab' => 'admin']), $base_url)); ?>">Руководителям</a>
    </div>

    <?php if (empty($school_ratings)) : ?>
        <p>Нет данных для отображения.</p>
    <?php else : ?>

    <?php // ================================================================
          // ВКЛАДКА: ОБЩАЯ ИНФОРМАЦИЯ
          // ================================================================ ?>
    <?php if ($active_tab === 'overview') : ?>

        <div class="vo-tab-instruction">
            <p>Тепловая карта показывает индекс объективности для каждой комбинации школа/предмет. Щёлкните по ячейке для подробностей и рекомендаций. Названия предметов и школ — ссылки на соответствующие разделы.</p>
        </div>

        <?php if (!empty($heatmap_data) && count($heatmap_subjects) > 1) : ?>
        <div class="vo-heatmap-section">
            <div class="vo-heatmap-wrapper">
                <table class="vo-heatmap-table">
                    <thead>
                        <tr>
                            <th>Школа</th>
                            <?php foreach ($heatmap_subjects as $subj_name) :
                                $subj_tab_url = esc_url(add_query_arg(array_merge($url_params_base, ['tab' => 'methodist', 'subject' => $subj_name]), $base_url));
                            ?>
                                <th><a href="<?php echo $subj_tab_url; ?>" class="vo-heatmap-link" title="<?php echo esc_attr($subj_name); ?>"><?php echo esc_html(mb_substr($subj_name, 0, 4)); ?></a></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($heatmap_data as $row) :
                            $show = false;
                            foreach ($school_ratings as $sr) {
                                if ($sr['school_id'] == $row['school_id'] && $sr['counted_entries'] >= $min_participants) { $show = true; break; }
                            }
                            if (!$show) continue;
                            $school_tab_url = esc_url(add_query_arg(array_merge($url_params_base, ['tab' => 'admin', 'school_id' => $row['school_id']]), $base_url));
                        ?>
                        <tr>
                            <td><a href="<?php echo $school_tab_url; ?>" class="vo-heatmap-link"><?php echo esc_html($row['school_name']); ?></a></td>
                            <?php foreach ($heatmap_subjects as $subj_name) :
                                if (isset($row['subjects'][$subj_name])) :
                                    $cell = $row['subjects'][$subj_name];
                                    $idx  = $cell['index'];
                                    if ($idx >= 80) $bg = 'rgba(46,125,50,' . (0.1 + (100 - $idx) * 0.01) . ')';
                                    elseif ($idx >= 50) $bg = 'rgba(245,127,23,' . (0.1 + (80 - $idx) * 0.015) . ')';
                                    else $bg = 'rgba(198,40,40,' . (0.15 + (50 - $idx) * 0.015) . ')';
                                    $border   = ($cell['weak_count'] >= 5) ? 'border: 2px solid #c62828;' : '';
                                    $cell_url = esc_url(add_query_arg(array_merge($url_params_base, [
                                        'tab' => 'overview', 'min_participants' => $min_participants,
                                        'detail_school' => $row['school_id'], 'detail_subject' => $subj_name,
                                    ]), $base_url)) . '#vo-detail';
                                ?>
                                    <td class="vo-heatmap-cell" style="background:<?php echo $bg; ?>;<?php echo $border; ?>"><a href="<?php echo $cell_url; ?>" title="<?php echo esc_attr($subj_name); ?>: подробнее"><?php echo $idx; ?></a></td>
                                <?php else : ?>
                                    <td style="color:#ccc;">&mdash;</td>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="font-size:0.8em; color:#666; margin-top:8px;">
                Цвет — индекс объективности. Толстая рамка — 5+ слабых участников. Щёлкните по числу для подробностей.
            </div>
        </div>

        <?php // --- Детальная панель --- ?>
        <?php if ($detail_school > 0 && !empty($detail_subject)) :
            $det_rating = $det_subj_data = null;
            foreach ($school_ratings as $sr) {
                if ($sr['school_id'] == $detail_school) {
                    $det_rating = $sr;
                    foreach ($sr['subjects'] as $sd) { if ($sd['name'] === $detail_subject) { $det_subj_data = $sd; break; } }
                    break;
                }
            }
            if ($det_rating && $det_subj_data) :
                $rec = vo_antirating_recommendations($det_subj_data['index'], $det_subj_data['load_index'], $det_subj_data['weak_count'], 'detail');
                $close_url    = esc_url(add_query_arg(array_merge($url_params_base, ['tab' => 'overview', 'min_participants' => $min_participants, 'detail_school' => '', 'detail_subject' => '']), $base_url));
                $results_link = !empty($det_subj_data['link']) ? esc_url(add_query_arg(array_merge($url_params_base, ['school_id' => $detail_school, 'show_school_score' => 1]), $det_subj_data['link'])) : '';
        ?>
            <div class="vo-detail-panel" id="vo-detail">
                <a href="<?php echo $close_url; ?>" class="vo-detail-close" title="Закрыть">&times;</a>
                <h4><?php echo esc_html($det_rating['school_name']); ?> — <?php echo esc_html($detail_subject); ?></h4>
                <div class="vo-detail-metrics">
                    <div class="vo-detail-metric">
                        <div class="vo-detail-metric-value" style="color:<?php echo vo_antirating_idx_color($det_subj_data['index']); ?>;"><?php echo $det_subj_data['index']; ?></div>
                        <div class="vo-detail-metric-label">Индекс объективности</div>
                    </div>
                    <div class="vo-detail-metric">
                        <div class="vo-detail-metric-value"><?php echo $det_subj_data['load_index']; ?>%</div>
                        <div class="vo-detail-metric-label">Нагрузка слабыми</div>
                    </div>
                    <div class="vo-detail-metric">
                        <div class="vo-detail-metric-value"><?php echo $det_subj_data['weak_count']; ?></div>
                        <div class="vo-detail-metric-label">Слабых участников</div>
                    </div>
                    <div class="vo-detail-metric">
                        <div class="vo-detail-metric-value"><?php echo $det_subj_data['participants']; ?></div>
                        <div class="vo-detail-metric-label">Учтено в расчёте</div>
                    </div>
                </div>
                <span class="vo-rec-level vo-rec-<?php echo $rec['level']; ?>"><?php echo esc_html($rec['summary']); ?></span>
                <?php if (!empty($rec['actions'])) : ?>
                    <ul class="vo-rec-actions"><?php foreach ($rec['actions'] as $a) : ?><li><?php echo esc_html($a); ?></li><?php endforeach; ?></ul>
                <?php endif; ?>
                <?php if (!empty($rec['load_note'])) : ?>
                    <div class="vo-rec-load-note"><?php echo esc_html($rec['load_note']); ?></div>
                <?php endif; ?>
                <?php if ($is_cumulative && !empty($det_subj_data['years'])) : ?>
                    <div class="vo-years-block">
                        <h5>Динамика по годам — <?php echo esc_html($detail_subject); ?></h5>
                        <table class="vo-years-table">
                            <thead><tr><th>Учебный год</th><th>Индекс</th><th>Учтено</th></tr></thead>
                            <tbody>
                            <?php
                            $low_subj_years = 0;
                            foreach ($det_subj_data['years'] as $yr) {
                                if ($yr['index'] < 50) $low_subj_years++;
                                echo '<tr><td>' . esc_html(($yr['year'] - 1) . '/' . $yr['year']) . '</td>'
                                   . '<td class="vo-idx-cell" style="color:' . vo_antirating_idx_color($yr['index']) . ';">' . $yr['index'] . '</td>'
                                   . '<td>' . $yr['counted'] . '</td></tr>';
                            }
                            ?>
                            </tbody>
                        </table>
                        <?php if ($low_subj_years >= 2) : ?>
                            <p class="vo-years-signal">Индекс ниже 50 повторяется: <?php echo $low_subj_years; ?> из <?php echo count($det_subj_data['years']); ?> лет наблюдения — устойчивый сигнал.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if ($results_link) : ?>
                    <p style="margin-top:12px;"><a href="<?php echo $results_link; ?>" class="vo-subject-link">Посмотреть результаты участников &rarr;</a></p>
                <?php endif; ?>
            </div>
        <?php endif; endif; ?>

        <?php endif; // heatmap_data ?>

        <!-- Кнопки точности -->
        <div class="vo-precision-wrap">
            <div class="vo-precision-buttons">
                <?php foreach ($precision_levels as $val => $info) :
                    $prec_url = esc_url(add_query_arg(array_merge($url_params_base, ['tab' => 'overview', 'min_participants' => $val, 'detail_school' => '', 'detail_subject' => '']), $base_url));
                ?>
                    <a class="vo-precision-btn <?php echo $min_participants == $val ? 'active' : ''; ?>" href="<?php echo $prec_url; ?>"><?php echo esc_html($info['label']); ?></a>
                <?php endforeach; ?>
            </div>
            <div class="vo-precision-desc"><?php echo esc_html($precision_levels[$min_participants]['desc'] ?? ''); ?></div>
        </div>


    <?php // ================================================================
          // ВКЛАДКА: МЕТОДИСТАМ
          // ================================================================ ?>
    <?php elseif ($active_tab === 'methodist') : ?>

        <?php if (empty($selected_subject)) : ?>
            <div class="vo-tab-instruction">
                <p>Выберите ваш предмет из списка. Вы увидите, как каждая школа справляется с подготовкой участников именно по вашему направлению: где результаты школьного этапа подтверждаются на районном, а где есть повод для разговора.</p>
            </div>
        <?php endif; ?>

        <div class="vo-antirating-filters">
            <form class="filter-row" method="get" action="">
                <input type="hidden" name="page_id" value="<?php echo $page_id; ?>">
                <input type="hidden" name="tab" value="methodist">
                <?php foreach ($url_params_base as $k => $v) : if ($v !== '') : ?>
                    <input type="hidden" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr($v); ?>">
                <?php endif; endforeach; ?>
                <select name="subject">
                    <option value="">Выберите предмет</option>
                    <?php foreach ($available_subjects as $subj) : ?>
                        <option value="<?php echo esc_attr($subj->subject_name); ?>" <?php selected($selected_subject, $subj->subject_name); ?>><?php echo esc_html($subj->subject_name); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Показать</button>
            </form>
        </div>

        <?php if (!empty($selected_subject)) : ?>
            <?php
            $methodist_ratings = [];
            foreach ($school_ratings as $r) {
                foreach ($r['subjects'] as $sd) {
                    if ($sd['name'] === $selected_subject) {
                        $methodist_ratings[] = [
                            'school_id'    => $r['school_id'],
                            'school_name'  => $r['school_name'],
                            'index'        => $sd['index'],
                            'load_index'   => $sd['load_index'],
                            'weak_count'   => $sd['weak_count'],
                            'participants' => $sd['participants'],
                            'link'         => $sd['link'],
                        ];
                        break;
                    }
                }
            }
            usort($methodist_ratings, function($a, $b) { return $a['index'] <=> $b['index']; });
            $attention_schools = array_filter($methodist_ratings, function($r) { return $r['index'] < 50; });
            ?>

            <div class="vo-tab-instruction">
                <p><strong><?php echo esc_html($selected_subject); ?></strong> — результаты по школам.</p>
                <p><strong>Индекс</strong> — совпадение позиций ШЭ и РЭ (0–100, чем выше тем лучше). <strong>Нагрузка</strong> — доля участников из нижних 25% на РЭ. <strong>Слабых</strong> — их абсолютное число от школы. <strong>Учтено</strong> — число выступлений, вошедших в расчёт.</p>
            </div>

            <?php if (empty($methodist_ratings)) : ?>
                <p>Нет данных по выбранному предмету.</p>
            <?php else : ?>
                <table class="vo-results-table">
                    <thead><tr>
                        <th>Школа</th>
                        <th class="vo-num-col">Индекс</th>
                        <th class="vo-num-col">Нагрузка</th>
                        <th class="vo-num-col">Слабых</th>
                        <th class="vo-num-col">Учтено</th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($methodist_ratings as $mr) :
                            $has_link    = !empty($mr['link']);
                            $results_url = $has_link ? esc_url(add_query_arg(array_merge($url_params_base, ['school_id' => $mr['school_id'], 'show_school_score' => 1]), $mr['link'])) : '';
                        ?>
                        <tr>
                            <td><?php echo esc_html($mr['school_name']); ?></td>
                            <td class="vo-num-col vo-idx-cell" style="color:<?php echo vo_antirating_idx_color($mr['index']); ?>;"><?php echo $mr['index']; ?></td>
                            <td class="vo-num-col <?php echo $mr['load_index'] > 15 ? 'vo-load-high' : ''; ?>"><?php echo $mr['load_index']; ?>%</td>
                            <td class="vo-num-col <?php echo $mr['weak_count'] >= 5 ? 'vo-load-high' : ''; ?>"><?php echo $mr['weak_count']; ?></td>
                            <td class="vo-num-col"><?php echo $mr['participants']; ?></td>
                            <td><?php if ($has_link) : ?><a href="<?php echo $results_url; ?>" class="vo-subject-link">результаты</a><?php endif; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (!empty($attention_schools)) : ?>
                <div class="vo-attention-section">
                    <h4>Школы, требующие внимания по предмету &laquo;<?php echo esc_html($selected_subject); ?>&raquo;</h4>
                    <p style="font-size:0.9em; margin-bottom:12px;">Индекс объективности ниже 50 — результаты ШЭ систематически не подтверждаются на районном:</p>
                    <ul>
                        <?php foreach ($attention_schools as $as) : ?>
                            <li><strong><?php echo esc_html($as['school_name']); ?></strong> (индекс <?php echo $as['index']; ?><?php if ($as['weak_count'] >= 5) echo ', высокая нагрузка слабыми'; ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="font-size:0.9em; margin-top:12px;"><strong>Рекомендуемые действия:</strong></p>
                    <ul>
                        <li>Обсудить ситуацию на районном методическом объединении (РМО) по предмету.</li>
                        <li>Связаться с ответственными за проведение школьного этапа в указанных школах.</li>
                        <li>При систематическом повторении — рекомендовать направление наблюдателя на ШЭ.</li>
                        <li>Рассмотреть пересмотр критериев отбора при высокой нагрузке слабыми участниками.</li>
                    </ul>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        <?php else : ?>
            <p style="color:#666;">Выберите предмет из списка выше, чтобы увидеть анализ.</p>
        <?php endif; ?>


    <?php // ================================================================
          // ВКЛАДКА: РУКОВОДИТЕЛЯМ
          // ================================================================ ?>
    <?php elseif ($active_tab === 'admin') : ?>

        <?php if (empty($selected_school_id)) : ?>
            <div class="vo-tab-instruction">
                <p>Выберите ваше учреждение. Вы увидите, по каким предметам результаты школьного этапа подтверждаются на районном, а где есть расхождения. Это поможет выстроить диалог с педагогами-предметниками.</p>
            </div>
        <?php endif; ?>

        <div class="vo-antirating-filters">
            <form class="filter-row" method="get" action="">
                <input type="hidden" name="page_id" value="<?php echo $page_id; ?>">
                <input type="hidden" name="tab" value="admin">
                <?php foreach ($url_params_base as $k => $v) : if ($v !== '') : ?>
                    <input type="hidden" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr($v); ?>">
                <?php endif; endforeach; ?>
                <select name="school_id">
                    <option value="">Выберите учреждение</option>
                    <?php foreach ($available_schools as $sch) : ?>
                        <option value="<?php echo $sch['id']; ?>" <?php selected($selected_school_id, $sch['id']); ?>><?php echo esc_html($sch['name']); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Показать</button>
            </form>
        </div>

        <?php if ($selected_school_id > 0) :
            $selected_rating = null;
            foreach ($school_ratings as $r) { if ($r['school_id'] == $selected_school_id) { $selected_rating = $r; break; } }
        ?>
            <?php if ($selected_rating) :
                $attention_subjects = array_filter($selected_rating['subjects'], function($s) { return $s['index'] < 50; });
            ?>

                <div class="vo-tab-instruction">
                    <p><strong><?php echo esc_html($selected_rating['school_name']); ?></strong> — общий индекс: <strong style="color:<?php echo vo_antirating_idx_color($selected_rating['index']); ?>;"><?php echo $selected_rating['index']; ?></strong>
                    (выступлений: <?php echo $selected_rating['total_entries']; ?>, учтено: <?php echo $selected_rating['counted_entries']; ?>, участников: <?php echo $selected_rating['unique_students']; ?>).</p>
                    <p><strong>Индекс</strong> — совпадение позиций ШЭ и РЭ (0–100). <strong>Нагрузка</strong> — доля слабых (нижние 25% на РЭ). <strong>Слабых</strong> — их число.</p>
                </div>

                <?php if ($is_cumulative && !empty($selected_rating['years'])) : ?>
                    <div class="vo-years-block">
                        <h5>Динамика по годам — <?php echo esc_html($selected_rating['school_name']); ?></h5>
                        <table class="vo-years-table">
                            <thead><tr><th>Учебный год</th><th>Индекс</th><th>Учтено</th></tr></thead>
                            <tbody>
                            <?php
                            $low_school_years = 0;
                            foreach ($selected_rating['years'] as $yr) {
                                if ($yr['index'] < 50) $low_school_years++;
                                echo '<tr><td>' . esc_html(($yr['year'] - 1) . '/' . $yr['year']) . '</td>'
                                   . '<td class="vo-idx-cell" style="color:' . vo_antirating_idx_color($yr['index']) . ';">' . $yr['index'] . '</td>'
                                   . '<td>' . $yr['counted'] . '</td></tr>';
                            }
                            ?>
                            </tbody>
                        </table>
                        <?php if ($low_school_years >= 2) : ?>
                            <p class="vo-years-signal">Индекс ниже 50 повторяется: <?php echo $low_school_years; ?> из <?php echo count($selected_rating['years']); ?> лет наблюдения — устойчивый сигнал, стоит разбираться системно.</p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($selected_rating['subjects'])) : ?>
                <table class="vo-results-table">
                    <thead><tr>
                        <th>Предмет</th>
                        <th class="vo-num-col">Индекс</th>
                        <th class="vo-num-col">Нагрузка</th>
                        <th class="vo-num-col">Слабых</th>
                        <th class="vo-num-col">Учтено</th>
                        <th></th>
                    </tr></thead>
                    <tbody>
                        <?php foreach ($selected_rating['subjects'] as $subj) :
                            $has_link = !empty($subj['link']);
                            $subj_url = $has_link ? esc_url(add_query_arg(array_merge($url_params_base, ['school_id' => $selected_rating['school_id'], 'show_school_score' => 1]), $subj['link'])) : '';
                        ?>
                        <tr>
                            <td><?php if ($has_link) : ?><a href="<?php echo $subj_url; ?>" class="vo-subject-link"><?php echo esc_html($subj['name']); ?></a><?php else : ?><?php echo esc_html($subj['name']); ?><?php endif; ?></td>
                            <td class="vo-num-col vo-idx-cell" style="color:<?php echo vo_antirating_idx_color($subj['index']); ?>;"><?php echo $subj['index']; ?></td>
                            <td class="vo-num-col <?php echo $subj['load_index'] > 15 ? 'vo-load-high' : ''; ?>"><?php echo $subj['load_index']; ?>%</td>
                            <td class="vo-num-col <?php echo $subj['weak_count'] >= 5 ? 'vo-load-high' : ''; ?>"><?php echo $subj['weak_count']; ?></td>
                            <td class="vo-num-col"><?php echo $subj['participants']; ?></td>
                            <td><?php if ($has_link) : ?><a href="<?php echo $subj_url; ?>" class="vo-subject-link">результаты</a><?php endif; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php if (!empty($attention_subjects)) : ?>
                <div class="vo-attention-section">
                    <h4>Предметы, требующие внимания</h4>
                    <p style="font-size:0.9em; margin-bottom:12px;">Индекс объективности ниже 50:</p>
                    <ul>
                        <?php foreach ($attention_subjects as $as) : ?>
                            <li><strong><?php echo esc_html($as['name']); ?></strong> (индекс <?php echo $as['index']; ?><?php if ($as['weak_count'] >= 5) echo ', слабых: ' . $as['weak_count']; ?>)</li>
                        <?php endforeach; ?>
                    </ul>
                    <p style="font-size:0.9em; margin-top:12px;"><strong>Рекомендуемые действия:</strong></p>
                    <ul>
                        <li>Обсудить с педагогом-предметником порядок проведения ШЭ: рассадку, контроль, соблюдение регламента.</li>
                        <li>Проверить, не совмещает ли педагог роли составителя заданий и организатора.</li>
                        <li>Обеспечить присутствие наблюдателя по проблемным предметам на следующем ШЭ.</li>
                        <li>Пересмотреть критерии отбора: направлять на РЭ участников с результатом существенно выше порогового.</li>
                    </ul>
                </div>
                <?php endif; ?>

                <?php if ($selected_rating['counted_entries'] < 10) : ?>
                <div class="vo-disclaimer" style="border-color:#f57f17; background:#fffbf5;">
                    По вашему учреждению учтено менее 10 выступлений. При малой выборке индекс может быть менее точным — не стоит делать далеко идущие выводы. Индекс станет надёжнее по мере накопления данных.
                </div>
                <?php endif; ?>

                <?php endif; ?>
            <?php else : ?>
                <p>Учреждение не найдено в расчёте. Возможно, недостаточно данных.</p>
            <?php endif; ?>
        <?php else : ?>
            <p style="color:#666;">Выберите учреждение из списка выше, чтобы увидеть анализ.</p>
        <?php endif; ?>

    <?php endif; // конец вкладок ?>

    <!-- Легенда -->
    <div class="vo-antirating-legend">
        <p><strong>Шкала индекса объективности:</strong></p>
        <div class="vo-legend-items">
            <div class="vo-legend-item"><span class="vo-legend-badge" style="background-color:#2e7d32;">80-100</span> результаты ШЭ подтверждаются на РЭ (объективно)</div>
            <div class="vo-legend-item"><span class="vo-legend-badge" style="background-color:#f57f17;">50-79</span> есть расхождения</div>
            <div class="vo-legend-item"><span class="vo-legend-badge" style="background-color:#c62828;">0-49</span> результаты ШЭ систематически не подтверждаются</div>
        </div>
    </div>

    <!-- Дисклеймер -->
    <div class="vo-disclaimer">
        <strong>Обратите внимание:</strong> индекс объективности — статистический инструмент, а не доказательство нарушений. Низкий индекс может объясняться различием условий проведения, стрессом учеников, особенностями подготовки. Данные — повод для конструктивного диалога, а не для санкций. Для школ с небольшим числом участников расхождения могут быть случайными.
    </div>

    <!-- Методика -->
    <details class="vo-antirating-methodology">
        <summary>Подробнее о методике расчёта</summary>
        <div class="vo-methodology-content">
            <h4>Что мы измеряем</h4>
            <p>Индекс объективности: <em>сохраняют ли ученики школы свои позиции при переходе от школьного этапа к районному?</em></p>

            <h4>Как считается</h4>
            <ol>
                <li><strong>Ранжирование</strong> участников группы (предмет + класс) по баллам ШЭ и РЭ.</li>
                <li><strong>Нормализация</strong> позиций к шкале 0–1.</li>
                <li><strong>Дельта</strong> = позиция ШЭ минус позиция РЭ.</li>
                <li><strong>Медиана</strong> дельт по школе (устойчива к выбросам).</li>
                <li><strong>Индекс</strong> 0–100. Выше 50 — на районном лучше, ниже — хуже.</li>
            </ol>

            <h4>Нагрузка слабыми</h4>
            <p>Слабый участник — балл в нижних 25%. Индекс нагрузки = доля слабых от школы среди всех. Сигнал: 5+ слабых от одной школы по одному предмету.</p>

            <h4>Ограничения</h4>
            <ul>
                <li>Группы менее 3 участников не учитываются.</li>
                <li>Нужны баллы за оба этапа.</li>
                <li>Сравниваются позиции, не абсолютные баллы.</li>
                <li>Низкий индекс — сигнал, не доказательство.</li>
            </ul>
        </div>
    </details>

    <?php endif; ?>
</div>
