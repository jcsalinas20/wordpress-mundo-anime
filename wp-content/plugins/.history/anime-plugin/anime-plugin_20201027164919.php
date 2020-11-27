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

register_activation_hook(__FILE__, 'elmeuplugin_activate');


register_deactivation_hook(__FILE__, 'elmeuplugin_deactivate');



add_action("wp_footer", "elmeuplugin_afegir_text_al_footer");



add_action("wp_head", "elmeuplugin_afegir_etiqueta_al_header");


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


function elmeuplugin_main()
{
    if (!current_user_can('manage_options')) wp_die(__('No tens prous permisos per accedir a aquesta pàgina.'));
    echo ("<div class='wrap'>
        <h1>Main</h1>
        <p>This is the Main</p>
        </div>");
}

function elmeuplugin_settings()
{
    if (!current_user_can('manage_options')) wp_die(__('No tens prous permisos per accedir a aquesta pàgina.'));
    echo ("<div class='wrap'>
        <h1>Settings</h1>
        <p>This is the Settings</p>
        </div>");
}
