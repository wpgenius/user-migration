<?php
/**
 *
 * @class       WPGenius_Shortcodes
 * @author      Team WPGenius (Makarand Mane)
 * @category    Admin
 * @package     user-migration/includes/widgets
 * @version     1.0
 */

 class WPGenius_Shortcodes extends WPGenius_Migration_API{

	public static $instance;
	public static function init(){

	    if ( is_null( self::$instance ) )
	        self::$instance = new WPGenius_Shortcodes();
	    return self::$instance;
	}

	public function __construct(){
		//add_shortcode( 'shortcode', array( $this, 'shortcode_callback') );
	} // END public function __construct
	
	public function shortcode_callback(){
		
	}
}