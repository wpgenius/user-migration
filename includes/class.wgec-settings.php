<?php
/**
 *
 * @class       WPGenius_Migration_Settings
 * @author      Team WPGenius (Makarand Mane)
 * @category    Admin
 * @package     user-migration/includes
 * @version     1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPGenius_Migration_Settings extends WPGenius_Migration_API{

	public static $instance;
	private $prefix		= 'events_';
	private $opt_grp	= 'events_api_';
	private $page		= 'events_settings';
	
	public static function init(){

	    if ( is_null( self::$instance ) )
	        self::$instance = new WPGenius_Migration_Settings();
	    return self::$instance;
	}

	private function __construct(){

		add_action( 'admin_menu', array($this,'events_settings_menu'), 11);
		add_action( 'admin_init', array($this,'events_register_settings'),10);
		
		add_action( 'update_option_'.$this->prefix.'reminder_enabled', 	array( $this,'event_reminder_update_cron_job'	), 10, 3);
		add_action( 'update_option_'.$this->prefix.'reminder_interval', array( $this,'event_reminder_update_cron_time'	), 10, 3);

	} // END public function __construct

	function events_settings_menu(){

		add_submenu_page(
			'events',
			__('Events Settings','user-migration' ), // page title
			__('Settings','user-migration' ), // menu title
			'manage_options', // capability
			$this->page, // menu slug
			array( $this, 'events_api_settings')
		);
	}

	function events_register_settings() {
		
		//Register settings
	    register_setting( $this->opt_grp, $this->prefix.'event_per_page',	array( 'type' => 'string', 'default' => '5' ) );
	    register_setting( $this->opt_grp, $this->prefix.'reminder_enabled',	array( 'type' => 'boolean', 'default' => 0 ) );
	    register_setting( $this->opt_grp, $this->prefix.'reminder_interval',array( 'type' => 'number', 'default' => '30' ) );
	    register_setting( $this->opt_grp, $this->prefix.'delete_db',		array( 'type' => 'boolean', 'default' => 0 ) );
	    register_setting( $this->opt_grp, $this->prefix.'delete_settings',	array( 'type' => 'boolean', 'default' => 0 ) );
		
		//Register sections
		add_settings_section( $this->prefix.'genaral',	 		__('General preference','user-migration'),		array( $this, 'events_genaral_title' ),	$this->page );
		add_settings_section( $this->prefix.'email',	 		__('E-mail preference','user-migration'),		array( $this, 'events_email_title' ),	$this->page );		
		add_settings_section( $this->prefix.'remove_section', 	__('Uninstall preference','user-migration'),	array( $this, 'events_remove_section_title' ),$this->page );
		
		//Add settings to section- events_api_section 
		add_settings_field( $this->prefix.'event_per_page',	__('Class per page :','user-migration'), array( $this, 'events_event_per_page_field' ), 	$this->page, $this->prefix.'genaral', array( 'label_for' => $this->prefix.'event_per_page' ) );
		
		//Class reminder settings
		add_settings_field( $this->prefix.'reminder_enabled',	__('Class reminder cron job:','user-migration'), array( $this, 'events_reminder_enabled_field' ), 	$this->page, $this->prefix.'email', array( 'label_for' => 'reminder_enabled' ) );
		add_settings_field( $this->prefix.'reminder_interval',	__('Schedule reminder Email (in minutes):','user-migration'), array( $this, 'events_reminder_interval_field' ), 	$this->page, $this->prefix.'email', array( 'label_for' => $this->prefix.'reminder_interval' ) );
			
		//Add settings to section- events_remove_section 
		add_settings_field( $this->prefix.'delete_db',__('Delete Classroom database table:','user-migration'), array( $this, 'events_delete_db_field' ), $this->page, $this->prefix.'remove_section', array( 'label_for' => 'delete_db' ) );
		add_settings_field( $this->prefix.'delete_settings',__('Delete settings:','user-migration'), array( $this, 'events_delete_settings_field' ), $this->page, $this->prefix.'remove_section', array( 'label_for' => 'delete_settings' ) );
		
	}
	
	function events_api_settings(){
		?>
        <div class="wrap">
    	
            <h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
            
            <form method="POST" action="options.php">
				<?php
					// output security fields for the registered setting "wporg"
					settings_fields( $this->opt_grp );
					// output setting sections and their fields
					// (sections are registered for "wporg", each field is registered to a specific section)
					do_settings_sections( $this->page );
					// output save settings button
					submit_button( __( 'Save Settings','user-migration') );
                 ?>
            </form>
        </div>
        <?php
		
	}
	
	function events_genaral_title(){?>
		<p><?php _e( 'Choose general preference for Events for WPLMS below.','user-migration'); ?></p>
        <?php 
	}
	
	function events_email_title(){?>
		<p><?php _e( 'Choose preference for e-mail for Events below.','user-migration'); ?></p>
        <?php 
	}	
	
	function events_remove_section_title(){
		?>
		<p><?php _e( 'Choose plugin uninstallation preference below.','user-migration'); ?></p>
        <?php 
	}
	
	function events_event_per_page_field(){
		?>
       	<input type='number' min="1" max="50" name='<?php echo $this->prefix ?>event_per_page' id='<?php echo $this->prefix ?>event_per_page' value='<?php echo get_option( $this->prefix.'event_per_page' );?>' style="width: 300px;">
        <?php
	}
	
	function events_reminder_enabled_field(){
		?>
       	 <label><input type="radio" name="<?php echo $this->prefix ?>reminder_enabled" <?php checked( get_option( $this->prefix.'reminder_enabled' ), 1 ); ?> value="1" id="reminder_enabled"> <?php _e('Enable','user-migration'); ?></label>
         <label><input type="radio" name="<?php echo $this->prefix ?>reminder_enabled" <?php checked( get_option( $this->prefix.'reminder_enabled' ), 0 ); ?> value="0"> <?php _e('Disable','user-migration'); ?></label>
         <p>When you disable class reminder, you won't change time to schedule reminder email below.</p>
        <?php
	}
	
	function events_reminder_interval_field(){
		?>
       	<input type='number' min="5" max="7200" name='<?php echo $this->prefix ?>reminder_interval' id='<?php echo $this->prefix ?>reminder_interval' value='<?php echo get_option( $this->prefix.'reminder_interval' );?>' style="width: 300px;" <?php if( !get_option( $this->prefix.'reminder_enabled' ) ) echo 'readonly="readonly"';  ?> />
        <p>To send reminder to students for class, enter time in minutes. This field will be editable only when enable Class reminder. Accuracy of email depends upon site traffic and resources.</p>
        <?php
	}
	
	function events_delete_db_field(){
		?>
       	 <input type="radio" name="<?php echo $this->prefix ?>delete_db" <?php checked( get_option( $this->prefix.'delete_db' ), 1 ); ?> value="1"> <?php _e('Yes','user-migration'); ?>
         <input type="radio" name="<?php echo $this->prefix ?>delete_db" <?php checked( get_option( $this->prefix.'delete_db' ), 0 ); ?> value="0" id="delete_db"> <?php _e('No','user-migration'); ?>
        <?php	
	}
	
	function events_delete_settings_field(){
		?>
       	 <input type="radio" name="<?php echo $this->prefix ?>delete_settings" <?php checked( get_option( $this->prefix.'delete_settings' ), 1 ); ?> value="1"> <?php _e('Yes','user-migration'); ?>
         <input type="radio" name="<?php echo $this->prefix ?>delete_settings" <?php checked( get_option( $this->prefix.'delete_settings' ), 0 ); ?> value="0" id="delete_settings"> <?php _e('No','user-migration'); ?>
        <?php	
	}
	
	function event_reminder_update_cron_job( $old_value, $value, $option ){
		if( $value ) 
			$this->activate_cron();
		else 
			$this->deactivate_cron();
	}

	
	function event_reminder_update_cron_time( $old_value, $value, $option ){
		if( get_option( $this->prefix.'reminder_enabled' ) ) {
			$this->activate_cron( $value, 1 );
		}
	}

} // END class WPGenius_Migration_Settings