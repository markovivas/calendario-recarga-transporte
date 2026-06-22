<?php
/**
 * Plugin Name: Calendário de Recarga do Transporte Coletivo
 * Description: Gerencia calendários de recarga do transporte coletivo por ano. Exibe widget na intranet com informações sobre recargas atuais e futuras.
 * Version: 1.0.0
 * Author: Marco Vivas
 * Text Domain: calendario-recarga-tc
 */

defined('ABSPATH') || exit;

define('CRTC_VERSION', '1.0.0');
define('CRTC_PLUGIN_URL', plugin_dir_url(__FILE__));
define('CRTC_PLUGIN_DIR', plugin_dir_path(__FILE__));

function crtc_mes_name($num) {
    $meses = array(
        1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',
        4 => 'Abril', 5 => 'Maio', 6 => 'Junho',
        7 => 'Julho', 8 => 'Agosto', 9 => 'Setembro',
        10 => 'Outubro', 11 => 'Novembro', 12 => 'Dezembro',
    );
    return isset($meses[(int)$num]) ? $meses[(int)$num] : '';
}

function crtc_get_data() {
    $data = get_option('calendario_recarga_data', '{}');
    if (is_string($data)) {
        $decoded = json_decode($data, true);
        return is_array($decoded) ? $decoded : array();
    }
    if (is_array($data)) return $data;
    return array();
}

function crtc_set_data($data) {
    update_option('calendario_recarga_data', is_array($data) ? $data : array());
}

function crtc_format_date_br($date) {
    if (!$date) return '';
    $ts = strtotime($date);
    return $ts ? date('d/m/Y', $ts) : '';
}

function crtc_format_date_short($date) {
    if (!$date) return '';
    $ts = strtotime($date);
    return $ts ? date('d/m', $ts) : '';
}

if (!class_exists('Calendario_Recarga_Transporte')) :

class Calendario_Recarga_Transporte {

    private static $instance = null;
    private $buffer_started = false;

