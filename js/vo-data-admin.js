// Файл: /js/vo-data-admin.js

jQuery(document).ready(function ($) {
    
    /**
     * Асинхронно хеширует строку с использованием SHA-256.
     * @param {string} str - Входная строка.
     * @returns {Promise<string>} - Промис, который разрешается в строку с хешем.
     */
    async function sha256(str) {
        const textAsBuffer = new TextEncoder().encode(str);
        const hashBuffer = await crypto.subtle.digest('SHA-256', textAsBuffer);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('');
    }

    /**
     * Конвертирует числовое значение даты из Excel в строку формата 'ДДММГГГГ'.
     * @param {number} excelDate - Числовое представление даты из Excel.
     * @returns {string} - Отформатированная дата или пустая строка.
     */
    function formatExcelDate(excelDate) {
        // 1. Обработка числового формата (старая логика)
        if (typeof excelDate === 'number') {
            if (excelDate <= 0) {
                return '';
            }
            // Excel считает даты с 1900-01-01, но ошибочно считает 1900 год високосным.
            // Поэтому для дат после 29.02.1900 нужна корректировка. Проще всего вычесть 1 день и работать с UTC.
            const date = new Date(Date.UTC(1900, 0, excelDate - 1));
            const day = String(date.getUTCDate()).padStart(2, '0');
            const month = String(date.getUTCMonth() + 1).padStart(2, '0');
            const year = date.getUTCFullYear();
            return `${day}${month}${year}`;
        }

        // 2. НОВАЯ ЛОГИКА: Обработка строкового формата (например, "12.10.2015")
        if (typeof excelDate === 'string') {
            const dateStr = excelDate.trim();
            if (dateStr === '') {
                return '';
            }
            // Ищем шаблон ДД.ММ.ГГГГ. Разделителем может быть точка, слэш или дефис.
            const parts = dateStr.match(/^(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})$/);
            
            if (parts) {
                // parts[0] - вся строка, parts[1] - день, parts[2] - месяц, parts[3] - год
                const day = parts[1].padStart(2, '0');
                const month = parts[2].padStart(2, '0');
                const year = parts[3];
                
                // Простая валидация, чтобы отсечь неверные даты вроде 99.99.2020
                if (parseInt(month, 10) > 12 || parseInt(day, 10) > 31 || parseInt(month, 10) === 0 || parseInt(day, 10) === 0) {
                    return '';
                }
                
                return `${day}${month}${year}`;
            }
        }
        
        // 3. Если формат не числовой и не подходящая строка, возвращаем пустоту
        return '';
    }
    
    
    
    
    
    // --- Переменные и элементы DOM ---
    const dropZone = $('#drop-zone');
    const fileInput = $('#file-input');
    const uploadStatus = $('#upload-status');
    const importBtn = $('#import-btn');
    const cancelBtn = $('#cancel-btn');
    const uploadCard = $('#upload-card');
    const summaryCard = $('#summary-card');
    const resultsCard = $('#results-card');

    let allFilesData = []; // Здесь будем хранить данные всех валидных файлов

    // --- Обработчики событий для Drag & Drop ---
    
    // Улучшенный обработчик клика на drop-zone
    dropZone.on('click', (e) => {
        e.preventDefault();
        e.stopPropagation();
        fileInput.trigger('click');
    });
    
    // Улучшенный обработчик изменения файлов
    fileInput.on('change', function(e) {
        const files = e.target.files;
        console.log('Files selected via input:', files.length);
        
        if (files && files.length > 0) {
            handleFiles(files);
        }
        
        // Очищаем input после обработки для корректной работы при повторном выборе
        // Делаем это асинхронно, чтобы не помешать обработке
        setTimeout(() => {
            $(this).val('');
        }, 100);
    });

    // Предотвращаем всплытие события click на самом input
    fileInput.on('click', (e) => {
        e.stopPropagation();
    });

    dropZone.on('dragover', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.addClass('dragover');
    });
    
    dropZone.on('dragleave', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.removeClass('dragover');
    });
    
    dropZone.on('drop', (e) => {
        e.preventDefault();
        e.stopPropagation();
        dropZone.removeClass('dragover');
        
        const files = e.originalEvent.dataTransfer.files;
        console.log('Files dropped:', files.length);
        
        if (files && files.length > 0) {
            handleFiles(files);
        }
    });
    
    // --- Обработчики кнопок ---
    cancelBtn.on('click', resetUI);

    // --- Основная функция обработки файлов ---
    async function handleFiles(files) {
        if (!files || files.length === 0) {
            console.warn('No files provided to handleFiles');
            return;
        }
        
        const filesArray = Array.from(files);
        console.log('Processing files:', filesArray.map(f => f.name));
        
        resetUI();
        uploadStatus.text(`Обработка ${filesArray.length} файлов...`);

        const parsingPromises = filesArray.map(file => parseFile(file));

        try {
            const parsedResults = await Promise.all(parsingPromises);
            console.log('All files parsed successfully');
            // --- ИЗМЕНЕНИЕ: Запускаем новую функцию валидации и ОБРАБОТКИ ---
            await validateAndProcessData(parsedResults);
        } catch (error) {
            console.error('Error during file processing:', error);
            showError(error.message);
        }
    }
    // --- Функция парсинга одного файла ---
    function parseFile(file) {
        return new Promise((resolve, reject) => {
            console.log(`Starting to parse file: ${file.name}`);
            
            const reader = new FileReader();
            
            reader.onload = (e) => {
                try {
                    const data = new Uint8Array(e.target.result);
                    const workbook = XLSX.read(data, { type: 'array' });
                    const sheet = workbook.Sheets[workbook.SheetNames[0]];
                    const jsonData = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: "" });
                    
                    console.log(`File ${file.name} parsed successfully, rows: ${jsonData.length}`);
                    resolve({ fileName: file.name, data: jsonData });
                } catch (err) {
                    console.error(`Error parsing file ${file.name}:`, err);
                    reject(new Error(`Не удалось прочитать файл ${file.name}: ${err.message}`));
                }
            };
            
            reader.onerror = (err) => {
                console.error(`FileReader error for ${file.name}:`, err);
                reject(new Error(`Ошибка чтения файла ${file.name}.`));
            };
            
            reader.readAsArrayBuffer(file);
        });
    }
    
    async function validateAndProcessData(results) {
        console.log('Starting validation and data processing');
        
        const importType = $('#import-type').val();
        // НОВЫЙ КОД: Проверяем, включен ли импорт за прошлый год
        const isPreviousYearImport = $('#import-previous-year').is(':checked');
        
        const settings = vo_data_ajax.settings || {};
        if (!settings.col_snils || !settings.col_last_name || !settings.col_first_name || !settings.col_patronymic || !settings.col_dob) {
             throw new Error('Не все настройки колонок (СНИЛС, ФИО, Дата рождения) заданы в плагине.');
        }
    
        const subjectCell = settings.subject_cell || 'C3';
        const secretPrefix = settings.secret_prefix || 'Pre';
        const startRow = parseInt(settings.start_row, 10) || 6;
        let commonSubject = null;
        let classCounts = {};
        allProcessedRecords = []; 
    
        const idx = {
            lastName: colLetterToIndex(settings.col_last_name),
            firstName: colLetterToIndex(settings.col_first_name),
            patronymic: colLetterToIndex(settings.col_patronymic),
            dob: colLetterToIndex(settings.col_dob),
            gender: colLetterToIndex(settings.col_gender),
            snils: colLetterToIndex(settings.col_snils),
            class: colLetterToIndex(settings.col_class),
            classGrade: colLetterToIndex(settings.col_class_grade),
            schoolFullName: colLetterToIndex(settings.col_school_full_name),
            schoolShortName: colLetterToIndex(settings.col_school_short_name),
            schoolAddress: colLetterToIndex(settings.col_school_address),
            score: colLetterToIndex(settings.col_score),
            status: colLetterToIndex(settings.col_status),
        };
    
        for (const result of results) {
            const data = result.data;
            if (data.length < startRow) {
                throw new Error(`Файл ${result.fileName} слишком короткий (строк: ${data.length}, требуется минимум: ${startRow}).`);
            }
    
            const sheet = XLSX.utils.aoa_to_sheet(data);
            const subject = sheet[subjectCell] ? sheet[subjectCell].v : null;
    
            if (!subject) {
                throw new Error(`В файле ${result.fileName} не найден предмет в ячейке ${subjectCell}.`);
            }
    
            if (commonSubject === null) {
                commonSubject = subject;
            } else if (commonSubject !== subject) {
                throw new Error(`Обнаружены файлы с разными предметами ('${commonSubject}' и '${subject}'). Загрузите файлы только для одного предмета.`);
            }
    
            const dataRows = data.slice(startRow);
    
            const processingPromises = dataRows.map(async (row) => {
                if (!row || row.every(cell => cell === "")) return null;
    
                const scoreValue = row[idx.score];
                if ( (importType === 'district_prelim' || importType === 'district_final') && (scoreValue === "" || scoreValue === null || typeof scoreValue === 'undefined') ) {
                    return null;
                }
    
                // --- НОВАЯ ЛОГИКА ФИЛЬТРАЦИИ ДЛЯ ПРОШЛОГО ГОДА ---
                if (isPreviousYearImport) {
                    const status = String(row[idx.status] || '').trim().toLowerCase();
                    // Пропускаем всех, кто не победитель и не призер
                    console.log(status);
                    if (status !== 'победитель' && status !== 'призёр') {
                        return null;
                    }
                }
                // --- КОНЕЦ НОВОЙ ЛОГИКИ ---
    
                const snilsRaw = row[idx.snils] ? String(row[idx.snils]).replace(/[^\d]/g, '') : '';
                const lastName = row[idx.lastName] || '';
                const firstName = row[idx.firstName] || '';
                const patronymic = row[idx.patronymic] || '';
                const dobRaw = row[idx.dob] || '';
                let uuid = secretPrefix;
                
                if (snilsRaw && snilsRaw.length >= 11) {
                    uuid = await sha256(`${secretPrefix}_${snilsRaw}`);
                } else {
                    const dobFormatted = formatExcelDate(dobRaw);
                    if (lastName && firstName && dobFormatted) {
                        const combinedString = `${secretPrefix}_${lastName}${firstName}${dobFormatted}`;
                        uuid = await sha256(combinedString.replace(/\s+/g, '').toLowerCase());
                    } else {
                        return null;
                    }
                }
                
                const classGrade = parseInt(row[idx.classGrade], 10);
                 if (!isNaN(classGrade)) {
                    classCounts[classGrade] = (classCounts[classGrade] || 0) + 1;
                }
    
                return { uuid, last_name: lastName, first_name: firstName, patronymic: patronymic, gender: row[idx.gender] || '', class: row[idx.class] || '0', class_grade: row[idx.classGrade] || '0', school_full_name: row[idx.schoolFullName] || '', school_short_name: row[idx.schoolShortName] || '', school_address: row[idx.schoolAddress] || '', score: scoreValue || '0', status: row[idx.status] || '' };
            });
    
            const recordsFromFile = (await Promise.all(processingPromises)).filter(Boolean);
            allProcessedRecords.push(...recordsFromFile);
        }
        
        if (allProcessedRecords.length === 0) {
            throw new Error('Не найдено ни одной корректной записи для импорта. Проверьте данные в файлах (наличие баллов, статусы победителей/призеров).');
        }
        
        console.log(`Validation and processing successful, total records: ${allProcessedRecords.length}`);
        uploadCard.hide();
        displaySummary(commonSubject, classCounts);
        summaryCard.show();
    }    
    // --- Отображение сводки ---
    function displaySummary(subject, counts) {
        $('#summary-subject').text(subject);
        const tableBody = $('#summary-table-body');
        tableBody.empty();
        
        const sortedClasses = Object.keys(counts).sort((a, b) => parseInt(a) - parseInt(b));
        sortedClasses.forEach(classNum => {
            tableBody.append(`<tr><td>${classNum}</td><td>${counts[classNum]}</td></tr>`);
        });
        
        console.log(`Summary displayed: Subject - ${subject}, Total classes - ${sortedClasses.length}`);
    }
    
    // --- ОБНОВЛЕННАЯ ЛОГИКА ИМПОРТА С ПРЕДВАРИТЕЛЬНОЙ ПРОВЕРКОЙ ---

    // Удаляем старый обработчик и вешаем новый
    importBtn.off('click').on('click', startImportProcess);

    /**
     * Шаг 1: Запускает предварительную проверку на сервере.
     */
    function startImportProcess() {
        importBtn.prop('disabled', true).text('Проверка...');

        const importData = {
            action: 'vo_data_pre_import_check',
            nonce: vo_data_ajax.nonce,
            subject: $('#summary-subject').text(),
            import_type: $('#import-type').val(),
            is_previous_year: $('#import-previous-year').is(':checked')
        };

        $.ajax({
            url: vo_data_ajax.ajax_url,
            type: 'POST',
            data: importData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Проверка пройдена, запускаем основной импорт
                    executeImport();
                } else {
                    // Сервер вернул ошибку или предупреждение
                    if (response.data.confirm) {
                        // Это предупреждение, показываем диалог confirm
                        if (window.confirm(response.data.message)) {
                            // Пользователь согласился, запускаем основной импорт
                            executeImport();
                        } else {
                            // Пользователь отменил, сбрасываем UI
                            importBtn.prop('disabled', false).text('Импортировать данные');
                        }
                    } else {
                        // Это жесткая ошибка, просто показываем ее
                        showResult('error', `Ошибка: ${response.data.message}`);
                        importBtn.prop('disabled', false).text('Импортировать данные');
                    }
                }
            },
            error: function() {
                showResult('error', 'Критическая ошибка во время предварительной проверки.');
                importBtn.prop('disabled', false).text('Импортировать данные');
            }
        });
    }

    /**
     * Шаг 2: Выполняет основную загрузку данных на сервер (старая функция processImport).
     */
    function executeImport() {
        importBtn.prop('disabled', true).text('Импорт...');
        
        console.log('Starting main import process');
        
        const importData = {
            action: 'vo_data_import',
            nonce: vo_data_ajax.nonce,
            data: JSON.stringify({
                rows: allProcessedRecords,
                subject: $('#summary-subject').text(),
                import_type: $('#import-type').val(),
                is_previous_year: $('#import-previous-year').is(':checked')
            })
        };
        
        $.ajax({
            url: vo_data_ajax.ajax_url,
            type: 'POST',
            data: importData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showResult('success', response.data.message);
                } else {
                    showResult('error', `Ошибка: ${response.data.message}`);
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('Import failed:', textStatus, errorThrown);
                let errorMessage = `Критическая ошибка сервера: ${textStatus}`;
                if (jqXHR.responseJSON && jqXHR.responseJSON.data && jqXHR.responseJSON.data.message) {
                    errorMessage = `Ошибка: ${jqXHR.responseJSON.data.message}`;
                } else if (jqXHR.responseText) {
                    errorMessage = `Ошибка сервера. Проверьте логи.`;
                }
                showResult('error', errorMessage);
            },
            complete: function() {
                importBtn.prop('disabled', false).text('Импортировать данные');
            }
        });
    }

    // --- Вспомогательные функции (остаются без изменений) ---
    function colLetterToIndex(letter) {
        if (!letter || typeof letter !== 'string') return -1;
        return letter.toUpperCase().charCodeAt(0) - 'A'.charCodeAt(0);
    }
    
    function resetUI() {
        fileInput.val('');
        uploadStatus.empty();
        summaryCard.hide();
        resultsCard.hide();
        uploadCard.show();
        allProcessedRecords = [];
        console.log('UI reset');
    }

    function showError(message) {
        uploadStatus.html(`<span style="color: red;">${message}</span>`);
        console.error('Error shown to user:', message);
    }

    function showResult(type, message) {
        summaryCard.hide();
        resultsCard.html(`<div class="notice notice-${type} is-dismissible"><p>${message}</p></div>`).show();
        console.log(`Result shown: ${type} - ${message}`);
    }
}); // Конец jQuery(document).ready