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

