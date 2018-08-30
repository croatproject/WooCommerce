<?php
/*
Plugin Name: CROATCoin WooCommerce Gateway
Plugin URI: https://github.com/croatproject/WooCommerce
Description: Passarela de pagament amb CROAT per Woocommerce
Version: 0.5
Author: Croat Project Team
Author URI: https://croat.cat/
*/

// Load Texts
add_action('plugins_loaded', 'load_text');

function load_text() {
	$domain = 'croatcoin';
	$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
	if ( $loaded = load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' ) ) {
		return $loaded;
	} else {
		load_plugin_textdomain( $domain, FALSE, basename( dirname( __FILE__ ) ).'/lang/'  );
	}
}

// Init Plugin
add_action('plugins_loaded', 'croatcoin_init'); 

function croatcoin_init()
{
    define('PLUGIN_DIR', plugins_url(basename(plugin_dir_path(__FILE__)), basename(__FILE__)) . '/');
    global $woocommerce;
     
    class WC_Gateway_CROAT extends WC_Payment_Gateway
    { 
        public $locale;
         
        public function __construct()
        {
            global $woocommerce;
           
            $this->id                 = 'croat';
			$this->name 			  = 'croat';
            $this->icon               = apply_filters('woocommerce_bacs_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __('CROAT', 'woocommerce');
            $this->method_description = __('Pagaments amb CROAT.', 'croatcoin');
            
            
            $this->init_form_fields();
            $this->init_settings();
            
            
            $this->title        = __('CROAT', 'woocommerce');
			$this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option('instructions');
            $this->parity       = $this->get_option('parity');
            $this->decimals       = $this->get_option('decimals');			
            $this->show_price   = $this->get_option('show_price');	
            $this->show_qrcode   = $this->get_option('show_qrcode');		
			$this->show_wallet   = $this->get_option('show_wallet');
			$this->parity_source       = $this->get_option('parity_source');
            
            $this->account_details = get_option('woocommerce_croat_hashs', array(
                array(
                    'hash_name' => $this->get_option('hash_name')
                )
            ));
            
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'process_admin_options'
            ));
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(
                $this,
                'save_account_details'
            ));
            
            add_action('woocommerce_email_before_order_table', array(
                $this,
                'email_instructions'
            ), 10, 3);
        }
        
        public function init_form_fields()
        {
            
			$this->form_fields = array(
                'enabled' => array(
                    'title' => __('Activar/Desactivar', 'croatcoin'),
                    'type' => 'checkbox',
                    'label' => __('Activar pagaments amb CROATCoin', 'croatcoin'),
                    'default' => 'no'
                ),
				'description'     => array(
					'title'       => __( 'Descripció', 'croatcoin' ),
					'type'        => 'textarea',
					'description' => __( 'Payment method description that the customer will see on your checkout.', 'woocommerce' ),
					'default'     => __( 'Paga mitjançant CROAT. Un cop completada la comanda, t\'informarem de l\'adreça del moneder CROAT on realitzar el pagament.', 'croatcoin' ),
					'desc_tip'    => true,
				),	
                'account_details' => array(
                    'type' => 'account_details',
                    'description' => __('S\'aconsella posar diversos moneders per rebre el pagament.', 'croatcoin'),
                    'desc_tip' => true
                ),
				'show_wallet' => array(
                    'title' => __('Mostrar l\'adreça del moneder en finalitzar la comanda.', 'croatcoin'),
                    'type' => 'checkbox',
                    'label' => __('Mostra o Amaga l\'adreça del moneder al finalitzar la comanda.', 'croatcoin'),
                    'default' => 'no'
                ),
				'parity_source' => array(
                    'title' => __('Origen Paritat', 'croatcoin'),
                    'type' => 'select',
                    'description' => __('Escollir l\'origen de la paritat de € a CROAT.', 'croatcoin'),
					'options' => array(
						'worldcoinindex' => __('WorldCoinIndex', 'croatcoin'),
					),
                    'default' => 'stocksexchange',					
                    'desc_tip' => true
                ),
                'parity' => array(
                    'title' => __('Paritat manual', 'croatcoin'),
                    'type' => 'text',
                    'description' => __('Paritat de € cap a CROATs. Deixar en blanc per fer servir el preu del origen seleccionat.', 'croatcoin'),
                    'default' => '',
                    'desc_tip' => true
                ),	
                'decimals' => array(
                    'title' => __('Decimals', 'croatcoin'),
                    'type' => 'text',
                    'description' => __('Numero de decimals a utilitzar.', 'croatcoin'),
                    'default' => '2',
                    'desc_tip' => true
                ),				
                'show_price' => array(
                    'title' => __('Mostrar preu en CROAT', 'croatcoin'),
                    'type' => 'checkbox',
                    'label' => __('Mostra o Amaga el preu en CROAT als productes.', 'croatcoin'),
                    'default' => 'no'
                ), 
				'show_qrcode' => array(
                    'title' => __('Mostrar codi QR per al pagament', 'croatcoin'),
                    'type' => 'checkbox',
                    'label' => __('Mostra o Amaga el codi QR per realitzar el pagament al finalitzar la comanda.', 'croatcoin'),
                    'default' => 'no'
                ), 
            );
            
        }
        
        public function get_icon()
        {  
            $icon_html = '';
            global $woocommerce;
            $preu  = $woocommerce->cart->total;
            $paritat = $this->settings['parity'];
            $decimals = $this->settings['decimals'];			
            $paritat_source = $this->settings['parity_source'];			
			
			foreach ($this->account_details as $account) {
				$wallet = esc_attr(wp_unslash($account['hash_name']));
			}
			
            if ($paritat != ""){
                $croats = $preu / (float)$paritat;
                $croats = round($croats, $decimals);           
			}
			else 
			{
				$croats = get_croat_price($preu, $paritat_source, $decimals);
			}
             
            //wp_dequeue_script( 'wc-checkout' );
            
            $dir_croat = plugin_dir_url(__FILE__);
            
            $icon_html .= '<img src="' . $dir_croat . 'images/croat.png" width="100px" alt="'.__('Pagament en CROATS', 'croatcoin').'"/> ';
            $icon_html .= '<a href="https://www.croat.cat" target="_blank" >'.__('Què es CROAT?', 'croatcoin').'</a>';
            
            return apply_filters('woocommerce_gateway_icon', $icon_html, $this->id);
        }
        
        public function generate_account_details_html()
        {
            
            ob_start();
            
            $country = WC()->countries->get_base_country();
            $locale  = $this->get_country_locale();
            
            $sortcode = isset($locale[$country]['sortcode']['label']) ? $locale[$country]['sortcode']['label'] : __('Sort code', 'woocommerce');
            
?>
<tr valign="top">
   <th scope="row" class="titledesc"><?php
            _e('Moneders de pagament en CROATs', 'croatcoin');
?>: 
   </th>
   <td class="forminp" id="bacs_accounts">
      <table class="widefat wc_input_table sortable" cellspacing="0">
         <thead>
            <tr>
               <th class="sort">&nbsp;</th>
               <th><?php _e('Direcció del Moneder', 'croatcoin');?></th>
           </tr>
         </thead>
         <tbody class="accounts">
            <?php
            $i = -1;
            if ($this->account_details) {
                foreach ($this->account_details as $account) {
                    $i++;
                    
                    echo '<tr class="account">
                           <td class="sort"></td>
                           <td><input type="text" value="' . esc_attr(wp_unslash($account['hash_name'])) . '" name="croat_hashs[' . $i . ']" size="130" /></td>';
                }
            }
            
?>
        </tbody>
         <tfoot>
            <tr>
               <th colspan="2"><a href="#" class="add button"><?php
            _e('+ Afegir moneder', 'croatcoin');
?></a> <a href="#" class="remove_rows button"><?php
            _e('Esborrar seleccionats', 'croatcoin');
?></a></th>
            </tr>
         </tfoot>
      </table>
      <script type="text/javascript">
         jQuery(function() {
             jQuery('#bacs_accounts').on( 'click', 'a.add', function(){
         
                 var size = jQuery('#bacs_accounts').find('tbody .account').length;
                 
         
                 jQuery('<tr class="account">\
                         <td class="sort"></td>\
                         <td><input type="text" name="croat_hashs[' + size + ']" /></td>\
                         </tr>').appendTo('#bacs_accounts table tbody');
                     
                 
         
                 return false;
             });
         });
      </script>
   </td>
</tr>
<?php
            return ob_get_clean();
        }
        
        public function save_account_details()
        {
            $accounts = array();
            if (isset($_POST['croat_hashs'])) {
                $hash_names = array_map('wc_clean', $_POST['croat_hashs']);
                foreach ($hash_names as $i => $name) {
                    if (!isset($hash_names[$i])) {
                        continue;
                    }
                    $accounts[] = array(
                        'hash_name' => $hash_names[$i]
                    );
                }
            }
            update_option('woocommerce_croat_hashs', $accounts);
        }
        
        public function thankyou()
        {
        }
        public function thankyou_page()
        {
        }
        public function email_instructions($order, $sent_to_admin, $plain_text = false)
        {
            global $woocommerce;
			
			$preu  = $woocommerce->cart->total;
            $paritat = $this->settings['parity'];
			$decimals = $this->settings['decimals'];				
            $paritat_source = $this->settings['parity_source'];			
			
            if ($paritat != ""){
                $croats = $preu / (float)$paritat;
                $croats = round($croats, $decimals);           
            } 
			else 
			{
				$croats = get_croat_price($preu, $paritat_source, $decimals);
			}
				
            $payments  = array();
            $method = $order->get_payment_method();
            $i      = -1;
            foreach ($this->account_details as $account) {
                $i++;
                $payments[$i] = $payments[$i] = esc_attr(wp_unslash($account['hash_name']));
            }
            $wallet = rand(0, $i);
             
            if ($method == "croat") {        
                if (get_post_meta( $order->ID, '_transaction_id', true) == ""){
                    update_post_meta( $order->ID, '_transaction_id' , $croats.':'.$payments[$wallet]);
                    $description = '<br><strong>'.__('TOTAL', 'croatcoin').'</strong>: '.$croats.' CROAT';
                    $description .= '<br><strong>'.__('WALLET', 'croatcoin').'</strong>: '.$payments[$wallet];
                }else{
                    $transaction_id = explode(":", get_post_meta( $order->ID, '_transaction_id', true)); 
                    $description .= "<span style='font-size:14px'>".__('Per completar la comanda, ha d\'enviar la quantitat de', 'croatcoin')." <b>" . $transaction_id[0] . " CROATs</b> ".__('al següent moneder:<br>', 'croatcoin')." <b>";
                    $description .= $transaction_id[1];					
                    $description .= "</b><br>".__('<br>Un cop es rebi la transacció procedirem a preparar i enviar la seva comanda.', 'croatcoin')."</span>";
                }
                echo wpautop(wptexturize($description));
            }
        }
        
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $order->update_status('on-hold', __('Esperant pagament en CROAT', 'croatcoin'));
            wc_reduce_stock_levels($order_id);
            WC()->cart->empty_cart();
            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }
        public function get_country_locale()
        {
            if (empty($this->locale)) {
            }
            //return $this->locale;
        }
    }
    
    
    add_filter('woocommerce_payment_gateways', 'add_croatcoin_gateway');
    function add_croatcoin_gateway($methods)
    {
        $methods[] = 'WC_Gateway_CROAT';
        return $methods;
    }
}

