<?php
/* 
 * SIEMPRE Sistemas (tm)
 * Copyright (C) 2012 MEGA BYTE TECNOLOGY, C.A. J-31726844-0 <info@siempresistemas.com>
 */

class Response {
    
    const CONTENT_TYPE_JSON = 'application/json';
    const CONTENT_TYPE_HTML = 'text/html';
    const CONTENT_TYPE_CSS = 'text/css';
    
    public static function setContentType($content_type){
        global $conf;
        
        header("Content-type: ".$content_type."; charset=".$conf->file->character_set_client);
        if (isset($conf->global->MAIN_OPTIMIZE_SPEED) && ($conf->global->MAIN_OPTIMIZE_SPEED & 0x04)) { ob_start("ob_gzhandler"); }
    }
    
    public static function redirect(){
        
    }
}

?>
