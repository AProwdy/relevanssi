<?php
/**
 * /lib/tabs/autocomplete-tab.php
 *
 * Prints out the Autocomplete tab in Relevanssi settings.
 *
 * @package Relevanssi
 * @author  AP Development Team
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 * @see     https://www.relevanssi.com/
 */

/**
 * Prints out the autocomplete tab in Relevanssi settings.
 */
function relevanssi_autocomplete_tab() {
	$enabled     = get_option( 'relevanssi_autocomplete_enabled' );
	$enabled     = relevanssi_check( $enabled );
	$min_chars   = get_option( 'relevanssi_autocomplete_min_chars' );
	$max_results = get_option( 'relevanssi_autocomplete_max_results' );
	?>
	<table class="form-table" role="presentation">
	<tr id="row_autocomplete_enabled">
		<th scope="row">
			<?php esc_html_e( 'Enable autocomplete', 'relevanssi' ); ?>
		</th>
		<td>
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Show live search suggestions as visitors type.', 'relevanssi' ); ?></legend>
			<label for='relevanssi_autocomplete_enabled'>
				<input type='checkbox' name='relevanssi_autocomplete_enabled' id='relevanssi_autocomplete_enabled' <?php echo esc_html( $enabled ); ?> />
				<?php esc_html_e( 'Show live search suggestions as visitors type.', 'relevanssi' ); ?>
			</label>
		</fieldset>
		<p class="description"><?php esc_html_e( 'Adds a dropdown of suggested results under search boxes site-wide as the visitor types. No price or stock information is shown.', 'relevanssi' ); ?></p>
		</td>
	</tr>
	<tr id="row_autocomplete_min_chars">
		<th scope="row">
			<label for='relevanssi_autocomplete_min_chars'><?php esc_html_e( 'Minimum characters', 'relevanssi' ); ?></label>
		</th>
		<td>
			<input type='number' min='1' name='relevanssi_autocomplete_min_chars' id='relevanssi_autocomplete_min_chars' value='<?php echo esc_attr( $min_chars ); ?>' />
			<p class="description"><?php esc_html_e( 'How many characters the visitor must type before suggestions are fetched.', 'relevanssi' ); ?></p>
		</td>
	</tr>
	<tr id="row_autocomplete_max_results">
		<th scope="row">
			<label for='relevanssi_autocomplete_max_results'><?php esc_html_e( 'Number of suggestions', 'relevanssi' ); ?></label>
		</th>
		<td>
			<input type='number' min='1' name='relevanssi_autocomplete_max_results' id='relevanssi_autocomplete_max_results' value='<?php echo esc_attr( $max_results ); ?>' />
			<p class="description"><?php esc_html_e( 'How many suggestions to show in the dropdown.', 'relevanssi' ); ?></p>
		</td>
	</tr>
	</table>
	<?php
}
