<?php
/*
Plugin Name: Payright For Gravity Forms
Author URI:  https://payright.my
Description: Integrate your Gravity Forms site with Payright Payment Gateway.
Version:     1.0.0
Author:      Payright
Text Domain: gf-payright-addon
Domain Path: /languages
*/

defined( 'ABSPATH' ) or die;

define( 'GF_PAYRIGHT_ADDON_FILE', __FILE__ );
define( 'GF_PAYRIGHT_ADDON_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'GF_PAYRIGHT_ADDON_CLASS_PATH', GF_PAYRIGHT_ADDON_PLUGIN_PATH . 'class/' );
define( 'GF_PAYRIGHT_ADDON_TEMPLATE_PATH', GF_PAYRIGHT_ADDON_PLUGIN_PATH . 'template/' );
define( 'GF_PAYRIGHT_ADDON_INC_PATH', GF_PAYRIGHT_ADDON_PLUGIN_PATH . 'inc/' );
define( 'GF_PAYRIGHT_ADDON_NONCE_BN', basename( __FILE__ ) );
define( 'GF_PAYRIGHT_ADDON_NONCE_NAME', 'gf_payright_addon_nonce' );
define( 'GF_PAYRIGHT_ADDON_OPTSGROUP_NAME', 'gf_payright_addon_optsgroup' );
define( 'GF_PAYRIGHT_ADDON_OPTIONS_NAME', 'gf_payright_addon_options' );
define( 'GF_PAYRIGHT_ADDON_SLUG', 'gravityforms-payright-addon' );
define( 'GF_PAYRIGHT_ADDON_BILL_URL', 'https://payright.my/api/v1/bills' );
define( 'GF_PAYRIGHT_ADDON_VER', '1.0.0' );

add_action( 'gform_loaded', array( 'GFPayrightAddOnBootstrap', 'load' ), 5 );

class GFPayrightAddOnBootstrap {
	public static function load() {
		if ( ! method_exists( 'GFForms', 'include_addon_framework' ) ) {
			return;
		}

		GFForms::include_payment_addon_framework();
		require_once( GF_PAYRIGHT_ADDON_CLASS_PATH . 'class-gf-payright-addon.php' );
        GFAddOn::register( 'GFPayrightAddOn' );
	}
}

function gf_payright_addon() {
    return GFPayrightAddOn::get_instance();
}

add_action( 'the_content', 'gf_payright_filter_ty_page', 1000 );

function gf_payright_filter_ty_page( $content ) {
	if ( ! is_singular( 'page' ) ) return $content;
	global $post;
	global $wpdb;

	$result = $wpdb->get_row( "SELECT id, form_id FROM {$wpdb->prefix}gf_addon_feed WHERE meta LIKE '%\"tyPageId\":\"{$post->ID}\"%' AND is_active = 1" );
	if ( ! $result ) return $content;

	ob_start();
	require GF_PAYRIGHT_ADDON_TEMPLATE_PATH . 'thank-you.php';
	return ob_get_clean();
}

add_action( 'rest_api_init', 'gf_payright_add_api_cb_endpoint' );

function gf_payright_add_api_cb_endpoint() {
	register_rest_route( 'gf-payright/v1', '/c/(?P<order_id>\d+)', array(
		'methods' => array( 'GET', 'POST' ),
		'callback' => 'gf_payright_process_api_cb',
	) );
}

function gf_payright_process_api_cb( WP_REST_Request $request ) {
	$order_id = ( int ) $request->get_param( 'order_id' );
	if ( ! $order_id ) die( "Error (1)" );

	if( ! class_exists( 'GFAPI' ) ) die( "Error(2)" );

	require_once( GF_PAYRIGHT_ADDON_CLASS_PATH . 'class-payright-helper.php' );
	$payright_helper = new Payright_Helper();
	$payright_helper->response_handler( $order_id );
}