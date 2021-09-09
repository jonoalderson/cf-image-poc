<?php
/**
 * Plugin Name: Yoast Cloudflare images integration
 * Version: 1.0.1
 * Description: Provides support for Cloudflared images
 * Author: Jono Alderson
 * Text Domain: yoast-cf-image
 */

// Prevent direct file access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Set our constants.
if ( ! defined( 'YOAST_CF_IMAGES_VERSION' ) ) {
	define( 'YOAST_CF_IMAGES_VERSION', '1.0.1' );
}

if ( ! defined( 'YOAST_CF_IMAGES_PLUGIN_DIR' ) ) {
	define( 'YOAST_CF_IMAGES_PLUGIN_DIR', __DIR__ );
}

if ( ! defined( 'YOAST_CF_IMAGES_PLUGIN_PLUGIN_URL' ) ) {
	define( 'YOAST_CF_IMAGES_PLUGIN_PLUGIN_URL', trailingslashit( plugins_url() ) . trailingslashit( plugin_basename( __DIR__ ) ) );
}

if ( ! defined( 'YOAST_CF_IMAGES_PLUGIN_FILE' ) ) {
	define( 'YOAST_CF_IMAGES_PLUGIN_FILE', __FILE__ );
}

/**
 * Init the plugin
 */
( function() {

	// Load our autoloaders.
	require_once 'autoload.php';

	Yoast_CF_Images\Cloudflare_Image_Handler::register();
	Yoast_CF_Images\Social_Images::register();

} )();

/**
 * Returns a Cloudflared image
 *
 * @param  int    $id    The attachment ID.
 * @param  array  $atts  The atts to pass (see wp_get_attachment_image).
 * @param  string $size  The image size.
 * @param  bool   $echo  If the image should be echo'd.
 *
 * @return false|string  The HTML
 */
function get_cf_image( int $id, array $atts = array(), string $size, $echo = true ) {

	$image_class = Yoast_CF_Images\Cloudflare_Image_Helpers::get_image_class( $size );

	// Get the image.
	$image = new $image_class( $id, $atts, $size );
	if ( ! $image ) {
		return false;
	}

	// Construct the <img>, and wrap it in a <picture>.
	$html = $image->construct_img_el( true );

	// Maybe echo the image.
	if ( $echo ) {
		echo wp_kses( $html, array( 'picture', 'img' ) );
		return;
	}

	// Or just return the HTML.
	return $html;
}
