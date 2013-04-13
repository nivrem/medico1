<?php

/*
 * SIEMPRE Sistemas (tm)
 * Copyright (C) 2012 MEGA BYTE TECNOLOGY, C.A. J-31726844-0 <info@siempresistemas.com>
 */

/**
 * 	\file       htdocs/core/class/commonobject.class.php
 * 	\ingroup    core
 * 	\brief      File of parent class of all other business classes (invoices, contracts, proposals, orders, ...)
 * 	\version    $rowid: commonobject.class.php,v 1.148 2011/07/31 23:45:14 eldy Exp $
 */

require_once(SIEMP_DOCUMENT_ROOT."/core/class/price.class.php");
require_once(SIEMP_DOCUMENT_ROOT."/core/class/string.class.php");
require_once(SIEMP_DOCUMENT_ROOT."/core/class/tva.class.php");
require_once(SIEMP_DOCUMENT_ROOT."/core/class/date.class.php");

/**
 * 	\class 		CommonObject
 * 	\brief 		Class of all other business classes (invoices, contracts, proposals, orders, ...)
 */
class CommonObject {
    
    protected $db;
    
    var $linkedObjectBlock;
    var $objectid;
    // Instantiate hook classe of thirdparty module
    var $hooks = array();

    function _getUri() {
        return __FILE__;
    }

    /**
     *    Constructeur de la classe
     *    @param	DB		Handler acces base de donnees
     */
    function CommonObject($DB) {
        $this->db = $DB;
    }

