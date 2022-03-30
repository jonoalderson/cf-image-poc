<?php

namespace Edge_Images;

use Edge_Images\Helpers;
use Edge_Images\Image;

/**
 * Filters wp_get_attachment_image and related functions to use the edge.
 */
class Handler {

	/**
	 * Register the integration
	 *
	 * @return void
	 */
	public static function register() : void {

		// Bail if we shouldn't be transforming images.
		if ( ! Helpers::should_transform_images() ) {
			return;
		}

		$instance = new self();
		add_filter( 'wp_get_attachment_image_attributes', array( $instance, 'route_images_through_edge' ), 100, 3 );
		add_filter( 'wp_get_attachment_image', array( $instance, 'remove_dimension_attributes' ), 10, 5 );
		add_filter( 'wp_get_attachment_image', array( $instance, 'decorate_edge_image' ), 100, 5 );
		add_action( 'wp_enqueue_scripts', array( $instance, 'enqueue_css' ), 0 );
		add_filter( 'pre_render_block', array( $instance, 'alter_image_block_rendering' ), 10, 3 );
		add_filter( 'safe_style_css', array( $instance, 'allow_container_ratio_style' ) );
		add_filter( 'wp_get_attachment_image_src', array( $instance, 'fix_wp_get_attachment_image_svg' ), 1, 4 );
	}

	/**
	 * Fixes WP not retrieving the right values for SVGs.
	 *
	 * @param  array|false  $image         The image.
	 * @param  int          $attachment_id The attachment ID.
	 * @param  string|int[] $size          The size.
	 * @param  bool         $icon          Whether to use an icon.
	 *
	 * @return array                       The modified image
	 */
	public function fix_wp_get_attachment_image_svg( $image, $attachment_id, $size, $icon ) : array {

		if ( ! $image || ! isset( $image[0] ) || ! isset( $image[1] ) || ! isset( $image[2] ) ) {
			return array(); // Bail if the image isn't valid.
		}

		// Check if this is an SVG.
		if ( is_array( $image ) && preg_match( '/\.svg$/i', $image[0] ) && $image[1] <= 1 ) {
			if ( is_array( $size ) ) {
				// If $image is an array, we can use the H and W values.
				$image[1] = $size[0];
				$image[2] = $size[1];
			} elseif ( ( $xml = simplexml_load_file( $image[0] ) ) !== false ) {
				// Otherwise, we should get the attributes from the SVG file.
				$attr     = $xml->attributes();
				$viewbox  = explode( ' ', $attr->viewBox );
				$image[1] = isset( $attr->width ) && preg_match( '/\d+/', $attr->width, $value ) ? (int) $value[0] : ( count( $viewbox ) == 4 ? (int) $viewbox[2] : null );
				$image[2] = isset( $attr->height ) && preg_match( '/\d+/', $attr->height, $value ) ? (int) $value[0] : ( count( $viewbox ) == 4 ? (int) $viewbox[3] : null );
			} else {
				// Or fall back to no values.
				$image[1] = null;
				$image[2] = null;
			}
		}

		return $image;
	}


	/**
	 * Adds our aspect ratio variable as a safe style
	 *
	 * @param  array $styles The safe styles.
	 *
	 * @return array         The filtered styles
	 */
	public function allow_container_ratio_style( $styles ) : array {

		// Bail if $styles isn't an array.
		if ( ! is_array( $styles ) ) {
			return $styles;
		}

		$styles[] = '--aspect-ratio';
		return $styles;
	}

	/**
	 * Enqueue our CSS
	 *
	 * @return void
	 */
	public function enqueue_css() : void {

		// Bail if images shouldn't wrap in a container.
		// $disable = apply_filters( 'Edge_Images\disable_container_wrap', false );
		// if ( $disable ) {
		// return;
		// }

		// Get our stylesheet
		$stylesheet_path = Helpers::STYLES_PATH . '/images.css';
		if ( ! file_exists( $stylesheet_path ) ) {
			return; // Bail if we couldn't find it.
		}

		// Enqueue a dummy style
		wp_register_style( 'edge-images', false );
		wp_enqueue_style( 'edge-images' );

		// Output the stylesheet inline
		$stylesheet = file_get_contents( $stylesheet_path );
		wp_add_inline_style( 'edge-images', $stylesheet );
	}

