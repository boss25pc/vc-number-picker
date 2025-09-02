<?php
/**
 * Plugin Name: VC Number Picker (VaultComps)
 * Description: Pro number picker board for Woo Lottery products. Multi-select, 10‑minute reservations, per-number cart lines, skill question, compliance note, grid-size control (50/59/100/custom).
 * Version:     2.0.3
 * Author:      VaultComps
 * Requires PHP: 7.4
 * Text Domain: vcnp
 */

if (!defined('ABSPATH')) exit;

define('VCNP_VERSION', '2.0.2');
define('VCNP_PATH', plugin_dir_path(__FILE__));
define('VCNP_URL',  plugin_dir_url(__FILE__));

// Core includes
require_once VCNP_PATH . 'helpers.php';
require_once VCNP_PATH . 'class-frontend.php';
require_once VCNP_PATH . 'class-ajax.php';
require_once VCNP_PATH . 'class-orders.php';
require_once VCNP_PATH . 'class-admin-grid.php';

add_action('plugins_loaded', function(){
    if (!class_exists('WooCommerce')) return;
    // Bootstrap
    new VCNP_Frontend();
    new VCNP_Ajax();
    new VCNP_Orders();
    if (is_admin()) new VCNP_Admin_Grid_Size();
});
