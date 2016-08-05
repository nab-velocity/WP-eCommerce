<?php
                                                                                
/**
 * Plugin Name: WP eVelocity
 * Plugin URI: http://nabvelocity.com/
 * Description: A plugin that provides the easy and flexible payment via 
 * velocity gateway embed with Wp eCommerce pluging. 
 * Version: 1.0.0
 * Author: Velocity Team
 * Author URI: http://nabvelocity.com/
 * Text Domain: 
 * */
require_once(realpath(dirname(__FILE__)) . '/classes/helper.php');
require_once(realpath(dirname(__FILE__)) . '/classes/form.php');
require_once(realpath(dirname(dirname(__FILE__))) . '/wp-e-commerce/wpsc-core/wpsc-functions.php');
require_once(realpath(dirname(dirname(__FILE__))) . '/wp-e-commerce/wpsc-includes/merchant.class.php');
require_once(realpath(dirname(dirname(__FILE__))) . '/wp-e-commerce/wpsc-includes/purchaselogs.class.php');
require_once(realpath(dirname(__FILE__)) . '/sdk/Velocity.php');

register_activation_hook(__FILE__, 'eVelocity_register');

/*
 * here we get the current version of database version
 * @param null
 * return string
 */

function get_db_version() {
    global $wpdb;
    $row = $wpdb->get_results("SELECT VERSION() as VERSION");
    return $row[0]->VERSION;
}

/*
 * This function call at the time of plugin activation and also create one 
 * velocity custom table in datbase.
 * and check the wpecommerce dependency.
 * Call by register activation hook
 * @param null
 * return null
 */

function eVelocity_register() {

    // Check firt Wp eCommerce plugin is active or not.
    if (!is_plugin_active('wp-e-commerce/wp-shopping-cart.php') and current_user_can('activate_plugins')) {
        // Stop activation redirect and show error
        wp_die('Sorry, but this plugin requires the Wp eCommerce plugin to be installed and active before install and active the Wp eVelocity plugin. <br><a href="' . admin_url('plugins.php') . '">&laquo; Return to Plugins</a>');
    }
    global $wpdb;
    $velocity_db_version = get_db_version();
    $velocity_transaction_table = $wpdb->prefix . 'wpsc_velocity_transactions';

    /*
     * We'll set the default character set and collation for this table.
     * If we don't do this, some characters could end up being converted 
     * to just ?'s when saved in our table.
     */
    $charset_collate = '';

    if (!empty($wpdb->charset)) {
        $charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
    }

    if (!empty($wpdb->collate)) {
        $charset_collate .= " COLLATE {$wpdb->collate}";
    }

    // table structure define here.
    $sql = "CREATE TABLE $velocity_transaction_table (
          id mediumint(9) NOT NULL AUTO_INCREMENT,
          transaction_id varchar(220) DEFAULT '' NOT NULL,
          transaction_status varchar(100) DEFAULT '' NOT NULL,
          order_id varchar(20) DEFAULT '' NOT NULL,
          request_obj text NOT NULL,
          response_obj text NOT NULL,
          UNIQUE KEY id (id)
     ) $charset_collate;";

    // include wordpress file to work database task.
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    add_option('jal_db_version', $velocity_db_version);
    
    wp_schedule_event(time(), 'daily', 'cron_velocity_daily');
}

add_action('cron_velocity_daily', 'remove_log_daily');

/*
* This function call to remove the log data daily
* Call by action hook cron_velocity_daily.
* @param null
* return null
*/
function remove_log_daily() {

    $filep = realpath(dirname(__FILE__)) . '/log/payment.log';
    $filer = realpath(dirname(__FILE__)) . '/log/refund.log';
    $file = realpath(dirname(__FILE__)) . '/log/';
    $t = time();
    
    // create backup payment log daily by date
    $backupp = file_get_contents($filep);
    file_put_contents($file . 'payment_' . date("Y-m-d",$t) . '.log', $backupp);
    
    // remove payment log
    $myfile = fopen($filep, "w") or die("Unable to open file!");
    fwrite($myfile, '');
    fclose($myfile);
    
    // create backup refund log daily by date
    $backupp = file_get_contents($filer);
    file_put_contents($file . 'refund_' . date("Y-m-d",$t) . '.log', $backupp);

    // remove refund log.
    $myfile = fopen($filer, "w") or die("Unable to open file!");
    fwrite($myfile, '');
    fclose($myfile);
    
}

