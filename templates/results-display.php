<?php
// Файл: /templates/results-display.php
if (!defined('ABSPATH')) exit;

// --- Блок 1: Определяем переменные для заголовков и видимости колонок ---
$page_title = "Список участников районного этапа";
$show_status_col = false;
$show_school_score_col = false;

if ($current_stage === 'district_final') {
    $page_title = "Итоги районного этапа";
    $show_status_col = true;
    $show_school_score_col = true;
} elseif ($current_stage === 'regional_stage') {
    if ($has_regional_qualifiers) {
        $page_title = "Итоги районного этапа.<br>Прошедшие на региональный этап 🎉";
    } else {
        $page_title = "Итоги районного этапа.<br>Прошедших на региональный этап нет 😢";
    }
    $show_status_col = true;
    $show_school_score_col = true;
} elseif ($current_stage === 'district_prelim') {
    $page_title = "Предварительные результаты<br>районного этапа";
    $show_school_score_col = true;
}

// Определяем, нужно ли показывать колонку школы (если фильтр не выбран)
$show_school_col = empty($filters['selected_school']);

// Принудительное отображение ШБ через GET-параметр (например, при переходе с антирейтинга)
$force_show_school_score = !empty($_GET['show_school_score']);

?>

<style>

select { font-size: 14px; line-height: 2; color: black; border: 1px solid black; border-radius:2px; box-shadow: none; padding: 0 24px 0 8px; min-height: 30px; max-width: 25rem; -webkit-appearance: none; background: #fff; cursor: pointer; vertical-align: middle; }
select:hover { color: #f36f03; }
select:focus { border-color: #000; color: #f36f03; }
button { display: inline-block; text-decoration: none; font-size: 16px; line-height: 2; min-height: 30px; margin: 0; padding: 0 10px; cursor: pointer; border: 1px solid black; border-radius:2px; -webkit-appearance: none; white-space: nowrap; box-sizing: border-box; background-color:#f36f0355; color:black;  }
button:hover { background-color:#f36f0322; color:black; }
.filter-row { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom:28px; }
.filter-row select, .filter-row button { flex:1; height: 42px; padding: 2px 12px; font-size: 16px; vertical-align: middle; box-sizing: border-box; }
.table-wrapper { overflow-x: auto; max-width: 100%; -webkit-overflow-scrolling: touch; }
.grade { border-bottom: 2px solid #000; margin: 25px 0 0 0; text-align: right; padding: 3px; }
.vo-results-table { width: 100%; border-collapse: collapse; border-spacing: 0; font-size: 14px; --vo-border-color: #000; --vo-border-thin: 1px; --vo-border-thick: 2px; }

/* Общие стили для всех ячеек (горизонтальная линия) */

.vo-results-table strong {font-weight: 500;}

.vo-results-table td, 
.vo-results-table th { 
    vertical-align:top;
    padding: 4px; 
    background-repeat: no-repeat;
    /* Только нижняя тонкая линия по умолчанию */
    background-image: linear-gradient(to right, var(--vo-border-color), var(--vo-border-color));
    background-size: 100% var(--vo-border-thin); 
    background-position: left bottom; 
}
.vo-results-table td:first-child, 
.vo-results-table th:first-child { 
    width:5%;
}
.vo-results-table td:nth-child(2), 
.vo-results-table th:nth-child(2) { 
    width:35%;
}
/* Вертикальные прерывающиеся линии для всех ячеек, кроме первой в ряду */
.vo-results-table td:not(:first-child), 
.vo-results-table th:not(:first-child) { 
    background-image: 
        /* Вертикальная линия */
        linear-gradient(to bottom, transparent 2px, var(--vo-border-color) 2px, var(--vo-border-color) calc(100% - 2px), transparent calc(100% - 2px)), 
        /* Горизонтальная линия (унаследована, но переопределяем для точности) */
        linear-gradient(to right, var(--vo-border-color), var(--vo-border-color));
    background-size: 
        var(--vo-border-thin) 100%, 
        100% var(--vo-border-thin); 
    background-position: 
        left top, 
        left bottom; 
}

/* Стили для всех ячеек заголовка */
.vo-results-table th {
    /* Делаем нижнюю границу толстой */
    background-size: 100% var(--vo-border-thick);
}

/* Переопределяем стили для ячеек заголовка, КРОМЕ ПЕРВОЙ */
.vo-results-table th:not(:first-child) {
    background-image: 
        /* ИЗМЕНЕНО ЗДЕСЬ: Увеличиваем нижний отступ до 3px */
        linear-gradient(to bottom, transparent 2px, var(--vo-border-color) 2px, var(--vo-border-color) calc(100% - 3px), transparent calc(100% - 3px)),
        linear-gradient(to right, var(--vo-border-color), var(--vo-border-color));
    /* Задаем толщину для вертикальной и горизонтальной линий */
    background-size: 
        var(--vo-border-thin) 100%, 
        100% var(--vo-border-thick);
}

/* Стили для всех ячеек последнего ряда */
.vo-results-table tr:last-child td {
    /* Делаем нижнюю границу толстой */
    background-size: 100% var(--vo-border-thick);
}

/* Переопределяем стили для ячеек последнего ряда, КРОМЕ ПЕРВОЙ */
.vo-results-table tr:last-child td:not(:first-child) {
    background-image: 
        /* ИЗМЕНЕНО ЗДЕСЬ: Аналогичное изменение для последней строки */
        linear-gradient(to bottom, transparent 2px, var(--vo-border-color) 2px, var(--vo-border-color) calc(100% - 3px), transparent calc(100% - 3px)),
        linear-gradient(to right, var(--vo-border-color), var(--vo-border-color));
    /* Задаем толщину для вертикальной и горизонтальной линий */
    background-size: 
        var(--vo-border-thin) 100%, 
        100% var(--vo-border-thick);
}

.rt { text-align: right; }
.school-score-col { display: none; }
.vo-results-wrapper.show-school-scores .school-score-col { display: table-cell; }

@media (min-width: 400px) {
.vo-results-table { font-size: 18px; }
}

</style>

<div class="vo-results-wrapper<?php if ($force_show_school_score) echo ' show-school-scores'; ?>">

    <h2><?php echo $page_title; ?></h2>
    <h3>Данные за <?php echo ($academic_year - 1) . '/' . $academic_year; ?> учебный год</h3>

    <?php // <<< НАЧАЛО НОВОГО БЛОКА: Уведомление о региональном этапе >>> ?>
    <?php if ($current_stage === 'regional_stage') : ?>
        <div class="vo-regional-notice" style="margin-bottom: 20px;">
            <?php if ($has_regional_qualifiers) : ?>
                <p>Участники, прошедшие на региональный этап, отмечены звездочкой (*).</p>
            <?php else : ?>
                <p>К сожалению, в этом году прошедших на региональный этап ВсОШ по предмету <strong><?php echo esc_html($subject_name); ?></strong> нет.</em></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
    <?php // <<< КОНЕЦ НОВОГО БЛОКА >>> ?>


    <?php if (empty($current_stage)) : ?>
        <div class="vo-results-notice"><p>За <?php echo ($academic_year - 1) . '/' . $academic_year; ?> учебный год данных пока нет.</p></div>
    <?php else: ?>

        <!-- Форма с фильтрами -->
        <div class="vo-results-filters">
            <form class="filter-row" method="get" action="">
                <input type="hidden" name="page_id" value="<?php echo get_the_ID(); ?>">
                <?php if ($academic_year !== vo_data_academic_year()) : ?>
                    <input type="hidden" name="vo_year" value="<?php echo esc_attr($academic_year); ?>">
                <?php endif; ?>
                <select name="school_id">
                    <option value="0">Все школы</option>
                    <?php foreach (($filters['schools'] ?? []) as $school) : ?>
                        <option value="<?php echo esc_attr($school->id); ?>" <?php selected($filters['selected_school'], $school->id); ?>>
                            <?php echo esc_html($school->short_name); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="class">
                    <option value="0">Все классы</option>
                    <?php foreach (($filters['classes'] ?? []) as $class_num) : ?>
                        <option value="<?php echo esc_attr($class_num); ?>" <?php selected($filters['selected_class'], $class_num); ?>>
                            <?php echo esc_html($class_num); ?> класс
                        </option>
                    <?php endforeach; ?>
                </select>
                <button type="submit">Применить</button>
            </form>
        </div>

        <?php if (empty($results_by_class)) : ?>
            <p>По вашему запросу ничего не найдено.</p>
        <?php else : ?>
            
            <?php foreach ($results_by_class as $class_grade => $participants) : ?>
             <h3 class="grade"><?php echo esc_html($class_grade); ?> класс</h3>
              <div class="table-wrapper">
                <table class="vo-results-table">
                    <thead>
                        <tr>
                            <th>№</th>
                            <th>ФИО</th>
                            <?php if ($show_school_col) echo '<th>Школа</th>'; ?>
                            <th>Балл</th>
                            <?php if ($show_status_col) echo '<th>Статус</th>'; ?>
                            <?php if ($show_school_score_col) echo '<th class="school-score-col">ШБ</th>'; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $index => $row) : ?>
                            <?php 
                            // <<< ИЗМЕНЕНА ЛОГИКА: Выделение работает только на этапе regional_stage >>>
                            $is_regional_qualifier = ($current_stage === 'regional_stage' && !empty($row->is_regional_qualifier));
                            ?>
                            <tr <?php if ($is_regional_qualifier) echo 'style="background-color: rgba(243, 111, 3, 0.1);"'; ?>>
                                <td class='rt number'><?php echo $index + 1; ?></td>
                                <td>
                                    <?php 
                                    $participant_name = esc_html($row->last_name . ' ' . $row->first_name);
                                    if ($is_regional_qualifier) {
                                        echo '<strong>' . $participant_name . ' *</strong>';
                                    } else {
                                        echo $participant_name;
                                    }
                                    ?>
                                </td>
                                <?php if ($show_school_col) echo '<td class="school">' . esc_html($row->short_name) . '</td>'; ?>
                                <td>
                                    <?php
                                    // Проверяем, является ли участник лауреатом прошлого года
                                    if (!empty($row->ppg_status)) {
                                        // Теперь проверяем, участвовал ли он в этом году.
                                        // Контроллер присваивает score = 'ППГ', если участник НЕ участвовал.
                                        if ($row->score === 'ППГ') {
                                            // Если он НЕ участвовал, выводим только его статус (Призер ПГ / Победитель ПГ)
                                            echo esc_html($row->ppg_status) . ' ПГ';
                                        } else {
                                            // Если он УЧАСТВОВАЛ, выводим его балл за этот год и в скобках статус
                                            echo esc_html($row->score) . '<br>(' . esc_html($row->ppg_status) . ' ПГ)';
                                        }
                                    } else {
                                        // Обычный участник, не ППГ
                                        echo esc_html($current_stage === 'school_stage' ? $row->school_level_score : $row->score);
                                    }
                                    ?>
                                </td>
                                <?php if ($show_status_col) : ?>
                                    <td class="status">
                                        <?php 
                                        $status = esc_html($row->status);
                                        if (in_array($row->status, ['Победитель', 'Призёр'])) {
                                            echo '<strong>' . $status . '</strong>';
                                        } else {
                                            echo $status;
                                        }
                                        ?>
                                    </td>
                                <?php endif; ?>
                                <?php if ($show_school_score_col) echo '<td class="school-score-col">' . esc_html($row->school_level_score ?? '') . '</td>'; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
              </div>
            <?php endforeach; ?>

            <?php if (!empty($class_cutoffs)) : ?>
                <div class="vo-passing-scores" style="margin-top: 28px; border: 1px solid #000; display: inline-block; padding: 12px 18px;">
                    <strong style="display:block; margin-bottom: 8px;">Проходной балл на районный этап</strong>
                    <table style="border-collapse: collapse;">
                        <tbody>
                            <?php foreach ($class_cutoffs as $cutoff_class => $cutoff_score) : ?>
                                <tr>
                                    <td style="padding: 2px 16px 2px 0;"><?php echo esc_html($cutoff_class); ?> класс</td>
                                    <td style="padding: 2px 0; text-align: right;"><strong><?php echo esc_html($cutoff_score); ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endif; ?>
        
        <?php if ($show_school_score_col) : ?>
            <div style="margin-top: 15px;">
                <label>
                    <input type="checkbox" id="show-school-score"<?php if ($force_show_school_score) echo ' checked'; ?>>
                    Показать балл за школьный этап (ШБ).
                </label>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>