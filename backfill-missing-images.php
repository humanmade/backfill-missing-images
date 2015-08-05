<?php

namespace Backfill_Missing_Images;

/*
 * Plugin Name: Backfill Missing Images
 * Description: Generate images for any 404s in your post content(s)
 * Author: humanmade
 * Author URI: http://hmn.md
 * Version: 0.1
 * License: GPL version 2 or later - http://www.gnu.org/licenses/old-licenses/gpl-2.0.html
*/

require_once dirname( __FILE__ ) . '/inc/class-image-replacer.php';

if ( defined( 'WP_CLI' ) && WP_CLI ) {
	include __DIR__ . '/inc/class-wp-cli-command.php';
	\WP_CLI::add_command( 'backfill-missing-images', __NAMESPACE__ . '\\WP_CLI_Command' );
}
