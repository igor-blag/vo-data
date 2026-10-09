<?php
// Файл: /includes/vo-data-helpers.php
// Общие вспомогательные функции плагина VO Data.

if (!defined('ABSPATH')) exit;

/**
 * Текущий учебный год (формат: 2026 для учебного года 2025/2026).
 */
function vo_data_academic_year() {
    return (int)date('n') >= 9 ? (int)date('Y') + 1 : (int)date('Y');
}

/**
 * Определяет учебный год для шорткода.
 * Приоритет: атрибут year шорткода -> GET-параметр vo_year -> текущий учебный год.
 *
 * Внимание: имя параметра URL не должно быть year — это служебная query-переменная
 * WordPress (архивы по датам), из-за которой страница отдаёт 404. Обратная
 * совместимость со старым ?year= сохранена ниже.
 *
 * @param array $atts Атрибуты шорткода.
 * @return int
 */
function vo_data_resolve_academic_year($atts = []) {
    $year = 0;
    if (!empty($atts['year'])) {
        $year = intval($atts['year']);
    } elseif (isset($_GET['vo_year'])) {
        $year = intval($_GET['vo_year']);
    } elseif (isset($_GET['year'])) {
        $year = intval($_GET['year']);
    }
    if ($year < 2000 || $year > 2200) {
        $year = vo_data_academic_year();
    }
    return $year;
}

/**
 * Имена таблиц плагина.
 */
function vo_data_table_names() {
    global $wpdb;
    return [
        'results'      => $wpdb->prefix . 'vo_results',
        'participants'  => $wpdb->prefix . 'vo_participants',
        'schools'       => $wpdb->prefix . 'vo_schools',
        'subjects'      => $wpdb->prefix . 'vo_subjects',
        'comp_status'   => $wpdb->prefix . 'vo_competition_status',
        'stage_types'   => $wpdb->prefix . 'vo_stage_types',
    ];
}

/**
 * Текущая версия кэша (инкрементируется при импорте).
 */
function vo_data_cache_version() {
    return get_option('vo_data_cache_version', 1);
}

/**
 * Вычисление медианы массива чисел.
 */
function vo_data_median($arr) {
    sort($arr);
    $count = count($arr);
    if ($count === 0) return 0;
    $mid = intdiv($count, 2);
    return ($count % 2 === 0) ? ($arr[$mid - 1] + $arr[$mid]) / 2 : $arr[$mid];
}

/**
 * Генерирует текстовые рекомендации по индексу объективности и нагрузке.
 *
 * @param int    $index      Индекс объективности (0-100).
 * @param int    $load_index Процент нагрузки слабыми.
 * @param int    $weak_count Абсолютное число слабых участников.
 * @param string $context    'methodist' | 'admin' | 'detail'
 * @return array
 */
