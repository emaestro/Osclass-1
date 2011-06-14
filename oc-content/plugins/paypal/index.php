<?php
/*
Plugin Name: Paypal payment
Plugin URI: http://www.osclass.org/
Description: Paypal payment options
Version: 0.1
Author: OSClass
Author URI: http://www.osclass.org/
Short Name: paypal
*/

require_once osc_plugins_path().osc_plugin_folder(__FILE__).'functions.php';

function paypal_install() {
    $conn = getConnection() ;
    $conn->autocommit(false);
    try {
        $path = osc_plugin_resource('paypal/struct.sql');
        $sql = file_get_contents($path);
        $conn->osc_dbImportSQL($sql);
        $conn->commit();
        osc_set_preference('default_premium_cost', '1.0', 'paypal', 'STRING');
        osc_set_preference('allow_premium', '0', 'paypal', 'BOOLEAN');
        osc_set_preference('default_publish_cost', '1.0', 'paypal', 'STRING');
        osc_set_preference('pay_per_post', '0', 'paypal', 'BOOLEAN');
        osc_set_preference('premium_days', '7', 'paypal', 'INTEGER');
        osc_set_preference('currency', 'USD', 'paypal', 'STRING');
        osc_set_preference('api_username', '', 'paypal', 'STRING');
        osc_set_preference('api_password', '', 'paypal', 'STRING');
        osc_set_preference('api_signature', '', 'paypal', 'STRING');
        $items = $conn->osc_dbFetchResults("SELECT pk_i_id FROM %st_item", DB_TABLE_PREFIX);
        $date = date('Y-m-d H:i:s');
        foreach($items as $item) {
            $conn->osc_dbExec("INSERT INTO %st_paypal_publish (`fk_i_item_id`, `dt_date`, `b_paid`) VALUES ('%d', '%s', '1')", DB_TABLE_PREFIX, $item['pk_i_id'], $date);
        }
        $conn->osc_dbExec("INSERT INTO %st_pages (s_internal_name, b_indelible, dt_pub_date) VALUES ('email_paypal', 1, NOW() )", DB_TABLE_PREFIX);
        $page_id = $conn->osc_dbFetchResult("SELECT * FROM %st_pages WHERE s_internal_name = 'email_paypal'", DB_TABLE_PREFIX);
        $conn->osc_dbExec("INSERT INTO %st_pages_description (fk_i_pages_id, fk_c_locale_code, s_title, s_text) VALUES (%d, '%s', '{WEB_TITLE} - Publish option for your ad: {ITEM_TITLE}', '<p>Hi {CONTACT_NAME}!</p>\r\n<p> </p>\r\n<p>We just published your item ({ITEM_TITLE}) on {WEB_TITLE}.</p>\r\n<p>{START_PUBLISH_FEE}</p>\r\n<p>In order to make your ad available to anyone on {WEB_TITLE}, you should complete the process and pay the publish fee. You could do that on the following link: {PUBLISH_LINK}</p>\r\n<p>{END_PUBLISH_FEE}</p>\r\n<p> </p>\r\n<p>{START_PREMIUM_FEE}</p>\r\n<p>You could make your ad premium and make it to appear on top result of the searches made on {WEB_TITLE}. You could do that on the following link: {PREMIUM_LINK}</p>\r\n<p>{END_PREMIUM_FEE}</p>\r\n<p> </p>\r\n<p>This is an automatic email, if you already did that, please ignore this email.</p>\r\n<p> </p>\r\n<p>Thanks</p>');
')", DB_TABLE_PREFIX, $page_id['pk_i_id'], osc_language());
        
    } catch (Exception $e) {
        $conn->rollback();
        echo $e->getMessage();
    }
    $conn->autocommit(true);
}

function paypal_uninstall() {
    $conn = getConnection() ;
    $conn->autocommit(false);
    try {
        $conn->osc_dbExec('DROP TABLE %st_paypal_wallet', DB_TABLE_PREFIX);
        $conn->osc_dbExec('DROP TABLE %st_paypal_premium', DB_TABLE_PREFIX);
        $conn->osc_dbExec('DROP TABLE %st_paypal_publish', DB_TABLE_PREFIX);
        $conn->osc_dbExec('DROP TABLE %st_paypal_prices', DB_TABLE_PREFIX);
        $conn->osc_dbExec('DROP TABLE %st_paypal_log', DB_TABLE_PREFIX);
        $page_id = $conn->osc_dbFetchResult("SELECT * FROM %st_pages WHERE s_internal_name = 'email_paypal'", DB_TABLE_PREFIX);
        $conn->osc_dbExec("DELETE FROM %st_pages WHERE pk_i_id = %d", DB_TABLE_PREFIX, $page_id['pk_i_id']);
        $conn->osc_dbExec("DELETE FROM %st_pages_description WHERE fk_i_pages_id = %d", DB_TABLE_PREFIX, $page_id['pk_i_id']);
        $conn->commit();
        osc_delete_preference('default_premium_cost', 'paypal');
        osc_delete_preference('allow_premium', 'paypal');
        osc_delete_preference('default_publish_cost', 'paypal');
        osc_delete_preference('pay_per_post', 'paypal');
        osc_delete_preference('premium_days', 'paypal');
        osc_delete_preference('currency', 'paypal');
        osc_delete_preference('api_username', 'paypal');
        osc_delete_preference('api_password', 'paypal');
        osc_delete_preference('api_signature', 'paypal');
    } catch (Exception $e) {
        $conn->rollback();
        echo $e->getMessage();
    }
    $conn->autocommit(true);
}

function paypal_path() {
    return osc_base_url()."oc-content/plugins/".osc_plugin_folder(__FILE__);
}

function paypal_button($amount = "0.00", $description = "", $rpl="||", $itemnumber = "101") {

    $APIUSERNAME  = osc_get_preference('api_username', 'paypal');
    $APIPASSWORD  = osc_get_preference('api_password', 'paypal');
    $APISIGNATURE = osc_get_preference('api_signature', 'paypal');
    $ENDPOINT     = "https://api-3t.sandbox.paypal.com/nvp";
    $VERSION      = "65.1"; //must be >= 65.1
    $REDIRECTURL  = "https://www.sandbox.paypal.com/incontext?token=";
  
    //Build the Credential String:
    $cred_str = "USER=" . $APIUSERNAME . "&PWD=" . $APIPASSWORD . "&SIGNATURE=" . $APISIGNATURE . "&VERSION=" . $VERSION;
    //For Testing this is hardcoded. You would want to set these variable values dynamically
    $nvp_str  = "&METHOD=SetExpressCheckout" 
    . "&RETURNURL=".osc_base_url()."oc-content/plugins/".osc_plugin_folder(__FILE__)."return.php?rpl=".$rpl //set your Return URL here
    . "&CANCELURL=".osc_base_url()."oc-content/plugins/".osc_plugin_folder(__FILE__)."cancel.php?rpl=".$rpl //set your Cancel URL here
    . "&PAYMENTREQUEST_0_CURRENCYCODE=".osc_get_preference("currency", "paypal")
    . "&PAYMENTREQUEST_0_AMT=".$amount
    . "&PAYMENTREQUEST_0_ITEMAMT=".$amount
    . "&PAYMENTREQUEST_0_TAXAMT=0"
    . "&PAYMENTREQUEST_0_DESC=".$description
    . "&PAYMENTREQUEST_0_PAYMENTACTION=Sale"
    . "&L_PAYMENTREQUEST_0_ITEMCATEGORY0=Digital"
    . "&L_PAYMENTREQUEST_0_NAME0=".$description
    . "&L_PAYMENTREQUEST_0_NUMBER0=".$itemnumber
    . "&L_PAYMENTREQUEST_0_QTY0=1"
    . "&L_PAYMENTREQUEST_0_TAXAMT0=0"
    . "&L_PAYMENTREQUEST_0_AMT0=".$amount
    . "&L_PAYMENTREQUEST_0_DESC0=Download"
    . "&useraction=commit";
  
    //combine the two strings and make the API Call
    $req_str = $cred_str . $nvp_str;
    $response = PPHttpPost($ENDPOINT, $req_str);
    //check Response
    if($response['ACK'] == "Success" || $response['ACK'] == "SuccessWithWarning") {
        //setup redirect URL
        $redirect_url = $REDIRECTURL . urldecode($response['TOKEN']);
        $r = rand(0,1000);
        ?><a href="<?php echo $redirect_url; ?>" id='paypalBtn_<?php echo $r; ?>'><img src='<?php echo paypal_path();?>paypal.gif' border='0' /></a>
        <script>
            var dg = new PAYPAL.apps.DGFlow({
                trigger: "paypalBtn_<?php echo $r; ?>"
            });
        </script><?php
    } else if($response['ACK'] == "Failure" || $response['ACK'] == "FailureWithWarning") {
        $redirect_url = ""; //SOMETHING FAILED
        //print_r($response);
    }
    
}


function paypal_admin_menu() {
    echo '<h3><a href="#">Paypal Options</a></h3>
    <ul> 
        <li><a href="'.osc_admin_render_plugin_url(osc_plugin_folder(__FILE__)."conf.php").'">&raquo; ' . __('Paypal Options') . '</a></li>
        <li><a href="'.osc_admin_render_plugin_url(osc_plugin_folder(__FILE__)."conf_prices.php").'">&raquo; ' . __('Categories fees') . '</a></li>
    </ul>';
}

function paypal_load_js() {
    echo "<script src ='https://www.paypalobjects.com/js/external/dg.js' type='text/javascript'></script>";
}


function paypal_redirect_to($url) {
    header('Location: ' . $url);
    exit;
}

function paypal_publish($item) {
    if(osc_get_preference('pay_per_post', 'paypal')) {
        // Check if it's already payed or not
        $conn = getConnection();
        // Item is not paid, continue
        $ppl_category = $conn->osc_dbFetchResult("SELECT f_publish_cost FROM %st_paypal_prices WHERE fk_i_category_id = %d", DB_TABLE_PREFIX, $item['fk_i_category_id']);
        if($ppl_category && isset($ppl_category['f_publish_cost'])) {
            $category_fee = $ppl_category["f_publish_cost"];
        } else {
            $category_fee = osc_get_preference("default_publish_cost", "paypal");
        }
        paypal_send_email($item, $category_fee);
        if($category_fee>0) {
            // Catch and re-set FlashMessages
            osc_resend_flash_messages();
            $conn->osc_dbExec("INSERT INTO  %st_paypal_publish (`fk_i_item_id` ,`dt_date` ,`b_paid`)VALUES ('%d',  '%s',  '0')", DB_TABLE_PREFIX, $item['pk_i_id'], date('Y-m-d H:i:s'));
            paypal_redirect_to(osc_render_file_url(osc_plugin_folder(__FILE__)."payperpublish.php&itemId=".$item['pk_i_id']));
        } else {
            // PRICE IS ZERO
            $conn->osc_dbExec("INSERT INTO  %st_paypal_publish (`fk_i_item_id` ,`dt_date` ,`b_paid`)VALUES ('%d',  '%s',  '1')", DB_TABLE_PREFIX, $item['pk_i_id'], date('Y-m-d H:i:s'));
        }
    } else {
        // NO NEED TO PAY PUBLISH FEE
        paypal_send_email($item, 0);
    }
    $category = Category::newInstance()->findByPrimaryKey($item['fk_i_category_id']);
    View::newInstance()->_exportVariableToView('category', $category);
    paypal_redirect_to(osc_search_category_url());
}

function paypal_user_menu() {
    echo '<li class="opt_paypal" ><a href="' . osc_render_file_url(osc_plugin_folder(__FILE__)."user_menu.php") . '" >' . __("Paypal Options", "paypal") . '</a></li>' ;
}


function paypal_send_email($item, $category_fee) {
    
    if(!osc_is_web_user_logged_in()) {
        $mPages = new Page() ;
        $aPage = $mPages->findByInternalName('email_paypal') ;
        $locale = osc_current_user_locale() ;

        $content = array();
        if(isset($aPage['locale'][$locale]['s_title'])) {
            $content = $aPage['locale'][$locale];
        } else {
            $content = current($aPage['locale']);
        }
        //$item =  $this->itemManager->findByPrimaryKey($itemId);

        $item_url = osc_item_url( ) ;
        $item_url = '<a href="'.$item_url.'" >'.$item_url.'</a>';
        $publish_url = osc_render_file_url(osc_plugin_folder(__FILE__)."payperpublish.php&itemId=".$item['pk_i_id']);
        $premium_url = osc_render_file_url(osc_plugin_folder(__FILE__)."makepremium.php&itemId=".$item['pk_i_id']);

        $words   = array();
        $words[] = array('{ITEM_ID}', '{CONTACT_NAME}', '{CONTACT_EMAIL}', '{WEB_URL}', '{ITEM_TITLE}',
            '{ITEM_URL}', '{WEB_TITLE}', '{PUBLISH_LINK}', '{PUBLISH_URL}', '{PREMIUM_LINK}', '{PREMIUM_URL}',
            '{START_PUBLISH_FEE}', '{END_PUBLISH_FEE}', '{START_PREMIUM_FEE}', '{END_PREMIUM_FEE}');
        $words[] = array($item['pk_i_id'], $item['s_contact_name'], $item['s_contact_email'], osc_base_url(), $item['s_title'],
            $item_url, osc_page_title(), '<a href="' . $publish_url . '">' . $publish_url . '</a>', $publish_url, '<a href="' . $premium_url . '">' . $premium_url . '</a>', $premium_url, '', '', '', '') ;
        

        if($category_fee==0) {
            $content['s_text'] = preg_replace('|{START_PUBLISH_FEE}(.*){END_PUBLISH_FEE}|', '', $content['s_text']);
        }

        $conn = getConnection();
        $ppl_category = $conn->osc_dbFetchResult("SELECT f_premium_cost FROM %st_paypal_prices WHERE fk_i_category_id = %d", DB_TABLE_PREFIX, $item['fk_i_category_id']);
        if($ppl_category && isset($ppl_category['f_premium_cost']) && $ppl_category['f_premium_cost']>0) {
            $premium_fee = $ppl_category["f_premium_cost"];
        } else {
            $premium_fee = osc_get_preference("default_premium_cost", "paypal");
        }
        if($premium_fee==0) {
            $content['s_text'] = preg_replace('|{START_PREMIUM_FEE}(.*){END_PREMIUM_FEE}|', '', $content['s_text']);
        }
        
        $title   = osc_mailBeauty($content['s_title'], $words) ;
        $body    = osc_mailBeauty($content['s_text'], $words) ;
        
        $emailParams =  array(
            'subject' => $title
            ,'to' => $item['s_contact_email']
            ,'to_name' => $item['s_contact_name']
            ,'body' => $body
            ,'alt_body' => $body
        );

        osc_sendMail($emailParams);
    }
    
}
// This is needed in order to be able to activate the plugin
osc_register_plugin(osc_plugin_path(__FILE__), 'paypal_install');
// This is a hack to show a Configure link at plugins table (you could also use some other hook to show a custom option panel)
osc_add_hook(osc_plugin_path(__FILE__)."_configure", '');
// This is a hack to show a Uninstall link at plugins table (you could also use some other hook to show a custom option panel)
osc_add_hook(osc_plugin_path(__FILE__)."_uninstall", 'paypal_uninstall');

osc_add_hook('admin_menu', 'paypal_admin_menu');
osc_add_hook('header', 'paypal_load_js');
osc_add_hook('posted_item', 'paypal_publish');
osc_add_hook('user_menu', 'paypal_user_menu');

?>