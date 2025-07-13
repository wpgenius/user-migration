<?php
/**
 *
 * @class       WPGenius_Migration_Actions
 * @author      Team WPGenius (Makarand Mane)
 * @category    Admin
 * @package     user-migration/includes
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGenius_Migration_Actions extends WPGenius_Migration_API{

	public static $instance;
	public static function init(){

	    if ( is_null( self::$instance ) )
	        self::$instance = new WPGenius_Migration_Actions();
	    return self::$instance;
	}

	private function __construct(){		
		//Frontend
		add_action( 'wp_enqueue_scripts',	array($this, 'enqueue_scripts'));
		//event_reminder
		add_action( 'event_reminder',	array( $this, 'event_reminder_mail' ),	10,	2);		
		//event_reminder cron hook
		add_action( 'UMC_event_reminder',	array( $this, 'event_reminder_cron' ),	10 );
		
	} // END public function __construct

	function enqueue_scripts( ){
		
		if( is_admin() )
			return;
				
		if( is_singular( 'event' ) ){
			wp_enqueue_style('UMC_css',UMC_DIR_URL.'assets/css/style.css');			
			wp_enqueue_script( 'wgec-common', UMC_DIR_URL.'assets/js/wgec-common.js' ,array( 'jquery'));
			wp_localize_script( 'wgec-common', 'UMC_common', $this->get_localise( 'common' ) );
		}
	}
	
	public function event_reminder_mail( $event_id, $data ){
		if ( $event_id ) {
			extract( $data );
			// add tokens to parse in email
			
		}
	}
	
	public function event_reminder_cron( $event_id ){
		
		if( get_option( 'events_reminder_enabled' ) ){ 
			//$data = $this->get_class( $event_id );
			//do_action( 'event_reminder', $event_id, $data );
		}
		else
			$this->deactivate_cron();
	}
		
} // END class WPGenius_Migration_Actions