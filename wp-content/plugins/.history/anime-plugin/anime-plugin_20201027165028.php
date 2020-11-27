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

 // Include mfp-functions.php, use require_once to stop the script if mfp-functions.php is not found
require_once plugin_dir_path(__FILE__) . 'includes/my_functions.php';

register_activation_hook(__FILE__, 'elmeuplugin_activate');
register_deactivation_hook(__FILE__, 'elmeuplugin_deactivate');

add_action("wp_footer", "elmeuplugin_afegir_text_al_footer");

add_action("wp_head", "elmeuplugin_afegir_etiqueta_al_header");

add_filter("the_content", "elmeuplugin_filtre_contingut");


add_action('admin_menu', 'elmeuplugin_My_Admin_Link');
