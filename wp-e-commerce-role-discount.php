<?php
/*
Plugin Name: WP E-Commerce role discount
Plugin URI:  http://haet.at/wp-e-commerce-role-discount/
Description: Special prices for premium customers
Version: 1.2.2
Author: haet webdevelopment
Author URI: http://haet.at
License: GPL
*/

/*  Copyright 2012 haet (email : contact@haet.at) */


define( 'HAET_ROLEDISCOUNT_PATH', plugin_dir_path(__FILE__) );
define( 'HAET_ROLEDISCOUNT_URL', plugin_dir_url(__FILE__) );

require HAET_ROLEDISCOUNT_PATH . 'includes/class-haetrolediscount.php';
load_plugin_textdomain('haetrolediscount', false, dirname( plugin_basename( __FILE__ ) ) . '/translations' );





if (class_exists("HaetRoleDiscount")) {
    $wp_haetrolediscount = new HaetRoleDiscount();
}

//Actions and Filters	
if (isset($wp_haetrolediscount)) {
    register_activation_hook( __FILE__,  array(&$wp_haetrolediscount, 'init'));
    add_action('wp_head',array(&$wp_haetrolediscount,'removeAdminBar'));
}



?>