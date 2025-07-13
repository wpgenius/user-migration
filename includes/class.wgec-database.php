<?php
/**
 *
 * @class       WPGenius_Migration_DB
 * @author      Team WPGenius (Makarand Mane)
 * @category    Admin
 * @package     user-migration/includes
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGenius_Migration_DB extends WPGenius_Migration_API{

	public function __construct(){
		/*
		* https://code.tutsplus.com/tutorials/custom-database-tables-creating-the-table--wp-28124
		* #blog #gist
		*/
		add_action( 'init', array( $this, 'WPGenius_Migration_endpoint'), 1 );
		add_action( 'init', array( $this, 'WPGenius_Migration_table'), 1 );
		add_action( 'switch_blog', array( $this, 'WPGenius_Migration_table') );

	} // END public function __construct
	
	public function WPGenius_Migration_endpoint(){
		add_rewrite_endpoint( 'completeClass', EP_ROOT );
		
		add_rewrite_rule( '^joinClass/([0-9]+)/?',	'index.php?joinClass=$matches[1]', 'top');
		add_rewrite_tag( '%joinClass%',		'([^&]+)' );
		
		if( !get_option('UMC_permalinks_flushed') ) { 
			flush_rewrite_rules(false);
			update_option('UMC_permalinks_flushed', 1);	 
		}
	}
	
	public function WPGenius_Migration_table() {
		global $wpdb;
		$wpdb->UMC_Events = "{$wpdb->prefix}wpgenius_events";
	}

	public function activate_events() {
		$this->install_events_database();		
		//as Cron jobs are not scheduled while plugin activation, I have added a cron job. It invokes a hook function for update option.
		wp_schedule_single_event( time() + 30 , 'update_option_events_reminder_enabled', array(  0, get_option('events_reminder_enabled' ), 'events_reminder_enabled' ) );
		update_option('UMC_permalinks_flushed', 0);
		flush_rewrite_rules();
	}
	
	public function deactivate_events() {
		$this->deactivate_cron();
		delete_option('UMC_permalinks_flushed');
		flush_rewrite_rules();
	}
	
	public function uninstall_events() {		
		$this->delete_events_database();
		$this->delete_events_settings();
	}

	private function install_events_database(){
		global $wpdb;
		
		$table_name = $wpdb->prefix . "wpgenius_events"; 
		$charset_collate = $wpdb->get_charset_collate();
		$sql = array();

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		
		foreach($sql as $query )
			dbDelta( $query );
	}
	
	private function delete_events_database(){		
		if( get_option('events_delete_db') ){		
			global $wpdb;
			$wpdb->query(  "DROP TABLE IF EXISTS ".$wpdb->prefix . "wpgenius_events ;" );
		}		
	}
	
	private function delete_events_settings(){	
		if( get_option('events_delete_settings') ){		
			unregister_setting( 'events_api', 'event_per_page' );
			unregister_setting( 'events_api', 'events_reminder_enabled' );
			unregister_setting( 'events_api', 'events_reminder_interval' );
			unregister_setting( 'events_api', 'events_delete_db' );		
			unregister_setting( 'events_api', 'events_delete_settings' );
			delete_option( 'event_per_page' );
			delete_option( 'events_reminder_enabled' );
			delete_option( 'events_reminder_interval' );
			delete_option( 'events_delete_db' );		
			delete_option( 'events_delete_settings' );
		}
	}

}
$wbcdb = new WPGenius_Migration_DB();