/**
 *  WpeVelocity class
 *  This dependent plugin for payment use the Wp eCommerce core 
 *  class to provide the payment functionality via velocity gateway.
 * 
 *  @category payment
 *  @package wp-e-velocity
 *  @author velocity
 *  @since 1.0.0
 */
class WpeVelocity extends wpsc_merchant {

    private $version;
    private $pluginURL;

    public function __construct() {

        $this->version = WpeVelocity_Helper::getVersion();
        $this->pluginURL = plugins_url('', __FILE__);
        $this->paymentMethods = 'wpevelocity';

        add_action('init', array($this, 'setVelocityPaymentMethod'));
        add_action('wpsc_submit_checkout', array($this, 'checkOut'));
        add_action('wpsc_purchlogitem_links_start', array($this, 'adminOrderAccess'));
        add_action('admin_menu', array($this, 'addMenuItem'));
        add_action('admin_init', array($this, 'initSettings'));
        add_action('wpsc_inside_shopping_cart', array($this, 'setPaymentFromUrl'));
        add_filter('wpsc_set_purchlog_statuses', array($this, 'velocity_add_wpec_sales_status'));

        if (!is_admin()) {
            wp_enqueue_script('wpevelocity', $this->pluginURL . '/assets/js/wpevelocity.js', array('jquery'), '1.0');
        }
        
        if (is_admin()) {
            wp_enqueue_script('wpevelocityadmin', $this->pluginURL . '/assets/js/wpevelocityadmin.js', array('jquery'), '1.0');
        }
        
        if (WpeVelocity_Helper::isWpeVelocityPage()) {
            wp_enqueue_style('wpevelocity', $this->pluginURL . '/assets/css/wpevelocity.css', array(), '1.0');
        }

        if ((!empty($this->settings['wpevelocity_identitytoken']) && 
                (!empty($this->settings['wpevelocity_workflowid'])) 
            && (!empty($this->settings['wpevelocity_applicationprofileid'])) && 
                (!empty($this->settings['wpevelocity_merchantprofileid'])))) {
            $this->updateInternal = true;
        }
    }

    /*
     * This is display the refund link in admin order for refund request.
     * Call by action hook 'wpsc_purchlogitem_links_start'
     * @param null
     * return null
     */

    public function adminOrderAccess() {
        global $purchlogitem;

        if ($purchlogitem->extrainfo->gateway == 'wpevelocity_cc' && $purchlogitem->extrainfo->processed != 7) {
            echo '<a href="options-general.php?page=wpevelocity-refund&id=' 
            . base64_encode(json_encode($purchlogitem)) . '"><img src="' 
            . plugins_url("assets/images/return.png", __FILE__) 
            . '" style="width:20px; height:20px;margin-right:10px;" />Velocity Online Refund</a>';
        } else if ($purchlogitem->extrainfo->gateway == 'wpevelocity_cc' && $purchlogitem->extrainfo->processed == 7) {
            echo '<span style="opacity:.5;pointer-events: none;"><img src="' 
            . plugins_url("assets/images/return.png", __FILE__) 
            . '" style="width:20px; height:20px;margin-right:10px;" />Velocity Online Refund</span>';
        }
    }

    /*
     * This function create new order status refund payment for velocity order.
     * Call by filter hook 'wpsc_set_purchlog_statuses'
     * @param array all order statuses array's
     * return array after add the new order status array
     */

    function velocity_add_wpec_sales_status($statuses) {

        $new_statuses = array(
            array(
                'internalname'   => 'order_refunded',
                'label'          => 'Payment Refunded',
                'is_transaction' => true,
                'order'          => 7,
            ),
        );

        return array_merge($statuses, $new_statuses);
    }

    /*
     * This function create admin configuration form field for velocity credential.
     * Call by action hook 'admin_init'
     * @param null
     * return null
     */

