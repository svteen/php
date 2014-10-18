<?php
error_reporting(0);
include("class.myws.php");
include("SoapDiscovery.class.php");
$action = $_GET['action'];
if($action == 'get' ){
	$disco = new SoapDiscovery('Myws', 'rbws');
	$aaa = $disco->getWSDL();
	file_put_contents('./wsdl/rbws.wsdl', $aaa);
	exit;	
}
//No wsdl
//$server = new SoapServer(null, array("location"=>"http://localhost/server_wsdl.php", "uri"=>"server_wsdl.php"));
$server = new SoapServer('./wsdl/rbws.wsdl', array('soap_version' => SOAP_1_2));
$server->setClass('Myws');   
//$server->addFunction('echoString');
//$server->addFunction(SOAP_FUNCTIONS_ALL); 
$server->handle();
