<?php
// Файл: /templates/antirating-styles.php
// CSS-стили для страницы индекса объективности.
if (!defined('ABSPATH')) exit;
?>
<style>
.vo-antirating-wrapper h2 { margin-bottom: 4px; }
.vo-antirating-wrapper > h3 { margin-top: 0; }

/* Вкладки */
.vo-tabs { display: flex; gap: 0; margin-bottom: 24px; border-bottom: 2px solid #ddd; flex-wrap: wrap; }
.vo-tab-btn {
    padding: 10px 20px; font-size: 0.95em; cursor: pointer;
    border: none; background: none; color: #666;
    border-bottom: 2px solid transparent; margin-bottom: -2px;
    white-space: nowrap; transition: all 0.15s; text-decoration: none;
}
.vo-tab-btn:hover { color: #f36f03; }
.vo-tab-btn.active { color: #000; font-weight: 500; border-bottom-color: #f36f03; }

/* Инструкция */
.vo-tab-instruction {
    background: #f5f0eb; border-left: 3px solid #f36f03;
    padding: 12px 16px; margin-bottom: 20px; font-size: 0.9em; line-height: 1.6;
}
.vo-tab-instruction p { margin: 0 0 6px 0; }
.vo-tab-instruction p:last-child { margin-bottom: 0; }

/* Фильтры */
.vo-antirating-filters { margin-bottom: 20px; }
.vo-antirating-filters .filter-row {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 20px;
}
.vo-antirating-filters select {
    font-size: 16px; line-height: 2; color: black;
    border: 1px solid black; border-radius: 2px; box-shadow: none;
    padding: 2px 24px 2px 12px; height: 42px; width: 100%;
    -webkit-appearance: none; background: #fff; cursor: pointer; box-sizing: border-box;
}
.vo-antirating-filters select:hover { color: #f36f03; }
.vo-antirating-filters button {
    font-size: 16px; line-height: 2; height: 42px; width: 100%;
    padding: 0 20px; cursor: pointer; border: 1px solid black; border-radius: 2px;
    -webkit-appearance: none; background-color: #f36f0355; color: black;
    box-sizing: border-box; white-space: nowrap;
}
.vo-antirating-filters button:hover { background-color: #f36f0322; }

/* Кнопки точности */
.vo-precision-wrap { margin-top: 20px; }
.vo-precision-buttons { display: flex; gap: 0; flex-wrap: wrap; }
.vo-precision-btn {
    padding: 8px 16px; font-size: 0.9em; cursor: pointer;
    border: 1px solid #999; background: #fff; color: #333;
    text-decoration: none; transition: all 0.15s;
    margin-right: -1px;
}
.vo-precision-btn:first-child { border-radius: 2px 0 0 2px; }
.vo-precision-btn:last-child { border-radius: 0 2px 2px 0; margin-right: 0; }
.vo-precision-btn:hover { background: #f5f0eb; }
.vo-precision-btn.active { background: #f36f03; color: #fff; border-color: #f36f03; font-weight: 500; z-index: 1; position: relative; }
.vo-precision-desc { margin-top: 8px; font-size: 0.8em; color: #666; }

/* Переключатель периода наблюдения */
.vo-period-bar { display: flex; flex-wrap: wrap; align-items: center; margin-bottom: 18px; }
.vo-period-label { font-size: 0.9em; color: #666; margin-right: 10px; }
.vo-period-btn {
    padding: 6px 14px; font-size: 0.9em; cursor: pointer;
    border: 1px solid #999; background: #fff; color: #333;
    text-decoration: none; transition: all 0.15s; margin: 2px 4px 2px 0;
    border-radius: 2px; white-space: nowrap;
}
.vo-period-btn:hover { background: #f5f0eb; color: #f36f03; }
.vo-period-btn.active { background: #f36f03; color: #fff; border-color: #f36f03; font-weight: 500; }

/* Блок динамики по годам */
.vo-years-block { margin-top: 14px; padding: 12px 16px; border: 1px solid #ddd; border-radius: 2px; background: #fff; }
.vo-years-block h5 { margin: 0 0 8px 0; font-size: 0.95em; }
.vo-years-table { border-collapse: collapse; font-size: 0.9em; min-width: 200px; }
.vo-years-table th, .vo-years-table td { padding: 4px 20px 4px 4px; border-bottom: 1px solid #eee; text-align: left; }
.vo-years-table th { font-weight: 500; }
.vo-years-table tr:last-child td { border-bottom: none; }
.vo-years-table .vo-idx-cell { font-weight: 500; }
.vo-years-signal { margin: 8px 0 0 0; font-size: 0.85em; color: #c62828; }

/* Тепловая матрица */
.vo-heatmap-section { margin-top: 8px; }
.vo-heatmap-wrapper { overflow-x: auto; max-width: 100%; -webkit-overflow-scrolling: touch; }
.vo-heatmap-table { border-collapse: collapse; font-size: 0.8em; width: 100%; }
.vo-heatmap-table th, .vo-heatmap-table td {
    padding: 6px 8px; text-align: center; border: 1px solid #ddd;
    min-width: 36px; white-space: nowrap;
}
.vo-heatmap-table th { background: #f5f5f5; font-weight: 500; position: sticky; top: 0; z-index: 1; }
.vo-heatmap-table th:first-child { text-align: left; position: sticky; left: 0; z-index: 2; background: #f5f5f5; }
.vo-heatmap-table td:first-child { text-align: left; font-weight: 500; position: sticky; left: 0; background: #fff; z-index: 1; }
.vo-heatmap-cell a { color: inherit; text-decoration: none; display: block; }
.vo-heatmap-cell a:hover { font-weight: 700; }
.vo-heatmap-link { color: inherit; text-decoration: none; border-bottom: 1px dashed #999; }
.vo-heatmap-link:hover { color: #f36f03; border-bottom-color: #f36f03; }

/* Детальная панель по ячейке */
.vo-detail-panel {
    margin-top: 20px; padding: 20px;
    border: 2px solid #f36f03; border-radius: 2px; background: #fffbf5;
}
.vo-detail-panel h4 { margin: 0 0 12px 0; font-size: 1.1em; }
.vo-detail-close { float: right; color: #999; text-decoration: none; font-size: 1.2em; line-height: 1; }
.vo-detail-close:hover { color: #f36f03; }
.vo-detail-metrics { display: flex; gap: 24px; flex-wrap: wrap; margin-bottom: 16px; }
.vo-detail-metric { text-align: center; }
.vo-detail-metric-value { font-size: 1.8em; font-weight: 500; line-height: 1.2; }
.vo-detail-metric-label { font-size: 0.8em; color: #666; }
.vo-rec-level { display: inline-block; padding: 3px 10px; border-radius: 3px; font-size: 0.85em; font-weight: 500; margin-bottom: 8px; }
.vo-rec-critical { background: #c6282822; color: #c62828; }
.vo-rec-warning { background: #f57f1733; color: #e65100; }
.vo-rec-notice { background: #f57f1711; color: #f57f17; }
.vo-rec-ok { background: #2e7d3211; color: #2e7d32; }
.vo-rec-good { background: #2e7d3222; color: #2e7d32; }
.vo-rec-actions { margin: 10px 0 0 0; padding-left: 18px; }
.vo-rec-actions li { margin-bottom: 4px; font-size: 0.9em; }
.vo-rec-load-note { margin-top: 10px; padding: 8px 12px; background: #c6282809; border-left: 3px solid #c62828; font-size: 0.85em; }

/* Таблицы результатов */
.vo-results-table { width: 100%; border-collapse: collapse; font-size: 0.9em; margin-bottom: 16px; }
.vo-results-table th { background: #f5f5f5; font-weight: 500; text-align: left; padding: 8px 12px; border: 1px solid #ddd; white-space: nowrap; }
.vo-results-table td { padding: 8px 12px; border: 1px solid #ddd; }
.vo-results-table tr:hover { background: #fffbf5; }
.vo-results-table .vo-num-col { text-align: center; min-width: 48px; }
.vo-results-table .vo-idx-cell { font-weight: 500; }

/* Секция рекомендаций */
.vo-attention-section { margin-top: 24px; padding: 16px 20px; border: 1px solid #c6282844; border-radius: 2px; background: #fdf5f5; }
.vo-attention-section h4 { margin: 0 0 10px 0; color: #c62828; font-size: 1em; }
.vo-attention-section ul { padding-left: 18px; margin: 8px 0; }
.vo-attention-section li { margin-bottom: 6px; font-size: 0.9em; }

/* Ссылки */
.vo-subject-link { color: inherit; text-decoration: none; border-bottom: 1px dashed #999; }
.vo-subject-link:hover { color: #f36f03; border-bottom-color: #f36f03; }

/* Индикатор индекса */
.vo-index-green .vo-index-value { color: #2e7d32; }
.vo-index-yellow .vo-index-value { color: #f57f17; }
.vo-index-red .vo-index-value { color: #c62828; }
.vo-meta { font-size: 0.85em; color: #666; margin-top: 4px; }
.vo-load-high { color: #c62828; font-weight: 500; }

/* Легенда */
.vo-legend-items { margin-top: 8px; }
.vo-legend-item { margin-bottom: 6px; font-size: 0.9em; }
.vo-legend-badge { display: inline-block; color: #fff; font-weight: 500; padding: 1px 8px; border-radius: 3px; font-size: 0.85em; min-width: 40px; text-align: center; margin-right: 6px; }
.vo-antirating-legend { margin-top: 24px; padding: 16px; border: 1px solid #ddd; border-radius: 2px; background: #fafafa; font-size: 0.9em; }

/* Дисклеймер */
.vo-disclaimer { margin-top: 16px; padding: 12px 16px; border: 1px solid #ddd; border-radius: 2px; background: #fafafa; font-size: 0.8em; color: #666; line-height: 1.5; }

/* Методика */
.vo-antirating-methodology { margin-top: 16px; border: 1px solid #ddd; border-radius: 2px; background: #fafafa; }
.vo-antirating-methodology summary { padding: 14px 16px; cursor: pointer; font-weight: 500; font-size: 0.95em; user-select: none; }
.vo-antirating-methodology summary:hover { color: #f36f03; }
.vo-antirating-methodology[open] summary { border-bottom: 1px solid #ddd; }
.vo-methodology-content { padding: 16px 20px; font-size: 0.9em; line-height: 1.6; }
.vo-methodology-content h4 { margin: 20px 0 8px 0; font-size: 1em; color: #000; }
.vo-methodology-content h4:first-child { margin-top: 0; }
.vo-methodology-content ol, .vo-methodology-content ul { padding-left: 20px; }
.vo-methodology-content li { margin-bottom: 8px; }

@media (max-width: 600px) {
    .vo-detail-metrics { flex-direction: column; gap: 12px; }
    .vo-precision-buttons { flex-direction: column; }
    .vo-precision-btn { border-radius: 0 !important; margin-right: 0; margin-bottom: -1px; }
    .vo-precision-btn:first-child { border-radius: 2px 2px 0 0 !important; }
    .vo-precision-btn:last-child { border-radius: 0 0 2px 2px !important; margin-bottom: 0; }
    .vo-results-table { font-size: 0.8em; }
    .vo-results-table th, .vo-results-table td { padding: 6px 8px; }
}
</style>
