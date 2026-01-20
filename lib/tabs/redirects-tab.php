<?php
/**
 * /lib/tabs/redirects-tab.php
 *
 * Prints out the Redirects tab in Relevanssi settings.
 *
 * @package Relevanssi
 * @author  Mikko Saari
 * @license https://wordpress.org/about/gpl/ GNU General Public License
 * @see     https://www.relevanssi.com/
 */

/**
 * Prints out the redirects tab in Relevanssi settings.
 */
function relevanssi_redirects_tab() {
	$redirects = get_option( 'relevanssi_redirects', '' );
	?>
	<h2><?php esc_html_e( 'Redirects', 'relevanssi' ); ?></h2>

	<p><?php esc_html_e( 'Set up redirects. These are keywords that automatically redirect the user to certain page, without going through the usual search process.', 'relevanssi' ); ?></p>
	
	<table class="form-table">
	<tr>
		<th scope="row"><label for="relevanssi_redirects"><?php esc_html_e( 'Redirects', 'relevanssi' ); ?></label></th>
		<td>
			<textarea name="relevanssi_redirects" id="relevanssi_redirects" rows="10" cols="80"><?php echo esc_textarea( $redirects ); ?></textarea>
			<p class="description"><?php esc_html_e( 'Format: keyword = URL. One per line. Example: job = /careers/', 'relevanssi' ); ?></p>
		</td>
	</tr>
	</table>
	<?php
}