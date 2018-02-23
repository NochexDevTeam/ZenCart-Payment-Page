<?php 
/**
 * nochex_apc_handler.php callback handler for Nochex APC payment method
 *
 * @package paymentMethod
 * @copyright Copyright 2003-2006 Zen Cart Development Team
 * @copyright Portions Copyright 2003 osCommerce
 * @license http://www.zen-cart.com/license/2_0.txt GNU Public License V2.0
 */
 
/* Zen cart and Nochex functions */

/*if (!isset($_SESSION['language'])) $_SESSION['language'] = 'english';
if (file_exists(DIR_WS_LANGUAGES . $_SESSION['language'] . '/' . $template_dir_select . 'checkout_process.php')) {
  require(DIR_WS_LANGUAGES . $_SESSION['language'] . '/' . $template_dir_select . 'checkout_process.php');
} else {
  require(DIR_WS_LANGUAGES . $_SESSION['language'] . '/checkout_process.php');
}
*/
/* ------ */
/* Include Nochex functions */ 
require('includes/modules/payment/nochex_apc/nochex_functions.php');
/*
 Include custom application_top.php */
require('includes/application_top.php');
//require('includes/modules/payment/nochex_apc/apc_application_top.php'); doesn't work

/* Include checkout_process in relation to the session language **/
require('includes/languages/english/modules/payment/nochex_apc.php');

/* APC Code */

// Payment confirmation from http post 
ini_set("SMTP","mail.nochex.com" ); 
$header = "From: apc@nochex.com";

$your_email = $_POST["to_email"];  // your merchant account email address
  
function http_post($server, $port, $url, $vars) { 
    // get urlencoded vesion of $vars array 
    $urlencoded = ""; 
    foreach ($vars as $Index => $Value) // loop round variables and encode them to be used in query
    $urlencoded .= urlencode($Index ) . "=" . urlencode($Value) . "&"; 
    $urlencoded = substr($urlencoded,0,-1);   // returns portion of string, everything but last character

    $headers = "POST $url HTTP/1.0\r\n"  // headers to be sent to the server
    . "Content-Type: application/x-www-form-urlencoded\r\n" 
    . "Host: www.nochex.com\r\n" 
    . "Content-Length: ". strlen($urlencoded) . "\r\n\r\n";  // length of the string
  
    $fp = fsockopen($server, $port, $errno, $errstr, 20);  // returns file pointer
    if (!$fp) return "ERROR: fsockopen failed.\r\nError no: $errno - $errstr";  // if cannot open socket then display error message

    fputs($fp, $headers);  //writes to file pointer
    fputs($fp, $urlencoded);  
  
    $ret = ""; 
    while (!feof($fp)) $ret .= fgets($fp, 1024); // while it’s not the end of the file it will loop 
    fclose($fp);  // closes the connection
	
    return $ret; // array 
    } 

// uncomment below to force a DECLINED response 
//$_POST['order_id'] = "1"; 
  
$response = http_post("ssl://www.nochex.com", 443, "/apcnet/apc.aspx", $_POST); 
// stores the response from the Nochex server 
$debug = "IP -> " . $_SERVER['REMOTE_ADDR'] ."\r\n\r\nPOST DATA:\r\n"; 
foreach($_POST as $Index => $Value) 
$debug .= "$Index -> $Value\r\n"; 
$debug .= "\r\nRESPONSE:\r\n$response";
// Retrieves the order_id and save it as a variable which can be used in the update query to find a particular record in a database or datatable.	
	 $order_ID = $_POST['order_id']; 
// An email to check the order_ID
	//mail($your_email, "Order_ID Test", $_POST['order_id'] . " and Response" . $response, $header);
		
if (!strstr($response, "AUTHORISED")) {  // searches response to see if AUTHORISED is present if it isn’t a failure message is displayed
    $msg = "APC was not AUTHORISED.\r\n\r\n$debug";  // displays debug message
	
	$new_status = MODULE_PAYMENT_NOCHEX_PENDING_STATUS_ID;
    $db->Execute("update " . TABLE_ORDERS  . "
                    set orders_status = " . MODULE_PAYMENT_NOCHEX_PENDING_STATUS_ID . "
                    where orders_id = '" . $_POST["order_id"] . "'");

  $comments = 'Nochex payment of '.sprintf("%01.2f", $_POST["amount"]).' received at '.$_POST['transaction_date'].' with transaction ID:'.$_POST['transaction_id']. ' this was a '. $_POST['status'] .' transaction, ' .$msg;
  
   $sql_data_array = array('orders_id' => $_POST["order_id"],
                          'orders_status_id' => MODULE_PAYMENT_NOCHEX_PENDING_STATUS_ID,
                          'date_added' => 'now()',
                          'comments' => $comments,
                          'customer_notified' => false
  );
  zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

  $_SESSION['cart']->reset(true);
  
  
} 
else { 
   $msg = "APC was Authorised";
	
	$new_status = MODULE_PAYMENT_NOCHEX_PROCESSING_STATUS_ID;
    $db->Execute("update " . TABLE_ORDERS  . "
                    set orders_status = " . MODULE_PAYMENT_NOCHEX_PROCESSING_STATUS_ID . "
                    where orders_id = '" . $_POST["order_id"] . "'");

  $comments = 'Nochex payment of '.sprintf("%01.2f", $_POST["amount"]).' received at '.$_POST['transaction_date'].' with transaction ID:'.$_POST['transaction_id']. ' this was a '. $_POST['status'] .' transaction, ' .$msg;
  
   $sql_data_array = array('orders_id' => $_POST["order_id"],
                          'orders_status_id' => MODULE_PAYMENT_NOCHEX_PROCESSING_STATUS_ID,
                          'date_added' => 'now()',
                          'comments' => $comments,
                          'customer_notified' => false
  );
  zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);

  $_SESSION['cart']->reset(true);
} 
 
// sends an email explaining whether APC was successful or not, the subject will be “APC Debug” but you can change this to whatever you want.
//mail($your_email, "APC Debug", $msg, $header); 

?>  


