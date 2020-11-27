<?php

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
        'Settings',
        'Settings',
        'manage_options',
        'settings',
        'elmeuplugin_settings'
    );
}