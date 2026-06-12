<?php
/**
 * /lib/autocomplete.php
 *
 * Search autocomplete (live suggestions) for Relevanssi.
 *
 * @package Relevanssi
 * @author  AP Development Team
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 * @see     https://www.relevanssi.com/
 */

/**
 * Formats a post into an autocomplete suggestion.
 *
 * Suggestions never include price or stock information, for any user.
 * Non-product results, and products without a featured image, get a null
 * thumbnail; the front end shows a generic icon in that case.
 *
 * @param WP_Post $post The post to format.
 *
 * @return array {
 *     @type string      $title     The post title.
 *     @type string      $url       The post permalink.
 *     @type string      $type      'product' for the 'product' post type, 'other' otherwise.
 *     @type string|null $thumbnail The product thumbnail URL, or null.
 * }
 */
function relevanssi_autocomplete_format_result( $post ) {
	$type      = 'product' === $post->post_type ? 'product' : 'other';
	$thumbnail = null;

	if ( 'product' === $type ) {
		$thumbnail_url = get_the_post_thumbnail_url( $post, 'thumbnail' );
		if ( $thumbnail_url ) {
			$thumbnail = $thumbnail_url;
		}
	}

	return array(
		'title'     => get_the_title( $post ),
		'url'       => get_permalink( $post ),
		'type'      => $type,
		'thumbnail' => $thumbnail,
	);
}
