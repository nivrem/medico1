<?php

/*
 * SIEMPRE Sistemas (tm)
 * Copyright (C) 2012 MEGA BYTE TECNOLOGY, C.A. J-31726844-0 <info@siempresistemas.com>
 */

/**
 *  \file       htdocs/core/class/translate.class.php
 *  \ingroup    core
 *  \brief      File for Tanslate class
 *  \version    $rowid: translate.class.php,v 1.49 2011/08/01 22:03:50 eldy Exp $
 */

require_once(SIEMP_DOCUMENT_ROOT."/core/class/string.class.php");

/**
 *      \class      Translate
 * 		\brief      Class to manage translations
 */
class Translate {

    public static $dir;      // Directories that contains /langs subdirectory
    public static $defaultlang;    // Langue courante en vigueur de l'utilisateur
    public static $direction = 'ltr';   // Left to right or Right to left
    public static $tab_translate = array();  // Tableau des traductions
    public static $tab_loaded = array();  // Array to store result after loading each language file
    public static $cache_labels = array();  // Cache for labels
    public static $charset_inputfile = array(); // To store charset encoding used for language
    public static $charset_output = 'UTF-8'; // Codage used by "trans" method outputs
    
    public static $origlang = ''; // Codage used by "trans" method outputs

    /**
     *  Constructor
     *  @param      dir             Force directory that contains /langs subdirectory (value is sometine '..' like into install/* pages
     *                              or support/* pages).
     *  @param      conf			Object with SIEMPRE configuration
     */

    public static function init($conf, $dir = "") {
        if (!empty($conf->file->character_set_client))
            self::$charset_output = $conf->file->character_set_client; // If charset output is forced
        self::$dir = $conf->file->siemp_document_root;
    }

    /**
     *  Set accessor for this->defaultlang
     *  @param      srclang     	Language to use
     */
    public static function setDefaultLang($srclang = 'es_ES') {
        global $conf;

        //Syslog::log("Translate::setDefaultLang srclang=".$srclang,LOG_DEBUG);
        // If a module ask to force a priority on langs directories (to use its own lang files)
        if (!empty($conf->global->MAIN_FORCELANGDIR)) {
            $more = array();
            foreach ($conf->file->siemp_document_root as $dir) {
                $newdir = $dir . $conf->global->MAIN_FORCELANGDIR;    // For example $conf->global->MAIN_FORCELANGDIR is '/mymodule' meaning we search files into '/mymodule/langs/xx_XX'
                if (!in_array($newdir, self::$dir))
                    $more[] = $newdir;       // We add the forced dir into the array $more. Just after, we add entries into $more to list of lang dir $this->dir.
            }
            self::$dir = array_merge($more, self::$dir);    // Forced dir ($more) are before standard dirs ($this->dir)
        }

        self::$origlang = $srclang;

        if (empty($srclang) || $srclang == 'auto') {
            $langpref = $_SERVER['HTTP_ACCEPT_LANGUAGE'];
            $langpref = preg_replace("/;([^,]*)/i", "", $langpref);
            $langpref = str_replace("-", "_", $langpref);
            $langlist = preg_split("/[;,]/", $langpref);
            $codetouse = $langlist[0];
        }
        else
            $codetouse = $srclang;

        // We redefine $srclang
        $langpart = explode("_", $codetouse);
        //print "Short before _ : ".$langpart[0].'/ Short after _ : '.$langpart[1].'<br>';
        if (isset($langpart[1])) { // If it's for a codetouse that is a long code xx_YY
            // Array force long code from first part, even if long code is defined
            $longforshort = array('ar' => 'ar_SA');
            if (isset($longforshort[strtolower($langpart[0])]))
                $srclang = $longforshort[strtolower($langpart[0])];
            else {
                $srclang = strtolower($langpart[0]) . "_" . strtoupper($langpart[1]);
                $longforlong = array('no_nb' => 'nb_NO');
                if (isset($longforlong[strtolower($srclang)]))
                    $srclang = $longforlong[strtolower($srclang)];
            }
        }
        else {      // If it's for a codetouse that is a short code xx
            // Array to convert short lang code into long code.
            $longforshort = array('ar' => 'ar_SA', 'el' => 'el_GR', 'ca' => 'ca_ES', 'en' => 'en_US', 'nb' => 'nb_NO', 'no' => 'nb_NO');
            if (isset($longforshort[strtolower($langpart[0])]))
                $srclang = $longforshort[strtolower($langpart[0])];
            else
                $srclang = strtolower($langpart[0]) . "_" . strtoupper($langpart[0]);
        }

        self::$defaultlang = $srclang;
        //print self::$defaultlang;
    }

