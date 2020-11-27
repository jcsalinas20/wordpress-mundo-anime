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
function elmeuplugin_activate() {
    add_option('elmeuplugin_titol',"hola això és un títol",'','yes');
}
register_activation_hook( __FILE__, 'elmeuplugin_activate' );


Afegeix el següent codi per registrar un hook cada cop que s'activi el teu plugin, i poder definir una opció que més tard farem servir:
/**
 * Deactivation the plugin.
 */
function elmeuplugin_deactivate() {
    delete_option('elmeuplugin_titol');
}
register_deactivation_hook( __FILE__, 'elmeuplugin_deactivate' );

function ap_text_al_footer(){
    echo get_option("ap_titol");
}
add_action("wp_footer", "ap_text_al_footer");