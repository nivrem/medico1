<?php

/*
 * SIEMPRE Sistemas (tm)
 * Copyright (C) 2012 MEGA BYTE TECNOLOGY, C.A. J-31726844-0 <info@siempresistemas.com>
 */

/**
 * Description of request
 *
 * @author programacion4
 */
class Request {
    
    /**
     * Devuelve el valor de un parametro pasado por GET o POST
     * @param string $parameter
     * @return $parameter
     */
    public static function getParameter($parameter) {
        if(isset($_REQUEST[$parameter])) {
            return $_REQUEST[$parameter];
        }
        else
            return '';
    }
    
    /**
     *  Retorna el parametro que se paso pero solo por POST
     *  @param type $parameter
     *  @return string
     */
    public static function getPostParameter($parameter){
        if(isset($_POST[$parameter])) {
            return $_POST[$parameter];
        }
        else
            return '';
    }
    
    /**
     *  Retorna el parametro pero que solo se paso por GET
     * @param type $parameter
     * @return string
     */
    public static function getGetParameter($parameter){
        if(isset($_GET[$parameter])) {
            return $_GET[$parameter];
        }
        else
            return '';
    }
    
    /**
     * Setea el valor de un parametro en GET, POST y REQUEST
     * @param type $key Clave a setear
     * @param type $parameter Valor a establecer
     */
    public static function setParameter($key, $parameter){
        $_GET[$key] = $parameter;
        $_POST[$key] = $parameter;
        $_REQUEST[$key] = $parameter;
    }
    
    public static function getSelf(){
        return $_SERVER["PHP_SELF"];
    }
    
    /**
     * Verifica si existe un parametro definido que fue pasado por GET o POST
     * @param string $parameter
     * @return boolean
     */
    public static function isDefined($parameter){
        return isset($_REQUEST[$parameter]);
    }
    
    public static function isEmpty($parameter){
         if(isset($_REQUEST[$parameter])){
            return empty($_REQUEST[$parameter]);
        }else{
            return true;
         }
    }

    /**
     * Devuelve el tipo de metodo por el cual enviaron los parametros
     * @return boolean
     */
    public static function isMethodGet() {
        if($_SERVER['REQUEST_METHOD'] == 'GET'){
            return true;
        }
        else{
            return false;
        }
    }
    
    public static function deleteParameter($parameter){
        unset($_REQUEST[$parameter]);
    }
    
    /**
     * Devuelve el tipo de metodo por el cual enviaron los parametros
     * @return boolean
     */
    public static function isMethodPost() {
        if($_SERVER['REQUEST_METHOD'] == 'POST'){
            return true;
        }
        else{
            return false;
        }
    }
    
    /**
     * Enlaza los datos enviados en la peticion anterior con un objeto pasado por parametro
     * @param object $object
     */
    public static function bindRequest(&$object){
        if(is_object($object)){
            $keys = get_object_vars($object);
            $keys = array_keys($keys);
            foreach($keys as $key){
                if(self::isDefined($key)){
                    $object->$key = self::getParameter($key);
                }
            }
        }
    }

    /**
     * Retorna el token del formulario
     * @return string
     */
    public static function getFormToken() {
        return $_SESSION['newtoken'];
    }

    /**
     * Retorna la accion realizada por un formulario
     * @return boolean || null
     */
    public static function getFormAction() {
        if(self::isDefined('action')){
            $action = $_REQUEST['action'];
            return $action;
        }
        else{
            return NULL;
        }
    }
    
    /**
     * Limpia el parametro que recibe
     * @param string El parametro a limpiar
     * @return string El parametro limpio
     */
    protected function clearParameters($param){
        //  TODO Optimizar funcion
        //  TODO Limpiar de ataque de sql inyection
        
        
        return addslashes($param);
    }
    
        /**
     * Verifica si el formulario fue reenviado
     * @param int parametro para identificar el envio de la data
     * @return boolean
     */
    public function actionBlock($parameter) {
        if(isset($_SESSION['sendForm'])) {
            if (self::getParameter($parameter) == $_SESSION['sendForm']) {
                return false;
            } else if (self::isDefined($parameter)){
                $_SESSION['sendForm'] = self::getParameter($parameter);
                return true;
            }else{
                return false;
            }
        } else {
            $_SESSION['sendForm'] = self::getParameter($parameter);
            return true;
        }
    }

}

?>
