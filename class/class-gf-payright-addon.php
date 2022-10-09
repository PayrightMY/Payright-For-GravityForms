<?php
defined( 'ABSPATH' ) or die;

GFForms::include_addon_framework();

class GFPayrightAddOn extends GFPaymentAddOn {
	protected $_version = GF_PAYRIGHT_ADDON_VER;
	protected $_min_gravityforms_version = '2.1.3.2';
	protected $_slug = GF_PAYRIGHT_ADDON_SLUG;
	protected $_path = GF_PAYRIGHT_ADDON_SLUG . '.php';
	protected $_full_path = GF_PAYRIGHT_ADDON_PLUGIN_PATH;
	protected $_title = 'Gravity Forms Payright Add-On';
	protected $_short_title = 'Payright';
	protected $_supports_callbacks = true;
	protected $_requires_credit_card = false;

	private static $_instance = null;

	public static function get_instance() {
		if ( self::$_instance == null ) {
			self::$_instance = new GFPayrightAddOn();
		}

		return self::$_instance;
	}

	public function feed_settings_fields() {

		return array(
			array(
				'description' => '',
				'fields'      => array(
					array(
						'name'     => 'feedName',
						'label'    => esc_html__( 'Feed Name', 'gf-payright-addon' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
						'tooltip'  => '<h6>' . esc_html__( 'Feed Name', 'gf-payright-addon' ) . '</h6>' . esc_html__( 'Enter a feed name to uniquely identify this setup.', 'gf-payright-addon' )
					),
					array(
						'name'     => 'apiKey',
						'label'    => esc_html__( 'Payright API key', 'gf-payright-addon' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
						'tooltip'  => '<h6>' . esc_html__( 'Payright API key', 'gf-payright-addon' ) . '</h6>' . esc_html__( 'Enter your Payright API key.', 'gf-payright-addon' )
					),
					array(
						'name'     => 'collectionId',
						'label'    => esc_html__( 'Payright collection id', 'gf-payright-addon' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
						'tooltip'  => '<h6>' . esc_html__( 'Payright collection id', 'gf-payright-addon' ) . '</h6>' . esc_html__( 'Enter your Payright collection id.', 'gf-payright-addon' )
					),
					array(
						'name'     => 'signatureKey',
						'label'    => esc_html__( 'Payright signature key', 'gf-payright-addon' ),
						'type'     => 'text',
						'class'    => 'medium',
						'required' => true,
						'tooltip'  => '<h6>' . esc_html__( 'Payright signature key', 'gf-payright-addon' ) . '</h6>' . esc_html__( 'Enter your Payright signature key.', 'gf-payright-addon' )
					),
					array(
						'name'     => 'tyPageId',
						'label'    => esc_html__( 'Thank You page', 'gf-payright-addon' ),
						'type'     => 'select',
						'choices'  => $this->get_pages_dropdown(),
						'tooltip'  => '<h6>' . esc_html__( 'Transaction Type', 'gravityforms' ) . '</h6>' . esc_html__( 'Select a transaction type.', 'gravityforms' ),
						'required' => true
					)
				)
			),
			array(
				'title'  => esc_html__( 'Other Settings', 'gravityforms' ),
				'fields' => $this->other_settings_fields()
			),
		);
	}

	public function get_pages_dropdown() {
		global $wpdb;
		$pages = $wpdb->get_results( "SELECT ID, post_title FROM $wpdb->posts WHERE post_type = 'page' AND post_status = 'publish' ORDER BY post_title ASC" );

		$array = array( array( 'label' => __( 'Select a page', 'gf-payright-addon' ), 'value' => 0 ) );

		foreach( $pages as $page ) {
			$array[] = array(
				'label' => $page->post_title,
				'value' => $page->ID
			);
		}

		return $array;
	}

	public function other_settings_fields() {
		$other_settings = array(
			array(
				'name'      => 'billingInformation',
				'label'     => esc_html__( 'Billing Information', 'gravityforms' ),
				'type'      => 'field_map',
				'field_map' => $this->billing_info_fields(),
				'tooltip'   => '<h6>' . esc_html__( 'Billing Information', 'gravityforms' ) . '</h6>' . esc_html__( 'Map your Form Fields to the available listed fields.', 'gravityforms' )
			),
		);

		$other_settings[] = array(
			'name'    => 'conditionalLogic',
			'label'   => esc_html__( 'Conditional Logic', 'gravityforms' ),
			'type'    => 'feed_condition',
			'tooltip' => '<h6>' . esc_html__( 'Conditional Logic', 'gravityforms' ) . '</h6>' . esc_html__( 'When conditions are enabled, form submissions will only be sent to the payment gateway when the conditions are met. When disabled, all form submissions will be sent to the payment gateway.', 'gravityforms' )
		);

		return $other_settings;
	}

	public function billing_info_fields() {
		$fields = array(
			array( 'name' => 'billing_name', 'label' => esc_html__( 'Billing Name', 'gravityforms' ), 'required' => false ),
			array( 'name' => 'billing_email', 'label' => esc_html__( 'Billing Email', 'gravityforms' ), 'required' => false ),
			array( 'name' => 'billing_phone', 'label' => esc_html__( 'Billing Phone', 'gravityforms' ), 'required' => false ),
		);

		return $fields;
	}

	public function redirect_url( $feed, $submission_data, $form, $entry ) {
		$order_id = $entry['id'];

        $detail = sprintf( __( 'Payment for Order #%d', 'gf-payright-addon' ), $order_id );

        $callback_url = get_rest_url( null, '/gf-payright/v1/c/' . $order_id );
        $return_url = isset( $feed['meta']['tyPageId'] ) && intval( $feed['meta']['tyPageId'] ) > 0 ? get_permalink( $feed['meta']['tyPageId'] ) : $entry['source_url'];
		$return_url = add_query_arg( 'order_id', $order_id, $return_url );

		$amount = sanitize_text_field( $submission_data['payment_amount'] );
		$name = sanitize_text_field( $submission_data['billing_name'] );
		$email = sanitize_text_field( $submission_data['billing_email'] );
		$phone = sanitize_text_field( $submission_data['billing_phone'] );

		if ( ! is_numeric( $amount ) || $amount <= 0 ) {
			$this->send_error_message( 'Invalid amount', 'gf-payright-addon' );
		}
		if ( empty( $name ) ) {
			$this->send_error_message( 'Invalid name', 'gf-payright-addon' );
		}
		if ( empty( $email ) || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
			$this->send_error_message( 'Invalid email address', 'gf-payright-addon' );
		}
		if ( empty( $phone ) ) {
			$this->send_error_message( 'Invalid phone number', 'gf-payright-addon' );
		}

		$post_args = array(
			'body' => json_encode( [
				'collection' => $feed['meta']['collectionId'],
				'description' => $detail,
				'amount' => intval( $amount * 100 ),
				'order_id' => $order_id,
				'biller_name' => $name,
				'biller_email' => $email,
				'biller_mobile' => trim( preg_replace( '/[^\d]/', '', $phone ) ),
				'callback_url' => $callback_url,
				'redirect_url' => $return_url,
				'external_reference_no' => $order_id,
			] ),
			'method' => 'POST',
			'headers' => [
				'Content-type' => 'application/json',
				'Accept' => 'application/json',
				'Authorization' => 'Bearer '. $feed['meta']['apiKey'],
			],
		);

        $request = wp_remote_post( GF_PAYRIGHT_ADDON_BILL_URL, $post_args );
        $response = wp_remote_retrieve_body( $request );

        $data_response = json_decode( $response, true );

        if ( ! isset( $data_response['data']['id'] ) ) {
			$out = array();
			foreach( $data_response['errors'] as $error ) {
				foreach( $error as $msg ) {
					$out[] = $msg;
				}
			}
			$this->send_error_message( implode( '. ', $out ) );
        }

        $bill_id = $data_response['data']['id'] ?? 0;
		if ( empty( $bill_id ) ) $this->send_error_message( 'Invalid API response (1)', 'gf-payright-addon' );

		$entry['payment_amount'] = $amount;
		$entry['payment_method'] = 'payright';
		$entry['transaction_type'] = 'pending_payment';
		$entry['transaction_id'] = $bill_id;
		GFAPI::update_entry( $entry );

		$this->add_pending_payment( $entry, array( 'payment_status' => 'pending', 'transaction_id' => $bill_id, 'amount' => $amount ) ); 

        $bill_url = $data_response['data']['url'] ?? null;
		if ( ! filter_var( $bill_url, FILTER_VALIDATE_URL ) ) $this->send_error_message( 'Invalid API response (2)', 'gf-payright-addon' );

        return $bill_url;
	}

	public function send_error_message( $message, $title = '' ) {
		wp_die( $message, $title, array( 'back_link' => true ) );
	}
}