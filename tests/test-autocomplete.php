<?php
/**
 * Class AutocompleteTest
 *
 * @package Relevanssi
 * @author  AP Development Team
 */

/**
 * Test the Relevanssi search autocomplete feature.
 *
 * @group autocomplete
 */
class AutocompleteTest extends WP_UnitTestCase {

	/**
	 * Installs Relevanssi and registers the 'product' post type (normally
	 * provided by WooCommerce) so autocomplete tests can create product
	 * posts without WooCommerce installed.
	 */
	public static function wpSetUpBeforeClass() {
		relevanssi_install();
		relevanssi_init();

		register_post_type(
			'product',
			array(
				'public' => true,
				'label'  => 'Products',
			)
		);
	}

	/**
	 * Test the autocomplete settings tab renders the saved option values.
	 */
	public function test_relevanssi_autocomplete_tab() {
		update_option( 'relevanssi_autocomplete_enabled', 'on' );
		update_option( 'relevanssi_autocomplete_min_chars', 4 );
		update_option( 'relevanssi_autocomplete_max_results', 8 );

		ob_start();
		relevanssi_autocomplete_tab();
		$output = ob_get_clean();

		$this->assertStringContainsString( "name='relevanssi_autocomplete_enabled'", $output );
		$this->assertStringContainsString( 'checked', $output );
		$this->assertStringContainsString( "name='relevanssi_autocomplete_min_chars'", $output );
		$this->assertStringContainsString( "value='4'", $output );
		$this->assertStringContainsString( "name='relevanssi_autocomplete_max_results'", $output );
		$this->assertStringContainsString( "value='8'", $output );
	}
}
