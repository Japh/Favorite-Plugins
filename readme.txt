=== Favorite Plugins ===
Contributors: japh
Tags: favorite, plugins, install
Requires at least: 3.4
Tested up to: 3.4.2
Stable tag: 0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Quickly and easily access and install your favorited plugins from WordPress.org, right from your dashboard.

== Description ==

***** Please note, this plugin is currently inoperable until WordPress.org has a Favorites API *****

This is a simple plugin that adds "Favorites" to the plugin installation screen in WordPress. Simply enter your WordPress.org username (or even someone else's) and see a list of favorite plugins that can be easily installed.

Now, favoriting on WordPress.org is even more useful!

== Installation ==

Installation is easy, just follow these steps:

1. Upload the `favorite-plugins` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress
1. Now under the 'Plugins' -> 'New' menu, you'll have a 'Favorites' tab
1. On the 'Favorites' tab, simple enter your WordPress.org username, and click 'Get Favorites'
1. Ta-da! Now you can install your favorite plugins!

== Frequently Asked Questions ==

= Can I get another user's favorite plugins this way? =

Certainly! Simply enter the other user's username into the field instead, click 'Get Favorites', and you'll see their favorite plugins.

= What if I already have some of my favorite plugins installed? =

Not to worry, the 'Favorites' tab will show you if you already have one of your favorite plugins installed. In fact, it'll even let you know if the installed version is out of date.

== Screenshots ==

1. The new 'Favorites' tab

== Changelog ==

= 0.4 =
* Removed the HTML parsing library and all scraping code from the plugin
* This update stops the plugin from working

= 0.3 =
* The HTML parsing library is now loaded in a more conservative location, so it's only loaded when needed

= 0.2 =
* Implemented caching with transients

= 0.1 =
* This is the very first version!
