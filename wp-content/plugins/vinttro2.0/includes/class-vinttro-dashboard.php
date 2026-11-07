<?php
if (!defined('ABSPATH')) exit;

class Vinttro_Dashboard {

    public function __construct() {
        add_shortcode('vinttro_client_screen', [$this, 'render_shortcode']);
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        
        // Prevent Vite variable leakage to window
        add_filter('script_loader_tag', [$this, 'add_module_type_to_dashboard'], 10, 3);
    }

    public function add_module_type_to_dashboard($tag, $handle, $src) {
        if ('vinttro-dashboard-js' === $handle) {
            return sprintf('<script type="module" src="%s" id="%s-js"></script>' . "\n", esc_url($src), esc_attr($handle));
        }
        return $tag;
    }
    public function register_assets() {
        $js_path  = VINTTRO_PATH . 'build/dashboard.js';
        $css_path = VINTTRO_PATH . 'build/dashboard.css';

        $js_ver  = file_exists($js_path) ? filemtime($js_path) : '2.0.0';
        $css_ver = file_exists($css_path) ? filemtime($css_path) : '2.0.0';

        wp_register_script(
            'vinttro-dashboard-js',
            VINTTRO_URL . 'build/dashboard.js',
            [],
            $js_ver,
            true // Load in footer
        );

        wp_register_style(
            'vinttro-dashboard-css',
            VINTTRO_URL . 'build/dashboard.css',
            [],
            $css_ver
        );

        $default_api = (defined('WP_ENVIRONMENT_TYPE') && wp_get_environment_type() === 'development')
            ? 'http://localhost:3005/api'
            : 'https://api.vinttro.co.uk/api';

        $api_base = defined('VINTTRO_API_BASE') ? VINTTRO_API_BASE : $default_api;

        wp_localize_script('vinttro-dashboard-js', 'vinttroDashboardConfig', [
            'apiBase' => untrailingslashit(esc_url_raw($api_base)), // Normalizes without trailing slash
            'nonce'   => wp_create_nonce('wp_rest'),
        ]);
    }

    public function render_shortcode($atts) {
        $params = shortcode_atts([
            'client_id' => isset($_GET['client_id']) ? sanitize_text_field($_GET['client_id']) : 'C-10492'
        ], $atts);

        // Enqueue the compiled React assets when the shortcode is on the page
        wp_enqueue_script('vinttro-dashboard-js');
        wp_enqueue_style('vinttro-dashboard-css');

        return sprintf(
            '<div id="vinttro-client-dashboard" data-client-id="%s"><div style="padding: 24px; text-align: center; color: #64748b;">Loading Client Record...</div></div>',
            esc_attr($params['client_id'])
        );
    }
}