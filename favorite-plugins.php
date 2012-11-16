<?php
/*
Plugin Name: Favorite Plugins
Plugin URI: http://japh.wordpress.com/plugins/favorite-plugins
Description: Quickly and easily access and install your favorited plugins from WordPress.org, right from your dashboard.
Version: 0.7
Author: Japh
Author URI: http://japh.wordpress.com
License: GPL2
*/

/*  Copyright 2012  Japh  (email : wordpress@japh.com.au)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

/**
 * Favorite Plugins
 *
 * Quickly and easily access and install your favorited plugins from
 * WordPress.org, right from your dashboard.
 *
 * @package JaphFavoritePlugins
 * @author Japh <wordpress@japh.com.au>
 * @copyright 2012 Japh
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt GPL2
 * @version 0.7
 * @link http://japh.wordpress.com/plugins/favorite-plugins
 * @since 0.1
 */

/**
 * Main class for the Favourite Plugins plugin
 *
 * @package JaphFavoritePlugins
 * @copyright 2012 Japh
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt GPL2
 * @version 0.7
 * @since 0.1
 */
class Japh_Favorite_Plugins {

	public $version = '0.7';
	public $username = null;

	/**
	 * Constructor for the plugin's main class
	 *
	 * @since 0.1
	 */
	function __construct() {

		if ( (float) get_bloginfo( 'version' ) < 3.5 ) {

			$current_version = get_option( 'jfp_favourite_plugins_version' );

			if ( $current_version != $this->version ) {
				update_option( 'jfp_favourite_plugins_version', $this->do_update( $current_version ) );
			}

			add_action( 'init', array( $this, 'textdomain' ) );
			add_filter( 'install_plugins_tabs', array( $this, 'add_favorites_tab' ) );

			add_action( 'install_plugins_pre_favorites', array( $this, 'do_favorites_tab' ) );
			add_action( 'install_plugins_favorites', array( $this, 'install_plugins_favorites' ), 10, 1 );
			add_action( 'install_plugins_favorites', 'display_plugins_table');

			$this->username = get_user_option( 'wporg_favorites' );
		}
	}

	/**
	 * Housekeeping things for plugin activation
	 *
	 * @since 0.1
	 */
	function activate() {

		// Nothing to do...

	}

	/**
	 * Housekeeping things for plugin deactivation
	 *
	 * @since 0.1
	 */
	function deactivate() {

		// Nothing to do...

	}

	/**
	 * Add a Favorites tab to the install plugins tabs
	 *
	 * This method also checks if there is already a Favorites tab,
	 * which is potentially coming to WordPress core in 3.5
	 *
	 * @since 0.1
	 * @param array $tabs The array of existing install plugins tabs
	 * @return array The new array of install plugins tabs
	 */
	function add_favorites_tab( $tabs ) {

		$tabs['favorites'] = __( 'Favorites', 'jfp' );
		return $tabs;

	}

	/**
	 * Output contents of the Favorites tab
	 *
	 * Props @Otto42 : http://core.trac.wordpress.org/ticket/22002
	 * Any code here from Otto is used with permission.
	 *
	 * @since 0.1
	 * @param array $paged The current page for the tab
	 * @return void
	 */
	function do_favorites_tab() {
		global $wp_list_table, $paged;

		$this->username = isset( $_REQUEST['user'] ) ? stripslashes( $_REQUEST['user'] ) : $this->username;

		if ( $this->username ) {
			$per_page = 30;

			$args = array( 'user' => $this->username, 'page' => $paged, 'per_page' => $per_page );
			update_user_meta( get_current_user_id(), 'wporg_favorites', $this->username );

			$api = plugins_api( 'query_plugins', $args );

			$wp_list_table->items = $api->plugins;
			$wp_list_table->set_pagination_args(
				array(
					'total_items' => $api->info['results'],
					'per_page' => $per_page,
				)
			);
		} else {
			$args = false;
		}
	}

	/**
	 * Output username form at the top of the favorite plugins table
	 *
	 * Props @Otto42 : http://core.trac.wordpress.org/ticket/22002
	 * Any code here from Otto is used with permission.
	 *
	 * Updated for feature parity with WordPress 3.5
	 *
	 * @since 0.5
	 * @param int $page Current pagination number
	 * @return void
	 */
	function install_plugins_favorites( $page = 1 ) {
		$this->username = isset( $_REQUEST['user'] ) ? stripslashes( $_REQUEST['user'] ) : $this->username;
		?>
			<p class="install-help"><?php _e( 'If you have marked plugins as favorites on WordPress.org, you can browse them here.' ); ?></p>
			<form method="get" action="">
				<input type="hidden" name="tab" value="favorites" />
				<p>
					<label for="user"><?php _e( 'Your WordPress.org username:' ); ?></label>
					<input type="search" id="user" name="user" value="<?php echo esc_attr( $this->username ); ?>" />
					<input type="submit" class="button" value="<?php esc_attr_e( 'Get Favorites' ); ?>" />
				</p>
			</form>
		<?php
	}

	/**
	 * Loads the plugin's translations
	 *
	 * @since 0.1
	 * @return void
	 */
	function textdomain() {

		// Setup plugin's language directory and filter
		$jfp_language_directory = dirname( plugin_basename( __FILE__ ) ) . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR;
		$jfp_language_directory = apply_filters( 'jfp_language_directory', $jfp_language_directory );

		// Load translations
		load_plugin_textdomain( 'jfp', false, $jfp_language_directory );

	}

	/**
	 * A simple function to handle any cleanup during an update
	 *
	 * @since 0.2
	 * @param string $current_version Provides the current version installed for comparison
	 * @return void
	 */
	function do_update( $current_version ) {

		switch ( $current_version ) {
			case '0.1':
				delete_option( 'jfp_favorite_plugins' );
			case '0.5':
				update_user_meta( get_current_user_id(), 'wporg_favorites', get_option( 'jfp_favorite_user' ) );
				delete_option( 'jfp_favorite_user' );
		}

		return $this->version;
	}

}

function jfp_execute() {
	// Kick everything into action...
	$japh_favorite_plugins = new Japh_Favorite_Plugins();
}
add_action( 'admin_init', 'jfp_execute' );
