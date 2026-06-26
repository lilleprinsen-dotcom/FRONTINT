<?php
/**
 * Plugin Name: OmniBridge WooCommerce Adapter
 * Description: Thin WooCommerce adapter for the OmniBridge WooCommerce and Front Systems integration.
 * Version: 0.1.0
 * Author: OmniBridge
 * Text Domain: omnibridge
 * Requires PHP: 8.1
 * Requires at least: 6.4
 *
 * This plugin is intentionally thin. Core integration logic belongs in the
 * Laravel platform. WooCommerce-specific adapter behavior can be added here.
 *
 * Product sync planning:
 * - Later add lightweight product meta such as _omnibridge_sync_to_front,
 *   _omnibridge_last_sync_status, _omnibridge_last_synced_at, and
 *   _omnibridge_last_sync_error.
 * - Later add a small product admin panel and bulk action for marking selected
 *   products for Front sync.
 * - Do not scan the full WooCommerce catalog here. Avoid heavy admin queries.
 * - Do not implement actual Woo to Front sync in this plugin.
 */

if (! defined('ABSPATH')) {
    exit;
}

define('OMNIBRIDGE_PLUGIN_VERSION', '0.1.0');
define('OMNIBRIDGE_PLUGIN_FILE', __FILE__);

add_action('plugins_loaded', static function (): void {
    if (! class_exists('WooCommerce')) {
        return;
    }

    // TODO: Register settings, signed adapter endpoints, and WooCommerce hooks.
});
