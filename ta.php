<?php
error_reporting(E_ALL);
ini_set('display_errors','1');
/****************************************************************************/
/*        WSAA (Web Services Auntentificación y Autorización de Afip)      */
/*  GENERACIÓNN TA (ticket de Auntentificación y Autorización para afip)   */
/*Autor:Ch47ly                           */
/*Licencia:GPL                                                             */
/****************************************************************************/
/*PASOS PREVIOS*/
/*PREVIAMENTE TIENE QUE ESTAR ADHERIDO AL SERVICIO WSASS DE AFIP CON CLAVE FISCAL(Manual afip WSASS_como_adherirse)*/
/*PREVIAMENTE TIENE QUE HABER GENERADO  PRIVATEKEY Y CERT CON OPENSSL SEGÚN INDICACIONES AFIP*/
/****************************************************************************/
/* 1- SINCRONIZAR LA FECHA Y HORA DEL PC                                    */
/*     -Se realiza con NTP (Network Time Protocol)                          */
/*     -En linux (Debian,Ubuntu y derivados)                                */
/*     -        apt-get install ntp                                          */
/*     -        ntp-p                                                       */
/* 2- GENERACIÓN DE ARCHIVO XML - TRA.XML (LoginTicketRequest.xml)          */
/*     -Estructura según schema XSD propuesto por afip en manual            */
/*     (Manual:ticket de Auntentificación y Autorización)                   */

include 'LoginTicketRequest.php';//Archivo con la estructuta del xml que hay que agregar hora de generación del ticket, hora en que expira y servicio "wsfe";
$date = date('Y-m-d\TH:i:s');
$ndate = new DateTime();
$ndate->modify('-0 hours');
$ndate->modify('+1440 minute');
$ndate->modify('-0 second');
$ndate=$ndate->format('Y-m-d\TH:i:s');
$loginTicketRequest = new SimpleXMLElement($xmlstr);
$loginTicketRequest->addChild('header');
$uniqueId = $loginTicketRequest->header[0]->addChild('uniqueId', '4325399');
$generationTime= $loginTicketRequest->header[0]->addChild('generationTime',$date);
$expirationTime= $loginTicketRequest->header[0]->addChild('expirationTime', $ndate);
$service = $loginTicketRequest->addChild('service', 'wsfe');
if($loginTicketRequest->asXML('LoginTicketRequest.xml')) {
echo 'archivo LoginTicketRequest.xml creado con exito<br>';
}
else {
echo 'no se pudo crear TRA.xml<br>';
}
/* 3- GENERACIÓN DE MENSAJE CMS                                             */
/*     -Genera mensaje CMS con LoginTicketRequest.xml firma electrónica     */
/*     y bae64.                                                             */	
$privateKey='file://PrivateKey';//Clave privada
$passPhrase='Frasepass';//Frase pass
$Cert = openssl_x509_read('file://archivo.pem');//file://archivo.pem path donde se encuentra el archivo pem.
$CMSsinB64 = openssl_pkcs7_sign('LoginTicketRequest.xml', 'LoginTicketRequest.tmp', $Cert,
			array($privateKey, $passPhrase),
			array(),
			!PKCS7_DETACHED
		);
if($CMSsinB64==true){
echo 'firma electrónica creada con exito<br>';
}
else {
echo 'no se pudo crear firma electronica<br>';
}
$inf=fopen("LoginTicketRequest.tmp", "r");
  $i=0;
  $CMS="";
  while (!feof($inf)) 
    { 
      $buffer=fgets($inf);
      if ( $i++ >= 4 ){
	$CMS.=$buffer;
	}
    }
  fclose($inf);
#  unlink("LoginTicketRequest.tmp");
  unlink("LoginTicketRequest.tmp");
$CMS;

/* 4- INVOCACIÓN WSAA CON EL CMS.                                           */
/*     -Conección SOAP con wsdl según esquema afip 'wsaa.wsdl'.             */
/*     -Recibe LoginTicket_Response.xml que es el TA(ticket de autorización)*/

try{
$options = array(
	'soap_version'   => SOAP_1_2,
	'location' => 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms',//server homologación(para test) AFIP
	'trace'          => 1,
);

$clienteSOAP=new SoapClient('wsaa.wsdl', $options);//debe incluir el archivo wsaa.wsdl en la misma carpeta
$resultado = $clienteSOAP->loginCms(array('in0'=>$CMS));
echo "REQUEST HEADERS:\n" .$clienteSOAP->__getLastRequestHeaders() . "\n";
file_put_contents("LoginTicket_Request.xml",$clienteSOAP->__getLastRequest());
file_put_contents("LoginTicket_Response.xml",$clienteSOAP->__getLastResponse());

} 
catch (SoapFault $fault) {
    trigger_error("SOAP Fault: (faultcode: {$fault->faultcode}, faultstring: {$fault->faultstring})", E_USER_ERROR);
 var_dump($fault);
}	
?>