    /**
     *  Return active language code for current user
     * 	It's an accessor for this->defaultlang
     *  @param     mode        0=Long language code, 1=Short language code
     *  @return    string      Language code used (en_US, en_AU, fr_FR, ...)
     */
    public static function getDefaultLang($mode = 0) {
        if (empty($mode))
            return self::$defaultlang;
        else
            return substr(self::$defaultlang, 0, 2);
    }

    /**
     *  Load translation key-value for a particular file, into a memory array.
     *  If data for file already loaded, do nothing.
     * 	All data in translation array are stored in UTF-8 format.
     *  tab_loaded is completed with $domain key.
     *  Value for hash are: 1:Loaded from disk, 2:Not found, 3:Loaded from cache
     *  @param      domain      		File name to load (.lang file). Use file@module if file is in a module directory.
     *  @param      alt         		0 (try xx_ZZ then 1), 1 (try xx_XX then 2), 2 (try en_US or fr_FR or es_ES)
     * 	@param		stopafterdirection	Stop when the DIRECTION tag is found (optimize)
     * 	@param		forcelangdir		To force a lang directory
     * 	@return		int					<0 if KO, 0 if already loaded, >0 if OK
     */
    public static function Load($domain, $alt = 0, $stopafterdirection = 0, $forcelangdir = '') {
        global $conf;

        // Check parameters
        if (empty($domain)) {
            CommonObject::printError('', "Translate::Load ErrorWrongParameters");
            exit;
        }

        //Syslog::log("Translate::Load Start domain=".$domain." alt=".$alt." forcelangdir=".$forcelangdir." self::$defaultlang=".self::$defaultlang);

        $newdomain = $domain;
        $modulename = '';

        // Search if module directory name is different of lang file name
        if (preg_match('/^([^@]+)?@([^@]+)$/i', $domain, $regs)) {
            $newdomain = (!empty($regs[1]) ? $regs[1] : $regs[2]);
            $modulename = (!empty($regs[1]) ? $regs[2] : '');
        }

        // Check cache
        if (!empty(self::$tab_loaded[$newdomain])) { // File already loaded for this domain
            //Syslog::log("Translate::Load already loaded for newdomain=".$newdomain);
            return 0;
        }

        $fileread = 0;
        $langofdir = (empty($forcelangdir) ? self::$defaultlang : $forcelangdir);

        // Redefine alt
        $langarray = explode('_', $langofdir);
        if ($alt < 1 && strtolower($langarray[0]) == strtolower($langarray[1]))
            $alt = 1;
        if ($alt < 2 && (strtolower($langofdir) == 'en_us' || strtolower($langofdir) == 'fr_fr' || strtolower($langofdir) == 'es_es'))
            $alt = 2;

        foreach (self::$dir as $searchdir) {
            // If $domain is "file@module" instead of "file" then we look for module lang file
            // in htdocs/custom/modules/mymodule/langs/code_CODE/file.lang
            // and in htdocs/mymodule/langs/code_CODE/file.lang for backward compatibility
            // instead of file htdocs/langs/code_CODE/filelang
            if (preg_match('/@/', $domain))
                $searchdir = $searchdir . "/" . (!empty($modulename) ? $modulename : $newdomain) . "/langs";
            else
                $searchdir = $searchdir . "/langs";

            // Directory of translation files
            $scandir = $searchdir . "/" . $langofdir;
            $file_lang = $scandir . "/" . $newdomain . ".lang";
            $file_lang_osencoded = String::osEncode($file_lang);
            $filelangexists = is_file($file_lang_osencoded);

            //Syslog::log('Translate::Load Try to read for alt='.$alt.' langofdir='.$langofdir.' file_lang='.$file_lang." => ".$filelangexists);

            if ($filelangexists) {
                $found = false;

                // Enable cache of lang file in memory (not by default)
                $usecachekey = '';
                // Using a memcached server
                if (!empty($conf->memcached->enabled) && !empty($conf->global->MEMCACHED_SERVER)) {
                    $usecachekey = $newdomain . '_' . $langofdir;
                }
                // Using cache with shmop. Speed gain: 40ms - Memory overusage: 200ko (Size of session cache file)
                else if (isset($conf->global->MAIN_OPTIMIZE_SPEED) && ($conf->global->MAIN_OPTIMIZE_SPEED & 0x02)) {
                    $usecachekey = $newdomain;
                }

                if ($usecachekey) {
                    require_once(SIEMP_DOCUMENT_ROOT . "/lib/memory.lib.php");
                    $tmparray = siemp_getcache($usecachekey);
                    if (is_array($tmparray) && sizeof($tmparray)) {
                        self::$tab_translate = array_merge($tmparray, self::$tab_translate); // Already found values tab_translate overwrites duplicates
                        //print $newdomain."\n";
                        //var_dump(self::$tab_translate);
                        if ($alt == 2)
                            $fileread = 1;
                        $found = true;      // Found in SIEMPRE PHP cache
                    }
                }

                if (!$found) {
                    if ($fp = @fopen($file_lang, "rt")) {
                        if ($usecachekey)
                            $tabtranslatedomain = array(); // To save lang content in cache

                        while ($line = fgets($fp, 4096)) { // Ex: Need 225ms for all fgets on all lang file for Third party page. Same speed than file_get_contents
                            if ($line[0] != "\n" && $line[0] != " " && $line[0] != "#") {
                                $tab = explode('=', $line, 2);
                                $key = trim($tab[0]);
                                //print "Domain=$domain, found a string for $tab[0] with value $tab[1]<br>";
                                if ((!empty($conf->global->MAIN_USE_CUSTOM_TRANSLATION) || empty(self::$tab_translate[$key])) && isset($tab[1])) {    // If data was already found, we must not enter here, even if MAIN_FORCELANGDIR is set (MAIN_FORCELANGDIR is to replace lang dir, not to overwrite)
                                    $value = trim(preg_replace('/\\n/', "\n", $tab[1]));

                                    if ($key == 'CHARSET') {  // This is to declare in which charset files are encoded
                                        self::$charset_inputfile[$newdomain] = strtoupper($value);
                                        //print 'File '.$file_lang.' is declared to have format '.self::$charset_inputfile[$newdomain].'<br>';
                                    } elseif ($key == 'DIRECTION') { // This is to declare direction of language
                                        if ($alt < 2 || empty(self::$tab_translate[$key])) { // We load direction only for primary files or if not yer load
                                            self::$tab_translate[$key] = $value;

                                            if ($stopafterdirection)
                                                break; // We do not save tab if we stop after DIRECTION
                                            else if ($usecachekey)
                                                $tabtranslatedomain[$key] = $value;
                                        }
                                    }
                                    else {
                                        // On stocke toujours dans le tableau Tab en UTF-8
                                        if (empty(self::$charset_inputfile[$newdomain]) || self::$charset_inputfile[$newdomain] == 'ISO-8859-1')
                                            $value = utf8_encode($value);

                                        //print 'XX'.$key;
                                        self::$tab_translate[$key] = $value;

                                        if ($usecachekey)
                                            $tabtranslatedomain[$key] = $value; // To save lang content in cache
                                    }
                                }
                            }
                        }
                        fclose($fp);
                        $fileread = 1;

                        // To save lang content for usecachekey into cache
                        if ($usecachekey && sizeof($tabtranslatedomain)) {
                            require_once(SIEMP_DOCUMENT_ROOT . "/lib/memory.lib.php");
                            $size = siemp_setcache($usecachekey, $tabtranslatedomain);
                        }

                        if (empty($conf->global->MAIN_FORCELANGDIR) && empty($conf->global->MAIN_USE_CUSTOM_TRANSLATION))
                            break;  // Break loop on each root dir. If a module has forced dir, we do not stop loop.
                    }
                }
            }
        }

        // Now we complete with next file
        if ($alt == 0) {
            // This function MUST NOT contains call to syslog
            //Syslog::log("Translate::Load loading alternate translation file (to complete ".self::$defaultlang."/".$newdomain.".lang file)", LOG_DEBUG);
            $langofdir = strtolower($langarray[0]) . '_' . strtoupper($langarray[0]);
            self::load($domain, $alt + 1, $stopafterdirection, $langofdir);
        }

        // Now we complete with reference en_US/fr_FR/es_ES file
        if ($alt == 1) {
            // This function MUST NOT contains call to syslog
            //Syslog::log("Translate::Load loading alternate translation file (to complete ".self::$defaultlang."/".$newdomain.".lang file)", LOG_DEBUG);
            $langofdir = 'en_US';
            if (preg_match('/^fr/i', $langarray[0]))
                $langofdir = 'fr_FR';
            if (preg_match('/^es/i', $langarray[0]))
                $langofdir = 'es_ES';
            self::load($domain, $alt + 1, $stopafterdirection, $langofdir);
        }

        if ($alt == 2) {
            if ($fileread)
                self::$tab_loaded[$newdomain] = 1; // Set domain file as loaded

            if (empty(self::$tab_loaded[$newdomain]))
                self::$tab_loaded[$newdomain] = 2;           // Marque ce fichier comme non trouve
        }

        // Check to be sure that SeparatorDecimal differs from SeparatorThousand
        if (!empty(self::$tab_translate["SeparatorDecimal"]) && !empty(self::$tab_translate["SeparatorThousand"])
                && self::$tab_translate["SeparatorDecimal"] == self::$tab_translate["SeparatorThousand"])
            self::$tab_translate["SeparatorThousand"] = '';

        return 1;
    }

