<?php

namespace Backfill_Missing_Images;

use WP_CLI;

/**
 * Generate images for any non-registered image sizes in post content
 */
class WP_CLI_Command extends \WP_CLI_Command {

	/**
	 * @subcommand fix-image-sizes
	 * @synopsis [--dry-run] [--verbose] [--post-id=<post-id>]
	 */
	public function fix_image_sizes( $args, $args_assoc ) {

		$args_assoc = wp_parse_args( $args_assoc, array(
			'dry-run' => false,
			'verbose' => false
		));

		$image_replacer = new Image_Replacer();

		WP_CLI::line( 'Looking for posts...' );

		if ( ! empty( $args_assoc['post-id'] ) ) {
			$posts = explode( ',', $args_assoc['post-id'] );
		} else {
			$posts = $image_replacer->get_posts_with_images_in_content();
		}

		WP_CLI::line( sprintf( 'Found %d posts with embedded images.', $total = count( $posts) ) );

		$upload_dir = wp_upload_dir();
		$progress = \WP_CLI\Utils\make_progress_bar( 'Searching for broken images', $total );

		$todo = array();

		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );
			$progress->tick();

			$images = $image_replacer->get_images_from_string( $post->post_content );

			if ( $args_assoc['verbose'] ) {
				WP_CLI::line( sprintf( 'Found %d images in post.', count( $images ) ) );
			}

			foreach ( $images as $image ) {
				if ( $image_replacer->is_image_missing( $image ) ) {
					$todo[] = $image;

					if ( $args_assoc['verbose'] ) {
						WP_CLI::line( sprintf( 'Image %s is missing.', $image['url'] ) );
					}
				}
			}

		}

		$progress->finish();

		$progress = \WP_CLI\Utils\make_progress_bar( sprintf( 'Fixing %d broken images', count( $todo ) ), count( $todo ) );

		foreach ( $todo as $image ) {

			$progress->tick();

			if ( $args_assoc['verbose'] ) {
				WP_CLI::line( sprintf( 'Missing image URL %s, creating new image...', $image['url'] ) );
			}

			if ( ! $args_assoc['dry-run'] ) {
				$image_replacer->generate_image( $image['original_path'], $image['size'] );
			}
		}

		$progress->finish();

		WP_CLI::success( 'Done.' );
	}

	/**
	 * @subcommand fix-external-attachments
	 * @synopsis [--dry-run] [--verbose] [--post-id=<post-id>]
	 */
	public function fix_external_attachments( $args, $args_assoc ) {

		$args_assoc = wp_parse_args( $args_assoc, array(
			'dry-run' => false,
			'verbose' => false
		));

		$image_replacer = new Image_Replacer();

		WP_CLI::line( 'Looking for posts...' );

		if ( ! empty( $args_assoc['post-id'] ) ) {
			$posts = explode( ',', $args_assoc['post-id'] );
		} else {
			$posts = get_posts( array( 'post_status' => 'any', 'fields' => 'ids', 'showposts' => -1 ) );
		}

		WP_CLI::line( sprintf( 'Found %d posts with.', $total = count( $posts) ) );

		$progress = \WP_CLI\Utils\make_progress_bar( 'Searching for broken images', $total );

		$todo = array();

		foreach ( $posts as $post_id ) {
			$post = get_post( $post_id );
			$progress->tick();

			$images = $image_replacer->get_external_attachments_from_string( $post->post_content );

			if ( $args_assoc['verbose'] ) {
				WP_CLI::line( sprintf( 'Found %d images in post.', count( $images ) ) );
			}

			$t = array( 'post' => $post, 'image' => array() );

			foreach ( $images as $image ) {
				if ( $image['post'] = get_post( $post_id ) ) {
					$t['image'][] = $image;

					if ( $args_assoc['verbose'] ) {
						WP_CLI::line( sprintf( 'Image %s is missing.', $image['url'] ) );
					}
				}
			}

			$todo[] = $t;
		}

		$progress->finish();

		$progress = \WP_CLI\Utils\make_progress_bar( sprintf( 'Fixing %d broken images', count( $todo ) ), count( $todo ) );

		$posts_to_update = array();

		foreach ( $todo as $t ) {

			$progress->tick();

			foreach ( $t['image'] as $image ) {
				$new_url = wp_get_attachment_image_src( $image['id'], $image['size'] )[0];

				if ( $args_assoc['verbose'] ) {
					WP_CLI::line( sprintf( 'Missing image URL %s, replacing with local image %s', $image['url'], $new_url ) );
				}

				$t['post']->post_content = str_replace( $image['url'], $new_url, $t['post']->post_content );
			}

			if ( ! $args_assoc['dry-run'] ) {
				wp_update_post( $t['post'] );
			}
		}

		$progress->finish();

		WP_CLI::success( 'Done.' );
	}
}