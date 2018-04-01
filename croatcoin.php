<?php
/*
Plugin Name: CROATCoin WooCommerce Gateway
Plugin URI: https://github.com/croatproject/WooCommerce
Description: Passarela de pagament amb CROAT per Woocommerce
Version: 0.1
Author: Croat Project Team
Author URI: https://croat.cat/
*/

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
            $this->icon               = apply_filters('woocommerce_bacs_icon', '');
            $this->has_fields         = false;
            $this->method_title       = __('CROAT', 'woocommerce');
            $this->method_description = __('Pagaments amb CROAT.', 'woocommerce');
            
            
            $this->init_form_fields();
            $this->init_settings();
            
            
            $this->title        = $this->get_option('title');
            $this->description  = $this->get_option('description');
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
                    'title' => __('Enable/Disable', 'woocommerce'),
                    'type' => 'checkbox',
                    'label' => __('Activar pagaments amb CROATCoin', 'woocommerce'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('This controls the title which the user sees during checkout.', 'woocommerce'),
                    'default' => __('CROAT', 'woocommerce'),
                    'desc_tip' => true
                ),
                
                'description' => array(
                    'title' => __('Description', 'woocommerce'),
                    'type' => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'woocommerce'),
                    'default' => __('Realitza el pagament directament amb CROATs.', 'woocommerce'),
                    'desc_tip' => true
                ),
                
                'parity' => array(
                    'title' => __('Paritat', 'woocommerce'),
                    'type' => 'text',
                    'description' => __('Paritat dels productes en € cap a CROATs', 'woocommerce'),
                    'default' => __('0.5', 'woocommerce'),
                    'desc_tip' => true
                ),
                
                'account_details' => array(
                    'type' => 'account_details',
                    'description' => __('S\'aconsella posar diversos moneders per rebre el pagament.', 'woocommerce'),
                    'desc_tip' => true
                )
            );
            
        }
        
        public function get_icon()
        {
            $icon_html = '';
            $paritat = $this->settings['parity'];
            
            global $woocommerce;
            $euros  = $woocommerce->cart->total;
            $croats = $euros / (float)$paritat;
            $croats = round($croats, 2);
            
            $dir_croat = plugin_dir_url(__FILE__);
            
            $icon_html .= '<img src="' . $dir_croat . '/croat.png" alt="Marca de acceptació de CROAT"/> ';
            $icon_html .= '<a href="https://www.croat.cat" target="_blank" >¿Què es CROAT?</a>';
            
            $icon_html .= '  <div style="font-size: 13px">Total en CROATS: <b>' . $croats . ' CROATS</b>, aplicant una Paritat de ' . $paritat . ' €/CROAT</div>';
            
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
            _e('Moneders de pagament en CROATs', 'woocommerce');
?>: 
   </th>
   <td class="forminp" id="bacs_accounts">
      <table class="widefat wc_input_table sortable" cellspacing="0">
         <thead>
            <tr>
               <th class="sort">&nbsp;</th>
               <th><?php _e('Direcció del Moneder', 'woocommerce');?></th>
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
                           <td><input type="text" value="' . esc_attr(wp_unslash($account['hash_name'])) . '" name="croat_hashs[' . $i . ']" /></td>';
                    
                    
                }
            }
            
?>
        </tbody>
         <tfoot>
            <tr>
               <th colspan="2"><a href="#" class="add button"><?php
            _e('+ Afegir moneder', 'woocommerce');
?></a> <a href="#" class="remove_rows button"><?php
            _e('Esborrar seleccionats', 'woocommerce');
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
        echo "hola2";
        }
        public function thankyou_page()
        {
        echo "hola";
        }
        public function email_instructions($order, $sent_to_admin, $plain_text = false)
        {
            global $woocommerce;
            $paritat = $this->settings['parity'];
            
            $euros  = $woocommerce->cart->total;
            $croats = $euros / (float)$paritat;
            $croats = round($croats, 2);
            $payments  = array();
            $method = $order->get_payment_method();
            $i      = -1;
            foreach ($this->account_details as $account) {
                $i++;
                $payments[$i] = $payments[$i] = esc_attr(wp_unslash($account['hash_name']));
            }
            $wallet = rand(0, $i);
             
            if ($method == "croat") { 
                
                $description = "<span style='font-size:14px'>Per completar la comanda, ha d'enviar la quantitat de <b>" . $croats . " CROATS</b> al següent moneder: <b>";
                $description .= $payments[$wallet];
                $description .= "</b><br>Un cop es rebi la transacció s'enviarà la comanda.</span>";
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