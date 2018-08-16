<?php
//include "phpqrcode/qrlib.php";
include ("phpqrcode.php");

$preu = $_POST['preu'];

    $crt = "crt111";
	$wallet = $_GET['wallet']; 
	$preu = $_GET['preu']; 
     
    // we need to be sure ours script does not output anything!!! 
    // otherwise it will break up PNG binary! 
     
    ob_start("callback"); 
     
    // here DB request or some processing 
    $codeText = "$wallet,$preu$crt"; 
     
    // end of processing here 
    $debugLog = ob_get_contents(); 
    ob_end_clean(); 
     
    // outputs image directly into browser, as PNG stream 
    QRcode::png($codeText, false, QR_ECLEVEL_M, 4, 1);
	

?>