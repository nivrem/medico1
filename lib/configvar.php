<?php

if(eregi("configvar.php", $_SERVER["PHP_SELF"])) die("Access denied!");
//Directorios 1 local 2 vivo
//define("APPROOT",$_SERVER['DOCUMENT_ROOT']."/RTP/");//local

// Ej: se usa para los includes para los llamados a los archibos 1 local 2 vivo
//define("DOMAIN_ROOT", "http://".$_SERVER['SERVER_NAME']."/RTP/");//local


// fuentes de el fpdf
//define('FPDF_FONTPATH',APPROOT.'font/');
////Correo
//define("SEND_MAIL", "false"); //Activa � Desactiva el envio de Correo.
////carpetas
//define("FOLDER_ATTACH", "files"); // atachment
////nomre sstema
//define("SYSTEM_NAME","Sistemas de Facturacion"); // sistema
////archivo de logout
//define("LOGOUT",DOMAIN_ROOT."lib/common/Logout.php"); // sistema


//DATOS GENERALES DE CONEXION 
//define("SERVER_MSSQL_LOCAL","192.168.0.189");
//define("SERVER_MSSQL_LOCAL","10.10.7.100");
//define("SERVER_MSSQL_LOCAL","10.10.3.89");
//define("SERVER_MSSQL_LOCAL","172.16.0.15");

// define("USER_MSSQL_REMOTE","sa");
// define("PASS_MSSQL_REMOTE","123456");
// define("SERVER_MSSQL_REMOTE","10.10.7.103");
// define("BD_MSSQL_REMOTE_REMOTE","CYBERLUX");
// define("BDS_MSSQL_REMOTE","BDSATUS");
// define("DBA_MSSQL_REMOTE_REMOTE","mssql");

define('bd_easysale','cybernew');
define("USER_MSSQL_LOCAL","root");


define("PASS_MSSQL_LOCAL","localhost");
define("SERVER_MSSQL_LOCAL","localhost");

define("BDS_MSSQL_LOCAL","sertev");
define("DBA_MSSQL_LOCAL","mysql");

?>