    public function initSettings() {
        register_setting('wpevelocity', 'eg_setting_name');
        register_setting('wpevelocity_options', 'wpevelocity_options', array('WpeVelocity_Form', 'validateSettings'));

        // Add Main Settings section
        add_settings_section('wpevelocity_main', __('Velocity Configuration', 'wpevelocity'), false, 'wpevelocity_config');

        // Add Main Settings fields
        add_settings_field('wpevelocity_identitytoken', __('Identity Token', 'wpevelocity'), array('WpeVelocity_Form', 'textareaSettings'), 'wpevelocity_config', 'wpevelocity_main', array("wpevelocity_identitytoken"));
        add_settings_field('wpevelocity_workflowid', __('Work Flow Id/Service Id', 'wpevelocity'), array('WpeVelocity_Form', 'textfieldSettings'), 'wpevelocity_config', 'wpevelocity_main', array("wpevelocity_workflowid"));
        add_settings_field('wpevelocity_applicationprofileid', __('Application Profile Id', 'wpevelocity'), array('WpeVelocity_Form', 'textfieldSettings'), 'wpevelocity_config', 'wpevelocity_main', array("wpevelocity_applicationprofileid"));
        add_settings_field('wpevelocity_merchantprofileid', __('Merchant Profile Id', 'wpevelocity'), array('WpeVelocity_Form', 'textfieldSettings'), 'wpevelocity_config', 'wpevelocity_main', array("wpevelocity_merchantprofileid"));
        add_settings_field('wpevelocity_testmode', __('Test/Production Mode', 'wpevelocity'), array('WpeVelocity_Form', 'selectOptionSettings'), 'wpevelocity_config', 'wpevelocity_main', array("wpevelocity_testmode"));
    }

    /*
     * This function add admin configuration and refund page with menu links.
     * Call by action hook 'admin_menu'
     * @param null
     * return null
     */

    public function addMenuItem() {
        add_options_page('WpeVelocity', 'WpeVelocity Configuration', 'manage_options', 'wpevelocity-configuration', array($this, 'renderAdmin'));
        add_options_page('WpeVelocityRefund', '', 'manage_options', 'wpevelocity-refund', array($this, 'renderRefund'));
    }

    /*
     * This function render admin configuration page template.
     * Call by method 'add_options_page' of 'addMenuItem'.
     * @param null
     * return null
     */

    public function renderAdmin() {
        include(realpath(dirname(__FILE__)) . '/templates/admin.php');
    }

    /*
     * This function render admin payment refund page template.
     * Call by method 'add_options_page' of 'addMenuItem'.
     * @param null
     * return null
     */

    public function renderRefund() {
        include(realpath(dirname(__FILE__)) . '/templates/refund.php');
    }

    /*
     * This function validate the card type coressponding to card number.
     * 
     * @param string credit card number
     * @param string credit card type
     * return boolean(no error) or string(error) 
     */

    private function card_validate($cardno, $cardtype) {
        $exp = '';
        $error = '';
        if ($cardtype == 'MasterCard') {
            $exp .= "/^(?:5[1-5][0-9]{14})$/";
            $error .= "Not a valid Mastercard number!";
        } elseif ($cardtype == 'Visa') {
            $exp .= "/^(?:4[0-9]{12}(?:[0-9]{3})?)$/";
            $error .= "Not a valid Visa credit card number!";
        } elseif ($cardtype == 'Discover') {
            $exp .= "/^(?:6(?:011|5[0-9][0-9])[0-9]{12})$/";
            $error .= "Not a valid Discover card number!";
        } elseif ($cardtype == 'AmericanExpress') {
            $exp .= "/^(?:3[47][0-9]{13})$/";
            $error .= "Not a valid Amercican Express credit card number!";
        } else {
            return "Card not supported to this plugin.";
        }
        if (preg_match($exp, $cardno)) {
            return true;
        } else {
            return $error;
        }
    }

    /*
     * This function process the payment via velocity gateway after checkout.
     * Call by action hook 'wpsc_submit_checkout'
     * @param null
     * return null
     */

