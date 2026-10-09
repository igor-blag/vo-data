<?php if (!defined('ABSPATH')) exit; ?>
<style>
    .vo-summary-wrapper {
        max-width: 1200px;
        font-family: inherit;
        --vo-border-color: #000;
        --vo-border-thin: 1px;
        --vo-border-thick: 2px;
    }
    .vo-summary-wrapper h2 {
        margin-bottom: 4px;
    }
    .vo-summary-wrapper h3 {
        margin-top: 0;
        color: #555;
        font-weight: normal;
    }

    /* --- Карточки статистики --- */
    .vo-summary-cards {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 24px;
    }
    .vo-summary-card {
        border: 1px solid #000;
        border-radius: 2px;
        padding: 14px 16px;
        background: #f9f9f9;
        text-align: center;
    }
    .vo-summary-card .vo-card-value {
        font-size: 2em;
        font-weight: bold;
        color: #f36f03;
        line-height: 1.2;
    }
    .vo-summary-card .vo-card-label {
        font-size: 0.85em;
        color: #555;
        margin-top: 4px;
    }

    /* --- Вкладки --- */
    .vo-summary-tabs {
        display: flex;
        gap: 0;
        border-bottom: 2px solid #000;
        margin-bottom: 20px;
    }
    .vo-summary-tab {
        padding: 10px 20px;
        text-decoration: none;
        color: #333;
        border: 1px solid transparent;
        border-bottom: none;
        border-radius: 2px 2px 0 0;
        margin-bottom: -2px;
        font-size: 0.8em;
        font-weight:700;
    }
    .vo-summary-tab:hover {
        color: #f36f03;
    }
    .vo-summary-tab.active {
        border-color: #000;
        border-bottom: 2px solid #fff;
        background: #fff;
        font-weight: bold;
        color: #000;
    }

    /* --- Панели вкладок (переключаются JS без перезагрузки) --- */
    .vo-summary-panel {
        display: none;
    }
    .vo-summary-panel.is-active {
        display: block;
    }

    /* --- Фильтры (ретро-стиль как на странице результатов) --- */
    .vo-summary-filters {
        display: flex;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        margin-bottom: 28px;
    }
    .vo-summary-filters select {
        font-size: 14px;
        line-height: 2;
        color: black;
        border: 1px solid black;
        border-radius: 2px;
        box-shadow: none;
        padding: 0 24px 0 8px;
        min-height: 30px;
        max-width: 25rem;
        -webkit-appearance: none;
        background: #fff;
        cursor: pointer;
        flex: 1;
        height: 42px;
        font-size: 16px;
        box-sizing: border-box;
    }
    .vo-summary-filters select:hover { color: #f36f03; }
    .vo-summary-filters select:focus { border-color: #000; color: #f36f03; }
    .vo-summary-filters button {
        display: inline-block;
        text-decoration: none;
        font-size: 16px;
        line-height: 2;
        min-height: 30px;
        margin: 0;
        padding: 0 10px;
        cursor: pointer;
        border: 1px solid black;
        border-radius: 2px;
        -webkit-appearance: none;
        white-space: nowrap;
        box-sizing: border-box;
        background-color: #f36f0355;
        color: black;
        flex: 1;
        height: 42px;
    }
    .vo-summary-filters button:hover { background-color: #f36f0322; color: black; }

    /* --- Таблицы (ретро-стиль с градиентными бордерами) --- */
    .vo-summary-table {
        width: 100%;
        border-collapse: collapse;
        border-spacing: 0;
        font-size: 14px;
    }
    .vo-summary-table strong { font-weight: 500; }

    /* Все ячейки: горизонтальная тонкая линия снизу */
    .vo-summary-table td,
    .vo-summary-table th {
        vertical-align: top;
        padding: 4px;
        background-repeat: no-repeat;
        background-image: linear-gradient(to right, var(--vo-border-color), var(--vo-border-color));
        background-size: 100% var(--vo-border-thin);
        background-position: left bottom;
    }

    /* Вертикальные прерывающиеся линии (не первая колонка) */
    .vo-summary-table td:not(:first-child),
    .vo-summary-table th:not(:first-child) {
        background-image:
            linear-gradient(to bottom, transparent 2px, var(--vo-border-color) 2px, var(--vo-border-color) calc(100% - 2px), transparent calc(100% - 2px)),
            linear-gradient(to right, var(--vo-border-color), var(--vo-border-color));
        background-size:
            var(--vo-border-thin) 100%,
            100% var(--vo-border-thin);
        background-position:
            left top,
            left bottom;
    }

    /* Заголовок: толстая нижняя линия */
    .vo-summary-table th {
        background-size: 100% var(--vo-border-thick);
    }
    .vo-summary-table th:not(:first-child) {
        background-image:
            linear-gradient(to bottom, transparent 2px, var(--vo-border-color) 2px, var(--vo-border-color) calc(100% - 3px), transparent calc(100% - 3px)),
            linear-gradient(to right, var(--vo-border-color), var(--vo-border-color));
        background-size:
            var(--vo-border-thin) 100%,
            100% var(--vo-border-thick);
    }

    /* Последняя строка: толстая нижняя линия */
    .vo-summary-table tbody tr:last-child td {
        background-size: 100% var(--vo-border-thick);
    }
    .vo-summary-table tbody tr:last-child td:not(:first-child) {
        background-image:
            linear-gradient(to bottom, transparent 2px, var(--vo-border-color) 2px, var(--vo-border-color) calc(100% - 3px), transparent calc(100% - 3px)),
            linear-gradient(to right, var(--vo-border-color), var(--vo-border-color));
        background-size:
            var(--vo-border-thin) 100%,
            100% var(--vo-border-thick);
    }

    /* tfoot: толстая верхняя линия (через первую ячейку tfoot) */
    .vo-summary-table tfoot td {
        font-weight: bold;
        background-size: 100% var(--vo-border-thick);
        background-position: left top;
    }
    .vo-summary-table tfoot td:not(:first-child) {
        background-image:
            linear-gradient(to bottom, transparent 2px, var(--vo-border-color) 2px, var(--vo-border-color) calc(100% - 2px), transparent calc(100% - 2px)),
            linear-gradient(to right, var(--vo-border-color), var(--vo-border-color));
        background-size:
            var(--vo-border-thin) 100%,
            100% var(--vo-border-thick);
        background-position:
            left top,
            left top;
    }

    /* Ширина первой колонки */
    .vo-summary-table td:first-child,
    .vo-summary-table th:first-child {
        width: 5%;
    }

    /* Текстовые утилиты */
    .vo-summary-table .vo-text-center { text-align: center; }
    .vo-summary-table .vo-text-right { text-align: right; }
    .vo-summary-table .rt { text-align: right; }

    /* Статус победитель */
    .vo-summary-table .vo-status-winner {
        font-weight: 500;
        color: #f36f03;
    }

    /* Региональник: оранжевая подсветка */
    .vo-summary-table .vo-regional-yes td {
        background-color: rgba(243, 111, 3, 0.1);
    }

    /* Ссылки в таблице */
    .vo-summary-table a {
        color: #f36f03;
        text-decoration: none;
    }
    .vo-summary-table a:hover {
        text-decoration: underline;
    }

    /* Обёртка для горизонтального скролла */
    .vo-summary-table-wrap {
        overflow-x: auto;
        max-width: 100%;
        -webkit-overflow-scrolling: touch;
    }

    /* Счётчик результатов */
    .vo-summary-count {
        font-size: 0.9em;
        color: #555;
        margin-bottom: 12px;
    }

    /* Группировка по предмету */
    .vo-subject-group-header td {
        font-weight: bold;
        padding-top: 14px !important;
        background-size: 100% var(--vo-border-thick) !important;
    }

    @media (min-width: 400px) {
        .vo-summary-table { font-size: 18px; }
    }

    @media (max-width: 768px) {
        .vo-summary-cards {
            grid-template-columns: repeat(2, 1fr);
        }
        .vo-summary-tabs {
            flex-wrap: wrap;
        }
        .vo-summary-table {
            font-size: 13px;
        }
    }
</style>
