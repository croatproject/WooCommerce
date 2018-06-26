<?php
/*
Plugin Name: CROATCoin WooCommerce Gateway
Plugin URI: https://github.com/croatproject/WooCommerce
Description: Passarela de pagament amb CROAT per Woocommerce
Version: 0.5
Author: Croat Project Team
Author URI: https://croat.cat/
*/

add_action('plugins_loaded', 'load_text');
add_action('plugins_loaded', 'croatcoin_init'); 


function load_text() {
	$domain = 'croatcoin';
	$locale = apply_filters( 'plugin_locale', get_locale(), $domain );
	if ( $loaded = load_textdomain( $domain, trailingslashit( WP_LANG_DIR ) . $domain . '/' . $domain . '-' . $locale . '.mo' ) ) {
		return $loaded;
	} else {
		load_plugin_textdomain( $domain, FALSE, basename( dirname( __FILE__ ) ).'/lang/'  );
	}
}

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
            $this->icon               = apply_filters('woocommerce_bacs_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __('CROAT', 'woocommerce');
            $this->method_description = __('Pagaments amb CROAT.', 'croatcoin');
            
            
            $this->init_form_fields();
            $this->init_settings();
            
            
            $this->title        = __('CROAT', 'woocommerce');
            $this->description  = __('Realitza el pagament directament amb CROATs, t\'enviarem un email amb el total i el moneder on fer l\'ingrés.', 'croatcoin');
            $this->instructions = $this->get_option('instructions');
            $this->parity       = $this->get_option('parity');
            
            
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
            
            //add_action( 'woocommerce_thankyou_bacs', array( $this, 'thankyou_page' ) );
            
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
                                
                'parity' => array(
                    'title' => __('Paritat', 'croatcoin'),
                    'type' => 'text',
                    'description' => __('Paritat dels productes en € cap a CROATs. Deixar en blanc per fer servir preu de mercat.', 'croatcoin'),
                    'default' => '',
                    'desc_tip' => true
                ),
                
                'account_details' => array(
                    'type' => 'account_details',
                    'description' => __('S\'aconsella posar diversos moneders per rebre el pagament.', 'croatcoin'),
                    'desc_tip' => true
                )
            );
            
        }
        
        public function get_icon()
        {  
            $icon_html = '';
            global $woocommerce;
            $euros  = $woocommerce->cart->total;
            $paritat = $this->settings['parity'];
            
            
            if ($paritat != ""){
                $croats = $euros / (float)$paritat;
                $croats = round($croats, 4);           
            } else {
              $api= "https://www.worldcoinindex.com/apiservice/ticker?key=yCLLGOW7SGGVwi7EPRA3sMe8ewu7BN&label=croatbtc&fiat=eur";
              $getapi = file_get_contents($api);
              $valorcroat = json_decode($getapi, true);
              $paritat= $valorcroat['Markets'][0]['Price'];
              $croats= $euros/$paritat;
              $croats= round($croats, 4);    
            }
             
            //wp_dequeue_script( 'wc-checkout' );
            
            $dir_croat = plugin_dir_url(__FILE__);
            
            $icon_html .= '<img src="' . $dir_croat . '/croat.png" alt="'.__('Pagament en CROATS', 'croatcoin').'"/> ';
            $icon_html .= '<a href="https://www.croat.cat" target="_blank" >'.__('Què es CROAT?', 'croatcoin').'</a>';
            
            $icon_html .= '  <div style="font-size: 13px">'.__('Total en CROATS:', 'croatcoin').' <b>' . $croats . ' CROATS</b>, '.__('aplicant una paritat de', 'croatcoin').' ' . round($paritat, 4) . ' €/CROAT - <small><a href="https://www.worldcoinindex.com/coin/croatcoin" target="_blank">WorldCoinIndex</a></small></div>';
            
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
                           <td><input type="text" value="' . esc_attr(wp_unslash($account['hash_name'])) . '" name="croat_hashs[' . $i . ']" size="120" /></td>';
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
            $euros  = $woocommerce->cart->total;
            $paritat = $this->settings['parity'];
           
            if ($paritat != ""){
                $croats = $euros / (float)$paritat;
                $croats = round($croats, 4);           
            } else {
              $api= "https://www.worldcoinindex.com/apiservice/ticker?key=yCLLGOW7SGGVwi7EPRA3sMe8ewu7BN&label=croatbtc&fiat=eur";
              $getapi = file_get_contents($api);
              $valorcroat = json_decode($getapi, true);
              $paritat= $valorcroat['Markets'][0]['Price'];
              $croats= $euros/$paritat;
              $croats= round($croats, 4);    
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
                    $description = '<br><strong>'.__('TOTAL', 'croatcoin').'</strong>: '.$croats.' Croats';
                    $description .= '<br><strong>'.__('WALLET', 'croatcoin').'</strong>: '.$payments[$wallet];
                }else{
                    $transaction_id = explode(":", get_post_meta( $order->ID, '_transaction_id', true)); 
                    $description .= "<span style='font-size:14px'>".__('Per completar la comanda, ha d\'enviar la quantitat de', 'croatcoin')." <b>" . $transaction_id[0] . " CROATS</b> ".__('al següent moneder:', 'croatcoin')." <b>";
                    $description .= $transaction_id[1];
                    $description .= "</b><br>".__('Un cop es rebi la transacció s\'enviarà la comanda.', 'croatcoin')."</span>";
                }
                echo wpautop(wptexturize($description));
            }
        }
        
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $order->update_status('on-hold', __('Awaiting BACS payment', 'woocommerce'));
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