    public function checkOut() {
        global $wpsc_cart, $wpdb;

        // Check if payment is done by WpeVelocity
        if (!isset($_POST['custom_gateway']) || $_POST['custom_gateway'] !== 'wpevelocity_cc')
            return false;

        // validate the form detail
        if (!isset($_POST['velocity_card_type']) || $_POST['velocity_card_type'] == '') {
            $this->set_error_message("Please select the card type first");
            $this->return_to_checkout();
        }

        $cardv = $this->card_validate($_POST['velocity_card_number'], $_POST['velocity_card_type']);

        if (!isset($_POST['velocity_card_number']) || $_POST['velocity_card_number'] == '') {
            $this->set_error_message("enter the card number!");
            $this->return_to_checkout();
        } elseif (!is_bool($cardv)) {
            $this->set_error_message($cardv);
            $this->return_to_checkout();
        }

        if (!isset($_POST['velocity_card_name']) || $_POST['velocity_card_name'] == '') {
            $this->set_error_message("enter the name of card owner");
            $this->return_to_checkout();
        }

        if (!isset($_POST['velocity_cvv']) || $_POST['velocity_cvv'] == '') {
            $this->set_error_message("enter the card cvv");
            $this->return_to_checkout();
        } elseif($_POST['velocity_card_type'] == 'AmericanExpress' && strlen($_POST['velocity_cvv']) < 4) {
            $this->set_error_message("enter the 4 digits valid cvv");
            $this->return_to_checkout();
        } elseif($_POST['velocity_card_type'] != 'AmericanExpress' && strlen($_POST['velocity_cvv']) != 3) {
            $this->set_error_message("enter the 3 digits valid cvv");
            $this->return_to_checkout();
        } elseif (!is_numeric($_POST['velocity_cvv'])) {
            $this->set_error_message("enter the valid numeric cvv");
            $this->return_to_checkout();
        }

        $gateway = $_POST['custom_gateway'];
        $pmCode = get_option($gateway . "_code");

        try {

            $v_obj = new StdClass();

            // Get the grand total of order
            $v_obj->amount = intval($wpsc_cart->total_price * 100);
            $v_obj->country = $wpsc_cart->selected_country;

            // Get the used currency for the shop
            $currency = $wpdb->get_row("SELECT `code` FROM `" . WPSC_TABLE_CURRENCY_LIST . "` WHERE `id`='" . get_option('currency_type') . "' LIMIT 1");
            $v_obj->currency = $currency->code;

            // Get the Wordpress language and adjust format so Velocity accepts it.
            $language_locale = get_bloginfo('language');
            $v_obj->language = strtoupper(substr($language_locale, 0, 2));

            // Fetch the issuer   
            $issuer = (isset($_POST[$pmCode . '_issuer'])) ? $_POST[$pmCode . '_issuer'] : 'DEFAULT';

            // Get the order detail form purchase log.
            $purchlogitem = new wpsc_purchaselogs_items($wpsc_cart->log_id);


            $totalp = $purchlogitem->extrainfo->totalprice;

            $velocity_credential = get_option('wpevelocity_options');

            if ($velocity_credential['wpevelocity_testmode'] == 'test')
                $isTestAccount = 'true';
            else
                $isTestAccount = 'false';

            $velocityProcessor = new VelocityProcessor($velocity_credential['wpevelocity_applicationprofileid'], $velocity_credential['wpevelocity_merchantprofileid'], $velocity_credential['wpevelocity_workflowid'], $isTestAccount, $velocity_credential['wpevelocity_identitytoken']);

            $avsData = array(
                'Street'        => $purchlogitem->userinfo['billingaddress']['value'],
                'City'          => $purchlogitem->userinfo['billingcity']['value'],
                'StateProvince' => '',
                'PostalCode'    => $purchlogitem->userinfo['billingpostcode']['value'],
                'Country'       => 'USA'
            );

            $cardData = array(
                'cardtype'   => $_POST['velocity_card_type'],
                'pan'        => $_POST['velocity_card_number'],
                'expire'     => $_POST['velocity_expiry_month'] . $_POST['velocity_expiry_year'],
                'cvv'        => $_POST['velocity_cvv'],
                'track1data' => '',
                'track2data' => ''
            );

            $verifydata = array(
                'amount'       => $totalp,
                'avsdata'      => $avsData,
                'carddata'     => $cardData,
                'entry_mode'   => 'Keyed',
                'IndustryType' => 'Ecommerce',
                'Reference'    => 'xyt',
                'EmployeeId'   => '11'
            );
            
            if (isset($purchlogitem->userinfo['billingemail']['value']) && !empty($purchlogitem->userinfo['billingemail']['value']))
                $verifydata['email'] = $purchlogitem->userinfo['billingemail']['value'];
            
            if (isset($purchlogitem->userinfo['billingphone']['value']) && !empty($purchlogitem->userinfo['billingphone']['value']))
                $verifydata['phone'] = $purchlogitem->userinfo['billingphone']['value'];
            
            $verifyxml = VelocityXmlCreator::verifyXML($verifydata);  // got Verify xml object.
            $verifyrequest = $verifyxml->saveXML();  
            
            // save the verify request xml into log file
            WpeVelocity_Helper::islog(serialize($verifyrequest),'P');
            
            $response = $velocityProcessor->verify($verifydata);
            
            // save the verify response into log file
            WpeVelocity_Helper::islog(serialize($response),'P');
            
            unset($verifydata['carddata']);
            $authandcapdata = array_merge($verifydata, array('token'    => $response['PaymentAccountDataToken'],
                                                             'order_id' => $wpsc_cart->log_id)
                                );

            if (is_array($response) && isset($response['Status']) && $response['Status'] == 'Successful') {

                // got authorizeandcapture xml request object. 
                $xml = VelocityXmlCreator::authorizeandcaptureXML($authandcapdata);

                $req = $xml->saveXML();
                $obj_req = serialize($req);
                
                // save the authandcap request xml into log file
                WpeVelocity_Helper::islog($obj_req,'P');
                
                // Request for the authrizeandcapture transaction
                $cap_response = $velocityProcessor->authorizeAndCapture($authandcapdata);
                
                // save the authandcap response into log file
                WpeVelocity_Helper::islog(serialize($cap_response),'P');

                if (is_array($cap_response) && isset($cap_response['Status']) && $cap_response['Status'] == 'Successful') {

                    $orderID = $wpsc_cart->log_id;

                    // Insert payment into velocity table
                    $table_name = $wpdb->prefix . "wpsc_velocity_transactions";
                    $wpdb->insert($table_name, array('transaction_id' => $cap_response['TransactionId'], 'transaction_status' => $cap_response['TransactionState'], 'order_id' => $orderID, 'request_obj' => $obj_req, 'response_obj' => serialize($cap_response)));

                    $this->purchase_id = $orderID;
                    $v_order = $wpdb->get_row("SELECT * FROM $table_name WHERE `order_id` = $orderID");
                    $order = $wpdb->get_row("SELECT * FROM `" . WPSC_TABLE_PURCHASE_LOGS . "` WHERE id = $orderID");

                    // update the coupon status.
                    $coupon_code = $purchlogitem->extrainfo->discount_data; 
                    $wpdb->update(
                        WPSC_TABLE_COUPON_CODES, 
                        array(
                            'is-used' => 1,
                            'active' => 0
                        ), 
                        array(
                            'coupon_code' => $coupon_code,
                            'use-once' => 1
                            ), 
                        array(
                            '%d',
                            '%d'
                        ), 
                        array(
                            '%s',
                            '%d'
                        )
                    );
                    
                    set_transient("{$order->sessionid}_pending_email_sent", true, 60 * 60 * 12);
                    set_transient("{$order->sessionid}_receipt_email_sent", true, 60 * 60 * 12);
                    $processed = $this->getEcommerceStatus(($v_order->transaction_status));
                    $this->set_transaction_details($v_order->transaction_id, $processed);
                    $this->set_authcode($cap_response['ApprovalCode']);
                    transaction_results($order->sessionid, true, $v_order->transaction_id);
                    $this->go_to_transaction_results($order->sessionid);
                } else if (is_array($cap_response) && (isset($cap_response['Status']) && $cap_response['Status'] != 'Successful')) {
                    $this->set_error_message($cap_response["StatusMessage"]);
                    $this->return_to_checkout();
                } else if (is_string($cap_response)) {
                    $this->set_error_message($cap_response);
                    $this->return_to_checkout();
                } else {
                    $this->set_error_message("Unknow issue occured please contact admin.");
                    $this->return_to_checkout();
                }
            } else if (is_array($response) && (isset($response['Status']) && $response['Status'] != 'Successful')) {
                $this->set_error_message($response["StatusMessage"]);
                $this->return_to_checkout();
            } else if (is_string($response)) {
                $this->set_error_message($response);
                $this->return_to_checkout();
            } else {
                $this->set_error_message("Unknow issue occured please contact admin.");
                $this->return_to_checkout();
            }
        } catch (Exception $e) {
            $this->set_error_message($e->getMessage());
            $this->return_to_checkout();
        }
    }

