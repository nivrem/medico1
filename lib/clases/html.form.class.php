<?php

/*
 * SIEMPRE Sistemas (tm)
 * Copyright (C) 2012 MEGA BYTE TECNOLOGY, C.A. J-31726844-0 <info@siempresistemas.com>
 */

/**
 *  \file       htdocs/core/class/html.form.class.php
 *  \ingroup    core
 *  \brief      File of class with all html predefined components
 *  \version	$rowid: html.form.class.php,v 1.194 2011/08/04 21:46:51 eldy Exp $
 */

require_once(SIEMP_DOCUMENT_ROOT."/core/class/price.class.php");
require_once(SIEMP_DOCUMENT_ROOT."/core/class/string.class.php");
require_once(SIEMP_DOCUMENT_ROOT."/core/class/string.class.php");
require_once(SIEMP_DOCUMENT_ROOT."/core/class/tva.class.php");
require_once(SIEMP_DOCUMENT_ROOT."/core/class/date.class.php");
require_once(SIEMP_DOCUMENT_ROOT."/lib/propal.lib.php");

/**
 * 	\class      Form
 * 	\brief      Class to manage generation of HTML components
 * 	\remarks	Only common components must be here.
 */
class Form {
    
    const ACTION_SHOW = 'show';
    const ACTION_NEW = 'new';
    const ACTION_CREATE = 'create';
    const ACTION_EDIT = 'edit';
    const ACTION_UPDATE = 'update';
    const ACTION_DELETE = 'delete';
    
    var $db;
    var $error;
    // Cache arrays
    var $cache_types_paiements = array();
    var $cache_conditions_paiements = array();
    var $cache_availability = array();
    var $cache_demand_reason = array();
    var $tva_taux_value;
    var $tva_taux_libelle;

    /**
     * Constructor
     * @param      $DB      Database handler
     */
    function Form($DB) {
        $this->db = $DB;
    }

    /**
     * Output key field for an editable field
     * @param      text            Text of label
     * @param      htmlname        Name of select field
     * @param      preselected     Preselected value for parameter
     * @param      paramkey        Key of parameter (unique if there is several parameter to show)
     * @param      paramvalue      Value of parameter
     * @param      perm            Permission to allow button to edit parameter
     * @param      typeofdata      Type of data (string by default, email, ...)
     * @return     string          HTML edit field
     * TODO no GET or POST in class file, use a param
     */
    function editfieldkey($text, $htmlname, $preselected, $paramkey, $paramvalue, $perm, $typeofdata = 'string') {
        $ret = '';
        $ret.='<table class="nobordernopadding" width="100%"><tr><td nowrap="nowrap">';
        $ret.=Translate::trans($text);
        $ret.='</td>';
        if (Request::getParameter('action') != 'edit' . $htmlname && $perm)
            $ret.='<td align="right"><a href="' . $_SERVER["PHP_SELF"] . '?action=edit' . $htmlname . '&amp;' . $paramkey . '=' . $paramvalue . '">' . Imagen::edit(Translate::trans('Edit'), 1) . '</a></td>';
        $ret.='</tr></table>';
        return $ret;
    }

    /**
     * 	Output val field for an editable field
     * 	@param		text			Text of label (not used in this function)
     * 	@param		htmlname		Name of select field
     * 	@param		preselected		Preselected value for parameter
     * 	@param		paramkey		Key of parameter (unique if there is several parameter to show)
     * 	@param		perm			Permission to allow button to edit parameter
     * 	@param		typeofdata		Type of data ('string' by default, 'email', 'text', ...)
     * 	@param		editvalue		Use this value instead $preselected
     *  @return     string          HTML edit field
     *  TODO no GET or POST in class file, use a param
     */
    function editfieldval($text, $htmlname, $preselected, $paramkey, $paramvalue, $perm, $typeofdata = 'string', $editvalue = '') {
        $ret = '';
        if (Request::getParameter('action') == 'edit' . $htmlname) {
            $ret.="\n";
            $ret.='<form method="post" action="' . $_SERVER["PHP_SELF"] . '">';
            $ret.='<input type="hidden" name="action" value="set' . $htmlname . '">';
            $ret.='<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
            $ret.='<input type="hidden" name="' . $paramkey . '" value="' . $paramvalue . '">';
            
            $ret.= '<div class="input-append" style="margin: 0px;">';
                if (in_array($typeofdata, array('string', 'email'))) {
                    $ret.='<input class="input-small" type="text" name="' . $htmlname . '" value="' . ($editvalue ? $editvalue : $preselected) . '">';
                } else if ($typeofdata == 'text') {
                    $ret.='<textarea rows="3" name="' . $htmlname . '">' . ($editvalue ? $editvalue : $preselected) . '</textarea>';
                }
            
                $ret.= '<button class="btn btn-small" type="button">' . Translate::trans("Modify") . '</button>';
            $ret.= '</div>';
            $ret.='</form>' . "\n";
        } else {
            if ($typeofdata == 'email')
                $ret.= siemp_print_email($preselected, 0, 0, 0, 0, 1);
            else
                $ret.= $preselected;
        }
        return $ret;
    }

    /**
     * 	Show a text and picto with tooltip on text or picto
     * 	@param  text				Text to show
     * 	@param  htmltext	    	Content html of tooltip. Must be HTML/UTF8 encoded.
     * 	@param	tooltipon			1=tooltip sur texte, 2=tooltip sur picto, 3=tooltip sur les 2
     * 	@param	direction			-1=Le picto est avant, 0=pas de picto, 1=le picto est apres
     * 	@param	img					Code img du picto (use img_xxx() function to get it)
     *  @param  extracss            Add a CSS style to td tags
     *  @param  notabs              Do not include table and tr tags
     *  @param  incbefore			Include code before the text
     *  @param  noencodehtmltext    Do not encode into html entity the htmltext
     * 	@return	string				Code html du tooltip (texte+picto)
     * 	@see	Use function textwithpicto if you can.
     */
    static function textwithtooltip($text, $htmltext, $tooltipon = 1, $direction = 0, $img = '', $extracss = '', $notabs = 0, $incbefore = '', $noencodehtmltext = 0) {
        global $conf;

        if ($incbefore)
            $text = $incbefore . $text;
        if (!$htmltext)
            return $text;

        // Sanitize tooltip
        $htmltext = str_replace("\\", "\\\\", $htmltext);
        $htmltext = str_replace("\r", "", $htmltext);
        $htmltext = str_replace("\n", "", $htmltext);

        $htmltext = str_replace('"', "&quot;", $htmltext);
        if ($tooltipon == 2 || $tooltipon == 3)
            $paramfortooltipimg = ' class="classfortooltip' . ($extracss ? ' ' . $extracss : '') . '" title="' . ($noencodehtmltext ? $htmltext : String::escapeHtmlTag($htmltext, 1)) . '"'; // Attribut to put on td img tag to store tooltip
        else
            $paramfortooltipimg = ($extracss ? ' class="' . $extracss . '"' : ''); // Attribut to put on td text tag
        if ($tooltipon == 1 || $tooltipon == 3)
            $paramfortooltiptd = ' class="classfortooltip' . ($extracss ? ' ' . $extracss : '') . '" title="' . ($noencodehtmltext ? $htmltext : String::escapeHtmlTag($htmltext, 1)) . '"'; // Attribut to put on td tag to store tooltip
        else
            $paramfortooltiptd = ($extracss ? ' class="' . $extracss . '"' : ''); // Attribut to put on td text tag

        $s = "";
        if (empty($notabs))
            $s.='<table class="nobordernopadding" summary=""><tr>';
        if ($direction > 0) {
            if ($text != '') {
                $s.='<td' . $paramfortooltiptd . '>' . $text;
                if ($direction)
                    $s.='&nbsp;';
                $s.='</td>';
            }
            if ($direction)
                $s.='<td' . $paramfortooltipimg . ' valign="top" width="14">' . $img . '</td>';
        }
        else {
            if ($direction)
                $s.='<td' . $paramfortooltipimg . ' valign="top" width="14">' . $img . '</td>';
            if ($text != '') {
                $s.='<td' . $paramfortooltiptd . '>';
                if ($direction)
                    $s.='&nbsp;';
                $s.=$text . '</td>';
            }
        }
        if (empty($notabs))
            $s.='</tr></table>';

        return $s;
    }

    /**
     * 	Show a text with a picto and a tooltip on picto
     * 	@param     	text				Text to show
     * 	@param   	htmltooltip     	Content of tooltip
     * 	@param		direction			1=Icon is after text, -1=Icon is before text
     * 	@param		type				Type of picto (info, help, warning, superadmin...)
     *  @param  	extracss            Add a CSS style to td tags
     *  @param      noencodehtmltext    Do not encode into html entity the htmltext
     * 	@return		string				HTML code of text, picto, tooltip
     */
    function textwithpicto($text, $htmltext, $direction = 1, $type = 'help', $extracss = '', $noencodehtmltext = 0) {
        global $conf;

        if ($type == "0")
            $type = 'info'; // For backward compatibility

        $alt = '';
        
        // If info or help with smartphone, show only text
        if (!empty($conf->browser->phone)) {
            if ($type == 'info' || $type == 'help')
                return $text;
        }
        
        // Info or help
        if ($type == 'info')
            $img = Imagen::help(0, $alt);
        if ($type == 'help' || $type == 1)
            $img = Imagen::help(1, $alt);
        if ($type == 'superadmin')
            $img = Imagen::redStar($alt);
        if ($type == 'admin')
            $img = Imagen::picto($alt, "star");
        // Warnings
        if ($type == 'warning')
            $img = Imagen::warning($alt);

        return $this->textwithtooltip($text, $htmltext, 2, $direction, $img, $extracss, 0, '', $noencodehtmltext);
    }

    /**
     *    Return combo list of activated countries, into language of user
     *    @param     selected         Id or Code or Label of preselected country
     *    @param     htmlname         Name of html select object
     *    @param     htmloption       Options html on select object
     */
    function select_pays($selected = '', $htmlname = 'pays_id', $htmloption = '') {
        print $this->select_country($selected, $htmlname, $htmloption);
    }

    /**
     *    Return combo list of activated countries, into language of user
     *    @param     selected         Id or Code or Label of preselected country
     *    @param     htmlname         Name of html select object
     *    @param     htmloption       Options html on select object
     *    @return    string           HTML string with select
     */
    function select_country($selected = '', $htmlname = 'pays_id', $htmloption = '') {
        global $conf;

        Translate::load("dict");

        $out = '';
        $countryArray = array();
        $label = array();

        $sql = "SELECT rowid, code as code_iso, libelle as label";
        $sql.= " FROM " . MAIN_DB_PREFIX . "c_pays";
        $sql.= " WHERE active = 1";
        $sql.= " AND entity = ".$conf->entity;
        $sql.= " ORDER BY code ASC";
        //echo $sql;die;
        Syslog::log("Form::select_country sql=" . $sql);
        $resql = $this->db->query($sql);
        if ($resql) {
            $out.= '<select rowid="select' . $htmlname . '" class="flat selectpays" name="' . $htmlname . '" ' . $htmloption . '>';
            $num = $this->db->num_rows($resql);
            $i = 0;
            if ($num) {
                $foundselected = false;

                while ($i < $num) {
                    $obj = $this->db->fetch_object($resql);
                    $countryArray[$i]['rowid'] = $obj->rowid;
                    $countryArray[$i]['code_iso'] = $obj->code_iso;
                    $countryArray[$i]['label'] = ($obj->code_iso && Translate::trans("Country" . $obj->code_iso) != "Country" . $obj->code_iso ? Translate::trans("Country" . $obj->code_iso) : ($obj->label != '-' ? $obj->label : ''));
                    $label[$i] = $countryArray[$i]['label'];
                    $i++;
                }

                array_multisort($label, SORT_ASC, $countryArray);

                foreach ($countryArray as $row) {
                    if ($selected && $selected != '-1' && ($selected == $row['rowid'] || $selected == $row['code_iso'] || $selected == $row['label'])) {
                        $foundselected = true;
                        $out.= '<option value="' . $row['rowid'] . '" selected="selected">';
                    } else {
                        $out.= '<option value="' . $row['rowid'] . '">';
                    }
                    $out.= $row['label'];
                    if ($row['code_iso'])
                        $out.= ' (' . $row['code_iso'] . ')';
                    $out.= '</option>';
                }
            }
            $out.= '</select>';
        }
        else {
            CommonObject::printError($this->db);
        }

        return $out;
    }

    /**
     *    Retourne la liste des types de comptes financiers
     *    @param      selected        Type pre-selectionne
     *    @param      htmlname        Nom champ formulaire
     */
    function select_type_comptes_financiers($selected = 1, $htmlname = 'type') {
        Translate::load("banks");

        $type_available = array(0, 1, 2);

        print '<select class="flat" name="' . $htmlname . '">';
        $num = count($type_available);
        $i = 0;
        if ($num) {
            while ($i < $num) {
                if ($selected == $type_available[$i]) {
                    print '<option value="' . $type_available[$i] . '" selected="selected">' . Translate::trans("BankType" . $type_available[$i]) . '</option>';
                } else {
                    print '<option value="' . $type_available[$i] . '">' . Translate::trans("BankType" . $type_available[$i]) . '</option>';
                }
                $i++;
            }
        }
        print '</select>';
    }

    /**
     * 		Return list of social contributions.
     * 		Use mysoc->pays_id or mysoc->pays_code so they must be defined.
     * 		@param      selected        Preselected type
     * 		@param      htmlname        Name of field in form
     * 		@param		useempty		Set to 1 if we want an empty value
     * 		@param		maxlen			Max length of text in combo box
     * 		@param		help			Add or not the admin help picto
     */
    function select_type_socialcontrib($selected = '', $htmlname = 'actioncode', $useempty = 0, $maxlen = 40, $help = 1) {
        global $db, $user, $mysoc;

        if (empty($mysoc->pays_id) && empty($mysoc->pays_code)) {
            CommonObject::printError('', 'Call to select_type_socialcontrib with mysoc country not yet defined');
            exit;
        }

        if (!empty($mysoc->pays_id)) {
            $sql = "SELECT c.rowid, c.libelle as type";
            $sql.= " FROM " . MAIN_DB_PREFIX . "c_chargesociales as c";
            $sql.= " WHERE c.active = 1";
            $sql.= " AND c.fk_pays = " . $mysoc->pays_id;
            $sql.= " AND c.entity = ".$conf->entity;
            $sql.= " ORDER BY c.libelle ASC";
        } else {
            $sql = "SELECT c.rowid, c.libelle as type";
            $sql.= " FROM " . MAIN_DB_PREFIX . "c_chargesociales as c, " . MAIN_DB_PREFIX . "c_pays as p";
            $sql.= " WHERE c.active = 1 AND c.fk_pays = p.rowid";
            $sql.= " AND p.code = '" . $mysoc->pays_code . "'";
            $sql.= " AND c.entity = ".$conf->entity;
            $sql.= " AND p.entity = ".$conf->entity;
            $sql.= " ORDER BY c.libelle ASC";
        }

        Syslog::log("Form::select_type_socialcontrib sql=" . $sql, LOG_DEBUG);
        $resql = $db->query($sql);
        if ($resql) {
            $num = $db->num_rows($resql);
            if ($num) {
                print '<select class="flat" name="' . $htmlname . '">';
                $i = 0;

                if ($useempty)
                    print '<option value="0">&nbsp;</option>';
                while ($i < $num) {
                    $obj = $db->fetch_object($resql);
                    print '<option value="' . $obj->rowid . '"';
                    if ($obj->rowid == $selected)
                        print ' selected="selected"';
                    print '>' . String::trunc($obj->type, $maxlen);
                    $i++;
                }
                print '</select>';
                if ($user->admin && $help)
                    print Imagen::infoAdmin(Translate::trans("YouCanChangeValuesForThisListFromDictionnarySetup"), 1);
            }
            else {
                print Translate::trans("ErrorNoSocialContributionForSellerCountry", $mysoc->pays_code);
            }
        } else {
            CommonObject::printError($db, $db->lasterror());
        }
    }

    /**
     * 		Return list of types of lines (product or service)
     * 		Example: 0=product, 1=service, 9=other (for external module)
     * 		@param      selected        Preselected type
     * 		@param      htmlname        Name of field in html form
     * 		@param		showempty		Add an empty field
     * 		@param		hidetext		Do not show label before combo box
     * 		@param		forceall		Force to show products and services in combo list, whatever are activated modules
     */
    public static function select_type_of_lines($selected = '', $htmlname = 'type', $showempty = 0, $hidetext = 0, $forceall = 0) {
        global $conf;

        // If product & services are enabled or both disabled.
        if ($forceall || ($conf->product->enabled && $conf->service->enabled)
                || (empty($conf->product->enabled) && empty($conf->service->enabled))) {
            if (empty($hidetext))
                print Translate::trans("Type") . ': ';
            print '<select class="flat" name="' . $htmlname . '">';
            if ($showempty) {
                print '<option value="-1"';
                if ($selected == -1)
                    print ' selected="selected"';
                print '>&nbsp;</option>';
            }

            print '<option value="0"';
            if (0 == $selected)
                print ' selected="selected"';
            print '>' . Translate::trans("Product");

            print '<option value="1"';
            if (1 == $selected)
                print ' selected="selected"';
            print '>' . Translate::trans("Service");

            print '</select>';
            //if ($user->admin) print Imagen::infoAdmin(Translate::trans("YouCanChangeValuesForThisListFromDictionnarySetup"),1);
        }
        if (!$forceall && empty($conf->product->enabled) && $conf->service->enabled) {
            print '<input type="hidden" name="' . $htmlname . '" value="1">';
        }
        if (!$forceall && $conf->product->enabled && empty($conf->service->enabled)) {
            print '<input type="hidden" name="' . $htmlname . '" value="0">';
        }
    }

