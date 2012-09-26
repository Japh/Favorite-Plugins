<?php
/*
Plugin Name: Favorite Plugins
Plugin URI: http://japh.wordpress.com/plugins/favorite-plugins
Description: Quickly and easily access and install your favorited plugins from WordPress.org, right from your dashboard.
Version: 0.2
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
 * @version 0.2
 * @link http://japh.wordpress.com/plugins/favorite-plugins
 * @since 0.1
 */

// Plugin folder URL
if ( ! defined( 'JFP_PLUGIN_URL' ) ) {
	define( 'JFP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

// Plugin folder path
if ( ! defined(' JFP_PLUGIN_DIR' ) ) {
	define( 'JFP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Plugin base file
if ( ! defined( 'JFP_PLUGIN_FILE' ) ) {
	define( 'JFP_PLUGIN_FILE', __FILE__ );
}

/**
 * Main class for the Favourite Plugins plugin
 *
 * @package JaphFavoritePlugins
 * @copyright 2012 Japh
 * @license http://www.gnu.org/licenses/old-licenses/gpl-2.0.txt GPL2
 * @version 0.2
 * @since 0.1
 */
class Japh_Favorite_Plugins {

	public $version = '0.2';
	public $username = null;
	public $favorite_plugins = null;

	private $plugin_expiry = 60; // Expiry of transient storing plugin results in minutes
	private $html_expiry = 15; // Expiry of the transient storing HTML output in minutes

	/**
	 * Constructor for the plugin's main class
	 *
	 * @since 0.1
	 */
	function __construct() {

		$current_version = get_option( 'jfp_favourite_plugins_version' );

		if ( $current_version != $this->version ) {
			update_option( 'jfp_favourite_plugins_version', $this->do_update( $current_version ) );
		}

		add_action( 'init', array( &$this, 'textdomain' ) );
		add_action( 'admin_init', array( &$this, 'load_libraries' ) );
		add_filter( 'install_plugins_tabs', array( &$this, 'add_favorites_tab' ) );
		add_action( 'install_plugins_favorites', array( &$this, 'do_favorites_tab' ) );

		$this->username = get_option( 'jfp_favorite_user' );
		$this->favorite_plugins = get_transient( 'jfp_favourite_plugins' );

	}

	/**
	 * Loads any 3rd-party libraries the plugin utilises
	 *
	 * This function will load in any libraries used by the plugin, currently:
	 *  + PHP Simple HTML DOM Parser
	 *
	 * @since 0.1
	 */
	function load_libraries() {

		/** Require the PHP Simple HTML DOM Parser library */
		require( JFP_PLUGIN_DIR . 'lib' . DIRECTORY_SEPARATOR . 'simple_html_dom.php' );

	}

	/**
	 * Housekeeping things for plugin activation
	 *
	 * @since 0.1
	 */
	function activate() {

		add_option( 'jfp_favorite_user' );

	}

	/**
	 * Housekeeping things for plugin deactivation
	 *
	 * @since 0.1
	 */
	function deactivate() {

		delete_option( 'jfp_favorite_user' );

	}

	/**
	 * Add a Favorites tab to the install plugins tabs
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
	 * @since 0.1
	 * @param array $paged The current page for the tab
	 * @return void
	 */
	function do_favorites_tab( $paged ) {

		if ( ! empty( $_GET['username'] ) && $_GET['username'] != $this->username ) {

			if ( wp_verify_nonce( $_GET['nonce'], 'favorite-plugins-nonce' ) ) {

				update_option( 'jfp_favorite_user', $_GET['username'] );
				$this->username = $_GET['username'];
				$this->favorite_plugins = $this->get_favorites();
				set_transient( 'jfp_favorite_plugins', $this->favorite_plugins, 60 * $this->plugin_expiry );

			}

		} elseif ( empty( $this->favorite_plugins ) && ! empty( $this->username ) ) {

			$this->favorite_plugins = $this->get_favorites();
			set_transient( 'jfp_favorite_plugins', $this->favorite_plugins, 60 * $this->plugin_expiry );

		}

		/* Let's store the HTML in a transient for an hour */

		$html = get_transient( 'jfp_favourite_plugins_html_' . $this->username );

		if ( false === $html ) {

			$html = '';

			$plugins = unserialize( $this->favorite_plugins );
			$plugins_count = count( $plugins );

			$html = $this->favorites_table_header( $plugins_count );

			$html .= $this->display_favorites_table( $plugins );

			$html .= $this->favorites_table_footer( $plugins_count );

			set_transient( 'jfp_favourite_plugins_html_' . $this->username, $html, 60 * $this->html_expiry );

		}

		echo $html;

	}

	/**
	 * Get favorites
	 *
	 * We'll try and get the favourites from the database, if they're not there,
	 * we'll hit WordPress.org and grab them.
	 *
	 * @since 0.1
	 * @return string Serialized string of favorite plugins. FALSE if no plugins.
	 */
	function get_favorites() {

		$favorite_plugins = $this->fetch_favorites();

		if ( ! empty( $favorite_plugins ) ) {
			return serialize( $favorite_plugins );
		} else {
			return false;
		}

	}

	/**
	 * Fetch favorites from WordPress.org
	 *
	 * Grab favorites from the user's profile page on WordPress.org. Parse the
	 * HTML of the page to find the favorites and put them into an array.
	 *
	 * @since 0.1
	 * @return string Serialized string of favorite plugins
	 */
	function fetch_favorites() {

		$favorites_url = 'http://profiles.wordpress.org/' . $this->username;
		$favorites_html = wp_remote_get( esc_url( $favorites_url ) );

		if ( is_wp_error( $favorites_html ) ) {
			return '-1';
		} else {
			if ( preg_match( '/(?:<body[^>]*>)(.*)<\/body>/isU', $favorites_html['body'], $matches ) ) {
				$body = $matches[1];
			}

			$favorite_plugins = array();

			$doc = str_get_html( $body );

			foreach ( $doc->find( 'div.main-plugins' ) as $section ) {

				$header = $section->find( 'h4' );

				foreach ( $header as $head ) {

					if ( strpos( strtolower( $head->innertext ), strtolower( "favorite plugins" ) ) !== false ) {

						$favorites_list = $head->next_sibling();

						foreach ( $favorites_list->children() as $favorite ) {
							if ( ! empty( $favorite->plaintext ) ) {

								$a = $favorite->find( 'a' );

								foreach ( $a as $link ) {

									$new_favorite = array();
									$new_favorite['name'] = $link->innertext;
									$new_favorite['url'] = $link->href;
									$slug = explode( '/', $link->href );
									$new_favorite['slug'] = $slug[count( $slug ) - 2];

									$favorite_plugins[$new_favorite['slug']] = $new_favorite;

								}
							}
						}
					}
				}
			}

			return $favorite_plugins;
		}
	}

	/**
	 * Return the table header for the favorite plugins table display
	 *
	 * @since 0.1
	 * @param int $plugins_count Number of plugins to be displayed
	 * @return string HTML table header for output
	 */
	function favorites_table_header( $plugins_count ) {

		$html = '';

		// Header
		$html .= '<div class="tablenav top">' . "\n";

		$html .= '	<div class="alignleft actions">' . "\n";
		$html .= '		<form id="favorite-plugins" method="get" action="">' . "\n";
		$html .= '			<input type="hidden" name="tab" value="favorites">' . "\n";
		$html .= '			<input type="search" name="username" value="' . ( ! empty( $this->username ) ? $this->username : '' ) . '">' . "\n";
		$html .= '			<label class="screen-reader-text" for="plugin-favorite-input">' . __( 'Favorite Plugins', 'jfp' ) . '</label>' . "\n";
		$html .= '			<input type="submit" name="plugin-favorite-input" id="plugin-favorite-input" class="button" value="' . __( 'Get Favorites' , 'jfp' ) . '">' . "\n";
		$html .= '			<input type="hidden" name="nonce" value="' . wp_create_nonce( 'favorite-plugins-nonce' ) . '">';
		$html .= '		</form>' . "\n";
		$html .= '	</div>' . "\n";

		$html .= '	<div class="tablenav-pages one-page">' . "\n";
		$html .= '		<span class="displaying-num">' . sprintf( _n( '%d item', '%d items', $plugins_count ), $plugins_count ) . '</span>' . "\n";
		$html .= '	</div>' . "\n";
		$html .= '	<br class="clear">' . "\n";
		$html .= '</div>' . "\n";

		$html .= '<table class="wp-list-table widefat plugin-install" cellspacing="0">' . "\n";

		// Table head
		$html .= '	<thead>' . "\n";
		$html .= '		<tr>' . "\n";
		$html .= '			<th scope="col" id="name" class="manage-column column-name" style="">' . __( 'Name' ) . '</th>' . "\n";
		$html .= '			<th scope="col" id="version" class="manage-column column-version" style="">' . __( 'Version' ) . '</th>' . "\n";
		$html .= '			<th scope="col" id="rating" class="manage-column column-rating" style="">' . __( 'Rating' ) . '</th>' . "\n";
		$html .= '			<th scope="col" id="description" class="manage-column column-description" style="">' . __( 'Description' ) . '</th>' . "\n";
		$html .= '		</tr>' . "\n";
		$html .= '	</thead>' . "\n";

		// Table foot
		$html .= '	<tfoot>' . "\n";
		$html .= '		<tr>' . "\n";
		$html .= '			<th scope="col" id="name" class="manage-column column-name" style="">' . __( 'Name' ) . '</th>' . "\n";
		$html .= '			<th scope="col" id="version" class="manage-column column-version" style="">' . __( 'Version' ) . '</th>' . "\n";
		$html .= '			<th scope="col" id="rating" class="manage-column column-rating" style="">' . __( 'Rating' ) . '</th>' . "\n";
		$html .= '			<th scope="col" id="description" class="manage-column column-description" style="">' . __( 'Description' ) . '</th>' . "\n";
		$html .= '		</tr>' . "\n";
		$html .= '	</tfoot>' . "\n";

		return $html;

	}

	/**
	 * Return the table footer for the favorite plugins table display
	 *
	 * @since 0.1
	 * @param int $plugins_count Number of plugins to be displayed
	 * @return string HTML table footer for output
	 */
	function favorites_table_footer( $plugins_count ) {

		$html = '';

		// Table footer
		$html .= '<div class="tablenav bottom">' . "\n";
		$html .= '	<div class="tablenav-pages one-page">' . "\n";
		$html .= '		<span class="displaying-num">' . sprintf( _n( '%d item', '%d items', $plugins_count ), $plugins_count ) . '</span>' . "\n";
		$html .= '	</div>' . "\n";
		$html .= '	<br class="clear">' . "\n";
		$html .= '</div>' . "\n";

		return $html;

	}

	/**
	 * Return a table with the favorite plugins for display
	 *
	 * @since 0.1
	 * @param array $favorite_plugins User's favorite plugins serialized
	 * @return string HTML table output of plugin list
	 */
	function display_favorites_table( $plugins ) {

		$html = '';

		$html .= '	<tbody id="the-list">' . "\n";

		if ( ! empty( $plugins ) ) {

			// Table rows
			foreach ( $plugins as $plugin ) {

				$plugin = $this->get_plugin_info( $plugin );

				$status = $this->plugin_install_status( $plugin );

				$plugins_allowedtags = array(
					'a' => array( 'href' => array(),'title' => array(), 'target' => array() ),
					'abbr' => array( 'title' => array() ),'acronym' => array( 'title' => array() ),
					'code' => array(), 'pre' => array(), 'em' => array(),'strong' => array(),
					'ul' => array(), 'ol' => array(), 'li' => array(), 'p' => array(), 'br' => array()
				);

				$title = wp_kses( $plugin->name, $plugins_allowedtags );
				//Limit description to 400char, and remove any HTML.
				$description = strip_tags( $plugin->sections['description'] );
				if ( strlen( $description ) > 400 )
					$description = mb_substr( $description, 0, 400 ) . '&#8230;';
				//remove any trailing entities
				$description = preg_replace( '/&[^;\s]{0,6}$/', '', $description );
				//strip leading/trailing & multiple consecutive lines
				$description = trim( $description );
				$description = preg_replace( "|(\r?\n)+|", "\n", $description );
				//\n => <br>
				$description = nl2br( $description );
				$version = wp_kses( $plugin->version, $plugins_allowedtags );

				$name = strip_tags( $title . ' ' . $version );

				$author = $plugin->author;
				if ( ! empty( $plugin->author ) )
					$author = ' <cite>' . sprintf( __( 'By %s' ), $author ) . '.</cite>';

				$author = wp_kses( $author, $plugins_allowedtags );

				$action_links = array();
				$action_links[] = '<a href="' . self_admin_url( 'plugin-install.php?tab=plugin-information&amp;plugin=' . $plugin->slug .
									'&amp;TB_iframe=true&amp;width=600&amp;height=550' ) . '" class="thickbox" title="' .
									esc_attr( sprintf( __( 'More information about %s' ), $name ) ) . '">' . __( 'Details' ) . '</a>';

				if ( current_user_can( 'install_plugins' ) || current_user_can( 'update_plugins' ) ) {
					$status = install_plugin_install_status( $plugin );

					switch ( $status['status'] ) {
						case 'install':
							if ( $status['url'] )
								$action_links[] = '<a class="install-now" href="' . $status['url'] . '" title="' . esc_attr( sprintf( __( 'Install %s' ), $name ) ) . '">' . __( 'Install Now' ) . '</a>';
							break;
						case 'update_available':
							if ( $status['url'] )
								$action_links[] = '<a href="' . $status['url'] . '" title="' . esc_attr( sprintf( __( 'Update to version %s' ), $status['version'] ) ) . '">' . sprintf( __( 'Update Now' ), $status['version'] ) . '</a>';
							break;
						case 'latest_installed':
						case 'newer_installed':
							$action_links[] = '<span title="' . esc_attr__( 'This plugin is already installed and is up to date' ) . ' ">' . _x( 'Installed', 'plugin' ) . '</span>';
							break;
					}
				}

				$action_links = apply_filters( 'plugin_install_action_links', $action_links, $plugin );

				$html .= '		<tr>' . "\n";

				$html .= '			<td class="name column-name"><strong>' . $title . '</strong>';
				$html .= '				<div class="action-links">' . ( !empty( $action_links ) ? implode( ' | ', $action_links ) : '' ) . '</div>';
				$html .= '			</td>';
				$html .= '			<td class="vers column-version">' . $version . '</td>';
				$html .= '			<td class="vers column-rating">';
				$html .= '				<div class="star-holder" title="' . sprintf( _n( '(based on %s rating)', '(based on %s ratings)', $plugin->num_ratings ), number_format_i18n( $plugin->num_ratings ) ) . '">';
				$html .= '				<div class="star star-rating" style="width: ' . esc_attr( str_replace( ',', '.', $plugin->rating ) ) . 'px"></div>';
				$html .= '				</div>';
				$html .= '			</td>';
				$html .= '			<td class="desc column-description">' . $description . $author . '</td>';

				$html .= '		</tr>' . "\n";
			}
		} else {
				$html .= '		<tr class="no-items">' . "\n";
				$html .= '			<td class="colspanchange" colspan="4">' . __( 'No favorite plugins found.', 'jfp' ) . '</td>' . "\n";
				$html .= '		</tr>' . "\n";
		}

		$html .= '	</tbody>' . "\n";
		$html .= '</table>' . "\n";

		return $html;

	}

	/**
	 * Determine the status we can perform on a plugin.
	 *
	 * @since 0.1
	 * @param array $favorite An array containing details for a plugin
	 * @return array An array with elements representing the plugin's status
	 */
	function plugin_install_status( $favorite ) {
		// this function is called recursively, $loop prevents further loops.
		if ( is_array( $favorite ) )
			$favorite = (object) $favorite;

		//Default to a "new" plugin
		$status = 'install';
		$url = false;

		//Check to see if this plugin is known to be installed, and has an update awaiting it.
		$update_plugins = get_site_transient('update_plugins');
		if ( isset( $update_plugins->response ) ) {
			foreach ( (array)$update_plugins->response as $file => $plugin ) {
				if ( $plugin->slug === $favorite->slug ) {
					$status = 'update_available';
					$update_file = $file;
					$version = $plugin->new_version;
					if ( current_user_can('update_plugins') )
						$url = wp_nonce_url(self_admin_url('update.php?action=upgrade-plugin&plugin=' . $update_file), 'upgrade-plugin_' . $update_file);
					break;
				}
			}
		}

		if ( 'install' == $status ) {
			if ( is_dir( WP_PLUGIN_DIR . DIRECTORY_SEPARATOR . $favorite->slug ) ) {
				$installed_plugin = get_plugins('/' . $favorite->slug);

				if ( empty( $installed_plugin ) ) {
					if ( current_user_can( 'install_plugins' ) )
						$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . $favorite->slug ), 'install-plugin_' . $favorite->slug );
				} else {
					$key = array_shift( $key = array_keys( $installed_plugin ) ); // Use the first plugin regardless of the name, Could have issues for multiple-plugins in one directory if they share different version numbers
					if ( version_compare( $favorite->version, $installed_plugin[ $key ]['Version'], '=' ) ){
						$status = 'latest_installed';
					} elseif ( version_compare( $favorite->version, $installed_plugin[ $key ]['Version'], '<' ) ) {
						$status = 'newer_installed';
						$version = $installed_plugin[ $key ]['Version'];
					} else {
						//If the above update check failed, Then that probably means that the update checker has out-of-date information, force a refresh
						if ( ! $loop ) {
							delete_site_transient( 'update_plugins' );
							wp_update_plugins();
							return install_plugin_install_status( $favorite, true );
						}
					}
				}
			} else {
				// "install" & no directory with that slug
				if ( current_user_can( 'install_plugins' ) )
					$url = wp_nonce_url( self_admin_url( 'update.php?action=install-plugin&plugin=' . $favorite->slug ), 'install-plugin_' . $favorite->slug );
			}
		}
		if ( isset( $_GET['from'] ) )
			$url .= '&amp;from=' . urlencode( stripslashes( $_GET['from'] ) );

		return compact( 'status', 'url', 'version' );
	}

	/**
	 * Gather info for a specific plugin from WordPress.org's API
	 *
	 * @since 0.1
	 * @param array $plugin An array representing a specific plugin
	 * @return object An object representing a plugin and its info
	 */
	function get_plugin_info( $plugin ) {

		$plugin = (object) $plugin;

		$res = null;

		$request = wp_remote_post( 'http://api.wordpress.org/plugins/info/1.0/', array( 'timeout' => 15, 'body' => array( 'action' => 'plugin_information', 'request' => serialize( $plugin ) ) ) );
		if ( is_wp_error( $request ) ) {
			$res = new WP_Error('plugins_api_failed', __( 'An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="http://wordpress.org/support/">support forums</a>.' ), $request->get_error_message() );
		} else {
			$res = maybe_unserialize( wp_remote_retrieve_body( $request ) );
			if ( ! is_object( $res ) && ! is_array( $res ) )
				$res = new WP_Error('plugins_api_failed', __( 'An unexpected error occurred. Something may be wrong with WordPress.org or this server&#8217;s configuration. If you continue to have problems, please try the <a href="http://wordpress.org/support/">support forums</a>.' ), wp_remote_retrieve_body( $request ) );
		}

		return $res;

	}

	/**
	 * Loads the plugin's translations
	 *
	 * @since 0.1
	 * @return void
	 */
	function textdomain() {

		// Setup plugin's language directory and filter
		$jfp_language_directory = dirname( plugin_basename( JFP_PLUGIN_FILE ) ) . DIRECTORY_SEPARATOR . 'languages' . DIRECTORY_SEPARATOR;
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
		}

		return $this->version;
	}

}

// Kick everything into action...
$japh_favorite_plugins = new Japh_Favorite_Plugins();
