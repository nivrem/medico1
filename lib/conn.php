<?php

//Evitamos ataques de scripts de otros sitios
if(eregi("conn.php", $_SERVER["PHP_SELF"])) die("Acceso denegado!");
// ESTRUCTURA SECUENCIAL PSEUDO ORIENTADO A OBJETOS
//CONEXION CON EL SERVIDOR
 $link_mysql;

$link_mysql=mysql_connect(SERVER_MSSQL_LOCAL,USER_MSSQL_LOCAL,PASS_MSSQL_LOCAL) or die("No pudo conectar al servidor");

mysql_select_db(BDS_MSSQL_LOCAL,$link_mysql) or die( "Error al seleccionar la BD");


?>