    /**
     * Return translated value of key. Search in lang file, then into database.
     * If not found, return key.
     * WARNING: To avoid infinite loop (getLabelFromKey->transnoentities->getTradFromKey), getLabelFromKey must
     * not be called with same value than input.
     *
     * @param  key
     * @return string
     */
    public static function getTradFromKey($key) {
        global $db;
        $newstr = $key;
        if (preg_match('/^CurrencySing([A-Z][A-Z][A-Z])$/i', $key, $reg)) {
            $newstr = self::getLabelFromKey($db, $reg[1], 'c_currencies', 'code_iso', 'labelsing');
        } else if (preg_match('/^Currency([A-Z][A-Z][A-Z])$/i', $key, $reg)) {
            $newstr = self::getLabelFromKey($db, $reg[1], 'c_currencies', 'code_iso', 'label');
        } else if (preg_match('/^SendingMethod([0-9A-Z]+)$/i', $key, $reg)) {
            $newstr = self::getLabelFromKey($db, $reg[1], 'c_shipment_mode', 'code', 'libelle');
        } else if (preg_match('/^PaymentTypeShort([0-9A-Z]+)$/i', $key, $reg)) {
            $newstr = self::getLabelFromKey($db, $reg[1], 'c_paiement', 'code', 'libelle');
        } else if (preg_match('/^Civility([0-9A-Z]+)$/i', $key, $reg)) {
            // TODO da error revisar
            $newstr = self::getLabelFromKey($db, $reg[1], 'c_civilite', 'code', 'civilite');
        } else if (preg_match('/^OrderSource([0-9A-Z]+)$/i', $key, $reg)) {
            // TODO Add a table for OrderSourceX
            $newstr=self::getLabelFromKey($db,$reg[1],'c_ordersource','code','label');
        }
        return $newstr;
    }