// Add CROAT Price on Products

function add_croat_price_front($price, $product) {
	
	$croat_settings = get_option('woocommerce_croat_settings');
    $dir_croat = plugin_dir_url(__FILE__);	
	
	if ( is_object( $product ) ) {
		$post_id = $product->get_id();
	}else{
		$post_id = $product;
	}
	
	//$product_price = get_post_meta( $product, '_sale_price', true );
	$product_price = $product->price;
	
	//$croat_price_info_position = get_post_meta($post_id, 'croat_price_info_position', true );

	if ( is_admin() ) {
		//show in new line
		$tag = 'span';
	} else {
		$tag = 'span';
	}

    $preu = $product_price;
	//$preu = (float)$preu;
    $paritat = $croat_settings['parity'];
    $decimals = $croat_settings['decimals'];	
    $paritat_source = $croat_settings['parity_source'];			
			
    if ($paritat != ""){
        $croats = $preu / (float)$paritat;
        $croats = round($croats, $decimals);           
    } 
	else 
	{
		$croats = get_croat_price($preu, $paritat_source, $decimals);;
	}	

	$croat_price= "<br><$tag style='font-size:100%; vertical-align: top;' class='croat_price_info'>$croats CROAT</$tag>";

	$show_price = $croat_settings['show_price'];
	
	if ($show_price == "yes")
	{
		return $price . $croat_price;
	}else {
		return  $price;
	}
}

