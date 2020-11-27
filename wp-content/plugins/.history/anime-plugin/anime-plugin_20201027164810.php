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
function elmeuplugin_activate()
{
    add_option('elmeuplugin_titol', "hola això és un títol", '', 'yes');
}
register_activation_hook(__FILE__, 'elmeuplugin_activate');


/**
 * Deactivation the plugin.
 */
function elmeuplugin_deactivate()
{
    delete_option('elmeuplugin_titol');
}
register_deactivation_hook(__FILE__, 'elmeuplugin_deactivate');


function elmeuplugin_afegir_text_al_footer()
{
    echo get_option("elmeuplugin_titol");
}
add_action("wp_footer", "elmeuplugin_afegir_text_al_footer");


function elmeuplugin_afegir_etiqueta_al_header()
{
    echo "<meta name='google-site-verification' content='ABCDEFGHI' />";
}
add_action("wp_head", "elmeuplugin_afegir_etiqueta_al_header");

function elmeuplugin_filtre_contingut($the_post)
{
    return "[Exclusiva] " . $the_post;
}
add_filter("the_content", "elmeuplugin_filtre_contingut");

/*
* Add my new menu to the Admin Control Panel
*/
function elmeuplugin_My_Admin_Link()
{
    add_menu_page('My First Plugin', 'My First Plugin', 'manage_options', 'main', 'elmeuplugin_main');
    add_submenu_page(
        'main',
        'Main',
        'Main',
        'manage_options',
        'main',
        'elmeuplugin_main'
    );
    add_submenu_page(
        'main',
        'Settings',
        'Settings',
        'manage_options',
        'settings',
        'elmeuplugin_settings'
    );
}
add_action('admin_menu', 'elmeuplugin_My_Admin_Link');


