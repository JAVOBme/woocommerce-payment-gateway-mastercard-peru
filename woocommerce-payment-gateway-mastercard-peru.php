<?php

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

/**
 * Mastercard Peru Payment Gateway
 *
 * Provides a Master Card Peru Payment Gateway.
 *
 * Plugin Name: WooCommerce Mastercard Peru Payment Gateway
 * Plugin URI: http://www.javob.me
 * Description: Mastercard Peru Payment gateway for woocommerce
 * Version: 1.0
 * Author: Javier Ordinola
 * Author URI: http://www.javob.me
 */

add_action('plugins_loaded', 'woocommerce_javob_mastercardperu_init', 0);

function woocommerce_javob_mastercardperu_init(){

	if(!class_exists('WC_Payment_Gateway')) return;

	if( isset($_GET['msg']) && !empty($_GET['msg']) ){
		add_action('the_content', 'showMastercardMessage');
	}
	function showMastercardMessage($content){
		return '<div class="'.htmlentities($_GET['type']).'">'.htmlentities(urldecode($_GET['msg'])).'</div>'.$content;
	}

	class WC_Gateway_MastercardPeru extends WC_Payment_Gateway {

		/**
		 * Constructor for the gateway.
		 *
		 * @access public
		 * @return void
		 */

		public function __construct() {

			global $woocommerce;

			$this->id                = 'mastercarperu';
			$this->icon              = apply_filters( 'woocommerce_mastercard_icon', plugins_url( 'images/mastercard.gif' , __FILE__ ) );
			$this->has_fields        = false;
			$this->method_title      = __( 'Mastercard', 'mastercard-woocommerce' );
			$this->order_button_text = __( 'Proceed to Mastercard', 'mastercard-woocommerce' );

			//Carga de campos del formulario.
			$this -> init_form_fields();

			//Carga las configuraciones.
			$this -> init_settings();

			$this->liveurl			= $this->settings['liveurl'];
			$this->testurl			= 'http://server.punto-web.com/gateway/PagoWebHd.asp';
			
			//$this->notify_url		= WC()->api_request_url( 'WC_Gateway_Paypal' );


			// Define user set variables
			$this->title 			= $this->get_option('title');
			$this->description		= $this->settings['description'];
			$this->key_merchant		= $this->settings['key_merchant'];
			$this->store_code		= $this->settings['store_code'];
			$this->testmode			= $this->get_option( 'testmode' );
			$this->debug			= $this->get_option( 'debug' );
			$this->page_style		= $this->get_option( 'page_style' );

			if ($this->testmode == "yes")
				$this->debug = "yes";

			// Logs
			if ( 'yes' == $this->debug ){
				if(version_compare( WOOCOMMERCE_VERSION, '2.1', '>=')){
					$this->log = new WC_Logger();
				}else{
					$this->log = $woocommerce->logger();
				}
			}

			$this->msg['message'] = "";
			$this->msg['class'] = "";

			add_action('mastercard_init', array( $this, 'mastercard_successful_request'));
			add_action( 'woocommerce_receipt_mastercard', array( $this, 'receipt_page' ) );
			//update for woocommerce >2.0
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_mastercard_response' ) );
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				/* 2.0.0 */
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			} else {
				/* 1.6.6 */
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}

		}

		/**
		 * Initialise Gateway Settings Form Fields
		 *
		 * @access public
		 * @return void
		 */
		function init_form_fields() {
			$this->form_fields = array(
				'enabled' => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Mastercard', 'javob' ),
					'default' => 'yes'
				),
				'title' => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Mastercard', 'woocommerce' ),
					'desc_tip'    => true,
				),
				'description' => array(
					'title'       => __( 'Description', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Pay via Mastercard; you can pay with your credit or debit card', 'javob' )
				),
				'key_merchant' => array(
					'title'			=> __('Key Merchant', 'mastercard-woocommerce'),
					'type'			=> 'text',
					'description'	=> __('Key provided by Procesos MC Perú', 'mastercard-woocommerce'),
					'default'		=> '',
					'desc_tip'		=> true
				),
				'store_code' => array(
					'title'			=> __( 'Store code', 'woocommerce' ),
					'type'			=> 'text',
					'description'	=> __('This ID is sent by Mastercard Peru.', 'javob'),
					'default'		=> '',
					'desc_tip'    => true,
					'placeholder' => '1234567'
				),
				'liveurl' => array(
					'title'	      => __('Live URL', 'javob'),
					'type'		  => 'text',
					'description' => __('The final URL for live web mode', 'javob'),
					'default'	  => '',
					'desc_tip'	  => true
				),
				'page_style' => array(
					'title'       => __( 'Page Style', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Optionally enter the name of the page style you wish to use.', 'javob' ),
					'default'     => '',
					'desc_tip'    => true,
					'placeholder' => __( 'Optional', 'woocommerce' )
				),
				'testing' => array(
					'title'       => __( 'Gateway Testing', 'woocommerce' ),
					'type'        => 'title',
					'description' => ''
				),
				'testmode' => array(
					'title'       => __( 'Mastercard Testing', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable Mastercard testing', 'woocommerce' ),
					'default'     => 'no',
					'description' => __( 'Mastercard testmode can be used to test payments.'),
					'desc_tip'		=> true
				),
				'testurl' => array(
					'title'		  => __('Mastercard url test', 'javob'),
					'type'		  => 'text',
					'description' => __('The URL for test mode', 'javob'), 
					'default'	  => 'http://server.punto-web.com/gateway/PagoWebHd.asp',
					'desc_tip'		=> true
				),
				'debug' => array(
					'title'       => __( 'Debug Log', 'woocommerce' ),
					'type'        => 'checkbox',
					'label'       => __( 'Enable logging', 'woocommerce' ),
					'default'     => 'no',
					'description' => sprintf( __( 'Log Mastercard events, such as IPN requests, inside <code>woocommerce/logs/mastercard-%s.txt</code>', 'woocommerce' ), sanitize_file_name( wp_hash( 'mastercard' ) ) )
				),
				'redirect_page_id' => array(
					'title' 		=> __('Return Page', 'mastercard-woocommerce'),
					'type' 			=> 'select',
					'options' 		=> get_pagess(__('Select Page', 'mastercard-woocommerce')),
					'description' 	=> __('URL of success page', 'mastercard-woocommerce'),
					'desc_tip' 		=> true
				),
				'empty_cart' => array(
					'title' 		=> __('Empty Cart after payment completed.', 'mastercard-woocommerce'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Do you want empty the user shopping cart after the payment is complete?.', 'mastercard-woocommerce'),
					'default' 		=> 'no',
					'description' 	=> __('Do you want empty the user shopping cart after the payment is complete?.', 'mastercard-woocommerce')
				)
			);
		}


		/**
		 * Admin Panel Options
		 *
	     * @access public
	     * @return string
		 **/

		public function admin_options() {
			?>
			<h3><?php _e( 'Mastercard Per&uacute;', 'woocommerce' ); ?></h3>
			<p><?php _e( 'Mastercard standard works by sending the user to Mastercard to enter their payment information.', 'woocommerce' ); ?></p>

			<table class="form-table">
				<?php
				// Generate the HTML For the settings form.
				$this->generate_settings_html();
				?>
			</table><!--/.form-table-->
			<?php
		}

		/**
		 * Generate the Mastercard Payment Fields
	     *
	     * @access public
	     * @return string
	     */
		function payment_fields(){
			if($this->description) echo wpautop(wptexturize($this->description));
		}

		/**
		 * Generate the Mastercard Form for checkout
		 *
		 * @access public
		 * @param mixed $order
		 * @return string
		 **/
		function receipt_page($order){
			echo '<p>'.__('Thank you for your order, please click the button below to pay with Mastercard.', 'mastercard-woocommerce').'</p>';
			echo $this->generate_mastercard_form($order);
		}

		/**
		* Generate Mastercard POST arguments
	    *
	    * @access public
	    * @param mixed $order_id
	    * @return array
		**/
		function get_mastercard_args( $order_id ) {

			global $woocommerce;
			$order = new WC_Order( $order_id );
			$txnid = $order->order_key;
			$order_id = $order->id;
			$currency 	= 'PEN';
			$country 	= 'PER';

			$date_transaction	= date("Ymd");
			$hour_transaction	= date("His");

			//autogenerador aleatorio
			$fecha_g			= new DateTime();
			$autogenerator		= "LLMG".$fecha_g->getTimestamp();

			//verifica el modo debug
			if ( 'yes' == $this->debug ) {
				//Test Mode
				if ('yes' == $this->testmode) {
					$urlfinal = $this->testurl;
				}
				else {
					$urlfinal = $this->liveurl;
				}
				$this->log->add( 'mastercard', 'Generating payment form for order ' . $order->get_order_number() . '. Notify URL: ' . $urlfinal );
			}

			// Mastercard Args
			$mastercard_args = array(
				'store_code'		=> $this->store_code,
				'order_id'      	=> $order->order_id,
				'amount'			=> $order->order_total,
				'currency'			=> $currency,
				'date_transaction'	=> $date_transaction,
				'hour_transaction'	=> $hour_transaction,
				'autogenerator'		=> $autogenerator,
				'userid'			=> $order->user_id,
				'country'			=> $country,
				'key_merchant'		=> $this->key_merchant
			);

			return $mastercard_args;
		}

		/**
		 * Generate the Mastercard button link
		 *
		 * @access public
		 * @param mixed $order_id
		 * @return string
		 */
		function generate_mastercard_form( $order_id ) {
			global $woocommerce;
			$order = new WC_Order( $order_id );

			if ( 'yes' == $this->testmode ) {
				$mastercard_adr = $this->testurl;
			} else {
				$mastercard_adr = $this->liveurl;
			}

			$mastercard_args = $this->get_mastercard_args( $order_id );
			$mastercard_args_array = array();

			foreach ( $mastercard_args as $key => $value ) {
				$mastercard_args_array[] = '<input type="hidden" name="'.esc_attr( $key ) . '" value="' . esc_attr( $value ) . '" />';
			}

			//Genera Firma
			function hmacsha1($key,$data, $hex = false) {
				$blocksize=64;
				$hashfunc='sha1';
				if (strlen($key)>$blocksize)
					$key=pack('H*', $hashfunc($key));
				$key=str_pad($key,$blocksize,chr(0x00));
				$ipad=str_repeat(chr(0x36),$blocksize);
				$opad=str_repeat(chr(0x5c),$blocksize);
				$hmac =
				pack('H*',$hashfunc(($key^$opad).pack('H*',$hashfunc(($key^$ipad).$data))));
				if ($hex == false) {
					return $hmac;
				}else{
					return bin2hex($hmac);
				}
			}

			//Concatenando arg
			foreach ($mastercard_args as $key => $value) {
				$cadena_args_array[] = esc_attr($value); 
			}
			$Cadenafinal = implode("",$cadena_args_array);

			//Generando MAC
			$strHash= urlencode(base64_encode(hmacsha1($this->key_merchant,$Cadenafinal)));

			$mastercard_args_array[] = '<input type="hidden" name="strhash" value="' . $strHash . '" />';

			wc_enqueue_js( '
				$.blockUI({
						message: "' . esc_js( __( 'Thank you for your order. We are now redirecting you to Mastercard to make payment.', 'mastercard-woocommerce' ) ) . '",
						baseZ: 99999,
						overlayCSS:
						{
							background: "#fff",
							opacity: 0.6
						},
						css: {
							padding:        "20px",
							zindex:         "9999999",
							textAlign:      "center",
							color:          "#555",
							border:         "3px solid #aaa",
							backgroundColor:"#fff",
							cursor:         "wait",
							lineHeight:		"24px",
						}
					});
				jQuery("#submit_mastercard_payment_form").click();
			' );

			return '<form action="' . esc_url( $mastercard_adr ) . '" method="post" id="mastercard_payment_form" target="_top">
					' . implode( '', $mastercard_args_array ) . '
					<!-- Button Fallback -->
					<div class="payment_buttons">
						<input type="submit" class="button alt" id="submit_mastercard_payment_form" value="' . __( 'Pay via Mastercard', 'mastercard-woocommerce' ) . '" /> <a class="button cancel" href="' . esc_url( $order->get_cancel_order_url() ) . '">' . __( 'Cancel order &amp; restore cart', 'woocommerce' ) . '</a>
					</div>
					<script type="text/javascript">
						jQuery(".payment_buttons").hide();
					</script>
				</form>';
		}

		/**
		 * Process the payment and return the result
		 *
		 * @access public
		 * @param int $order_id
		 * @return array
		 */
		function process_payment( $order_id ) {
			$order = new WC_Order( $order_id );
			if ( $this->form_method == 'GET' ) {
				$mastercard_args = $this->get_mastercard_args( $order_id );
				$mastercard_args = http_build_query( $mastercard_args, '', '&' );
				if ( $this->testmode == 'yes' ):
					$mastercard_adr = $this->testurl . '&';
				else :
					$mastercard_adr = $this->liveurl . '?';
				endif;

				return array(
					'result' 	=> 'success',
					'redirect'	=> $mastercard_adr . $mastercard_args
				);
			} else {
				if (version_compare( WOOCOMMERCE_VERSION, '2.1', '>=')) {
					return array(
						'result' 	=> 'success',
						'redirect'	=> add_query_arg('order-pay', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
					);
				} else {
					return array(
						'result' 	=> 'success',
						'redirect'	=> add_query_arg('order', $order->id, add_query_arg('key', $order->order_key, get_permalink(woocommerce_get_page_id('pay' ))))
					);
				}
			}
		}

		/**
		 * Check for valid mastercard server callback
		 * @access public
		 * @return void
		 **/
		function check_mastercard_response(){
			@ob_clean();
			if ( ! empty( $_REQUEST ) ) {
				header( 'HTTP/1.1 200 OK' );
				print_r($_REQUEST);
				do_action( "mastercard_init", $_REQUEST );
			} else {
				wp_die( __("Mastercard Request Failure", 'mastercard-woocommerce') );
			}
		}










		/**
		 *  Get order information
		 *
		 * @access public
		 * @param mixed $posted
		 * @return void
		 */
		function get_mastercard_order( $posted ) {
			$custom =  $posted['order_id'];
			// Backwards comp for IPN requests
			$order_id = (int) $custom;
			$order_key = ($posted['referenceCode'])?$posted['referenceCode']:$posted['reference_sale'];
			$order = new WC_Order( $order_id );
			if ( ! isset( $order->id ) ) {
				// We have an invalid $order_id, probably because invoice_prefix has changed
				$order_id 	= woocommerce_get_order_id_by_order_key( $order_key );
				$order 		= new WC_Order( $order_id );
			}
			// Validate key
			if ( $order->order_key !== $order_key ) {
				if ( $this->debug=='yes' )
					$this->log->add( 'payulatam', __('Error: Order Key does not match invoice.', 'payu-latam-woocommerce') );
				exit;
			}
			return $order;
		}
	}
		/**
		 * Get pages for return page setting
		 *
		 * @access public
		 * @return bool
		 */
		function get_pagess($title = false, $indent = true) {
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
						$next_page = get_page($has_parent);
						$has_parent = $next_page->post_parent;
					}
				}
				// add to page list array array
				$page_list[$page->ID] = $prefix . $page->post_title;
			}
			return $page_list;
		}

		/**
		 * Add the Gateway to WooCommerce
		 **/

		function woocommerce_add_javob_mastercardperu_gateway($methods) {
			$methods[] = 'WC_Gateway_MastercardPeru';
			return $methods;
		}

		add_filter('woocommerce_payment_gateways', 'woocommerce_add_javob_mastercardperu_gateway' );
	
}