	/**
	 * Alter block editor image rendering
	 *
	 * TODO: Account for when images are linked (via $block['attrs']['linkDestination']).
	 * TODO: Account for figure/figcaption.
	 *
	 * @param string|null   $pre_render   The pre-rendered content.
	 * @param array         $parsed_block The parsed block's properties.
	 * @param WP_Block|null $parent_block The parent block
	 *
	 * @return string|null                The HTML content to render.
	 */
	public function alter_image_block_rendering( $pre_render, array $parsed_block, $parent_block ) {

		// Bail if we're in the admin or doing a REST request.
		if ( is_admin() || defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}

		// Bail if this isn't an image block .
		if ( $parsed_block['blockName'] !== 'core/image' ) {
			return $pre_render;
		}

		// Bail if there's no image ID set.
		if ( ! isset( $parsed_block['attrs']['id'] ) ) {
			return $pre_render;
		}

		// Build our image.
		$atts  = $this->get_image_atts( $parsed_block );
		$image = $this->get_content_image( $parsed_block['attrs']['id'], $atts );

		// Bail if we didn't get an image; fall back to the original block.
		if ( ! $image ) {
			return $pre_render;
		}

		return $image;
	}

	/**
	 * Gets atts from the <img> to pass to the edge <img>
	 *
	 * @param  array $parsed_block The parsed block's properties.
	 * @return array               The image atts array
	 */
	private function get_image_atts( array $parsed_block ) : array {
		$atts = array();

		// Get the alt attribute if it's set.
		$atts['alt'] = Helpers::get_alt_from_img_el( $parsed_block['innerHTML'] );

		// Get the link destination if it's set.
		if ( isset( $parsed_block['attrs']['linkDestination'] ) ) {
			$atts['href'] = $this->get_image_link( $parsed_block );
		}

		// Get the caption if there's one present.
		$caption = Helpers::get_caption_from_img_el( $parsed_block['innerHTML'] );
		if ( $caption && $caption !== '' ) {
			$atts['caption'] = $caption;
		}

		return $atts;
	}

	/**
	 * Get the image link
	 *
	 * @param  array $parsed_block The parsed block's properties.
	 *
	 * @return string              The image link
	 */
	private function get_image_link( array $parsed_block ) : string {
		switch ( $parsed_block['attrs']['linkDestination'] ) {
			case 'custom':
				$href = Helpers::get_link_from_img_el( $parsed_block['innerHTML'] );
				break;
			case 'attachment':
				$href = get_attachment_link( $parsed_block['attrs']['id'] );
				break;
			case 'media':
				$image = get_edge_image_object( $parsed_block['attrs']['id'], array(), 'full' );
				if ( ! $image ) {
					break;
				}
				$href = $image->attrs['src'];
				break;
			default:
				$href = '';
		}
		return $href;
	}

	/**
	 * Gets an image sized for display in the_content.
	 *
	 * @param  int $id The attachment ID.
	 *
	 * @return false|Image The Edge Image
	 */
	private function get_content_image( int $id, array $atts = array() ) {

		// Get the height and width of the full-sized image.
		$image = wp_get_attachment_image_src( $id, 'full' );
		if ( ! $image ) {
			return false;
		}

		// Constrain our image to the maximum content width, based on the ratio.
		$attrs = Helpers::constrain_image_to_content_width( $image[1], $image[2] );

		// Add WP's native block class(es).
		if ( ! isset( $atts['container-class'] ) ) {
			$atts['container-class'] = array();
		}
		$atts['container-class'][] = 'wp-block-image';

		// Get our transformed image.
		$image = get_edge_image( $id, $atts, 'content', false );

		// Bail if we didn't get an image.
		if ( ! $image ) {
			return false;
		}

		return $image;
	}

	/**
	 * Decorate our edge image with appropriate atts and markup
	 *
	 * @param  string $html             The <img> HTML.
	 * @param  int    $attachment_id    The attachment ID.
	 * @param  mixed  $size             The image size.
	 * @param  bool   $icon             Whether to use an icon.
	 * @param  array  $attr             The image attributes.
	 *
	 * @return string                   The modified HTML.
	 */
	public static function decorate_edge_image( $html = '', $attachment_id = 0, $size = false, bool $icon = false, $attr = array() ) : string {

		// Bail if there's no HTML.
		if ( ! $html ) {
			return '';
		}

		// Bail if there's no attachment ID.
		if ( ! $attachment_id ) {
			return $html;
		}

		// Bail if this image has been excluded via a filter.
		if ( ! Helpers::should_transform_image( $attachment_id ) ) {
			return $html;
		}

		// Merge defaults into $attr.
		$defaults      = Helpers::get_default_image_attrs();
		$attr['class'] = implode( ' ', array_merge( explode( ' ', $attr['class'] ), $defaults['class'] ) );
		$attr          = wp_parse_args( $attr, $defaults );

		// Get the edge image sizes array.
		$sizes = apply_filters( 'Edge_Images\sizes', Helpers::get_wp_image_sizes() );

		// Grab the attrs for the image size, or continue with defaults.
		if ( array_key_exists( $size, $sizes ) ) {
			$attr = wp_parse_args( $sizes[ $size ], $attr );
		}

		// Maybe wrap the picture in a link.
		if ( isset( $attr['href'] ) && $attr['href'] ) {
			$html = sprintf(
				'<a href="%s">%s</a>',
				$attr['href'],
				$html
			);
		}

		// Maybe get a caption.
		if ( isset( $attr['caption'] ) && $attr['caption'] ) {
			$html = sprintf(
				'%s<figcaption>%s</figcaption>',
				$html,
				$attr['caption']
			);
		}

		// Maybe wrap the image in a container.
		if ( apply_filters( 'Edge_Images\disable_container_wrap', false ) !== true ) {
			$html = sprintf(
				'<%s style="%s" class="%s %s">%s</%s>',
				( isset( $attr['container-type'] ) ) ? $attr['container-type'] : 'picture',
				self::get_container_styles( $attr ),
				( isset( $attr['container-class'] ) ) ? Helpers::classes_array_to_string( $attr['container-class'] ) : null,
				'image-id-' . $attachment_id,
				$html,
				( isset( $attr['container-type'] ) ) ? $attr['container-type'] : 'picture',
			);
		}

		$html = wp_kses(
			$html,
			array(
				'figure'     => Helpers::allowed_container_attrs(),
				'picture'    => Helpers::allowed_container_attrs(),
				'img'        => Helpers::allowed_img_attrs(),
				'a'          => array( 'href' => array() ),
				'figcaption' => array(),
			)
		);

		return $html;
	}

