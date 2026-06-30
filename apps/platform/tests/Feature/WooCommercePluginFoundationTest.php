<?php

namespace Tests\Feature;

use Tests\TestCase;

class WooCommercePluginFoundationTest extends TestCase
{
    public function test_plugin_exposes_production_ready_adapter_foundation(): void
    {
        $plugin = $this->pluginContents();

        $this->assertStringContainsString('Version: 0.2.0', $plugin);
        $this->assertStringContainsString("register_rest_route(self::REST_NAMESPACE, '/health'", $plugin);
        $this->assertStringContainsString("register_rest_route(self::REST_NAMESPACE, '/connection-test'", $plugin);
        $this->assertStringContainsString('hash_hmac', $plugin);
        $this->assertStringContainsString('x-omnibridge-timestamp', $plugin);
        $this->assertStringContainsString('x-omnibridge-signature', $plugin);
        $this->assertStringContainsString('writes_performed', $plugin);
        $this->assertStringContainsString('_omnibridge_sync_to_front', $plugin);
        $this->assertStringContainsString('_omnibridge_exclude_from_front', $plugin);
        $this->assertStringContainsString('_omnibridge_resync_requested_at', $plugin);
        $this->assertStringContainsString('current_user_can', $plugin);
        $this->assertStringContainsString('wp_verify_nonce', $plugin);
        $this->assertStringContainsString('woocommerce_save_data', $plugin);
    }

    public function test_plugin_remains_thin_and_avoids_heavy_sync_logic(): void
    {
        $plugin = $this->pluginContents();

        $this->assertStringNotContainsString('WP_Query', $plugin);
        $this->assertStringNotContainsString('wc_get_products', $plugin);
        $this->assertStringNotContainsString('save_post_product', $plugin);
        $this->assertStringNotContainsString('wp_remote_post', $plugin);
        $this->assertStringNotContainsString('/api/products', $plugin);
        $this->assertStringNotContainsString('/api/Stock/adjust', $plugin);
        $this->assertStringNotContainsString('/api/PricelistV2', $plugin);
    }

    private function pluginContents(): string
    {
        $plugin = file_get_contents(base_path('../woocommerce-plugin/lilleprinsen-front-sync.php'));

        $this->assertIsString($plugin);

        return $plugin;
    }
}