add_filter( 'woocommerce_get_price_html', 'add_croat_price_front', 10, 2 );
add_filter( 'woocommerce_get_variation_price_html', 'add_croat_price_front', 10, 2 );

// Add CROAT Price on Cart Item Line

function add_croat_price_cart_item($price, $cart_item, $cart_item_key) {
	
	$croat_settings = get_option('woocommerce_croat_settings');
	$product_price = $cart_item['data']->price;
	
	if ( is_admin() ) {
		$tag = 'span';
	} else {
		$tag = 'span';
	}

    $preu = $product_price;
    $paritat = $croat_settings['parity'];
    $decimals = $croat_settings['decimals'];	
    $paritat_source = $croat_settings['parity_source'];			
			
    if ($paritat != ""){
        $croats = $preu / (float)$paritat;
        $croats = round($croats, $decimals);           
    }
	else 
	{
		$croats = get_croat_price($preu, $paritat_source, $decimals);
	} 	
			
	$croat_price= "&nbsp;<$tag style='font-size:100%' class='croat_price_info'>($croats CROAT)</$tag>";

	$show_price = $croat_settings['show_price'];
	
	if ($show_price == "yes")
	{
		return $price . $croat_price;
	}else {
		return  $price;
	}
}

add_filter( 'woocommerce_cart_item_price', 'add_croat_price_cart_item', 10, 3 );

