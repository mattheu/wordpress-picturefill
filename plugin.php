<?php
/*
Plugin Name: WordPress Picturefill
Plugin URI: https://github.com/mattheu/wordpress-picturefill
Description: Abstraction for creating the markup required by picturefill within WordPress. Uses WPThumb for easy image resizing.
Author: Matth.eu
Version: 1.0
Author URI: http://matth.eu
*/

define( 'WPPF_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WPPF_URL', plugin_dir_url( __FILE__ ) );

class WPThumb_Picture {

	// The default image - used as a default/fallback.
	private $default;

	// Image attrs including class and alt.
	private $attr;

	// Array of all the different images used to build the picture element.
	private $images = array();

	// Retina Multiplier. Usually 2x. Could use 1x or 1.5x if you like.
	public $high_res_multiplier = 2;

	public function __construct( $attr = array() ) {

		$this->attr = wp_parse_args(
			$attr,
			array(
				'class' => ''
			)
		);

	}

	/**
	 * Helper function for adding image sources for picture element.
	 *
	 * @param int $attachment_id 	The ID of the wordpress attachment.
	 * @param string $size          string or array. The image size of the required source.
	 * @param string $media_query   The media query used by this souce.
	 */
	public function add_picture_source( $attachment_id, $size = 'post-thumbnail', $media_query = '' ) {

		$this->images[] = array(
			'attachment_id' => $attachment_id,
			'size'          => $size,
			'media_query'	=> $media_query
		);

	}

	/**
	 * Get the picture element HTML
	 */
	public function get_picture() {

		if ( empty( $this->images ) )
			return;

		$output = sprintf(
			"\n<picture class=\"%s\">\n",
			implode( ' ', array_map( 'sanitize_html_class', explode( ' ', $this->attr['class'] ) ) )
		);

		$output .= "\t<!--[if IE 9]><video style=\"display: none;\"><![endif]-->\n";

		foreach ( $this->images as $image )
			$output .= $this->get_picture_source( $image );

		$output .= "\t<!--[if IE 9]></video><![endif]-->\n";

		$default = reset( $this->images );
		$output .= "\t" . wp_get_attachment_image( $default['attachment_id'], $default['size'] ) . "\n";

		$output .= '</picture>';

		return $output;

	}

	/**
	 * Get the source element for a single image.
	 *
	 * @param  [type] $image [description]
	 * @return [type]        [description]
	 */
	private function get_picture_source( $image ) {

		$image_defaults = array(
			'size' => 'thumbnail',
			'media_query' => null
		);

		$image = wp_parse_args( $image, $image_defaults );

		// The source element for the requested image
		$requested  = wp_get_attachment_image_src( $image['attachment_id'], $image['size'] );
		$data_media = esc_attr( $this->get_picture_source_media_attr( $image['media_query'] ) );

		$output = sprintf(
			"\t<source srcset=\"%s\" %s></span>\n",
			esc_attr( $requested[0] ),
			! empty( $data_media ) ? sprintf( 'media="%s"', esc_attr( $data_media ) ) : null
		);

		// The source element for the high res version of the requested image.
		$original = wp_get_attachment_image_src( $image['attachment_id'], 'full' );

		// Calculate the size args for the high resoloution image
		$size_high_res = array(
			0      => (int) $requested[1] * $this->high_res_multiplier,
			1      => (int) $requested[2] * $this->high_res_multiplier,
			'crop' => $requested[3]
		);

		// If the original image is at least as large as the high res version,
		// add a picture source for a high res version
		if ( $original[1] >= $size_high_res[0] && $original[2] >= $size_high_res[1] ) {

			$requested_high_res = wp_get_attachment_image_src( $image['attachment_id'], $size_high_res );
			$data_media = esc_attr( $this->get_picture_source_media_attr( $image['media_query'], true ) );
			$output .= sprintf(
				"\t<source srcset=\"%s\" %s></span>\n",
				esc_attr( $requested_high_res[0] ),
				! empty( $data_media ) ? sprintf( 'media="%s"', esc_attr( $data_media ) ) : null
			);

		}

		return $r;

	}

