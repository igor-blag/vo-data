<?php
// Файл: /templates/dashboard-display.php
if (!defined('ABSPATH')) exit;
?>
<style>
    .vo-dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 20px;
    }

    /* --- НОВАЯ СТИЛИЗАЦИЯ БЛОКОВ --- */

    /* 1. Базовый стиль для ВСЕХ блоков */
    .vo-status-block {
        display: block;
        padding: 20px;
        border-radius: 2px;
        text-decoration: none;
        color: #333;
        transition: all 0.2s ease-in-out;
        
        /* По умолчанию у блока тонкая черная рамка и светлый фон */
        border: 1px solid #000;
        background-color: #f9f9f9;
        background-image: none; /* Убедимся, что по умолчанию градиента нет */
    }

    .vo-status-block:hover {
        background: #ffe1d0;
    }

    .vo-status-block h3 {
        margin-top: 0;
        color: #000;
    }

    .vo-status-block p {
        margin-bottom: 0;
        font-size: 0.9em;
    }

    /* 2. Стилизация для этапа "Предварительные результаты" */
    .status-district_prelim {
        /* Создаем резкий градиент, который заливает 33% блока */
        background-image: linear-gradient(
            0deg, 
            rgba(243, 111, 3, 0.3) 23%, /* Полупрозрачный оранжевый на 33% ширины */
            transparent 23%             /* Далее - прозрачный */
        );
    }
    
    /* 3. Стилизация для этапа "Итоги" */
    .status-district_final {
        /* Тот же градиент, но теперь он "вырос" до 66% */
        background-image: linear-gradient(
            0deg, 
            rgba(243, 111, 3, 0.2) 66%, 
            transparent 66%
        );
    }

    /* 4. Стилизация для этапа "Определены участники регионального этапа" */
    .status-regional_stage {
        /* Здесь градиент не нужен, просто заливаем весь фон цветом */
        background-image: none;
        background-color: rgba(243, 111, 3, 0.2);
    }

    /* Для статуса school_stage отдельный стиль не нужен, т.к. его вид - это базовый стиль .vo-status-block */

</style>

<div class="vo-dashboard-wrapper">
    <h2>Результаты районного этапа по всем предметам<br>за <?php echo ($academic_year - 1) . '/' . $academic_year; ?> учебный год</h2>

    <?php if (empty($subjects)) : ?>
        <div class="vo-results-notice">
            <p>Нет данных о загруженных олимпиадах в этом учебном году.</p>
        </div>
    <?php else : ?>
        <div class="vo-dashboard-grid">
            <?php foreach ($subjects as $subject) : ?>
                <a href="<?php echo esc_url($subject->link); ?>" class="vo-status-block status-<?php echo esc_attr($subject->stage_slug); ?>">
                    <h3><?php echo esc_html($subject->subject_name); ?></h3>
                    <p><?php echo esc_html($subject->stage_name); ?></p>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
