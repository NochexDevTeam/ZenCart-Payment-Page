<?php 
	/**
	 * nochex_callback_handler.php callback handler for Nochex payment method 
	 */
  
	/* Include Nochex functions */ 
	require('includes/modules/payment/nochex_apc/nochex_functions.php');
	/* Include custom application_top.php */
	require('includes/application_top.php'); 
	/* Include checkout_process in relation to the session language **/
	require('includes/languages/english/modules/payment/nochex_apc.php');

	/* Callback Code */
	// Payment confirmation from http post 
	ini_set("SMTP","mail.nochex.com"); 
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
    . "Host: secure.nochex.com\r\n" 
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

	// stores the response from the Nochex server   
	$response = http_post("ssl://secure.nochex.com", 443, "/callback/callback.aspx", $_POST); 

	// Callback Debug
	$debug = "IP -> " . $_SERVER['REMOTE_ADDR'] ."\r\n\r\nPOST DATA:\r\n"; 
	foreach($_POST as $Index => $Value) 
	$debug .= "$Index -> $Value\r\n"; 
	$debug .= "\r\nRESPONSE:\r\n$response"; 

	if ($_POST['transaction_status'] == "100"){
		$status = " TEST";
	}else{
		$status = " LIVE";
	}

		$order_ID = $_POST['order_id']; 
		$trans_date = $_POST['transaction_date'];
		$trans_Id = $_POST['transaction_id']; 
		$trans_amount = $_POST["amount"]; 
 
	if (!strstr($response, "AUTHORISED")) {   
		$msg = "Callback was not AUTHORISED.\r\n\r\n$debug";   
		$new_status = MODULE_PAYMENT_NOCHEX_PENDING_STATUS_ID;
		/*$db->Execute("update " . TABLE_ORDERS  . "
					  set orders_status = " . $new_status . "
					  where orders_id = '" . $order_ID . "'");*/
		$comments = 'Nochex payment of '.sprintf("%01.2f", $trans_amount).' received at '.$trans_date.' with transaction ID:'.$trans_Id. ' this was a '. $status .' transaction, ' .$msg;  
		$sql_data_array = array('orders_id' => $order_ID,
							  'orders_status_id' => $new_status,
							  'date_added' => 'now()',
							  'comments' => $comments,
							  'customer_notified' => false
    );
	zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array);
	} else { 
		$msg = "Callback was Authorised";	
		$new_status = MODULE_PAYMENT_NOCHEX_PROCESSING_STATUS_ID; 
		$comments = 'Nochex payment of '.sprintf("%01.2f", $trans_amount).' received at '.$trans_date.' with transaction ID:'.$trans_Id. ' this was a '. $status .' transaction, ' .$msg;  
		$sql_data_array = array('orders_id' => $order_ID,
							  'orders_status_id' => $new_status,
							  'date_added' => 'now()',
							  'comments' => $comments,
							  'customer_notified' => false
   );
   zen_db_perform(TABLE_ORDERS_STATUS_HISTORY, $sql_data_array); 
} 
 
?>  


