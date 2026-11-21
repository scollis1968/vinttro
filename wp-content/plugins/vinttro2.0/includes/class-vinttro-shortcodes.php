<?php
if (!defined('ABSPATH')) exit;

class Vinttro_Shortcodes {

    private const SCRIPT_HANDLE = 'vinttro-app-js';
    private const STYLE_HANDLE  = 'vinttro-app-css';

    public function __construct() {
        add_shortcode('vinttro_client_screen', [$this, 'render_client_dashboard']);
        add_shortcode('vinttro_event_list',    [$this, 'render_event_list']);

        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_filter('script_loader_tag',  [$this, 'add_module_type'], 10, 3);
    }

    public function add_module_type($tag, $handle, $src) {
        if (self::SCRIPT_HANDLE === $handle) {
            return sprintf(
                '<script type="module" src="%s" id="%s-js"></script>' . "\n",
                esc_url($src),
                esc_attr($handle)
            );
        }
        return $tag;
    }

    public function register_assets() {
        $js_path  = VINTTRO_PATH . 'build/dashboard.js';
        $css_path = VINTTRO_PATH . 'build/dashboard.css';

        wp_register_script(
            self::SCRIPT_HANDLE,
            VINTTRO_URL . 'build/dashboard.js',
            [],
            file_exists($js_path) ? filemtime($js_path) : '2.0.0',
            true
        );

        wp_register_style(
            self::STYLE_HANDLE,
            VINTTRO_URL . 'build/dashboard.css',
            [],
            file_exists($css_path) ? filemtime($css_path) : '2.0.0'
        );

        $default_api = (defined('WP_ENVIRONMENT_TYPE') && wp_get_environment_type() === 'development')
            ? 'http://localhost:3005/api'
            : 'https://api.vinttro.co.uk/api';

        $api_base = defined('VINTTRO_API_BASE') ? VINTTRO_API_BASE : $default_api;

        // Shared global config (keep vinttroDashboardConfig alias if legacy scripts read it)
        $config = [
            'apiBase' => untrailingslashit(esc_url_raw($api_base)),
            'nonce'   => wp_create_nonce('wp_rest'),
        ];

        wp_localize_script(self::SCRIPT_HANDLE, 'vinttroConfig', $config);
    }

    private function enqueue_app_assets() {
        wp_enqueue_script(self::SCRIPT_HANDLE);
        wp_enqueue_style(self::STYLE_HANDLE);
    }

    public function render_client_dashboard($atts) {
        $params = shortcode_atts([
            'client_id' => isset($_GET['client_id']) ? sanitize_text_field($_GET['client_id']) : 'C-10492',
        ], $atts);

        $this->enqueue_app_assets();

        return sprintf(
            '<div id="vinttro-client-dashboard" data-client-id="%s"><div style="padding: 24px; text-align: center; color: #64748b;">Loading Client Record...</div></div>',
            esc_attr($params['client_id'])
        );
    }

    public function render_event_list($atts) {
        $params = shortcode_atts([
            'account_id' => isset($_GET['account_id']) ? sanitize_text_field($_GET['account_id']) : '',
        ], $atts);

        $this->enqueue_app_assets();

        return sprintf(
            '<div id="vinttro-event-list" data-account-id="%s"><div style="padding: 24px; text-align: center; color: #64748b;">Loading Events...</div></div>',
            esc_attr($params['account_id'])
        );
    }
}