    /**
     * 		Return list of types of notes
     * 		@param      selected        Preselected type
     * 		@param      htmlname        Name of field in form
     * 		@param		showempty		Add an empty field
     */
    function select_type_fees($selected = '', $htmlname = 'type', $showempty = 0) {
        global $db, $user,$conf;
        Translate::load("trips");

        print '<select class="flat" name="' . $htmlname . '">';
        if ($showempty) {
            print '<option value="-1"';
            if ($selected == -1)
                print ' selected="selected"';
            print '>&nbsp;</option>';
        }

        $sql = "SELECT c.code, c.libelle as type FROM " . MAIN_DB_PREFIX . "c_type_fees as c";
        $sql.= " AND c.entity = ".$conf->entity;
        $sql.= " ORDER BY lower(c.libelle) ASC";
        $resql = $db->query($sql);
        if ($resql) {
            $num = $db->num_rows($resql);
            $i = 0;

            while ($i < $num) {
                $obj = $db->fetch_object($resql);
                print '<option value="' . $obj->code . '"';
                if ($obj->code == $selected)
                    print ' selected="selected"';
                print '>';
                if ($obj->code != Translate::trans($obj->code))
                    print Translate::trans($obj->code);
                else
                    print Translate::trans($obj->type);
                $i++;
            }
        }
        print '</select>';
        if ($user->admin)
            print Imagen::infoAdmin(Translate::trans("YouCanChangeValuesForThisListFromDictionnarySetup"), 1);
    }

    /**
     *    	Output html form to select a third party
     * 		@param      selected        Preselected type
     * 		@param      htmlname        Name of field in form
     *    	@param      filter          Optionnal filters criteras
     * 		@param		showempty		Add an empty field
     * 		@param		showtype		Show third party type in combolist (customer, prospect or supplier)
     * 		@param		forcecombo		Force to use combo box
     */
    function select_societes($selected = '', $htmlname = 'socid', $filter = '', $showempty = 0, $showtype = 0, $forcecombo = 0) {
        print $this->select_company($selected, $htmlname, $filter, $showempty, $showtype, $forcecombo);
    }

    /**
     *    	Output html form to select a third party
     * 		@param      selected        Preselected type
     * 		@param      htmlname        Name of field in form
     *    	@param      filter          Optionnal filters criteras
     * 		@param		showempty		Add an empty field
     * 		@param		showtype		Show third party type in combolist (customer, prospect or supplier)
     * 		@param		forcecombo		Force to use combo box
     */
    function select_company($selected = '', $htmlname = 'socid', $filter = '', $showempty = 0, $showtype = 0, $forcecombo = 0) {
        global $conf, $user;

        $out = '';

        // On recherche les societes
        $sql = "SELECT s.rowid, s.nom, s.client, s.fournisseur, s.code_client, s.code_fournisseur";
        $sql.= " FROM " . MAIN_DB_PREFIX . "societe as s";
        if (!$user->rights->societe->client->voir && !$user->societe_id)
            $sql .= ", " . MAIN_DB_PREFIX . "societe_commerciaux as sc";
        $sql.= " WHERE s.entity = " . $conf->entity;
        if ($filter)
            $sql.= " AND " . $filter;
        if (!$user->rights->societe->client->voir && !$user->societe_id)
            $sql.= " AND s.rowid = sc.fk_soc AND sc.fk_user = " . $user->rowid;
        $sql.= " AND s.entity = ".$conf->entity;
        if (!$user->rights->societe->client->voir && !$user->societe_id)
            $sql.= " AND sc.entity = ".$conf->entity;
        $sql.= " ORDER BY nom ASC";

        Syslog::log("Form::select_societes sql=" . $sql);
        $resql = $this->db->query($sql);
        if ($resql) {
            if ($conf->global->COMPANY_USE_SEARCH_TO_SELECT && !$forcecombo) {
                //$minLength = (is_numeric($conf->global->COMPANY_USE_SEARCH_TO_SELECT)?$conf->global->COMPANY_USE_SEARCH_TO_SELECT:2);

                $out.= ajax_combobox($htmlname);
            }

            $out.= '<select rowid="' . $htmlname . '" class="flat" name="' . $htmlname . '">';
            if ($showempty)
                $out.= '<option value="-1">&nbsp;</option>';
            $num = $this->db->num_rows($resql);
            $i = 0;
            if ($num) {
                while ($i < $num) {
                    $obj = $this->db->fetch_object($resql);
                    $label = $obj->nom;
                    if ($showtype) {
                        if ($obj->client || $obj->fournisseur)
                            $label.=' (';
                        if ($obj->client == 1 || $obj->client == 3)
                            $label.=Translate::trans("Customer");
                        if ($obj->client == 2 || $obj->client == 3)
                            $label.=($obj->client == 3 ? ', ' : '') . Translate::trans("Prospect");
                        if ($obj->fournisseur)
                            $label.=($obj->client ? ', ' : '') . Translate::trans("Supplier");
                        if ($obj->client || $obj->fournisseur)
                            $label.=')';
                    }
                    if ($selected > 0 && $selected == $obj->rowid) {
                        $out.= '<option value="' . $obj->rowid . '" selected="selected">' . $label . '</option>';
                    } else {
                        $out.= '<option value="' . $obj->rowid . '">' . $label . '</option>';
                    }
                    $i++;
                }
            }
            $out.= '</select>';
        } else {
            CommonObject::printError($this->db);
        }

        return $out;
    }
    
    /**
     *    	Output html form to select a third party
     * 		@param      selected        Preselected type
     * 		@param      htmlname        Name of field in form
     *    	@param      filter          Optionnal filters criteras
     * 		@param		showempty		Add an empty field
     * 		@param		showtype		Show third party type in combolist (customer, prospect or supplier)
     * 		@param		forcecombo		Force to use combo box
     */
    public static function selectTypeBidding($selected = '', $htmlname = 'type') {
        $valuesSelect = array();
                      $valuesSelect[1]['value'] = BiddingValorization::TYPE_MAX;
                      $valuesSelect[1]['label'] = Translate::trans('BiddingMax');
                      $valuesSelect[2]['value'] = BiddingValorization::TYPE_MIN;
                      $valuesSelect[2]['label'] = Translate::trans('BiddingMin');
        $out = '';
            $out.= '<select rowid="' . $htmlname . '" class="flat" name="' . $htmlname . '">';
                   
            foreach ($valuesSelect as $key => $value) {
                if ($selected > 0 && $selected == $valuesSelect[$key]['value']) {
                    $out.= '<option value="' . $valuesSelect[$key]['value'] . '" selected="selected">' . $valuesSelect[$key]['label'] . '</option>';
                } else {
                    $out.= '<option value="' . $valuesSelect[$key]['value'] . '">' . $valuesSelect[$key]['label'] . '</option>';
                }
            }
            $out.= '</select>';
       return $out;
    }

    /**
     *    	Return HTML combo list of absolute discounts
     *    	@param      selected        Id remise fixe pre-selectionnee
     *    	@param      htmlname        Nom champ formulaire
     *    	@param      filter          Criteres optionnels de filtre
     * 		@param		maxvalue		Max value for lines that can be selected
     * 		@return		int				Return number of qualifed lines in list
     */
    function select_remises($selected = '', $htmlname = 'remise_id', $filter = '', $socid, $maxvalue = 0) {
        global $conf;

        // On recherche les remises
        $sql = "SELECT re.rowid, re.amount_ht, re.amount_tva, re.amount_ttc,";
        $sql.= " re.description, re.fk_facture_source";
        $sql.= " FROM " . MAIN_DB_PREFIX . "societe_remise_except as re";
        $sql.= " WHERE fk_soc = " . $socid;
        $sql.= " AND re.entity = ".$conf->entity;
        if ($filter)
            $sql.= " AND " . $filter;
        $sql.= " ORDER BY re.description ASC";

        Syslog::log("Form::select_remises sql=" . $sql);
        $resql = $this->db->query($sql);
        
        if ($resql) {
            print '<select class="input-large" name="' . $htmlname . '">';
                $num = $this->db->num_rows($resql);

                $qualifiedlines = $num;

                $i = 0;
                if ($num) {
                    print '<option value="0">&nbsp;</option>';
                    while ($i < $num) {
                        $obj = $this->db->fetch_object($resql);
                        $desc = String::trunc($obj->description, 40);
                        if ($desc == '(CREDIT_NOTE)')
                            $desc = Translate::trans("CreditNote");
                        if ($desc == '(DEPOSIT)')
                            $desc = Translate::trans("Deposit");

                        $selectstring = '';
                        if ($selected > 0 && $selected == $obj->rowid)
                            $selectstring = ' selected="selected"';

                        $disabled = '';
                        if ($maxvalue && $obj->amount_ttc > $maxvalue) {
                            $qualifiedlines--;
                            $disabled = ' disabled="true"';
                        }

                        print '<option value="' . $obj->rowid . '"' . $selectstring . $disabled . '>' . $desc . ' (' . Price::price($obj->amount_ht) . ' ' . Translate::trans("HT") . ' - ' . Price::price($obj->amount_ttc) . ' ' . Translate::trans("TTC") . ')</option>';
                        $i++;
                    }
                }
                print '</select>';
            return $qualifiedlines;
        } else {
            CommonObject::printError($this->db);
            return -1;
        }
    }