function vo_antirating_recommendations($index, $load_index, $weak_count, $context = 'detail') {
    $rec = [];

    if ($index < 30) {
        $rec['level'] = 'critical';
        $rec['summary'] = 'Критическое расхождение между результатами школьного и районного этапов.';
        if ($context === 'methodist') {
            $rec['actions'] = [
                'Вынести вопрос на обсуждение на районном методическом объединении (РМО).',
                'Рекомендовать руководству школы обеспечить независимый контроль при проведении следующего школьного этапа.',
                'Рассмотреть направление наблюдателя на школьный этап по данному предмету.',
            ];
        } elseif ($context === 'admin') {
            $rec['actions'] = [
                'Провести беседу с педагогом, ответственным за проведение школьного этапа по предмету.',
                'Проверить условия проведения: рассадку, контроль, доступ к материалам.',
                'Обеспечить независимое наблюдение на следующем школьном этапе.',
            ];
        } else {
            $rec['actions'] = [
                'Результаты школьного этапа по данному предмету в данной школе не подтверждаются на районном уровне.',
                'Необходим контроль условий проведения школьного этапа.',
            ];
        }
    } elseif ($index < 50) {
        $rec['level'] = 'warning';
        $rec['summary'] = 'Значительное расхождение. Результаты школьного этапа слабо подтверждаются на районном.';
        if ($context === 'methodist') {
            $rec['actions'] = [
                'Обсудить ситуацию с ответственным за предмет в школе.',
                'При повторении ситуации из года в год — вынести на РМО.',
            ];
        } elseif ($context === 'admin') {
            $rec['actions'] = [
                'Обратить внимание на условия проведения ШЭ по данному предмету.',
                'Уточнить у педагога порядок проведения олимпиады.',
            ];
        } else {
            $rec['actions'] = [
                'Рекомендуется обратить внимание на объективность проведения школьного этапа.',
            ];
        }
    } elseif ($index < 70) {
        $rec['level'] = 'notice';
        $rec['summary'] = 'Умеренное расхождение. Есть отклонения, но они могут быть в пределах допустимого.';
        $rec['actions'] = [
            'Отслеживать динамику в следующем учебном году.',
        ];
    } elseif ($index < 80) {
        $rec['level'] = 'ok';
        $rec['summary'] = 'Небольшое расхождение. В целом результаты согласуются.';
        $rec['actions'] = [];
    } else {
        $rec['level'] = 'good';
        $rec['summary'] = 'Результаты школьного этапа подтверждаются на районном. Школьный этап проведён объективно.';
        $rec['actions'] = [];
    }

    // Дополнительно по нагрузке
    if ($weak_count >= 5) {
        $rec['load_note'] = "Высокая концентрация слабых участников ($weak_count чел. в нижних 25%). Возможно, стоит пересмотреть критерии отбора на районный этап по данному предмету.";
    } elseif ($load_index > 15) {
        $rec['load_note'] = "Повышенная доля слабых участников ({$load_index}%). Рекомендуется обратить внимание на качество отбора.";
    }

    return $rec;
}

/**
 * Диагностика причин нехватки данных для расчёта индекса объективности.
 *
 * @param array $raw_data      Все строки с обоими баллами > 0.
 * @param array $groups        Группы предмет+класс из raw_data.
 * @param array $all_district  Все выступления РЭ (с баллом > 0).
 * @param string $subject_name Выбранный предмет (или '' для всех).
 * @return string HTML-сообщение с пояснением.
 */
function vo_antirating_insufficient_data_message($raw_data, $groups, $all_district, $subject_name = '') {
    $total_district = count($all_district);
    $total_with_both = count($raw_data);

    // Сколько групп слишком маленькие
    $small_groups = 0;
    $eligible_groups = 0;
    foreach ($groups as $g) {
        if (count($g) < 3) {
            $small_groups++;
        } else {
            $eligible_groups++;
        }
    }

    $subject_label = !empty($subject_name) ? " по предмету «{$subject_name}»" : '';

    $html = '<div class="vo-results-notice" style="padding: 16px; border: 1px solid #ddd; border-radius: 2px; background: #fafafa;">';
    $html .= '<p><strong>Недостаточно данных для расчёта индекса объективности' . esc_html($subject_label) . '.</strong></p>';
    $html .= '<p style="font-size:0.9em; color:#555;">Причины:</p>';
    $html .= '<ul style="font-size:0.9em; color:#555; padding-left:20px; margin:8px 0;">';

    if ($total_district === 0) {
        $html .= '<li>Нет участников с ненулевым баллом на районном этапе.</li>';
    } else {
        if ($total_with_both === 0) {
            $html .= '<li>Из ' . $total_district . ' выступлений на РЭ ни у одного участника нет одновременно ненулевого балла за школьный и районный этапы.</li>';
            $html .= '<li>Возможные причины: не заполнены баллы школьного этапа, участники получили 0 баллов на РЭ, или участники — призёры/победители прошлого года без балла ШЭ.</li>';
        } elseif ($eligible_groups === 0) {
            $html .= '<li>Найдено ' . $total_with_both . ' выступлений с обоими баллами, но во всех группах (предмет + класс) менее 3 участников — слишком мало для расчёта.</li>';
        }
    }

    $html .= '</ul>';
    $html .= '<p style="font-size:0.85em; color:#888;">Индекс станет доступен, когда появится достаточно данных с баллами за оба этапа.</p>';
    $html .= '</div>';

    return $html;
}
