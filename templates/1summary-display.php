<?php
// Файл: /templates/summary-display.php
// Шаблон сводки по итогам районного этапа.
if (!defined('ABSPATH')) exit;

$page_id  = get_the_ID();
$base_url = get_permalink($page_id);

// Сохраняем vo_year в URL, если он явно задан (не текущий)
$url_params_base = [];
if ($academic_year !== vo_data_academic_year()) {
    $url_params_base['vo_year'] = $academic_year;
}
?>

<?php include __DIR__ . '/summary-styles.php'; ?>

<div class="vo-summary-wrapper">
    <h2>Итоги районного этапа ВсОШ</h2>
    <h3><?php echo ($academic_year - 1) . '/' . $academic_year; ?> учебный год</h3>

    <!-- Карточки -->
    <div class="vo-summary-cards">
        <div class="vo-summary-card">
            <div class="vo-card-value"><?php echo $total_participants; ?></div>
            <div class="vo-card-label">участников</div>
        </div>
        <div class="vo-summary-card">
            <div class="vo-card-value"><?php echo $total_winners; ?></div>
            <div class="vo-card-label">победителей</div>
        </div>
        <div class="vo-summary-card">
            <div class="vo-card-value"><?php echo $total_prizers; ?></div>
            <div class="vo-card-label">призёров</div>
        </div>
        <div class="vo-summary-card">
            <div class="vo-card-value"><?php echo $result_rate; ?>%</div>
            <div class="vo-card-label">результативность</div>
        </div>
        <div class="vo-summary-card">
            <div class="vo-card-value"><?php echo $total_regional; ?></div>
            <div class="vo-card-label">на региональный</div>
        </div>
        <div class="vo-summary-card">
            <div class="vo-card-value"><?php echo $total_subjects; ?></div>
            <div class="vo-card-label">предметов</div>
        </div>
        <div class="vo-summary-card">
            <div class="vo-card-value"><?php echo $total_schools; ?></div>
            <div class="vo-card-label">школ</div>
        </div>
    </div>

    <!-- Вкладки -->
    <div class="vo-summary-tabs">
        <a class="vo-summary-tab <?php echo $active_tab === 'laureates' ? 'active' : ''; ?>"
           href="<?php echo esc_url(add_query_arg(array_merge($url_params_base, ['tab' => 'laureates']), $base_url)); ?>">Победители и призёры</a>
        <a class="vo-summary-tab <?php echo $active_tab === 'regional' ? 'active' : ''; ?>"
           href="<?php echo esc_url(add_query_arg(array_merge($url_params_base, ['tab' => 'regional']), $base_url)); ?>">На региональный</a>
        <a class="vo-summary-tab <?php echo $active_tab === 'by_subject' ? 'active' : ''; ?>"
           href="<?php echo esc_url(add_query_arg(array_merge($url_params_base, ['tab' => 'by_subject']), $base_url)); ?>">По предметам</a>
        <a class="vo-summary-tab <?php echo $active_tab === 'by_school' ? 'active' : ''; ?>"
           href="<?php echo esc_url(add_query_arg(array_merge($url_params_base, ['tab' => 'by_school']), $base_url)); ?>">По школам</a>
    </div>

    <?php // ================================================================
          // ВКЛАДКА: ПОБЕДИТЕЛИ И ПРИЗЁРЫ
          // ================================================================ ?>
    <?php if ($active_tab === 'laureates') : ?>

        <form method="get" action="<?php echo esc_url($base_url); ?>" class="vo-summary-filters">
            <?php foreach ($url_params_base as $k => $v) : ?>
                <input type="hidden" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr($v); ?>">
            <?php endforeach; ?>
            <input type="hidden" name="tab" value="laureates">

            <select name="subject">
                <option value="">Все предметы</option>
                <?php foreach ($available_subjects as $subj) : ?>
                    <option value="<?php echo esc_attr($subj->subject_name); ?>" <?php selected($selected_subject, $subj->subject_name); ?>>
                        <?php echo esc_html($subj->subject_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="school_id">
                <option value="">Все школы</option>
                <?php foreach ($available_schools as $sch) : ?>
                    <option value="<?php echo esc_attr($sch->id); ?>" <?php selected($selected_school, $sch->id); ?>>
                        <?php echo esc_html($sch->short_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Показать</button>
        </form>

        <?php if (empty($laureates)) : ?>
            <p>Нет победителей и призёров по заданным параметрам.</p>
        <?php else : ?>
            <div class="vo-summary-count">Найдено: <?php echo count($laureates); ?></div>

            <div class="vo-summary-table-wrap">
            <table class="vo-summary-table">
                <thead>
                    <tr>
                        <th class="rt">№</th>
                        <?php if (!$selected_subject) : ?><th>Предмет</th><?php endif; ?>
                        <th>Класс</th>
                        <th>Фамилия, имя</th>
                        <?php if (!$selected_school) : ?><th>Школа</th><?php endif; ?>
                        <th>Балл</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $n = 0;
                    $current_subject = '';
                    foreach ($laureates as $row) :
                        $n++;
                        $is_winner = ($row->status === 'Победитель');

                        // Группировка: разделитель при смене предмета
                        $show_subject_header = (!$selected_subject && $row->subject_name !== $current_subject);
                        $current_subject = $row->subject_name;

                        // Ссылка на страницу результатов предмета
                        $subject_link = $row->page_id ? get_permalink($row->page_id) : '';

                        // Colspan для заголовка группы
                        $colspan = 5; // №, Класс, ФИО, Балл, Статус
                        if (!$selected_school) $colspan++;
                    ?>
                        <?php if ($show_subject_header) : ?>
                            <tr class="vo-subject-group-header">
                                <td colspan="<?php echo $colspan; ?>">
                                    <?php if ($subject_link) : ?>
                                        <a href="<?php echo esc_url($subject_link); ?>"><?php echo esc_html($current_subject); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html($current_subject); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <tr>
                            <td class="rt"><?php echo $n; ?></td>
                            <?php if ($selected_subject) : ?>
                                <td>
                                    <?php if ($subject_link) : ?>
                                        <a href="<?php echo esc_url($subject_link); ?>"><?php echo esc_html($row->subject_name); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html($row->subject_name); ?>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="vo-text-center"><?php echo esc_html($row->class_grade); ?></td>
                            <td><?php echo esc_html($row->last_name . ' ' . $row->first_name); ?></td>
                            <?php if (!$selected_school) : ?>
                                <td><?php echo esc_html($row->school_name); ?></td>
                            <?php endif; ?>
                            <td class="vo-text-center"><?php echo esc_html($row->score); ?></td>
                            <td class="<?php echo $is_winner ? 'vo-status-winner' : ''; ?>">
                                <?php echo $is_winner ? '<strong>' . esc_html($row->status) . '</strong>' : esc_html($row->status); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

    <?php // ================================================================
          // ВКЛАДКА: НА РЕГИОНАЛЬНЫЙ
          // ================================================================ ?>
    <?php elseif ($active_tab === 'regional') : ?>

        <form method="get" action="<?php echo esc_url($base_url); ?>" class="vo-summary-filters">
            <?php foreach ($url_params_base as $k => $v) : ?>
                <input type="hidden" name="<?php echo esc_attr($k); ?>" value="<?php echo esc_attr($v); ?>">
            <?php endforeach; ?>
            <input type="hidden" name="tab" value="regional">

            <select name="subject">
                <option value="">Все предметы</option>
                <?php foreach ($available_subjects as $subj) : ?>
                    <option value="<?php echo esc_attr($subj->subject_name); ?>" <?php selected($selected_subject, $subj->subject_name); ?>>
                        <?php echo esc_html($subj->subject_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <select name="school_id">
                <option value="">Все школы</option>
                <?php foreach ($available_schools as $sch) : ?>
                    <option value="<?php echo esc_attr($sch->id); ?>" <?php selected($selected_school, $sch->id); ?>>
                        <?php echo esc_html($sch->short_name); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit">Показать</button>
        </form>

        <?php if (empty($regional_qualifiers)) : ?>
            <p>Нет участников, прошедших на региональный этап<?php
                if ($selected_subject) echo ' по предмету «' . esc_html($selected_subject) . '»';
                if ($selected_school) {
                    $school_name_display = '';
                    foreach ($available_schools as $sch) {
                        if ($sch->id == $selected_school) { $school_name_display = $sch->short_name; break; }
                    }
                    if ($school_name_display) echo ' от ' . esc_html($school_name_display);
                }
            ?>.</p>
        <?php else : ?>
            <div class="vo-summary-count">Прошли на региональный: <?php echo count($regional_qualifiers); ?></div>

            <div class="vo-summary-table-wrap">
            <table class="vo-summary-table">
                <thead>
                    <tr>
                        <th class="rt">№</th>
                        <?php if (!$selected_subject) : ?><th>Предмет</th><?php endif; ?>
                        <th>Класс</th>
                        <th>Фамилия, имя</th>
                        <?php if (!$selected_school) : ?><th>Школа</th><?php endif; ?>
                        <th>Балл</th>
                        <th>Статус</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $n = 0;
                    $current_subject = '';
                    foreach ($regional_qualifiers as $row) :
                        $n++;
                        $is_winner = ($row->status === 'Победитель');

                        $show_subject_header = (!$selected_subject && $row->subject_name !== $current_subject);
                        $current_subject = $row->subject_name;
                        $subject_link = $row->page_id ? get_permalink($row->page_id) : '';

                        $colspan = 5;
                        if (!$selected_school) $colspan++;
                    ?>
                        <?php if ($show_subject_header) : ?>
                            <tr class="vo-subject-group-header">
                                <td colspan="<?php echo $colspan; ?>">
                                    <?php if ($subject_link) : ?>
                                        <a href="<?php echo esc_url($subject_link); ?>"><?php echo esc_html($current_subject); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html($current_subject); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endif; ?>

                        <tr>
                            <td class="rt"><?php echo $n; ?></td>
                            <?php if ($selected_subject) : ?>
                                <td>
                                    <?php if ($subject_link) : ?>
                                        <a href="<?php echo esc_url($subject_link); ?>"><?php echo esc_html($row->subject_name); ?></a>
                                    <?php else : ?>
                                        <?php echo esc_html($row->subject_name); ?>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                            <td class="vo-text-center"><?php echo esc_html($row->class_grade); ?></td>
                            <td><?php echo esc_html($row->last_name . ' ' . $row->first_name); ?></td>
                            <?php if (!$selected_school) : ?>
                                <td><?php echo esc_html($row->school_name); ?></td>
                            <?php endif; ?>
                            <td class="vo-text-center"><?php echo esc_html($row->score); ?></td>
                            <td class="<?php echo $is_winner ? 'vo-status-winner' : ''; ?>">
                                <?php echo $is_winner ? '<strong>' . esc_html($row->status) . '</strong>' : esc_html($row->status); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php endif; ?>

    <?php // ================================================================
          // ВКЛАДКА: ПО ПРЕДМЕТАМ
          // ================================================================ ?>
    <?php elseif ($active_tab === 'by_subject') : ?>

        <?php if (empty($by_subject)) : ?>
            <p>Нет данных по предметам.</p>
        <?php else : ?>
            <div class="vo-summary-table-wrap">
            <table class="vo-summary-table">
                <thead>
                    <tr>
                        <th>Предмет</th>
                        <th class="vo-text-center">Участники</th>
                        <th class="vo-text-center">Победители</th>
                        <th class="vo-text-center">Призёры</th>
                        <th class="vo-text-center">Результативность</th>
                        <th class="vo-text-center">Средний балл</th>
                        <th class="vo-text-center">Проходной (призёр)</th>
                        <th class="vo-text-center">На РЭ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sum_part = $sum_win = $sum_priz = $sum_reg = 0;
                    foreach ($by_subject as $row) :
                        $subj_link = $row->page_id ? get_permalink($row->page_id) : '';
                        $rate = $row->participants > 0 ? round(($row->winners + $row->prizers) / $row->participants * 100, 1) : 0;
                        $sum_part += $row->participants;
                        $sum_win  += $row->winners;
                        $sum_priz += $row->prizers;
                        $sum_reg  += $row->regional;
                    ?>
                        <tr>
                            <td>
                                <?php if ($subj_link) : ?>
                                    <a href="<?php echo esc_url($subj_link); ?>"><?php echo esc_html($row->subject_name); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($row->subject_name); ?>
                                <?php endif; ?>
                            </td>
                            <td class="vo-text-center"><?php echo $row->participants; ?></td>
                            <td class="vo-text-center"><?php echo $row->winners; ?></td>
                            <td class="vo-text-center"><?php echo $row->prizers; ?></td>
                            <td class="vo-text-center"><?php echo $rate; ?>%</td>
                            <td class="vo-text-center"><?php echo $row->avg_score; ?></td>
                            <td class="vo-text-center"><?php echo $row->min_prizer_score !== null ? $row->min_prizer_score : '—'; ?></td>
                            <td class="vo-text-center"><?php echo $row->regional; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Итого</td>
                        <td class="vo-text-center"><?php echo $sum_part; ?></td>
                        <td class="vo-text-center"><?php echo $sum_win; ?></td>
                        <td class="vo-text-center"><?php echo $sum_priz; ?></td>
                        <td class="vo-text-center"><?php echo $sum_part > 0 ? round(($sum_win + $sum_priz) / $sum_part * 100, 1) : 0; ?>%</td>
                        <td class="vo-text-center">—</td>
                        <td class="vo-text-center">—</td>
                        <td class="vo-text-center"><?php echo $sum_reg; ?></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        <?php endif; ?>

    <?php // ================================================================
          // ВКЛАДКА: ПО ШКОЛАМ
          // ================================================================ ?>
    <?php elseif ($active_tab === 'by_school') : ?>

        <?php if (empty($by_school)) : ?>
            <p>Нет данных по школам.</p>
        <?php else : ?>
            <div class="vo-summary-table-wrap">
            <table class="vo-summary-table">
                <thead>
                    <tr>
                        <th>Школа</th>
                        <th class="vo-text-center">Участники</th>
                        <th class="vo-text-center">Предметов</th>
                        <th class="vo-text-center">Победители</th>
                        <th class="vo-text-center">Призёры</th>
                        <th class="vo-text-center">Результативность</th>
                        <th class="vo-text-center">На РЭ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $sum_part = $sum_win = $sum_priz = $sum_reg = 0;
                    foreach ($by_school as $row) :
                        $rate = $row->participants > 0 ? round(($row->winners + $row->prizers) / $row->participants * 100, 1) : 0;
                        $sum_part += $row->participants;
                        $sum_win  += $row->winners;
                        $sum_priz += $row->prizers;
                        $sum_reg  += $row->regional;

                        // Ссылка: отфильтровать лауреатов по этой школе
                        $school_url = add_query_arg(array_merge($url_params_base, ['tab' => 'laureates', 'school_id' => $row->school_id]), $base_url);
                    ?>
                        <tr>
                            <td><a href="<?php echo esc_url($school_url); ?>"><?php echo esc_html($row->short_name); ?></a></td>
                            <td class="vo-text-center"><?php echo $row->participants; ?></td>
                            <td class="vo-text-center"><?php echo $row->subjects_count; ?></td>
                            <td class="vo-text-center"><?php echo $row->winners; ?></td>
                            <td class="vo-text-center"><?php echo $row->prizers; ?></td>
                            <td class="vo-text-center"><?php echo $rate; ?>%</td>
                            <td class="vo-text-center"><?php echo $row->regional; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td>Итого</td>
                        <td class="vo-text-center"><?php echo $sum_part; ?></td>
                        <td class="vo-text-center">—</td>
                        <td class="vo-text-center"><?php echo $sum_win; ?></td>
                        <td class="vo-text-center"><?php echo $sum_priz; ?></td>
                        <td class="vo-text-center"><?php echo $sum_part > 0 ? round(($sum_win + $sum_priz) / $sum_part * 100, 1) : 0; ?>%</td>
                        <td class="vo-text-center"><?php echo $sum_reg; ?></td>
                    </tr>
                </tfoot>
            </table>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
