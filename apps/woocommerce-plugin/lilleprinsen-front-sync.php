<?php
/**
 * Plugin Name: OmniBridge WooCommerce Adapter
 * Description: Thin WooCommerce adapter for the OmniBridge WooCommerce and Front Systems integration.
 * Version: 0.2.0
 * Author: OmniBridge
 * Text Domain: omnibridge
 * Requires PHP: 8.1
 * Requires at least: 6.4
 *
 * This plugin is intentionally thin. Core integration logic belongs in the
 * Laravel platform. The plugin provides WooCommerce-local settings, status
 * checks, signed adapter endpoints, and lightweight product metadata only.
 */

if (! defined('ABSPATH')) {
    exit;
}

define('OMNIBRIDGE_PLUGIN_VERSION', '0.2.0');
define('OMNIBRIDGE_PLUGIN_FILE', __FILE__);
define('OMNIBRIDGE_PLUGIN_OPTION', 'omnibridge_adapter_settings');

if (! class_exists('OmniBridge_WooCommerce_Adapter')) {
    final class OmniBridge_WooCommerce_Adapter
    {
        private const REST_NAMESPACE = 'omnibridge/v1';
        private const SIGNATURE_TOLERANCE_SECONDS = 300;

        public function boot(): void
        {
            add_action('admin_menu', [$this, 'register_admin_page']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('rest_api_init', [$this, 'register_rest_routes']);

            if ($this->woocommerce_active()) {
                add_action('woocommerce_product_options_general_product_data', [$this, 'render_product_fields']);
                add_action('woocommerce_admin_process_product_object', [$this, 'save_product_fields']);
                add_filter('bulk_actions-edit-product', [$this, 'register_product_bulk_actions']);
                add_filter('handle_bulk_actions-edit-product', [$this, 'handle_product_bulk_action'], 10, 3);
            }
        }

        public function register_admin_page(): void
        {
            if ($this->woocommerce_active()) {
                add_submenu_page(
                    'woocommerce',
                    __('OmniBridge', 'omnibridge'),
                    __('OmniBridge', 'omnibridge'),
                    'manage_woocommerce',
                    'omnibridge-adapter',
                    [$this, 'render_settings_page'],
                );

                return;
            }

            add_options_page(
                __('OmniBridge', 'omnibridge'),
                __('OmniBridge', 'omnibridge'),
                'manage_options',
                'omnibridge-adapter',
                [$this, 'render_settings_page'],
            );
        }

        public function register_settings(): void
        {
            register_setting('omnibridge_adapter', OMNIBRIDGE_PLUGIN_OPTION, [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings'],
                'default' => $this->default_settings(),
            ]);
        }

        public function sanitize_settings($settings): array
        {
            $settings = is_array($settings) ? $settings : [];
            $current = $this->settings();

            return [
                'environment' => in_array($settings['environment'] ?? '', ['staging', 'production'], true)
                    ? $settings['environment']
                    : 'staging',
                'platform_url' => esc_url_raw((string) ($settings['platform_url'] ?? '')),
                'tenant_key' => sanitize_text_field((string) ($settings['tenant_key'] ?? '')),
                'shared_secret' => ($settings['shared_secret'] ?? '') === ''
                    ? ($current['shared_secret'] ?? '')
                    : sanitize_text_field((string) ($settings['shared_secret'] ?? '')),
                'enable_signed_endpoints' => ! empty($settings['enable_signed_endpoints']) ? '1' : '0',
                'enable_product_fields' => ! empty($settings['enable_product_fields']) ? '1' : '0',
            ];
        }

        public function register_rest_routes(): void
        {
            register_rest_route(self::REST_NAMESPACE, '/health', [
                'methods' => 'GET',
                'callback' => [$this, 'health_response'],
                'permission_callback' => '__return_true',
            ]);

            register_rest_route(self::REST_NAMESPACE, '/connection-test', [
                'methods' => 'GET',
                'callback' => [$this, 'connection_test_response'],
                'permission_callback' => [$this, 'verify_signed_request'],
            ]);
        }

        public function health_response(): WP_REST_Response
        {
            return new WP_REST_Response([
                'status' => $this->woocommerce_active() ? 'ready' : 'needs_attention',
                'plugin' => [
                    'name' => 'OmniBridge WooCommerce Adapter',
                    'version' => OMNIBRIDGE_PLUGIN_VERSION,
                    'environment' => $this->settings()['environment'],
                    'signed_endpoints_enabled' => $this->signed_endpoints_enabled(),
                    'product_fields_enabled' => $this->product_fields_enabled(),
                ],
                'woocommerce' => [
                    'active' => $this->woocommerce_active(),
                    'version' => $this->woocommerce_version(),
                ],
                'wordpress' => [
                    'home_url' => home_url(),
                    'site_url' => site_url(),
                    'rest_url' => esc_url_raw(rest_url(self::REST_NAMESPACE)),
                ],
                'endpoints' => [
                    'health' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/health')),
                    'connection_test' => esc_url_raw(rest_url(self::REST_NAMESPACE . '/connection-test')),
                ],
                'server_time_utc' => gmdate('c'),
            ], 200);
        }

        public function connection_test_response(): WP_REST_Response
        {
            $settings = $this->settings();

            return new WP_REST_Response([
                'status' => 'success',
                'message' => 'OmniBridge WooCommerce adapter responded to a signed read-only test.',
                'read_only' => true,
                'writes_performed' => false,
                'plugin' => [
                    'version' => OMNIBRIDGE_PLUGIN_VERSION,
                    'environment' => $settings['environment'],
                    'tenant_key_configured' => $settings['tenant_key'] !== '',
                    'shared_secret_configured' => $settings['shared_secret'] !== '',
                    'product_fields_enabled' => $this->product_fields_enabled(),
                ],
                'woocommerce' => [
                    'active' => $this->woocommerce_active(),
                    'version' => $this->woocommerce_version(),
                    'currency' => $this->woocommerce_active() ? get_woocommerce_currency() : null,
                ],
                'capabilities' => [
                    'signed_connection_test' => true,
                    'product_sync_flags' => $this->product_fields_enabled(),
                    'gift_card_adapter' => 'planned',
                    'catalog_scan_inside_plugin' => false,
                    'sync_logic_inside_plugin' => false,
                ],
                'server_time_utc' => gmdate('c'),
            ], 200);
        }

        public function verify_signed_request(WP_REST_Request $request)
        {
            if (! $this->signed_endpoints_enabled()) {
                return new WP_Error(
                    'omnibridge_signed_endpoints_disabled',
                    __('Signed OmniBridge endpoints are disabled in plugin settings.', 'omnibridge'),
                    ['status' => 503],
                );
            }

            $secret = $this->settings()['shared_secret'] ?? '';

            if ($secret === '') {
                return new WP_Error(
                    'omnibridge_missing_shared_secret',
                    __('OmniBridge shared secret is not configured.', 'omnibridge'),
                    ['status' => 503],
                );
            }

            $timestamp = (string) $request->get_header('x-omnibridge-timestamp');
            $signature = (string) $request->get_header('x-omnibridge-signature');

            if ($timestamp === '' || $signature === '') {
                return new WP_Error(
                    'omnibridge_missing_signature_headers',
                    __('Missing OmniBridge signature headers.', 'omnibridge'),
                    ['status' => 401],
                );
            }

            if (! ctype_digit($timestamp) || abs(time() - (int) $timestamp) > self::SIGNATURE_TOLERANCE_SECONDS) {
                return new WP_Error(
                    'omnibridge_signature_expired',
                    __('OmniBridge signature timestamp is outside the allowed window.', 'omnibridge'),
                    ['status' => 401],
                );
            }

            $expected = hash_hmac('sha256', $request->get_method() . "\n" . $request->get_route() . "\n" . $timestamp, $secret);

            if (! hash_equals($expected, $signature)) {
                return new WP_Error(
                    'omnibridge_invalid_signature',
                    __('Invalid OmniBridge signature.', 'omnibridge'),
                    ['status' => 401],
                );
            }

            return true;
        }

        public function render_settings_page(): void
        {
            if (! current_user_can($this->woocommerce_active() ? 'manage_woocommerce' : 'manage_options')) {
                return;
            }

            $settings = $this->settings();
            ?>
            <div class="wrap">
                <h1><?php esc_html_e('OmniBridge WooCommerce Adapter', 'omnibridge'); ?></h1>
                <p><?php esc_html_e('This plugin keeps WooCommerce connected to the OmniBridge platform without running sync logic inside WordPress.', 'omnibridge'); ?></p>

                <h2><?php esc_html_e('Status', 'omnibridge'); ?></h2>
                <table class="widefat striped" style="max-width: 760px">
                    <tbody>
                    <tr>
                        <th><?php esc_html_e('Plugin version', 'omnibridge'); ?></th>
                        <td><?php echo esc_html(OMNIBRIDGE_PLUGIN_VERSION); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('WooCommerce', 'omnibridge'); ?></th>
                        <td><?php echo esc_html($this->woocommerce_active() ? 'Active' : 'Not active'); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Health endpoint', 'omnibridge'); ?></th>
                        <td><code><?php echo esc_html(rest_url(self::REST_NAMESPACE . '/health')); ?></code></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e('Signed connection test endpoint', 'omnibridge'); ?></th>
                        <td><code><?php echo esc_html(rest_url(self::REST_NAMESPACE . '/connection-test')); ?></code></td>
                    </tr>
                    </tbody>
                </table>

                <form method="post" action="options.php" style="margin-top: 24px">
                    <?php settings_fields('omnibridge_adapter'); ?>
                    <table class="form-table" role="presentation">
                        <tbody>
                        <tr>
                            <th scope="row"><label for="omnibridge_environment"><?php esc_html_e('Environment', 'omnibridge'); ?></label></th>
                            <td>
                                <select id="omnibridge_environment" name="<?php echo esc_attr(OMNIBRIDGE_PLUGIN_OPTION); ?>[environment]">
                                    <option value="staging" <?php selected($settings['environment'], 'staging'); ?>><?php esc_html_e('Staging', 'omnibridge'); ?></option>
                                    <option value="production" <?php selected($settings['environment'], 'production'); ?>><?php esc_html_e('Production', 'omnibridge'); ?></option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="omnibridge_platform_url"><?php esc_html_e('OmniBridge platform URL', 'omnibridge'); ?></label></th>
                            <td>
                                <input id="omnibridge_platform_url" class="regular-text" type="url" name="<?php echo esc_attr(OMNIBRIDGE_PLUGIN_OPTION); ?>[platform_url]" value="<?php echo esc_attr($settings['platform_url']); ?>" placeholder="https://platform.example.com">
                                <p class="description"><?php esc_html_e('Used later for lightweight callbacks from WooCommerce to the platform. No callbacks are sent by this version.', 'omnibridge'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="omnibridge_tenant_key"><?php esc_html_e('Tenant key', 'omnibridge'); ?></label></th>
                            <td><input id="omnibridge_tenant_key" class="regular-text" name="<?php echo esc_attr(OMNIBRIDGE_PLUGIN_OPTION); ?>[tenant_key]" value="<?php echo esc_attr($settings['tenant_key']); ?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><label for="omnibridge_shared_secret"><?php esc_html_e('Shared secret', 'omnibridge'); ?></label></th>
                            <td>
                                <input id="omnibridge_shared_secret" class="regular-text" type="password" name="<?php echo esc_attr(OMNIBRIDGE_PLUGIN_OPTION); ?>[shared_secret]" value="" autocomplete="new-password" placeholder="<?php echo esc_attr($settings['shared_secret'] === '' ? 'Not configured' : 'Configured - leave blank to keep'); ?>">
                                <p class="description"><?php esc_html_e('Used for HMAC signatures on adapter test endpoints. Leave blank to keep the existing secret.', 'omnibridge'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Adapter endpoints', 'omnibridge'); ?></th>
                            <td><label><input type="checkbox" name="<?php echo esc_attr(OMNIBRIDGE_PLUGIN_OPTION); ?>[enable_signed_endpoints]" value="1" <?php checked($settings['enable_signed_endpoints'], '1'); ?>> <?php esc_html_e('Enable signed adapter endpoints', 'omnibridge'); ?></label></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Product fields', 'omnibridge'); ?></th>
                            <td><label><input type="checkbox" name="<?php echo esc_attr(OMNIBRIDGE_PLUGIN_OPTION); ?>[enable_product_fields]" value="1" <?php checked($settings['enable_product_fields'], '1'); ?>> <?php esc_html_e('Show lightweight product sync fields in WooCommerce admin', 'omnibridge'); ?></label></td>
                        </tr>
                        </tbody>
                    </table>
                    <?php submit_button(__('Save OmniBridge settings', 'omnibridge')); ?>
                </form>
            </div>
            <?php
        }

        public function render_product_fields(): void
        {
            if (! $this->product_fields_enabled()) {
                return;
            }

            echo '<div class="options_group">';

            woocommerce_wp_checkbox([
                'id' => '_omnibridge_sync_to_front',
                'label' => __('Sync to Front', 'omnibridge'),
                'description' => __('Marks this product as eligible for a future OmniBridge sync run.', 'omnibridge'),
            ]);

            woocommerce_wp_checkbox([
                'id' => '_omnibridge_exclude_from_front',
                'label' => __('Exclude from Front', 'omnibridge'),
                'description' => __('Prevents this product from being included in future OmniBridge sync runs.', 'omnibridge'),
            ]);

            $post_id = get_the_ID();
            $last_status = $post_id ? get_post_meta($post_id, '_omnibridge_last_sync_status', true) : '';
            $last_synced_at = $post_id ? get_post_meta($post_id, '_omnibridge_last_synced_at', true) : '';
            $last_error = $post_id ? get_post_meta($post_id, '_omnibridge_last_sync_error', true) : '';

            echo '<p class="form-field"><label>' . esc_html__('OmniBridge status', 'omnibridge') . '</label><span class="description">';
            echo esc_html($last_status ?: __('Not synced yet', 'omnibridge'));
            if ($last_synced_at) {
                echo '<br>' . esc_html__('Last synced:', 'omnibridge') . ' ' . esc_html($last_synced_at);
            }
            if ($last_error) {
                echo '<br>' . esc_html__('Last error:', 'omnibridge') . ' ' . esc_html($last_error);
            }
            echo '</span></p>';
            echo '</div>';
        }

        public function save_product_fields($product): void
        {
            if (! $this->product_fields_enabled() || ! is_object($product) || ! method_exists($product, 'update_meta_data')) {
                return;
            }

            $product_id = method_exists($product, 'get_id') ? (int) $product->get_id() : 0;

            if ($product_id <= 0 || ! current_user_can('edit_product', $product_id)) {
                return;
            }

            $nonce = isset($_POST['woocommerce_meta_nonce'])
                ? sanitize_text_field(wp_unslash((string) $_POST['woocommerce_meta_nonce']))
                : '';

            if ($nonce === '' || ! wp_verify_nonce($nonce, 'woocommerce_save_data')) {
                return;
            }

            $product->update_meta_data('_omnibridge_sync_to_front', isset($_POST['_omnibridge_sync_to_front']) ? 'yes' : 'no');
            $product->update_meta_data('_omnibridge_exclude_from_front', isset($_POST['_omnibridge_exclude_from_front']) ? 'yes' : 'no');
        }

        public function register_product_bulk_actions(array $actions): array
        {
            if (! $this->product_fields_enabled()) {
                return $actions;
            }

            $actions['omnibridge_mark_sync_to_front'] = __('OmniBridge: mark for Front sync', 'omnibridge');
            $actions['omnibridge_exclude_from_front'] = __('OmniBridge: exclude from Front sync', 'omnibridge');
            $actions['omnibridge_request_resync'] = __('OmniBridge: request resync', 'omnibridge');

            return $actions;
        }

        public function handle_product_bulk_action(string $redirect_to, string $action, array $post_ids): string
        {
            if (! $this->product_fields_enabled()) {
                return $redirect_to;
            }

            if (! in_array($action, ['omnibridge_mark_sync_to_front', 'omnibridge_exclude_from_front', 'omnibridge_request_resync'], true)) {
                return $redirect_to;
            }

            foreach ($post_ids as $post_id) {
                $post_id = (int) $post_id;

                if ($post_id <= 0) {
                    continue;
                }

                if (! current_user_can('edit_product', $post_id)) {
                    continue;
                }

                if ($action === 'omnibridge_mark_sync_to_front') {
                    update_post_meta($post_id, '_omnibridge_sync_to_front', 'yes');
                    update_post_meta($post_id, '_omnibridge_exclude_from_front', 'no');
                }

                if ($action === 'omnibridge_exclude_from_front') {
                    update_post_meta($post_id, '_omnibridge_exclude_from_front', 'yes');
                }

                if ($action === 'omnibridge_request_resync') {
                    update_post_meta($post_id, '_omnibridge_resync_requested_at', gmdate('c'));
                }
            }

            return add_query_arg('omnibridge_bulk_action', rawurlencode($action), $redirect_to);
        }

        private function settings(): array
        {
            return wp_parse_args(get_option(OMNIBRIDGE_PLUGIN_OPTION, []), $this->default_settings());
        }

        private function default_settings(): array
        {
            return [
                'environment' => 'staging',
                'platform_url' => '',
                'tenant_key' => '',
                'shared_secret' => '',
                'enable_signed_endpoints' => '1',
                'enable_product_fields' => '1',
            ];
        }

        private function signed_endpoints_enabled(): bool
        {
            return ($this->settings()['enable_signed_endpoints'] ?? '1') === '1';
        }

        private function product_fields_enabled(): bool
        {
            return ($this->settings()['enable_product_fields'] ?? '1') === '1';
        }

        private function woocommerce_active(): bool
        {
            return class_exists('WooCommerce');
        }

        private function woocommerce_version(): ?string
        {
            if (! defined('WC_VERSION')) {
                return null;
            }

            return (string) WC_VERSION;
        }
    }
}

add_action('plugins_loaded', static function (): void {
    (new OmniBridge_WooCommerce_Adapter())->boot();
});