    /**
     *  Return text translated of text received as parameter (and encode it into HTML)
     *              Si il n'y a pas de correspondance pour ce texte, on cherche dans fichier alternatif
     *              et si toujours pas trouve, il est retourne tel quel
     *              Les parametres de cette methode peuvent contenir de balises HTML.
     *  @param      key         cle de chaine a traduire
     *  @param      param1      chaine de param1
     *  @param      param2      chaine de param2
     *  @param      param3      chaine de param3
     *  @param      param4      chaine de param4
     * 	@param		maxsize		taille max
     *  @return     string      Chaine traduite et code en HTML
     */
    public static function trans($key, $param1 = '', $param2 = '', $param3 = '', $param4 = '', $maxsize = 0) {
        if (!empty(self::$tab_translate[$key])) { // Translation is available
            $str = self::$tab_translate[$key];

            if (!preg_match('/^Format/', $key))
                $str = sprintf($str, $param1, $param2, $param3, $param4); // Replace %s and %d except for FormatXXX strings.
            if ($maxsize)
                $str = String::trunc($str, $maxsize);

            // We replace some HTML tags by __xx__ to avoid having them encoded by htmlentities
            $str = str_replace(array('<', '>', '"',), array('__lt__', '__gt__', '__quot__'), $str);

            $str = self::convToOutputCharset($str); // Convert string to self::$charset_output
            // Crypt string into HTML
            // $str est une chaine stockee en memoire au format self::$charset_output
            $str = htmlentities($str, ENT_QUOTES, self::$charset_output);

            // Restore HTML tags
            $str = str_replace(array('__lt__', '__gt__', '__quot__'), array('<', '>', '"',), $str);
            return $str;
        }
        else {        // Translation is not available
            $str = self::getTradFromKey($key);
            return self::convToOutputCharset($str);
        }
    }

