// Файл: /js/vo-data-public.js
jQuery(document).ready(function($) {

    // --- [vo_summary] Вкладки без перезагрузки страницы ---
    // Панели всех вкладок уже отрисованы сервером, здесь только переключаем видимость.
    $('.vo-summary-wrapper[data-vo-summary]').each(function() {
        var wrapper = $(this);

        wrapper.on('click', '.vo-summary-tab', function(e) {
            var tab  = $(this);
            var name = tab.data('vo-tab');
            if (!name) return;

            // Если JS отключён, ссылка отработает как обычный переход (fallback).
            e.preventDefault();

            wrapper.find('.vo-summary-tab').removeClass('active');
            tab.addClass('active');

            wrapper.find('.vo-summary-panel').removeClass('is-active');
            wrapper.find('.vo-summary-panel[data-vo-panel="' + name + '"]').addClass('is-active');

            // Обновляем адрес без перезагрузки, чтобы ссылку можно было скопировать
            if (window.history && window.history.replaceState) {
                var url = new URL(window.location.href);
                url.searchParams.set('tab', name);
                window.history.replaceState(null, '', url.toString());
            }
        });
    });

    // --- [vo_results] Переключатель колонки с баллом школьного этапа (ШБ) ---
    var showSchoolScoreCheckbox = $('#show-school-score');
    var resultsWrapper = $('.vo-results-wrapper');

    if (showSchoolScoreCheckbox.length && resultsWrapper.length) {
        // Функция для переключения класса на главном контейнере
        function toggleSchoolScoreVisibility() {
            resultsWrapper.toggleClass('show-school-scores', showSchoolScoreCheckbox.is(':checked'));
        }

        // Начальное состояние (если страница перезагрузилась с отмеченным чекбоксом)
        toggleSchoolScoreVisibility();

        // Обработчик изменения состояния чекбокса
        showSchoolScoreCheckbox.on('change', toggleSchoolScoreVisibility);
    }
});