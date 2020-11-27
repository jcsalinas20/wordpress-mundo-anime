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
    /*add_menu_page(
        'My First Page', // Title of the page
        'My First Plugin', // Text to show on the menu link
        'manage_options', // Capability requirement to see the link
        'includes/my_admin_page.php', // The 'slug' - file to display when clicking the link
        'elmeuplugin_admin_page',
        '',
        2,
    );

    add_submenu_page('my-top-level-slug', 'My Custom Page', 'includes/my_admin_page.php', 'manage_options', 'includes/my_admin_page.php', '');
*/
    add_menu_page('My First Plugin', 'My First Plugin', 'manage_options', 'my-top-level-slug');
    add_submenu_page(
        'my-top-level-slug',
        'My First Plugin',
        'My First Plugin',
        'manage_options',
        'my-top-level-slug'
    );
    add_submenu_page(
        'my-top-level-slug',
        'My Custom Submenu Page',
        'My Custom Submenu Page',
        'manage_options',
        'my-secondary-slug'
    );
}
add_action('admin_menu', 'elmeuplugin_My_Admin_Link');

//add_submenu_page( string $slug_top_menu, string $nombre_pagina, string $nombre_menu, string $permisos, string $menu_slug, callable $funcion = '' )



function elmeuplugin_admin_page()
{
    if (!current_user_can('manage_options')) wp_die(__('No tens prous permisos per accedir a aquesta pàgina.'));
    echo ("<div class='wrap'>
        <h1>Hello!</h1>
        <p>This is my plugin's first page</p>
        </div>");
}
