$jfp = jQuery.noConflict();

/**
 * Simple jQuery API wrapper for WordPress.org Favorite Plugins
 *
 * @author Japh <wordPress@japh.com.au>
 * @version 0.1
 */
function FavoritePlugins(){}

/**
 * Call to user repo list end-point of GitHub API
 *
 * @author Japh <japh@envato.com>
 * @since 1.0
 */
FavoritePlugins.Favorites = function ( username, callback ) {

	if ( $jfp( '#ajax-notification-nonce' ).length > 0 ) {
		var nonce = $jfp.trim( $jfp( '#ajax-notification-nonce' ).text() );
		var favorites_list = new Array();

		$jfp('#wpcontent .ajax-loading').css( 'visibility', 'visible' );

		$jfp('#favorite-plugins').load(
			ajaxurl,
			{
				action: 'get_favorites',
				nonce: nonce,
				username: username,
			},
			function () {
			}
		);
	}

}

$jfp(document).ready(function () {
	FavoritePlugins.Favorites( 'japh', 'whatever');
});
