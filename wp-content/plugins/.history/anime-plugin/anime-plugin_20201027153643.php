<?php
/**
 * Plugin Name: Anime Plugin
 * Plugin URI: https://example.com/plugins/the-basics/
 * Description: Handle the basics with this plugin.
 * Version: 1.10.3 * 
 * Requires at least: 5.2
 * Requires PHP: 7.2
 * Author: Juan Carlos Salinas
 * Author URI: https://author.example.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: anime-plugin
 * Domain Path: /languages
*/

/**
 * Activate the plugin.
 */
function anime_activate() {
    add_option('elmeuplugin_titol',"hola això és un títol",'','yes');
}
register_activation_hook( __FILE__, 'elmeuplugin_activate' );


/**
 * Deactivation the plugin.
 */
function elmeuplugin_deactivate() {
    delete_option('elmeuplugin_titol');
}
register_deactivation_hook( __FILE__, 'elmeuplugin_deactivate' );