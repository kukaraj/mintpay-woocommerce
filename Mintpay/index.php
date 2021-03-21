<?php
/*
Plugin Name: Mintpay
Plugin URI: https://www.mintpay.lk
Description: WooCommerce payment plugin of Mintpay payment gateway. Sri Lanka's first buy now pay later platform, that allows consumers to split their payment into 3 interst-free installments.
Version: 1.0.0
Author: Algoredge (Private) Limited
Author URI: https://www.mintpay.lk
*/

add_action('plugins_loaded', 'woocommerce_gateway_mintpay_init', 0);
define('mintpay_IMG', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');

function woocommerce_gateway_mintpay_init() {
	if ( !class_exists( 'WC_Payment_Gateway' ) ) return;

	if (!session_id()) {
		 session_start();
	}	

	/**
 	 * Gateway class
 	 */
	class WC_Gateway_mintpay extends WC_Payment_Gateway {

	     /**
         * Make __construct()
         **/	
		public function __construct(){
			
			$this->id 					= 'mintpay'; // ID for WC to associate the gateway values
            //$this->icon 				= WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__ )) . '/assets/img/logo.png';
			$this->method_title 		= "Mintpay"; // Gateway Title as seen in Admin Dashboad
			$this->method_description	= "Sri Lanka's first buy now pay later platform, that allows consumers to split their payment into 3 interst-free installments.";
			$this->has_fields 			= false; // Inform WC if any fileds have to be displayed to the visitor in Frontend 
			
			$this->init_form_fields();	// defines your settings to WC
			$this->init_settings();		// loads the Gateway settings into variables for WC

			$this->title 		= 'Pay in 3 interest-free installments with Mintpay';
			$this->description 	=  "<p>Split your bill into 3, interest-free installments.<br/>
            Don’t have a Mintpay account yet? <a href='https://app.mintpay.lk/signup/' target='_blank'><u>Click here</u></a> to sign up now.<br/>T&C applies</p><img src='https://mintpay-logo.s3.ap-south-1.amazonaws.com/payment-gateway/logo_w120_h32.png' srcset='https://mintpay-logo.s3.ap-south-1.amazonaws.com/payment-gateway/logo_w120_h32.png 1x,https://mintpay-logo.s3.ap-south-1.amazonaws.com/payment-gateway/logo_w240_h64.png 2x,https://mintpay-logo.s3.ap-south-1.amazonaws.com/payment-gateway/logo_w360_h96.png 3x' height='35' alt='Mintpay' />";
            $this->merchant_id 		= $this->get_option( 'merchant_id' );
            $this->merchant_secret 	= $this->get_option( 'merchant_secret');
            $this->test_mode        = 'yes' === $this->get_option( 'test_mode' );
	
            $this->msg['message']	= '';
            $this->msg['class'] 	= '';
			
			add_action('init', array(&$this, 'check_mintpay_response'));
            add_action('woocommerce_api_' . strtolower(get_class($this)), array($this, 'check_mintpay_response')); //update for woocommerce >2.0

            if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
                    add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) ); //update for woocommerce >2.0
                 } else {
                    add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) ); // WC-1.6.6
                }
            add_action('woocommerce_receipt_mintpay', array(&$this, 'receipt_page'));

		} //END-__construct
		
        /**
         * Initiate Form Fields in the Admin Backend
         **/
		function init_form_fields(){

			$this->form_fields = array(
				// Activate the Gateway
				'enabled' => array(
					'title' 		=> __('Enable', 'woo_mintpay'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Enable mintpay gateway', 'woo_mintpay'),
					'default' 		=> 'yes',
					'description' 	=> 'Show mintpay as a payment option at checkout',
					'desc_tip' 		=> true
				),

				// Activate Test mode
				'test_mode' => array(
					'title' 		=> __('Test Mode', 'woo_mintpay'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Enable test mode', 'woo_mintpay'),
					'default' 		=> 'yes',
					'description' 	=> 'Test mintpay payment gateway in sandbox environment',
					'desc_tip' 		=> true
				),

				// LIVE Key-ID
      			'merchant_id' => array(
					'title' 		=> __('Merchant ID', 'woo_mintpay'),
					'type' 			=> 'text',
					'description' 	=> __('Your mintpay Merchant ID'),
					'desc_tip' 		=> true
				),
  				// LIVE Key-Secret
    			'merchant_secret' => array(
					'title' 			=> __('Merchant Secret', 'woo_mintpay'),
					'type' 			=> 'text',
					'description' 	=> __('Your mintpay Merchant Secret'),
					'desc_tip' 		=> true
                ),
			);

		} //END-init_form_fields
		
        /**
         * Admin Panel Options
         * - Show info on Admin Backend
         **/
		public function admin_options(){
			echo '<h3>'.__('Mintpay', 'woo_mintpay').'</h3>';
			echo '<p>'.__("WooCommerce payment plugin of Mintpay payment gateway. Sri Lanka's first buy now pay later platform, that allows consumers to split their payment into 3 interst-free installments").'</p>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
			echo '</table>';
		} //END-admin_options

        /**
         *  There are no payment fields, but we want to show the description if set.
         **/
		function payment_fields(){
			if( $this->description ) {
				echo wpautop( wptexturize( $this->description ) );
			}
		} //END-payment_fields
		
        /**
         * Receipt Page
         **/
		function receipt_page($order){
			echo '<p><strong>' . __('Thank you for your order.', 'woo_mintpay').'</strong><br/>' . __('The payment page will open soon.', 'woo_mintpay').'</p>';
			echo $this->generate_mintpay_form($order);
		} //END-receipt_page



    
        /**
         * Generate button link
         **/
		function generate_mintpay_form($order_id){
			global $woocommerce;

			$order = wc_get_order( $order_id );

			$merchant_id = $this->merchant_id;
			$merchant_secret = $this->merchant_secret;
			$amount = $order->get_total();

			$success_hash = hash_hmac('sha256', $merchant_id . sprintf("%.02f", round($amount, 2)) . $order_id, $merchant_secret);
			$fail_hash = hash_hmac('sha256', $order_id, $merchant_secret);


		  	$_SESSION['order_id'] = $order_id;
			
			$redirect_url = $order->get_checkout_order_received_url();
			
			$notify_url = "";
			// Redirect URL : For WooCoomerce 2.0
			if ( version_compare(WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				$notify_url = add_query_arg( 'wc-api', get_class( $this ), $redirect_url );
			}

			$success_url = $notify_url . '&hash=' .  base64_encode($success_hash);
			$fail_url = $notify_url . '&hash=' .  base64_encode($fail_hash);


			if ($this->test_mode){
				$api_url = "https://dev.mintpay.lk/user-order/api/";
				$form_url = "https://dev.mintpay.lk/user-order/login/";
			} else {
				$api_url = "https://app.mintpay.lk/user-order/api/";
				$form_url = "https://app.mintpay.lk/user-order/login/";
			}

            foreach($order->get_items() as $item_id => $item) {

            	$order_items[] = array(
            		'name'         => $item->get_name(),
                    'product_id'   => $item->get_product_id(),
                    'sku'          => $item->get_type(),
                    'quantity'     => $item->get_quantity(),
                    'unit_price'   => $item->get_total(),
                    'created_date' => "2001-10-01 01:10:01",
                    'updated_date' => "2001-10-01 01:10:01",
                    'discount'     => "0.00"
                );
            }

			$postData = [
				'merchant_id'           => $merchant_id,
				'order_id'              => $order_id,
				'total_price'           => $amount,
				'discount'              => $order ->get_total_discount(),
                'customer_id'           => $order->get_customer_id(),
                'customer_email'        => $order->get_billing_email(),
                'customer_telephone'    => $order->get_billing_phone(),
                'ip'                    => $order->get_customer_ip_address(),
                'x_forwarded_for'       => $order->get_customer_ip_address(),
                'delivery_street'       => $order->get_billing_address_1() . $order->get_billing_address_2(),
                'delivery_region'       => $order->get_billing_city(),
                'delivery_postcode'     => $order->get_billing_postcode(),
                'cart_created_date'     => date_format($order->get_date_created(),"Y-m-d H:i:s"),
                'cart_updated_date'     => date_format($order->get_date_modified(),"Y-m-d H:i:s"),
                'success_url'           => $success_url,
                'fail_url'              => $fail_url,
                'products'              => $order_items,
			];
            
			
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $api_url);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));  //Post Fields
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

			$headers = [
				'Authorization: Token '. $merchant_secret,
				'Content-Type: application/json',
			];

			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			$server_output = curl_exec ($ch);
			$mintpayRequestData = json_decode($server_output, true);

			curl_close ($ch);

			if(isset($mintpayRequestData['message']) && $mintpayRequestData['message']=='Success'){
			
			return '<form action="'. $form_url .'"method="post" id="mintpay_payment_form">
			<input type="hidden" name="purchase_id" value="'.$mintpayRequestData['data'].'" >
			<input type="submit" class="button-alt" id="submit_mintpay_payment_form" value="'.__('Pay via mintpay', 'woo_mintpay').'" /> <a class="button cancel" href="'.$order->get_cancel_order_url().'">'.__('Cancel order &amp; restore cart', 'woo_mintpay').'</a>
				<script type="text/javascript">
				jQuery(function(){
				jQuery("body").block({
					message: "'.__('Thanks for your order! We are now redirecting you to Mintpay payment gateway to make the payment.', 'woo_mintpay').'",
					overlayCSS: {
						background		: "#fff",
						opacity			: 0.8
					},
					css: {
						padding			: 20,
						textAlign		: "center",
						color			: "#333",
						border			: "1px solid #eee",
						backgroundColor	: "#fff",
						cursor			: "wait",
						lineHeight		: "32px"
					}
				});
				jQuery("#submit_mintpay_payment_form").click();});
				</script>
			</form>';

		}

		else{
			return wp_redirect( wc_get_cart_url() );
		}
		
		} //END-generate_mintpay_form


		function check_mintpay_response(){
			global $woocommerce;

			if(isset($_GET['key']) && isset($_GET['hash'])){

				if (isset($_SESSION['order_id'])){
					$order = wc_get_order( $_SESSION['order_id'] );

					$post_success_hash = hash_hmac('sha256', $this->merchant_id . sprintf("%.02f", round($order->get_total(), 2)) . $_SESSION['order_id'],  $this->merchant_secret);

					$post_failed_hash = hash_hmac('sha256', $_SESSION['order_id'],  $this->merchant_secret);

					if ( base64_decode($_GET['hash']) == $post_success_hash){
						$order->payment_complete();
						$woocommerce->cart->empty_cart();
						wp_redirect( $order->get_checkout_order_received_url() );
					}

					else if ( base64_decode($_GET['hash']) == $post_failed_hash){
						$cancelled_text = "Payment failed.";
						$order->update_status( 'cancelled', $cancelled_text );
						wp_redirect( $order->get_cancel_order_url() );
					} 
					else{
						$cancelled_text = "Suspicious response.";
						$order->update_status( 'cancelled', $cancelled_text );
						$woocommerce->cart->empty_cart();
						wp_redirect( $order->get_cancel_order_url() );
						//return echo $post_hash;
					}

				}

				else{
					wp_redirect( wc_get_cart_url() );
				}
			}

			else{
					wp_redirect( wc_get_cart_url() );
			}
		}
				

		
        /**
         * Process the payment and return the result
         **/
        function process_payment($order_id){
			global $woocommerce;
            $order = new WC_Order($order_id);
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) { // For WC 2.1.0
			  	$checkout_payment_url = $order->get_checkout_payment_url( true );
			} else {
				$checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
			}

			return array(
				'result' => 'success', 
				'redirect' => add_query_arg(
					'order', 
					$order->id, 
					add_query_arg(
						'key', 
						$order->order_key, 
						$checkout_payment_url						
					)
				)
			);
		} //END-process_payment


        /**
         * Get Page list from WordPress
         **/
		function mintpay_get_pages($title = false, $indent = true) {
			$wp_pages = get_pages('sort_column=menu_order');
			$page_list = array();
			if ($title) $page_list[] = $title;
			foreach ($wp_pages as $page) {
				$prefix = '';
				// show indented child pages?
				if ($indent) {
                	$has_parent = $page->post_parent;
                	while($has_parent) {
                    	$prefix .=  ' - ';
                    	$next_page = get_post($has_parent);
                    	$has_parent = $next_page->post_parent;
                	}
            	}
            	// add to page list array array
            	$page_list[$page->ID] = $prefix . $page->post_title;
        	}
        	return $page_list;
		} //END-mintpay_get_pages

	} //END-class
	
	/**
 	* Add the Gateway to WooCommerce
 	**/
	function woocommerce_add_gateway_mintpay_gateway($methods) {
		$methods[] = 'WC_Gateway_mintpay';
		return $methods;
	}//END-wc_add_gateway
	
	add_filter('woocommerce_payment_gateways', 'woocommerce_add_gateway_mintpay_gateway' );
	
} //END-init

/**
* 'Settings' link on plugin page
**/
add_filter( 'plugin_action_links', 'mintpay_add_action_plugin', 10, 5 );
function mintpay_add_action_plugin( $actions, $plugin_file ) {
	static $plugin;

	if (!isset($plugin))
		$plugin = plugin_basename(__FILE__);
	if ($plugin == $plugin_file) {

			$settings = array('settings' => '<a href="admin.php?page=wc-settings&tab=checkout&section=wc_gateway_mintpay">' . __('Settings') . '</a>');
		
    			$actions = array_merge($settings, $actions);
			
		}
		
		return $actions;
}//END-settings_add_action_link
