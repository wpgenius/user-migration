<?php
/*
Plugin Name: User Migration CLI
Plugin URI: https://wpgenius.in
Description: Export and import users (with meta, roles, capabilities, passwords, and IDs) using WP-CLI.
Version: 1.0
Author: Makarand Mane
Author URI: https://makarandmane.com
Text Domain: user-migration
*/
/*
Copyright 2025  Team WPGenius  (email : makarand@wpgenius.in)
*/

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'UMC_DIR_URL', plugin_dir_url( __FILE__ ) );
define( 'UMC_DIR_PATH', plugin_dir_path( __FILE__ ) );

include_once UMC_DIR_PATH.'includes/class.wgec-init.php';
include_once UMC_DIR_PATH.'includes/class.migration-cli.php';
// include_once UMC_DIR_PATH.'includes/class.wgec-database.php';
// include_once UMC_DIR_PATH.'includes/class.wgec-ajax.php';
// include_once UMC_DIR_PATH.'includes/class.wgec-admin.php';
// include_once UMC_DIR_PATH.'includes/class.wgec-settings.php';
// include_once UMC_DIR_PATH.'includes/class.wgec-actions.php';
// include_once UMC_DIR_PATH.'includes/shortcodes/shortcodes.php';

// Add text domain
add_action('plugins_loaded','WPGenius_Migration_translations');
function WPGenius_Migration_translations(){
    $locale = apply_filters("plugin_locale", get_locale(), 'user-migration');
    $lang_dir = dirname( __FILE__ ) . '/languages/';
    $mofile        = sprintf( '%1$s-%2$s.mo', 'user-migration', $locale );
    $mofile_local  = $lang_dir . $mofile;
    $mofile_global = WP_LANG_DIR . '/plugins/' . $mofile;

    if ( file_exists( $mofile_global ) ) {
        load_textdomain( 'user-migration', $mofile_global );
    } else {
        load_textdomain( 'user-migration', $mofile_local );
    }  
}

// if(class_exists('WPGenius_Migration_Actions'))
//  	WPGenius_Migration_Actions::init();

// if(class_exists('WPGenius_Shortcodes'))
//     WPGenius_Shortcodes::init();

// if(class_exists('WPGenius_Migration_Ajax'))
//  	WPGenius_Migration_Ajax::init();

// if(class_exists('WPGenius_Migration_Admin') && is_admin())
//  	WPGenius_Migration_Admin::init();

// if(class_exists('WPGenius_Migration_Settings'))
//  	WPGenius_Migration_Settings::init();

// register_activation_hook( 	__FILE__, array( $wbcdb, 'activate_events' 	) );
// register_deactivation_hook( __FILE__, array( $wbcdb, 'deactivate_events' ) );
// register_activation_hook( __FILE__, function(){ register_uninstall_hook( __FILE__, array( 'WPGenius_Migration_DB', 'uninstall_events' ) ); });