    /**
     *    	Return list of all contacts (for a third party or all)
     *    	@param      socid      	    Id ot third party or 0 for all
     *    	@param      selected   	    Id contact pre-selectionne
     *    	@param      htmlname  	    Name of HTML field ('none' for a not editable field)
     *      @param      show_empty      0=no empty value, 1=add an empty value
     *      @param      exclude         List of contacts rowid to exclude
     * 		@param		limitto			Disable answers that are not rowid in this array list
     * 	    @param		showfunction    Add function into label
     * 		@param		moreclass		Add more class to class style
     * 		@return		int				<0 if KO, Nb of contact in list if OK
     */
    function select_contacts($socid, $selected = '', $htmlname = 'contactid', $showempty = 0, $exclude = '', $limitto = '', $showfunction = 0, $moreclass = '') {
        global $conf;

        // On recherche les societes
        $sql = "SELECT s.rowid, s.name, s.firstname, s.poste FROM";
        $sql.= " " . MAIN_DB_PREFIX . "socpeople as s";
        $sql.= " WHERE s.entity = " . $conf->entity;
        if ($socid > 0)
            $sql.= " AND fk_soc=" . $socid;
        $sql.= " ORDER BY s.name ASC";

        Syslog::log("Form::select_contacts sql=" . $sql);
        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            if ($num == 0)
                return 0;

            if ($htmlname != 'none')
                print '<select class="input-large ' . ($moreclass ? ' ' . $moreclass : '') . '" rowid="' . $htmlname . '" name="' . $htmlname . '">';
            if ($showempty)
                print '<option value="0"></option>';
            $num = $this->db->num_rows($resql);
            $i = 0;
            if ($num) {
                include_once(SIEMP_DOCUMENT_ROOT . '/contact/class/contact.class.php');
                $contactstatic = new Contact($this->db);

                while ($i < $num) {
                    $obj = $this->db->fetch_object($resql);

                    $contactstatic->rowid = $obj->rowid;
                    $contactstatic->name = $obj->name;
                    $contactstatic->firstname = $obj->firstname;

                    if ($htmlname != 'none') {
                        $disabled = 0;
                        if (is_array($exclude) && sizeof($exclude) && in_array($obj->rowid, $exclude))
                            $disabled = 1;
                        if (is_array($limitto) && sizeof($limitto) && !in_array($obj->rowid, $limitto))
                            $disabled = 1;
                        if ($selected && $selected == $obj->rowid) {
                            print '<option value="' . $obj->rowid . '"';
                            if ($disabled)
                                print ' disabled="true"';
                            print ' selected="selected">';
                            print $contactstatic->getFullName();
                            if ($showfunction && $obj->poste)
                                print ' (' . $obj->poste . ')';
                            print '</option>';
                        }
                        else {
                            print '<option value="' . $obj->rowid . '"';
                            if ($disabled)
                                print ' disabled="true"';
                            print '>';
                            print $contactstatic->getFullName();
                            if ($showfunction && $obj->poste)
                                print ' (' . $obj->poste . ')';
                            print '</option>';
                        }
                    }
                    else {
                        if ($selected == $obj->rowid) {
                            print $contactstatic->getFullName();
                            if ($showfunction && $obj->poste)
                                print ' (' . $obj->poste . ')';
                        }
                    }
                    $i++;
                }
            }
            if ($htmlname != 'none') {
                print '</select>';
            }
            return $num;
        } else {
            CommonObject::printError($this->db);
            return -1;
        }
    }

    /**
     * 	Return select list of users
     *  @param      selected        Id user preselected
     *  @param      htmlname        Field name in form
     *  @param      show_empty      0=liste sans valeur nulle, 1=ajoute valeur inconnue
     *  @param      exclude         Array list of users rowid to exclude
     * 	@param		disabled		If select list must be disabled
     *  @param      include         Array list of users rowid to include
     * 	@param		enableonly		Array list of users rowid to be enabled. All other must be disabled
     */
    function select_users($selected = '', $htmlname = 'userid', $show_empty = 0, $exclude = '', $disabled = 0, $include = '', $enableonly = '') {
        print $this->select_siempusers($selected, $htmlname, $show_empty, $exclude, $disabled, $include, $enableonly);
    }

    /**
     * 	Return select list of users
     *  @param      selected        User rowid or user object of user preselected. If -1, we use rowid of current user.
     *  @param      htmlname        Field name in form
     *  @param      show_empty      0=liste sans valeur nulle, 1=ajoute valeur inconnue
     *  @param      exclude         Array list of users rowid to exclude
     * 	@param		disabled		If select list must be disabled
     *  @param      include         Array list of users rowid to include
     * 	@param		enableonly		Array list of users rowid to be enabled. All other must be disabled
     */
    function select_siempusers($selected = '', $htmlname = 'userid', $show_empty = 0, $exclude = '', $disabled = 0, $include = '', $enableonly = '') {
        global $conf, $user;

        // If no preselected user defined, we take current user
        if ($selected < -1 && empty($conf->global->SOCIETE_DISABLE_DEFAULT_SALESREPRESENTATIVE))
            $selected = $user->rowid;

        // Permettre l'exclusion d'utilisateurs
        if (is_array($exclude))
            $excludeUsers = implode("','", $exclude);
        // Permettre l'inclusion d'utilisateurs
        if (is_array($include))
            $includeUsers = implode("','", $include);

        $out = '';

        // On recherche les utilisateurs
        $sql = "SELECT u.rowid, u.name, u.firstname, u.login, u.admin";
        $sql.= " FROM " . MAIN_DB_PREFIX . "user as u";
        $sql.= " WHERE u.entity =" . $conf->entity;
        if (is_array($exclude) && $excludeUsers)
            $sql.= " AND u.rowid NOT IN ('" . $excludeUsers . "')";
        if (is_array($include) && $includeUsers)
            $sql.= " AND u.rowid IN ('" . $includeUsers . "')";
        $sql.= " ORDER BY u.name ASC";

        Syslog::log("Form::select_siempusers sql=" . $sql);
        $resql = $this->db->query($sql);
        if ($resql) {
            $out.= '<select class="input-large" rowid="' . $htmlname . '" name="' . $htmlname . '"' . ($disabled ? ' disabled="true"' : '') . '>';
            if ($show_empty)
                $out.= '<option value="-1">&nbsp;</option>' . "\n";
            $num = $this->db->num_rows($resql);
            $i = 0;
            if ($num) {
                $userstatic = new User($this->db);

                while ($i < $num) {
                    $obj = $this->db->fetch_object($resql);

                    $userstatic->rowid = $obj->rowid;
                    $userstatic->nom = $obj->name;
                    $userstatic->prenom = $obj->firstname;

                    $disableline = 0;
                    if (is_array($enableonly) && sizeof($enableonly) && !in_array($obj->rowid, $enableonly))
                        $disableline = 1;

                    if ((is_object($selected) && $selected->rowid == $obj->rowid) || (!is_object($selected) && $selected == $obj->rowid)) {
                        $out.= '<option value="' . $obj->rowid . '"';
                        if ($disableline)
                            $out.= ' disabled="true"';
                        $out.= ' selected="selected">';
                    }
                    else {
                        $out.= '<option value="' . $obj->rowid . '"';
                        if ($disableline)
                            $out.= ' disabled="true"';
                        $out.= '>';
                    }
                    $out.= $userstatic->getFullName();

                    $out.= '</option>';
                    $i++;
                }
            }
            $out.= '</select>';
        }
        else {
            CommonObject::printError($this->db);
        }

        return $out;
    }

    /**
     *  Return list of products for customer in Ajax if Ajax activated or go to select_produits_do
     *  @param		selected				Preselected products
     *  @param		htmlname				Name of HTML seletc field (must be unique in page)
     *  @param		filtertype				Filter on product type (''=nofilter, 0=product, 1=service)
     *  @param		limit					Limit on number of returned lines
     *  @param		price_level				Level of price to show
     *  @param		status					-1=Return all products, 0=Products not on sell, 1=Products on sell
     *  @param		finished				2=all, 1=finished, 0=raw material
     *  @param		$selected_input_value	Value of preselected input text (with ajax)
     */
    function select_produits($selected = '', $htmlname = 'productid', $filtertype = '', $limit = 20, $price_level = 0, $status = 1, $finished = 2, $selected_input_value = '', $hidelabel = 0) {
        global $conf;

        if ($conf->global->PRODUIT_USE_SEARCH_TO_SELECT) {
            if ($selected && empty($selected_input_value)) {
                require_once(SIEMP_DOCUMENT_ROOT . "/product/class/product.class.php");
                $product = new Product($this->db);
                $product->fetch($selected);
                $selected_input_value = $product->ref;
            }
            // mode=1 means customers products
            print ajax_autocompleter($selected, $htmlname, SIEMP_URL_ROOT . '/product/ajaxproducts.php', 'htmlname=' . $htmlname . '&outjson=1&price_level=' . $price_level . '&type=' . $filtertype . '&mode=1&status=' . $status . '&finished=' . $finished, $conf->global->PRODUIT_USE_SEARCH_TO_SELECT);
            if (!$hidelabel)
                print Translate::trans("RefOrLabel") . ' : ';
            print '<input type="text" size="20" name="search_' . $htmlname . '" id="search_' . $htmlname . '" value="' . $selected_input_value . '" />';
            print '<br>';
        }
        else {
            $this->select_produits_do($selected, $htmlname, $filtertype, $limit, $price_level, '', $status, $finished, 0);
        }
    }

    /**
     * 	Return list of products for a customer
     * 	@param      selected        Preselected product
     * 	@param      htmlname        Name of select html
     *  @param		filtertype      Filter on product type (''=nofilter, 0=product, 1=service)
     * 	@param      limit           Limite sur le nombre de lignes retournees
     * 	@param      price_level     Level of price to show
     * 	@param      filterkey       Filter on product
     * 	@param		status          -1=Return all products, 0=Products not on sell, 1=Products on sell
     *  @param      finished        Filter on finished field: 2=No filter
     *  @param      disableout      Disable print output
     *  @return     array           Array of keys for json
     */
    function select_produits_do($selected = '', $htmlname = 'productid', $filtertype = '', $limit = 20, $price_level = 0, $filterkey = '', $status = 1, $finished = 2, $disableout = 0) {
        global $conf, $db;

        $sql = "SELECT ";
        $sql.= " p.rowid, p.label, p.ref, p.fk_product_type, p.price, p.price_ttc, p.price_base_type, p.duration, p.stock";
        // Multilang : we add translation
        if ($conf->global->MAIN_MULTILANGS) {
            $sql.= ", pl.label as label_translated";
        }
        $sql.= " FROM " . MAIN_DB_PREFIX . "product as p";
        // Multilang : we add translation
        if ($conf->global->MAIN_MULTILANGS) {
            $sql.= " LEFT JOIN " . MAIN_DB_PREFIX . "product_lang as pl ON pl.fk_product = p.rowid AND pl.lang='" . Translate::getDefaultLang() . "'";
        }
        $sql.= ' WHERE p.entity = ' . $conf->entity;
        if ($conf->global->MAIN_MULTILANGS) {
            $sql.= " AND pl.entity = ".$conf->entity;
        }
        if ($finished == 0) {
            $sql.= " AND p.finished = " . $finished;
        } elseif ($finished == 1) {
            $sql.= " AND p.finished = " . $finished;
            if ($status >= 0)
                $sql.= " AND p.tosell = " . $status;
        }
        elseif ($status >= 0) {
            $sql.= " AND p.tosell = " . $status;
        }
        if (strval($filtertype) != '')
            $sql.=" AND p.fk_product_type=" . $filtertype;
        // Add criteria on ref/label
        if ($filterkey && $filterkey != '') {
            if (!empty($conf->global->PRODUCT_DONOTSEARCH_ANYWHERE)) {   // Can use index
                $sql.=" AND (p.ref LIKE '" . $filterkey . "%' OR p.label LIKE '" . $filterkey . "%'";
                if ($conf->global->MAIN_MULTILANGS)
                    $sql.=" OR pl.label LIKE '" . $filterkey . "%'";
                $sql.=")";
            }
            else {
                $sql.=" AND (p.ref LIKE '%" . $filterkey . "%' OR p.label LIKE '%" . $filterkey . "%'";
                if ($conf->global->MAIN_MULTILANGS)
                    $sql.=" OR pl.label LIKE '%" . $filterkey . "%'";
                $sql.=")";
            }
        }
        $sql.= $db->order("p.ref");
        $sql.= $db->plimit($limit);

        // Build output string
        $outselect = '';
        $outjson = array();

        Syslog::log("Form::select_produits_do search product sql=" . $sql, LOG_DEBUG);
        $result = $this->db->query($sql);
        if ($result) {
            $num = $this->db->num_rows($result);

            $outselect.='<select class="flat" name="' . $htmlname . '">';
            $outselect.='<option value="0" selected="selected">&nbsp;</option>';

            $i = 0;
            while ($num && $i < $num) {
                $outkey = '';
                $outval = '';
                $outref = '';

                $objp = $this->db->fetch_object($result);

                $label = $objp->label;
                if (!empty($objp->label_translated))
                    $label = $objp->label_translated;
                if ($filterkey && $filterkey != '')
                    $label = preg_replace('/(' . preg_quote($filterkey) . ')/i', '<strong>$1</strong>', $label, 1);

                $outkey = $objp->rowid;
                $outref = $objp->ref;

                $opt = '<option value="' . $objp->rowid . '"';
                $opt.= ($objp->rowid == $selected) ? ' selected="selected"' : '';
                if ($conf->stock->enabled && $objp->fk_product_type == 0 && isset($objp->stock)) {
                    if ($objp->stock > 0) {
                        $opt.= ' style="background-color:#32CD32; color:#F5F5F5;"';
                    } else if ($objp->stock <= 0) {
                        $opt.= ' style="background-color:#FF0000; color:#F5F5F5;"';
                    }
                }
                $opt.= '>';
                $opt.= Translate::convToOutputCharset($objp->ref) . ' - ' . Translate::convToOutputCharset(String::trunc($label, 32)) . ' - ';

                $objRef = $objp->ref;
                if ($filterkey && $filterkey != '')
                    $objRef = preg_replace('/(' . preg_quote($filterkey) . ')/i', '<strong>$1</strong>', $objRef, 1);
                $outval.=$objRef . ' - ' . String::trunc($label, 32) . ' - ';

                $found = 0;
                $currencytext = Translate::trans("Currency" . $conf->currency);
                $currencytextnoent = Translate::transnoentities("Currency" . $conf->currency);
                if (String::strlen($currencytext) > 10)
                    $currencytext = $conf->currency; // If text is too long, we use the short code
                if (String::strlen($currencytextnoent) > 10)
                    $currencytextnoent = $conf->currency;   // If text is too long, we use the short code

                    
// Multiprice
                if ($price_level >= 1) {  // If we need a particular price level (from 1 to 6)
                    $sql = "SELECT price, price_ttc, price_base_type ";
                    $sql.= "FROM " . MAIN_DB_PREFIX . "product_price ";
                    $sql.= "WHERE fk_product='" . $objp->rowid . "'";
                    $sql.= " AND price_level=" . $price_level;
                    $sql.= " AND entity = ".$conf->entity;
                    $sql.= " ORDER BY date_price";
                    $sql.= " DESC limit 1";

                    Syslog::log("Form::select_produits_do search price for level '.$price_level.' sql=" . $sql);
                    $result2 = $this->db->query($sql);
                    if ($result2) {
                        $objp2 = $this->db->fetch_object($result2);
                        if ($objp2) {
                            $found = 1;
                            if ($objp2->price_base_type == 'HT') {
                                $opt.= Price::price($objp2->price, 1) . ' ' . $currencytext . ' ' . Translate::trans("HT");
                                $outval.= Price::price($objp2->price, 1) . ' ' . $currencytextnoent . ' ' . Translate::transnoentities("HT");
                            } else {
                                $opt.= Price::price($objp2->price_ttc, 1) . ' ' . $currencytext . ' ' . Translate::trans("TTC");
                                $outval.= Price::price($objp2->price_ttc, 1) . ' ' . $currencytextnoent . ' ' . Translate::transnoentities("TTC");
                            }
                        }
                    } else {
                        CommonObject::printError($this->db);
                    }
                }

                // If level no defined or multiprice not found, we used the default price
                if (!$found) {
                    if ($objp->price_base_type == 'HT') {
                        $opt.= Price::price($objp->price, 1) . ' ' . $currencytext . ' ' . Translate::trans("HT");
                        $outval.= Price::price($objp->price, 1) . ' ' . $currencytextnoent . ' ' . Translate::transnoentities("HT");
                    } else {
                        $opt.= Price::price($objp->price_ttc, 1) . ' ' . $currencytext . ' ' . Translate::trans("TTC");
                        $outval.= Price::price($objp->price_ttc, 1) . ' ' . $currencytextnoent . ' ' . Translate::transnoentities("TTC");
                    }
                }

                if ($conf->stock->enabled && isset($objp->stock) && $objp->fk_product_type == 0) {
                    $opt.= ' - ' . Translate::trans("Stock") . ':' . $objp->stock;
                    $outval.=' - ' . Translate::transnoentities("Stock") . ':' . $objp->stock;
                }

                if ($objp->duration) {
                    $duration_value = substr($objp->duration, 0, String::strlen($objp->duration) - 1);
                    $duration_unit = substr($objp->duration, -1);
                    if ($duration_value > 1) {
                        $dur = array("h" => Translate::trans("Hours"), "d" => Translate::trans("Days"), "w" => Translate::trans("Weeks"), "m" => Translate::trans("Months"), "y" => Translate::trans("Years"));
                    } else {
                        $dur = array("h" => Translate::trans("Hour"), "d" => Translate::trans("Day"), "w" => Translate::trans("Week"), "m" => Translate::trans("Month"), "y" => Translate::trans("Year"));
                    }
                    $opt.= ' - ' . $duration_value . ' ' . Translate::trans($dur[$duration_unit]);
                    $outval.=' - ' . $duration_value . ' ' . Translate::transnoentities($dur[$duration_unit]);
                }

                $opt.= "</option>\n";

                // Add new entry
                // "key" value of json key array is used by jQuery automatically as selected value
                // "label" value of json key array is used by jQuery automatically as text for combo box
                $outselect.=$opt;
                array_push($outjson, array('key' => $outkey, 'value' => $outref, 'label' => $outval));

                $i++;
            }

            $outselect.='</select>';

            $this->db->free($result);

            if (empty($disableout))
                print $outselect;
            return $outjson;
        }
        else {
            CommonObject::printError($db);
        }
    }

    /**
     * 	Return list of products for customer in Ajax if Ajax activated or go to select_produits_fournisseurs_do
     * 	@param		socid			Id third party
     * 	@param     	selected        Preselected product
     * 	@param     	htmlname        Name of HTML Select
     *  @param		filtertype      Filter on product type (''=nofilter, 0=product, 1=service)
     * 	@param     	filtre          For a SQL filter
     */
    function select_produits_fournisseurs($socid, $selected = '', $htmlname = 'productid', $filtertype = '', $filtre) {
        global $conf;

        if ($conf->global->PRODUIT_USE_SEARCH_TO_SELECT) {
            // mode=2 means suppliers products
            print ajax_autocompleter('', $htmlname, SIEMP_URL_ROOT . '/product/ajaxproducts.php', ($socid > 0 ? 'socid=' . $socid . '&' : '') . 'htmlname=' . $htmlname . '&outjson=1&price_level=' . $price_level . '&type=' . $filtertype . '&mode=2&status=' . $status . '&finished=' . $finished, $conf->global->PRODUIT_USE_SEARCH_TO_SELECT);
            print Translate::trans("RefOrLabel") . ' : <input type="text" size="16" name="search_' . $htmlname . '" rowid="search_' . $htmlname . '">';
            print '<br>';
        } else {
            $this->select_produits_fournisseurs_do($socid, $selected, $htmlname, $filtertype, $filtre, '', -1, 0);
        }
    }

    /**
     * 	Retourne la liste des produits de fournisseurs
     * 	@param		socid   		Id societe fournisseur (0 pour aucun filtre)
     * 	@param      selected        Produit pre-selectionne
     * 	@param      htmlname        Nom de la zone select
     *  @param		filtertype      Filter on product type (''=nofilter, 0=product, 1=service)
     * 	@param      filtre          Pour filtre sql
     * 	@param      filterkey       Filtre des produits
     *  @param      status          -1=Return all products, 0=Products not on sell, 1=Products on sell
     *  @param      disableout      Disable print output
     *  @return     array           Array of keys for json
     */
    function select_produits_fournisseurs_do($socid, $selected = '', $htmlname = 'productid', $filtertype = '', $filtre = '', $filterkey = '', $statut = -1, $disableout = 0) {
        global $conf;

        Translate::load('stocks');

        $sql = "SELECT p.rowid, p.label, p.ref, p.price, p.duration,";
        //  Equivalencia
        $sql.= " p.equivalencia,";
        $sql.= " pf.ref_fourn,";
        $sql.= " pfp.rowid as idprodfournprice, pfp.price as fprice, pfp.quantity, pfp.unitprice,";
        $sql.= " s.nom";
        $sql.= " FROM " . MAIN_DB_PREFIX . "product as p";
        $sql.= " LEFT JOIN " . MAIN_DB_PREFIX . "product_fournisseur as pf ON (p.rowid = pf.fk_product AND pf.entity = p.entity)";
        $sql.= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON (pf.fk_soc = s.rowid AND s.entity = p.entity)";
        $sql.= " LEFT JOIN " . MAIN_DB_PREFIX . "product_fournisseur_price as pfp ON (pf.rowid = pfp.fk_product_fournisseur AND pfp.entity = p.entity)";
        $sql.= " WHERE p.entity = " . $conf->entity;
        $sql.= " AND p.tobuy = 1";
        if ($socid)
            $sql.= " AND pf.fk_soc = " . $socid;
        if (strval($filtertype) != '')
            $sql.=" AND p.fk_product_type=" . $filtertype;
        if (!empty($filtre))
            $sql.=" " . $filtre;
        // Add criteria on ref/label
        if ($filterkey && $filterkey != '') {
            if (!empty($conf->global->PRODUCT_DONOTSEARCH_ANYWHERE)) {
                $sql.=" AND (pf.ref_fourn LIKE '" . $filterkey . "%' OR p.ref LIKE '" . $filterkey . "%' OR p.label LIKE '" . $filterkey . "%')";
            } else {
                $sql.=" AND (pf.ref_fourn LIKE '%" . $filterkey . "%' OR p.ref LIKE '%" . $filterkey . "%' OR p.label LIKE '%" . $filterkey . "%')";
            }
        }
        $sql.= " ORDER BY pf.ref_fourn DESC";

        // Build output string
        $outselect = '';
        $outjson = array();

        Syslog::log("Form::select_produits_fournisseurs_do sql=" . $sql, LOG_DEBUG);
        $result = $this->db->query($sql);
        if ($result) {

            $num = $this->db->num_rows($result);

            $outselect.='<select class="flat" rowid="select' . $htmlname . '" name="' . $htmlname . '">';
            if (!$selected)
                $outselect.='<option value="0" selected="selected">&nbsp;</option>';
            else
                $outselect.='<option value="0">&nbsp;</option>';

            $i = 0;
            while ($i < $num) {
                $outkey = '';
                $outval = '';
                $outref = '';

                $objp = $this->db->fetch_object($result);

                $outkey = $objp->idprodfournprice;
                $outref = $objp->ref;

                $opt = '<option value="' . $objp->idprodfournprice . '"';
                if ($selected == $objp->idprodfournprice)
                    $opt.= ' selected="selected"';
                if ($objp->fprice == '')
                    $opt.=' disabled="disabled"';
                $opt.= '>';

                $objRef = $objp->ref;
                if ($filterkey && $filterkey != '')
                    $objRef = preg_replace('/(' . preg_quote($filterkey) . ')/i', '<strong>$1</strong>', $objRef, 1);
                $objRefFourn = $objp->ref_fourn;
                if ($filterkey && $filterkey != '')
                    $objRefFourn = preg_replace('/(' . preg_quote($filterkey) . ')/i', '<strong>$1</strong>', $objRefFourn, 1);
                $label = $objp->label;
                if ($filterkey && $filterkey != '')
                    $label = preg_replace('/(' . preg_quote($filterkey) . ')/i', '<strong>$1</strong>', $label, 1);

                $opt.=Translate::convToOutputCharset($objp->ref) . ' (' . Translate::convToOutputCharset($objp->ref_fourn) . ') - ';
                $outval.=$objRef . ' (' . $objRefFourn . ') - ';
                $opt.=Translate::convToOutputCharset(String::trunc($objp->label, 18)) . ' - ';
                $outval.=String::trunc($label, 18) . ' - ';

                if ($objp->fprice != '') {  // Keep != ''
                    $currencytext = Translate::trans("Currency" . $conf->currency);
                    $currencytextnoent = Translate::transnoentities("Currency" . $conf->currency);
                    if (String::strlen($currencytext) > 10)
                        $currencytext = $conf->currency;   // If text is too long, we use the short code
                    if (String::strlen($currencytextnoent) > 10)
                        $currencytextnoent = $conf->currency;   // If text is too long, we use the short code

                    $opt.= Price::price($objp->fprice) . ' ' . $currencytext . "/" . $objp->quantity;
                    $outval.= Price::price($objp->fprice) . ' ' . $currencytextnoent . "/" . $objp->quantity;
                    if ($objp->quantity == 1) {
                        $opt.= strtolower(Translate::trans("Unit"));
                        $outval.=strtolower(Translate::transnoentities("Unit"));
                    } else {
                        $opt.= strtolower(Translate::trans("Units"));
                        $outval.=strtolower(Translate::transnoentities("Units"));
                    }
                    if ($objp->quantity >= 1) {
                        $opt.=" (" . Price::price($objp->unitprice) . ' ' . $currencytext . "/" . strtolower(Translate::trans("Unit")) . ")";
                        $outval.=" (" . Price::price($objp->unitprice) . ' ' . $currencytextnoent . "/" . strtolower(Translate::transnoentities("Unit")) . ")";
                    }
                    if ($objp->duration) {
                        $opt .= " - " . $objp->duration;
                        $outval.=" - " . $objp->duration;
                    }
                    if (!$socid) {
                        $opt .= " - " . String::trunc($objp->nom, 8);
                        $outval.=" - " . String::trunc($objp->nom, 8);
                    }
                } else {
                    $opt.= Translate::trans("NoPriceDefinedForThisSupplier");
                    $outval.=Translate::transnoentities("NoPriceDefinedForThisSupplier");
                }
                $opt .= "</option>\n";

                // Add new entry
                // "key" value of json key array is used by jQuery automatically as selected value
                // "label" value of json key array is used by jQuery automatically as text for combo box
                $outselect.=$opt;
                array_push($outjson, array('key' => $outkey, 'value' => $outref, 'label' => $outval));

                $i++;
            }
            $outselect.='</select>';

            $this->db->free($result);

            if (empty($disableout))
                print $outselect;
            return $outjson;
        }
        else {
            CommonObject::printError($db);
        }
    }

    /**
     * 	Return list of suppliers prices for a product
     *  @param		productid       Id of product
     *  @param      htmlname        Name of HTML field
     */
    function select_product_fourn_price($productid, $htmlname = 'productfournpriceid') {
        global $conf;

        Translate::load('stocks');

        $sql = "SELECT p.rowid, p.label, p.ref, p.price, p.duration,";
        $sql.= " pf.ref_fourn,";
        $sql.= " pfp.rowid as idprodfournprice, pfp.price as fprice, pfp.quantity, pfp.unitprice,";
        $sql.= " s.nom";
        $sql.= " FROM " . MAIN_DB_PREFIX . "product as p";
        $sql.= " LEFT JOIN " . MAIN_DB_PREFIX . "product_fournisseur as pf ON (p.rowid = pf.fk_product AND pf.entity = p.entity)";
        $sql.= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s ON (s.rowid = pf.fk_soc AND s.entity = p.entity)";
        $sql.= " LEFT JOIN " . MAIN_DB_PREFIX . "product_fournisseur_price as pfp ON (pf.rowid = pfp.fk_product_fournisseur AND pfp.entity = p.entity)";
        $sql.= " WHERE p.tobuy = 1";
        $sql.= " AND s.fournisseur = 1";
        $sql.= " AND p.rowid = " . $productid;
        $sql.= " AND p.entity = ".$conf->entity;
        $sql.= " ORDER BY s.nom, pf.ref_fourn DESC";

        Syslog::log("Form::select_product_fourn_price sql=" . $sql, LOG_DEBUG);
        $result = $this->db->query($sql);

        if ($result) {
            $num = $this->db->num_rows($result);

            $form = '<select class="flat" name="' . $htmlname . '">';

            if (!$num) {
                $form.= '<option value="0">-- ' . Translate::trans("NoSupplierPriceDefinedForThisProduct") . ' --</option>';
            } else {
                $form.= '<option value="0">&nbsp;</option>';

                $i = 0;
                while ($i < $num) {
                    $objp = $this->db->fetch_object($result);

                    $opt = '<option value="' . $objp->idprodfournprice . '"';
                    $opt.= '>' . $objp->nom . ' - ' . $objp->ref_fourn . ' - ';

                    if ($objp->quantity == 1) {
                        $opt.= Price::price($objp->fprice);
                        $opt.= Translate::trans("Currency" . $conf->currency) . "/";
                    }

                    $opt.= $objp->quantity . ' ';

                    if ($objp->quantity == 1) {
                        $opt.= strtolower(Translate::trans("Unit"));
                    } else {
                        $opt.= strtolower(Translate::trans("Units"));
                    }
                    if ($objp->quantity > 1) {
                        $opt.=" - ";
                        $opt.= Price::price($objp->unitprice) . Translate::trans("Currency" . $conf->currency) . "/" . strtolower(Translate::trans("Unit"));
                    }
                    if ($objp->duration)
                        $opt .= " - " . $objp->duration;
                    $opt .= "</option>\n";

                    $form.= $opt;
                    $i++;
                }
                $form.= '</select>';

                $this->db->free($result);
            }
            return $form;
        }
        else {
            CommonObject::printError($db);
        }
    }

    /**
     *    Retourne la liste deroulante des adresses
     *    @param      selected          Id contact pre-selectionn
     *    @param      socid
     *    @param      htmlname          Name of HTML field
     *    @param      showempty         Add an empty field
     */
    function select_address($selected = '', $socid, $htmlname = 'address_id', $showempty = 0) {
        global $conf;
        // On recherche les utilisateurs
        $sql = "SELECT a.rowid, a.label";
        $sql .= " FROM " . MAIN_DB_PREFIX . "societe_address as a";
        $sql .= " WHERE a.fk_soc = " . $socid;
        $sql.= " AND a.entity = ".$conf->entity;
        $sql .= " ORDER BY a.label ASC";

        Syslog::log("Form::select_address sql=" . $sql);
        $resql = $this->db->query($sql);
        if ($resql) {
            print '<select class="flat" name="' . $htmlname . '">';
            if ($showempty)
                print '<option value="0">&nbsp;</option>';
            $num = $this->db->num_rows($resql);
            $i = 0;
            if ($num) {
                while ($i < $num) {
                    $obj = $this->db->fetch_object($resql);

                    if ($selected && $selected == $obj->rowid) {
                        print '<option value="' . $obj->rowid . '" selected="selected">' . $obj->label . '</option>';
                    } else {
                        print '<option value="' . $obj->rowid . '">' . $obj->label . '</option>';
                    }
                    $i++;
                }
            }
            print '</select>';
            return $num;
        } else {
            CommonObject::printError($this->db);
        }
    }

    /**
     *      Charge dans cache la liste des conditions de paiements possibles
     *      @return     int             Nb lignes chargees, 0 si deja chargees, <0 si ko
     */
    function load_cache_conditions_paiements() {
        global $conf;

        if (sizeof($this->cache_conditions_paiements))
            return 0;    // Cache deja charge

        $sql = "SELECT rowid, code, libelle";
        $sql.= " FROM " . MAIN_DB_PREFIX . 'c_payment_term';
        $sql.= " WHERE active=1";
        $sql.= " AND entity = ".$conf->entity;
        $sql.= " ORDER BY sortorder";
        Syslog::log('Form::load_cache_conditions_paiements sql=' . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);

                // Si traduction existe, on l'utilise, sinon on prend le libelle par defaut
                $libelle = (Translate::trans("PaymentConditionShort" . $obj->code) != ("PaymentConditionShort" . $obj->code) ? Translate::trans("PaymentConditionShort" . $obj->code) : ($obj->libelle != '-' ? $obj->libelle : ''));
                $this->cache_conditions_paiements[$obj->rowid]['code'] = $obj->code;
                $this->cache_conditions_paiements[$obj->rowid]['label'] = $libelle;
                $i++;
            }
            return 1;
        } else {
            CommonObject::printError($this->db);
            return -1;
        }
    }

    /**
     *      Charge dans cache la liste des délais de livraison possibles
     *      @return     int             Nb lignes chargees, 0 si deja chargees, <0 si ko
     */
    function load_cache_availability() {
        global $conf;

        if (sizeof($this->cache_availability))
            return 0;    // Cache deja charge

        $sql = "SELECT rowid, code, label";
        $sql.= " FROM " . MAIN_DB_PREFIX . 'c_availability';
        $sql.= " WHERE active=1";
        $sql.= " AND entity = ".$conf->entity;
        $sql.= " ORDER BY rowid";
        Syslog::log('Form::load_cache_availability sql=' . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);

                // Si traduction existe, on l'utilise, sinon on prend le libelle par defaut
                $label = (Translate::trans("AvailabilityType" . $obj->code) != ("AvailabilityType" . $obj->code) ? Translate::trans("AvailabilityType" . $obj->code) : ($obj->label != '-' ? $obj->label : ''));
                $this->cache_availability[$obj->rowid]['code'] = $obj->code;
                $this->cache_availability[$obj->rowid]['label'] = $label;
                $i++;
            }
            return 1;
        } else {
            CommonObject::printError($this->db);
            return -1;
        }
    }

    /**
     *      Retourne la liste des types de delais de livraison possibles
     *      @param      selected        Id du type de delais pre-selectionne
     *      @param      htmlname        Nom de la zone select
     *      @param      filtertype      To add a filter
     * 		@param		addempty		Add empty entry
     */
    function select_availability($selected = '', $htmlname = 'availid', $filtertype = '', $addempty = 0) {
        global $user;

        $this->load_cache_availability();

        print '<select class="flat" name="' . $htmlname . '">';
        if ($addempty)
            print '<option value="0">&nbsp;</option>';
        foreach ($this->cache_availability as $rowid => $arrayavailability) {
            if ($selected == $rowid) {
                print '<option value="' . $rowid . '" selected="selected">';
            } else {
                print '<option value="' . $rowid . '">';
            }
            print $arrayavailability['label'];
            print '</option>';
        }
        print '</select>';
        if ($user->admin)
            print Imagen::infoAdmin(Translate::trans("YouCanChangeValuesForThisListFromDictionnarySetup"), 1);
    }

    /**
     *      Load into cache cache_demand_reason, array of input reasons
     *      @return     int             Nb of lines loaded, 0 if already loaded, <0 if ko
     */
    function load_cache_demand_reason() {
        global $conf;

        if (sizeof($this->cache_demand_reason))
            return 0;    // Cache already loaded

        $sql = "SELECT rowid, code, label";
        $sql.= " FROM " . MAIN_DB_PREFIX . 'c_input_reason';
        $sql.= " WHERE active=1";
        $sql.= " AND entity = ".$conf->entity;
        $sql.= " ORDER BY rowid";
        Syslog::log('Form::load_cache_demand_reason sql=' . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;
            $tmparray = array();
            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);

                // Si traduction existe, on l'utilise, sinon on prend le libelle par defaut
                $label = (Translate::trans("DemandReasonType" . $obj->code) != ("DemandReasonType" . $obj->code) ? Translate::trans("DemandReasonType" . $obj->code) : ($obj->label != '-' ? $obj->label : ''));
                $tmparray[$obj->rowid]['rowid'] = $obj->rowid;
                $tmparray[$obj->rowid]['code'] = $obj->code;
                $tmparray[$obj->rowid]['label'] = $label;
                $i++;
            }
            $this->cache_demand_reason = siemp_sort_array($tmparray, 'label', $order = 'asc', $natsort = '', $case_sensitive = '');

            unset($tmparray);
            return 1;
        } else {
            CommonObject::printError($this->db);
            return -1;
        }
    }

    /**
     *      Return list of events that triggered an object creation
     *      @param      selected        Id du type d'origine pre-selectionne
     *      @param      htmlname        Nom de la zone select
     *      @param      exclude         To exclude a code value (Example: SRC_PROP)
     * 		@param		addempty		Add an empty entry
     */
    function select_demand_reason($selected = '', $htmlname = 'demandreasonid', $exclude = '', $addempty = 0) {
        global $user;

        $this->load_cache_demand_reason();

        print '<select class="flat" name="' . $htmlname . '">';
        if ($addempty)
            print '<option value="0"' . (empty($selected) ? ' selected="selected"' : '') . '>&nbsp;</option>';
        foreach ($this->cache_demand_reason as $rowid => $arraydemandreason) {
            if ($arraydemandreason['code'] == $exclude)
                continue;

            if ($selected == $arraydemandreason['rowid']) {
                print '<option value="' . $arraydemandreason['rowid'] . '" selected="selected">';
            } else {
                print '<option value="' . $arraydemandreason['rowid'] . '">';
            }
            print $arraydemandreason['label'];
            print '</option>';
        }
        print '</select>';
        if ($user->admin)
            print Imagen::infoAdmin(Translate::trans("YouCanChangeValuesForThisListFromDictionnarySetup"), 1);
    }

    /**
     *      Charge dans cache la liste des types de paiements possibles
     *      @return     int             Nb lignes chargees, 0 si deja chargees, <0 si ko
     */
    function load_cache_types_paiements() {
        global $conf;

        if (sizeof($this->cache_types_paiements))
            return 0;    // Cache deja charge

        $sql = "SELECT rowid, code, libelle, type";
        $sql.= " FROM " . MAIN_DB_PREFIX . "c_paiement";
        $sql.= " WHERE active > 0";
        $sql.= " AND entity = ".$conf->entity;
        $sql.= " ORDER BY rowid";
        Syslog::log('Form::load_cache_types_paiements sql=' . $sql, LOG_DEBUG);
        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            $i = 0;
            while ($i < $num) {
                $obj = $this->db->fetch_object($resql);

                // Si traduction existe, on l'utilise, sinon on prend le libelle par defaut
                $libelle = (Translate::trans("PaymentTypeShort" . $obj->code) != ("PaymentTypeShort" . $obj->code) ? Translate::trans("PaymentTypeShort" . $obj->code) : ($obj->libelle != '-' ? $obj->libelle : ''));
                $this->cache_types_paiements[$obj->rowid]['code'] = $obj->code;
                $this->cache_types_paiements[$obj->rowid]['label'] = $libelle;
                $this->cache_types_paiements[$obj->rowid]['type'] = $obj->type;
                $i++;
            }
            return $num;
        } else {
            CommonObject::printError($this->db);
            return -1;
        }
    }

    /**
     *      \brief      Retourne la liste des types de paiements possibles
     *      \param      selected        Id du type de paiement pre-selectionne
     *      \param      htmlname        Nom de la zone select
     *      \param      filtertype      Pour filtre
     * 		\param		addempty		Ajoute entree vide
     */
    function select_conditions_paiements($selected = '', $htmlname = 'condid', $filtertype = -1, $addempty = 0) {
        global $user;

        $this->load_cache_conditions_paiements();

        print '<select class="flat" name="' . $htmlname . '">';
        if ($addempty)
            print '<option value="0">&nbsp;</option>';
        foreach ($this->cache_conditions_paiements as $rowid => $arrayconditions) {
            if ($selected == $rowid) {
                print '<option value="' . $rowid . '" selected="selected">';
            } else {
                print '<option value="' . $rowid . '">';
            }
            print $arrayconditions['label'];
            print '</option>';
        }
        print '</select>';
        if ($user->admin)
            print Imagen::infoAdmin(Translate::trans("YouCanChangeValuesForThisListFromDictionnarySetup"), 1);
    }

    /**
     *      Return list of payment methods
     *      @param      selected        Id du mode de paiement pre-selectionne
     *      @param      htmlname        Nom de la zone select
     *      @param      filtertype      To filter on field type in llx_c_paiement (array('code'=>xx,'label'=>zz))
     *      @param      format          0=rowid+libelle, 1=code+code, 2=code+libelle, 3=rowid+code
     *      @param      empty			1=peut etre vide, 0 sinon
     * 		@param		noadmininfo		0=Add admin info, 1=Disable admin info
     *      @param      maxlength       Max length of label
     */
    function select_types_paiements($selected = '', $htmlname = 'paiementtype', $filtertype = '', $format = 0, $empty = 0, $noadmininfo = 0, $maxlength = 0) {
        global $user;
        
        Syslog::log("Form::select_type_paiements $selected, $htmlname, $filtertype, $format", LOG_DEBUG);

        $filterarray = array();
        if ($filtertype == 'CRDT')
            $filterarray = array(0, 2);
        elseif ($filtertype == 'DBIT')
            $filterarray = array(1, 2);
        elseif ($filtertype != '' && $filtertype != '-1')
            $filterarray = explode(',', $filtertype);

        $this->load_cache_types_paiements();

        print '<select rowid="select' . $htmlname . '" class="flat selectpaymenttypes" name="' . $htmlname . '">';
        if ($empty)
            print '<option value="">&nbsp;</option>';
        foreach ($this->cache_types_paiements as $rowid => $arraytypes) {
            // On passe si on a demande de filtrer sur des modes de paiments particuliers
            if (sizeof($filterarray) && !in_array($arraytypes['type'], $filterarray))
                continue;

            // We discard empty line if showempty is on because an empty line has already been output.
            if ($empty && empty($arraytypes['code']))
                continue;

            if ($format == 0)
                print '<option value="' . $rowid . '"';
            if ($format == 1)
                print '<option value="' . $arraytypes['code'] . '"';
            if ($format == 2)
                print '<option value="' . $arraytypes['code'] . '"';
            if ($format == 3)
                print '<option value="' . $rowid . '"';
            // Si selected est text, on compare avec code, sinon avec rowid
            if (preg_match('/[a-z]/i', $selected) && $selected == $arraytypes['code'])
                print ' selected="selected"';
            elseif ($selected == $rowid)
                print ' selected="selected"';
            print '>';
            if ($format == 0)
                $value = ($maxlength ? String::trunc($arraytypes['label'], $maxlength) : $arraytypes['label']);
            if ($format == 1)
                $value = $arraytypes['code'];
            if ($format == 2)
                $value = ($maxlength ? String::trunc($arraytypes['label'], $maxlength) : $arraytypes['label']);
            if ($format == 3)
                $value = $arraytypes['code'];
            print $value ? $value : '&nbsp;';
            print '</option>';
        }
        print '</select>';
        if ($user->admin && !$noadmininfo)
            print Imagen::infoAdmin(Translate::trans("YouCanChangeValuesForThisListFromDictionnarySetup"), 1);
    }

    /**
     *      \brief      Selection HT ou TTC
     *      \param      selected        Id pre-selectionne
     *      \param      htmlname        Nom de la zone select
     */
    function select_PriceBaseType($selected = '', $htmlname = 'price_base_type') {
        print $this->load_PriceBaseType($selected, $htmlname);
    }

    /**
     *      \brief      Selection HT ou TTC
     *      \param      selected        Id pre-selectionne
     *      \param      htmlname        Nom de la zone select
     */
    function load_PriceBaseType($selected = '', $htmlname = 'price_base_type') {
        $return = '';

        $return.= '<select class="flat" name="' . $htmlname . '">';
        $options = array(
            'HT' => Translate::trans("HT"),
            'TTC' => Translate::trans("TTC")
        );
        foreach ($options as $rowid => $value) {
            if ($selected == $rowid) {
                $return.= '<option value="' . $rowid . '" selected="selected">' . $value;
            } else {
                $return.= '<option value="' . $rowid . '">' . $value;
            }
            $return.= '</option>';
        }
        $return.= '</select>';

        return $return;
    }

    /**
     *    Return combo list of differents status of a proposal
     *    Values are rowid of table c_propalst
     *
     *    @param    selected    etat pre-selectionne
     *    @param	short		Use short labels
     */
    function select_propal_statut($selected = '', $short = 0) {
        global $conf;

        $sql = "SELECT rowid as rowid, code, label, active FROM " . MAIN_DB_PREFIX . "c_propalst";
        $sql .= " WHERE active = 1";
        $sql.= " AND entity = ".$conf->entity;

        Syslog::log("Form::select_propal_statut sql=" . $sql);
        $resql = $this->db->query($sql);
        if ($resql) {
            print '<select class="flat" name="propal_statut">';
            print '<option value="">&nbsp;</option>';
            $num = $this->db->num_rows($resql);
            $i = 0;
            if ($num) {
                while ($i < $num) {
                    $obj = $this->db->fetch_object($resql);
                    if ($selected == $obj->rowid) {
                        print '<option value="' . $obj->rowid . '" selected="selected">';
                    } else {
                        print '<option value="' . $obj->rowid . '">';
                    }
                    $key = $obj->code;
                    if (Translate::trans("PropalStatus" . $key . ($short ? 'Short' : '')) != "PropalStatus" . $key . ($short ? 'Short' : '')) {
                        print Translate::trans("PropalStatus" . $key . ($short ? 'Short' : ''));
                    } else {
                        $conv_to_new_code = array('PR_DRAFT' => 'Draft', 'PR_OPEN' => 'Opened', 'PR_CLOSED' => 'Closed', 'PR_SIGNED' => 'Signed', 'PR_NOTSIGNED' => 'NotSigned', 'PR_FAC' => 'Billed');
                        if (!empty($conv_to_new_code[$obj->code]))
                            $key = $conv_to_new_code[$obj->code];
                        print (Translate::trans("PropalStatus" . $key . ($short ? 'Short' : '')) != "PropalStatus" . $key . ($short ? 'Short' : '')) ? Translate::trans("PropalStatus" . $key . ($short ? 'Short' : '')) : $obj->label;
                    }
                    print '</option>';
                    $i++;
                }
            }
            print '</select>';
        }
        else {
            CommonObject::printError($this->db);
        }
    }

    /**
     *    Return a HTML select list of bank accounts
     *
     *    @param      selected          Id account pre-selected
     *    @param      htmlname          Name of select zone
     *    @param      statut            Status of searched accounts (0=open, 1=closed)
     *    @param      filtre            To filter list
     *    @param      useempty          1=Add an empty value in list, 2=Add an empty value in list only if there is more than 2 entries.
     *    @param      moreattrib        To add more attribute on select
     */
    function select_comptes($selected = '', $htmlname = 'accountid', $statut = 0, $filtre = '', $useempty = 0, $moreattrib = '') {
        global $conf;

        Translate::load("admin");

        $sql = "SELECT rowid, label, bank";
        $sql.= " FROM " . MAIN_DB_PREFIX . "bank_account";
        $sql.= " WHERE clos = '" . $statut . "'";
        $sql.= " AND entity = " . $conf->entity;
        if ($filtre)
            $sql.=" AND " . $filtre;
        $sql.= " ORDER BY label";
        Syslog::log("Form::select_comptes sql=" . $sql);
        $result = $this->db->query($sql);
        if ($result) {
            $num = $this->db->num_rows($result);
            $i = 0;
            if ($num) {
                print '<select rowid="select' . $htmlname . '" class="flat selectbankaccount" name="' . $htmlname . '"' . ($moreattrib ? ' ' . $moreattrib : '') . '>';
                if ($useempty == 1 || ($useempty == 2 && $num > 1)) {
                    print '<option value="' . $obj->rowid . '">&nbsp;</option>';
                }

                while ($i < $num) {
                    $obj = $this->db->fetch_object($result);
                    if ($selected == $obj->rowid) {
                        print '<option value="' . $obj->rowid . '" selected="selected">';
                    } else {
                        print '<option value="' . $obj->rowid . '">';
                    }
                    print $obj->label;
                    print '</option>';
                    $i++;
                }
                print "</select>";
            } else {
                print Translate::trans("NoActiveBankAccountDefined");
            }
        } else {
            CommonObject::printError($this->db);
        }
    }

    /**
     *    Return list of categories having choosed type
     *    @param    type			Type de categories (0=product, 1=supplier, 2=customer, 3=member)
     *    @param    selected    	Id of category preselected
     *    @param    select_name		HTML field name
     *    @param    maxlength       Maximum length for labels
     *    @param    excludeafterid  Exclude all categories after this leaf in category tree.
     */
    function select_all_categories($type, $selected = '', $select_name = "", $maxlength = 64, $excludeafterid = 0) {
        Translate::load("categories");

        if ($select_name == "")
            $select_name = "catMere";

        $cat = new Categorie($this->db);
        $cate_arbo = $cat->get_full_arbo($type, $excludeafterid);

        $output = '<select class="flat" name="' . $select_name . '" rowid="'.$select_name.'">';
        if (is_array($cate_arbo)) {
            if (!sizeof($cate_arbo))
                $output.= '<option value="0" disabled="true">' . Translate::trans("NoCategoriesDefined") . '</option>';
            else {
                $output.= '<option value="0">&nbsp;</option>';
                foreach ($cate_arbo as $key => $value) {
                    if (isset($cate_arbo[$key]['rowid']) && $cate_arbo[$key]['rowid'] == $selected) {
                        $add = 'selected="selected" ';
                    } else {
                        $add = '';
                    }
                    $output.= '<option ' . $add . 'value="' . (isset($cate_arbo[$key]['rowid']) ? $cate_arbo[$key]['rowid'] : '') . '">' . String::trunc((isset($cate_arbo[$key]['fulllabel']) ? $cate_arbo[$key]['fulllabel'] : ''), $maxlength, 'middle') . '</option>';
                }
            }
        }
        $output.= '</select>';
        $output.= "\n";
        return $output;
    }

    /**
     *     Show a confirmation HTML form or AJAX popup
     *     @param  page        	   Url of page to call if confirmation is OK
     *     @param  title       	   title
     *     @param  question    	   question
     *     @param  action      	   action
     * 	   @param  formquestion	   an array with forms complementary inputs
     * 	   @param  selectedchoice  "" or "no" or "yes"
     * 	   @param  useajax		   0=No, 1=Yes, 2=Yes but submit page with &confirm=no if choice is No
     *     @param  height          Force height of box
     *     @return string          'ajax' if a confirm ajax popup is shown, 'html' if it's an html form
     */
    function form_confirm($page, $title, $question, $action, $formquestion = '', $selectedchoice = "", $useajax = 0, $height = 170, $width = 500) {
        print $this->formconfirm($page, $title, $question, $action, $formquestion, $selectedchoice, $useajax, $height, $width);
    }

    /**
     *     Show a confirmation HTML form or AJAX popup
     *     @param  page        	   Url of page to call if confirmation is OK
     *     @param  title       	   title
     *     @param  question    	   question
     *     @param  action      	   action
     * 	   @param  formquestion	   an array with complementary inputs to add into forms: array(array('label'=> ,'type'=> , ))
     * 	   @param  selectedchoice  "" or "no" or "yes"
     * 	   @param  useajax		   0=No, 1=Yes, 2=Yes but submit page with &confirm=no if choice is No
     *     @param  height          Force height of box
     *     @return string          'ajax' if a confirm ajax popup is shown, 'html' if it's an html form
     */
    function formconfirm($page, $title, $question, $action, $formquestion = '', $selectedchoice = "", $useajax = 0, $height = 170, $width = 500) {
        global $conf;

        $more = '';
        $formconfirm = '';
        $inputarray = array();

        if ($formquestion) {
            $more.='<table class="nobordernopadding" width="100%">' . "\n";
            $more.='<tr><td colspan="3" valign="top">' . $formquestion['text'] . '</td></tr>' . "\n";
            foreach ($formquestion as $key => $input) {
                if (is_array($input)) {
                    if ($input['type'] == 'text') {
                        $more.='<tr><td valign="top">' . $input['label'] . '</td><td valign="top" colspan="2" align="left"><input type="text" class="flat" rowid="' . $input['name'] . '" name="' . $input['name'] . '" size="' . $input['size'] . '" value="' . $input['value'] . '" /></td></tr>' . "\n";
                    } else if ($input['type'] == 'password') {
                        $more.='<tr><td valign="top">' . $input['label'] . '</td><td valign="top" colspan="2" align="left"><input type="password" class="flat" rowid="' . $input['name'] . '" name="' . $input['name'] . '" size="' . $input['size'] . '" value="' . $input['value'] . '" /></td></tr>' . "\n";
                    } else if ($input['type'] == 'select') {
                        $more.='<tr><td valign="top">';
                        if (!empty($input['label']))
                            $more.=$input['label'] . '</td><td valign="top" colspan="2" align="left">';
                        $more.=$this->selectarray($input['name'], $input['values'], $input['default'], 1);
                        $more.='</td></tr>' . "\n";
                    }
                    else if ($input['type'] == 'checkbox') {
                        $more.='<tr>';
                        $more.='<td valign="top">' . $input['label'] . ' </td><td valign="top" align="left">';
                        $more.='<input type="checkbox" class="flat" rowid="' . $input['name'] . '" name="' . $input['name'] . '"';
                        if (!is_bool($input['value']) && $input['value'] != 'false')
                            $more.=' checked="true"';
                        if (is_bool($input['value']) && $input['value'])
                            $more.=' checked="true"';
                        if ($input['disabled'])
                            $more.=' disabled="true"';
                        $more.=' /></td>';
                        $more.='<td valign="top" align="left">&nbsp;</td>';
                        $more.='</tr>' . "\n";
                    }
                    else if ($input['type'] == 'radio') {
                        $i = 0;
                        foreach ($input['values'] as $selkey => $selval) {
                            $more.='<tr>';
                            if ($i == 0)
                                $more.='<td valign="top">' . $input['label'] . '</td>';
                            else
                                $more.='<td>&nbsp;</td>';
                            $more.='<td valign="top" width="20"><input type="radio" class="flat" rowid="' . $input['name'] . '" name="' . $input['name'] . '" value="' . $selkey . '"';
                            if ($input['disabled'])
                                $more.=' disabled="true"';
                            $more.=' /></td>';
                            $more.='<td valign="top" align="left">';
                            $more.=$selval;
                            $more.='</td></tr>' . "\n";
                            $i++;
                        }
                    }
                    else if ($input['type'] == 'other') {
                        $more.='<tr><td valign="top">';
                        if (!empty($input['label']))
                            $more.=$input['label'] . '</td><td valign="top" colspan="2" align="left">';
                        $more.=$input['value'];
                        $more.='</td></tr>' . "\n";
                    }
                    array_push($inputarray, $input['name']);
                }
            }
            $more.='</table>' . "\n";
        }

        $formconfirm.= "\n<!-- begin form_confirm -->\n";

        //  TODO El sistema por defecto soporta ajax
        //if ($useajax && $conf->use_javascript_ajax) {
        if ($useajax) {
            $autoOpen = true;
            $dialogconfirm = 'dialog-confirm';
            if (!is_int($useajax)) {
                $button = $useajax;
                $useajax = 1;
                $autoOpen = false;
                $dialogconfirm.='-' . $button;
            }
            $pageyes = $page . '&action=' . $action . '&confirm=yes';
            $pageno = ($useajax == 2 ? $page . '&confirm=no' : '');

            // New code using jQuery only
            $formconfirm.= '<div rowid="' . $dialogconfirm . '" title="' . String::escapeHtmlTag($title) . '" style="display: none;">';
            if (!empty($more))
                $formconfirm.= '<p>' . $more . '</p>';
            $formconfirm.= Imagen::help('', '') . ' ' . $question;
            $formconfirm.= '</div>' . "\n";
            $formconfirm.= '<script type="text/javascript">
            $(function() {
                var choice=\'ko\';
                var	$inputarray=' . json_encode($inputarray) . ';
                var button=\'' . $button . '\';
            	var dialogconfirm=\'' . $dialogconfirm . '\';

			    $( "#" + dialogconfirm ).dialog({
			        autoOpen: ' . ($autoOpen ? 'true' : 'false') . ',
			        resizable: false,
			        height:' . $height . ',
			        width:' . $width . ',
			        modal: true,
			        closeOnEscape: false,
			        close: function(event, ui) {
			             if (choice == \'ok\') {
			             	var options="";
			             	if ($inputarray.length>0) {
			             		$.each($inputarray, function() {
			             			var inputname = this;
			             			var inputvalue = $("#" + this).val();
			             			options += \'&\' + inputname + \'=\' + inputvalue;
			             		});
			             		//alert( options );
			             	}
			             	location.href=\'' . $pageyes . '\' + options;
			             }
                         ' . ($pageno ? 'if (choice == \'ko\') location.href=\'' . $pageno . '\';' : '') . '
		              },
			        buttons: {
			            \'' . String::escapeJs(Translate::transnoentities("Yes")) . '\': function() {
			                choice=\'ok\';
			                $(this).dialog(\'close\');
			            },
			            \'' . String::escapeJs(Translate::transnoentities("No")) . '\': function() {
			            	choice=\'ko\';
			                $(this).dialog(\'close\');
			            }
			        }
			    });

			    if (button.length > 0) {
			    	$( "#" + button ).click(function() {
			    		$( "#" + dialogconfirm ).dialog( \'open\' );
			    	});
			    }
			});
			</script>';

            $formconfirm.= "\n";
        }
        else {
            $formconfirm.= '<form method="POST" action="' . $page . '" class="notoptoleftroright">' . "\n";
            $formconfirm.= '<input type="hidden" name="action" value="' . $action . '">';
            $formconfirm.= '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">' . "\n";

            $formconfirm.= '<table width="100%" class="valid">' . "\n";

            // Ligne titre
            $formconfirm.= '<tr class="validtitre"><td class="validtitre" colspan="3">' . Imagen::picto('', 'recent') . ' ' . $title . '</td></tr>' . "\n";

            // Ligne formulaire
            if ($more) {
                $formconfirm.='<tr class="valid"><td class="valid" colspan="3">' . "\n";
                $formconfirm.=$more;
                $formconfirm.='</td></tr>' . "\n";
            }

            // Ligne message
            $formconfirm.= '<tr class="valid">';
            $formconfirm.= '<td class="valid">' . $question . '</td>';
            $formconfirm.= '<td class="valid">';
            $newselectedchoice = empty($selectedchoice) ? "no" : $selectedchoice;
            $formconfirm.= $this->selectyesno("confirm", $newselectedchoice);
            $formconfirm.= '</td>';
            $formconfirm.= '<td class="valid" align="center"><input class="btn btn-small" type="submit" value="' . Translate::trans("Validate") . '"></td>';
            $formconfirm.= '</tr>' . "\n";

            $formconfirm.= '</table>' . "\n";

            if (is_array($formquestion)) {
                foreach ($formquestion as $key => $input) {
                    if ($input['type'] == 'hidden')
                        $formconfirm.= '<input type="hidden" name="' . $input['name'] . '" value="' . $input['value'] . '">';
                }
            }

            $formconfirm.= "</form>\n";
            $formconfirm.= '<br>';
        }

        $formconfirm.= "<!-- end form_confirm -->\n";
        return $formconfirm;
    }

    /**
     *    Show a form to select a project
     *    @param      page        Page
     *    @param      socid       Id societe
     *    @param      selected    Id project pre-selectionne
     *    @param      htmlname    Nom du formulaire select
     */
    function form_project($page, $socid, $selected = '', $htmlname = 'projectid') {
        require_once(SIEMP_DOCUMENT_ROOT . "/lib/project.lib.php");

        Translate::load("project");
        if ($htmlname != "none") {
            print '<form method="post" action="' . $page . '">';
            print '<input type="hidden" name="action" value="classin">';
            print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
                print '<div class="input-append">';
                    Project::selectProjects($socid, $selected, $htmlname);
                    print '<input type="submit" class="btn btn-small" value="' . Translate::trans("Modify") . '">';
                print '</div>';
            print '</form>';
        } else {
            if ($selected) {
                $project = new Project($this->db);
                $project->fetch($selected);
                //print '<a href="'.SIEMP_URL_ROOT.'/project/fiche.php?rowid='.$selected.'">'.$project->title.'</a>';
                print $project->getNomUrl(0);
            } else {
                print "&nbsp;";
            }
        }
    }

    /**
     *    	Show a form to select payment conditions
     *    	@param      page        	Page
     *    	@param      selected    	Id condition pre-selectionne
     *    	@param      htmlname    	Name of select html field
     * 		@param		addempty		Ajoute entree vide
     */
    function form_conditions_reglement($page, $selected = '', $htmlname = 'cond_reglement_id', $addempty = 0) {
        if ($htmlname != "none") {
            print '<form method="post" action="' . $page . '">';
            print '<input type="hidden" name="action" value="setconditions">';
            print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
            print '<table class="nobordernopadding" cellpadding="0" cellspacing="0">';
            print '<tr><td>';
            $this->select_conditions_paiements($selected, $htmlname, -1, $addempty);
            print '</td>';
            print '<td align="left"><input type="submit" class="btn btn-small" value="' . Translate::trans("Modify") . '"></td>';
            print '</tr></table></form>';
        } else {
            if ($selected) {
                $this->load_cache_conditions_paiements();
                print $this->cache_conditions_paiements[$selected]['label'];
            } else {
                print "&nbsp;";
            }
        }
    }

    /**
     *    	Show a form to select a delivery delay
     *    	@param      page        	Page
     *    	@param      selected    	Id condition pre-selectionne
     *    	@param      htmlname    	Name of select html field
     * 		@param		addempty		Ajoute entree vide
     */
    function form_availability($page, $selected = '', $htmlname = 'availability', $addempty = 0) {
        if ($htmlname != "none") {
            print '<form method="post" action="' . $page . '">';
            print '<input type="hidden" name="action" value="setavailability">';
            print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
            print '<table class="nobordernopadding" cellpadding="0" cellspacing="0">';
            print '<tr><td>';
            $this->select_availability($selected, $htmlname, -1, $addempty);
            print '</td>';
            print '<td align="left"><input type="submit" class="btn btn-small" value="' . Translate::trans("Modify") . '"></td>';
            print '</tr></table></form>';
        } else {
            if ($selected) {
                $this->load_cache_availability();
                print $this->cache_availability[$selected]['label'];
            } else {
                print "&nbsp;";
            }
        }
    }

    /**
     *    	Show a select form to select origin
     *    	@param      page        	Page
     *    	@param      selected    	Id condition pre-selectionne
     *    	@param      htmlname    	Name of select html field
     * 		@param		addempty		Add empty entry
     */
    function form_demand_reason($page, $selected = '', $htmlname = 'demandreason', $addempty = 0) {
        if ($htmlname != "none") {
            print '<form method="post" action="' . $page . '">';
            print '<input type="hidden" name="action" value="setdemandreason">';
            print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
            print '<table class="nobordernopadding" cellpadding="0" cellspacing="0">';
            print '<tr><td>';
            $this->select_demand_reason($selected, $htmlname, -1, $addempty);
            print '</td>';
            print '<td align="left"><input type="submit" class="btn btn-small" value="' . Translate::trans("Modify") . '"></td>';
            print '</tr></table></form>';
        } else {
            if ($selected) {
                $this->load_cache_demand_reason();
                foreach ($this->cache_demand_reason as $key => $val) {
                    if ($val['rowid'] == $selected) {
                        print $val['label'];
                        break;
                    }
                }
            } else {
                print "&nbsp;";
            }
        }
    }

    /**
     *    Show a form to select a date
     *    @param      page        Page
     *    @param      selected    Date preselected
     *    @param      htmlname    Name of input html field
     */
    function form_date($page, $selected = '', $htmlname) {
        if ($htmlname != "none") {
            print '<form method="post" action="' . $page . '" name="form' . $htmlname . '">';
            print '<input type="hidden" name="action" value="set' . $htmlname . '">';
            print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
            print '<table class="nobordernopadding" cellpadding="0" cellspacing="0">';
            print '<tr><td>';
            print $this->select_date($selected, $htmlname, 0, 0, 1, 'form' . $htmlname);
            print '</td>';
            print '<td align="left"><input type="submit" class="btn btn-small" value="' . Translate::trans("Modify") . '"></td>';
            print '</tr></table></form>';
        } else {
            if ($selected) {
                $this->load_cache_types_paiements();
                print $this->cache_types_paiements[$selected]['label'];
            } else {
                print "&nbsp;";
            }
        }
    }

    /**
     *    	Show a select form to choose a user
     *    	@param      page        	Page
     *   	@param      selected    	Id of user preselected
     *    	@param      htmlname    	Name of input html field
     *  	@param      exclude         List of users rowid to exclude
     *  	@param      include         List of users rowid to include
     */
    function form_users($page, $selected = '', $htmlname = 'userid', $exclude = '', $include = '') {
        if ($htmlname != "none") {
            print '<form method="POST" action="' . $page . '" name="form' . $htmlname . '">';
            print '<input type="hidden" name="action" value="set' . $htmlname . '">';
            print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
            print '<table class="nobordernopadding" cellpadding="0" cellspacing="0">';
            print '<tr><td>';
            print $this->select_users($selected, $htmlname, 1, $exclude, 0, $include);
            print '</td>';
            print '<td align="left"><input type="submit" class="btn btn-small" value="' . Translate::trans("Modify") . '"></td>';
            print '</tr></table></form>';
        } else {
            if ($selected) {
                require_once(SIEMP_DOCUMENT_ROOT . "/user/class/user.class.php");
                //$this->load_cache_contacts();
                //print $this->cache_contacts[$selected];
                $theuser = new User($this->db);
                $theuser->fetch($selected);
                print $theuser->getNomUrl(1);
            } else {
                print "&nbsp;";
            }
        }
    }

    /**
     *    \brief      Affiche formulaire de selection des modes de reglement
     *    \param      page        Page
     *    \param      selected    Id mode pre-selectionne
     *    \param      htmlname    Name of select html field
     */
    function form_modes_reglement($page, $selected = '', $htmlname = 'mode_reglement_id') {
        if ($htmlname != "none") {
            print '<form method="POST" action="' . $page . '">';
            print '<input type="hidden" name="action" value="setmode">';
            print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
            print '<table class="nobordernopadding" cellpadding="0" cellspacing="0">';
            print '<tr><td>';
            $this->select_types_paiements($selected, $htmlname);
            print '</td>';
            print '<td align="left"><input type="submit" class="btn btn-small" value="' . Translate::trans("Modify") . '"></td>';
            print '</tr></table></form>';
        } else {
            if ($selected) {
                $this->load_cache_types_paiements();
                print $this->cache_types_paiements[$selected]['label'];
            } else {
                print "&nbsp;";
            }
        }
    }

    /**
     * 	Show a select box with available absolute discounts
     *
     *  @param  string	$page        	Page URL where form is shown
     *  @param  int		$selected    	Value pre-selected
     * 	@param  string	$htmlname    	Nom du formulaire select. Si none, non modifiable
     * 	@param	int		$socid			Third party rowid
     * 	@param	float	$amount			Total amount available
     * 	@param	string	$filter			SQL filter on discounts
     * 	@param	int		$maxvalue		Max value for lines that can be selected
     *  @param  string	$more           More string to add
     *  @return	void
     */
    function form_remise_dispo($page, $selected = '', $htmlname = 'remise_id', $socid, $amount, $filter = '', $maxvalue = 0, $more = '') {
        global $conf;
        if ($htmlname != "none") {
            print '<form method="post" action="' . $page . '">';
            print '<input type="hidden" name="action" value="setabsolutediscount">';
            print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
                if (!empty($conf->global->FACTURE_DEPOSITS_ARE_JUST_PAYMENTS)) {
                    if (!$filter || $filter == "fk_facture_source IS NULL")
                        print Translate::trans("CompanyHasAbsoluteDiscount", Price::price($amount), Translate::transnoentities("Currency" . $conf->currency)) . ': ';    // If we want deposit to be substracted to payments only and not to total of final invoice
                    else
                        print Translate::trans("CompanyHasCreditNote", Price::price($amount), Translate::transnoentities("Currency" . $conf->currency)) . ': ';
                }
                else {
                    if (!$filter || $filter == "fk_facture_source IS NULL OR (fk_facture_source IS NOT NULL AND description='(DEPOSIT)')")
                        print Translate::trans("CompanyHasAbsoluteDiscount", Price::price($amount), Translate::transnoentities("Currency" . $conf->currency)) . ': ';
                    else
                        print Translate::trans("CompanyHasCreditNote", Price::price($amount), Translate::transnoentities("Currency" . $conf->currency)) . ': ';
                }
                $newfilter = 'fk_facture IS NULL AND fk_facture_line IS NULL'; // Remises disponibles
                if ($filter)
                    $newfilter.=' AND (' . $filter . ')';

                print '<div class="input-append">';
                    $nbqualifiedlines = $this->select_remises($selected, $htmlname, $newfilter, $socid, $maxvalue);
                    if ($nbqualifiedlines > 0) {
                        print ' &nbsp; <input type="submit" class="btn btn-small" value="' . Translate::trans("UseLine") . '"';
                        if ($filter && $filter != "fk_facture_source IS NULL OR (fk_facture_source IS NOT NULL AND description='(DEPOSIT)')")
                            print '" title="' . Translate::trans("UseCreditNoteInInvoicePayment");
                        print '">';
                    }
                print '</div>';

                if ($more)
                    print $more;
            print '</form>';
        }
        else {
            if ($selected) {
                print $selected;
            } else {
                print "0";
            }
        }
    }

    /**
     *    \brief      Affiche formulaire de selection des contacts
     *    \param      page        Page
     *    \param      selected    Id contact pre-selectionne
     *    \param      htmlname    Nom du formulaire select
     */
    function form_contacts($page, $societe, $selected = '', $htmlname = 'contactidp') {
        if ($htmlname != "none") {
            print '<form method="post" action="' . $page . '">';
            print '<input type="hidden" name="action" value="set_contact">';
            print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
            print '<table class="nobordernopadding" cellpadding="0" cellspacing="0">';
            print '<tr><td>';
            $num = $this->select_contacts($societe->rowid, $selected, $htmlname);
            if ($num == 0) {
                print '<font class="alert alert-error fade in"><a class="close" data-dismiss="alert" href="#">&times;</a>Cette societe n\'a pas de contact, veuillez en cr�er un avant de faire votre proposition commerciale</font><br>';
                print '<a href="' . SIEMP_URL_ROOT . '/contact/fiche.php?socid=' . $societe->rowid . '&amp;action=create&amp;backtoreferer=1">' . Translate::trans('AddContact') . '</a>';
            }
            print '</td>';
            print '<td align="left"><input type="submit" class="btn btn-small" value="' . Translate::trans("Modify") . '"></td>';
            print '</tr></table></form>';
        } else {
            if ($selected) {
                require_once(SIEMP_DOCUMENT_ROOT . "/contact/class/contact.class.php");
                //$this->load_cache_contacts();
                //print $this->cache_contacts[$selected];
                $contact = new Contact($this->db);
                $contact->fetch($selected);
                print $contact->getFullName();
            } else {
                print "&nbsp;";
            }
        }
    }

    /**
     *    \brief      Affiche formulaire de selection des tiers
     *    \param      page        Page
     *    \param      selected    Id contact pre-selectionne
     *    \param      htmlname    Nom du formulaire select
     */
    function form_thirdparty($page, $selected = '', $htmlname = 'socid') {
        if ($htmlname != "none") {
            print '<form method="post" action="' . $page . '">';
            print '<input type="hidden" name="action" value="set_thirdparty">';
            print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
            print '<table class="nobordernopadding" cellpadding="0" cellspacing="0">';
            print '<tr><td>';
            $num = $this->select_societes($selected, $htmlname);
            print '</td>';
            print '<td align="left"><input type="submit" class="btn btn-small" value="' . Translate::trans("Modify") . '"></td>';
            print '</tr></table></form>';
        } else {
            if ($selected) {
                require_once(SIEMP_DOCUMENT_ROOT . "/societe/class/societe.class.php");
                $soc = new Societe($this->db);
                $soc->fetch($selected);
                print $soc->getNomUrl($langs);
            } else {
                print "&nbsp;";
            }
        }
    }

    /**
     *    	\brief      Affiche formulaire de selection de l'adresse
     *    	\param      page        	Page
     *    	\param      selected    	Id condition pre-selectionne
     *    	\param      htmlname    	Nom du formulaire select
     * 		\param		origin        	Origine de l'appel pour pouvoir creer un retour
     *      \param      originid      	Id de l'origine
     */
    function form_address($page, $selected = '', $socid, $htmlname = 'address_id', $origin = '', $originid = '') {
        if ($htmlname != "none") {
            print '<form method="post" action="' . $page . '">';
            print '<input type="hidden" name="action" value="setaddress">';
            print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';
            print '<table class="nobordernopadding" cellpadding="0" cellspacing="0">';
            print '<tr><td>';
            $this->select_address($selected, $socid, $htmlname, 1);
            print '</td>';
            print '<td align="left"><input type="submit" class="btn btn-small" value="' . Translate::trans("Modify") . '">';
            Translate::load("companies");
            print ' &nbsp; <a href=' . SIEMP_URL_ROOT . '/comm/address.php?socid=' . $socid . '&action=create&origin=' . $origin . '&originid=' . $originid . '>' . Translate::trans("AddAddress") . '</a>';
            print '</td></tr></table></form>';
        } else {
            if ($selected) {
                require_once(SIEMP_DOCUMENT_ROOT . "/societe/class/address.class.php");
                $address = new Address($this->db);
                $result = $address->fetch_address($selected);
                print '<a href=' . SIEMP_URL_ROOT . '/comm/address.php?socid=' . $address->socid . '&rowid=' . $address->rowid . '&action=edit&origin=' . $origin . '&originid=' . $originid . '>' . $address->label . '</a>';
            } else {
                print "&nbsp;";
            }
        }
    }

    /**
     *    Retourne la liste des devises, dans la langue de l'utilisateur
     *    @param     selected    code devise pre-selectionne
     *    @param     htmlname    nom de la liste deroulante
     */
    function select_currency($selected = '', $htmlname = 'currency_id') {
        print $this->selectcurrency($selected, $htmlname);
    }

    /**
     *    Retourne la liste des devises, dans la langue de l'utilisateur
     *    @param     selected    code devise pre-selectionne
     *    @param     htmlname    nom de la liste deroulante
     */
    function selectcurrency($selected = '', $htmlname = 'currency_id') {
        global $conf, $user;

        Translate::load("dict");

        $out = '';
        $currencyArray = array();
        $label = array();

        if ($selected == 'euro' || $selected == 'euros')
            $selected = 'EUR';   // Pour compatibilite

        $sql = "SELECT code_iso, label";
        $sql.= " FROM " . MAIN_DB_PREFIX . "c_currencies";
        $sql.= " WHERE active = 1";
        $sql.= " AND entity = ".$conf->entity;
        $sql.= " ORDER BY code_iso ASC";

        $resql = $this->db->query($sql);
        if ($resql) {
            $out.= '<select class="flat" name="' . $htmlname . '">';
            $num = $this->db->num_rows($resql);
            $i = 0;
            if ($num) {
                $foundselected = false;

                while ($i < $num) {
                    $obj = $this->db->fetch_object($resql);
                    $currencyArray[$i]['code_iso'] = $obj->code_iso;
                    $currencyArray[$i]['label'] = ($obj->code_iso && Translate::trans("Currency" . $obj->code_iso) != "Currency" . $obj->code_iso ? Translate::trans("Currency" . $obj->code_iso) : ($obj->label != '-' ? $obj->label : ''));
                    $label[$i] = $currencyArray[$i]['label'];
                    $i++;
                }

                array_multisort($label, SORT_ASC, $currencyArray);

                foreach ($currencyArray as $row) {
                    if ($selected && $selected == $row['code_iso']) {
                        $foundselected = true;
                        $out.= '<option value="' . $row['code_iso'] . '" selected="selected">';
                    } else {
                        $out.= '<option value="' . $row['code_iso'] . '">';
                    }
                    $out.= $row['label'];
                    if ($row['code_iso'])
                        $out.= ' (' . $row['code_iso'] . ')';
                    $out.= '</option>';
                }
            }
            $out.= '</select>';
            if ($user->admin)
                $out.= Imagen::infoAdmin(Translate::trans("YouCanChangeValuesForThisListFromDictionnarySetup"), 1);
            return $out;
        }
        else {
            CommonObject::printError($this->db);
        }
    }

    /**
     *      \brief      Output an HTML select vat rate
     *      \param      htmlname            Nom champ html
     *      \param      selectedrate        Forcage du taux tva pre-selectionne. Mettre '' pour aucun forcage.
     *      \param      societe_vendeuse    Objet societe vendeuse
     *      \param      societe_acheteuse   Objet societe acheteuse
     *      \param      idprod              Id product
     *      \param      info_bits           Miscellanous information on line
     *      \param      type               ''=Unknown, 0=Product, 1=Service (Used if idprod not defined)
     *      \remarks    Si vendeur non assujeti a TVA, TVA par defaut=0. Fin de regle.
     *                  Si le (pays vendeur = pays acheteur) alors la TVA par defaut=TVA du produit vendu. Fin de regle.
     *                  Si (vendeur et acheteur dans Communaute europeenne) et bien vendu = moyen de transports neuf (auto, bateau, avion), TVA par defaut=0 (La TVA doit etre paye par l'acheteur au centre d'impots de son pays et non au vendeur). Fin de regle.
     *                  Si (vendeur et acheteur dans Communaute europeenne) et bien vendu autre que transport neuf alors la TVA par defaut=TVA du produit vendu. Fin de regle.
     *                  Sinon la TVA proposee par defaut=0. Fin de regle.
     *      @deprecated
     */
    function select_tva($htmlname = 'tauxtva', $selectedrate = '', $societe_vendeuse = '', $societe_acheteuse = '', $idprod = 0, $info_bits = 0, $type = '') {
        print $this->load_tva($htmlname, $selectedrate, $societe_vendeuse, $societe_acheteuse, $idprod, $info_bits, $type);
    }

    /**
     *      \brief      Output an HTML select vat rate
     *      \param      htmlname           Nom champ html
     *      \param      selectedrate       Forcage du taux tva pre-selectionne. Mettre '' pour aucun forcage.
     *      \param      societe_vendeuse   Objet societe vendeuse
     *      \param      societe_acheteuse  Objet societe acheteuse
     *      \param      idprod             Id product
     *      \param      info_bits          Miscellanous information on line
     *      \param      type               ''=Unknown, 0=Product, 1=Service (Used if idprod not defined)
     *      \remarks    Si vendeur non assujeti a TVA, TVA par defaut=0. Fin de regle.
     *                  Si le (pays vendeur = pays acheteur) alors la TVA par defaut=TVA du produit vendu. Fin de regle.
     *                  Si (vendeur et acheteur dans Communaute europeenne) et bien vendu = moyen de transports neuf (auto, bateau, avion), TVA par defaut=0 (La TVA doit etre paye par l'acheteur au centre d'impots de son pays et non au vendeur). Fin de regle.
     *                  Si (vendeur et acheteur dans Communaute europeenne) et bien vendu autre que transport neuf alors la TVA par defaut=TVA du produit vendu. Fin de regle.
     *                  Sinon la TVA proposee par defaut=0. Fin de regle.
     */
    function load_tva($htmlname = 'tauxtva', $selectedrate = '', $societe_vendeuse = '', $societe_acheteuse = '', $idprod = 0, $info_bits = 0, $type = '', $pedido = 0) {
        global $conf, $mysoc;

        $return = '';
        $txtva = array();
        $libtva = array();
        $nprtva = array();
        $notetva = array();
        $localtax1 = array();

        // Define defaultnpr and defaultttx
        $defaulttx = str_replace('*', '', $selectedrate);
        
        // Check parameters
        if (is_object($societe_vendeuse) && !$societe_vendeuse->pays_code) {
            if ($societe_vendeuse->rowid == $mysoc->rowid) {
                $return.= '<font class="alert alert-error fade in"><a class="close" data-dismiss="alert" href="#">&times;</a>' . Translate::trans("ErrorYourCountryIsNotDefined") . '</div>';
            } else {
                $return.= '<font class="alert alert-error fade in"><a class="close" data-dismiss="alert" href="#">&times;</a>' . Translate::trans("ErrorSupplierCountryIsNotDefined") . '</div>';
            }
            return $return;
        }

        // Get list of all VAT rates to show
        // First we defined code_pays to use to find list
        if (is_object($societe_vendeuse)) {
            $code_pays = "'" . $societe_vendeuse->pays_code . "'";
        } else {
            $code_pays = "'" . $mysoc->pays_code . "'";   // Pour compatibilite ascendente
        }
        if (!empty($conf->global->SERVICE_ARE_ECOMMERCE_200238EC)) {    // If option to have vat for end customer for services is on
            if (!$societe_vendeuse->isInEEC() && $societe_acheteuse->isInEEC() && !$societe_acheteuse->isACompany()) {
                // We also add the buyer
                if (is_numeric($type)) {
                    if ($type == 1) { // We know product is a service
                        $code_pays.=",'" . $societe_acheteuse->pays_code . "'";
                    }
                } else if (!$idprod) {  // We don't know type of product
                    $code_pays.=",'" . $societe_acheteuse->pays_code . "'";
                } else {
                    $prodstatic = new Product($this->db);
                    $prodstatic->fetch($idprod);
                    if ($prodstatic->type == 1) {   // We know product is a service
                        $code_pays.=",'" . $societe_acheteuse->pays_code . "'";
                    }
                }
            }
        }

        if ($societe_vendeuse->fournisseur && !$pedido) {
            // Now we get list
            $sql = "SELECT t.taux, t.note, t.localtax1";
            $sql.= " FROM " . MAIN_DB_PREFIX . "c_tva as t, " . MAIN_DB_PREFIX . "c_pays as p";
            $sql.= " WHERE t.fk_pays = p.rowid";
            $sql.= " AND t.active = 1";
            $sql.= " AND p.code in (" . $code_pays . ")";
            $sql.= " AND t.entity = ".$conf->entity;
            $sql.= " AND p.entity = ".$conf->entity;
            $sql.= " ORDER BY t.taux ASC";
        } else {
            // Now we get list
            $sql = "SELECT DISTINCT t.taux";
            $sql.= " FROM " . MAIN_DB_PREFIX . "c_tva as t, " . MAIN_DB_PREFIX . "c_pays as p";
            $sql.= " WHERE t.fk_pays = p.rowid";
            $sql.= " AND t.active = 1";
            $sql.= " AND p.code in (" . $code_pays . ")";
            $sql.= " AND t.entity = ".$conf->entity;
            $sql.= " AND p.entity = ".$conf->entity;
            $sql.= " ORDER BY t.taux ASC";
        }
        
        $resql = $this->db->query($sql);
        if ($resql) {
            $num = $this->db->num_rows($resql);
            if ($num) {
                for ($i = 0; $i < $num; $i++) {
                    $obj = $this->db->fetch_object($resql);
                    $txtva[$i] = $obj->taux;
                    $libtva[$i] = $obj->taux;
                    $notetva[$i] = (isset($obj->note) ? $obj->note : '');
                    $localtax1[$i] = (isset($obj->localtax1) ? $obj->localtax1 : '');
                }
            } else {
                $return.= '<font class="alert alert-error fade in"><a class="close" data-dismiss="alert" href="#">&times;</a>' . Translate::trans("ErrorNoVATRateDefinedForSellerCountry", $code_pays) . '</font>';
            }
        } else {
            $return.= '<font class="alert alert-error fade in"><a class="close" data-dismiss="alert" href="#">&times;</a>' . $this->db->error() . '</font>';
        }

        // Definition du taux a pre-selectionner (si defaulttx non force et donc vaut -1 ou '')
        if ($defaulttx < 0 || String::strlen($defaulttx) == 0) {
            $defaulttx = Tva::getDefaultTva($societe_vendeuse, $societe_acheteuse, $idprod);
        }

        // Si taux par defaut n'a pu etre determine, on prend dernier de la liste.
        // Comme ils sont tries par ordre croissant, dernier = plus eleve = taux courant
        if ($defaulttx < 0 || String::strlen($defaulttx) == 0) {
            $defaulttx = $txtva[sizeof($txtva) - 1];
        }

        $nbdetaux = sizeof($txtva);
        if ($nbdetaux > 0) {
            $return.= '<select class="flat" rowid="' . $htmlname . '" name="' . $htmlname . '">';

            for ($i = 0; $i < $nbdetaux; $i++) {
                $return.= '<option value="' . $txtva[$i];
                
                if ($societe_vendeuse->fournisseur)
                    $return.= $localtax1[$i] ? '-I' : '';
                $return.= '"';
                
                if ($txtva[$i] == $defaulttx) {
                    $return.= ' selected="selected"';
                }

                if ($societe_vendeuse->fournisseur && !$pedido) {
                    $return.= '>' . $notetva[$i];
                    $return.= '</option>';
                } else {
                    $return.= '>' . Tva::vatRate($libtva[$i]);
                    $return.= '</option>';
                }

                $return.= '>' . Tva::vatRate($libtva[$i]);
                $return.= '</option>';

                $this->tva_taux_value[$i] = $txtva[$i];
                $this->tva_taux_libelle[$i] = $libtva[$i];
            }
            $return.= '</select>';
        }

        return $return;
    }

    /**
     * 		Show a HTML widget to input a date or combo list for day, month, years and optionnaly hours and minutes
     *      Fields are preselected with :
     *            	- set_time date (Local PHP server timestamps or date format YYYY-MM-DD or YYYY-MM-DD HH:MM)
     *            	- local date of PHP server if set_time is ''
     *            	- Empty (fields empty) if set_time is -1 (in this case, parameter empty must also have value 1)
     * 		@param	set_time 		Pre-selected date (must be a local PHP server timestamp)
     * 		@param	prefix			Prefix for fields name
     * 		@param	h				1=Show also hours
     * 		@param	m				1=Show also minutes
     * 		@param	empty			0=Fields required, 1=Empty input is allowed
     * 		@param	form_name 		Form name. Used by popup dates.
     * 		@param	d				1=Show days, month, years
     * 		@param	addnowbutton	Add a button "Now"
     * 		@param	nooutput		Do not output html string but return it
     * 		@param 	disabled		Disable input fields
     *      @param  fullday         When a checkbox with this html name is on, hour and day are set with 00:00 or 23:59
     * 		@return	nothing or string if nooutput is 1
     */
    static function select_date($set_time = '', $prefix = 're', $h = 0, $m = 0, $empty = 0, $form_name = "", $d = 1, $addnowbutton = 0, $nooutput = 0, $disabled = 0, $fullday = '') {
        global $conf;

        $retstring = '';

        if ($prefix == '')
            $prefix = 're';
        if ($h == '')
            $h = 0;
        if ($m == '')
            $m = 0;
        if ($empty == '')
            $empty = 0;

        if (!$set_time && $empty == 0)
            $set_time = Date::now('tzuser');

        // Analysis of the pre-selection date
        if (preg_match('/^([0-9]+)\-([0-9]+)\-([0-9]+)\s?([0-9]+)?:?([0-9]+)?/', $set_time, $reg)) {
            // Date format 'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM:SS'
            $syear = $reg[1];
            $smonth = $reg[2];
            $sday = $reg[3];
            $shour = $reg[4];
            $smin = $reg[5];
        } elseif (strval($set_time) != '' && $set_time != -1) {
            // set_time est un timestamps (0 possible)
            $syear = Date::printDate($set_time, "%Y");
            $smonth = Date::printDate($set_time, "%m");
            $sday = Date::printDate($set_time, "%d");
            $shour = Date::printDate($set_time, "%H");
            $smin = Date::printDate($set_time, "%M");
        } else {
            // Date est '' ou vaut -1
            $syear = '';
            $smonth = '';
            $sday = '';
            $shour = '';
            $smin = '';
        }

        if ($d) {
            // Show date with popup
            //  TODO El sistema por defecto soporta ajax
            if ((empty($conf->global->MAIN_POPUP_CALENDAR) || $conf->global->MAIN_POPUP_CALENDAR != "none")) {
                
                $formated_date = null;
                if (strval($set_time) != '' && $set_time != -1) {
                    //$formated_date=Date::printDate($set_time,$conf->format_date_short);
                    $formated_date = Date::printDate($set_time, Translate::trans("FormatDateShort"));  // FormatDateShort for Date::printDate/FormatDateShortJava that is same for javascript
                }

                // Calendrier popup version eldy
                if (empty($conf->global->MAIN_POPUP_CALENDAR) || $conf->global->MAIN_POPUP_CALENDAR == "eldy") {
                    // Zone de saisie manuelle de la date
                    $retstring.='<input rowid="' . $prefix . '" name="' . $prefix . '" type="text" size="9" maxlength="11" value="' . $formated_date . '"';
                    $retstring.=($disabled ? ' disabled="true"' : '');
                    $retstring.=' onChange="dpChangeDay(\'' . $prefix . '\',\'' . Translate::trans("FormatDateShortJava") . '\'); "';  // FormatDateShort for Date::printDate/FormatDateShortJava that is same for javascript
                    $retstring.='>';

                    // Icone calendrier
                    if (!$disabled) {
                        $retstring.='<button rowid="' . $prefix . 'Button" type="button" class="dpInvisibleButtons"';
                        $base = SIEMP_URL_ROOT . '/lib/';
                        $retstring.=' onClick="showDP(\'' . $base . '\',\'' . $prefix . '\',\'' . Translate::trans("FormatDateShortJava") . '\',\'' . Translate::getDefaultLang() . '\');">' . Imagen::object(Translate::trans("SelectDate"), 'calendarday') . '</button>';
                    }

                    $retstring.='<input type="hidden" rowid="' . $prefix . 'day"   name="' . $prefix . 'day"   value="' . $sday . '">' . "\n";
                    $retstring.='<input type="hidden" rowid="' . $prefix . 'month" name="' . $prefix . 'month" value="' . $smonth . '">' . "\n";
                    $retstring.='<input type="hidden" rowid="' . $prefix . 'year"  name="' . $prefix . 'year"  value="' . $syear . '">' . "\n";
                } else {
                    print "Bad value of calendar";
                }
            }

            // Show date with combo selects
            //  TODO El sistema por defecto soporta ajax
            if (isset($conf->global->MAIN_POPUP_CALENDAR) && $conf->global->MAIN_POPUP_CALENDAR == "none") {
                // Day
                $retstring.='<select' . ($disabled ? ' disabled="true"' : '') . ' class="flat" name="' . $prefix . 'day">';

                if ($empty || $set_time == -1) {
                    $retstring.='<option value="0" selected="selected">&nbsp;</option>';
                }

                for ($day = 1; $day <= 31; $day++) {
                    if ($day == $sday) {
                        $retstring.="<option value=\"$day\" selected=\"selected\">$day";
                    } else {
                        $retstring.="<option value=\"$day\">$day";
                    }
                    $retstring.="</option>";
                }

                $retstring.="</select>";

                $retstring.='<select' . ($disabled ? ' disabled="true"' : '') . ' class="flat" name="' . $prefix . 'month">';
                if ($empty || $set_time == -1) {
                    $retstring.='<option value="0" selected="selected">&nbsp;</option>';
                }

                // Month
                for ($month = 1; $month <= 12; $month++) {
                    $retstring.='<option value="' . $month . '"' . ($month == $smonth ? ' selected="selected"' : '') . '>';
                    $retstring.=Date::printDate(mktime(12, 0, 0, $month, 1, 2000), "%b");
                    $retstring.="</option>";
                }
                $retstring.="</select>";

                // Year
                if ($empty || $set_time == -1) {
                    $retstring.='<input' . ($disabled ? ' disabled="true"' : '') . ' class="flat" type="text" size="3" maxlength="4" name="' . $prefix . 'year" value="' . $syear . '">';
                } else {
                    $retstring.='<select' . ($disabled ? ' disabled="true"' : '') . ' class="flat" name="' . $prefix . 'year">';

                    for ($year = $syear - 5; $year < $syear + 10; $year++) {
                        if ($year == $syear) {
                            $retstring.="<option value=\"$year\" selected=\"true\">" . $year;
                        } else {
                            $retstring.="<option value=\"$year\">" . $year;
                        }
                        $retstring.="</option>";
                    }
                    $retstring.="</select>\n";
                }
            }
        }

        if ($d && $h)
            $retstring.='&nbsp;';

        if ($h) {
            // Show hour
            $retstring.='<select' . ($disabled ? ' disabled="true"' : '') . ' class="flat ' . ($fullday ? $fullday . 'hour' : '') . '" name="' . $prefix . 'hour">';
            if ($empty)
                $retstring.='<option value="-1">&nbsp;</option>';
            for ($hour = 0; $hour < 24; $hour++) {
                if (String::strlen($hour) < 2) {
                    $hour = "0" . $hour;
                }
                if ($hour == $shour) {
                    $retstring.="<option value=\"$hour\" selected=\"true\">$hour</option>";
                } else {
                    $retstring.="<option value=\"$hour\">$hour</option>";
                }
            }
            $retstring.="</select>";
            $retstring.="H\n";
        }

        if ($m) {
            // Show minutes
            $retstring.='<select' . ($disabled ? ' disabled="true"' : '') . ' class="flat ' . ($fullday ? $fullday . 'min' : '') . '" name="' . $prefix . 'min">';
            if ($empty)
                $retstring.='<option value="-1">&nbsp;</option>';
            for ($min = 0; $min < 60; $min++) {
                if (String::strlen($min) < 2) {
                    $min = "0" . $min;
                }
                if ($min == $smin) {
                    $retstring.="<option value=\"$min\" selected=\"true\">$min</option>";
                } else {
                    $retstring.="<option value=\"$min\">$min</option>";
                }
            }
            $retstring.="</select>";
            $retstring.="M\n";
        }

        // Add a "Now" button
        //  TODO El sistema por defecto soporta ajax
        if ($addnowbutton) {
            // Script which will be inserted in the OnClick of the "Now" button
            $reset_scripts = "";

            // Generate the date part, depending on the use or not of the javascript calendar
            if (empty($conf->global->MAIN_POPUP_CALENDAR) || $conf->global->MAIN_POPUP_CALENDAR == "eldy") {
                $base = SIEMP_URL_ROOT . '/lib/';
                $reset_scripts .= 'resetDP(\'' . $base . '\',\'' . $prefix . '\',\'' . Translate::trans("FormatDateShortJava") . '\',\'' . Translate::getDefaultLang() . '\');';
            } else {
                $reset_scripts .= 'this.form.elements[\'' . $prefix . 'day\'].value=formatDate(new Date(), \'d\'); ';
                $reset_scripts .= 'this.form.elements[\'' . $prefix . 'month\'].value=formatDate(new Date(), \'M\'); ';
                $reset_scripts .= 'this.form.elements[\'' . $prefix . 'year\'].value=formatDate(new Date(), \'yyyy\'); ';
            }
            // Generate the hour part
            if ($h) {
                if ($fullday)
                    $reset_scripts .= " if (jQuery('#fullday:checked').val() == null) {";
                $reset_scripts .= 'this.form.elements[\'' . $prefix . 'hour\'].value=formatDate(new Date(), \'HH\'); ';
                if ($fullday)
                    $reset_scripts .= ' } ';
            }
            // Generate the minute part
            if ($m) {
                if ($fullday)
                    $reset_scripts .= " if (jQuery('#fullday:checked').val() == null) {";
                $reset_scripts .= 'this.form.elements[\'' . $prefix . 'min\'].value=formatDate(new Date(), \'mm\'); ';
                if ($fullday)
                    $reset_scripts .= ' } ';
            }
            // If reset_scripts is not empty, print the button with the reset_scripts in OnClick
            if ($reset_scripts) {
                $retstring.='<button class="dpInvisibleButtons" rowid="' . $prefix . 'ButtonNow" type="button" name="_useless" value="Now" onClick="' . $reset_scripts . '">';
                $retstring.=Translate::trans("Now");
                //print Imagen::refresh(Translate::trans("Now"));
                $retstring.='</button> ';
            }
        }

        if (!empty($nooutput))
            return $retstring;

        print $retstring;
        return;
    }

    /**
     * 	Function to show a form to select a duration on a page
     * 	@param		prefix   	prefix
     * 	@param  	iSecond  	Default preselected duration (number of seconds)
     * 	@param		disabled	Disable the combo box
     */
    function select_duration($prefix, $iSecond = '', $disabled = 0) {
        if ($iSecond) {
            require_once(SIEMP_DOCUMENT_ROOT . "/lib/date.lib.php");

            $hourSelected = Date::secondToTime($iSecond, 'hour');
            $minSelected = Date::secondToTime($iSecond, 'min');
        }

        print '<select class="flat" name="' . $prefix . 'hour"' . ($disabled ? ' disabled="true"' : '') . '>';
        for ($hour = 0; $hour < 24; $hour++) {
            print '<option value="' . $hour . '"';
            if ($hourSelected == $hour) {
                print " selected=\"true\"";
            }
            print ">" . $hour . "</option>";
        }
        print "</select>";
        print "H &nbsp;";
        print '<select class="flat" name="' . $prefix . 'min"' . ($disabled ? ' disabled="true"' : '') . '>';
        for ($min = 0; $min <= 55; $min = $min + 5) {
            print '<option value="' . $min . '"';
            if ($minSelected == $min)
                print ' selected="selected"';
            print '>' . $min . '</option>';
        }
        print "</select>";
        print "M&nbsp;";
    }

    /**
     * 	Show a select form from an array
     * 	@param	htmlname        Name of html select area
     * 	@param	array           Array with key+value
     * 	@param	rowid              Preselected key
     * 	@param	show_empty      1 si il faut ajouter une valeur vide dans la liste, 0 sinon
     * 	@param	key_in_label    1 pour afficher la key dans la valeur "[key] value"
     * 	@param	value_as_key    1 to use value as key
     * 	@param  option          Valeur de l'option en fonction du type choisi
     * 	@param  translate       Translate and encode value
     * 	@param	maxlen			Length maximum for labels
     * 	@param	disabled		Html select box is disabled
     * 	@return	string			HTML select string
     */
    function selectarray($htmlname, $array, $rowid = '', $show_empty = 0, $key_in_label = 0, $value_as_key = 0, $option = '', $translate = 0, $maxlen = 0, $disabled = 0) {

        $out = '<select rowid="' . $htmlname . '" ' . ($disabled ? 'disabled="true" ' : '') . 'class="flat" name="' . $htmlname . '" ' . ($option != '' ? $option : '') . '>';

        if ($show_empty) {
            $out.='<option value="-1"' . ($rowid == -1 ? ' selected="selected"' : '') . '>&nbsp;</option>' . "\n";
        }

        if (is_array($array)) {
            foreach ($array as $key => $value) {
                $out.='<option value="' . ($value_as_key ? $value : $key) . '"';
                // Si il faut pre-selectionner une valeur
                if ($rowid != '' && ($rowid == $key || $rowid == $value)) {
                    $out.=' selected="selected"';
                }

                $out.='>';

                if ($key_in_label) {
                    $newval = ($translate ? Translate::trans($value) : $value);
                    $selectOptionValue = siemp_htmlentitiesbr($key . ' - ' . ($maxlen ? String::trunc($newval, $maxlen) : $newval));
                    $out.=$selectOptionValue;
                } else {
                    $newval = ($translate ? Translate::trans($value) : $value);
                    $selectOptionValue = siemp_htmlentitiesbr($maxlen ? String::trunc($newval, $maxlen) : $newval);
                    if ($value == '' || $value == '-') {
                        $selectOptionValue = '&nbsp;';
                    }
                    $out.=$selectOptionValue;
                }
                $out.="</option>\n";
            }
        }

        $out.="</select>";
        return $out;
    }

    /**
     * 	Show a select form from an array
     * 	@deprecated				Use selectarray instead
     */
    function select_array($htmlname, $array, $rowid = '', $show_empty = 0, $key_in_label = 0, $value_as_key = 0, $option = '', $translate = 0, $maxlen = 0) {
        print $this->selectarray($htmlname, $array, $rowid, $show_empty, $key_in_label, $value_as_key, $option, $translate, $maxlen);
    }

    /**
     *    	Return an html string with a select combo box to choose yes or no
     *    	@param      name            Name of html select field
     *    	@param      value           Pre-selected value
     *  	@param      option          0 return yes/no, 1 return 1/0
     * 		@return		int or string	See option
     */
    public static function selectyesno($htmlname, $value = '', $option = 0) {
        $yes = "yes";
        $no = "no";

        if ($option) {
            $yes = "1";
            $no = "0";
        }

        $resultyesno = '<select class="flat" rowid="' . $htmlname . '" name="' . $htmlname . '">' . "\n";
        if (("$value" == 'yes') || ($value == 1)) {
            $resultyesno .= '<option value="' . $yes . '" selected="selected">' . Translate::trans("Yes") . '</option>' . "\n";
            $resultyesno .= '<option value="' . $no . '">' . Translate::trans("No") . '</option>' . "\n";
        } else {
            $resultyesno .= '<option value="' . $yes . '">' . Translate::trans("Yes") . '</option>' . "\n";
            $resultyesno .= '<option value="' . $no . '" selected="selected">' . Translate::trans("No") . '</option>' . "\n";
        }
        $resultyesno .= '</select>' . "\n";
        return $resultyesno;
    }

    /**
     *    Retourne la liste des modeles d'export
     *    @param      selected          Id modele pre-selectionne
     *    @param      htmlname          Nom de la zone select
     *    @param      type              Type des modeles recherches
     *    @param      useempty          Affiche valeur vide dans liste
     */
    function select_export_model($selected = '', $htmlname = 'exportmodelid', $type = '', $useempty = 0) {
        global $conf;
        $sql = "SELECT rowid, label";
        $sql.= " FROM " . MAIN_DB_PREFIX . "export_model";
        $sql.= " WHERE type = '" . $type . "'";
        $sql.= " AND entity = ".$conf->entity;
        $sql.= " ORDER BY rowid";
        $result = $this->db->query($sql);
        if ($result) {
            print '<select class="flat" name="' . $htmlname . '">';
            if ($useempty) {
                print '<option value="-1">&nbsp;</option>';
            }

            $num = $this->db->num_rows($result);
            $i = 0;
            while ($i < $num) {
                $obj = $this->db->fetch_object($result);
                if ($selected == $obj->rowid) {
                    print '<option value="' . $obj->rowid . '" selected="selected">';
                } else {
                    print '<option value="' . $obj->rowid . '">';
                }
                print $obj->label;
                print '</option>';
                $i++;
            }
            print "</select>";
        } else {
            CommonObject::printError($this->db);
        }
    }

    /**
     *    Return a HTML area with the reference of object and a navigation bar for a business object
     *    To add a particular filter on select, you must set $object->next_prev_filter to SQL criteria.
     *    @param      object		Object to show
     *    @param      paramid   	Name of parameter to use to name the rowid into the URL link
     *    @param      morehtml  	More html content to output just before the nav bar
     *    @param	  shownav	  	Show Condition (navigation is shown if value is 1)
     *    @param      fieldid   	Nom du champ en base a utiliser pour select next et previous
     *    @param      fieldref   	Nom du champ objet ref (object->ref) a utiliser pour select next et previous
     *    @param      morehtmlref  	Code html supplementaire a afficher apres ref
     *    @param      moreparam  	More param to add in nav link url.
     * 	  @return     string    	Portion HTML avec ref + boutons nav
     */
    function showrefnav($object, $paramid, $morehtml = '', $shownav = 1, $fieldid = 'rowid', $fieldref = 'ref', $morehtmlref = '', $moreparam = '') {
        $ret = '';
        
        $object->load_previous_next_ref((isset($object->next_prev_filter) ? $object->next_prev_filter : ''), $fieldid);
        $previous_ref = $object->ref_previous ? '<a href="' . $_SERVER["PHP_SELF"] . '?' . $paramid . '=' . urlencode($object->ref_previous) . $moreparam . '">' . Imagen::previous() . '</a>' : '';
        $next_ref = $object->ref_next ? '<a href="' . $_SERVER["PHP_SELF"] . '?' . $paramid . '=' . urlencode($object->ref_next) . $moreparam . '">' . Imagen::next() . '</a>' : '';
        
        //print "xx".$previous_ref."x".$next_ref;
        if ($previous_ref || $next_ref || $morehtml) {
            $ret.='<table class="nobordernopadding" width="100%"><tr class="nobordernopadding"><td class="nobordernopadding">';
        }

        $ret.=$object->$fieldref;
        if ($morehtmlref) {
            $ret.=' ' . $morehtmlref;
        }

        if ($morehtml) {
            $ret.='</td><td class="nobordernopadding" align="right">' . $morehtml;
        }
        if ($shownav && ($previous_ref || $next_ref)) {
            $ret.='</td><td class="nobordernopadding" align="center" width="20">' . $previous_ref . '</td>';
            $ret.='<td class="nobordernopadding" align="center" width="20">' . $next_ref;
        }
        if ($previous_ref || $next_ref || $morehtml) {
            $ret.='</td></tr></table>';
        }
        return $ret;
    }

    /**
     *    	Return HTML code to output a photo
     *    	@param      modulepart		Key to define module concerned ('societe', 'userphoto', 'memberphoto')
     *     	@param      object			Object containing data to retrieve file name
     * 		@param		width			Width of photo
     * 	  	@return     string    		HTML code to output photo
     */
    function showphoto($modulepart, $object, $width = 100) {
        global $conf;

        $ret = '';
        $dir = '';
        $file = '';
        $altfile = '';
        $email = '';

        if ($modulepart == 'societe') {
            $dir = $conf->societe->dir_output;
            $smallfile = $object->logo;
            $smallfile = preg_replace('/(\.png|\.gif|\.jpg|\.jpeg|\.bmp)/i', '_small\\1', $smallfile);
            if ($object->logo)
                $file = $object->rowid . '/logos/thumbs/' . $smallfile;
        }
        else if ($modulepart == 'userphoto') {
            $dir = $conf->user->dir_output;
            if ($object->photo)
                $file = get_exdir($object->rowid, 2) . $object->photo;
            if (!empty($conf->global->MAIN_OLD_IMAGE_LINKS))
                $altfile = $object->rowid . ".jpg"; // For backward compatibility
            $email = $object->email;
        }
        else if ($modulepart == 'memberphoto') {
            $dir = $conf->adherent->dir_output;
            if ($object->photo)
                $file = get_exdir($object->rowid, 2) . 'photos/' . $object->photo;
            if (!empty($conf->global->MAIN_OLD_IMAGE_LINKS))
                $altfile = $object->rowid . ".jpg"; // For backward compatibility
            $email = $object->email;
        }

        if ($dir) {
            $cache = '0';
            if ($file && file_exists($dir . "/" . $file)) {
                if ($modulepart == 'societe') {
                    // TODO Link to large image
                    $ret.='<a href="' . SIEMP_URL_ROOT . '/viewimage.php?modulepart=' . $modulepart . '&file=' . urlencode($file) . '&cache=' . $cache . '">';
                    $ret.='<img alt="Photo" rowid="photologo' . (preg_replace('/[^a-z]/i', '_', $file)) . '" class="photologo" border="0" style="height: 100px;" src="' . SIEMP_URL_ROOT . '/viewimage.php?modulepart=' . $modulepart . '&file=' . urlencode($file) . '&cache=' . $cache . '">';
                    $ret.='</a>';
                } else {
                    // TODO Link to large image
                    $ret.='<a href="' . SIEMP_URL_ROOT . '/viewimage.php?modulepart=' . $modulepart . '&file=' . urlencode($file) . '&cache=' . $cache . '">';
                    $ret.='<img alt="Photo" rowid="photologo' . (preg_replace('/[^a-z]/i', '_', $file)) . '" class="photologo" border="0" width="' . $width . '" src="' . SIEMP_URL_ROOT . '/viewimage.php?modulepart=' . $modulepart . '&file=' . urlencode($file) . '&cache=' . $cache . '">';
                    $ret.='</a>';
                }
            } else if ($altfile && file_exists($dir . "/" . $altfile)) {
                $ret.='<a href="' . SIEMP_URL_ROOT . '/viewimage.php?modulepart=' . $modulepart . '&file=' . urlencode($file) . '&cache=' . $cache . '">';
                $ret.='<img alt="Photo alt" rowid="photologo' . (preg_replace('/[^a-z]/i', '_', $file)) . '" class="photologo" border="0" width="' . $width . '" src="' . SIEMP_URL_ROOT . '/viewimage.php?modulepart=' . $modulepart . '&file=' . urlencode($altfile) . '&cache=' . $cache . '">';
                $ret.='</a>';
            }
        }
        else
            CommonObject::printError('', 'Call of showphoto with wrong parameters');

        /* Disabled. lightbox seems to not work. I don't know why.
          $ret.="\n<script type=\"text/javascript\">
          jQuery(function() {
          jQuery('.photologo').lightBox();
          });
          </script>\n";

          $ret.="\n<script type=\"text/javascript\">
          jQuery(function() {
          jQuery('.photologo').lightBox({
          overlayBgColor: '#FFF',
          overlayOpacity: 0.6,
          imageLoading: '".SIEMP_URL_ROOT."/includes/jquery/plugins/lightbox/images/lightbox-ico-loading.gif',
          imageBtnClose: '".SIEMP_URL_ROOT."/includes/jquery/plugins/lightbox/images/lightbox-btn-close.gif',
          imageBtnPrev: '".SIEMP_URL_ROOT."/includes/jquery/plugins/lightbox/images/lightbox-btn-prev.gif',
          imageBtnNext: '".SIEMP_URL_ROOT."/includes/jquery/plugins/lightbox/images/lightbox-btn-next.gif',
          containerResizeSpeed: 350,
          txtImage: 'Imagem',
          txtOf: 'de'
          });
          });
          </script>\n";
         */

        return $ret;
    }

    /**
     * 	Return select list of groups
     *  @param      selected        Id group preselected
     *  @param      htmlname        Field name in form
     *  @param      show_empty      0=liste sans valeur nulle, 1=ajoute valeur inconnue
     *  @param      exclude         Array list of groups rowid to exclude
     * 	@param		disabled		If select list must be disabled
     *  @param      include         Array list of groups rowid to include
     * 	@param		enableonly		Array list of groups rowid to be enabled. All other must be disabled
     */
    function select_siempgroups($selected = '', $htmlname = 'groupid', $show_empty = 0, $exclude = '', $disabled = 0, $include = '', $enableonly = '') {
        global $conf;

        // Permettre l'exclusion de groupes
        if (is_array($exclude))
            $excludeGroups = implode("','", $exclude);
        // Permettre l'inclusion de groupes
        if (is_array($include))
            $includeGroups = implode("','", $include);

        $out = '';

        // On recherche les groupes
        $sql = "SELECT ug.rowid, ug.nom ";
        $sql.= " FROM " . MAIN_DB_PREFIX . "usergroup as ug ";
        $sql.= " WHERE ug.entity = " . $conf->entity;
        if (is_array($exclude) && $excludeGroups)
            $sql.= " AND ug.rowid NOT IN ('" . $excludeGroups . "')";
        if (is_array($include) && $includeGroups)
            $sql.= " AND ug.rowid IN ('" . $includeGroups . "')";
        $sql.= " ORDER BY ug.nom ASC";

        Syslog::log("Form::select_siempgroups sql=" . $sql);
        $resql = $this->db->query($sql);
        if ($resql) {
            $out.= '<select class="flat" name="' . $htmlname . '"' . ($disabled ? ' disabled="true"' : '') . '>';
            if ($show_empty)
                $out.= '<option value="-1"' . ($rowid == -1 ? ' selected="selected"' : '') . '>&nbsp;</option>' . "\n";
            $num = $this->db->num_rows($resql);
            $i = 0;
            if ($num) {
                while ($i < $num) {
                    $obj = $this->db->fetch_object($resql);
                    $disableline = 0;
                    if (is_array($enableonly) && sizeof($enableonly) && !in_array($obj->rowid, $enableonly))
                        $disableline = 1;

                    $out.= '<option value="' . $obj->rowid . '"';
                    if ($disableline)
                        $out.= ' disabled="true"';
                    if ((is_object($selected) && $selected->rowid == $obj->rowid) || (!is_object($selected) && $selected == $obj->rowid)) {
                        $out.= ' selected="selected"';
                    }
                    $out.= '>';

                    $out.= $obj->nom;

                    $out.= '</option>';
                    $i++;
                }
            }
            $out.= '</select>';
        } else {
            CommonObject::printError($this->db);
        }

        return $out;
    }
    
    /**
    * 	Return yes or no in current language
    * 	@param	yesno			Value to test (1, 'yes', 'true' or 0, 'no', 'false')
    * 	@param	case			1=Yes/No, 0=yes/no
    * 	@param	color			0=texte only, 1=Text is formated with a color font style ('ok' or 'error'), 2=Text is formated with 'ok' color.
    */
   public static function yn($yesno, $case = 1, $color = 0) {
       $result = 'unknown';
       if ($yesno == 1 || strtolower($yesno) == 'yes' || strtolower($yesno) == 'true') {  // A mettre avant test sur no a cause du == 0
           $result = ($case ? Translate::trans("Yes") : Translate::trans("yes"));
           $classname = 'ok';
       } elseif ($yesno == 0 || strtolower($yesno) == 'no' || strtolower($yesno) == 'false') {
           $result = ($case ? Translate::trans("No") : Translate::trans("no"));
           if ($color == 2)
               $classname = 'ok';
           else
               $classname = 'error';
       }
       if ($color)
           return '<font class="' . $classname . '">' . $result . '</font>';
       return $result;
   }
   
   /**
     * 	Return true if email syntax is ok.
     * 	@param	    address     email (Ex: "toto@titi.com", "John Do <johndo@titi.com>")
     * 	@return     boolean     true if email syntax is OK, false if KO or empty string
     */
    public static function isValidEmail($address) {
        if (preg_match("/.*<(.+)>/i", $address, $regs)) {
            $address = $regs[1];
        }
        // 2 letters domains extensions are for countries
        // 3 letters domains extensions: biz|com|edu|gov|int|mil|net|org|pro|...
        if (preg_match("/^[^@\s\t]+@([a-zA-Z0-9\-]+\.)+([a-zA-Z0-9\-]{2,3}|asso|aero|coop|info|name)\$/i", $address)) {
            return true;
        } else {
            return false;
        }
    }
    
    /**
    *  Return true if phone number syntax is ok.
    *  @param      address     phone (Ex: "0601010101")
    *  @return     boolean     true if phone syntax is OK, false if KO or empty string
    */
   public static function isValidPhone($address) {
       return true;
   }

   /**
    * Renderiza la accion de un formulario
    * @param int $action
    */
   public static function getFormActionClass($action){
       print '<input type="hidden" name="action" value="'.$action.'">';
   }
}

?>