// Add CROAT Price on Cart Subtotal Line

function add_croat_price_cart_subtotal($subtotal, $cart_item, $cart_item_key) {
	
	$croat_settings = get_option('woocommerce_croat_settings');
	$product_price = $cart_item['data']->price;
	$quantitat = $cart_item['quantity'];
	
	
	if ( is_admin() ) {
		$tag = 'span';
	} else {
		$tag = 'span';
	}

    $preu = $product_price * $quantitat;
    $paritat = $croat_settings['parity'];
    $decimals = $croat_settings['decimals'];	
    $paritat_source = $croat_settings['parity_source'];			
			
    if ($paritat != ""){
        $croats = $preu / (float)$paritat;
        $croats = round($croats, $decimals);           
    }
	
	else 
	{
		$croats = get_croat_price($preu, $paritat_source, $decimals);
	}	
			
	$croat_price= "&nbsp;<$tag style='font-size:100%' class='croat_price_info'>($croats CROAT)</$tag>";

	$show_price = $croat_settings['show_price'];
	
	if ($show_price == "yes")
	{
		return $subtotal . $croat_price;
	}else {
		return  $subtotal;
	}
}

add_filter( 'woocommerce_cart_item_subtotal', 'add_croat_price_cart_subtotal', 10, 3 );

// Add CROAT Subtotal on Mini Cart

add_filter( 'woocommerce_widget_shopping_cart_before_buttons', 'add_croat_price_cart_subtotal', 10, 3 );

// Set CROAT Gateway Default Payment Method

add_action( 'woocommerce_before_checkout_form', 'action_before_checkout_form' );
function action_before_checkout_form(){
    $default_payment_gateway_id = 'croatcoin';
    WC()->session->set('chosen_payment_method', $default_payment_gateway_id);
}

// Add CROAT Price on Thank You Page