	/**
	 * Process the media attribute value. Adds high-res args if required.
	 *
	 * @param  string  $media_query media query. Currently only supports a single query. ('and' is ok, but ',' is not)
	 * @param  boolean $high_res      If this is for a high res image source, pass true to append the high res media query args.
	 * @return string               full string to add as data-media attribute.
	 */
	private function get_picture_source_media_attr( $media_query, $high_res = false ) {

		if ( $high_res ) {
			if ( empty( $media_query ) ) {
				return "(min-device-pixel-ratio: $this->high_res_multiplier), (-webkit-min-device-pixel-ratio: $this->high_res_multiplier)";
			} else {
				return "$media_query and (min-device-pixel-ratio: $this->high_res_multiplier), $media_query and (-webkit-min-device-pixel-ratio: $this->high_res_multiplier)";
			}
		} else {
			return $media_query;
		}

	}

}

/**
 *	Enqueue the picturefill scripts
 */
add_action( 'wp_enqueue_scripts', function() {

	add_action( 'wp_head', function() {
		echo '<script>document.createElement( "picture" );</script>';
	}, 1 );

	wp_enqueue_script( 'wppf_picturefill', trailingslashit( WPPF_URL ) . 'js/picturefill.min.js', false, true );

} );

/**
 * Returns a picture element for the passed args.
 *
 * @param  array $images An array of image args. Each image should be passed as an array of args: array( 'attachment_id' => int, 'size' => string or array, 'media_query' => string )
 * @return [type]         [description]
 */
function wpthumb_get_picture( $images, $attr = array() ) {

	$picture = new WPThumb_Picture( $attr );

	foreach ( $images as $image ) {
		$picture->add_picture_source( $image['attachment_id'], $image['size'], $image['media_query'] );
	}

	return $picture->get_picture();

}

/**
 * Filter the post thumbnail output.
 *
 * @param  string       $html
 * @param  int          $post_id
 * @param  int          $post_thumbnail_id
 * @param  string/array $size
 * @param  array        $attr
 * @return string html for the picture element.
 */
function _wpthumb_picture_post_thumbnail_html( $html, $post_id, $post_thumbnail_id, $size, $attr ) {

	$html = wpthumb_get_the_post_thumbnail_picture( $post_id, $size, $attr );

	return $html;

}
add_filter( 'post_thumbnail_html', '_wpthumb_picture_post_thumbnail_html', 10, 5 );

/**
 * Returns the post thumbnail in the picture element markup with high resoloution version.
 *
 * @param int $post_id Optional. Post ID.
 * @param string $size Optional. Image size. Defaults to 'post-thumbnail'.
 * @param string|array $attr Optional. Query string or array of attributes.
 */
function wpthumb_get_the_post_thumbnail_picture( $post_id = null, $size = 'post-thumbnail', $attr = '' ) {

	$post_id = ( null === $post_id ) ? get_the_ID() : $post_id;
	$post_thumbnail_id = get_post_thumbnail_id( $post_id );
	$size = apply_filters( 'post_thumbnail_size', $size );

	return wpthumb_get_attachment_picture( $post_thumbnail_id, $size, $attr );

}

/**
 * Output the post thumbnail in the picture element markup with high resoloution version.
 *
 * @param int $post_id Optional. Post ID.
 * @param string $size Optional. Image size. Defaults to 'post-thumbnail'.
 * @param string|array $attr Optional. Query string or array of attributes.
 */
function wpthumb_the_post_thumbnail_picture( $size = 'post-thumbnail', $attr = '' ) {

	echo wpthumb_get_the_post_thumbnail_picture( get_the_ID(), $size, $attr );

}

/**
 * Returns the <picture> element for the attachment.
 *
 * Return the markup required for the proposed picture html element as implemented by the picturefill polyfill.
 * https://github.com/scottjehl/picturefill
 *
 * @param int $post_id Optional. Post ID.
 * @param string $size Optional. Image size. Defaults to 'post-thumbnail'.
 * @param string|array $attr Optional. Query string or array of attributes.
 */
function wpthumb_get_attachment_picture( $attachment_id, $size, $attr = '' ) {

	if ( empty( $attachment_id ) )
		return;

	$picture = new WPThumb_Picture( $attr );
	$picture->add_picture_source( $attachment_id, $size );
	return $picture->get_picture();

}