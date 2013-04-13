<?php
class PDF_RETIRO extends FPDF
{
//Cabecera de página
function Header()
{
    global $title;

    $this->Ln(20);
}

function Footer()
{
	//Posición: a 1,5 cm del final
   /* $this->SetY(-50);
    //Arial italic 8
    $this->SetFont('Arial','I',8);
    //Número de página
    $this->Cell(0,10,'Page '.$this->PageNo().'/{nb}',0,0,'C');
*/
 
	
}

function headerImagen()
{

	$this->Image('../images/encabezado.jpg',10,8,200);
	/*
	$num_guia,$link
	$this->SetFont('Times','B',14);	
	$this->SetXY(175,17);
	$this->Cell(10,10,'No: ');//etiqueta nota
	$this->SetFont('Times','B',14);
	$this->SetTextColor(255,0,0);
	$this->SetXY(190,17);
	//die('esta es la guia'.$num_guia);
   	$this->Cell(10,10,$num_guia,0,0,0,0,$link);//numero de la guia o control de salida
	$this->SetTextColor(0,0,0);*/
}

function footerImagen()
{

	$this->Image('../images/pie.jpg',10,240,200);
	
}


////////////////SECCION IMPRECION DEL COMPROBANTE DE SALIDA////////////////////
function dataControlEncabezado($num_ret,$fecha,$link,$fecha_con,$factura,$cliente,$status)
{
	$label_num_ret=utf8_decode('Nro. de Ret:   ');//etiqueta de nmero de guia
	
	$this->SetFont('Arial','B',12);
	$this->SetXY(140,60);
   	$this->Cell(40,10,$label_num_ret.' '.$num_ret,0,0,0,0,$link);//numero de la guia o control de salida
	//ETIQUETAS DE DETALLLES///
	$this->SetFont('Arial','',10);
	$this->SetXY(25,60);
	$this->Cell(40,10,'Fecha');//etiqueta de la fecha
	$this->SetFont('Arial','B',10);
	$this->SetXY(25,65);
	$this->Cell(40,10,'Datos de la Factura');//etiqueta de de datos de la factura
	$this->SetFont('Arial','',10);
	$this->SetXY(25,70);
	$this->Cell(40,10,'Fecha');//etiqueta de la fecha
	$this->SetXY(120,70);
	$this->Cell(40,10,'Factura');//etiqueta de los telefonos
	$this->SetXY(25,75);
	$this->Cell(40,10,'Cliente');//etiqueta de la placa
	$this->SetFont('Arial','B',10);
	$this->SetXY(25,80);
	$this->Cell(40,10,'Transporte');//etiqueta del destino1
	$this->SetFont('Arial','',10);
	$this->SetXY(25,85);
	$this->Cell(40,10,'Empresa');//etiqueta del destino1
	//ETIQUETAS DE DETALLLES///
	
	
	///DATOS //
	$this->SetFont('Arial','B',9);
	$this->SetXY(45,60);
	$this->Cell(40,10,$fecha);//fecha
	//$this->SetXY(45,65);
	//$this->Cell(40,10,$transporte);//fecha
	$this->SetXY(45,70);
	$this->Cell(40,10,$fecha_con);//fecha
	$this->SetXY(140,70);
	$this->Cell(40,10,$factura);//telefonos chofer
	$this->SetXY(45,75);
	$this->Cell(40,10,$cliente);//placa
	$this->SetXY(45,85);
	$this->MultiCell(120,10,utf8_decode('Inversiones Nemartiz'));//destino se usa multi cell por los posibles saltos de linea
	
	///DATOS //
	
	//LINEAS DIVISOPRIAS DE MODULOS//1
	$this->Line(22,95,203,95);
	//LINEAS DIVISOPRIAS DE MODULOS//2
	$this->Line(22,179,203,179);
	//LINEAS DIVISOPRIAS DE MODULOS//3
	$this->Line(22,229,203,229);
	  
}

function dataControlDetalle($articulo,$cantidad,$tipo,$i){
//	die($id_factura.'----'.$monto_factura.'----'.$cliente.'----'.$i);
	
	//rectangulo de las Facturas
	$this->Rect(20, 100, 185, 65,'');//rectangulo de la fattuta
	//seccion de titulos de las facturas
	$this->SetFont('Arial','B',11);	
	$this->SetXY(20,100);
	$this->Cell(130,5,'Articulo',1,0,'C',0);//etiqueta nota
	$this->SetXY(150,100);
	$this->Cell(10,5,'Cant',1,0,'C',0);//etiqueta nota
	$this->SetXY(160,100);
	$this->Cell(45,5,'Motivo',1,0,'C',0);//etiqueta nota
	
	$y_inicual=105;
	$y_actual=$y_inicual+($i*4);
	//esto va para el detalle
	$this->SetFont('Arial','',8	);	
	$this->SetXY(20,$y_actual);
	$this->MultiCell(130,4,' '.$articulo,1,'');//Detalle de faturas nombre del cliente
	$this->SetXY(150,$y_actual);
	$this->Cell(10,4,$cantidad,1,0,'C',0);//Detalle de faturas numero de la factura
	$this->SetXY(160,$y_actual);
	$this->Cell(45,4,' '.$tipo,1,'');//Detalle de faturas monto de la factura
		
	//MONTO FINAL	
	$this->SetFont('Arial','B',12);	
	$this->SetXY(115,165);
	$this->Cell(40,10,'Monto sin I.V.A: ');//etiqueta nota
	
	



}

function dataControlDetalleTotalizador($monto_sin_iva){
	///monto final
	$this->SetFont('Arial','B',12);	
	$this->SetXY(150,165);
	$this->Cell(50,10,number_format($monto_sin_iva,2,",",".").' ',0,0,'R');//Detalle monto sin iva
}


function dataControlPie($motivo,$observaciones){
	//DETALLES BASES PIE//
	//rectangulo de la nota
	$this->Rect(25, 185, 175, 35,'');//rectangulo de la nota
	$this->SetFont('Arial','B',10);	
	$this->SetXY(27,185);
	$this->Cell(40,10,utf8_decode('Observación   : '));//etiqueta nota
	//$this->SetXY(27,200);
	//$this->Cell(40,10,utf8_decode('Cambio Status: '));//etiqueta nota
	
	//observacion o nota
	$this->SetFont('Arial','',9);	
	$this->SetXY(55,189);
	$this->MultiCell(150,3,$observaciones);//etiqueta nota
	//$this->SetXY(55,204);
	//$this->MultiCell(150,3,$motivo);//etiqueta nota

}

////////////////SECCION IMPRECION DEL COMPROBANTED DE SALIDA////////////////////



}//finde la claase

?>