    /*
     * This function set payment form url.
     * Call by action hook 'wpsc_inside_shopping_cart'
     * @param null
     * return null
     */

    public function setPaymentFromUrl() {
        
        echo '<input type="hidden" id="vpayurl" value="' . plugins_url("templates/cardform.php", __FILE__) . '" />';
        wp_enqueue_style('wpevelocityform', $this->pluginURL . '/assets/css/wpevelocityform.css', array(), '1.0');
        
    }
    
    /*
     * This function return the current order status number.
     * 
     * @param string current order status
     * return integer order status number
     */

    public function getEcommerceStatus($status) {
        if ($status == WpeVelocity_StatusCode::ERROR)
            return 6; // 'Payment Declined'

        if ($status == WpeVelocity_StatusCode::OPEN)
            return 2; // 'Order Received'

        if ($status == WpeVelocity_StatusCode::SUCCESS)
            return 3; // 'Accepted Payment'

        if ($status == WpeVelocity_StatusCode::REFUND)
            return 7; // 'Refund Payment'
        return 6;
    }

    /*
     * This function display our velocity payment option.
     * Call by action hook 'init'
     * @param null
     * return null
     */

    public function setVelocityPaymentMethod() {

        global $nzshpcrt_gateways, $num, $gateway_checkout_form_fields, $wpdb, $wpsc_cart;

        $gatewayNames = get_option('payment_gateway_names');

        if (!$this->paymentMethods)
            return;

        $num++;

        $nzshpcrt_gateways[$num] = array(
            'name'                 => 'wpevelocity_cc',
            'is_exclusive'         => true,
            'payment_type'         => 'velocity_credit_card',
            'supported_currencies' => array('currency_list' => array('USD')),
            'payment_gateway'      => 'nabvelocity',
            'internalname'         => 'wpevelocity_cc',
            'display_name'         => 'Credit Card Via Velocity'
        );
        add_option($nzshpcrt_gateways[$num]['internalname'] . "_code", "cc");

        update_option('payment_gateway_names', $gatewayNames);
    }
   
}

new WpeVelocity();

/**
 *  WpeVelocity_StatusCode static class
 *  Contains the payment statuscode constants
 * 
 *  @author velocity
 *  @since 1.0.0
 */
class WpeVelocity_StatusCode {

    const OPEN = "OPEN";
    const AUTHORIZED = "AUTHORIZED";
    const ERROR = "ERR";
    const SUCCESS = "Captured";
    const REFUND = "REFUND";
    const CHARGEBACK = "CBACK";

}

register_deactivation_hook( __FILE__, 'cron_velocity_daily_remove' );

/*
 * This function call at the time of plugin deactivate.
 * remove the cron job for clear the log data.
 * Call by register deactivation hook
 * @param null
 * return null
 */
function cron_velocity_daily_remove() {
    wp_clear_scheduled_hook('cron_velocity_daily');
}

register_uninstall_hook(__FILE__, 'eVelocity_uninstall');

/*
 * This function call at the time of plugin uninstall and code delete from server.
 * Call by register uninstall hook
 * @param null
 * return null
 */
function eVelocity_uninstall() {
    
    delete_option('wpevelocity_options');
    
}