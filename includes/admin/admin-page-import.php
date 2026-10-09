<?php
// Файл: /includes/admin/admin-page-import.php

if (!defined('ABSPATH')) exit;

/**
 * Отображает универсальную страницу импорта данных из файлов.
 */
function vo_data_import_page_html() {
    $page_slug = $_GET['page'];
    $page_title = '';
    $instruction = '';
    
    // Определяем заголовок и тип импорта в зависимости от slug'а страницы
    switch ($page_slug) {
        case 'vo-data-school-import':
            $page_title = 'Загрузка результатов школьного этапа (только прошедшие на район)';
            $import_type = 'school_stage';
            $instruction = 'Школьный этап';
            break;
        case 'vo-data-district-prelim-import':
            $page_title = 'Загрузка предварительных результатов районного этапа';
            $import_type = 'district_prelim';
            $instruction = 'Предвар';
            break;
        case 'vo-data-district-final-import':
            $page_title = 'Загрузка итогов районного этапа';
            $import_type = 'district_final';
            $instruction = '<p><strong>На этой странице следует загружать только итоговые результаты районного этапа.</strong></p>
            <p>Для отображения в списке прошедших на районный этап победителей и призеров прошлого года
            потребуется загрузить итоги районного этапа ВсОШ только в первый учебный год работы с данным плагином, пока база данных пустая.</p>
            <p>В дальнейшем информация о победителях и призерах прошлого года будет появляться автоматически.</p>';
            break;
        default:
            $page_title = 'Импорт данных';
            $import_type = '';
            $instruction = '';
            break;
    }
    ?>
    <style>
        .drop-zone { border: 2px dashed #ccc; border-radius: 8px; padding: 40px; text-align: center; color: #777; font-size: 1.2em; transition: all 0.3s ease; }
        .drop-zone.dragover { border-color: #007cba; background-color: #f7f7f7; color: #333; }
        .drop-zone input[type="file"] { display: none; }
        .summary-card, .results-card { display: none; }
    </style>

    <div class="wrap">
        <h1><?php echo esc_html($page_title); ?></h1>
        <input type="hidden" id="import-type" value="<?php echo esc_attr($import_type); ?>">
        <div class="notice" style="max-width:800px"><?php echo $instruction; ?></div>
        
        <!-- Карта 1: Зона для загрузки файлов -->
        <div id="upload-card" class="card">
            
            <?php // --- НОВЫЙ БЛОК: Чекбокс для импорта за прошлый год ---
            if ($import_type === 'district_final') : ?>
            <div style="padding: 10px; border: 1px solid #c3c4c7; margin-bottom: 20px;">
                <label for="import-previous-year">
                    <input type="checkbox" id="import-previous-year" name="import-previous-year">
                    <strong>Импортировать данные за прошлый учебный год</strong>
                </label>
                <p class="description">
                    Если эта опция включена, будут загружены только победители и призеры, и они будут записаны в базу данных как участники прошлого учебного года.
                    Используйте для первоначального наполнения базы.
                </p>
            </div>
            <?php endif; 
            // --- КОНЕЦ НОВОГО БЛОКА --- ?>

            <div id="drop-zone" class="drop-zone"><p>Перетащите файлы сюда или щёлкните, чтобы выбрать</p></div>
            <input type="file" id="file-input" multiple accept=".xlsx, .xls" />
            <div id="upload-status" style="margin-top: 15px;"></div>
        </div>

        <!-- ... остальной HTML без изменений ... -->
        <div id="summary-card" class="summary-card card">
            <h2>Сводка перед импортом</h2>
            <p><strong>Предмет:</strong> <span id="summary-subject"></span><br><strong>Этап:</strong> <span id="summary-stage"><?php echo esc_html($page_title); ?></span></p>
            <table class="wp-list-table widefat striped">
                <thead><tr><th>Класс</th><th>Количество участников</th></tr></thead>
                <tbody id="summary-table-body"></tbody>
            </table>
            <p style="margin-top: 20px;"><button id="import-btn" class="button button-primary button-hero">Импортировать данные</button> <button id="cancel-btn" class="button button-secondary">Отмена</button></p>
        </div>
        
        <div id="results-card" class="results-card card"></div>
    </div>
    <?php
}