    /**
     *      \brief      Check if ref is used.
     * 		\return		int			<0 if KO, 0 if not found, >0 if found
     */
    function verifyNumRef() {
        global $conf;
        
        $sql = "SELECT rowid";
        $sql.= " FROM " . MAIN_DB_PREFIX . $this->table_element;
        $sql.= " WHERE ref = '" . $this->ref . "'";
        $sql.= " AND entity = " . $conf->entity;
        Syslog::log("CommonObject::verifyNumRef sql=" . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            return $num;
        } else {
            $this->error = $this->db->lasterror();
            Syslog::log("CommonObject::verifyNumRef " . $this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     *      Add a link between element $this->element and a contact
     *      @param      fk_socpeople        Id of contact to link
     *   	@param 		type_contact 		Type of contact (code or id)
     *      @param      source              external=Contact extern (llx_socpeople), internal=Contact intern (llx_user)
     *      @param      notrigger			Disable all triggers
     *      @return     int                 <0 if KO, >0 if OK
     */
    function add_contact($fk_socpeople, $type_contact, $source = 'external', $notrigger = 0) {
        global $user, $conf;

        Syslog::log("CommonObject::add_contact $fk_socpeople, $type_contact, $source");

        // Check parameters
        if ($fk_socpeople <= 0) {
            $this->error = Translate::trans("ErrorWrongValueForParameter", "1");
            Syslog::log("CommonObject::add_contact " . $this->error, LOG_ERR);
            return -1;
        }
        if (!$type_contact) {
            $this->error = Translate::trans("ErrorWrongValueForParameter", "2");
            Syslog::log("CommonObject::add_contact " . $this->error, LOG_ERR);
            return -2;
        }

        $id_type_contact = 0;
        if (is_numeric($type_contact)) {
            $id_type_contact = $type_contact;
        } else {
            // On recherche id type_contact
            $sql = "SELECT tc.rowid";
            $sql.= " FROM " . MAIN_DB_PREFIX . "c_type_contact as tc";
            $sql.= " WHERE element='" . $this->element . "'";
            $sql.= " AND entity = ".$conf->entity;
            $sql.= " AND source='" . $source . "'";
            $sql.= " AND code='" . $type_contact . "' AND active=1";
            $resql = $this->db->query($sql);
            if ($resql) {
                $obj = $this->db->fetch_object($resql);
                $id_type_contact = $obj->rowid;
            }
        }

        $datecreate = Date::now();
        
        // Insertion dans la base
        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "element_contact";
        $sql.= " (entity,element_id, fk_socpeople, datecreate, statut, fk_c_type_contact) ";
        $sql.= " VALUES (".$conf->entity. "," . $this->rowid . ", " . $fk_socpeople . " , ";
        $sql.= $this->db->idate($datecreate);
        $sql.= ", 4, '" . $id_type_contact . "' ";
        $sql.= ")";
        $this->db->begin();
        Syslog::log("CommonObject::add_contact sql=" . $sql);
        $resql = $this->db->query($sql);
        
        if ($resql) {
            if (!$notrigger) {
                if ($source == 'external') {
                    include_once(SIEMP_DOCUMENT_ROOT . "/contact/class/contact.class.php");
                    $this->objcontact = new Contact($this->db);
                    $this->objcontact->fetch($fk_socpeople);
                }

                if ($source == 'internal') {
                    include_once(SIEMP_DOCUMENT_ROOT . "/user/class/user.class.php");
                    $this->objcontact = new User($this->db);
                    $this->objcontact->fetch($fk_socpeople);
                }

                // Call triggers
                include_once(SIEMP_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                $interface = new Interfaces($this->db);
                $result = $interface->run_triggers(strtoupper($this->element) . '_ADD_CONTACT', $this, $user, $conf);
                if ($result < 0) {
                    $this->errors = $interface->errors;
                }
                // End call triggers
                $this->db->commit();
            }

            return 1;
        } else {
            if ($this->db->errno() == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
                $this->error = $this->db->errno();
                $this->db->rollback();
                return -2;
            } else {
                $this->error = $this->db->error();
                Syslog::log($this->error, LOG_ERR);
                $this->db->rollback();
                return -1;
            }
        }
    }

    /**
     *      Update a link to contact line
     *      @param      rowid               Id of line contact-element
     * 		@param		statut	            New status of link
     *      @param      type_contact_id     Id of contact type
     *      @return     int                 <0 if KO, >= 0 if OK
     */
    function update_contact($rowid, $statut, $type_contact_id) {
        global $conf;
        // Insertion dans la base
        $sql = "UPDATE " . MAIN_DB_PREFIX . "element_contact set";
        $sql.= " statut = " . $statut . ",";
        $sql.= " fk_c_type_contact = '" . $type_contact_id . "'";
        $sql.= " WHERE rowid = " . $rowid;
        $sql.= " AND entity = ".$conf->entity;
        $this->db->begin();
        // Retour
        if ($this->db->query($sql)) {
            $this->db->commit();
            return 0;
        } else {
            CommonObject::printError($this->db);
            $this->db->rollback();
            return -1;
        }
    }

    /**
     *    Delete a link to contact line
     *    @param      	rowid			Id of contact link line to delete
     *    @param		notrigger		Disable all triggers
     *    @return     	int				>0 if OK, <0 if KO
     */
    function delete_contact($rowid, $notrigger = 0) {
        global $user, $conf;

        $sql = "DELETE FROM " . MAIN_DB_PREFIX . "element_contact";
        $sql.= " WHERE rowid =" . $rowid;
        $sql.= " AND entity = ".$conf->entity;
        $this->db->begin();
        Syslog::log("CommonObject::delete_contact sql=" . $sql);
        if ($this->db->query($sql)) {
            if (!$notrigger) {
                // Call triggers
                include_once(SIEMP_DOCUMENT_ROOT . "/core/class/interfaces.class.php");
                $interface = new Interfaces($this->db);
                $result = $interface->run_triggers(strtoupper($this->element) . '_DELETE_CONTACT', $this, $user, $conf);
                if ($result < 0) {
                    $error++;
                    $this->errors = $interface->errors;
                }
                // End call triggers
                $this->db->commit();
            }

            return 1;
        } else {
            $this->error = $this->db->lasterror();
            Syslog::log("CommonObject::delete_contact error=" . $this->error, LOG_ERR);
            $this->db->rollback();
            return -1;
        }
    }

    /**
     *    Delete all links between an object $this and all its contacts
     *    @return     int	>0 if OK, <0 if KO
     */
    function delete_linked_contact() {
        global $conf;
        $temp = array();
        $typeContact = $this->liste_type_contact('');

        foreach ($typeContact as $key => $value) {
            array_push($temp, $key);
        }
        $listId = implode(",", $temp);

        $sql = "DELETE FROM " . MAIN_DB_PREFIX . "element_contact";
        $sql.= " WHERE element_id =" . $this->rowid;
        $sql.= " AND fk_c_type_contact IN (" . $listId . ")";
        $sql.= " AND entity = ".$conf->entity;
        $this->db->begin();
        Syslog::log("CommonObject::delete_linked_contact sql=" . $sql);
        if ($this->db->query($sql)) {
            $this->db->commit();
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            Syslog::log("CommonObject::delete_linked_contact error=" . $this->error, LOG_ERR);
            $this->db->rollback();
            return -1;
        }
    }

    /**
     *    Get array of all contacts for an object
     *    @param		statut		int          Status of lines to get (-1=all)
     *    @param		source		string       Source of contact: external or thirdparty (llx_socpeople) or internal (llx_user)
     *    @param		int         list         0:Return array contains all properties, 1:Return array contains just id
     *    @return		array		             Array of contacts
     */
    function liste_contact($statut = -1, $source = 'external', $list = 0) {
        global $conf;

        $tab = array();

        $sql = "SELECT ec.rowid, ec.statut, ec.fk_socpeople as id";
        if ($source == 'internal')
            $sql.=", '-1' as socid";
        if ($source == 'external' || $source == 'thirdparty')
            $sql.=", t.fk_soc as socid";
        $sql.= ", t.civilite as civility, t.name as lastname, t.firstname, t.email";
        $sql.= ", tc.source, tc.element, tc.code, tc.libelle";
        $sql.= " FROM " . MAIN_DB_PREFIX . "c_type_contact tc";
        $sql.= ", " . MAIN_DB_PREFIX . "element_contact ec";
        if ($source == 'internal')
            $sql.=" LEFT JOIN " . MAIN_DB_PREFIX . "user t on ec.fk_socpeople = t.rowid";
        if ($source == 'external' || $source == 'thirdparty')
            $sql.=" LEFT JOIN " . MAIN_DB_PREFIX . "socpeople t on ec.fk_socpeople = t.rowid";
        $sql.= " WHERE ec.element_id =" . $this->rowid;
        $sql.= " AND tc.entity = ".$conf->entity;
        $sql.= " AND ec.entity = ".$conf->entity;
        if ($source == 'internal') $sql.= " AND t.entity = ".$conf->entity;
        if ($source == 'external' || $source == 'thirdparty') $sql.= " AND t.entity = ".$conf->entity;
        $sql.= " AND ec.fk_c_type_contact=tc.rowid";
        $sql.= " AND tc.element='" . $this->element . "'";
        if ($source == 'internal')
            $sql.= " AND tc.source = 'internal'";
        if ($source == 'external' || $source == 'thirdparty')
            $sql.= " AND tc.source = 'external'";
        $sql.= " AND tc.active=1";
        if ($statut >= 0)
            $sql.= " AND ec.statut = '" . $statut . "'";
        $sql.=" ORDER BY t.name ASC";

        Syslog::log("CommonObject::liste_contact sql=" . $sql);
        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);

                if (!$list) {
                    $transkey = "TypeContact_" . $obj->element . "_" . $obj->source . "_" . $obj->code;
                    $libelle_type = (Translate::trans($transkey) != $transkey ? Translate::trans($transkey) : $obj->libelle);
                    $tab[$i] = array('source' => $obj->source, 'socid' => $obj->socid, 'id' => $obj->id,
                        'nom' => $obj->lastname, // For backward compatibility
                        'civility' => $obj->civility, 'lastname' => $obj->lastname, 'firstname' => $obj->firstname, 'email' => $obj->email,
                        'rowid' => $obj->rowid, 'code' => $obj->code, 'libelle' => $libelle_type, 'status' => $obj->statut);
                } else {
                    $tab[$i] = $obj->id;
                }

                $i++;
            }

            return $tab;
        } else {
            $this->error = $this->db->error();
            CommonObject::printError($this->db);
            return -1;
        }
    }

    /**
     *    Return fetch cursor of a contact
     *    FIXME We should never return an open db cursor
     *    @param      rowid      L'identifiant du contact
     *    @return     object     L'objet construit par SiempDb.fetch_object
     */
    function detail_contact($rowid) {
        global $conf;
        $sql = "SELECT ec.datecreate, ec.statut, ec.fk_socpeople, ec.fk_c_type_contact,";
        $sql.= " tc.code, tc.libelle,";
        $sql.= " s.fk_soc";
        $sql.= " FROM " . MAIN_DB_PREFIX . "c_type_contact as tc, " . MAIN_DB_PREFIX . "element_contact as ec";
        $sql.= " LEFT JOIN " . MAIN_DB_PREFIX . "socpeople as s ON (ec.fk_socpeople=s.rowid AND ec.entity = s.entity)"; // Si contact de type external, alors il est li� � une societe
        $sql.= " WHERE ec.rowid =" . $rowid;
        $sql.= " AND ec.entity = tc.entity";
        $sql.= " AND tc.entity = ".$conf->entity;
        $sql.= " AND ec.fk_c_type_contact=tc.rowid";
        $sql.= " AND tc.element = '" . $this->element . "'";
        //echo $sql;
        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);
            return $obj;
        } else {
            $this->error = $this->db->error();
            CommonObject::printError($this->db);
            return null;
        }
    }

    /**
     *      Return array with list of possible values for type of contacts
     *      @param      source      internal, external or all if not defined
     *      @param		order		Sort order by : code or rowid
     *      @param      option      0=Return array id->label, 1=Return array code->label
     *      @return     array       Array list of type of contacts (id->label if option=0, code->label if option=1)
     */
    function liste_type_contact($source = 'internal', $order = 'code', $option = 0) {
        global $conf;

        $tab = array();
        $sql = "SELECT DISTINCT tc.rowid, tc.code, tc.libelle";
        $sql.= " FROM " . MAIN_DB_PREFIX . "c_type_contact as tc";
        $sql.= " WHERE tc.element='" . $this->element . "'";
        $sql.= " AND tc.entity = ".$conf->entity;
        if (!empty($source))
            $sql.= " AND tc.source='" . $source . "'";
        $sql.= " ORDER by tc." . $order;

        //print "sql=".$sql;
        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);

                $transkey = "TypeContact_" . $this->element . "_" . $source . "_" . $obj->code;
                $libelle_type = (Translate::trans($transkey) != $transkey ? Translate::trans($transkey) : $obj->libelle);
                if (empty($option))
                    $tab[$obj->rowid] = $libelle_type;
                else
                    $tab[$obj->code] = $libelle_type;
                $i++;
            }
            return $tab;
        }
        else {
            $this->error = $this->db->lasterror();
            //CommonObject::printError($this->db);
            return null;
        }
    }

    /**
     *      Return id of contacts for a source and a contact code.
     *      Example: contact client de facturation ('external', 'BILLING')
     *      Example: contact client de livraison ('external', 'SHIPPING')
     *      Example: contact interne suivi paiement ('internal', 'SALESREPFOLL')
     * 		@param		source		'external' or 'internal'
     * 		@param		code		'BILLING', 'SHIPPING', 'SALESREPFOLL', ...
     * 		@param		status		limited to a certain status
     *      @return     array       List of id for such contacts
     */
    function getIdContact($source, $code, $status = 0) {
        global $conf;

        $result = array();
        $i = 0;

        $sql = "SELECT ec.fk_socpeople";
        $sql.= " FROM " . MAIN_DB_PREFIX . "element_contact as ec,";
        if ($source == 'internal')
            $sql.= " " . MAIN_DB_PREFIX . "user as c,";
        if ($source == 'external')
            $sql.= " " . MAIN_DB_PREFIX . "socpeople as c,";
        $sql.= " " . MAIN_DB_PREFIX . "c_type_contact as tc";
        $sql.= " WHERE ec.element_id = " . $this->rowid;
        $sql.= " AND ec.fk_socpeople = c.rowid";
        $sql.= " AND c.entity =" . $conf->entity;
        $sql.= " AND tc.entity = ".$conf->entity;
        if ($source == 'internal' || $source == 'external') 
            $sql.= " AND c.entity = ".$conf->entity;
        $sql.= " AND ec.fk_c_type_contact = tc.rowid";
        $sql.= " AND tc.element = '" . $this->element . "'";
        $sql.= " AND tc.source = '" . $source . "'";
        $sql.= " AND tc.code = '" . $code . "'";
        $sql.= " AND tc.active = 1";
        if ($status)
            $sql.= " AND ec.statut = " . $status;

        Syslog::log("CommonObject::getIdContact sql=" . $sql);
        $resql = $this->db->query($sql);
        if ($resql) {
            while ($obj = $this->db->fetch_object($resql)) {
                $result[$i] = $obj->fk_socpeople;
                $i++;
            }
        } else {
            $this->error = $this->db->error();
            Syslog::log("CommonObject::getIdContact " . $this->error, LOG_ERR);
            return null;
        }

        return $result;
    }

    /**
     * 		\brief      Charge le contact d'id $rowid dans this->contact
     * 		\param      contactid          Id du contact
     * 		\return		int			<0 if KO, >0 if OK
     */
    function fetch_contact($contactid) {
        require_once(SIEMP_DOCUMENT_ROOT . "/contact/class/contact.class.php");
        $contact = new Contact($this->db);
        $result = $contact->fetch($contactid);
        $this->contact = $contact;
        return $result;
    }

    /**
     *    	Load the third party of object from id $this->socid into this->thirdpary
     * 		@return		int			<0 if KO, >0 if OK
     */
    function fetch_thirdparty() {
        global $conf;

        if (empty($this->socid))
            return 0;

        $thirdparty = new Societe($this->db);
        $result = $thirdparty->fetch($this->socid);
        $this->client = $thirdparty;  // deprecated
        $this->thirdparty = $thirdparty;

        // Use first price level if level not defined for third party
        if ($conf->global->PRODUIT_MULTIPRICES && empty($this->thirdparty->price_level)) {
            $this->client->price_level = 1; // deprecated
            $this->thirdparty->price_level = 1;
        }

        return $result;
    }

    /**
     * 		\brief      Charge le project d'id $this->fk_project dans this->project
     * 		\return		int			<0 if KO, >=0 if OK
     */
    function fetch_projet() {
        if (empty($this->fk_project))
            return 0;

        $project = new Project($this->db);
        $result = $project->fetch($this->fk_project);
        $this->project = $project;
        return $result;
    }

    /**
     * 		\brief      Charge le user d'id userid dans this->user
     * 		\param      userid 		Id du contact
     * 		\return		int			<0 if KO, >0 if OK
     */
    function fetch_user($userid) {
        $user = new User($this->db);
        $result = $user->fetch($userid);
        $this->user = $user;
        return $result;
    }

    /**
     * 		Charge l'adresse d'id $this->fk_address dans this->address
     * 		@param      fk_address 		Id de l'adresse
     * 		@return		int				<0 if KO, >0 if OK
     */
    function fetch_address($fk_address) {
        $object = new Societe($this->db);
        $result = $object->fetch_address($fk_address);
        $this->deliveryaddress = $object; // TODO obsolete
        $this->adresse = $object;    // TODO obsolete
        $this->address = $object;
        return $result;
    }

    /**
     * 		Read linked origin object
     */
    function fetch_origin() {
        // TODO uniformise code
        if ($this->origin == 'shipping')
            $this->origin = 'expedition';
        if ($this->origin == 'delivery')
            $this->origin = 'livraison';

        $object = $this->origin;

        $classname = ucfirst($object);
        $this->$object = new $classname($this->db);
        $this->$object->fetch($this->origin_id);
    }

    /**
     *    	Load object from specific field
     *    	@param		table		Table element or element line
     *    	@param		field		Field selected
     *    	@param		key			Import key
     * 		@return		int			<0 if KO, >0 if OK
     */
    function fetchObjectFrom($table, $field, $key) {
        global $conf;

        $result = false;

        $sql = "SELECT rowid FROM " . MAIN_DB_PREFIX . $table;
        $sql.= " WHERE " . $field . " = '" . $key . "'";
        $sql.= " AND entity = " . $conf->entity;
        $resql = $this->db->query($sql);
        if ($resql) {
            $row = $this->db->fetch_row($resql);
            $result = $this->fetch($row[0]);
        }

        return $result;
    }
    
    /**
     * Cargar el objeto con el sql proporcionado
     * @param object $object Objeto al cual se le cargaran los campos
     * @param string $sql Sql que se ejecutara para traer los campos
     */
    function fetchObject(&$object,$sql){
        $resql = $this->db->query($sql);
        
        if ($resql) {
            if ($this->db->num_rows($resql)) {
                $object = $this->db->fetch_object($resql);
                if(is_object($object)){
                    $keys = get_object_vars($object);
                    $keys = array_keys($keys);
                    foreach($keys as $key){
                        $this->$key = $object->$key;
                    }
                }
                $this->db->free($resql);
                return true;
            }
            
            $this->error = "Record Not Found";
            return false;
        } else {
            $this->error = $this->db->error();
            Syslog::log("CommonObject::Fetch Error " . $this->error, LOG_ERR);
            return false;
        }
    }
    
    function fetchObjects($rs_object,&$object){
                if(is_object($rs_object)){
                    $keys = get_object_vars($rs_object);
                    $keys = array_keys($keys);
                    foreach($keys as $key){
                        $object->$key = $rs_object->$key;
                    }
                }
                return $object;
    }

    /**
     *    	Update a specific field from an object
     *    	@param		table		Table element or element line
     *    	@param		id			Object id
     *    	@param		field		Field to update
     *    	@param		value		New value
     * 		@return		int			<0 if KO, >0 if OK
     */
    public function updateField($field, $value, $rowid = 0, $table = '') {
        global $conf;

        $sql = "UPDATE " . MAIN_DB_PREFIX . (($table != '') ? $table : $this->table_element) . " SET ";
        $sql.= $field . " = '" . $this->db->escape($value) . "'";
        $sql.= " WHERE rowid = " . (($rowid != 0) ? $rowid : $this->rowid);
        $sql.= " AND entity = ".$conf->entity;
        
        $this->db->begin();
        Syslog::log("CommonObject::updateField sql=" . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            $this->db->commit();
            return 1;
        } else {
            $this->db->rollback();
            CommonObject::printError($this->db);
            return -1;
        }
    }

    /**
     *      \brief      Load properties id_previous and id_next
     *      \param      filter		Optional filter
     * 	 	\param      fieldid   	Name of field to use for the select MAX and MIN
     *      \return     int         <0 if KO, >0 if OK
     */
    function load_previous_next_ref($filter = '', $fieldid) {
        global $conf, $user;

        if (!$this->table_element) {
            CommonObject::printError('', "CommonObject::load_previous_next_ref was called on objet with property table_element not defined", LOG_ERR);
            return -1;
        }

        // this->ismultientitymanaged contains
        // 0=No test on entity, 1=Test with field entity, 2=Test with link by societe
        $alias = 's';
        if ($this->element == 'societe')
            $alias = 'te';

        $sql = "SELECT MAX(te." . $fieldid . ")";
        $sql.= " FROM " . MAIN_DB_PREFIX . $this->table_element . " as te";
        if ($this->ismultientitymanaged == 2 || ($this->element != 'societe' && empty($this->isnolinkedbythird) && empty($user->rights->societe->client->voir)))
            $sql.= ", " . MAIN_DB_PREFIX . "societe as s"; // If we need to link to societe to limit select to entity
        if (empty($this->isnolinkedbythird) && !$user->rights->societe->client->voir)
            $sql.= " LEFT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON " . $alias . ".rowid = sc.fk_soc";
        $sql.= " WHERE te." . $fieldid . " < '" . $this->db->escape($this->ref) . "'";
        if (empty($this->isnolinkedbythird) && !$user->rights->societe->client->voir)
            $sql.= " AND sc.fk_user = " . $user->rowid;
        if (!empty($filter))
            $sql.=" AND " . $filter;
        if ($this->ismultientitymanaged == 2 || ($this->element != 'societe' && empty($this->isnolinkedbythird) && !$user->rights->societe->client->voir))
            $sql.= ' AND te.fk_soc = s.rowid';   // If we need to link to societe to limit select to entity
        if ($this->ismultientitymanaged == 1)
            $sql.= " AND te.entity = ".$conf->entity;
        if ($this->ismultientitymanaged == 2 || ($this->element != 'societe' && empty($this->isnolinkedbythird) && empty($user->rights->societe->client->voir)))
            $sql.= " AND s.entity = ".$conf->entity;
        if (empty($this->isnolinkedbythird) && !$user->rights->societe->client->voir)
            $sql.= " AND sc.entity = ".$conf->entity;
        $result = $this->db->query($sql);
        
        if (!$result) {
            $this->error = $this->db->error();
            return -1;
        }
        $row = $this->db->fetch_row($result);
        $this->ref_previous = $row[0];


        $sql = "SELECT MIN(te." . $fieldid . ")";
        $sql.= " FROM " . MAIN_DB_PREFIX . $this->table_element . " as te";
        if ($this->ismultientitymanaged == 2 || ($this->element != 'societe' && empty($this->isnolinkedbythird) && !$user->rights->societe->client->voir))
            $sql.= ", " . MAIN_DB_PREFIX . "societe as s"; // If we need to link to societe to limit select to entity
        if (empty($this->isnolinkedbythird) && !$user->rights->societe->client->voir)
            $sql.= " LEFT JOIN " . MAIN_DB_PREFIX . "societe_commerciaux as sc ON " . $alias . ".rowid = sc.fk_soc";
        $sql.= " WHERE te." . $fieldid . " > '" . $this->db->escape($this->ref) . "'";
        if (empty($this->isnolinkedbythird) && !$user->rights->societe->client->voir)
            $sql.= " AND sc.fk_user = " . $user->rowid;
        if (!empty($filter))
            $sql.=" AND " . $filter;
        if ($this->ismultientitymanaged == 2 || ($this->element != 'societe' && empty($this->isnolinkedbythird) && !$user->rights->societe->client->voir))
            $sql.= ' AND te.fk_soc = s.rowid';   // If we need to link to societe to limit select to entity
        if ($this->ismultientitymanaged == 1)
           $sql.= " AND te.entity = ".$conf->entity;
        if ($this->ismultientitymanaged == 2 || ($this->element != 'societe' && empty($this->isnolinkedbythird) && !$user->rights->societe->client->voir))
            $sql.= " AND s.entity = ".$conf->entity;
        if (empty($this->isnolinkedbythird) && !$user->rights->societe->client->voir)
            $sql.= " AND sc.entity = ".$conf->entity;
        
        $result = $this->db->query($sql);
        if (!$result) {
            $this->error = $this->db->error();
            return -2;
        }
        $row = $this->db->fetch_row($result);
        $this->ref_next = $row[0];

        return 1;
    }

    /**
     *      \brief      Return list of id of contacts of project
     *      \param      source      Source of contact: external (llx_socpeople) or internal (llx_user) or thirdparty (llx_societe)
     *      \return     array		Array of id of contacts (if source=external or internal)
     * 								Array of id of third parties with at least one contact on project (if source=thirdparty)
     */
    function getListContactId($source = 'external') {
        $contactAlreadySelected = array();
        $tab = $this->liste_contact(-1, $source);
        $num = sizeof($tab);
        $i = 0;
        while ($i < $num) {
            if ($source == 'thirdparty')
                $contactAlreadySelected[$i] = $tab[$i]['socid'];
            else
                $contactAlreadySelected[$i] = $tab[$i]['id'];
            $i++;
        }
        return $contactAlreadySelected;
    }

    /**
     * 	\brief     	Link element with a project
     * 	\param     	projid		Project id to link element to
     * 	\return		int			<0 if KO, >0 if OK
     */
    function setProject($projectid) {
        global $conf;
        if (!$this->table_element) {
            Syslog::log("CommonObject::setProject was called on objet with property table_element not defined", LOG_ERR);
            return -1;
        }

        $sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element;
        if ($projectid)
            $sql.= ' SET fk_projet = ' . $projectid;
        else
            $sql.= ' SET fk_projet = NULL';
        $sql.= ' WHERE rowid = ' . $this->rowid;
        $sql.= " AND entity = ".$conf->entity;
        $this->db->begin();
        Syslog::log("CommonObject::setProject sql=" . $sql);
        if ($this->db->query($sql)) {
            $this->fk_project = $projectid;
            $this->db->commit();
            return 1;
        } else {
            CommonObject::printError($this->db);
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * 		\brief		Set last model used by doc generator
     * 		\param		user		User object that make change
     * 		\param		modelpdf	Modele name
     * 		\return		int			<0 if KO, >0 if OK
     */
    function setDocModel($user, $modelpdf) {
        global $conf;
        if (!$this->table_element) {
            Syslog::log("CommonObject::setDocModel was called on objet with property table_element not defined", LOG_ERR);
            return -1;
        }

        $newmodelpdf = String::trunc($modelpdf, 255);

        $sql = "UPDATE " . MAIN_DB_PREFIX . $this->table_element;
        $sql.= " SET model_pdf = '" . $this->db->escape($newmodelpdf) . "'";
        $sql.= " WHERE rowid = " . $this->rowid;
        $sql.= " AND entity = ".$conf->entity;
        // if ($this->element == 'facture') $sql.= " AND fk_statut < 2";
        // if ($this->element == 'propal')  $sql.= " AND fk_statut = 0";
        $this->db->begin();
        Syslog::log("CommonObject::setDocModel sql=" . $sql);
        $resql = $this->db->query($sql);
        if ($resql) {
            $this->modelpdf = $modelpdf;
            $this->db->commit();
            return 1;
        } else {
            CommonObject::printError($this->db);
            $this->db->rollback();
            return 0;
        }
    }

    /**
     *      Stocke un numero de rang pour toutes les lignes de detail d'un element qui n'en ont pas.
     * 		@param		renum		true to renum all already ordered lines, false to renum only not already ordered lines.
     */
    function line_order($renum = false, $rowidorder = 'ASC') {
        global $conf;
        if (!$this->table_element_line) {
            Syslog::log("CommonObject::line_order was called on objet with property table_element_line not defined", LOG_ERR);
            return -1;
        }
        if (!$this->fk_element) {
            Syslog::log("CommonObject::line_order was called on objet with property fk_element not defined", LOG_ERR);
            return -1;
        }

        $sql = 'SELECT count(rowid) FROM ' . MAIN_DB_PREFIX . $this->table_element_line;
        $sql.= ' WHERE ' . $this->fk_element . '=' . $this->rowid;
        $sql.= " AND entity = ".$conf->entity;
        if (!$renum)
            $sql.= ' AND rang = 0';
        if ($renum)
            $sql.= ' AND rang <> 0';
        $resql = $this->db->query($sql);
        if ($resql) {
            $row = $this->db->fetch_row($resql);
            $nl = $row[0];
        }
        if ($nl > 0) {
            $sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . $this->table_element_line;
            $sql.= ' WHERE ' . $this->fk_element . ' = ' . $this->rowid;
            $sql.= " AND entity = ".$conf->entity;
            $sql.= ' ORDER BY rang ASC, rowid ' . $rowidorder;
            $resql = $this->db->query($sql);
            if ($resql) {
                $num = $this->db->num_rows($resql);
                $i = 0;
                while ($i < $num) {
                    $row = $this->db->fetch_row($resql);
                    $this->updateRangOfLine($row[0], ($i + 1));
                    $i++;
                }
            }
        }
    }

    /**
     * Update a line to have a lower rank
     * @param $rowid
     */
    function line_up($rowid) {
        $this->line_order();

        // Get rang of line
        $rang = $this->getRangOfLine($rowid);

        // Update position of line
        $this->updateLineUp($rowid, $rang);
    }

    /**
     * Update a line to have a higher rank
     * @param $rowid
     */
    function line_down($rowid) {
        $this->line_order();

        // Get rang of line
        $rang = $this->getRangOfLine($rowid);

        // Get max value for rang
        $max = $this->line_max();

        // Update position of line
        $this->updateLineDown($rowid, $rang, $max);
    }

    /**
     * 	   Update position of line (rang)
     */
    function updateRangOfLine($rowid, $rang) {
        global $conf;
        $sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element_line . ' SET rang  = ' . $rang;
        $sql.= ' WHERE rowid = ' . $rowid;
        $sql.= " AND entity = ".$conf->entity;
        $this->db->begin();
        if (!$this->db->query($sql)) {
            $this->db->rollback();
            CommonObject::printError($this->db);
        }
        else{
            $this->db->commit();
        }
    }

    /**
     * 	   Update position of line with ajax (rang)
     */
    function line_ajaxorder($roworder) {
        $rows = explode(',', $roworder);
        $num = count($rows);

        for ($i = 0; $i < $num; $i++) {
            $this->updateRangOfLine($rows[$i], ($i + 1));
        }
    }

    /**
     * 	   Update position of line up (rang)
     */
    function updateLineUp($rowid, $rang) {
        global $conf;
        if ($rang > 1) {
            $sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element_line . ' SET rang = ' . $rang;
            $sql.= ' WHERE ' . $this->fk_element . ' = ' . $this->rowid;
            $sql.= " AND entity = ".$conf->entity;
            $sql.= ' AND rang = ' . ($rang - 1);
            $this->db->begin();
            if ($this->db->query($sql)) {
                $sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element_line . ' SET rang  = ' . ($rang - 1);
                $sql.= ' WHERE rowid = ' . $rowid;
                $sql.= " AND entity = ".$conf->entity;
                if (!$this->db->query($sql)) {
                    CommonObject::printError($this->db);
                    $this->db->rollback();
                }
                else{
                    $this->db->commit();
                }
            } else {
                CommonObject::printError($this->db);
                $this->db->rollback();
            }
        }
    }

    /**
     * 	   Update position of line down (rang)
     */
    function updateLineDown($rowid, $rang, $max) {
        global $conf;
        if ($rang < $max) {
            $sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element_line . ' SET rang = ' . $rang;
            $sql.= ' WHERE ' . $this->fk_element . ' = ' . $this->rowid;
            $sql.= ' AND rang = ' . ($rang + 1);
            $sql.= " AND entity = ".$conf->entity;
            $this->db->begin();
            if ($this->db->query($sql)) {
                $sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element_line . ' SET rang = ' . ($rang + 1);
                $sql.= ' WHERE rowid = ' . $rowid;
                $sql.= " AND entity = ".$conf->entity;
                if (!$this->db->query($sql)) {
                    CommonObject::printError($this->db);
                    $this->db->rollback();
                }else{
                    $this->db->commit();
                }
            } else {
                CommonObject::printError($this->db);
                $this->db->rollback();
            }
        }
    }

    /**
     * 	   Get position of line (rang)
     *     @return     int     Value of rang in table of lines
     */
    function getRangOfLine($rowid) {
        global $conf;
        $sql = 'SELECT rang FROM ' . MAIN_DB_PREFIX . $this->table_element_line;
        $sql.= ' WHERE rowid =' . $rowid;
        $sql.= " AND entity = ".$conf->entity;
        $resql = $this->db->query($sql);
        if ($resql) {
            $row = $this->db->fetch_row($resql);
            return $row[0];
        }
    }

    /**
     * 	   Get rowid of the line relative to its position
     *     @return     int     Rowid of the line
     */
    function getIdOfLine($rang) {
        global $conf;
        $sql = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . $this->table_element_line;
        $sql.= ' WHERE ' . $this->fk_element . ' = ' . $this->rowid;
        $sql.= ' AND rang = ' . $rang;
        $sql.= " AND entity = ".$conf->entity;
        $resql = $this->db->query($sql);
        if ($resql) {
            $row = $this->db->fetch_row($resql);
            return $row[0];
        }
    }

    /**
     * 	   Get max value used for position of line (rang)
     *     @result     int     Max value of rang in table of lines
     */
    function line_max($fk_parent_line = 0) {
        global $conf;
        // Search the last rang with fk_parent_line
        if ($fk_parent_line) {
            $sql = 'SELECT max(rang) FROM ' . MAIN_DB_PREFIX . $this->table_element_line;
            $sql.= ' WHERE ' . $this->fk_element . ' = ' . $this->rowid;
            $sql.= ' AND fk_parent_line = ' . $fk_parent_line;
            $sql.= " AND entity = ".$conf->entity;
            $resql = $this->db->query($sql);
            if ($resql) {
                $row = $this->db->fetch_row($resql);
                if (!empty($row[0])) {
                    return $row[0];
                } else {
                    return $this->getRangOfLine($fk_parent_line);
                }
            }
        }
        // If not, search the last rang of element
        else {
            $sql = 'SELECT max(rang) FROM ' . MAIN_DB_PREFIX . $this->table_element_line;
            $sql.= ' WHERE ' . $this->fk_element . ' = ' . $this->rowid;
            $sql.= " AND entity = ".$conf->entity;
            $resql = $this->db->query($sql);
            if ($resql) {
                $row = $this->db->fetch_row($resql);
                return $row[0];
            }
        }
    }

    /**
     *    \brief      Update private note of element
     *    \param      note			New value for note
     *    \return     int         	<0 if KO, >0 if OK
     */
    function update_note($note) {
        global $conf;
        if (!$this->table_element) {
            Syslog::log("CommonObject::update_note was called on objet with property table_element not defined", LOG_ERR);
            return -1;
        }

        $sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element;
        // TODO uniformize fields note_private
        if ($this->table_element == 'fichinter' || $this->table_element == 'project' || $this->table_element == 'projet_task') {
            $sql.= " SET note_private = '" . $this->db->escape($note) . "'";
        } else {
            $sql.= " SET note = '" . $this->db->escape($note) . "'";
        }
        $sql.= " WHERE rowid =" . $this->rowid;
        $sql.= " AND entity = ".$conf->entity;

        Syslog::log("CommonObject::update_note sql=" . $sql, LOG_DEBUG);
        if ($this->db->query($sql)) {
            $this->note = $note;
            return 1;
        } else {
            $this->error = $this->db->error();
            Syslog::log("CommonObject::update_note error=" . $this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     *    \brief      Update public note of element
     *    \param      note_public	New value for note
     *    \return     int         	<0 if KO, >0 if OK
     */
    function update_note_public($note_public) {
        global $conf;
        if (!$this->table_element) {
            Syslog::log("CommonObject::update_note_public was called on objet with property table_element not defined", LOG_ERR);
            return -1;
        }

        $sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element;
        $sql.= " SET note_public = '" . $this->db->escape($note_public) . "'";
        $sql.= " WHERE rowid =" . $this->rowid;
        $sql.= " AND entity = ".$conf->entity;
        $this->db->begin();
        Syslog::log("CommonObject::update_note_public sql=" . $sql);
        if ($this->db->query($sql)) {
            $this->note_public = $note_public;
            $this->db->commit();
            return 1;
        } else {
            $this->error = $this->db->error();
            $this->db->rollback();
            return -1;
        }
    }

    /**
     * 	Update total_ht, total_ttc and total_vat for an object (sum of lines)
     * 	@param	   exclspec          Exclude special product (product_type=9)
     *  @param     roundingadjust    -1=Use default method (MAIN_ROUNDOFTOTAL_NOT_TOTALOFROUND or 0), 0=Use total of rounding, 1=Use rounding of total
     * 	@return	   int               <0 if KO, >0 if OK
     */
    function update_price($exclspec = 0, $roundingadjust = -1) {
        global $conf;
        
        if ($roundingadjust < 0 && isset($conf->global->MAIN_ROUNDOFTOTAL_NOT_TOTALOFROUND))
            $roundingadjust = $conf->global->MAIN_ROUNDOFTOTAL_NOT_TOTALOFROUND;
        if ($roundingadjust < 0)
            $roundingadjust = 0;

        $err = 0;

        // List lines to sum
        $fieldtva = 'total_tva';
        $fieldlocaltax1 = 'total_localtax1';
        $fieldlocaltax2 = 'total_localtax2';
        if ($this->element == 'facture_fourn' || $this->element == 'invoice_supplier')
            $fieldtva = 'tva';

        $sql = 'SELECT qty, total_ht, ' . $fieldtva . ' as total_tva, ' . $fieldlocaltax1 . ' as total_localtax1, ' . $fieldlocaltax2 . ' as total_localtax2, total_ttc,';
        $sql.= ' tva_tx as vatrate';
        $sql.= ' FROM ' . MAIN_DB_PREFIX . $this->table_element_line;
        $sql.= ' WHERE ' . $this->fk_element . ' = ' . $this->rowid;
        $sql.= " AND entity = ".$conf->entity;
        //TODO Se comento porque fallaba
        //if ($exclspec)
            //$sql.= ' AND product_type <> 9';
        
        Syslog::log("CommonObject::update_price sql=" . $sql);
        
        $resql = $this->db->query($sql);
        if ($resql) {
            $this->total_ht = 0;
            $this->total_tva = 0;
            $this->total_localtax1 = 0;
            $this->total_localtax2 = 0;
            $this->total_ttc = 0;

            //  Descuento general de la factura
            $this->total_remise_proveedor = 0;

            //  IVA que se debe descontar
            $this->total_iva_remise_proveedor = 0;

            //	retencion de IVA
            $this->total_retencion_iva = 0;

            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);

                //  Total sin IVA 
                $this->total_ht += $obj->total_ht;

                //  Total del IVA, solo en valor del IVA
                $this->total_tva += $obj->total_tva;

                $this->total_localtax1 += $obj->total_localtax1;
                $this->total_localtax2 += $obj->total_localtax2;

                //  Total con IVA incluido
                $this->total_ttc += $obj->total_ttc;

                //  Sacar el descuento que es general de la factura
                //      este descuento se debe aplicar por cada fila
                if ($this->remise_proveedor > 0) {
                    $this->total_remise_proveedor += ($obj->total_ht * $this->remise_proveedor) / 100;

                    //  Debo calcular otra vez el IVA pero con el descuento general aplicado
                    $total_tmp = $obj->total_ht - (($obj->total_ht * $this->remise_proveedor) / 100);

                    //  Guardo los ivas con los descuentos
                    $this->total_iva_remise_proveedor += (($total_tmp * $obj->vatrate) / 100);
                }

                // TODO Also fill array by vat rate
                $varates[$this->vatrate][] = array('total_ht' => $obj->total_ht, 'total_tva' => $obj->total_tva, 'total_ttc' => $obj->total_ttc,
                    'total_localtax1' => $obj->total_localtax1, 'total_localtax2' => $obj->total_localtax2);
                $i++;
            }

            $this->db->free($resql);

            //  Si tiene descuento general debo recalcular el IVA
            if ($this->remise_proveedor > 0) {
                $this->total_ht -= $this->total_remise_proveedor;
                $this->total_tva = $this->total_iva_remise_proveedor;
                $this->total_ttc = $this->total_ht + $this->total_tva;
            }

            //  Si tiene asignado retencion de IVA entonces lo calculo, si y solo si no tiene la retencion ANULADA
            if ($this->tva_retencion_iva != -1) {
                $this->total_retencion_iva = ($this->tva_retencion_iva / 100) * $this->total_tva;
                //$this->total_ttc = $this->total_ttc - $this->total_tva;
                //$this->total_ttc = $this->total_ttc + ($this->total_tva - $this->total_retencion_iva);
            }

            // TODO
            if ($roundingadjust) {
                // For each vatrate, calculate if two method of calculation differs
                // If it differs
                if (1 == 2) {
                    // Adjust a line and update it
                }
            }

            // Now update field total_ht, total_ttc and tva
            $fieldht = 'total_ht';
            $fieldtva = 'tva';
            $fieldlocaltax1 = 'localtax1';
            $fieldlocaltax2 = 'localtax2';
            $fieldttc = 'total_ttc';
            if ($this->element == 'facture' || $this->element == 'facturerec')
                $fieldht = 'total';
            if ($this->element == 'facture_fourn' || $this->element == 'invoice_supplier')
                $fieldtva = 'total_tva';
            if ($this->element == 'propal')
                $fieldttc = 'total';
            // TODO REVISAR CODIGO
            /*
            if ($this->table_element == 'facture_fourn') {
                //  Si es factura de proveedor entonces guardo los totales de retenciones
                $sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element . ' SET';
                $sql .= " " . $fieldht . "='" . Price::price2num($this->total_ht) . "',";
                $sql .= " " . $fieldtva . "='" . Price::price2num($this->total_tva) . "',";
                $sql .= " " . $fieldlocaltax1 . "='" . Price::price2num($this->total_localtax1) . "',";
                $sql .= " " . $fieldlocaltax2 . "='" . Price::price2num($this->total_localtax2) . "',";
                $sql .= " " . $fieldttc . "='" . Price::price2num($this->total_ttc) . "',";
                $sql .= " total_retencion_iva='" . Price::price2num($this->total_retencion_iva) . "',";
                $sql .= " total_retencion_islr='" . Price::price2num($this->total_retencion_islr) . "',";
                $sql .= " total_remise_proveedor='" . Price::price2num($this->total_remise_proveedor) . "',";
                $sql .= " remise_proveedor='" . Price::price2num($this->remise_proveedor) . "'";
                $sql .= ' WHERE rowid = ' . $this->rowid;
                $sql.= " AND entity = ".$conf->entity;
            } else {
                $sql = 'UPDATE ' . MAIN_DB_PREFIX . $this->table_element . ' SET';
                $sql .= " " . $fieldht . "='" . Price::price2num($this->total_ht) . "',";
                $sql .= " " . $fieldtva . "='" . Price::price2num($this->total_tva) . "',";
                $sql .= " " . $fieldlocaltax1 . "='" . Price::price2num($this->total_localtax1) . "',";
                $sql .= " " . $fieldlocaltax2 . "='" . Price::price2num($this->total_localtax2) . "',";
                $sql .= " " . $fieldttc . "='" . Price::price2num($this->total_ttc) . "'";
                $sql .= ' WHERE rowid = ' . $this->rowid;
                $sql.= " AND entity = ".$conf->entity;
            }
            */
            
            $this->db->begin();
            Syslog::log("CommonObject::update_price sql=" . $sql);
            $resql = $this->db->query($sql);
            if ($resql) {
                $this->db->commit();
                return 1;
            } else {
                $this->error = $this->db->error();
                Syslog::log("CommonObject::update_price error=" . $this->error, LOG_ERR);
                $this->db->rollback();
                return -1;
            }
        } else {
            $this->error = $this->db->error();
            Syslog::log("CommonObject::update_price error=" . $this->error, LOG_ERR);
            return -1;
        }
    }

    /**
     * 	   Add objects linked in llx_element_element.
     *     @return         int         <=0 if KO, >0 if OK
     */
    function add_object_linked() {
        global $conf;
        $this->db->begin();

        $sql = "INSERT INTO " . MAIN_DB_PREFIX . "element_element (";
        $sql.= "fk_source";
        $sql.= ", sourcetype";
        $sql.= ", fk_target";
        $sql.= ", targettype";
        $sql.= ", entity";
        $sql.= ") VALUES (";
        $sql.= $this->origin_id;
        $sql.= ", '". $this->origin . "'";
        $sql.= ", " . $this->rowid;
        $sql.= ", '". $this->element . "'";
        $sql.= ", " . $conf->entity;
        $sql.= ")";
        
        Syslog::log("CommonObject::add_object_linked sql=" . $sql);
        if ($this->db->query($sql)) {
            $this->db->commit();
            return 1;
        } else {
            $this->error = $this->db->lasterror();
            $this->db->rollback();
            return 0;
        }
    }

    /**
     * 	   Fetch array of objects linked to current object. Links are loaded into this->linked_object array.
     *     @param  sourceid
     *     @param  sourcetype
     *     @param  targetid
     *     @param  targettype
     *     @param  clause			OR, AND
     */
    function fetchObjectLinked($sourceid = '', $sourcetype = '', $targetid = '', $targettype = '', $clause = 'OR') {
        global $conf;

        $this->linkedObjectsIds = array();
        $this->linkedObjects = array();

        $justsource = false;
        $justtarget = false;

        if (!empty($sourceid) && !empty($sourcetype) && empty($targetid) && empty($targettype))
            $justsource = true;
        if (empty($sourceid) && empty($sourcetype) && !empty($targetid) && !empty($targettype))
            $justtarget = true;

        $sourceid = (!empty($sourceid) ? $sourceid : $this->rowid );
        $targetid = (!empty($targetid) ? $targetid : $this->rowid );
        $sourcetype = (!empty($sourcetype) ? $sourcetype : (!empty($this->origin) ? $this->origin : $this->element ) );
        $targettype = (!empty($targettype) ? $targettype : $this->element );

        // Links beetween objects are stored in this table
        $sql = 'SELECT fk_source, sourcetype, fk_target, targettype';
        $sql.= ' FROM ' . MAIN_DB_PREFIX . 'element_element';
        $sql.= " WHERE ";
        if ($justsource || $justtarget) {
            if ($justsource)
                $sql.= "fk_source = '" . $sourceid . "' AND sourcetype = '" . $sourcetype . "'";
            if ($justtarget)
                $sql.= "fk_target = '" . $targetid . "' AND targettype = '" . $targettype . "'";
        }
        else {
            $sql.= "(fk_source = '" . $sourceid . "' AND sourcetype = '" . $sourcetype . "')";
            $sql.= " " . $clause . " (fk_target = '" . $targetid . "' AND targettype = '" . $targettype . "')";
        }
        $sql.= " AND entity = ".$conf->entity;
        //print $sql;

        Syslog::log("CommonObject::fetchObjectLink sql=" . $sql);
        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);
                if ($obj->fk_source == $sourceid) {
                    $this->linkedObjectsIds[$obj->targettype][] = $obj->fk_target;
                }
                if ($obj->fk_target == $targetid) {
                    $this->linkedObjectsIds[$obj->sourcetype][] = $obj->fk_source;
                }
                $i++;
            }

            if (!empty($this->linkedObjectsIds)) {
                foreach ($this->linkedObjectsIds as $objecttype => $objectids) {
                    // Parse element/subelement (ex: project_task)
                    $module = $element = $subelement = $objecttype;
                    if ($objecttype != 'order_supplier' && $objecttype != 'invoice_supplier' && preg_match('/^([^_]+)_([^_]+)/i', $objecttype, $regs)) {
                        $module = $element = $regs[1];
                        $subelement = $regs[2];
                    }

                    $classpath = $element . '/class';

                    // To work with non standard path
                    if ($objecttype == 'facture') {
                        $classpath = 'compta/facture/class';
                    }
                    if ($objecttype == 'propal') {
                        $classpath = 'comm/propal/class';
                    }
                    if ($objecttype == 'shipping') {
                        $classpath = 'expedition/class';
                        $subelement = 'expedition';
                        $module = 'expedition_bon';
                    }
                    if ($objecttype == 'delivery') {
                        $classpath = 'livraison/class';
                        $subelement = 'livraison';
                        $module = 'livraison_bon';
                    }
                    if ($objecttype == 'invoice_supplier') {
                        $classpath = 'fourn/class';
                        $module = 'fournisseur';
                    }
                    if ($objecttype == 'order_supplier') {
                        $classpath = 'fourn/class';
                        $module = 'fournisseur';
                    }
                    if ($objecttype == 'fichinter') {
                        $classpath = 'fichinter/class';
                        $subelement = 'fichinter';
                        $module = 'ficheinter';
                    }

                    // TODO ajout temporaire - MAXIME MANGIN
                    if ($objecttype == 'contratabonnement') {
                        $classpath = 'contrat/class';
                        $subelement = 'contrat';
                        $module = 'contratabonnement';
                    }

                    $classfile = strtolower($subelement);
                    $classname = ucfirst($subelement);
                    if ($objecttype == 'invoice_supplier') {
                        $classfile = 'fournisseur.facture';
                        $classname = 'FactureFournisseur';
                    }
                    if ($objecttype == 'order_supplier') {
                        $classfile = 'fournisseur.commande';
                        $classname = 'CommandeFournisseur';
                    }

                    if ($conf->$module->enabled && $element != $this->element) {
                        siemp_include_once('/' . $classpath . '/' . $classfile . '.class.php');

                        $num = sizeof($objectids);

                        for ($i = 0; $i < $num; $i++) {
                            $object = new $classname($this->db);
                            $ret = $object->fetch($objectids[$i]);
                            if ($ret >= 0) {
                                $this->linkedObjects[$objecttype][$i] = $object;
                            }
                        }
                        return $this->linkedObjects;
                    }
                }
            }
        } else {
            CommonObject::printError($this->db);
        }
    }

    /**
     *      Set statut of an object
     *      @param		statut			Statut to set
     *      @param		elementId		Id of element to force (use this->id by default)
     *      @param		elementType		Type of element to force (use ->this->element by default)
     *      @return     int				<0 if ko, >0 if ok
     */
    function setStatut($statut, $elementId = '', $elementType = '') {
        global $conf;
        $elementId = (!empty($elementId) ? $elementId : $this->rowid);
        $elementTable = (!empty($elementType) ? $elementType : $this->table_element);

        $sql = "UPDATE " . MAIN_DB_PREFIX . $elementTable;
        $sql.= " SET fk_statut = " . $statut;
        $sql.= " WHERE rowid=" . $elementId;
        $sql.= " AND entity = ".$conf->entity;
        $this->db->begin();
        Syslog::log("CommonObject::setStatut sql=" . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if (!$resql) {
            $this->error = $this->db->lasterror();
            Syslog::log("CommonObject::setStatut " . $this->error, LOG_ERR);
            $this->db->rollback();
            return -1;
        }else{
            $this->db->commit();
        }

        return 1;
    }

    /**
     * 	Fetch field list
     */
    function getFieldList() {
        global $conf;

        $this->field_list = array();

        $sql = "SELECT rowid, name, alias, title, align, sort, search, enabled, rang";
        $sql.= " FROM " . MAIN_DB_PREFIX . "c_field_list";
        $sql.= " WHERE element = '" . $this->fieldListName . "'";
        $sql.= " AND entity = " . $conf->entity;
        $sql.= " ORDER BY rang ASC";

        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);

            $i = 0;
            while ($i < $num) {
                $fieldlist = array();

                $obj = $this->db->fetch_object($resql);

                $fieldlist["id"] = $obj->rowid;
                $fieldlist["name"] = $obj->name;
                $fieldlist["alias"] = $obj->alias;
                $fieldlist["title"] = Translate::trans($obj->title);
                $fieldlist["align"] = $obj->align;
                $fieldlist["sort"] = $obj->sort;
                $fieldlist["search"] = $obj->search;
                $fieldlist["enabled"] = verifCond($obj->enabled);
                $fieldlist["order"] = $obj->rang;

                array_push($this->field_list, $fieldlist);

                $i++;
            }
            $this->db->free($resql);
        } else {
            CommonObject::printError($db, $sql);
        }
    }

    /**
     *  Load type of canvas of an object
     *  @param      id      Record id
     *  @param      ref     Record ref
     */
    function getCanvas($rowid = 0, $ref = '') {
        global $conf;

        $ref = trim($ref);

        $sql = "SELECT rowid, canvas";
        $sql.= " FROM " . MAIN_DB_PREFIX . $this->table_element;
        $sql.= " WHERE entity = " . $conf->entity;
        if (!empty($rowid))
            $sql.= " AND rowid = " . $rowid;
        if (!empty($ref))
            $sql.= " AND ref = '" . $ref . "'";
        $sql.= " AND entity = ".$conf->entity;

        $resql = $this->db->query($sql);
        if ($resql) {
            $obj = $this->db->fetch_object($resql);

            $this->rowid = $obj->rowid;
            $this->canvas = $obj->canvas;

            return 1;
        } else {
            CommonObject::printError($this->db);
            return -1;
        }
    }

    /**
     * 	Init array this->hooks with instantiated controler and/or dao
     * 	@param	     arraytype	      Array list of hooked tab/features. For example: thirdpartytab, ...
     */
    function callHooks($arraytype) {
        global $conf;

        if (!is_array($arraytype))
            $arraytype = array($arraytype);

        $i = 0;

        foreach ($conf->hooks_modules as $module => $hooks) {
            if ($conf->$module->enabled) {
                foreach ($arraytype as $type) {
                    if (in_array($type, $hooks)) {
                        $path = '/' . $module . '/class/';
                        $actionfile = 'actions_' . $module . '.class.php';
                        $daofile = 'dao_' . $module . '.class.php';
                        $pathroot = '';

                        $this->hooks[$i]['type'] = $type;

                        // Include actions class (controller)
                        //print 'include '.$path.$actionfile."\n";
                        $resaction = siemp_include_once($path . $actionfile);

                        // Include dataservice class (model)
                        //print 'include '.$path.$daofile."\n";
                        $resdao = siemp_include_once($path . $daofile);

                        // Instantiate actions class (controller)
                        if ($resaction) {
                            $controlclassname = 'Actions' . ucfirst($module);
                            $objModule = new $controlclassname($this->db);
                            $this->hooks[$i]['modules'][$objModule->module_number] = $objModule;
                        }

                        // TODO storing dao is useless here. It's goal of controller to known which dao to manage
                        if ($resdao) {
                            // Instantiate dataservice class (model)
                            $modelclassname = 'Dao' . ucfirst($module);
                            $this->hooks[$i]['modules'][$objModule->module_number]->object = new $modelclassname($this->db);
                        }

                        $i++;
                    }
                }
            }
        }
    }

    /**
     * 	Get special code of line
     * 	@param		lineid		Id of line
     */
    function getSpecialCode($lineid) {
        global $conf;
        $sql = 'SELECT special_code FROM ' . MAIN_DB_PREFIX . $this->table_element_line;
        $sql.= ' WHERE rowid = ' . $lineid;
        $sql.= " AND entity = ".$conf->entity;
        $resql = $this->db->query($sql);
        if ($resql) {
            $row = $this->db->fetch_row($resql);
            return $row[0];
        }
    }

    /**
     *  Function to get extra fields of a member into $this->array_options
     *  @param      rowid
     *  @param      optionsArray    Array resulting of call of extrafields->fetchNameOptionalsLabel()
     */
    function fetchOptionals($rowid, $optionsArray = '') {
        global $conf;
        if (!is_array($optionsArray)) {
            // optionsArray not already loaded, so we load it
            require_once(SIEMP_DOCUMENT_ROOT . "/core/class/extrafields.class.php");
            $extrafields = new ExtraFields($this->db);
            $optionsArray = $extrafields->fetchNameOptionalsLabel();
        }

        // Request to get complementary values
        if (sizeof($optionsArray) > 0) {
            $sql = "SELECT rowid";
            foreach ($optionsArray as $name => $label) {
                $sql.= ", " . $name;
            }
            $sql.= " FROM " . MAIN_DB_PREFIX . $this->table_element . "_extrafields";
            $sql.= " WHERE fk_object = " . $rowid;
            $sql.= " AND entity = ".$conf->entity;

            Syslog::log(get_class($this) . "::fetchOptionals sql=" . $sql, LOG_DEBUG);
            $resql = $this->db->query($sql);
            if ($resql) {
                if ($this->db->num_rows($resql)) {
                    $tab = $this->db->fetch_array($resql);

                    foreach ($tab as $key => $value) {
                        if ($key != 'rowid' && $key != 'updated' && $key != 'fk_member') {
                            // we can add this attribute to adherent object
                            $this->array_options["options_$key"] = $value;
                        }
                    }
                }
                $this->db->free($resql);
            } else {
                CommonObject::printError($this->db);
            }
        }
    }

    // --------------------
    // TODO: All functions here must be redesigned and moved as they are not business functions but output functions
    // --------------------

    /**
     *
     * Enter description here ...
     * @param unknown_type $objectid
     * @param unknown_type $objecttype
     * @param unknown_type $withpicto
     * @param unknown_type $option
     */
    function getElementUrl($objectid, $objecttype, $withpicto = 0, $option = '') {
        global $conf;

        // Parse element/subelement (ex: project_task)
        $module = $element = $subelement = $objecttype;
        if (preg_match('/^([^_]+)_([^_]+)/i', $objecttype, $regs)) {
            $module = $element = $regs[1];
            $subelement = $regs[2];
        }

        $classpath = $element . '/class';

        // To work with non standard path
        if ($objecttype == 'facture' || $objecttype == 'invoice') {
            $classpath = 'compta/facture/class';
            $module = 'facture';
            $subelement = 'facture';
        }
        if ($objecttype == 'commande' || $objecttype == 'order') {
            $classpath = 'commande/class';
            $module = 'commande';
            $subelement = 'commande';
        }
        if ($objecttype == 'propal') {
            $classpath = 'comm/propal/class';
        }
        if ($objecttype == 'shipping') {
            $classpath = 'expedition/class';
            $subelement = 'expedition';
            $module = 'expedition_bon';
        }
        if ($objecttype == 'delivery') {
            $classpath = 'livraison/class';
            $subelement = 'livraison';
            $module = 'livraison_bon';
        }
        if ($objecttype == 'invoice_supplier') {
            $classpath = 'fourn/class';
        }
        if ($objecttype == 'order_supplier') {
            $classpath = 'fourn/class';
        }
        if ($objecttype == 'contract') {
            $classpath = 'contrat/class';
            $module = 'contrat';
            $subelement = 'contrat';
        }
        if ($objecttype == 'member') {
            $classpath = 'adherents/class';
            $module = 'adherent';
            $subelement = 'adherent';
        }

        //print "objecttype=".$objecttype." module=".$module." subelement=".$subelement;

        $classfile = strtolower($subelement);
        $classname = ucfirst($subelement);
        if ($objecttype == 'invoice_supplier') {
            $classfile = 'fournisseur.facture';
            $classname = 'FactureFournisseur';
        }
        if ($objecttype == 'order_supplier') {
            $classfile = 'fournisseur.commande';
            $classname = 'CommandeFournisseur';
        }

        if ($conf->$module->enabled) {
            siemp_include_once('/' . $classpath . '/' . $classfile . '.class.php');

            $object = new $classname($this->db);
            $ret = $object->fetch($objectid);
            if ($ret > 0)
                return $object->getNomUrl($withpicto, $option);
        }
    }

    /* This is to show linked object block */

    /**
     *  Show linked object block
     *  TODO Move this into html.class.php
     *  But for the moment we don't know if it's possible as we keep a method available on overloaded objects.
     */
    function showLinkedObjectBlock() {
        global $bc;

        $this->fetchObjectLinked();

        $num = sizeof($this->linkedObjects);

        foreach ($this->linkedObjects as $objecttype => $objects) {
            $tplpath = $element = $subelement = $objecttype;

            if ($objecttype != 'order_supplier' && $objecttype != 'invoice_supplier' && preg_match('/^([^_]+)_([^_]+)/i', $objecttype, $regs)) {
                $element = $regs[1];
                $subelement = $regs[2];
                $tplpath = $element . '/' . $subelement;
            }

            // To work with non standard path
            if ($objecttype == 'facture') {
                $tplpath = 'compta/' . $element;
            }
            if ($objecttype == 'propal') {
                $tplpath = 'comm/' . $element;
            }
            if ($objecttype == 'shipping') {
                $tplpath = 'expedition';
            }
            if ($objecttype == 'delivery') {
                $tplpath = 'livraison';
            }
            if ($objecttype == 'invoice_supplier') {
                $tplpath = 'fourn/facture';
            }
            if ($objecttype == 'order_supplier') {
                $tplpath = 'fourn/commande';
            }

            $this->linkedObjectBlock = $objects;

            siemp_include_once('/' . $tplpath . '/tpl/linkedobjectblock.tpl.php');
        }

        return $num;
    }

    /* This is to show add lines */

    /**
     * 	Show add predefined products/services form
     *  TODO Edit templates to use global variables and include them directly in controller call
     *  But for the moment we don't know if it's possible as we keep a method available on overloaded objects.
     *  @param          $dateSelector       1=Show also date range input fields
     *  @param			$seller				Object thirdparty who sell
     *  @param			$buyer				Object thirdparty who buy
     */
    function formAddPredefinedProduct($dateSelector, $seller, $buyer) {
        // Use global variables + $dateSelector + $seller and $buyer
        include(SIEMP_DOCUMENT_ROOT . '/core/tpl/predefinedproductline_create.tpl.php');
    }

    /**
     * 	Show add free products/services form
     *  TODO Edit templates to use global variables and include them directly in controller call
     *  But for the moment we don't know if it'st possible as we keep a method available on overloaded objects.
     *  @param          $dateSelector       1=Show also date range input fields
     */
    function formAddFreeProduct($dateSelector, $seller, $buyer) {
        // Use global variables + $dateSelector + $seller and $buyer
        include(SIEMP_DOCUMENT_ROOT . '/core/tpl/freeproductline_create.tpl.php');
    }
    
    /* This is to show array of line of details */

    /**
     * 	Return HTML table for object lines
     *  TODO Move this into an output class file (htmlline.class.php)
     *  If lines are into a template, title must also be into a template
     *  But for the moment we don't know if it'st possible as we keep a method available on overloaded objects.
     *  @param      $action				Action code
     *  @param      $seller            	Object of seller third party
     *  @param      $buyer             	Object of buyer third party
     *  @param		$selected		   	Object line selected
     *  @param      $dateSelector      	1=Show also date range input fields
     */
    function printObjectLines($action = 'viewline', $seller, $buyer, $selected = 0, $dateSelector = 0) {
        // TODO test using div instead of tables
        /*
          print '<div class="table" id="tablelines">';
          print '<div class="thead">';
          print '<div class="tr">';
          print '<div class="td firstcol">'.Translate::trans('Description').'</div>';
          print '<div class="td">'.Translate::trans('VAT').'</div>';
          print '<div class="td">'.Translate::trans('PriceUHT').'</div>';
          print '<div class="td">'.Translate::trans('Qty').'</div>';
          print '<div class="td">'.Translate::trans('ReductionShort').'</div>';
          print '<div class="td">'.Translate::trans('TotalHTShort').'</div>';
          print '<div class="td endcol">&nbsp;</div>';
          print '<div class="td endcol">&nbsp;</div>';
          print '<div class="td end">&nbsp;</div>';
          print '</div></div>';
         */

        print '<tr class="liste_titre nodrag nodrop">';
        print '<td>' . Translate::trans('Description') . '</td>';
        print '<td align="right" width="50">' . Translate::trans('VAT') . '</td>';
        print '<td align="right" width="80">' . Translate::trans('PriceUHT') . '</td>';
        print '<td align="right" width="50">' . Translate::trans('Qty') . '</td>';
        print '<td align="right" width="50">' . Translate::trans('ReductionShort') . '</td>';
        print '<td align="right" width="50">' . Translate::trans('TotalHTShort') . '</td>';
        print '<td width="10">&nbsp;</td>';
        print '<td width="10">&nbsp;</td>';
        print '<td nowrap="nowrap">&nbsp;</td>'; // No width to allow autodim
        print "</tr>\n";

        $num = count($this->lines);
        $var = true;
        $i = 0;

        //print '<div class="tbody">';

        foreach ($this->lines as $line) {
            $var = !$var;

            if (!empty($this->hooks) && ( ($line->product_type == 9 && !empty($line->special_code)) || !empty($line->fk_parent_line) )) {
                if (empty($line->fk_parent_line)) {
                    foreach ($this->hooks as $hook) {
                        if (method_exists($hook['modules'][$line->special_code], 'printObjectLine')) {
                            $hook['modules'][$line->special_code]->printObjectLine($action, $this, $line, $var, $num, $i, $dateSelector, $seller, $buyer, $selected);
                        }
                    }
                }
            } else {
                $this->printLine($action, $line, $var, $num, $i, $dateSelector, $seller, $buyer, $selected);
            }

            $i++;
        }

        //print '</div></div>';
    }

    /**
     * 	Return HTML content of a detail line
     *  TODO Move this into an output class file (htmlline.class.php)
     *  If lines are into a template, title must also be into a template
     *  But for the moment we don't know if it's possible as we keep a method available on overloaded objects.
     *
     *  @param		$action			   GET/POST action
     * 	@param	    $line		       Selected object line to output
     *  @param      $var               Is it a an odd line
     *  @param      $num               Number of line
     *  @param      $i
     *  @param      $dateSelector      1=Show also date range input fields
     *  @param      $seller            Object of seller third party
     *  @param      $buyer             Object of buyer third party
     *  @param		$selected		   Object line selected
     */
    function printLine($action = 'viewline', $line, $var = true, $num = 0, $i = 0, $dateSelector = 0, $seller, $buyer, $selected = 0) {
        global $conf;

        $element = $this->element;
        if ($element == 'propal')
            $element = 'propale';   // To work with non standard path

            
// Show product and description
        $type = $line->product_type ? $line->product_type : $line->fk_product_type;
        // Try to enhance type detection using date_start and date_end for free lines where type
        // was not saved.
        if (!empty($line->date_start))
            $type = 1;
        if (!empty($line->date_end))
            $type = 1;

        // Ligne en mode visu
        if ($action != 'editline' || $selected != $line->rowid) {
            // Produit
            if ($line->fk_product > 0) {
                $product_static = new Product($db);

                $product_static->type = $line->fk_product_type;
                $product_static->id = $line->fk_product;
                $product_static->ref = $line->ref;
                $product_static->libelle = $line->product_label;
                $text = $product_static->getNomUrl(1);
                $text.= ' - ' . $line->product_label;
                $description = ($conf->global->PRODUIT_DESC_IN_FORM ? '' : siemp_htmlentitiesbr($line->description));

                // Use global variables + $seller and $buyer
                include(SIEMP_DOCUMENT_ROOT . '/core/tpl/predefinedproductline_view.tpl.php');
                //include(SIEMP_DOCUMENT_ROOT.'/core/tpl/predefinedproductlinediv_view.tpl.php');
            } else {
                // Use global variables + $dateSelector + $seller and $buyer
                include(SIEMP_DOCUMENT_ROOT . '/core/tpl/freeproductline_view.tpl.php');
            }
        }

        // Ligne en mode update
        if ($this->statut == 0 && $action == 'editline' && $selected == $line->rowid) {
            if ($line->fk_product > 0) {
                // Use global variables + $dateSelector + $seller and $buyer
                include(SIEMP_DOCUMENT_ROOT . '/core/tpl/predefinedproductline_edit.tpl.php');
            } else {
                // Use global variables + $dateSelector + $seller and $buyer
                include(SIEMP_DOCUMENT_ROOT . '/core/tpl/freeproductline_edit.tpl.php');
            }
        }
    }

    /* This is to show array of line of details of source object */

    /**
     * 	Return HTML table table of source object lines
     *  TODO Move this and previous function into output html class file (htmlline.class.php).
     *  If lines are into a template, title must also be into a template
     *  But for the moment we don't know if it's possible as we keep a method available on overloaded objects.
     */
    function printOriginLinesList() {
        print '<tr class="liste_titre">';
        print '<td>' . Translate::trans('Ref') . '</td>';
        print '<td>' . Translate::trans('Description') . '</td>';
        print '<td align="right">' . Translate::trans('VAT') . '</td>';
        print '<td align="right">' . Translate::trans('PriceUHT') . '</td>';
        print '<td align="right">' . Translate::trans('Qty') . '</td>';
        print '<td align="right">' . Translate::trans('ReductionShort') . '</td></tr>';

        $num = count($this->lines);
        $var = true;
        $i = 0;

        foreach ($this->lines as $line) {
            $var = !$var;

            if (!empty($this->hooks) && ( ($line->product_type == 9 && !empty($line->special_code)) || !empty($line->fk_parent_line) )) {
                if (empty($line->fk_parent_line)) {
                    foreach ($this->hooks as $hook) {
                        if (method_exists($hook['modules'][$line->special_code], 'printOriginObjectLine'))
                            $hook['modules'][$line->special_code]->printOriginObjectLine($this, $line, $var, $i);
                    }
                }
            }
            else {
                $this->printOriginLine($line, $var);
            }

            $i++;
        }
    }

    /**
     * 	Return HTML with a line of table array of source object lines
     *  TODO Move this and previous function into output html class file (htmlline.class.php).
     *  If lines are into a template, title must also be into a template
     *  But for the moment we don't know if it's possible as we keep a method available on overloaded objects.
     * 	@param		line
     * 	@param		var
     */
    function printOriginLine($line, $var) {
        global $bc;

        //var_dump($line);

        $date_start = $line->date_debut_prevue;
        if ($line->date_debut_reel)
            $date_start = $line->date_debut_reel;
        $date_end = $line->date_fin_prevue;
        if ($line->date_fin_reel)
            $date_end = $line->date_fin_reel;

        $this->tpl['label'] = '';
        if (!empty($line->fk_parent_line))
            $this->tpl['label'].= Imagen::picto('', 'rightarrow');

        if (($line->info_bits & 2) == 2) {  // TODO Not sure this is used for source object
            $discount = new DiscountAbsolute($db);
            $discount->fk_soc = $this->socid;
            $this->tpl['label'].= $discount->getNomUrl(0, 'discount');
        } else if ($line->fk_product) {
            $productstatic = new Product($this->db);
            $productstatic->id = $line->fk_product;
            $productstatic->ref = $line->ref;
            $productstatic->type = $line->fk_product_type;
            $this->tpl['label'].= $productstatic->getNomUrl(1);
            $this->tpl['label'].= $line->label ? ' - ' . $line->label : '';
            // Dates
            if ($line->product_type == 1 && ($date_start || $date_end)) {
                $this->tpl['label'].= get_date_range($date_start, $date_end);
            }
        } else {
            $this->tpl['label'].= ($line->product_type == -1 ? '&nbsp;' : ($line->product_type == 1 ? Imagen::object(Translate::trans(''), 'service') : Imagen::object(Translate::trans(''), 'product')));
            $this->tpl['label'].= ($line->label ? '&nbsp;' . $line->label : '');
            // Dates
            if ($line->product_type == 1 && ($date_start || $date_end)) {
                $this->tpl['label'].= get_date_range($date_start, $date_end);
            }
        }

        if ($line->desc) {
            if ($line->desc == '(CREDIT_NOTE)') {  // TODO Not sure this is used for source object
                $discount = new DiscountAbsolute($this->db);
                $discount->fetch($line->fk_remise_except);
                $this->tpl['description'] = Translate::transnoentities("DiscountFromCreditNote", $discount->getNomUrl(0));
            } elseif ($line->desc == '(DEPOSIT)') {  // TODO Not sure this is used for source object
                $discount = new DiscountAbsolute($this->db);
                $discount->fetch($line->fk_remise_except);
                $this->tpl['description'] = Translate::transnoentities("DiscountFromDeposit", $discount->getNomUrl(0));
            } else {
                $this->tpl['description'] = String::trunc($line->desc, 60);
            }
        } else {
            $this->tpl['description'] = '&nbsp;';
        }

        $this->tpl['vat_rate'] = Tva::vatRate($line->tva_tx, true);
        $this->tpl['price'] = Price::price($line->subprice);
        $this->tpl['qty'] = (($line->info_bits & 2) != 2) ? $line->qty : '&nbsp;';
        $this->tpl['remise_percent'] = (($line->info_bits & 2) != 2) ? Tva::vatRate($line->remise_percent, true) : '&nbsp;';

        include(SIEMP_DOCUMENT_ROOT . '/core/tpl/originproductline.tpl.php');
    }

    /**
     *     Add/Update extra fields
     *     TODO Use also type of field to do manage date fields
     */
    function insertExtraFields() {
        global $conf;
        if (sizeof($this->array_options) > 0) {
            $this->db->begin();

            $sql_del = "DELETE FROM " . MAIN_DB_PREFIX . $this->table_element . "_extrafields WHERE fk_object = " . $this->rowid;
            $sql.= " AND entity = ".$conf->entity;
            Syslog::log(get_class($this) . "::insertExtraFields delete sql=" . $sql_del);
            $this->db->query($sql_del);

            $sql = "INSERT INTO " . MAIN_DB_PREFIX . $this->table_element . "_extrafields (entity,fk_object";
            foreach ($this->array_options as $key => $value) {
                // Add field of attribut
                $sql.="," . substr($key, 8);   // Remove 'options_' prefix
            }
            $sql .= ") VALUES (".$conf->entity."," . $this->rowid;
            foreach ($this->array_options as $key => $value) {
                // Add field o fattribut
                if ($this->array_options[$key] != '') {
                    $sql.=",'" . $this->array_options[$key] . "'";
                } else {
                    $sql.=",null";
                }
            }
            $sql.=")";

            Syslog::log(get_class($this) . "::insertExtraFields insert sql=" . $sql);
            $resql = $this->db->query($sql);
            if (!$resql) {
                $this->error = $this->db->lasterror();
                Syslog::log(get_class($this) . "::update " . $this->error, LOG_ERR);
                $this->db->rollback();
                return -1;
            } else {
                $this->db->commit();
                return 1;
            }
        }
        else
            return 0;
    }
    
    /**
     * 	\brief  Show informations on an object
     * 	\param	object			Objet to show
     */
    function objectInfo() {
        global $db;
        
        Translate::load("other");

        // Import key
        if (isset($this->import_key))
            print Translate::trans("ImportedWithSet") . " : " . $this->import_key . '<br>';

        // User creation
        if (isset($this->user_creation)) {
            print Translate::trans("CreatedBy") . " : ";
            if (is_object($this->user_creation)) {
                print $this->user_creation->getNomUrl(1);
            } else {
                $userstatic = new User($db);
                $userstatic->fetch($this->user_creation);
                print $userstatic->getNomUrl(1);
            }
            print '<br>';
        }

        // Date
        if (isset($this->date_creation))
            print Translate::trans("DateCreation") . " : " . Date::printDate($this->date_creation, "dayhourtext") . '<br>';

        // User change
        if (isset($this->user_modification)) {
            print Translate::trans("ModifiedBy") . " : ";
            if (is_object($this->user_modification)) {
                print $this->user_modification->getNomUrl(1);
            } else {
                $userstatic = new User($db);
                $userstatic->fetch($this->user_modification);
                print $userstatic->getNomUrl(1);
            }
            print '<br>';
        }

        // Date
        if (isset($this->date_modification))
            print Translate::trans("DateLastModification") . " : " . Date::printDate($this->date_modification, "dayhourtext") . '<br>';

        // User validation
        if (isset($this->user_validation)) {
            print Translate::trans("ValidatedBy") . " : ";
            if (is_object($this->user_validation)) {
                print $this->user_validation->getNomUrl(1);
            } else {
                $userstatic = new User($db);
                $userstatic->fetch($this->user_validation);
                print $userstatic->getNomUrl(1);
            }
            print '<br>';
        }

        // Date
        if (isset($this->date_validation))
            print Translate::trans("DateValidation") . " : " . Date::printDate($this->date_validation, "dayhourtext") . '<br>';

        // User close
        if (isset($this->user_cloture)) {
            print Translate::trans("ClosedBy") . " : ";
            if (is_object($this->user_cloture)) {
                print $this->user_cloture->getNomUrl(1);
            } else {
                $userstatic = new User($db);
                $userstatic->fetch($this->user_cloture);
                print $userstatic->getNomUrl(1);
            }
            print '<br>';
        }

        // Date
        if (isset($this->date_cloture))
            print Translate::trans("DateClosing") . " : " . Date::printDate($this->date_cloture, "dayhourtext") . '<br>';

        // User conciliate
        if (isset($this->user_rappro)) {
            print Translate::trans("ConciliatedBy") . " : ";
            if (is_object($this->user_rappro)) {
                print $this->user_rappro->getNomUrl(1);
            } else {
                $userstatic = new User($db);
                $userstatic->fetch($this->user_rappro);
                print $userstatic->getNomUrl(1);
            }
            print '<br>';
        }

        // Date
        if (isset($this->date_rappro))
            print Translate::trans("DateConciliating") . " : " . Date::printDate($this->date_rappro, "dayhourtext") . '<br>';

        //Date send
        if (isset($this->date_envoi))
            print Translate::trans("DateLastSend") . " : " . Date::printDate($this->date_envoi, "dayhourtext") . '<br>';
    }
    
    /**
     *  Complete or removed entries into a head array (used to build tabs) with value added by external modules
     *  @param      conf            Object conf
     *  @param      object          Object object
     *  @param      head            Object head
     *  @param      h               New position to fill
     *  @param      type            Value for object where objectvalue can be
     *                              'thirdparty'       to add a tab in third party view
     *                              'intervention'     to add a tab in intervention view
     *                              'supplier_order'   to add a tab in supplier order view
     *                              'supplier_invoice' to add a tab in supplier invoice view
     *                              'invoice'          to add a tab in customer invoice view
     *                              'order'            to add a tab in customer order view
     *                              'product'          to add a tab in product view
     *                              'propal'           to add a tab in propal view
     *                              'member'           to add a tab in fundation member view
     *                              'categories_x'	   to add a tab in category view ('x': type of category (0=product, 1=supplier, 2=customer, 3=member)
     *  @param      mode            'add' to complete head, 'remove' to remove entries
     */
    public static function headFromModules($conf, $object, &$head, &$h, $type, $mode = 'add') {

        if (isset($conf->tabs_modules[$type]) && is_array($conf->tabs_modules[$type])) {
            $i = 0;
            foreach ($conf->tabs_modules[$type] as $value) {
                $values = explode(':', $value);
                if ($mode == 'add' && !preg_match('/^\-/', $values[1])) {
                    if (sizeof($values) == 6) {       // new declaration with permissions
                        if ($values[0] != $type)
                            continue;
                        if (verifCond($values[4])) {
                            if ($values[3])
                                Translate::load($values[3]);
                            $head[$h][0] = siemp_buildpath(preg_replace('/__ID__/i', $object->rowid."&".$object->paramenu, $values[5]), 1);
                            $head[$h][1] = Translate::trans($values[2]);
                            $head[$h][2] = str_replace('+', '', $values[1]);
                            $h++;
                        }
                    }
                    else if (sizeof($values) == 5) {       // new declaration
                        if ($values[0] != $type)
                            continue;
                        if ($values[3])
                            Translate::load($values[3]);
                        $head[$h][0] = siemp_buildpath(preg_replace('/__ID__/i', $object->rowid."&".$object->paramenu, $values[4]), 1);
                        $head[$h][1] = Translate::trans($values[2]);
                        $head[$h][2] = str_replace('+', '', $values[1]);
                        $h++;
                    }
                    else if (sizeof($values) == 4) {   // old declaration, for backward compatibility
                        if ($values[0] != $type)
                            continue;
                        if ($values[2])
                            Translate::load($values[2]);
                        $head[$h][0] = siemp_buildpath(preg_replace('/__ID__/i', $object->rowid."&".$object->paramenu, $values[3]), 1);
                        $head[$h][1] = Translate::trans($values[1]);
                        $head[$h][2] = 'tab' . $values[1];
                        $h++;
                    }
                }
                else if ($mode == 'remove' && preg_match('/^\-/', $values[1])) {
                    if ($values[0] != $type)
                        continue;
                    $tabname = str_replace('-', '', $values[1]);
                    foreach ($head as $key => $val) {
                        if ($head[$key][2] == $tabname) {
                            //print 'on vire '.$tabname.' key='.$key;
                            unset($head[$key]);
                            break;
                        }
                    }
                }
            }
        }
    }
        
    /**
     * 	Affiche message erreur system avec toutes les informations pour faciliter le diagnostic et la remontee des bugs.
     * 	On doit appeler cette fonction quand une erreur technique bloquante est rencontree.
     * 	Toutefois, il faut essayer de ne l'appeler qu'au sein de pages php, les classes devant
     * 	renvoyer leur erreur par l'intermediaire de leur propriete "error".
     * 	@param      db      	Database handler
     * 	@param      error		String or array of errors strings to show
     *  @see        siemp_htmloutput_errors
     */
    public static function printError($db = '', $error = '') {
        
        global $conf, $argv;
        global $SIEMPRE_main_prod;

        $out = '';
        $syslog = '';

        Translate::load("main");
        Translate::load("errors");

        if ($_SERVER['DOCUMENT_ROOT']) {    // Mode web
            $out.=Translate::trans("SIEMPREHasDetectedError") . ".<br>\n";
            if (!empty($conf->global->MAIN_FEATURES_LEVEL))
                $out.="You use an experimental level of features, so please do NOT report any bugs, anywhere, until going back to MAIN_FEATURES_LEVEL = 0.<br>\n";
            $out.=Translate::trans("InformationToHelpDiagnose") . ":<br>\n";

            $out.="<b>" . Translate::trans("Date") . ":</b> " . Date::printDate(time(), 'dayhourlog') . "<br>\n";
            ;
            $out.="<b>" . Translate::trans("SIEMPRE") . ":</b> " . SIEMP_VERSION . "<br>\n";
            ;
            if (isset($conf->global->MAIN_FEATURES_LEVEL))
                $out.="<b>" . Translate::trans("LevelOfFeature") . ":</b> " . $conf->global->MAIN_FEATURES_LEVEL . "<br>\n";;
            if (function_exists("phpversion")) {
                $out.="<b>" . Translate::trans("PHP") . ":</b> " . phpversion() . "<br>\n";
                //phpinfo();       // This is to show location of php.ini file
            }
            $out.="<b>" . Translate::trans("Server") . ":</b> " . $_SERVER["SERVER_SOFTWARE"] . "<br>\n";
            ;
            $out.="<br>\n";
            $out.="<b>" . Translate::trans("RequestedUrl") . ":</b> " . $_SERVER["REQUEST_URI"] . "<br>\n";
            ;
            $out.="<b>" . Translate::trans("Referer") . ":</b> " . (isset($_SERVER["HTTP_REFERER"]) ? $_SERVER["HTTP_REFERER"] : '') . "<br>\n";
            ;
            $out.="<b>" . Translate::trans("MenuManager") . ":</b> " . $conf->top_menu . "<br>\n";
            $out.="<br>\n";
            $syslog.="url=" . $_SERVER["REQUEST_URI"];
            $syslog.=", query_string=" . $_SERVER["QUERY_STRING"];
        } else {                              // Mode CLI
            $out.='> ' . Translate::transnoentities("ErrorInternalErrorDetected") . ":\n" . $argv[0] . "\n";
            $syslog.="pid=" . getmypid();
        }

        if (is_object($db)) {
            if ($_SERVER['DOCUMENT_ROOT']) {  // Mode web
                $out.="<b>" . Translate::trans("DatabaseTypeManager") . ":</b> " . $db->type . "<br>\n";
                $out.="<b>" . Translate::trans("RequestLastAccessInError") . ":</b> " . ($db->lastqueryerror() ? $db->lastqueryerror() : Translate::trans("ErrorNoRequestInError")) . "<br>\n";
                $out.="<b>" . Translate::trans("ReturnCodeLastAccessInError") . ":</b> " . ($db->lasterrno() ? $db->lasterrno() : Translate::trans("ErrorNoRequestInError")) . "<br>\n";
                $out.="<b>" . Translate::trans("InformationLastAccessInError") . ":</b> " . ($db->lasterror() ? $db->lasterror() : Translate::trans("ErrorNoRequestInError")) . "<br>\n";
                $out.="<br>\n";
            } else {                            // Mode CLI
                $out.='> ' . Translate::transnoentities("DatabaseTypeManager") . ":\n" . $db->type . "\n";
                $out.='> ' . Translate::transnoentities("RequestLastAccessInError") . ":\n" . ($db->lastqueryerror() ? $db->lastqueryerror() : Translate::trans("ErrorNoRequestInError")) . "\n";
                $out.='> ' . Translate::transnoentities("ReturnCodeLastAccessInError") . ":\n" . ($db->lasterrno() ? $db->lasterrno() : Translate::trans("ErrorNoRequestInError")) . "\n";
                $out.='> ' . Translate::transnoentities("InformationLastAccessInError") . ":\n" . ($db->lasterror() ? $db->lasterror() : Translate::trans("ErrorNoRequestInError")) . "\n";
            }
            $syslog.=", sql=" . $db->lastquery();
            $syslog.=", db_error=" . $db->lasterror();
        }

        if ($error) {
            Translate::load("errors");

            if (is_array($error))
                $errors = $error;
            else
                $errors = array($error);

            foreach ($errors as $msg) {
                $msg = Translate::trans($msg);
                if ($_SERVER['DOCUMENT_ROOT']) {  // Mode web
                    $out.="<b>" . Translate::trans("Message") . ":</b> " . $msg . "<br>\n";
                } else {                            // Mode CLI
                    $out.='> ' . Translate::transnoentities("Message") . ":\n" . $msg . "\n";
                }
                $syslog.=", msg=" . $msg;
            }
        }
        if (empty($SIEMPRE_main_prod) && $_SERVER['DOCUMENT_ROOT'] && function_exists('xdebug_call_file')) {
            xdebug_print_function_stack();
            $out.='<b>XDebug informations:</b>' . "<br>\n";
            $out.='File: ' . xdebug_call_file() . "<br>\n";
            $out.='Line: ' . xdebug_call_line() . "<br>\n";
            $out.='Function: ' . xdebug_call_function() . "<br>\n";
            $out.="<br>\n";
        }

        if (empty($SIEMPRE_main_prod))
            print $out;
        else
            define("MAIN_CORE_ERROR", 1);
        //else print 'Sorry, an error occured but the parameter $SIEMPRE_main_prod is defined in conf file so no message is reported to your browser. Please read the log file for error message.';
        Syslog::log("Error " . $syslog, LOG_ERR);
    }
}
