<?php
/**
 * Edge Images - Routes images through edge providers for optimization and transformation.
 *
 * @package   Edge_Images
 * @author    Jono Alderson
 * @license   GPL-2.0-or-later
 * @link      https://www.jonoalderson.com/
 * @copyright 2024 Jono Alderson
 *
 * @wordpress-plugin
 * Plugin Name:       Edge Images
 * Plugin URI:        https://www.jonoalderson.com/
 * Description:       Routes images through edge providers (like Cloudflare or Accelerated Domains) for automatic optimization and transformation. Improves page speed and image loading performance.
 * Version:           4.0.8
 * Requires PHP:      7.4
 * Requires at least: 5.6
 * Tested up to:      6.4
 * Author:           Jono Alderson
 * Author URI:       https://www.jonoalderson.com/
 * Text Domain:      edge-images
 * Domain Path:      /languages
 * License:          GPL v2 or later
 * License URI:      http://www.gnu.org/licenses/gpl-2.0.txt
 */

namespace Edge_Images;

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define constants.
define( 'EDGE_IMAGES_VERSION', '4.0.8' );
define( 'EDGE_IMAGES_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EDGE_IMAGES_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load autoloader.
require_once EDGE_IMAGES_PLUGIN_DIR . 'autoload.php';

// Initialize plugin on 'init' hook.
add_action( 'wp', [ Handler::class, 'register' ] );

// Initialize admin page.
if ( is_admin() ) {
    add_action( 'init', [ Admin_Page::class, 'register' ] );
}