	/**
	 * Get the inline styles for the container tag
	 *
	 * @param  array $attr The image attributes.
	 * @return string      The style attribute values
	 */
	private static function get_container_styles( $attr ) : string {

		$styles = array();

		// Set the aspect ratio.
		$ratio    = isset( $attr['ratio'] ) ? $attr['ratio'] : '1/1';
		$styles[] = '--aspect-ratio:' . $ratio;

		// Add height and width inline styles if this is a fixed image.
		if ( $attr['layout'] === 'fixed' ) {
			if ( $attr['width'] ) {
				$styles[] = sprintf( 'max-width:%dpx', $attr['width'] );
			}
			if ( $attr['height'] ) {
				$styles[] = sprintf( 'max-height:%dpx', $attr['height'] );
			}
		}

		return implode( ';', $styles );

	}

	/**
	 * Remove the (first two) height and width attrs from <img> markup.
	 *
	 * NOTE: Widthout this, we create duplicate properties
	 *       with wp_get_attachment_image_attributes!
	 *
	 * @param  string $html             The <img> HTML.
	 * @param  int    $attachment_id    The attachment ID.
	 * @param  mixed  $size             The image size.
	 * @param  bool   $icon             Whether to use an icon.
	 * @param  array  $attr             The image attributes.
	 *
	 * @return string       The modified tag
	 */
	public function remove_dimension_attributes( $html = '', $attachment_id, $size = false, $icon = false, $attr = array() ) : string {

		// Bail if there's no HTML.
		if ( ! $html ) {
			return '';
		}

		// Bail if there's no attachment ID.
		if ( ! $attachment_id ) {
			return $html;
		}

		// Bail if this image has been excluded via a filter.
		if ( ! Helpers::should_transform_image( $attachment_id ) ) {
			return $html;
		}

		$html = preg_replace( '/(width|height)="\d*"\s/', '', $html, 2 );
		return $html;
	}

	/**
	 * Check whether an image should use the edge
	 *
	 * @param int   $id The attachment ID.
	 *
	 * @param  array $attrs The attachment attributes.
	 *
	 * @return bool
	 */
	public function image_should_use_edge( int $id, array $attrs ) : bool {

		// Bail if we shouldn't be transforming this image.
		if ( ! Helpers::should_transform_image( $id ) ) {
			return false;
		}

		// Placeholder logic for broader exclusion.

		return true;
	}

	/**
	 * Alter an image to use the edge
	 *
	 * @param array        $attrs      The attachment attributes.
	 * @param \WP_Post     $attachment The attachment.
	 * @param string|array $size The attachment size.
	 *
	 * @return array             The modified image attributes
	 */
	public function route_images_through_edge( $attrs, $attachment, $size ) : array {

		// Bail if $attrs isn't an array.
		if ( ! is_array( $attrs ) ) {
			return $attrs;
		}

		// Bail if $attachment isn't a WP_POST.
		if ( ! is_a( $attachment, '\WP_POST' ) ) {
			return $attrs;
		}

		// Bail if $size isn't a string or an array.
		if ( ! ( is_string( $size ) || is_array( $size ) ) ) {
			return $attrs;
		}

		// Bail if this image shouldn't use the edge.
		if ( ! $this->image_should_use_edge( $attachment->ID, $attrs ) ) {
			return $attrs;
		}

		// Get the image object.
		$image = new Image( $attachment->ID, $attrs, $size );

		// Flatten the array properties.
		$image->flatten_array_properties();

		return $image->attrs;
	}

}
