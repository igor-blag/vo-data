<?php
// Файл: /includes/vo-data-settings.php

if (!defined('ABSPATH')) exit;

// Добавляем страницу настроек в меню "Настройки"
add_action('admin_menu', 'vo_data_add_settings_page');
function vo_data_add_settings_page() {
    add_options_page(
        'Настройки импорта VO Data', // Заголовок страницы
        'VO Data Import',           // Название в меню
        'manage_options',           // Права доступа
        'vo-data-settings',         // Slug страницы
        'vo_data_render_settings_page' // Функция для отображения HTML
    );
}

// Регистрируем наши настройки
add_action('admin_init', 'vo_data_register_settings');
function vo_data_register_settings() {
    register_setting(
        'vo_data_settings_group', 
        'vo_data_options',        
        'vo_data_sanitize_options'
    );

    add_settings_section(
        'vo_data_main_section', 
        'Настройки парсинга Excel-файлов', 
        null, 
        'vo-data-settings' 
    );

    add_settings_field('subject_cell', 'Ячейка с предметом', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_main_section', ['id' => 'subject_cell', 'default' => 'C3', 'desc' => 'Например: C3']);
    add_settings_field('start_row', 'Номер строки с заголовками столбцов', 'vo_data_field_number_render', 'vo-data-settings', 'vo_data_main_section', ['id' => 'start_row', 'default' => '6', 'desc' => 'Данные участников должны начинаться со следующей строки.']);
    
    add_settings_field(
        'dashboard_page_id', 
        'Главная страница результатов (дашборд)', 
        'vo_data_field_pages_dropdown_render', // Используем нашу новую функцию
        'vo-data-settings', 
        'vo_data_main_section', 
        [
            'id' => 'dashboard_page_id', 
            'desc' => 'Выберите страницу, на которой вы разместили шорткод <code>[vo_results_dashboard]</code>. Новые страницы для предметов будут создаваться как ее дочерние.'
        ]
    );
    
    add_settings_section('vo_data_columns_section', 'Сопоставление колонок', null, 'vo-data-settings');
    
    add_settings_field('col_last_name', 'Колонка: Фамилия', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_columns_section', ['id' => 'col_last_name', 'default' => 'B']);
    add_settings_field('col_first_name', 'Колонка: Имя', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_columns_section', ['id' => 'col_first_name', 'default' => 'C']);
    add_settings_field('col_patronymic', 'Колонка: Отчество', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_columns_section', ['id' => 'col_patronymic', 'default' => 'D']);
    add_settings_field('col_gender', 'Колонка: Пол', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_columns_section', ['id' => 'col_gender', 'default' => 'E']);
    add_settings_field('col_dob', 'Колонка: Дата рождения (Не передаётся БД)', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_columns_section', ['id' => 'col_dob', 'default' => 'F']);
    add_settings_field('col_class', 'Колонка: Класс обучения', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_columns_section', ['id' => 'col_class', 'default' => 'N']);
    add_settings_field('col_class_grade', 'Колонка: За какой класс выступает', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_columns_section', ['id' => 'col_class_grade', 'default' => 'O']);
    add_settings_field('col_school_short_name', 'Колонка: Краткое наименование школы', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_columns_section', ['id' => 'col_school_short_name', 'default' => 'L']);
    add_settings_field('col_school_address', 'Колонка: Адрес школы', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_columns_section', ['id' => 'col_school_address', 'default' => 'K']);
    add_settings_field('col_school_full_name', 'Колонка: Полное наименование школы', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_columns_section', ['id' => 'col_school_full_name', 'default' => 'J']);
    add_settings_field('col_score', 'Колонка: Результат (балл)', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_columns_section', ['id' => 'col_score', 'default' => 'Q']);
    add_settings_field('col_status', 'Колонка: Статус (Победитель/Призёр)', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_columns_section', ['id' => 'col_status', 'default' => 'P']);
    add_settings_field('col_snils', 'Колонка: СНИЛС (Не передаётся БД', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_columns_section', ['id' => 'col_snils', 'default' => 'R']);
    
    
    
    add_settings_field('subject_cell', 'Ячейка с предметом', 'vo_data_field_text_render', 'vo-data-settings', 'vo_data_main_section', ['id' => 'subject_cell', 'default' => 'C3', 'desc' => 'Например: C3']);

    
    add_settings_field(
        'secret_word',
        'Префикс для надежного шифрования чувствительной информации', 
        'vo_data_field_text_render', 
        'vo-data-settings', 
        'vo_data_main_section', 
        [
            'id' => 'secret_prefix', 
            'default' => 'Pre'
        ]
    );
    
}

function vo_data_field_text_render($args) {
    $options = get_option('vo_data_options');
    $id = $args['id'];
    $value = $options[$id] ?? ($args['default'] ?? '');
    $placeholder = $args['placeholder'] ?? '';
    $desc = $args['desc'] ?? '';
    echo "<input type='text' name='vo_data_options[{$id}]' value='" . esc_attr($value) . "' placeholder='" . esc_attr($placeholder) . "' class='regular-text' />";
    if ($desc) { echo "<p class='description'>{$desc}</p>"; }
}
function vo_data_field_number_render($args) {
    $options = get_option('vo_data_options');
    $id = $args['id'];
    $value = $options[$id] ?? ($args['default'] ?? '');
    $desc = $args['desc'] ?? '';
    echo "<input type='number' name='vo_data_options[{$id}]' value='" . esc_attr($value) . "' class='small-text' />";
    if ($desc) { echo "<p class='description'>{$desc}</p>"; }
}

function vo_data_field_pages_dropdown_render($args) {
    $options = get_option('vo_data_options');
    $id = $args['id'];
    $value = $options[$id] ?? 0;
    
    // Получаем все опубликованные страницы сайта
    $pages = get_pages(['post_status' => 'publish']);
    
    echo "<select id='{$id}' name='vo_data_options[{$id}]'>";
    echo "<option value='0'>-- Выберите страницу --</option>";
    foreach ($pages as $page) {
        // Используем selected() для автоматического выбора сохраненной опции
        printf(
            '<option value="%d" %s>%s</option>',
            $page->ID,
            selected($value, $page->ID, false),
            esc_html($page->post_title)
        );
    }
    echo "</select>";
    if (!empty($args['desc'])) {
        echo "<p class='description'>{$args['desc']}</p>";
    }
}

function vo_data_sanitize_options($input) {
    $sanitized_input = [];
    foreach ($input as $key => $value) {
        if ($key === 'start_row' || $key === 'dashboard_page_id') { // <-- Добавили 'dashboard_page_id'
            $sanitized_input[$key] = absint($value); 
        } else {
            $sanitized_input[$key] = sanitize_text_field(strtoupper($value));
        }
    }
    return $sanitized_input;
}
/**
 * Отображает HTML-код страницы настроек.
 */
function vo_data_render_settings_page() {
    ?>
    <div class="wrap">
        <h1>Настройки VO Data</h1>
        
        <?php
        // Уведомления о статусе операций
        if (isset($_GET['backup_success'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Резервная копия успешно создана.</p></div>';
        }
        if (isset($_GET['restore_success'])) {
            echo '<div class="notice notice-success is-dismissible"><p>Данные успешно восстановлены из резервной копии.</p></div>';
        }
        if (isset($_GET['backup_error'])) {
            echo '<div class="notice notice-error is-dismissible"><p><strong>Ошибка создания резервной копии:</strong> Не удалось создать ZIP-архив.</p></div>';
        }
        if (isset($_GET['restore_error'])) {
            $errors = [
                'upload' => 'Ошибка загрузки файла.',
                'zip_open' => 'Не удалось открыть ZIP-архив.',
                'no_data_file' => 'В архиве отсутствует файл backup-data.json.',
                'json_decode' => 'Не удалось прочитать данные из файла резервной копии.',
                'db_insert' => 'Произошла ошибка при записи данных в базу. Восстановление отменено.'
            ];
            $error_key = $_GET['restore_error'];
            $message = isset($errors[$error_key]) ? $errors[$error_key] : 'Произошла неизвестная ошибка.';
            echo '<div class="notice notice-error is-dismissible"><p><strong>Ошибка восстановления:</strong> ' . esc_html($message) . '</p></div>';
        }
        ?>

        <!-- Форма основных настроек -->
        <form action="options.php" method="post">
            <?php
            settings_fields('vo_data_settings_group');
            do_settings_sections('vo-data-settings');
            submit_button('Сохранить настройки');
            ?>
        </form>

        <hr>
        
        <!-- НОВЫЙ РАЗДЕЛ: Резервное копирование и Восстановление -->
        <h2>Резервное копирование и восстановление</h2>
        <div class="card">
            <h3>Создать резервную копию</h3>
            <p>Будет создан ZIP-архив, содержащий все данные из таблиц плагина и его текущие настройки. Файл будет сохранен в папке <code><?php echo esc_html(str_replace(ABSPATH, '', VO_DATA_PLUGIN_DIR) . 'Backups/'); ?></code>.</p>
            <form method="post" action="">
                <?php wp_nonce_field('vo_data_backup_nonce'); ?>
                <input type="hidden" name="vo_data_action" value="create_backup">
                <button type="submit" class="button button-primary">Создать резервную копию</button>
            </form>
        </div>

        <div class="card" style="margin-top: 15px;">
            <h3>Восстановить из файла</h3>
            <p><strong style="color: red;">ВНИМАНИЕ:</strong> Этот процесс полностью заменит все текущие данные и настройки плагина на данные из файла резервной копии. Это действие необратимо.</p>
            <form method="post" action="" enctype="multipart/form-data" onsubmit="return confirm('Вы уверены, что хотите восстановить данные? Все текущие данные плагина будут стерты и заменены данными из файла!');">
                <?php wp_nonce_field('vo_data_restore_nonce'); ?>
                <input type="hidden" name="vo_data_action" value="restore_backup">
                <p>
                    <label for="backup_file">Выберите ZIP-файл резервной копии:</label><br>
                    <input type="file" name="backup_file" id="backup_file" accept=".zip" required>
                </p>
                <button type="submit" class="button button-danger">Восстановить из файла</button>
            </form>
        </div>

        <hr>

        <!-- Секция "Опасная зона" (без изменений) -->
        <div class="card" style="border-left: 4px solid red; padding: 1px 12px;">
            <h2 style="color:red;">Опасная зона</h2>
            <p>Это действие необратимо и приведет к полной потере всех данных, собранных плагином.</p>
            <form method="post" action="">
                <?php wp_nonce_field('vo_data_delete_all_nonce'); ?>
                <input type="hidden" name="vo_data_action" value="delete_all_tables">
                <button type="submit" class="button" onclick="return confirm('Вы уверены, что хотите ПОЛНОСТЬЮ удалить все таблицы и данные плагина?');">
                    Удалить все таблицы и данные плагина
                </button>
            </form>
            <br>
        </div>
    </div>
    <?php
}