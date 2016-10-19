<?php
 if(!isset($_SESSION['VS_Usgestor_direc']))
 {
    session_start();  
 }
 require_once("../Session.php");
 $oSession = unserialize($_SESSION['VS_Usgestor_direc']);
 $cliente=$oSession->VScliente;
 $user=$oSession->VSid;
 $perfil=$oSession->VStipo;

 //Perfil 1: Operadorrrr
 //Perfil 3: Gestor  
 //Perfil 5: Punto de Venta

 /*
 echo "cliente : ".$cliente."<br>";
 echo "user : ".$user."<br>";
 echo "perfil : ".$perfil."<br>";
 */

 include("../BD.php");
 $BD= new BD();
 $BD->conectar();

 if( !empty($_POST['opcion']) )
 {
     
     if(!$BD->is_data($_POST["opcion"],8) || !$BD->is_data($_POST["fecha"],6,0,10)){
			 
		echo 0;

		exit();
	}

     $tituloVentas = "VENTAS";           
     $tituloCompras = "COMPRAS DEL MES";  

     switch ($_POST['opcion']) 
     {
         case 'graficos':
             if( !empty($_POST['fecha']) )
             {
                     $condicion = "";
                     $condicion2 = "";
                     $condicion_compra = "";
                     switch ($perfil) 
                     {
                         case 1:
                         /*
                            $query = "SELECT GROUP_CONCAT(id) ids FROM `cliente__maestro` WHERE padre = $cliente";
                            $resp  = $BD->consultar($query);*/
                            $condicion = " AND id_cliente > 0"; 
                            $condicion2 = " AND id_cliente > 0";  
                            $condicion_compra = " AND id_cliente_prov =  $cliente";   
                            $tituloVentas = "TRANSACCION"; 
                            $tituloCompras = "VENTAS";          
                         break;
                         case 3:
                            $query = "SELECT acceso FROM cliente__maestro WHERE id = $cliente";
                            $resp  = $BD->consultar($query);
                            if( $resp->fields["acceso"] == 1 )
                            {
                                $query = "SELECT GROUP_CONCAT(id) ids FROM `cliente__maestro` WHERE padre = $cliente";
                                $resp  = $BD->consultar($query);
                                $condicion = " AND id_cliente IN (".$resp->fields['ids'].",".$cliente.")";              
                            }
                            else
                            {
                                $condicion = " AND id_cliente IN (".$cliente.")";   
                            }

                            $condicion2 = " AND id_cliente = $cliente";
                            $condicion_compra = $condicion;   
                         break;

                         case 5:
                            $condicion = " AND id_cliente IN (".$cliente.")";
                            $condicion_compra = $condicion; 
                         break;
                     }

                     $fecha = $_POST['fecha'];
                     $aux = explode("-", $fecha);

                     $anio = $aux[0];
                     $mes = $aux[1];

                     $VentasMes = array(); 
                     $VentasMes['titulo'] = $tituloVentas ." DEL MES";
                     $VentasMes['subtitulo'] = "";
                     $VentasMes['series'] = array();
                     $VentasMes['series'][0]['name'] = "$tituloVentas por mes";
                     $VentasMes['series'][0]['data'] = array(); 

                     for ($i=1; $i <= ultimoDiaMes($mes) ; $i++) 
                     {
                     	  $VentasMes['series'][0]['data'][] = 0; 
                     }
                     for ($i=1; $i <= ultimoDiaMes($mes); $i++) 
                     { 
                        $VentasMes['categories'][] = $i;
                     } 
                     /*---------------Consulta Ventas Por Mes---------------*/

                     $query = "SELECT SUM(valor) total,day(fecha) dia FROM `venta__servicios` WHERE month(fecha) = '$mes' AND year(fecha) = '$anio' AND estado = 1 $condicion GROUP BY day(fecha)";
                     $resp =  $BD->consultar($query);
                     while(!$resp->EOF)
                     {
                     	for ($i=1; $i <= ultimoDiaMes($mes); $i++) 
                        { 
                            if( $i == $resp->fields["dia"] )
                            {
                                $VentasMes['series'][0]['data'][$i-1] = (int) $resp->fields["total"];
                                
                                $sw = 1;
                                break;
                            }
                        } 
                        $resp->MoveNext();
                     }

                     /*---------------Consulta Ventas Por Dia---------------*/

                     $VentasDia = array();
                     $VentasDia['series'] = array();
                     $VentasDia['titulo'] = "$tituloVentas POR DIA";
                     $VentasDia['subtitulo'] = "";
                     $VentasDia['series'][0]['name'] = "$tituloVentas por dia";
                     $VentasDia['series'][0]['data'] = array(); 

                     for( $i = 0 ; $i <= 24 ; $i++ )
                     {
                         $VentasDia['series'][0]['data'][$i] = 0;
                         if( $i <= 9 )
                            $aux = '0'.$i;
                         else 
                            $aux = $i;
                         $VentasDia['categories'][] = "$aux".":00";
                     }


                     $query = "SELECT SUM(valor) total,concat( DATE_FORMAT(horaregistro, '%H'),':00') hour FROM `venta__servicios` WHERE fecha = '$fecha' AND estado = 1 $condicion group by(hour)";
                     $resp =  $BD->consultar($query);
                     while(!$resp->EOF)
                     { 
                        for( $i = 0 ; $i <= 24 ; $i++ )
                        {
                            if( $i <= 9 )
                             $aux = '0'.$i.":00"; 
                            else 
                             $aux = $i.":00";

                            if( $aux == $resp->fields["hour"] )
                            {
                               $VentasDia['series'][0]['data'][$i] = (int) $resp->fields["total"];
                               break;
                            }  
                        }                          
                        $resp->MoveNext();
                     }   


                     /*---------------Consulta Compras Por Mes---------------*/
                     

                     $ComprasMes['series'][0]['name'] = "$tituloCompras";
                     $ComprasMes['titulo'] = " $tituloCompras DEL MES";
                     $ComprasMes['subtitulo'] = "";
                     $ComprasMes['series'][0]['data'] = array();


                     for ($i=1; $i <= 30; $i++) 
                     {
                        $ComprasMes['series'][0]['data'][] = 0; 
                     }
                     for ($i=1; $i <= 30; $i++) 
                     { 
                        $ComprasMes['categories'][] = $i;
                     }

                     $query = "SELECT SUM(valor_acreditado) total,day(fecha_acre) dia FROM `acreditacion` WHERE year(fecha_acre) = '$anio' AND month(fecha_acre) = '$mes' AND estado = 1 $condicion_compra GROUP BY day(fecha_acre)";
                     $resp =  $BD->consultar($query);
                     while(!$resp->EOF)
                     {
                        for ($i=1; $i <= 30; $i++) 
                        { 
                            if( $i == $resp->fields["dia"] )
                            {
                                $ComprasMes['series'][0]['data'][$i-1] = (int) $resp->fields["total"];
                                $sw = 1;
                                break;
                            }
                        } 
                        $resp->MoveNext();
                     } 

                     /*---------------Consulta Compras Por Dia---------------*/

                     $ComprasDia = array(); 
                     $ComprasDia['series'] = array();
                     $ComprasDia['titulo'] = "$tituloCompras POR DIA";
                     $ComprasDia['subtitulo'] = "";
                     $ComprasDia['series'][0]['name'] = "$tituloCompras por dia";
                     $ComprasDia['series'][0]['data'] = array(); 

                     for( $i = 0 ; $i <= 24 ; $i++ )
                     {
                         $ComprasDia['series'][0]['data'][$i] = 0;
                         if( $i <= 9 )
                            $aux = '0'.$i;
                         else 
                            $aux = $i;
                         $ComprasDia['categories'][] = "$aux".":00";
                     }


                     $query = "SELECT SUM(valor_acreditado) total,concat( DATE_FORMAT(hora_acre, '%H'),':00') hour  
                               FROM `acreditacion` 
                               WHERE fecha_acre = '$fecha' AND estado = 1 $condicion_compra GROUP BY hour";
                     
                     $resp =  $BD->consultar($query);
                     while(!$resp->EOF)
                     { 
                        for( $i = 0 ; $i <= 24 ; $i++ )
                        {
                            if( $i <= 9 )
                             $aux = '0'.$i.":00"; 
                            else 
                             $aux = $i.":00";

                            if( $aux == $resp->fields["hour"] )
                            {
                               $ComprasDia['series'][0]['data'][$i] = (int) $resp->fields["total"];
                               break;
                            }  
                        }                          
                        $resp->MoveNext();
                     }  

                     echo json_encode( array( "fecha" => $fecha , "anio" => $anio , "mes" => $mes  , "perfil" => $perfil , "cliente" => $cliente , "VentasMes" => $VentasMes , "VentasDia" => $VentasDia , "ComprasMes" => $ComprasMes , "ComprasDia" => $ComprasDia ));
             } 
         break;
         
         case "DetalleVentaMes":
             $perfil = $_POST["perfil"];
             $mes = $_POST["mes"];
             $anio = $_POST["anio"];

             $titulo = "VENTAS";

             $condicion = "";
             switch ($perfil) 
             {
                 case 1:
                    $condicion = " AND id_cliente > 0"; 
                    $condicion2 = " AND id_cliente > 0";   
                    $titulo = "TRANSACCIONES";             
                 break;
                 case 3:
                    $query = "SELECT acceso FROM cliente__maestro WHERE id = $cliente";
                    $resp  = $BD->consultar($query);
                    if( $resp->fields["acceso"] == 1 )
                    {
                        $query = "SELECT GROUP_CONCAT(id) ids FROM `cliente__maestro` WHERE padre = $cliente";
                        $resp  = $BD->consultar($query);
                        $condicion = " AND id_cliente IN (".$resp->fields['ids'].",".$cliente.")";              
                    }
                    else
                    {
                        $condicion = " AND id_cliente IN (".$cliente.")";   
                    }
                    $condicion_compra = $condicion;  
                    $condicion2 = " AND id_cliente = $cliente";
                 break; 
             } 

             $VentasMes = array(); 
             $VentasMes['titulo'] = "DETALLE $titulo POR MES";
             $VentasMes['subtitulo'] = "";
             $VentasMes['series'] = array();
             
             
             for ($i=1; $i <= ultimoDiaMes($mes); $i++) 
             { 
                $VentasMes['categories'][] = $i;
             }

             
             $query = "SELECT id_cliente , concat( nombres , ' ' , apellidos ) cliente 
                       FROM `venta__servicios` 
                       INNER JOIN cliente__maestro ON cliente__maestro.id = id_cliente
                       WHERE month(fecha) = '$mes' AND year(fecha) = '$anio' AND venta__servicios.estado = 1 $condicion GROUP BY id_cliente";
             
             $resp =  $BD->consultar($query);

             $indice = 0;
             while(!$resp->EOF)
             {
                    $VentasMes['series'][$indice]['name'] = $resp->fields['cliente'];
                    $VentasMes['series'][$indice]['data'] = array(); 
                    for ($i=1; $i <= ultimoDiaMes($mes) ; $i++) 
                    {
                          $VentasMes['series'][$indice]['data'][] = 0; 
                    }
                    $id_cliente = $resp->fields['id_cliente'];

                    $query = "SELECT SUM(valor) total,day(fecha) dia 
                       FROM `venta__servicios` 
                       INNER JOIN cliente__maestro ON cliente__maestro.id = id_cliente
                       WHERE month(fecha) = '$mes' AND year(fecha) = '$anio' AND venta__servicios.estado = 1 AND id_cliente = $id_cliente GROUP BY dia";
                    $resp2 =  $BD->consultar($query);

                    while(!$resp2->EOF)
                    {
                        for ($i=1; $i <= ultimoDiaMes($mes); $i++) 
                        { 
                            if( $i == $resp2->fields["dia"] )
                            {
                                $VentasMes['series'][$indice]['data'][$i-1] = (int) $resp2->fields["total"];
                                break;
                            }
                        } 
                        $resp2->MoveNext();
                    }
                    $resp->MoveNext();
                    $indice++;
             }

             echo json_encode( array( "VentasMes" => $VentasMes  ));
         break;

         case "DetalleCompraMes":
             $perfil = $_POST["perfil"];
             $mes = $_POST["mes"];
             $anio = $_POST["anio"];

             $titulo = "COMPRAS";

             $condicion_compra = "";
             switch ($perfil) 
             {
                 case 1:
                    $condicion = " AND id_cliente > 0"; 
                    $condicion2 = " AND id_cliente > 0";  
                    $condicion_compra = " AND id_cliente_prov =  $cliente";  
                    $titulo = "VENTAS";            
                 break;
                 case 3:
                    $query = "SELECT acceso FROM cliente__maestro WHERE id = $cliente";
                    $resp  = $BD->consultar($query);
                    if( $resp->fields["acceso"] == 1 )
                    {
                        $query = "SELECT GROUP_CONCAT(id) ids FROM `cliente__maestro` WHERE padre = $cliente";
                        $resp  = $BD->consultar($query);
                        $condicion = " AND id_cliente IN (".$resp->fields['ids'].",".$cliente.")";              
                    }
                    else
                    {
                        $condicion = " AND id_cliente IN (".$cliente.")";   
                    }
                    $condicion_compra = $condicion;
                    $condicion2 = " AND id_cliente = $cliente";
                 break; 
             } 

             $ComprasMes = array(); 
             $ComprasMes['titulo'] = "DETALLE $titulo POR MES";
             $ComprasMes['subtitulo'] = "";
             $ComprasMes['series'] = array();
             
             
             for ($i=1; $i <= ultimoDiaMes($mes); $i++) 
             { 
                $ComprasMes['categories'][] = $i;
             }

             
             $query = "SELECT id_cliente , concat( nombres , ' ' , apellidos ) cliente
                        FROM `acreditacion`
                        INNER JOIN cliente__maestro ON cliente__maestro.id = id_cliente
                        WHERE month(fecha_acre) = '$mes' AND year(fecha_acre) = '$anio' AND acreditacion.estado = 1 $condicion_compra GROUP BY id_cliente";
             
             $resp =  $BD->consultar($query);

             $indice = 0;
             while(!$resp->EOF)
             {
                    $ComprasMes['series'][$indice]['name'] = utf8_encode($resp->fields['cliente']);
                    $ComprasMes['series'][$indice]['data'] = array(); 
                    for ($i=1; $i <= ultimoDiaMes($mes) ; $i++) 
                    {
                          $ComprasMes['series'][$indice]['data'][] = 0; 
                    }
                    $id_cliente = $resp->fields['id_cliente'];

                    $query = "SELECT SUM(valor_acreditado) total,day(fecha_acre) dia  
                            FROM `acreditacion`
                            INNER JOIN cliente__maestro ON cliente__maestro.id = id_cliente
                            WHERE month(fecha_acre) = '$mes' AND year(fecha_acre) = '$anio' AND acreditacion.estado = 1 AND id_cliente = $id_cliente GROUP BY  dia";
                    $resp2 = $BD->consultar( $query );
                    while(!$resp2->EOF)
                    {
                        for ($i=1; $i <= ultimoDiaMes($mes); $i++) 
                        { 
                            if( $i == $resp2->fields["dia"] )
                            {
                                $ComprasMes['series'][$indice]['data'][$i-1] = (int) $resp2->fields["total"];
                                 
                                break;
                            }
                        } 
                        $resp2->MoveNext();
                    }
                    $resp->MoveNext();
                    $indice++;
             }

             echo json_encode( array( "ComprasMes" => $ComprasMes  ));
         break;

         case "DetalleVentaDia":
             $perfil = $_POST["perfil"];
             $fecha = $_POST["fecha"];

             $titulo = "VENTAS";


             $condicion = "";
             switch ($perfil) 
             {
                 case 1:
                    $condicion = " AND id_cliente > 0"; 
                    $condicion2 = " AND id_cliente > 0";  
                    $titulo = "TRANSACCIONES"; 
                 break;
                 case 3:
                    $query = "SELECT acceso FROM cliente__maestro WHERE id = $cliente";
                    $resp  = $BD->consultar($query);
                    if( $resp->fields["acceso"] == 1 )
                    {
                        $query = "SELECT GROUP_CONCAT(id) ids FROM `cliente__maestro` WHERE padre = $cliente";
                        $resp  = $BD->consultar($query);
                        $condicion = " AND id_cliente IN (".$resp->fields['ids'].",".$cliente.")";              
                    }
                    else
                    {
                        $condicion = " AND id_cliente IN (".$cliente.")";   
                    }

                    $condicion2 = " AND id_cliente = $cliente";
                 break; 
             } 

             $VentasDia = array(); 
             $VentasDia['series'] = array();
             $VentasDia['titulo'] = "DETALLE $titulo POR DIA";
             $VentasDia['subtitulo'] = "";
             
             for( $i = 0 ; $i <= 24 ; $i++ )
             {
                 if( $i <= 9 )
                    $aux = '0'.$i;
                 else 
                    $aux = $i;
                 $VentasDia['categories'][] = "$aux".":00";
             }
             

             $query = "SELECT id_cliente,concat( nombres , ' ' , apellidos ) cliente 
                       FROM `venta__servicios` 
                       INNER JOIN cliente__maestro ON cliente__maestro.id = id_cliente
                       WHERE fecha = '$fecha' AND venta__servicios.estado = 1 $condicion group by(id_cliente)";
             $resp =  $BD->consultar($query);
             $indice = 0;
             while(!$resp->EOF)
             { 
                $VentasDia['series'][$indice]['name'] = $resp->fields['cliente'];
                $VentasDia['series'][$indice]['data'] = array(); 
                for( $i = 0 ; $i <= 24 ; $i++ )
                {
                     $VentasDia['series'][$indice]['data'][$i] = 0;
                }
                $id_cliente = $resp->fields['id_cliente'];
                
                $query = "SELECT SUM(valor) total,concat( DATE_FORMAT(horaregistro, '%H'),':00') hour 
                          FROM `venta__servicios` 
                          WHERE fecha = '$fecha' AND estado = 1 AND id_cliente = $id_cliente group by hour";
                $resp2 =  $BD->consultar($query);
                $indice = 0;
                while(!$resp2->EOF)
                { 
                    for( $i = 0 ; $i <= 24 ; $i++ )
                    {
                        if( $i <= 9 )
                         $aux = '0'.$i.":00"; 
                        else 
                         $aux = $i.":00";

                        if( $aux == $resp2->fields["hour"] )
                        {
                           $VentasDia['series'][$indice]['data'][$i] = (int) $resp2->fields["total"];
                           break;
                        }  
                    }
                    $resp2->MoveNext();
                }                          
                $resp->MoveNext();
                $indice++;
             }   

             echo json_encode( array( "VentasDia" => $VentasDia  ));
         break;


         case "DetalleCompraDia":
             $perfil = $_POST["perfil"];
             $fecha = $_POST["fecha"];

             $titulo = "COMPRAS";

             $condicion = "";
             switch ($perfil) 
             {
                 case 1:
                    $condicion = " AND id_cliente > 0"; 
                    $condicion2 = " AND id_cliente > 0";  
                    $condicion_compra = " AND id_cliente_prov =  $cliente";    
                    $titulo = "VENTAS";            
                 break;
                 case 3:
                    $query = "SELECT acceso FROM cliente__maestro WHERE id = $cliente";
                    $resp  = $BD->consultar($query);
                    if( $resp->fields["acceso"] == 1 )
                    {
                        $query = "SELECT GROUP_CONCAT(id) ids FROM `cliente__maestro` WHERE padre = $cliente";
                        $resp  = $BD->consultar($query);
                        $condicion = " AND id_cliente IN (".$resp->fields['ids'].",".$cliente.")";              
                    }
                    else
                    {
                        $condicion = " AND id_cliente IN (".$cliente.")";   
                    }

                    $condicion2 = " AND id_cliente = $cliente";
                 break; 
             } 

             $VentasDia = array(); 
             $VentasDia['series'] = array();
             $VentasDia['titulo'] = "DETALLE $titulo POR DIA";
             $VentasDia['subtitulo'] = "";
             
             for( $i = 0 ; $i <= 24 ; $i++ )
             {
                 if( $i <= 9 )
                    $aux = '0'.$i;
                 else 
                    $aux = $i;
                 $VentasDia['categories'][] = "$aux".":00";
             } 

             $query = "SELECT id_cliente , concat( nombres , ' ' , apellidos ) cliente   
                               FROM `acreditacion` 
                               INNER JOIN cliente__maestro ON cliente__maestro.id = id_cliente
                               WHERE fecha_acre = '$fecha' AND acreditacion.estado = 1 $condicion_compra GROUP BY id_cliente";
            
             $resp =  $BD->consultar($query);
             $indice = 0;
             while(!$resp->EOF)
             { 
                $VentasDia['series'][$indice]['name'] = $resp->fields['cliente'];
                $VentasDia['series'][$indice]['data'] = array(); 
                for( $i = 0 ; $i <= 24 ; $i++ )
                {
                     $VentasDia['series'][$indice]['data'][$i] = 0;
                }

                $id_cliente = $resp->fields['id_cliente'];
                $query = "SELECT SUM(valor_acreditado) total,concat( DATE_FORMAT(hora_acre, '%H'),':00') hour
                               FROM `acreditacion` 
                               INNER JOIN cliente__maestro ON cliente__maestro.id = id_cliente
                               WHERE fecha_acre = '$fecha' AND acreditacion.estado = 1 AND id_cliente = $id_cliente GROUP BY hour";
           
                $resp2 =  $BD->consultar($query);
                while(!$resp2->EOF)
                { 
                    for( $i = 0 ; $i <= 24 ; $i++ )
                    {
                        if( $i <= 9 )
                         $aux = '0'.$i.":00"; 
                        else 
                         $aux = $i.":00";

                        if( $aux == $resp2->fields["hour"] )
                        {
                           $VentasDia['series'][$indice]['data'][$i] = (int) $resp2->fields["total"];
                           break;
                        }  
                    }             
                    $resp2->MoveNext();

                }                          
                $resp->MoveNext();
                $indice++;
             }   

             echo json_encode( array( "CompraDia" => $VentasDia  ));
         break;
     }
 }
 function ultimoDiaMes($mes)
 {
        switch($mes)
        {
             case 1:
               return 31;
             break; 
             
             case 2:
               if( $mes % 4 == 0 )
                   return 29;
               else
                   return 28;
             break;
             
             case 3:
               return 31;
             break;
             
             case 4:
               return 30;
             break;
             
             case 5:
               return 31;
             break;
             
             case 6:
               return 30;
             break;
             
             case 7:
               return 31;
             break;
   
             case 8:
               return 31;
             break;

             case 9:
               return 30;
             break;
             
             case 10:
               return 31;
             break;

             case 11:
               return 30;
             break;
             
             case 12:
               return 31;
             break;
        }
 }
?>