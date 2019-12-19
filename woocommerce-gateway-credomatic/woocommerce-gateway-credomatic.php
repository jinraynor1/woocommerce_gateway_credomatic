<?php
/*
Plugin Name: WooCommerce Gateway Credomatic
Plugin URI: https://mihost.com
Description: Gateway de pago Credomatic.
Version: 0.0.1
Author: MiHost
Author URI: https://mihost.com/
*/

  // Include our Gateway Class and Register Payment Gateway with WooCommerce
  add_action( 'plugins_loaded', 'bac_payment_init', 0 );
  function bac_payment_init() {
    
    if( ! session_id() ) {      
      session_start();
    }

  //solo para prueba de otro code
 // define('WOOCOMMERCE_DOTPAY_PLUGIN_DIR', plugin_dir_path( __FILE__ ));
 // define('WOOCOMMERCE_DOTPAY_PLUGIN_URL', plugin_dir_url( __FILE__ ));

    // If the parent WC_Payment_Gateway class doesn't exist
    // it means WooCommerce is not installed on the site
    // so do nothing
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;
    
    // If we made it this far, then include our Gateway Class
    include_once( 'woocommerce-gateway-credomatic-payment.php' );
    include_once( 'woocommerce-gateway-credomatic-request.php' );
    // Now that we have successfully included our class,
    // Lets add it too WooCommerce
    add_filter( 'woocommerce_payment_gateways', 'add_bac_payment_gateway' );
    function add_bac_payment_gateway( $methods ) {
      $methods[] = 'Credomatic_Payment_Gateway';
      return $methods;
    }
  }
  // Add custom action links
  add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), 'bac_payment_action_links' );
  function bac_payment_action_links( $links ) {
    $plugin_links = array(
      '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout' ) . '">' . __( 'Settings', 'bac-payment' ) . '</a>',
    );
    // Merge our new link with the default ones
    return array_merge( $plugin_links, $links );
  }
