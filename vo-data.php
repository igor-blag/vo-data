<?php
/**
 * Plugin Name: VO Data
 * Description: Плагин для публикации и отображения информации об участии во всероссийской олимпиаде школьников.
 * Version: 2.7.0 (Per-subject publication flag; previous-year laureates exclude graduates; notifications on publish)
 * Author: IgB
 */

if (!defined('ABSPATH')) exit;

// Константы для путей
define('VO_DATA_PLUGIN_DIR', plugin_dir_path(__FILE__));

// Версия плагина. Используется для автосброса кэша при обновлении файлов.
define('VO_DATA_VERSION', '2.7.0');

// Время жизни кэша публичных шорткодов (сек). Увеличено до 48 часов:
// импорт и так инвалидирует кэш через версию кэша.
define('VO_DATA_CACHE_TTL', 48 * HOUR_IN_SECONDS);

// --- Подключение основных модулей плагина ---

// Логика, выполняемая при активации плагина (создание таблиц)
require_once VO_DATA_PLUGIN_DIR . 'includes/vo-data-activator.php';

// Обработчик AJAX-запроса для импорта данных
require_once VO_DATA_PLUGIN_DIR . 'includes/vo-data-ajax-handler.php';

// Страница настроек плагина
require_once VO_DATA_PLUGIN_DIR . 'includes/vo-data-settings.php';

// Общие вспомогательные функции (медиана, учебный год, таблицы, рекомендации)
require_once VO_DATA_PLUGIN_DIR . 'includes/vo-data-helpers.php';

// Шорткоды: [vo_results], [vo_results_dashboard]
require_once VO_DATA_PLUGIN_DIR . 'includes/vo-data-shortcode.php';

// Шорткод: [vo_antirating] — индекс объективности школьного этапа
require_once VO_DATA_PLUGIN_DIR . 'includes/vo-data-antirating.php';

// Шорткод: [vo_summary] — сводка по итогам районного этапа
require_once VO_DATA_PLUGIN_DIR . 'includes/vo-data-summary.php';

// --- Подключение модулей административной панели ---

// Инициализация меню, скриптов и обработчиков для админки
require_once VO_DATA_PLUGIN_DIR . 'includes/admin/admin-init.php';

// Отображение главной страницы плагина (статистика и обслуживание)
require_once VO_DATA_PLUGIN_DIR . 'includes/admin/admin-page-main.php';

// Отображение страницы отбора на региональный этап
require_once VO_DATA_PLUGIN_DIR . 'includes/admin/admin-page-regional.php';

// Отображение универсальной страницы импорта
require_once VO_DATA_PLUGIN_DIR . 'includes/admin/admin-page-import.php';

// API для мобильного приложения (НОВОЕ)
require_once VO_DATA_PLUGIN_DIR . 'includes/vo-data-api.php';

// Логика резервного копирования и восстановления
require_once VO_DATA_PLUGIN_DIR . 'includes/vo-data-backup-handler.php';

// --- Миграция схемы (добавление колонки is_published) ---
//
// Хук активации срабатывает один раз, поэтому для уже активных сайтов
// колонку публикации добавляем на plugins_loaded.
add_action('plugins_loaded', 'vo_data_maybe_upgrade_schema', 5);

// --- Автосброс кэша при обновлении плагина ---
//
// Готовый HTML публичных шорткодов кэшируется в transients, а ключ завязан на
// опцию vo_data_cache_version, которая меняется только при импорте данных.
// Поэтому после обновления файлов (без импорта) сайт мог ещё VO_DATA_CACHE_TTL
// отдавать старый HTML — например, со ссылками ?year=…, дающими 404.
// Здесь сравниваем сохранённую версию плагина с текущей и при расхождении
// сбрасываем transients и увеличиваем версию кэша.
add_action('plugins_loaded', 'vo_data_maybe_flush_cache_on_version_change');
function vo_data_maybe_flush_cache_on_version_change() {
    if (get_option('vo_data_version') === VO_DATA_VERSION) {
        return;
    }

    global $wpdb;
    $like_transient = $wpdb->esc_like('_transient_vo_data_') . '%';
    $like_timeout   = $wpdb->esc_like('_transient_timeout_vo_data_') . '%';
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
        $like_transient,
        $like_timeout
    ));

    // Смена версии кэша инвалидирует и ключи в объектном кэше.
    update_option('vo_data_cache_version', (int) get_option('vo_data_cache_version', 1) + 1);
    update_option('vo_data_version', VO_DATA_VERSION);
}


// --- Регистрация хуков ---

// Хук активации
register_activation_hook(__FILE__, 'vo_data_on_activation');

// Обработчик для кнопки полного удаления данных (из файла admin-init.php)
add_action('admin_init', 'vo_data_handle_delete_all_data');

// Уведомление после удаления (из файла admin-init.php)
add_action('admin_notices', 'vo_data_show_deleted_notice');

// <<< НОВЫЙ ХУК: Подключаем обработчик для действий с бэкапами >>>
add_action('admin_init', 'vo_data_handle_backup_restore_actions');

add_action('admin_notices', 'vo_data_show_deleted_notice');
add_action('wp_enqueue_scripts', 'vo_data_register_public_scripts');


// Подключение скриптов для публичной части сайта.
// Скрипт только регистрируется здесь (без загрузки на странице);
// подключается на лету в шорткоде [vo_results], когда он действительно выводится.
add_action('wp_enqueue_scripts', 'vo_data_register_public_scripts');
function vo_data_register_public_scripts() {
    wp_register_script(
        'vo-data-public-js',
        plugin_dir_url(__FILE__) . 'js/vo-data-public.js',
        ['jquery'],
        VO_DATA_VERSION,
        true
    );
}

add_shortcode('vo-info', function() { return 'Информация из плагина vo-data'; });