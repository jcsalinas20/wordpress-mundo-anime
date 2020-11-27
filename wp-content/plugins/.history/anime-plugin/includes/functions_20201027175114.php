<?php

/**
 * Activation the plugin.
 */
function elmeuplugin_activate()
{
    add_option('elmeuplugin_titol', "hola això és un títol", '', 'yes');
}

/**
 * Deactivation the plugin.
 */
function elmeuplugin_deactivate()
{
    delete_option('elmeuplugin_titol');
}

function elmeuplugin_afegir_text_al_footer()
{
    echo get_option("elmeuplugin_titol");
}

function elmeuplugin_afegir_etiqueta_al_header()
{
    echo "<meta name='google-site-verification' content='ABCDEFGHI' />";
}

function elmeuplugin_filtre_contingut($the_post)
{
    return "[Exclusiva] " . $the_post;
}

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
        'Formulario',
        'Formulario',
        'manage_options',
        'form',
        'elmeuplugin_form'
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
    echo ("<div class='wrap'>
        <h1>Settings</h1>
        <p>This is the Settings</p>
        </div>");
}

function elmeuplugin_form()
{
    require("form.php");
}

function register_my_plugin_scripts()
{
    wp_register_style('anime-plugin-styles', plugins_url('anime-plugin/css/style.css'));
    wp_enqueue_style( 'anime-plugin-styles' );
    wp_register_script('anime-plugin', plugins_url('anime-plugin/js/plugin.js'));
    wp_enqueue_style( 'anime-plugin-styles' );
}
