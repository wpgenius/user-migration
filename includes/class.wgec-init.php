<?php
/**
 *
 * @class       WPGenius_Migration_API
 * @author      Team WPGenius (Makarand Mane)
 * @category    Admin
 * @package     user-migration/includes
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGenius_Migration_API{

	private function __construct(){
		
	} // END public function __construct.
	
	/**
	*	Convert time to another timezone
	*	Require- Source time
	*	Require- Destination timezone
	*	Require- Source timezone Defaults to UTC0
	*	Require- format Defaults to Y-m-d H:i:s
	*	Returns- Converted time
	*/
	public function convert_tz( $time, $to_tz = 'UTC', $from_tz = 'UTC', $format = 'Y-m-d H:i:s' ){	
		$date = new DateTime( $time, new DateTimeZone( $from_tz ) );
		if( $format != "U") $date->setTimezone( new DateTimeZone( $to_tz ) );
		return $date->format( $format );		
	}

	protected function get_timezone_to_timestamp( $timezone, $datetime ){
		global $wpdb;
		$timezone = $wpdb->get_col( $wpdb->prepare( "SELECT timezone_country FROM $wpdb->UMC_timezones WHERE timezone_id=%d ", $timezone ) );
		return $this->convert_tz( $datetime, '', $timezone[0], 'U' );
	}
	
	/**
	*	Convert time to another timezone
	*	Require- Source time
	*	Require- Destination timezone
	*	Require- format Defaults to Y-m-d H:i:s
	*	Returns- Converted time
	*/
	public function date( $timestamp, $to_tz, $format = 'Y-m-d H:i:s' ){	
		$date = new DateTime( '@'.$timestamp );
		$date->setTimezone( new DateTimeZone( $to_tz ) );
		return $date->format( $format );		
	}
	
	public function get_localise( $action ){
		switch( $action ){
			
			case 'single-event' :
				$args = array(
							'ajax_url'			=>	admin_url('admin-ajax.php').'?action=rsvp_event',
							'nonce'				=>	wp_create_nonce( 'event_security_nonce' ),
							'location_url'		=>	admin_url('admin.php').'?page=add_event&event_id=',
							'modal_url'			=>  admin_url('admin-ajax.php').'?action=create_front_event',
							'alert'				=>	__( 'Alert!', 'user-migration' ),
							'edit_event'		=>	__( 'Edit event', 'user-migration' ),
							'json_error'		=>	__( 'Please contact administrator!', 'user-migration' ),
							'saving_msg'		=>	__( 'Saving event...', 'user-migration' ),
							'save_msg'			=>	__( 'Save', 'user-migration' ),
						);
			break;			
			
			default:
				$args = array();
			break;
			
		}
		return $args;
	}	
	
	protected function security_check( $action ){
		if ( isset( $_REQUEST['security'] ) && wp_verify_nonce( $_REQUEST['security'], $action ) ){
			return true;
		}
		wp_send_json_error( array( 'msg'=> __('Invalid security token sent.', 'user-migration' ) ) );
	}	
	
	/**
	*	 schedule reminder mail cron job for single class
	*/
	private function schedule_cron( $class, $time_in_second = '' ){
		if( get_option('events_reminder_enabled' ) ){
			if( !$time_in_second ){
				$time = get_option('events_reminder_interval' );
				$time_in_second = $time * 60;
			}
				
			$args = array( (int)$class[ 'class_id'] );
			if( !wp_next_scheduled( 'UMC_event_reminder', $args ) )
				wp_schedule_single_event( $class[ 'start_ts' ] - $time_in_second, 'UMC_event_reminder', $args );
		}
	}	
	
	/**
	*	Unschedule reminder mail cron job for single class
	*/
	private function unschedule_cron( $class_id ){
		return wp_clear_scheduled_hook('UMC_event_reminder', array( (int)$class_id ) );
	}	
	
	/**
	*	Activate cron & schedules one time event for all upcomiong class
	*/
	protected function activate_cron( $time = '', $reschedule = 0 ){
		if( $reschedule ) 
			$this->deactivate_cron(); //Remove all cron Jobs
			
		if( !$time )
			$time = get_option('events_reminder_interval' );
		$time_in_second = $time * 60;
		
		// global $wpdb;
		// $args = array(
		// 	'status'	=>  'upcoming',
		// 	'per_page'	=>	1000
		// );
		// $classes = $this->get_classes( $args );
		// foreach ($classes as $class ) 
		// 	$this->schedule_cron( $class, $time_in_second );
	}
	
	/**
	*	Deactivate cron & deletes all scheduled cron jobs
	*/
	protected function deactivate_cron(){	
		
		$hook	= 'UMC_event_reminder';
		$crons 	= _get_cron_array();
		foreach ( $crons as $timestamp => $cron ) {
			if ( isset( $cron[ $hook ] ) ) {
				foreach( $cron[ $hook ] as $key => $single_event )
					wp_unschedule_event( $timestamp, $hook, $single_event[ 'args' ] );
			}
		}
		
		/**
		*	Optional code using DB
		global $wpdb;
		$args = array(
			'status'	=>  'upcoming',
			'per_page'	=>	1000
		);
		$classes = $this->get_classes( $args );
		foreach ($classes as $class ) {
			$this->unschedule_cron( $class[ 'class_id'] );
		}
		*/
	}
	


} // END class WPGenius_Migration_API