    public static function init() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->setup_hooks();
    }

    private function setup_hooks() {
        add_action('customize_register', array($this, 'customize_register'));
        add_action('widgets_init', array($this, 'register_widget'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        add_shortcode('calendario_recarga_completo', array($this, 'shortcode_full_calendar'));
        add_shortcode('calendario_recarga_widget', array($this, 'shortcode_widget'));
        add_filter('query_vars', array($this, 'add_query_vars'));
        add_action('template_redirect', array($this, 'template_redirect'));
        add_action('wp', array($this, 'inject_start_buffer'));
        add_action('wp_footer', array($this, 'inject_home_section'), 99999);
    }

    public function enqueue_assets() {
        wp_enqueue_style('crtc-frontend', CRTC_PLUGIN_URL . 'assets/css/frontend.css', array(), CRTC_VERSION);
    }

    public function add_query_vars($vars) {
        $vars[] = 'crtc_calendario';
        $vars[] = 'crtc_ano';
        return $vars;
    }

    public function template_redirect() {
        $calendario_page = get_query_var('crtc_calendario');
        if ($calendario_page) {
            $this->render_full_calendar_page();
            exit;
        }
    }

    public function inject_start_buffer() {
        if (is_front_page() && !is_admin() && !is_customize_preview() && !get_query_var('crtc_calendario')) {
            $this->buffer_started = true;
            ob_start();
        }
    }

    public function inject_home_section() {
        if (!$this->buffer_started) return;
        if (!ob_get_level()) return;

        $content = ob_get_clean();
        if ($content === false) return;

        $section = $this->render_home_section();
        $count = 0;
        $content = preg_replace(
            '#(</section>)\s*(<!-- Aniversariantes do Dia -->)#',
            "$1\n\n" . $section . "\n\n$2",
            $content,
            1,
            $count
        );
        // Fallback: inject before </main> if marker not found
        if (!$count) {
            $content = str_replace('</main>', $section . "\n</main>", $content);
        }

        echo $content;
    }

    // ============================================================
    //  CUSTOMIZER INTEGRATION
    // ============================================================

    public function customize_register($wp_customize) {
        if (!class_exists('CRTC_Calendar_Control')) {
            require_once CRTC_PLUGIN_DIR . 'includes/class-crtc-control.php';
        }

        $wp_customize->add_panel('crtc_panel', array(
            'title'    => 'Recarga do Transporte Coletivo',
            'priority' => 40,
        ));

        $wp_customize->add_section('crtc_section', array(
            'title'    => 'Gerenciar Calendários',
            'panel'    => 'crtc_panel',
            'priority' => 10,
        ));

        $wp_customize->add_setting('calendario_recarga_data', array(
            'type'              => 'option',
            'default'           => '{}',
            'sanitize_callback' => array($this, 'sanitize_calendar_data'),
            'transport'         => 'postMessage',
        ));

        $wp_customize->add_control(new CRTC_Calendar_Control($wp_customize, 'calendario_recarga_data', array(
            'label'    => 'Calendários por Ano',
            'section'  => 'crtc_section',
            'settings' => 'calendario_recarga_data',
        )));

        // === Seção: Aparência ===
        $wp_customize->add_section('crtc_aparicao_section', array(
            'title'    => 'Aparência',
            'panel'    => 'crtc_panel',
            'priority' => 20,
        ));

        $wp_customize->add_setting('crtc_logo', array(
            'type'              => 'option',
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ));
        $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'crtc_logo', array(
            'label'       => 'Logo (PNG recomendado)',
            'description' => 'Envie uma imagem para exibir no lugar do ícone padrão.',
            'section'     => 'crtc_aparicao_section',
            'settings'    => 'crtc_logo',
        )));

        $wp_customize->add_setting('crtc_logo_width', array(
            'type'              => 'option',
            'default'           => '80',
            'sanitize_callback' => 'absint',
        ));
        $wp_customize->add_control('crtc_logo_width', array(
            'label'       => 'Largura do Logo (px)',
            'description' => 'Altura ajustada automaticamente.',
            'section'     => 'crtc_aparicao_section',
            'type'        => 'number',
            'input_attrs' => array('min' => 40, 'max' => 200, 'step' => 5),
        ));
    }

    public function sanitize_calendar_data($value) {
        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            return '{}';
        }

        $clean = array();
        foreach ($decoded as $year => $months) {
            $year = (int)$year;
            if ($year < 2000 || $year > 2100) continue;
            if (!is_array($months)) continue;

            $clean_months = array();
            foreach ($months as $month) {
                if (!isset($month['mes']) || !isset($month['inicio']) || !isset($month['fim'])) continue;
                $m = (int)$month['mes'];
                if ($m < 1 || $m > 12) continue;
                $inicio = $this->validate_date($month['inicio']);
                $fim = $this->validate_date($month['fim']);
                if (!$inicio || !$fim) continue;

                $clean_months[] = array(
                    'mes'   => $m,
                    'inicio' => $inicio,
                    'fim'    => $fim,
                );
            }

            if (!empty($clean_months)) {
                usort($clean_months, function($a, $b) {
                    return $a['mes'] - $b['mes'];
                });
                $clean[$year] = $clean_months;
            }
        }

        return wp_json_encode($clean);
    }

    private function validate_date($date) {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return false;
        $ts = strtotime($date);
        if (!$ts) return false;
        return date('Y-m-d', $ts);
    }

    // ============================================================
    //  RECHARGE LOGIC
    // ============================================================

    public function get_recharge_info($data = null) {
        if (null === $data) $data = crtc_get_data();
        if (empty($data)) {
            return array('status' => 'none', 'message' => 'Nenhum calendário cadastrado.');
        }

        $today = current_time('Y-m-d');
        $today_ts = strtotime($today);
        $current_year = (int)date('Y');

        ksort($data);

        foreach ($data as $year => $months) {
            if ($year < $current_year) continue;

            usort($months, function($a, $b) {
                return $a['mes'] - $b['mes'];
            });

            foreach ($months as $month) {
                $inicio = $month['inicio'];
                $fim = $month['fim'];

                if ($today > $fim) continue;

                if ($today >= $inicio && $today <= $fim) {
                    $days_left = (int)ceil((strtotime($fim) - $today_ts) / DAY_IN_SECONDS);
                    return array(
                        'status'    => 'active',
                        'year'      => $year,
                        'month_num' => (int)$month['mes'],
                        'inicio'    => $inicio,
                        'fim'       => $fim,
                        'days_left' => $days_left,
                    );
                }

                if ($today < $inicio) {
                    $days_until = (int)ceil((strtotime($inicio) - $today_ts) / DAY_IN_SECONDS);
                    return array(
                        'status'     => 'upcoming',
                        'year'       => $year,
                        'month_num'  => (int)$month['mes'],
                        'inicio'     => $inicio,
                        'fim'        => $fim,
                        'days_until' => $days_until,
                    );
                }
            }
        }

        return array('status' => 'none', 'message' => 'Nenhuma recarga futura encontrada.');
    }

    // ============================================================
    //  WIDGET MARKUP RENDERER
    // ============================================================

    public function render_widget_markup($instance = array()) {
        $data = crtc_get_data();
        $info = $this->get_recharge_info($data);
        $title = !empty($instance['title']) ? $instance['title'] : 'Recarga do Transporte Coletivo';

        ob_start();
        ?>
        <div class="crtc-widget">
            <div class="crtc-widget-header">
                <span class="crtc-icon">🚌</span>
                <span class="crtc-title"><?php echo esc_html($title); ?></span>
            </div>
            <div class="crtc-widget-body">
                <?php if ($info['status'] === 'active') : ?>
                    <div class="crtc-status-active">
                        <div class="crtc-badge">✅ A recarga está aberta!</div>
                        <div class="crtc-period">
                            Período: <?php echo crtc_format_date_short($info['inicio']); ?> até <?php echo crtc_format_date_short($info['fim']); ?>
                        </div>
                        <?php if ($info['days_left'] > 0) : ?>
                        <div class="crtc-countdown pulse">
                            ⏳ Restam <strong><?php echo $info['days_left']; ?></strong> dia<?php echo $info['days_left'] != 1 ? 's' : ''; ?> para encerrar.
                        </div>
                        <?php else : ?>
                        <div class="crtc-countdown">
                            ⏳ Último dia de recarga!
                        </div>
                        <?php endif; ?>
                    </div>
                <?php elseif ($info['status'] === 'upcoming') : ?>
                    <div class="crtc-status-upcoming">
                        <div class="crtc-next-label">Próxima Recarga</div>
                        <div class="crtc-month-name">📅 <?php echo crtc_mes_name($info['month_num']); ?></div>
                        <div class="crtc-period">
                            <?php echo crtc_format_date_short($info['inicio']); ?> a <?php echo crtc_format_date_short($info['fim']); ?>
                        </div>
                        <div class="crtc-countdown">
                            ⏳ Faltam <strong><?php echo $info['days_until']; ?></strong> dia<?php echo $info['days_until'] != 1 ? 's' : ''; ?> para o início.
                        </div>
                        <div class="crtc-note">A recarga ainda não começou.</div>
                    </div>
                <?php else : ?>
                    <div class="crtc-status-none">
                        <p><?php echo esc_html($info['message']); ?></p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="crtc-widget-footer">
                <a href="<?php echo esc_url(add_query_arg('crtc_calendario', '1', home_url())); ?>" class="crtc-button">
                    Ver calendário completo →
                </a>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ============================================================
    //  HOME SECTION RENDERER (weather-style layout)
    // ============================================================

    public function render_home_section() {
        $data = crtc_get_data();
        $info = $this->get_recharge_info($data);
        $upcoming = $this->get_upcoming_months($data, 4);

        ob_start();
        ?>
        <section class="crtc-home-section">
            <div class="section-header">
                <h3>Recarga do Transporte Coletivo</h3>
            </div>
            <div class="crtc-home-container">
                <?php
                $logo_url = get_option('crtc_logo', '');
                $logo_width = get_option('crtc_logo_width', 80);
                ?>
                <div class="crtc-home-col crtc-home-col-logo">
                    <?php if ($logo_url) : ?>
                        <div class="crtc-home-logo"><img src="<?php echo esc_url($logo_url); ?>" alt=""></div>
                    <?php endif; ?>
                </div>
                <div class="crtc-home-col crtc-home-col-status">
                    <?php if ($info['status'] === 'active') : ?>
                        <div class="crtc-home-badge">✅ Recarga Aberta!</div>
                        <div class="crtc-home-days">⏳ <?php echo $info['days_left']; ?> dia<?php echo $info['days_left'] != 1 ? 's' : ''; ?></div>
                        <div class="crtc-home-label">restantes para encerrar</div>
                    <?php elseif ($info['status'] === 'upcoming') : ?>
                        <div class="crtc-home-label-small">PRÓXIMA RECARGA</div>
                        <div class="crtc-home-month"><?php echo crtc_mes_name($info['month_num']); ?></div>
                        <div class="crtc-home-days">⏳ Faltam <?php echo $info['days_until']; ?> dia<?php echo $info['days_until'] != 1 ? 's' : ''; ?></div>
                        <div class="crtc-home-label">para o início</div>
                    <?php else : ?>
                        <div class="crtc-home-label-small">RECARGA</div>
                        <div class="crtc-home-label"><?php echo esc_html($info['message']); ?></div>
                    <?php endif; ?>
                </div>
                <div class="crtc-home-col crtc-home-col-table">
                    <?php if (!empty($upcoming)) : ?>
                    <div class="crtc-home-upcoming-title">📅 Próximas Recargas</div>
                    <ul class="crtc-home-upcoming-list">
                        <?php foreach ($upcoming as $u) : ?>
                        <li>
                            <span class="crtc-up-month"><?php echo crtc_mes_name($u['month_num']); ?>:</span>
                            <span class="crtc-up-date"><?php echo crtc_format_date_short($u['inicio']); ?> a <?php echo crtc_format_date_short($u['fim']); ?></span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                    <a href="<?php echo esc_url(add_query_arg('crtc_calendario', '1', home_url())); ?>" class="crtc-home-button">
                        Ver calendário completo →
                    </a>
                </div>
            </div>
        </section>
        <?php
        return ob_get_clean();
    }

    private function get_upcoming_months($data, $limit = 4) {
        $today = current_time('Y-m-d');
        $upcoming = array();

        $years = array_keys($data);
        sort($years);

        foreach ($years as $year) {
            $months = $data[$year];
            usort($months, function($a, $b) {
                return $a['mes'] - $b['mes'];
            });

            foreach ($months as $month) {
                if (count($upcoming) >= $limit) break 2;
                if ($today <= $month['fim']) {
                    $upcoming[] = array(
                        'year'      => $year,
                        'month_num' => $month['mes'],
                        'inicio'    => $month['inicio'],
                        'fim'       => $month['fim'],
                    );
                }
            }
        }

        return $upcoming;
    }

    // ============================================================
    //  WIDGET CLASS
    // ============================================================

    public function register_widget() {
        register_widget('CRTC_Widget');
    }

    // ============================================================
    //  SHORTCODES
    // ============================================================

    public function shortcode_widget($atts) {
        return $this->render_widget_markup((array)$atts);
    }

    public function shortcode_full_calendar($atts) {
        $atts = shortcode_atts(array('year' => date('Y')), $atts);
        $year = (int)$atts['year'];
        return $this->render_full_calendar_table($year);
    }

    // ============================================================
    //  FULL CALENDAR PAGE
    // ============================================================

    public function render_full_calendar_page() {
        $data = crtc_get_data();
        $current_year = (int)date('Y');
        $year = (int)get_query_var('crtc_ano');
        if (!$year || !isset($data[$year])) {
            $year = $current_year;
        }

        $available_years = array_keys($data);
        rsort($available_years);
        $selected_year = $year;

        get_header();
        ?>
        <main class="crtc-full-page">
            <div class="crtc-full-container">
                <h1 class="crtc-full-title">🚌 Calendário de Recarga do Transporte Coletivo</h1>

                <?php if (!empty($available_years)) : ?>
                <div class="crtc-year-nav">
                    <span>Ano:</span>
                    <?php foreach ($available_years as $y) : ?>
                        <a href="<?php echo esc_url(add_query_arg(array('crtc_calendario' => '1', 'crtc_ano' => $y), home_url())); ?>"
                           class="crtc-year-link <?php echo $y === $selected_year ? 'active' : ''; ?>">
                            <?php echo $y; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php echo $this->render_full_calendar_table($selected_year); ?>

                <a href="<?php echo esc_url(home_url()); ?>" class="crtc-back-link">&larr; Voltar para a página inicial</a>
            </div>
        </main>
        <?php
        get_footer();
    }

    public function render_full_calendar_table($year) {
        $data = crtc_get_data();
        $year_data = isset($data[$year]) ? $data[$year] : array();
        $info = $this->get_recharge_info_for_year($year);

        if (empty($year_data)) {
            return '<div class="crtc-empty">Nenhum calendário cadastrado para ' . $year . '.</div>';
        }

        usort($year_data, function($a, $b) {
            return $a['mes'] - $b['mes'];
        });

        ob_start();
        ?>
        <div class="crtc-full-table-wrapper">
            <table class="crtc-full-table">
                <thead>
                    <tr>
                        <th>Mês</th>
                        <th>Data de Início</th>
                        <th>Data de Término</th>
                        <th>Situação</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($year_data as $month) :
                        $mes_num = (int)$month['mes'];
                        $inicio = $month['inicio'];
                        $fim = $month['fim'];
                        $today = current_time('Y-m-d');

                        if ($today >= $inicio && $today <= $fim) {
                            $class = 'crtc-row-active';
                            $situacao = '<span class="crtc-badge-small active">🔵 Em andamento</span>';
                        } elseif ($today < $inicio) {
                            $class = 'crtc-row-upcoming';
                            $situacao = '<span class="crtc-badge-small upcoming">⏳ Futura</span>';
                        } else {
                            $class = 'crtc-row-past';
                            $situacao = '<span class="crtc-badge-small past">✅ Realizada</span>';
                        }
                    ?>
                    <tr class="<?php echo $class; ?>">
                        <td data-label="Mês"><strong><?php echo crtc_mes_name($mes_num); ?></strong></td>
                        <td data-label="Início"><?php echo crtc_format_date_br($inicio); ?></td>
                        <td data-label="Término"><?php echo crtc_format_date_br($fim); ?></td>
                        <td data-label="Situação"><?php echo $situacao; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    private function get_recharge_info_for_year($year) {
        $data = crtc_get_data();
        $year_data = isset($data[$year]) ? $data[$year] : array();
        if (empty($year_data)) return null;

        $today = current_time('Y-m-d');
        $today_ts = strtotime($today);

        usort($year_data, function($a, $b) {
            return $a['mes'] - $b['mes'];
        });

        foreach ($year_data as $month) {
            $inicio = $month['inicio'];
            $fim = $month['fim'];
            if ($today > $fim) continue;
            if ($today >= $inicio && $today <= $fim) {
                return array('status' => 'active', 'days' => (int)ceil((strtotime($fim) - $today_ts) / DAY_IN_SECONDS));
            }
            if ($today < $inicio) {
                return array('status' => 'upcoming', 'days' => (int)ceil((strtotime($inicio) - $today_ts) / DAY_IN_SECONDS));
            }
        }

        return array('status' => 'past');
    }
}




// ============================================================
//  WIDGET CLASS
// ============================================================

if (!class_exists('CRTC_Widget')) :

class CRTC_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'calendario_recarga_widget',
            'Calendário de Recarga do Transporte',
            array(
                'description' => 'Exibe informações sobre a recarga do transporte coletivo.',
            )
        );
    }

    public function widget($args, $instance) {
        $crtc = Calendario_Recarga_Transporte::init();
        echo $args['before_widget'];
        echo $crtc->render_widget_markup($instance);
        echo $args['after_widget'];
    }

    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : '';
        ?>
        <p>
            <label for="<?php echo esc_attr($this->get_field_id('title')); ?>">Título:</label>
            <input class="widefat" id="<?php echo esc_attr($this->get_field_id('title')); ?>"
                   name="<?php echo esc_attr($this->get_field_name('title')); ?>" type="text"
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p class="description">Deixe em branco para usar o título padrão.</p>
        <?php
    }

    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = sanitize_text_field($new_instance['title']);
        return $instance;
    }
}

endif; // CRTC_Widget


// ============================================================
//  INITIALIZATION
// ============================================================

Calendario_Recarga_Transporte::init();

endif; // class_exists
