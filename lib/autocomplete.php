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

/**
 * Runs a Relevanssi search for autocomplete suggestions.
 *
 * Uses the same WP_Query + relevanssi_do_query() pattern as
 * relevanssi_admin_search(), so results are filtered through the exact
 * same restrictions (excluded posts/categories, indexed post types,
 * language scoping) as a normal search.
 *
 * @param string $q           The search query.
 * @param int    $max_results Maximum number of suggestions to return.
 *
 * @return array Formatted suggestions, see relevanssi_autocomplete_format_result().
 */
function relevanssi_autocomplete_get_results( string $q, int $max_results ) {
	$max_results = max( 1, $max_results );

	$query = new WP_Query();
	$query->parse_query(
		array(
			's'              => $q,
			'relevanssi'     => true,
			'posts_per_page' => $max_results,
			'post_status'    => 'publish',
		)
	);
	$posts = relevanssi_do_query( $query );

	return array_map( 'relevanssi_autocomplete_format_result', $posts );
}