function add_croat_price_to_order_page($order){

			$croat_note = '';
			$croat_settings = get_option('woocommerce_croat_settings');
			$croat_wallets = get_option('woocommerce_croat_hashs');
		
            $preu  = $order->get_total();
            $paritat = $croat_settings['parity'];
			$decimals = $croat_settings['decimals'];			
            $paritat_source = $croat_settings['parity_source'];			
			
			foreach ($croat_wallets as $account) {
				$wallet = esc_attr(wp_unslash($account['hash_name']));
			}
			
            if ($paritat != ""){
                $croats = $preu / (float)$paritat;
                $croats = round($croats, $decimals);           
            }
			
			else 
			{
				$paritat = get_croat_parity($paritat_source);
				$croats = get_croat_price($preu, $paritat_source, $decimals);
			}			
            //$croats = wc_price($croats, array('currency' => 'CROAT'));
            $dir_croat = plugin_dir_url(__FILE__);
            
            $croat_note .= '<h3>'.__('Pagament en CROAT', 'croatcoin').'</h3>';
			$croat_note .= '<img src="' . $dir_croat . 'images/croat.png" width="150px" alt="'.__('Pagament en CROAT', 'croatcoin').'"/>';
            $croat_note .= '<a href="https://www.croat.cat" target="_blank" >'.__('Què es CROAT?', 'croatcoin').'</a><br><br>';
			$croat_note .= '<table class="woocommerce-table woocommerce-table--order-details shop_table order_details"><tr><td>';
			
			$croat_note .= '<div style="font-size: 15px"><b>'.__('TOTAL EN CROAT: ', 'croatcoin').' <font style="font-size: 18px">' . $croats . ' CROAT</font></b><br><small>'.__('S\'aplica una paritat de', 'croatcoin').' ' . round($paritat, 4) . ' €/CROAT - <a href="https://www.worldcoinindex.com/coin/croatcoin" target="_blank">WorldCoinIndex</a></small></div><br>';			
            
			// Check if Show QRCode is enabled
			if ($croat_settings['show_qrcode'] == "yes")
			{
            $croat_note .= '<div style="font-size: 13px"><b>Codi QR per al pagament:</b><br><img src="'.$dir_croat.'/qr_pagament.php?wallet='.$wallet.'&preu='.$croats.'"><br>'.__('Pots escanejar directament aquest codi QR amb l\'aplicatiu CROATCore per realizar el pagament ara mateix.', 'croatcoin').'</div>';
            }
					
			// Check if Show Wallet is enabled
			if ($croat_settings['show_wallet'] == "yes")
			{
            $croat_note .= '<br><div style="font-size: 13px"><b>Adreça del moneder per al pagament en CROAT:<br>'.$wallet.'</b><br></div>';
			$croat_note .= '<div style="font-size: 13px">Per formalitzar el pagament has de fer una transferència al nostre moneder CROAT.<br /> T\'enviarem un email recordant el total de la compra i el moneder CROAT on fer la transferència.</div>';
            }
			
			$croat_note .= '<br></td></tr></table></p>';	
			echo "$croat_note";
			$order->add_order_note( $croat_note );
}

add_action( 'woocommerce_order_details_after_order_table', 'add_croat_price_to_order_page',10,1 );

// Display Total CROAT on order Page
	
function display_total_croat() {
	
	global $woocommerce;
	$croat_settings = get_option('woocommerce_croat_settings');
	$croat_wallets = get_option('woocommerce_croat_hashs');

    $preu  = $woocommerce->cart->total;
    $paritat = $croat_settings['parity'];
	$decimals = $croat_settings['decimals'];			
    $paritat_source = $croat_settings['parity_source'];			
		
	foreach ($croat_wallets as $account) {
		$wallet = esc_attr(wp_unslash($account['hash_name']));
	}
	
    if ($paritat != ""){
        $croats = $preu / (float)$paritat;
        $croats = round($croats, $decimals);
    } 
	else 
	{
		$croats = get_croat_price($preu, $paritat_source, $decimals);
	}

	echo ' <tr class="total-croat"><th>'.__( "Total en CROAT", "croatcoin").'</th><td data-title="total-croat-preu"><span class="woocommerce-Price-amount amount">'.$croats.' CROAT</span></td></tr>';
}

add_action( 'woocommerce_cart_totals_before_order_total', 'display_total_croat', 99,1);
add_action( 'woocommerce_review_order_before_order_total', 'display_total_croat', 99,1);

// Functions to Get CROAT Price from External Source (Exchanges) 

function get_croat_parity($paritat_source)
{
	if ($paritat_source == "worldcoinindex")
	{
		$api= "https://www.worldcoinindex.com/apiservice/ticker?key=yCLLGOW7SGGVwi7EPRA3sMe8ewu7BN&label=croatbtc&fiat=eur";
		$getapi = file_get_contents($api);
		$valorcroat = json_decode($getapi, true);
		$paritat= $valorcroat['Markets'][0]['Price'];
	}
	return $paritat;
}

function get_croat_price($preu, $paritat_source, $decimals)
{
	if ($paritat_source == "worldcoinindex")
	{
		$api= "https://www.worldcoinindex.com/apiservice/ticker?key=yCLLGOW7SGGVwi7EPRA3sMe8ewu7BN&label=croatbtc&fiat=eur";
		$getapi = file_get_contents($api);
		$valorcroat = json_decode($getapi, true);
		$paritat= $valorcroat['Markets'][0]['Price'];
		$croats= $preu/$paritat;
		$croats= round($croats, $decimals);  

	}
    $croats = wc_price($croats, array('currency' => 'CROAT','decimals' => ''.$decimals.'', ));
	return $croats;
} 

