<?php

namespace Yoast_CF_Images;

use Yoast_CF_Images\Cloudflare_Image_Handler as Handler;

/**
 * Provides helper methods.
 */
class Cloudflare_Image_Helpers {

	/**
	 * The plugin styles URL
	 *
	 * @var string
	 */
	const STYLES_URL = YOAST_CF_IMAGES_PLUGIN_PLUGIN_URL . 'assets/css';

	/**
	 * The Cloudflare host domain
	 *
	 * @var string
	 */
	const CF_HOST = 'https://yoast.com';

	/**
	 * The content width in pixels
	 *
	 * @var integer
	 */
	const CONTENT_WIDTH = 600;

	/**
	 * The min width a default srcset val should be generated at, in pixels.
	 *
	 * @var integer
	 */
	const MIN_WIDTH = 400;

	/**
	 * The max width a default srcset val should ever be generated at, in pixels.
	 *
	 * @var integer
	 */
	const WIDTH_MAX = 2400;

	/**
	 * The min width a default srcset val should be generated at, in pixels.
	 *
	 * @var integer
	 */
	const WIDTH_MIN = 400;

	/**
	 * The width to increment default srcset vals.
	 *
	 * @var integer
	 */
	const WIDTH_STEP = 100;

	/**
	 * Get the appropriate class for the image size
	 *
	 * @param  string $size The image size.
	 *
	 * @return string       The class name
	 */
	public static function get_image_class( $size ) : string {
		$image_base_class = 'Yoast_CF_Images';
		$default_class    = $image_base_class . '\\Cloudflare_Image';

		// Bail if this is a custom size.
		if ( is_array( $size ) ) {
			return $default_class;
		}

		// See if there's a specific size class for this image.
		$image_size_class = $image_base_class . '\\sizes\\' . $size;

		$class = ( class_exists( $image_size_class ) ) ? $image_size_class : $default_class;

		return $class;
	}

	/**
	 * Replace a SRC string with a Cloudflared version
	 *
	 * @param  string $src               The SRC attr.
	 * @param  int    $w                 The width in pixels.
	 * @param  int    $h                 The height in pixels.
	 *
	 * @return string      The modified SRC attr.
	 */
	public static function cf_src( string $src, int $w, int $h = null, string $fit = 'contain' ) : string {
		$cf_properties = array(
			'width'   => $w,
			'fit'     => $fit,
			'f'       => 'auto',
			'gravity' => 'auto',
			'onerror' => 'redirect',
		);
		if ( $h ) {
			$cf_properties['height'] = $h;
		}
		ksort( $cf_properties );

		$cf_prefix = 'https://yoast.com/cdn-cgi/image/';
		$cf_string = $cf_prefix . http_build_query(
			$cf_properties,
			'',
			'%2C'
		);

		$url  = wp_parse_url( $src );
		$path = ( isset( $url['path'] ) ) ? $url['path'] : '';

		return $cf_string . $path;
	}

	/**
	 * Creates an srcset val from a src and dimensions
	 *
	 * @param string $src  The image src attr.
	 * @param int    $w    The width in pixels.
	 * @param int    $h    The height in pixels.
	 *
	 * @return string   The srcset value
	 */
	public static function create_srcset_val( string $src, int $w, int $h = null ) : string {
		return sprintf(
			'%s %dw',
			self::cf_src( $src, $w, $h ),
			$w
		);
	}

	/**
	 * Get the content width value
	 *
	 * @return int The content width value
	 */
	public static function get_content_width() : int {
		global $content_width;
		if ( ! $content_width || $content_width > self::CONTENT_WIDTH ) {
			$content_width = self::CONTENT_WIDTH;
		}
		return $content_width;
	}

	/**
	 * Get the vals for a WP image size
	 *
	 * @param  string $size The size.
	 *
	 * @return false|array  The values
	 */
	public static function get_wp_size_vals( string $size ) {

		$vals = array();

		// Get our default image sizes.
		$default_image_sizes = get_intermediate_image_sizes();

		// Check the size is valid.
		if ( ! in_array( $size, $default_image_sizes, true ) ) {
			$size = 'large';
		}

		// Check if we have vlues for this size.
		$key = array_search( $size, $default_image_sizes, true );
		if ( $key === false ) {
			return false;
		}

		$vals = array(
			'width'  => intval( get_option( "{$default_image_sizes[$key]}_size_w" ) ),
			'height' => intval( get_option( "{$default_image_sizes[$key]}_size_h" ) ),
		);

		return $vals;
	}

	/**
	 * Normalize an image attr into an array of values
	 *
	 * @param  mixed $attr The attr to normalize.
	 *
	 * @return array       The array of values
	 */
	public static function normalize_attr_array( $attr ) : array {
		$attr = ( $attr ) ? $attr : array();
		if ( is_string( $attr ) ) {
			$attr = explode( ' ', $attr );
		}
		return array_unique( $attr );
	}

	/**
	 * Flatten an array of classes into a string
	 *
	 * @param  mixed $classes The classes.
	 *
	 * @return false|string The flattened classes
	 */
	public static function classes_array_to_string( $classes ) {
		if ( is_string( $classes ) ) {
			return sanitize_html_class( $classes );
		}

		if ( is_array( $classes ) ) {
			foreach ( $classes as &$class ) {
				$class = sanitize_html_class( $class );
			}
			return implode( ' ', $classes );
		}

		return false;
	}

}
