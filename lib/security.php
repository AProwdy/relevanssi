<?php
/**
 * /lib/security.php
 *
 * Anti-scraping and search protection logic.
 *
 * @package Relevanssi
 * @author  AP Development Team
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 */

add_filter( 'relevanssi_search_ok', 'relevanssi_security_check_referrer', 20, 2 );
add_filter( 'relevanssi_bots_to_block', 'relevanssi_security_add_ai_bots' );

/**
 * Adds modern AI bots and scrapers to the blocked list.
 *
 * @param array $bots The current list of blocked bots.
 *
 * @return array The updated list of blocked bots.
 */
function relevanssi_security_add_ai_bots( $bots ) {
	$ai_bots = array(
		'OpenAI'     => 'GPTBot',
		'ChatGPT'    => 'ChatGPT-User',
		'Claude'     => 'ClaudeBot',
		'Anthropic'  => 'Claude-Web',
		'Google-AI'  => 'Google-Extended',
		'Perplexity' => 'PerplexityBot',
		'Common-AI'  => 'CCBot',
	);

	return array_merge( $bots, $ai_bots );
}

/**
 * Checks the referrer and user-agent to protect against unauthorized scraping.
 *
 * @param bool     $search_ok Whether the search is allowed.
 * @param WP_Query $query     The WP_Query object.
 *
 * @return bool True if allowed, false if blocked.
 */
function relevanssi_security_check_referrer( $search_ok, $query ) {
	// If search is already disabled, keep it that way.
	if ( ! $search_ok ) {
		return $search_ok;
	}

	// Allow admin searches.
	if ( is_admin() ) {
		return $search_ok;
	}

	// Block known bots/scrapers using Relevanssi's built-in check.
	if ( function_exists( 'relevanssi_user_agent_is_bot' ) && relevanssi_user_agent_is_bot() ) {
		return false;
	}

	// Check referrer.
	$referrer = $_SERVER['HTTP_REFERER'] ?? '';

	if ( ! empty( $referrer ) ) {
		$referrer_host = wp_parse_url( $referrer, PHP_URL_HOST );
		$site_host     = wp_parse_url( home_url(), PHP_URL_HOST );

		// Normalize hosts by stripping 'www.' for a more reliable comparison.
		$referrer_host = str_replace( 'www.', '', strtolower( $referrer_host ) );
		$site_host     = str_replace( 'www.', '', strtolower( $site_host ) );

		// If referrer is from another domain, block it.
		if ( $referrer_host !== $site_host ) {
			return false;
		}
	}

	// If referrer is empty (direct link) or matches site host, allow it.
	return $search_ok;
}
