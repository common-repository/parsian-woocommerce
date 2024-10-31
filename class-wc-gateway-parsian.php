<?php

if( ! defined( 'ABSPATH' ) ) {
	exit();
}

function Load_BankParsian_Gateway() {
	if( class_exists( 'WC_Payment_Gateway' ) && ! class_exists( 'WC_Gateway_Bankparsian' ) && ! function_exists( 'Woocommerce_Add_BankParsian_Gateway' ) ) {

		add_filter( 'woocommerce_payment_gateways', 'Woocommerce_Add_BankParsian_Gateway' );

		function Woocommerce_Add_BankParsian_Gateway( $methods ) {
			$methods[] = 'WC_Gateway_Bankparsian';
			return $methods;
		}

		add_filter('woocommerce_currencies', 'add_IR_currency');

		function add_IR_currency($currencies) {
			$currencies['IRR'] = __('ریال', 'woocommerce');
			$currencies['IRT'] = __('تومان', 'woocommerce');
			$currencies['IRHR'] = __('هزار ریال', 'woocommerce');
			$currencies['IRHT'] = __('هزار تومان', 'woocommerce');

			return $currencies;
		}

		add_filter('woocommerce_currency_symbol', 'add_IR_currency_symbol', 10, 2);

		function add_IR_currency_symbol($currency_symbol, $currency) {
			switch ($currency) {
				case 'IRR': $currency_symbol = 'ریال';
					break;
				case 'IRT': $currency_symbol = 'تومان';
					break;
				case 'IRHR': $currency_symbol = 'هزار ریال';
					break;
				case 'IRHT': $currency_symbol = 'هزار تومان';
					break;
			}
			return $currency_symbol;
		}

		class WC_Gateway_Bankparsian extends WC_Payment_Gateway {
			public function __construct() {
				$this->author = 'barfaraz.com';
				$this->id = 'bankparsian';
				$this->method_title = __( 'بانک پارسیان', 'woocommerce' );
				$this->method_description = __( 'تنظیمات درگاه پرداخت بانک پارسیان برای افزونه فروشگاه ساز ووکامرس', 'woocommerce' );
				$this->icon = WP_PLUGIN_URL . '/' . plugin_basename(dirname(__FILE__)) . '/assets/images/logo.png';
				$this->has_fields = false;
				$this->init_form_fields();
				$this->init_settings();
				$this->title = $this->settings['title'];
				$this->description = $this->settings['description'];
				$this->pin = $this->settings['pin'];
				$this->connecting_message = $this->settings['connecting_message'];
				$this->connection_error_massage = $this->settings['connection_error_massage'];
				$this->success_massage = $this->settings['success_massage'];
				$this->failed_massage = $this->settings['failed_massage'];
				
				if( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
					add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
				} else {
					add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
				}
				
				add_action( 'woocommerce_receipt_' . $this->id . '', array( $this, 'Send_to_BankParsian_Gateway' ) );
				add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ) . '', array( $this, 'Return_from_BankParsian_Gateway' ) );
			}

			public function admin_options() {
				parent::admin_options();
			}

			public function init_form_fields() {
				$this->form_fields = array(
					'base_confing' => array(
						'title'			=> __( 'تنظیمات پایه', 'woocommerce' ),
						'type'			=> 'title',
						'description'	=> '',
					),
					'enabled' => array(
						'title'			=> __( 'فعال', 'woocommerce' ),
						'type'			=> 'checkbox',
						'label'			=> __( 'فعال سازی درگاه بانک پارسیان', 'woocommerce' ),
						'description'	=> __( 'برای فعال سازی درگاه بانک پارسیان این گزینه را تیک بزنید.', 'woocommerce' ),
						'default'		=> 'yes',
						'desc_tip'		=> true,
					),
					'title' => array(
						'title'			=> __( 'عنوان', 'woocommerce' ),
						'type'			=> 'text',
						'description'	=> __( 'این عنوان در هنگام انتخاب روش پرداخت به مشتری نشان داده می شود.', 'woocommerce' ),
						'default'		=> __( 'بانک پارسیان', 'woocommerce' ),
						'desc_tip'		=> true,
					),
					'description'		=> array(
						'title'			=> __( 'توضیحات', 'woocommerce' ),
						'type'			=> 'text',
						'desc_tip'		=> true,
						'description'	=> __( 'این توضیحات در هنگام انتخاب روش پرداخت به مشتری نشان داده می شود.', 'woocommerce' ),
						'default'		=> __( 'پرداخت امن از طریق درگاه بانک پارسیان', 'woocommerce' )
					),
					'account_confing'	=> array(
						'title'			=> __( 'تنظیمات بانک', 'woocommerce' ),
						'type'			=> 'title',
						'description'	=> '',
					),
					'pin' => array(
						'title'			=> __( 'شناسه پذیرنده', 'woocommerce' ),
						'type'			=> 'text',
						'description'	=> __( 'پین کد دریافت شده از بانک پارسیان.', 'woocommerce' ),
						'default'		=> '',
						'desc_tip'		=> true
					),
					'payment_confing' => array(
						'title'			=> __( 'تنظیمات عملیات پرداخت', 'woocommerce' ),
						'type'			=> 'title',
						'description'	=> '',
					),
					'connecting_message' => array(
						'title'			=> __( 'پیام اتصال به بانک', 'woocommerce' ),
						'type'			=> 'textarea',
						'description'	=> __( 'متن پیامی که میخواهید در هنگام اتصال به بانک به مشتری نمایش داده شود.', 'woocommerce' ),
						'default'		=> __( 'در حال اتصال به بانک...', 'woocommerce' ),
					),
					'connection_error_massage' => array(
						'title'			=> __( 'پیام خطا در اتصال به بانک', 'woocommerce' ),
						'type'			=> 'textarea',
						'description'	=> __( 'متن پیامی که میخواهید پس از خطا در اتصال به بانک به مشتری نمایش داده شود.', 'woocommerce' ),
						'default'		=> __( 'خطا در اتصال به بانک، لطفا مجددا تلاش نمایید.', 'woocommerce' ),
					),
					'success_massage' => array(
						'title'			=> __( 'پیام پرداخت موفق', 'woocommerce' ),
						'type'			=> 'textarea',
						'description'	=> __( 'متن پیامی که میخواهید پس از پرداخت موفق به مشتری نمایش داده شود.', 'woocommerce' ),
						'default'		=> __( 'با تشکر از شما، پرداخت سفارش شما با موفقیت انجام شد، کد رهگیری خود را یادداشت نمایید.', 'woocommerce' ),
					),
					'failed_massage'	=> array(
						'title'			=> __( 'پیام پرداخت ناموفق', 'woocommerce' ),
						'type'			=> 'textarea',
						'description'	=> __( 'متن پیامی که میخواهید پس از پرداخت ناموفق به مشتری نمایش داده شود.', 'woocommerce' ),
						'default'		=> __( 'پرداخت سفارش شما ناموفق بوده است. لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید.', 'woocommerce' ),
					),
				);
			}

			public function process_payment( $order_id ) {
				$order = new WC_Order( $order_id );	
				return array(
					'result'   => 'success',
					'redirect' => $order->get_checkout_payment_url(true)
				);
			}

			public function Send_to_BankParsian_Gateway($order_id) {
				global $woocommerce;
				$woocommerce->session->order_id_bankparsian = $order_id;
				$order = new WC_Order( $order_id );
				$currency = $order->get_order_currency();

				$form = '<form action="" method="POST" class="bankparsian-checkout-form" id="bankparsian-checkout-form">
				<input type="submit" name="bankparsian_submit" class="button alt" id="bankparsian-payment-button" value="'.__( 'پرداخت', 'woocommerce' ).'"/>
				<a class="button cancel" href="' . $woocommerce->cart->get_checkout_url() . '">' . __( 'بازگشت', 'woocommerce' ) . '</a>
				</form><br/>';
				
				echo $form;

				if( isset( $_POST['bankparsian_submit'] ) ) {
					$pin = $this->pin;
					$callbackUrl = add_query_arg( 'wc_order', $order_id , WC()->api_request_url( 'WC_Gateway_Bankparsian' ) );
					
					$amount = intval( $order->order_total );
					
					if ( strtolower( $currency ) == strtolower( 'IRT' ) || strtolower($currency) == strtolower( 'TOMAN' )
						|| strtolower( $currency ) == strtolower( 'Iran TOMAN' ) || strtolower($currency) == strtolower( 'Iranian TOMAN' )
						|| strtolower( $currency ) == strtolower( 'Iran-TOMAN' ) || strtolower($currency) == strtolower( 'Iranian-TOMAN' )
						|| strtolower( $currency ) == strtolower( 'Iran_TOMAN' ) || strtolower($currency) == strtolower( 'Iranian_TOMAN' )
						|| strtolower( $currency ) == strtolower( 'تومان' ) || strtolower($currency) == strtolower( 'تومان ایران' )
					)
						$amount = $amount * 10;
					else if ( strtolower( $currency ) == strtolower( 'IRHT' ) )
						$amount = $amount * 1000*10;
					else if ( strtolower( $currency ) == strtolower( 'IRHR' ) )
						$amount = $amount * 1000;

					if( ! class_exists( 'nusoap_client' ) ) {
						include_once( 'nusoap.php' );
					}

					$soapclient = new nusoap_client( 'https://pec.shaparak.ir/pecpaymentgateway/eshopservice.asmx?wsdl','wsdl' );

					$try_uniqid_count = 1;
					$try_uniqid = true;
					while( $try_uniqid == true ) {
						$parameters = array(
							'pin' => $pin,
							'amount' => $amount,
							'orderId' => time(),
							'callbackUrl' => $callbackUrl,
							'authority' => 0,
							'status' => 1
						);

						$result = $soapclient->call( 'PinPaymentRequest', $parameters );

						if( $result['status'] == 0 ) {
							$try_uniqid = false;
						} else {
							sleep(1);
						}
						if( $try_uniqid_count >= 10 ) {
							break;
						}
						$try_uniqid_count++;
					}

					if ( $soapclient->fault ) {
						$error = $result;
					} else {
						$err = $soapclient->getError();
						if ( $err ) {
							$error = $err;
						}
					}

					$status = $result['status'];
					$authority = $result['authority'];

					if( $status == 0 && $authority && $authority != -1 ) {
						$notice = wpautop( wptexturize( $this->connecting_message ) );
						if ( $notice ) {
							wc_add_notice( $notice , 'success' );
						}
						
						$pars_url = "https://pec.shaparak.ir/pecpaymentgateway/?au=$authority" ;
						echo "<script type='text/javascript'>window.onload = function () { top.location.href = '$pars_url'; };</script>";
					} else {
						$notice = wpautop( wptexturize( $this->connection_error_massage . '<br/>خطا: ' . $this->BankParsian_Gateway_Error( $status )  ) );
						if ( $notice ) {
							wc_add_notice( $notice , 'error' );
						}
					}
				}
			}

			public function Return_from_BankParsian_Gateway() {
				global $woocommerce;
				
				if( isset( $_GET['wc_order'] ) ) {
					$order_id = $_GET['wc_order'];
				} else {
					$order_id = $woocommerce->session->order_id_bankparsian;
				}
				
				if( $order_id ) {
					$order = new WC_Order( $order_id );
					
					if( $order->status != 'completed' ) {
						if( ! class_exists( 'nusoap_client' ) ) {
							include_once( 'nusoap.php' );
						}

						$pin = $this->pin;
						$authority = $_REQUEST['au'];
						$status = $_REQUEST['rs'];
						
						if( $status == 0 ) {
							$soapclient = new nusoap_client( 'https://pec.shaparak.ir/pecpaymentgateway/eshopservice.asmx?wsdl','wsdl' );

							$parameters = array(
								'pin' => $pin,
								'authority' => $authority,
								'status' => $status
							);
							
							$result = $soapclient->call( 'PinPaymentEnquiry', $parameters );
							
							if ( $soapclient->fault ) {
								$error = $result;
							} else {
								$err = $soapclient->getError();
								if ( $err ) {
									$error = $err;
								}
							}

							$status = $result['status'];

							if( $status == 0 && $authority && $authority != -1 ) {
								$notice = wpautop( wptexturize( $this->success_massage ) );
								if ( $notice ) {
									wc_add_notice( $notice , 'success' );
								}

								$notice = __( 'کد رهگیری شما: {authority}', 'woocommerce' );
								$notice = str_replace( '{authority}', $authority, $notice );
								if ( $notice ) {
									wc_add_notice( $notice , 'success' );
								}

								$order->payment_complete($order_id);
								$woocommerce->cart->empty_cart();

								update_post_meta( $order_id, '_transaction_id', $authority );

								wp_redirect( add_query_arg( 'wc_status', 'success', $this->get_return_url( $order ) ) );
								exit();
							} else {
								$notice = wpautop( wptexturize( $this->connection_error_massage . '<br/>خطا: ' . $this->BankParsian_Gateway_Error( $status )  ) );
								if ( $notice ) {
									wc_add_notice( $notice , 'error' );
								}

								wp_redirect(  $woocommerce->cart->get_checkout_url()  );
								exit();
							}
						} else {
							$notice = wpautop( wptexturize( $this->failed_massage ) );
							if( $notice ) {
								wc_add_notice( $notice , 'error' );
							}
						}
						
						wp_redirect( $woocommerce->cart->get_checkout_url() );
						exit();
					}
				} else {
					$notice = __( 'شناسه سفارش وجود ندارد.', 'woocommerce' );
					if ( $notice ) {
						wc_add_notice( $notice , 'error' );
					}
					
					wp_redirect( $woocommerce->cart->get_checkout_url() );
					exit();
				}
			}
			
			public function BankParsian_Gateway_Error( $err ) {
				switch( $err ) {
					case 1:
						$output = 'وضعیت نامشخص است.';
						break;
					case 20:
						$output = 'شناسه پذیرنده نامعتبر است.';
						break;
					case 22:
						$output = 'شناسه پذیرنده یا IP نامعتبر است.';
						break;
					case 30:
						$output = 'تراکنش قبلا انجام شده است.';
						break;
					case 30:
						$output = 'شماره تراکنش مشتری صحیح نمی باشد.';
						break;
				}
				return $output;
			}
		}
	}
}

add_action( 'plugins_loaded', 'Load_BankParsian_Gateway', 0 );