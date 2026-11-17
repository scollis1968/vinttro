<?php
if (!defined('ABSPATH')) exit;

class Vinttro_API {

    // Shared secret for server-to-server sync (can also define in wp-config.php)
    private $api_secret;

    public function __construct() {
        $this->api_secret = defined('VINTTRO_SYNC_SECRET') ? VINTTRO_SYNC_SECRET : 'vinttro_dev_secret_key_123';
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        // Read: Dashboard frontend fetch
        register_rest_route('vinttro/v1', '/client/(?P<id>[a-zA-Z0-9_\-]+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'get_client_summary'],
            'permission_callback' => '__return_true', // Frontend read (secured by WP nonce if needed)
        ]);

        // Write: Ingestion from SuiteCRM / Postman
        register_rest_route('vinttro/v1', '/client/(?P<id>[a-zA-Z0-9_\-]+)', [
            'methods'             => 'POST',
            'callback'            => [$this, 'upsert_client_summary'],
            'permission_callback' => [$this, 'authorize_sync_request'],
        ]);
    }

    /**
     * Authorize write requests via secret header or admin privileges
     */
    public function authorize_sync_request($request) {
        $header_key = $request->get_header('x-vinttro-api-key');
        if (!empty($header_key) && hash_equals($this->api_secret, $header_key)) {
            return true;
        }

        return current_user_can('manage_options');
    }

    /**
     * Redis connection helper
     */
    private function get_redis_client() {
        if (!class_exists('Redis')) {
            throw new Exception('PHP Redis extension (pecl-redis) is not installed or enabled.');
        }

        $redis = new Redis();
        $host  = defined('VINTTRO_REDIS_HOST') ? VINTTRO_REDIS_HOST : '127.0.0.1';
        $port  = defined('VINTTRO_REDIS_PORT') ? (int)VINTTRO_REDIS_PORT : 6379;
        $pass  = defined('VINTTRO_REDIS_PASS') ? VINTTRO_REDIS_PASS : null;

        $connected = @$redis->connect($host, $port, 1.5);
        if (!$connected) {
            throw new Exception("Unable to connect to Redis server at {$host}:{$port}");
        }

        if (!empty($pass)) {
            $redis->auth($pass);
        }

        return $redis;
    }

    /**
     * GET: Fetch client payload from Redis
     */
    public function get_client_summary($request) {
        $client_id = sanitize_text_field($request->get_param('id'));
        $redis_key = "client:{$client_id}";

        try {
            $redis = $this->get_redis_client();
            $data  = $redis->get($redis_key);

            if (!$data) {
                return new WP_Error('client_not_found', "No cached record for client '{$client_id}'", ['status' => 404]);
            }

            return rest_ensure_response(json_decode($data, true));
        } catch (Exception $e) {
            return new WP_Error('redis_error', $e->getMessage(), ['status' => 500]);
        }
    }

    /**
     * POST: Upload/Update client payload in Redis
     */
    public function upsert_client_summary($request) {
        $client_id = sanitize_text_field($request->get_param('id'));
        $body = $request->get_json_params();

        if (empty($body)) {
            return new WP_Error('invalid_payload', 'Payload must be valid JSON.', ['status' => 400]);
        }

        // Enforce matching ID in body
        $body['client_id'] = $client_id;
        $body['meta']['cached_at'] = gmdate('c');

        $redis_key = "client:{$client_id}";

        try {
            $redis = $this->get_redis_client();
            
            // Store with 48h TTL (172800 seconds) or set 0 for permanent persistence
            $redis->setex($redis_key, 172800, json_encode($body));

            return rest_ensure_response([
                'success'   => true,
                'client_id' => $client_id,
                'key'       => $redis_key,
                'timestamp' => $body['meta']['cached_at']
            ]);
        } catch (Exception $e) {
            return new WP_Error('redis_error', $e->getMessage(), ['status' => 500]);
        }
    }
}