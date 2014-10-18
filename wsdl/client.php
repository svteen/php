<?php 
//error_reporting(0);
//$ac = $_GET['ac'];

try {
	//$soap = new SoapClient(null, array('location'=>"http://localhost/server.php", 'uri'=>'server.php')); 
	ini_set('soap.wsdl_cache_enabled', '0'); // disabled cache..
	$soap = new SoapClient('http://localhost/server_wsdl.php?wsdl');
	echo $soap->show()."<br>";
	echo $result2 = $soap->__soapCall('show', array())."<br>";
	$one = "aaaa";
	$two = "bbbb";
	echo $soap->getMax($one, $two);
	//echo $result3 = $soap->__soapCall('getMax', array('11','bbb'))."<br>";
}catch(SoapFault $e){
	echo $e->getMessage();
	print ($soap->getMax('aaa', 'bbb'));
}catch(Exception $e){
	echo $e->getMessage();
}