    /**
     *  Return translated value of a text string
     *               Si il n'y a pas de correspondance pour ce texte, on cherche dans fichier alternatif
     *               et si toujours pas trouve, il est retourne tel quel.
     *               Parameters of this method must not contains any HTML tags.
     *  @param       key         key of string to translate
     *  @param       param1      chaine de param1
     *  @param       param2      chaine de param2
     *  @param       param3      chaine de param3
     *  @param       param4      chaine de param4
     *  @return      string      chaine traduite
     */
    public static function transnoentities($key, $param1 = '', $param2 = '', $param3 = '', $param4 = '') {
        if (!empty(self::$tab_translate[$key])) {
            // Si la traduction est disponible
            $newstr = sprintf(self::$tab_translate[$key], $param1, $param2, $param3, $param4);
        } else {
            $newstr = self::getTradFromKey($key);
        }
        return self::convToOutputCharset($newstr);
    }

    /**
     *  Return translated value of a text string
     *               Si il n'y a pas de correspondance pour ce texte, on cherche dans fichier alternatif
     *               et si toujours pas trouve, il est retourne tel quel.
     *               No convert to encoding charset of lang object is done.
     *               Parameters of this method must not contains any HTML tags.
     *  @param       key         key of string to translate
     *  @param       param1      chaine de param1
     *  @param       param2      chaine de param1
     *  @param       param3      chaine de param1
     *  @param       param4      chaine de param1
     *  @return      string      chaine traduite
     */
    public static function transnoentitiesnoconv($key, $param1 = '', $param2 = '', $param3 = '', $param4 = '') {
        if (!empty(self::$tab_translate[$key])) {
            // Si la traduction est disponible
            $newstr = sprintf(self::$tab_translate[$key], $param1, $param2, $param3, $param4);
        } else {
            $newstr = self::getTradFromKey($key);
        }
        return $newstr;
    }

    /**
     *  Return translation of a key depending on country
     *  @param       str            string root to translate
     *  @param       countrycode    country code (FR, ...)
     *  @return      string         translated string
     */
    public static function transcountry($str, $countrycode) {
        if (self::$tab_translate["$str$countrycode"])
            return self::trans("$str$countrycode");
        else
            return self::trans($str);
    }

    /**
     *  Retourne la version traduite du texte passe en parametre complete du code pays
     *  @param       str            string root to translate
     *  @param       countrycode    country code (FR, ...)
     *  @return      string         translated string
     */
    public static function transcountrynoentities($str, $countrycode) {
        if (self::$tab_translate["$str$countrycode"])
            return self::transnoentities("$str$countrycode");
        else
            return self::transnoentities($str);
    }

    /**
     *  Convert a string into output charset (this->charset_output that should be defined to conf->file->character_set_client)
     *  @param      str            	String to convert
     *  @param		pagecodefrom	Page code of src string
     *  @return     string         	Converted string
     */
    public static function convToOutputCharset($str, $pagecodefrom = 'UTF-8') {
        if ($pagecodefrom == 'ISO-8859-1' && self::$charset_output == 'UTF-8')
            $str = utf8_encode($str);
        if ($pagecodefrom == 'UTF-8' && self::$charset_output == 'ISO-8859-1')
            $str = utf8_decode(str_replace('€', chr(128), $str));
        return $str;
    }

    /**
     *  Return list of all available languages
     * 	@param		langdir		Directory to scan
     *  @param      maxlength   Max length for each value in combo box (will be truncated)
     *  @param		usecode		Show code instead of country name for language variant
     *  @return     array     	List of languages
     */
    public static function get_available_languages($langdir = SIEMP_DOCUMENT_ROOT, $maxlength = 0, $usecode = 0) {
        global $conf;

        // We scan directory langs to detect available languages
        $handle = opendir($langdir . "/langs");
        $langs_available = array();
        while ($dir = trim(readdir($handle))) {
            if (preg_match('/^[a-z]+_[A-Z]+/i', $dir)) {
                self::load("languages");

                if ($usecode || !empty($conf->global->MAIN_SHOW_LANGUAGE_CODE)) {
                    $langs_available[$dir] = $dir . ': ' . String::trunc(self::trans('Language_' . $dir), $maxlength);
                } else {
                    $langs_available[$dir] = self::trans('Language_' . $dir);
                }
            }
        }
        return $langs_available;
    }

