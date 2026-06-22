<?php
class CRTC_Calendar_Control extends WP_Customize_Control {

    public $type = 'crtc_calendar';

    public function render_content() {
        $data = crtc_get_data();
        $json = wp_json_encode($data);
        ?>
        <label>
            <?php if (!empty($this->label)) : ?>
                <span class="customize-control-title"><?php echo esc_html($this->label); ?></span>
            <?php endif; ?>
            <?php if (!empty($this->description)) : ?>
                <span class="description customize-control-description"><?php echo esc_html($this->description); ?></span>
            <?php endif; ?>
        </label>

        <div class="crtc-customizer-control">
            <textarea class="crtc-data-input" data-customize-setting-link="<?php echo esc_attr($this->settings['default']->id); ?>" style="position:absolute;left:-9999px;opacity:0;height:0;overflow:hidden;"><?php echo esc_textarea($json); ?></textarea>

            <div class="crtc-years-bar">
                <div class="crtc-year-tabs"></div>
                <button type="button" class="button crtc-add-year">+ Novo Ano</button>
            </div>

            <div class="crtc-calendar-editor" style="display:none;">
                <div class="crtc-current-year-header">
                    <strong>Ano: <span class="crtc-current-year-label"></span></strong>
                    <button type="button" class="button button-small crtc-remove-year" style="color:#a00;">Remover ano</button>
                </div>
                <table class="widefat crtc-month-table">
                    <thead>
                        <tr>
                            <th style="width:26%;">Mês</th>
                            <th style="width:37%;">Início</th>
                            <th style="width:37%;">Fim</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <p class="description" style="margin-top:8px;">Adicione anos e preencha as datas de recarga para cada mês. Clique em "Publicar" para salvar.</p>
        </div>

        <style>
            .crtc-customizer-control { margin-top: 4px; }
            .crtc-years-bar { display:flex; flex-wrap:wrap; gap:6px; align-items:center; margin-bottom:10px; }
            .crtc-year-tabs { display:flex; flex-wrap:wrap; gap:4px; flex:1; }
            .crtc-year-tab { padding:3px 10px; background:#f0f0f1; border:1px solid #c3c4c7; border-radius:3px; cursor:pointer; font-size:12px; line-height:1.8; }
            .crtc-year-tab.active { background:#2271b1; color:#fff; border-color:#2271b1; }
            .crtc-year-tab:hover:not(.active) { background:#dcdcde; }
            .crtc-current-year-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:6px; padding:6px 0; border-bottom:1px solid #ddd; }
            .crtc-month-table { border-collapse:collapse; width:100%; }
            .crtc-month-table th { font-size:11px; font-weight:600; padding:4px 6px; }
            .crtc-month-table td { padding:3px 4px; vertical-align:middle; }
            .crtc-month-table input[type="date"] { width:100%; font-size:11px; padding:2px 4px; min-width:0; box-sizing:border-box; }
            .crtc-month-table .month-name { font-size:12px; font-weight:500; white-space:nowrap; }
            .crtc-add-year { white-space:nowrap; }
        </style>

        <script>
        jQuery(function($) {
            var control = $('#customize-control-<?php echo esc_js($this->id); ?>');
            if (!control.length) return;

            var $storage = control.find('.crtc-data-input');
            var $tabs = control.find('.crtc-year-tabs');
            var $editor = control.find('.crtc-calendar-editor');
            var $tableBody = control.find('.crtc-month-table tbody');
            var $yearLabel = control.find('.crtc-current-year-label');
            var $removeBtn = control.find('.crtc-remove-year');
            var $addBtn = control.find('.crtc-add-year');

            var monthNames = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
            var data = {};
            var currentYear = null;

            function loadData() {
                try {
                    data = JSON.parse($storage.val()) || {};
                } catch(e) {
                    data = {};
                }
                renderTabs();
                if (currentYear && data[currentYear]) {
                    selectYear(currentYear);
                } else {
                    var years = Object.keys(data).sort();
                    if (years.length) {
                        selectYear(parseInt(years[0]));
                    } else {
                        selectYear(null);
                    }
                }
            }

            function saveData() {
                $storage.val(JSON.stringify(data));
                $storage.trigger('change');
            }

            function renderTabs() {
                $tabs.empty();
                var years = Object.keys(data).map(Number).sort();
                if (years.length === 0) {
                    $tabs.append('<span style="color:#999;font-size:12px;">Nenhum ano cadastrado.</span>');
                    return;
                }
                years.forEach(function(y) {
                    var $tab = $('<span class="crtc-year-tab">' + y + '</span>');
                    $tab.on('click', function() { selectYear(y); });
                    if (currentYear === y) $tab.addClass('active');
                    $tabs.append($tab);
                });
            }

            function selectYear(year) {
                currentYear = year;
                $tabs.find('.crtc-year-tab').removeClass('active');
                $tabs.find('.crtc-year-tab').filter(function() { return parseInt($(this).text()) === year; }).addClass('active');

                if (year && data[year]) {
                    $editor.show();
                    $yearLabel.text(year);
                    renderTable(year);
                } else if (year) {
                    $editor.show();
                    $yearLabel.text(year);
                    data[year] = [];
                    renderTable(year);
                } else {
                    $editor.hide();
                }
            }

            function renderTable(year) {
                $tableBody.empty();
                var months = data[year] || [];
                var monthMap = {};
                months.forEach(function(m) { monthMap[m.mes] = m; });

                for (var i = 1; i <= 12; i++) {
                    var m = monthMap[i] || { mes: i, inicio: '', fim: '' };
                    var $row = $('<tr>');
                    $row.append('<td><span class="month-name">' + monthNames[i-1] + '</span></td>');
                    var $inicio = $('<input type="date" class="crtc-date-inicio" value="' + (m.inicio || '') + '">');
                    var $fim = $('<input type="date" class="crtc-date-fim" value="' + (m.fim || '') + '">');

                    $inicio.on('change', { month: i }, function(e) {
                        updateMonth(year, e.data.month, 'inicio', $(this).val());
                    });
                    $fim.on('change', { month: i }, function(e) {
                        updateMonth(year, e.data.month, 'fim', $(this).val());
                    });

                    $row.append($('<td>').append($inicio));
                    $row.append($('<td>').append($fim));
                    $tableBody.append($row);
                }
            }

            function updateMonth(year, month, field, value) {
                if (!data[year]) data[year] = [];
                var idx = -1;
                for (var i = 0; i < data[year].length; i++) {
                    if (data[year][i].mes === month) { idx = i; break; }
                }
                if (idx === -1) {
                    data[year].push({ mes: month, inicio: '', fim: '' });
                    idx = data[year].length - 1;
                }
                data[year][idx][field] = value;

                if (!data[year][idx].inicio && !data[year][idx].fim) {
                    data[year].splice(idx, 1);
                }
                saveData();
            }

            $addBtn.on('click', function() {
                var year = prompt('Digite o ano (ex: 2026):');
                if (!year) return;
                year = parseInt(year);
                if (isNaN(year) || year < 2000 || year > 2100) {
                    alert('Ano inválido. Digite um ano entre 2000 e 2100.');
                    return;
                }
                if (data[year]) {
                    alert('Ano ' + year + ' já existe.');
                    return;
                }
                data[year] = [];
                saveData();
                renderTabs();
                selectYear(year);
            });

            $removeBtn.on('click', function() {
                if (!currentYear) return;
                if (!confirm('Remover o ano ' + currentYear + ' e todos os seus dados?')) return;
                delete data[currentYear];
                saveData();
                var years = Object.keys(data).map(Number).sort();
                if (years.length) {
                    selectYear(years[0]);
                } else {
                    selectYear(null);
                }
                renderTabs();
            });

            loadData();
        });
        </script>
        <?php
    }
}
