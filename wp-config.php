<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
define( 'FS_METHOD', 'direct' );

/** The name of the database for WordPress */
define( 'DB_NAME', 'wordpress' );

/** MySQL database username */
define( 'DB_USER', 'wp-user' );

/** MySQL database password */
define( 'DB_PASSWORD', 'mund0-ANIME.' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'lAU(XgxC:_(MX:awd+DEdf@=ejuf+W3<2BX#3/:?`mIbqfnNs?k0XmqNU8`c{v-}' );
define( 'SECURE_AUTH_KEY',  '[gn(Ow}s[|:+?XA?._aI]tYjO{3~u9uvdZH39C4g)qb(=Q9=p>8`PHX.eGr{4fod' );
define( 'LOGGED_IN_KEY',    'fd>]#TT3%7t9SGwc]p=CU8*AZHIAa1=2:.=}FwTAqP;Z4l_,0iD{gMOJ@p%X2giu' );
define( 'NONCE_KEY',        '0I70<w$7[S<aLl;)L=#-5.I ltk;oT%9vPVvO(.:e:&Z{{w~CYzCjP|s~6UfMH)T' );
define( 'AUTH_SALT',        'PXq|^}M`;)O4,HwBbKt_C4CL:kdIV|_6EKK0[OMpRV8=hL50Vf,[Lf|WiM&t_^zc' );
define( 'SECURE_AUTH_SALT', 'g_5k0lF9[wKmwL&Mfmv8H:cYw 42a@<W5HcfYir0V:}k:m0e.epBWH^:n`^N~mQx' );
define( 'LOGGED_IN_SALT',   '0;>(Y>X3csakrMff!)>RU{2ZJC&a (ru(,mHCX3/b@S<ZYUVahH*ivS, *zf&Z_O' );
define( 'NONCE_SALT',       '$JBj,q F~p4;* S4$64~_o`y00pR,8*[p<:^h6/Qm+E0A>LhY4%X,4(UahTF!od/' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'jcwp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
