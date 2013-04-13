<?php
session_start();
if(eregi("core.lib.php", $_SERVER["PHP_SELF"])) die("Access denied!");
//creacion de la session
//El config_var va en el mismo directorio del core.lib

//El conex va en el mismo directorio del corelib

//Carga de la clase de Coinexiones generales
//require(APPROOT."lib/conn.class.php");
//utilitarias

//require(APPROOT."lib/php/eliminar.php");
define("APPROOT",$_SERVER['DOCUMENT_ROOT']."/sertev/");
include_once (APPROOT."lib/configvar.php");
include_once (APPROOT."lib/conn.php");
//require(APPROOT."lib/php/fpdf/fpdf.php");
//require(APPROOT."lib/php/fpdf/pdf.class.php");
//require(APPROOT."lib/php/fpdf/pdf_retiro.class.php");

require(APPROOT."lib/clases/class.phpmailer.php");
require(APPROOT."lib/clases/BdQuery.class.php");
require(APPROOT."lib/clases/fallas.class.php");
require(APPROOT."lib/clases/usuarios.class.php");
require(APPROOT."lib/clases/numerosALetras.class.php");
require(APPROOT."lib/clases/reportes.class.php");
require(APPROOT."lib/clases/estadisticas.php");
//require(APPROOT."modulos/inser_repor_PDF.php");
//require(APPROOT."lib/clases/ClaseGeneral.php");

?>