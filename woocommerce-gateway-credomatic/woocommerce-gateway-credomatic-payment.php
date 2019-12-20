<?php

class Credomatic_Payment_Gateway extends WC_Payment_Gateway {
  
  function __construct() {
    
    $this->id = "bac_payment";
    $this->method_title = __( "WooCommerce Gateway Credomatic", 'bac-payment' );
    $this->method_description = __( "WooCommerce Gateway Credomatic Plug-in for WooCommerce", 'bac-payment' );
    $this->title = __( "BAC Payment Gateway", 'bac-payment' );
    $this->icon = null;
    $this->has_fields = true;
    $this->supports = array( 'default_credit_card_form' );
    $this->init_form_fields();
    $this->init_settings();
    
    
    // Turn these settings into variables we can use
    foreach ( $this->settings as $setting_key => $value ) {
      $this->$setting_key = $value;
    }
  
    add_action( 'admin_notices', array( $this,  'do_ssl_check' ) );
    
    // Save settings
    if ( is_admin() ) {      
      add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
    } 
    
    add_action( 'woocommerce_receipt_' . $this->id, array($this,'build_form'));  
    add_action( 'woocommerce_api_' . $this->id, array( $this, 'check_remote_response' ) );
    


  } 

  public function check_remote_response()
  {
 
    global $woocommerce;

    try{
      $hash = filter_input(INPUT_GET,'hash');
      $orderid = filter_input(INPUT_GET,'orderid');
      $amount = filter_input(INPUT_GET,'amount');
      $response =  filter_input(INPUT_GET,'response');
      $responsetext =  filter_input(INPUT_GET,'responsetext');
      $response_code =  filter_input(INPUT_GET,'response_code');
      $transactionid =  filter_input(INPUT_GET,'transactionid');
      $avsresponse =  filter_input(INPUT_GET,'avsresponse');
      $cvvresponse =  filter_input(INPUT_GET,'cvvresponse');
      $time = filter_input(INPUT_GET,'time');
      $key = $this->settings['api_key'];

      $my_hash = md5($orderid . "|" .$amount . "|" . $response . "|" . $transactionid . "|" .
      $avsresponse . "|" . $cvvresponse . "|" . $time . "|" . $key);
  
      $customer_order = new WC_Order( $orderid );

      if(!$customer_order){
          throw new Exception("Cannot get order id from gateway");
      }

      if($hash!=$my_hash){
    //    throw new Exception("Hash security exception from gateway");
      }



      if ( $response != 1  ) {    
        if($responsetext){
            $error = $responsetext;
        }else{
          $error = "empty error from gateway";
        }
        throw new Exception($error);
      }

      
        $customer_order->add_order_note( __( 'BAC payment completed.', 'bac-payment' ) );
        
        $order_id = method_exists( $customer_order, 'get_id' ) ? $customer_order->get_id() : $customer_order->ID;
        update_post_meta($order_id , '_wc_order_bac_authcode', $response );
        update_post_meta($order_id , '_wc_order_bac_transactionid', $transactionid );



        $customer_order->payment_complete();
        $woocommerce->cart->empty_cart();
        $customer_order->update_status( 'completed', __( 'Completed payment.', 'txtdomain' ) );


        $redirect =  WC_Payment_Gateway::get_return_url( $customer_order );
        wp_redirect($redirect);

    }catch(Exception $e){
      $customer_order->update_status( 'pending', __( 'Failed payment.', 'txtdomain' ) );
      wc_add_notice( $e->getMessage(), 'error' );
      $customer_order->add_order_note( 'Error: '. $e->getMessage() );
      wp_redirect( $customer_order->get_checkout_payment_url(), '301' );
    }

 
    
  }
  
  public function init_form_fields() {
    $this->form_fields = array(
      'enabled' => array(
        'title'   => __( 'Activar / Desactivar', 'bac-payment' ),
        'label'   => __( 'Activar este metodo de pago', 'bac-payment' ),
        'type'    => 'checkbox',
        'default' => 'no',
      ),
      'title' => array(
        'title'   => __( 'Título', 'bac-payment' ),
        'type'    => 'text',
        'desc_tip'  => __( 'Título de pago que el cliente verá durante el proceso de pago.', 'bac-payment' ),
        'default' => __( 'Tarjeta de crédito', 'bac-payment' ),
      ),
      'description' => array(
        'title'   => __( 'Descripción', 'bac-payment' ),
        'type'    => 'textarea',
        'desc_tip'  => __( 'Descripción de pago que el cliente verá durante el proceso de pago.', 'bac-payment' ),
        'default' => __( 'Pague con seguridad usando su tarjeta de crédito.', 'bac-payment' ),
        'css'   => 'max-width:350px;'
      ),
      'key_id' => array(
        'title'   => __( 'Key id', 'bac-payment' ),
        'type'    => 'text',
        'desc_tip'  => __( 'ID de clave de seguridad del panel de control del comerciante.', 'bac-payment' ),
        'default' => '',
      ),
      'api_key' => array(
        'title'   => __( 'Api key', 'bac-payment' ),
        'type'    => 'text',
        'desc_tip'  => __( 'ID de clave de api del panel de control del comerciante.', 'bac-payment' ),
        'default' => '',
      ),
      'endpoint_url' => array(
        'title'   => __( 'Endpoint URL', 'bac-payment' ),
        'type'    => 'text',
        'desc_tip'  => __( 'URL endpoint credomatic.', 'bac-payment' ),
        'default' => 'https://credomatic.compassmerchantsolutions.com/api/transact.php',
      ),
    );    
  }

  public function build_form($order_id){

    $order = new WC_Order( $order_id );
    
    echo '<p>' . __( 'Redirecting to payment provider.', 'txtdomain' ) . '</p>';
    
    $order->add_order_note( __( 'Order placed and user redirected.', 'txtdomain' ) );
    $order->update_status( 'on-hold', __( 'Awaiting payment.', 'txtdomain' ) );

    $request =  new Credomatic_Request_Gateway($this->settings, $order_id );
    echo $request->getForm();

  }
  // Submit payment and handle response
  public function process_payment( $order_id ) {
  $_SESSION['credomatic']['bac_payment-card-number'] = $_POST['bac_payment-card-number'];
  $_SESSION['credomatic']['bac_payment-card-expiry'] =  str_replace( array( '/', ' '), '', $_POST['bac_payment-card-expiry'] );
  $_SESSION['credomatic']['bac_payment-card-cvc'] = $_POST['bac_payment-card-cvc'];

  $order = new WC_Order( $order_id );
    return array(
        'result' => 'success',
        'redirect' => $order->get_checkout_payment_url( true )
    );

   
  }

  public function validate_fields() {
    return true;
  }
  
  // Check if we are forcing SSL on checkout pages
  // Custom function not required by the Gateway
  public function do_ssl_check() {
    if( $this->enabled == "yes" ) {
      if( get_option( 'woocommerce_force_ssl_checkout' ) == "no" ) {
        echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";  
      }
    }   
  }
}
/**
 * Display field value on the order edit page
 */
add_action( 'woocommerce_admin_order_data_after_billing_address', 'show_bac_info', 10, 1 );
function show_bac_info( $order ){
    $order_id = method_exists( $order, 'get_id' ) ? $order->get_id() : $order->id;
    echo '<p><strong>'.__('BAC Auth Code').':</strong> ' . get_post_meta( $order_id, '_wc_order_bac_authcode', true ) . '</p>';
    echo '<p><strong>'.__('BAC Transaction Id').':</strong> ' . get_post_meta( $order_id, '_wc_order_bac_transactionid', true ) . '</p>';
}
?>
