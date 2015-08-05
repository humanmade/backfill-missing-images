<?php

namespace Backfill_Missing_Images;

class Image_Replacer {

	public function __construct() {
		$this->upload_dir = wp_upload_dir();
	}

	public function get_posts_with_images_in_content() {
		global $wpdb;
		$posts = $wpdb->get_col( "SELECT ID FROM $wpdb->posts WHERE post_content REGEXP '-[0-9]+x[0-9]+\.(jpg|png|jpeg|gif|bmp)(\\'|\")'" );

		return $posts;
	}

	public function get_images_from_string( $string ) {

		$class_regex = 'class=".*?wp-image-(?P<post_id>[\d]+).*?"';
		$src_regex = 'src="(?P<url>' . $this->upload_dir['baseurl'] . '/[\d]+/[\d]+/([^"]+-(?P<size>[\d]+x[\d]+)\.(jpg|png|jpeg|bmp)))"';

		$regexes = array(
			sprintf( '<img [^>]*?%s [^>]*?%s', $class_regex, $src_regex ),
			sprintf( '<img [^>]*?%s [^>]*?%s', $src_regex, $class_regex )
		);

		$todo = array();

		foreach ( $regexes as $regex ) {
			preg_match_all( '#' . $regex . '#', $string, $matches, PREG_SET_ORDER );

			if ( ! $matches ) {
				continue;
			}

			foreach ( $matches as $match ) {

				$original_url = str_replace( '-' . $match['size'], '', $match['url'] );
				$original_path = str_replace( $this->upload_dir['baseurl'], $this->upload_dir['basedir'], $original_url );
				$todo[] = array(
					'size'          => array_map( 'absint', explode( 'x', $match['size'] ) ),
					'url'           => $match['url'],
					'original_path' => $original_path,
					'id'            => $match['post_id'],
				);
			}
		}

		return $todo;
	}

	/**
	 * Get attachment images in the string which have the url set
	 * to an external location.
	 *
	 * @param  [type] $string [description]
	 * @return [type]         [description]
	 */
	public function get_external_attachments_from_string( $string ) {
		$class_regex = 'class=".*?wp-image-(?P<post_id>[\d]+).*?"';
		$src_regex = 'src="(?P<url>.+?)"';

		$regexes = array(
			sprintf( '<img [^>]*?%s [^>]*?%s.*?>', $class_regex, $src_regex ),
			sprintf( '<img [^>]*?%s [^>]*?%s.*?>', $src_regex, $class_regex )
		);

		$upload_dir = wp_upload_dir();
		$todo = array();

		foreach ( $regexes as $regex ) {
			preg_match_all( '#' . $regex . '#', $string, $matches, PREG_SET_ORDER );

			if ( ! $matches ) {
				continue;
			}

			foreach ( $matches as $match ) {

				if ( strpos( $match['url'], $upload_dir['baseurl'] ) !== false ) {
					continue;
				}

				$has_width  = preg_match( '/width="(\d+)"/', $match[0], $width );
				$has_height = preg_match( '/height="(\d+)"/', $match[0], $height );

				$todo[] = array(
					'url'           => $match['url'],
					'id'            => (int) $match['post_id'],
					'size'          => array( $has_width ? (int) $width[1] : 0, $has_height ? (int) $height[1] : 0 ),
				);
			}
		}

		return $todo;
	}

	public function is_image_missing( $image ) {

		global $_wp_additional_image_sizes;

		$sizes = array_map( function( $size ) {
			return $size['width'] . 'x' . $size['height'];
		}, (array) $_wp_additional_image_sizes );

		foreach ( array( 'thumbnail', 'medium', 'large' ) as $size ) {
			$sizes[] = get_option( $size . '_size_w' ) . 'x' . get_option( $size . '_size_h' );
		}

		$todo = array();

		if ( in_array( implode( 'x', $image['size'] ), $sizes ) ) {
			return false;
		}

		$size = $image['size'];

		$src = wp_get_attachment_image_src( $image['id'], array( $size[0], $size[1] ) );

		if ( $src ) {
			if ( $src[0] === $image['url'] ) {
				return false;
			}
		}

		if ( strpos( $image['url'], $this->upload_dir['baseurl'] ) !== 0 ) {
			return false;
		}

		$image_path = str_replace( $this->upload_dir['baseurl'], $this->upload_dir['basedir'], $image['url'] );

		if ( file_exists( $image_path ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Generate a new image for a given image
	 *
	 * @param  string $file
	 * @param  array $size
	 * @return WP_Error|array
	 */
	public function generate_image( $file, $size ) {

		$size['width'] = $size[0];
		$size['height'] = $size[1];
		$size['crop'] = true;

		$editor = wp_get_image_editor( $file );

		if ( is_wp_error( $editor ) ) {
			return $editor;
		}

		$new = $editor->multi_resize( array( $size ) );

		return $new;
	}
}