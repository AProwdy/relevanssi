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
 * Results are cached in a transient for one minute, keyed on the query,
 * post type, and result count, so repeated keystrokes (especially the
 * backspace-and-retype pattern common while typing) skip the search.
 *
 * @param string $q           The search query.
 * @param int    $max_results Maximum number of suggestions to return.
 * @param string $post_type   Restrict results to this post type, or '' for all
 *                             indexed post types.
 *
 * @return array Formatted suggestions, see relevanssi_autocomplete_format_result().
 */
function relevanssi_autocomplete_get_results( string $q, int $max_results, string $post_type = '' ) {
	$max_results = max( 1, $max_results );

	$cache_key = 'relevanssi_ac_' . md5( wp_json_encode( array( $q, $max_results, $post_type ) ) );

	$cached_results = get_transient( $cache_key );
	if ( is_array( $cached_results ) ) {
		return $cached_results;
	}

	$args = array(
		's'              => $q,
		'relevanssi'     => true,
		'posts_per_page' => $max_results,
		'post_status'    => 'publish',
	);

	if ( '' !== $post_type ) {
		$args['post_type'] = $post_type;
	}

	$query = new WP_Query();
	$query->parse_query( $args );
	$posts = relevanssi_do_query( $query );

	$results = array_map( 'relevanssi_autocomplete_format_result', $posts );

	set_transient( $cache_key, $results, MINUTE_IN_SECONDS );

	return $results;
}

add_action( 'wp_ajax_relevanssi_autocomplete', 'relevanssi_autocomplete_ajax' );
add_action( 'wp_ajax_nopriv_relevanssi_autocomplete', 'relevanssi_autocomplete_ajax' );
add_action( 'wp_enqueue_scripts', 'relevanssi_autocomplete_enqueue_scripts' );

/**
 * Decides whether an autocomplete request should run a search.
 *
 * @param string $q The search query string.
 *
 * @return bool True if the feature is enabled and the query is long enough.
 */
function relevanssi_autocomplete_should_search( string $q ) {
	if ( 'on' !== get_option( 'relevanssi_autocomplete_enabled' ) ) {
		return false;
	}

	$min_chars = (int) get_option( 'relevanssi_autocomplete_min_chars', 3 );

	return mb_strlen( $q ) >= $min_chars;
}

/**
 * Handles the relevanssi_autocomplete AJAX action.
 *
 * Registered for both logged-in and logged-out users, since search must
 * work for anonymous visitors.
 */
function relevanssi_autocomplete_ajax() {
	check_ajax_referer( 'relevanssi_autocomplete', 'nonce' );

	$q = isset( $_REQUEST['q'] ) && is_string( $_REQUEST['q'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['q'] ) ) : '';

	$post_type = '';
	if ( isset( $_REQUEST['post_type'] ) && is_string( $_REQUEST['post_type'] ) ) {
		$requested_post_type = sanitize_key( wp_unslash( $_REQUEST['post_type'] ) );
		if ( post_type_exists( $requested_post_type ) ) {
			$post_type = $requested_post_type;
		}
	}

	if ( ! relevanssi_autocomplete_should_search( $q ) ) {
		wp_send_json_success(
			array(
				'query'   => $q,
				'results' => array(),
			)
		);
		return;
	}

	$max_results = (int) get_option( 'relevanssi_autocomplete_max_results', 5 );

	wp_send_json_success(
		array(
			'query'   => $q,
			'results' => relevanssi_autocomplete_get_results( $q, $max_results, $post_type ),
		)
	);
}

/**
 * Enqueues the autocomplete script and style on the front end.
 *
 * Only runs when the feature is enabled in settings and we're not in the
 * admin area.
 *
 * @global array $relevanssi_variables The global Relevanssi variables array.
 */
function relevanssi_autocomplete_enqueue_scripts() {
	if ( is_admin() ) {
		return;
	}

	if ( 'on' !== get_option( 'relevanssi_autocomplete_enabled' ) ) {
		return;
	}

	global $relevanssi_variables;
	$plugin_dir_url = plugin_dir_url( $relevanssi_variables['file'] );
	$version        = $relevanssi_variables['plugin_version'];

	wp_enqueue_style( 'relevanssi-autocomplete', $plugin_dir_url . 'lib/autocomplete.css', array(), $version );
	wp_enqueue_script( 'relevanssi-autocomplete', $plugin_dir_url . 'lib/autocomplete.js', array(), $version, true );

	wp_localize_script(
		'relevanssi-autocomplete',
		'relevanssiAutocomplete',
		array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'relevanssi_autocomplete' ),
			'minChars'   => (int) get_option( 'relevanssi_autocomplete_min_chars', 3 ),
			'maxResults' => (int) get_option( 'relevanssi_autocomplete_max_results', 5 ),
		)
	);
}
