<?php
defined( 'ABSPATH' ) or die;

class Payright_Helper {
	public function response_handler($entry_id) {
		$payright = gf_payright_addon();

		$entry = GFAPI::get_entry( $entry_id );
		$form  = GFAPI::get_form($entry['form_id']);
		$feed  = $payright->get_payment_feed($entry, $form);

		$payment_amount = $entry['payment_amount'];
		$transaction_id = $entry['transaction_id'];
		$feed_signature = $feed['meta']['signatureKey'];

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $json_payload = file_get_contents('php://input');
            $payright_payload = json_decode($json_payload, true);
            $is_callback = isset($payright_payload['id']);
            $signature = isset($payright_payload['signature']) ? $this->clean($payright_payload['signature']) : false;
        }

        if ($_SERVER['REQUEST_METHOD'] == 'GET') {
            if (isset($_REQUEST['payright'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $payright_payload = $_REQUEST['payright']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                $is_callback = false;
                $signature = isset($payright_payload['signature']) ? $this->clean($payright_payload['signature']) : false;
            }
        }

        if (isset($payright_payload) && is_array($payright_payload)) {
            $payright_payload_raw = $payright_payload; // store this as a raw data to use for checksum, if the data got sanitize, the checksum hash will be difference 
            $payright_payload = $this->sanitize_params($payright_payload);
        }

        if (isset($payright_payload['id']) && isset($payright_payload['order_no']) && $signature) {
            $order_id = $payright_payload['order_no'];
            $bill_id = $payright_payload['id'];
            $bill_status = $is_callback ? $payright_payload['state'] : $payright_payload['status'];
            $call_type = $is_callback ? 'callback' : 'redirect';
			$paid_at = $payright_payload['paid_at'];

            if ($entry && $entry_id != 0)
            {
                $order_status = strtolower($entry['payment_status']);
                switch($bill_status)
                {
                    case 'paid':
                        if (in_array($order_status, ['cancelled', 'pending', 'processing', 'on-hold'])) {
                            if ($order_status == 'cancelled' || $order_status == 'pending' || $order_status == 'on-hold') {
                                $calculate_checksum = $this->calculate_checksum($payright_payload_raw, $feed_signature, $call_type);
                                if ((string) $calculate_checksum !== (string) $signature)
                                {
                                    $payright->add_note($entry_id, sprintf(__('Mismatch signature data: Bill ID: %s, Order ID: %s', 'gf-payright-addon' ), $bill_id, $order_id), 'error');
                                    die('OK');
                                }

                                $payright->complete_payment($entry, array('payment_status' => 'paid', 'transaction_type' => 'payment', 'payment_date' => $paid_at, 'amount' => $payment_amount, 'transaction_id' => $transaction_id));
								die('OK');
                            }
                        }
                        break;
                    case 'due':
                        if (in_array($order_status, ['cancelled', 'pending', 'processing', 'on-hold'])) {
                            if ($order_status == 'cancelled' || $order_status == 'pending' || $order_status == 'on-hold') {
                                $calculate_checksum = $this->calculate_checksum($payright_payload_raw, $feed_signature, $call_type);
                                if ((string) $calculate_checksum !== (string) $signature)
                                {
                                    $payright->add_note($entry_id, sprintf(__('Mismatch signature data: Bill ID: %s, Order ID: %s', 'gf-payright-addon' ), $bill_id, $order_id), 'error');
                                    die('OK');
                                }

                                 $payright->add_note($entry_id, __('Payment attempt was failed', 'gf-payright-addon'), 'error');
                                    die('OK');
                            }
                        }
                        break;
                }
            }
        }
    }

    private function sanitize_params($data = [])
    {
        $params = [
             'id',
             'collection',
             'paid',
             'state',
             'amount',
             'paid_amount',
             'due_at',
             'biller_name',
             'biller_email',
             'biller_mobile',
             'url',
             'paid_at',
             'order_no',
             'status',
             'signature',
             'wc-api',
         ];

        foreach ($params as $k) {
            if (isset($data[$k])) {
                $data[$k] = sanitize_text_field($data[$k]);
            }
        }
        return $data;
    }

    private function calculate_checksum($data, $signatureKey, $type = 'redirect') {
        switch($type) {
            case 'redirect':
                $data_sorted['payright']['id'] = $data['id'];
                $data_sorted['payright']['order_no'] = $data['order_no'];
                $data_sorted['payright']['paid_at'] = $data['paid_at'];
                $data_sorted['payright']['status'] = $data['status'];
                return hash_hmac('sha256', json_encode($data_sorted, JSON_UNESCAPED_SLASHES), $signatureKey);
            case 'callback':
                $data_sorted['amount'] = $data['amount'];
                $data_sorted['biller_email'] = $data['biller_email'];
                $data_sorted['biller_mobile'] = $data['biller_mobile'];
                $data_sorted['biller_name'] = $data['biller_name'];
                $data_sorted['collection'] = $data['collection'];
                $data_sorted['due_at'] = $data['due_at'];
                $data_sorted['id'] = $data['id'];
                $data_sorted['order_no'] = $data['order_no'];
                $data_sorted['paid'] = $data['paid'];
                $data_sorted['paid_amount'] = $data['paid_amount'];
                $data_sorted['paid_at'] = $data['paid_at'];
                $data_sorted['state'] = $data['state'];
                $data_sorted['url'] = $data['url'];
                return hash_hmac('sha256', json_encode($data_sorted, JSON_UNESCAPED_SLASHES), $signatureKey);
        }
    }

    private function clean( $var ) {
        if ( is_array( $var ) ) {
            return array_map( 'self::clean', $var );
        } else {
            return is_scalar( $var ) ? sanitize_text_field( $var ) : $var;
        }
    }
}