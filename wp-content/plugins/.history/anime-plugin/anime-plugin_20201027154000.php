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
function ap_activate() {
    add_option('ap_titol',"hola això és un títol",'','yes');
}
register_activation_hook( __FILE__, 'ap_activate' );


/**
 * Deactivation the plugin.
 */
function ap_deactivate() {
    delete_option('ap_titol');
}
register_deactivation_hook( __FILE__, 'ap_deactivate' );


function ap_afegir_text_al_footer(){
    echo "<p>Després de que el footer carregui, aquest text apareixerà.</p>";
}
add_action("wp_footer", "ap_text_al_footer");