<?php
class HaetRoleDiscount {
    
    function __construct() {
        add_action( 'add_meta_boxes', array( &$this, 'addMetaBox' ) );
        add_action( 'save_post', array( &$this, 'saveMetaBox' ) );
        add_action('wpsc_top_of_products_page', array( &$this, 'updatePriceForDisplay'));
        add_action('wpsc_theme_footer',array( &$this, 'resetSpecialPrice'));
        add_action('wp_head',array( &$this, 'updateCart'));
        add_action('wpsc_before_shipping_of_shopping_cart',array( &$this, 'applyCoupon'));
    }

    
    /**
     * this function is only called once, when the plugin gets activated
     */
    function init() {
        add_role('haet_rolediscount_premium_customer', __('Premium Customer','haetrolediscount'));
        
        $options=$this->getOptions();
        if(!isset($options['coupon_code']) || $options['coupon_code']==''){
            $options['coupon_code']='premium-discount-'.uniqid();
            global $wpdb;
           
            $wpdb->query( 
                $wpdb->prepare( 
                        "
                        INSERT `".$wpdb->prefix."wpsc_coupon_codes` 
                        (`coupon_code`, `value`, `is-percentage`, `use-once`, `is-used`, `active`, `every_product`, `start`, `expiry`, `condition`) VALUES
                        (%s, 0.00, '0', '0', '0', '1', '1', '2012-01-01 00:00:00', '2030-12-31 00:00:00', 'a:0:{}')
                        ",
                        $options['coupon_code'] 
                )
            );
            
            update_option('haetrolediscount_options', $options);
        }
    }
    
    function getOptions() {
	$options = array(
            'coupon_code' => ''
            );
        $haetrolediscount_options = get_option('haetrolediscount_options');
        if (!empty($haetrolediscount_options)) {
                foreach ($haetrolediscount_options as $key => $option)
                        $options[$key] = $option;
        }				
        update_option('haetrolediscount_options', $options);
        return $options;
    }

    
    /**
     * create the meta box for the product edit page 
     */
    function addMetaBox(){
        add_meta_box( 
            'haet_premium_price',
            __( 'Premium Customers', 'haetrolediscount' ),
            array( &$this, 'printMetaBox'),
            'wpsc-product',
            'side'
        );
        
    }
    
    /**
      * print the Metabox and the premium price field to the product edit page
      *
      * @param object post
    */
    function printMetaBox( $post ) {

        // Use nonce for verification
        wp_nonce_field( plugin_basename( __FILE__ ), 'premium_price' );
        $discount = get_post_meta( $post->ID, '_haet_premium_discount', true );

        echo '<label for="haet_premium_discount">';
            _e("Price for premium customers", 'haetrolediscount' );
        echo '</label> ';
        echo '<input type="text" id="haet_premium_discount" name="haet_premium_discount" value="'.$discount.'" size="10" />';
    }

    /**
     * save the premium price from the metabox input to a meta field
     * 
     * @param int $post_id
     */
    function saveMetaBox( $post_id ) {
        // verify if this is an auto save routine. 
        // If it is our form has not been submitted, so we dont want to do anything
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
            return;

        // verify this came from the our screen and with proper authorization,
        // because save_post can be triggered at other times
        if ( !wp_verify_nonce( $_POST['premium_price'], plugin_basename( __FILE__ ) ) )
            return;

        // Check permissions
        if ( 'wpsc-product' == $_POST['post_type'] ) 
        {
            if ( !current_user_can( 'edit_page', $post_id ) )
                return;
        }
        else
        {
            return;
        }

        $discount = $_POST['haet_premium_discount'];
        update_post_meta( $post_id, '_haet_premium_discount',$discount);
    }


    
    /**
     * checks whether the current user is a premium customer
     * 
     * @global object $current_user
     * @return boolean 
     */
    function isPremiumCustomer(){
        global $current_user;
    	$user_roles = $current_user->roles;
    	$user_role = array_shift($user_roles);
        if($user_role=='haet_rolediscount_premium_customer')
            return true;
        return false;     
    }
    
    /**
     * set the special price for all products on the page to the premium customer price 
     */
    function updatePriceForDisplay(){
        if($this->isPremiumCustomer()){
            while (wpsc_have_products()){
                wpsc_the_product();

                $product_id = wpsc_the_product_id();
                $product = get_post( $product_id );
                $discount = get_post_meta( $product_id, '_haet_premium_discount', true );
                if ($discount>0){
                    $special_price = get_post_meta( $product_id, '_wpsc_special_price', true );
                    add_post_meta($product_id, '_haet_special_price_backup', $special_price, true);
                    update_post_meta( $product_id, '_wpsc_special_price',$discount);
                }
            }
        }
    }
    
    /**
     * reset the special price to the original value 
     */
    function resetSpecialPrice(){
        if($this->isPremiumCustomer()){
            while (wpsc_have_products()){
                wpsc_the_product();
                $product_id = wpsc_the_product_id();
                $product = get_post( $product_id );
                $special_price = get_post_meta( $product_id, '_haet_special_price_backup', true );
                delete_post_meta($product_id, '_haet_special_price_backup', $special_price);
                update_post_meta( $product_id, '_wpsc_special_price',$special_price);
            }
        }
    }
    
    /**
     * walk through the cart and update the special prices
     * 
     * @global object $wpsc_cart 
     */
    function updateCart(){
        if($this->isPremiumCustomer()){
            global $wpsc_cart;
            foreach($wpsc_cart->cart_items as $key => $cart_item) {
                $product_id = $cart_item->product_id;
                $product = get_post( $product_id );
                $discount = get_post_meta( $product_id, '_haet_premium_discount', true );
                if ($discount>0){
                    $special_price = get_post_meta( $product_id, '_wpsc_special_price', true );
                    update_post_meta( $product_id, '_wpsc_special_price',$discount);
                    $cart_item->refresh_item();
                    update_post_meta( $product_id, '_wpsc_special_price',$special_price);
                }
            }
            $wpsc_cart->clear_cache();
        }
    }
    
    function applyCoupon(){
        global $wpsc_cart;
        if($this->isPremiumCustomer()) { 
            $options=$this->getOptions();
            $wspc_coupons = new wpsc_coupons($options['coupon_code']); 
            $wpsc_cart->coupons_amount = $wspc_coupons->calculate_discount(); //Update the cart to include the discount amount.
        }
    }
         
    /**
     * hide the admin bar for premium customers 
     * thanks to Jason Witt: http://wp.tutsplus.com/tutorials/how-to-disable-the-admin-bar-in-wordpress-3-3/
     */
    function removeAdminBar(){
        if($this->isPremiumCustomer()){
            remove_action( 'admin_footer', 'wp_admin_bar_render', 1000 ); // for the admin page  
            remove_action( 'wp_footer', 'wp_admin_bar_render', 1000 ); // for the front end  
            function remove_admin_bar_style_backend() {  // css override for the admin page  
            echo '<style>body.admin-bar #wpcontent, body.admin-bar #adminmenu { padding-top: 0px !important; }</style>';  
            }  
            add_filter('admin_head','remove_admin_bar_style_backend');  
            function remove_admin_bar_style_frontend() { // css override for the frontend  
            echo '<style type="text/css" media="screen"> 
            html { margin-top: 0px !important; } 
            * html body { margin-top: 0px !important; } 
            </style>';  
            }  
            add_filter('wp_head','remove_admin_bar_style_frontend', 99);  
   
        }
    }
}

?>