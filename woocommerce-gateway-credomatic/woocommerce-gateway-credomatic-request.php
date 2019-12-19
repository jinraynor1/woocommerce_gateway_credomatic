<?php

class Credomatic_Request_Gateway{

    private $order_id;    
    private $credomatic_settings;

    public function __construct( $credomatic_settings , $order_id){
        $this->credomatic_settings = $credomatic_settings;
        $this->order_id = $order_id;            
        
    }


    public function getForm(){
        
        $order_id = $this->order_id;
        $customer_order = new WC_Order( $order_id );
        $time = time();
        $key_id = $this->credomatic_settings['key_id'];
        $orderid = str_replace( "#", "", $customer_order->get_order_number() );
        $hash = md5($orderid."|".$customer_order->get_total()."|".$time."|".$this->credomatic_settings['api_key']);

        $key_id = $this->credomatic_settings['key_id'] ;
        $amount = $customer_order->get_total();


        wc_enqueue_js( 'jQuery( "#credomatic-form" ).submit();' );
        $endpoint_url = $this->credomatic_settings['endpoint_url'];
        $redirect =  str_replace( 'https:', 'http:', add_query_arg( 'wc-api', 'bac_payment', home_url( '/' ) ) );

        if(!isset($_SESSION['credomatic'])){
            wc_add_notice( "session payment lost, please contact admin", 'error' );
            wp_redirect( $customer_order->get_view_order_url(), '301' );
            exit();
        }


        $ccnumber = $_SESSION['credomatic']['bac_payment-card-number'];
        $ccexp = $_SESSION['credomatic']['bac_payment-card-expiry'];
        $cvv = $_SESSION['credomatic']['bac_payment-card-cvc'];



return  <<<EOF
<form id="credomatic-form" action = "$endpoint_url" method="post">
                        <input type="hidden" name="orderid" value="$order_id">
						<input type="hidden" name="key_id" value="$key_id">
						<input type="hidden" name="hash" value="$hash">
                        <input type="hidden" name="time" value="$time">
                        <input type="hidden" name="amount" value="$amount">
                        <input type="hidden" name="ccnumber" value="$ccnumber">
                        <input type="hidden" name="ccexp" value="$ccexp">
                        <input type="hidden" name="cvv" value="$cvv">
						<input type="hidden" name="redirect" value="$redirect">
                        <input type="hidden" name="type" value="auth" >                        
                        <noscript>
                                <a href="$redirect">Click here to continue</a>
                        </noscript>                                                
</form>
EOF;
 
    
    }
}