    /**
     *  Return if a filename $filename exists for current language (or alternate language)
     *  @param      filename        Language filename to search
     *  @param      searchalt       Search also alernate language file
     *  @return     boolean         true if exists and readable
     */
    public static function file_exists($filename, $searchalt = 0) {
        // Test si fichier dans repertoire de la langue
        foreach (self::$dir as $searchdir) {
            if (is_readable(String::osEncode($searchdir . "/langs/" . self::$defaultlang . "/" . $filename)))
                return true;

            if ($searchalt) {
                // Test si fichier dans repertoire de la langue alternative
                if (self::$defaultlang != "en_US")
                    $filenamealt = $searchdir . "/langs/en_US/" . $filename;
                else
                    $filenamealt = $searchdir . "/langs/fr_FR/" . $filename;
                if (is_readable(String::osEncode($filenamealt)))
                    return true;
            }
        }

        return false;
    }

    /**
     *      Return full text translated to language label for a key. Store key-label in a cache.
     *      This function need module "numberwords" to be installed. If not it will return
     *                  same number (this module is not provided by default as it use non GPL source code).
     * 		@param		number		Number to encode in full text
     * 		@param		isamount	1=It's an amount, 0=it's just a number
     *      @return     string		Label translated in UTF8 (but without entities)
     * 								10 if setDefaultLang was en_US => ten
     * 								123 if setDefaultLang was fr_FR => cent vingt trois
     */
    public static function getLabelFromNumber($number, $isamount = 0) {
        global $conf;

        /*
          $outlang=$this->defaultlang;	// Output language we want
          $outlangarray=explode('_',$outlang,2);
          // If lang is xx_XX, then we use xx
          if (strtolower($outlangarray[0]) == strtolower($outlangarray[1])) $outlang=$outlangarray[0];
         */

        $newnumber = $number;
        foreach ($conf->file->siemp_document_root as $dirroot) {
            $dir = $dirroot . "/includes/modules/substitutions";
            $fonc = 'numberwords';
            if (file_exists($dir . '/functions_' . $fonc . '.lib.php')) {
                include_once($dir . '/functions_' . $fonc . '.lib.php');
                $newnumber = numberwords_getLabelFromNumber(self, $number, $isamount);
                break;
            }
        }

        return $newnumber;
    }

    /**
     *      Return a label for a key. Store key-label into cache variable $this->cache_labels to save SQL requests to get labels.
     * 		@param		db			Database handler
     * 		@param		key			Key to get label (key in language file)
     * 		@param		tablename	Table name without prefix
     * 		@param		fieldkey	Field for key
     * 		@param		fieldlabel	Field for label
     *      @return     string		Label in UTF8 (but without entities)
     * 		@remarks	This function can be used to get label in database but more often to get code from key id.
     */
    public static function getLabelFromKey($db, $key, $tablename, $fieldkey, $fieldlabel) {
        global $conf;
        // If key empty
        if ($key == '')
            return '';

        // Check in language array
        if (self::transnoentities($key) != $key) {
            return self::transnoentities($key);    // Found in language array
        }

        // Check in cache
        if (isset(self::$cache_labels[$tablename][$key])) { // Can be defined to 0 or ''
            return self::$cache_labels[$tablename][$key];   // Found in cache
        }

        $sql = "SELECT " . $fieldlabel . " as label";
        $sql.= " FROM " . MAIN_DB_PREFIX . $tablename;
        $sql.= " WHERE " . $fieldkey . " = '" . $key . "'";
        $sql.= " AND entity = ".$conf->entity;
        Syslog::log('Translate::getLabelFromKey sql=' . $sql, LOG_DEBUG);
        $resql = $db->query($sql);
        if ($resql) {
            $obj = $db->fetch_object($resql);
            if ($obj)
                self::$cache_labels[$tablename][$key] = $obj->label;
            else
                self::$cache_labels[$tablename][$key] = $key;

            $db->free($resql);
            return self::$cache_labels[$tablename][$key];
        }
        else {
            self::$error = $db->lasterror();
            Syslog::log("Translate::getLabelFromKey error=" . self::$error, LOG_ERR);
            return -1;
        }
    }

}
?>
