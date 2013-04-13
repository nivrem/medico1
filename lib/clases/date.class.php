<?php
/*
 * SIEMPRE Sistemas (tm)
 * Copyright (C) 2012 MEGA BYTE TECNOLOGY, C.A. J-31726844-0 <info@siempresistemas.com>
 */

class Date {
    
    /**
    * 	Return date for now. We should always use this function without parameters (that means GMT time).
    * 	@param			mode		'gmt' => we return GMT timestamp,
    * 								'tzserver' => we add the PHP server timezone
    *  							'tzref' => we add the company timezone
    * 								'tzuser' => we add the user timezone
    * 	@return         date		Timestamp
    */
    public static function now($mode = 'gmt') {
       // Note that gmmktime and mktime return same value (GMT) whithout parameters
       if ($mode == 'gmt')
           $ret = gmmktime(); // Time for now at greenwich.
       else if ($mode == 'tzserver') {   // Time for now with PHP server timezone added
           $tzsecond = -self::mktime(0, 0, 0, 1, 1, 1970);
           $ret = gmmktime() + $tzsecond;
       } else if ($mode == 'tzref') {    // Time for now where parent company timezone is added
           // TODO Should add the company timezone
           $ret = gmmktime();
       } else if ($mode == 'tzuser') {    // Time for now where user timezone is added
           //print 'eeee'.time().'-'.mktime().'-'.gmmktime();
           $tzhour = isset($_SESSION['siemp_tz']) ? $_SESSION['siemp_tz'] : 0;
           $ret = gmmktime() + ($tzhour * 60 * 60);
       }
       return $ret;
   }
   
   /**
    * 	Return a timestamp date built from detailed informations (by default a local PHP server timestamp)
    * 	Replace function mktime not available under Windows if year < 1970
    * 	PHP mktime is restricted to the years 1901-2038 on Unix and 1970-2038 on Windows
    * 	@param		hour			Hour	(can be -1 for undefined)
    * 	@param		minute			Minute	(can be -1 for undefined)
    * 	@param		second			Second	(can be -1 for undefined)
    * 	@param		month			Month
    * 	@param		day				Day
    * 	@param		year			Year
    * 	@param		gm				1=Input informations are GMT values, otherwise local to server TZ
    * 	@param		check			0=No check on parameters (Can use day 32, etc...)
    *  @param		isdst			Dayling saving time
    * 	@return		timestamp		Date as a timestamp, '' if error
    * 	@see 		Date::printDate, String::stringToTime
    */
   public static function mktime($hour, $minute, $second, $month, $day, $year, $gm = false, $check = 1, $isdst = true) {
       //print "- ".$hour.",".$minute.",".$second.",".$month.",".$day.",".$year.",".$_SERVER["WINDIR"]." -";
       // Clean parameters
       if ($hour == -1)
           $hour = 0;
       if ($minute == -1)
           $minute = 0;
       if ($second == -1)
           $second = 0;

       // Check parameters
       if ($check) {
           if (!$month || !$day)
               return '';
           if ($day > 31)
               return '';
           if ($month > 12)
               return '';
           if ($hour < 0 || $hour > 24)
               return '';
           if ($minute < 0 || $minute > 60)
               return '';
           if ($second < 0 || $second > 60)
               return '';
       }

       $usealternatemethod = false;
       if ($year <= 1970)
           $usealternatemethod = true;  // <= 1970
       if ($year >= 2038)
           $usealternatemethod = true;  // >= 2038

       if ($usealternatemethod || $gm) { // Si time gm, seule adodb peut convertir
           /*
             // On peut utiliser strtotime pour obtenir la traduction.
             // strtotime is ok for range: Friday 13 December 1901 20:45:54 GMT to Tuesday 19 January 2038 03:14:07 GMT.
             $montharray=array(1=>'january',2=>'february',3=>'march',4=>'april',5=>'may',6=>'june',
             7=>'july',8=>'august',9=>'september',10=>'october',11=>'november',12=>'december');
             $string=$day." ".$montharray[0+$month]." ".$year." ".$hour.":".$minute.":".$second." GMT";
             $date=strtotime($string);
             print "- ".$string." ".$date." -";
            */
           $date = adodb_mktime($hour, $minute, $second, $month, $day, $year, $isdst, $gm);
       } else {
           $date = mktime($hour, $minute, $second, $month, $day, $year);
       }
       return $date;
   }
   
   /**
    * 	Output date in a string format according to outputlangs (or langs if not defined).
    * 	Return charset is always UTF-8, except if encodetoouput is defined. In this cas charset is output charset.
    * 	@param	    time        	GM Timestamps date (or deprecated strings 'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM:SS')
    * 	@param	    format      	Output date format
    * 								"%d %b %Y",
    * 								"%d/%m/%Y %H:%M",
    * 								"%d/%m/%Y %H:%M:%S",
    * 								"day", "daytext", "dayhour", "dayhourldap", "dayhourtext"
    * 	@param		tzoutput		true=output or 'gmt' => string is for Greenwich location
    * 								false or 'tzserver' => output string is for local PHP server TZ usage
    * 								'tzuser' => output string is for local browser TZ usage
    * 	@param		outputlangs		Object lang that contains language for text translation.
    *  @param      encodetooutput  false=no convert into output pagecode
    * 	@return     string      	Formated date or '' if time is null
    *  @see        self::mktime, String::stringToTime, Date::getDateTms
    */
   public static function printDate($time, $format = '', $tzoutput = 'tzserver', $outputlangs = '', $encodetooutput = false) {
       global $conf, $langs;

       $to_gmt = false;
       $offsettz = $offsetdst = 0;
       if ($tzoutput) {
           $to_gmt = true; // For backward compatibility
           if (is_string($tzoutput)) {
               if ($tzoutput == 'tzserver') {
                   $to_gmt = false;
                   $offsettz = $offsetdst = 0;
               }
               if ($tzoutput == 'tzuser') {
                   $to_gmt = true;
                   $offsettz = (empty($_SESSION['siemp_tz']) ? 0 : $_SESSION['siemp_tz']) * 60 * 60;
                   $offsetdst = (empty($_SESSION['siemp_dst']) ? 0 : $_SESSION['siemp_dst']) * 60 * 60;
               }
               if ($tzoutput == 'tzcompany') {
                   $to_gmt = false;
                   $offsettz = $offsetdst = 0; // TODO Define this and use it later
               }
           }
       }

       if (!is_object($outputlangs))
           $outputlangs = $langs;

       // Si format non defini, on prend $conf->format_date_text_short sinon %Y-%m-%d %H:%M:%S
       if (!$format)
           $format = (isset($conf->format_date_text_short) ? $conf->format_date_text_short : '%Y-%m-%d %H:%M:%S');

       // Change predefined format into computer format. If found translation in lang file we use it, otherwise we use default.
       if ($format == 'day')
           $format = (Translate::trans("FormatDateShort") != "FormatDateShort" ? Translate::trans("FormatDateShort") : $conf->format_date_short);
       if ($format == 'hour')
           $format = (Translate::trans("FormatHourShort") != "FormatHourShort" ? Translate::trans("FormatHourShort") : $conf->format_hour_short);
       if ($format == 'hourduration')
           $format = (Translate::trans("FormatHourShortDuration") != "FormatHourShortDuration" ? Translate::trans("FormatHourShortDuration") : $conf->format_hour_short_duration);
       if ($format == 'daytext')
           $format = (Translate::trans("FormatDateText") != "FormatDateText" ? Translate::trans("FormatDateText") : $conf->format_date_text);
       if ($format == 'daytextshort')
           $format = (Translate::trans("FormatDateTextShort") != "FormatDateTextShort" ? Translate::trans("FormatDateTextShort") : $conf->format_date_text_short);
       if ($format == 'dayhour')
           $format = (Translate::trans("FormatDateHourShort") != "FormatDateHourShort" ? Translate::trans("FormatDateHourShort") : $conf->format_date_hour_short);
       if ($format == 'dayhourtext')
           $format = (Translate::trans("FormatDateHourText") != "FormatDateHourText" ? Translate::trans("FormatDateHourText") : $conf->format_date_hour_text);
       if ($format == 'dayhourtextshort')
           $format = (Translate::trans("FormatDateHourTextShort") != "FormatDateHourTextShort" ? Translate::trans("FormatDateHourTextShort") : $conf->format_date_hour_text_short);

       // Format not sensitive to language
       if ($format == 'dayhourlog')
           $format = '%Y%m%d%H%M%S';
       if ($format == 'dayhourldap')
           $format = '%Y%m%d%H%M%SZ';
       if ($format == 'dayhourxcard')
           $format = '%Y%m%dT%H%M%SZ';
       if ($format == 'dayxcard')
           $format = '%Y%m%d';
       if ($format == 'dayrfc')
           $format = '%Y-%m-%d';             // DATE_RFC3339
       if ($format == 'dayhourrfc')
           $format = '%Y-%m-%dT%H:%M:%SZ';   // DATETIME RFC3339


   // If date undefined or "", we return ""
       if (String::strlen($time) == 0)
           return '';  // $time=0 allowed (it means 01/01/1970 00:00:00)


   //print 'x'.$time;

       if (preg_match('/%b/i', $format)) {  // There is some text to translate
           // We inhibate translation to text made by strftime functions. We will use trans instead later.
           $format = str_replace('%b', '__b__', $format);
           $format = str_replace('%B', '__B__', $format);
       }
       if (preg_match('/%a/i', $format)) {  // There is some text to translate
           // We inhibate translation to text made by strftime functions. We will use trans instead later.
           $format = str_replace('%a', '__a__', $format);
           $format = str_replace('%A', '__A__', $format);
       }

       // Analyze date (deprecated)   Ex: 1970-01-01, 1970-01-01 01:00:00, 19700101010000
       if (preg_match('/^([0-9]+)\-([0-9]+)\-([0-9]+) ?([0-9]+)?:?([0-9]+)?:?([0-9]+)?/i', $time, $reg)
               || preg_match('/^([0-9][0-9][0-9][0-9])([0-9][0-9])([0-9][0-9])([0-9][0-9])([0-9][0-9])([0-9][0-9])$/i', $time, $reg)) {
           // This part of code should not be used.
           Syslog::log("Functions.lib::Date::printDate function call with deprecated value of time in page " . $_SERVER["PHP_SELF"], LOG_WARNING);
           // Date has format 'YYYY-MM-DD' or 'YYYY-MM-DD HH:MM:SS' or 'YYYYMMDDHHMMSS'
           $syear = $reg[1];
           $smonth = $reg[2];
           $sday = $reg[3];
           $shour = $reg[4];
           $smin = $reg[5];
           $ssec = $reg[6];

           $time = self::mktime($shour, $smin, $ssec, $smonth, $sday, $syear, true);
           $ret = adodb_strftime($format, $time + $offsettz + $offsetdst, $to_gmt);
       } else {
           // Date is a timestamps
           if ($time < 100000000000) { // Protection against bad date values
               $ret = adodb_strftime($format, $time + $offsettz + $offsetdst, $to_gmt);
           }
           else
               $ret = 'Bad value ' . $time . ' for date';
       }

       if (preg_match('/__b__/i', $format)) {
           // Here ret is string in PHP setup language (strftime was used). Now we convert to $outputlangs.
           $month = adodb_strftime('%m', $time + $offsettz + $offsetdst);
           if ($encodetooutput) {
               $monthtext = Translate::transnoentities('Month' . $month);
               $monthtextshort = Translate::transnoentities('MonthShort' . $month);
           } else {
               $monthtext = Translate::transnoentitiesnoconv('Month' . $month);
               $monthtextshort = Translate::transnoentitiesnoconv('MonthShort' . $month);
           }
           //print 'monthtext='.$monthtext.' monthtextshort='.$monthtextshort;
           $ret = str_replace('__b__', $monthtextshort, $ret);
           $ret = str_replace('__B__', $monthtext, $ret);
           //print 'x'.Translate::$charset_output.'-'.$ret.'x';
           //return $ret;
       }
       if (preg_match('/__a__/i', $format)) {
           $w = adodb_strftime('%w', $time + $offsettz + $offsetdst);
           $dayweek = Translate::transnoentitiesnoconv('Day' . $w);
           $ret = str_replace('__A__', $dayweek, $ret);
           $ret = str_replace('__a__', String::substr($dayweek, 0, 3), $ret);
       }

       return $ret;
   }
   
   /**
    *  Return an array with timezone values
    *  @return     array   Array with timezone values
    */
   public static function getTzArray() {
       $tzarray = array(-11 => "Pacific/Midway",
           -10 => "Pacific/Fakaofo",
           -9 => "America/Anchorage",
           -8 => "America/Los_Angeles",
           -7 => "America/Dawson_Creek",
           -6 => "America/Chicago",
           -5 => "America/Bogota",
           -4 => "America/Anguilla",
           -3 => "America/Araguaina",
           -2 => "America/Noronha",
           -1 => "Atlantic/Azores",
           0 => "Africa/Abidjan",
           1 => "Europe/Paris",
           2 => "Europe/Helsinki",
           3 => "Europe/Moscow",
           4 => "Asia/Dubai",
           5 => "Asia/Karachi",
           6 => "Indian/Chagos",
           7 => "Asia/Jakarta",
           8 => "Asia/Hong_Kong",
           9 => "Asia/Tokyo",
           10 => "Australia/Sydney",
           11 => "Pacific/Noumea",
           12 => "Pacific/Auckland",
           13 => "Pacific/Enderbury"
       );
       return $tzarray;
   }
   
   /**
    *  Add a delay to a date
    *  @param      time                Date timestamp (or string with format YYYY-MM-DD)
    *  @param      duration_value      Value of delay to add
    *  @param      duration_unit       Unit of added delay (d, m, y)
    *  @return     int                 New timestamp
    */
   public static function timePlusDuree($time, $duration_value, $duration_unit) {
       if ($duration_value == 0)
           return $time;
       if ($duration_value > 0)
           $deltastring = "+" . abs($duration_value);
       if ($duration_value < 0)
           $deltastring = "-" . abs($duration_value);
       if ($duration_unit == 'd') {
           $deltastring.=" day";
       }
       if ($duration_unit == 'm') {
           $deltastring.=" month";
       }
       if ($duration_unit == 'y') {
           $deltastring.=" year";
       }
       return strtotime($deltastring, $time);
   }
   
   /**   Converti les heures et minutes en secondes
    *    @param      iHours      Heures
    *    @param      iMinutes    Minutes
    *    @param      iSeconds    Secondes
    *    @return     iResult	    Temps en secondes
    */
    public static function time2Seconds($iHours = 0, $iMinutes = 0, $iSeconds = 0) {
        $iResult = ($iHours * 3600) + ($iMinutes * 60) + $iSeconds;
        return $iResult;
    }
   
   /** 	  	Return, in clear text, value of a number of seconds in days, hours and minutes
    *    	@param      iSecond		    Number of seconds
    *    	@param      format		    Output format (all: complete display, hour: displays only hours, min: displays only minutes, sec: displays only seconds)
    *      @param      lengthOfDay     Length of day (default 86400 seconds for 1 day, 28800 for 8 hour)
    *      @param      lengthOfWeek    Length of week (default 7)
    *    	@return     sTime		    Formated text of duration
    * 	                                Example: 0 return 00:00, 3600 return 1:00, 86400 return 1d, 90000 return 1 Day 01:00
    */
    public static function secondToTime($iSecond, $format = 'all', $lengthOfDay = 86400, $lengthOfWeek = 7) {
       if (empty($lengthOfDay))
           $lengthOfDay = 86400;         // 1 day = 24 hours
       if (empty($lengthOfWeek))
           $lengthOfWeek = 7;            // 1 week = 7 days

       if ($format == 'all') {
           if ($iSecond === 0)
               return '0'; // This is to avoid having 0 return a 12:00 AM for en_US

           $sTime = '';
           $sDay = 0;
           $sWeek = '';

           if ($iSecond >= $lengthOfDay) {
               for ($i = $iSecond; $i >= $lengthOfDay; $i -= $lengthOfDay) {
                   $sDay++;
                   $iSecond-=$lengthOfDay;
               }
               $dayTranslate = Translate::trans("Day");
               if ($iSecond >= ($lengthOfDay * 2))
                   $dayTranslate = Translate::trans("Days");
           }

           if ($lengthOfWeek < 7) {
               if ($sDay) {
                   if ($sDay >= $lengthOfWeek) {
                       $sWeek = (int) ( ( $sDay - $sDay % $lengthOfWeek ) / $lengthOfWeek );
                       $sDay = $sDay % $lengthOfWeek;
                       $weekTranslate = Translate::trans("DurationWeek");
                       if ($sWeek >= 2)
                           $weekTranslate = Translate::trans("DurationWeeks");
                       $sTime.=$sWeek . ' ' . $weekTranslate . ' ';
                   }
                   if ($sDay > 0) {
                       $dayTranslate = Translate::trans("Day");
                       if ($sDay > 1)
                           $dayTranslate = Translate::trans("Days");
                       $sTime.=$sDay . ' ' . $dayTranslate . ' ';
                   }
               }
           }

           if ($sDay)
               $sTime.=$sDay . ' ' . $dayTranslate . ' ';
           if ($iSecond || empty($sDay)) {
               $sTime.= self::printDate($iSecond, 'hourduration', true);
           }
       } else if ($format == 'hour') {
           $sTime = self::printDate($iSecond, '%H', true);
       } else if ($format == 'min') {
           $sTime = self::printDate($iSecond, '%M', true);
       } else if ($format == 'sec') {
           $sTime = self::printDate($iSecond, '%S', true);
       }
       return trim($sTime);
   }
   
    /** Return previous day
     *  @param      day     Day
     *  @param      month   Month
     *  @param      year    Year
     *  @return     array   Previous year,month,day
     */
     public static function getPrevDay($day, $month, $year) {
        $time = self::mktime(12, 0, 0, $month, $day, $year, 1, 0);
        $time-=24 * 60 * 60;
        $tmparray = self::getDateTms($time, true);
        return array('year' => $tmparray['year'], 'month' => $tmparray['mon'], 'day' => $tmparray['mday']);
    }
   
    /** Return next day
     *  @param      day     Day
     *  @param      month   Month
     *  @param      year    Year
     *  @return     array   Next year,month,day
     */
    public static function getNextDay($day, $month, $year) {
        $time = self::mktime(12, 0, 0, $month, $day, $year, 1, 0);
        $time+=24 * 60 * 60;
        $tmparray = self::getDateTms($time, true);
        return array('year' => $tmparray['year'], 'month' => $tmparray['mon'], 'day' => $tmparray['mday']);
    }
    
    /** 	Return previous month
    * 	@param		month	Month
    * 	@param		year	Year
    * 	@return		array	Previous year,month
    */
    public static function getPrevMonth($month, $year) {
       if ($month == 1) {
           $prev_month = 12;
           $prev_year = $year - 1;
       } else {
           $prev_month = $month - 1;
           $prev_year = $year;
       }
       return array('year' => $prev_year, 'month' => $prev_month);
   }
   
   /** 	Return next month
    * 	@param		month	Month
    * 	@param		year	Year
    * 	@return		array	Next year,month
    */
    public static function getNextMonth($month, $year) {
       if ($month == 12) {
           $next_month = 1;
           $next_year = $year + 1;
       } else {
           $next_month = $month + 1;
           $next_year = $year;
       }
       return array('year' => $next_year, 'month' => $next_month);
   }
   
   /** 	Return previous week
    *  @param      day     Day
    * 	@param		week	Week
    * 	@param		month	Month
    * 	@param		year	Year
    * 	@return		array	Previous year,month,day
    */
    public static function getPrevWeek($day, $week, $month, $year) {
       $tmparray = self::getFirstDay_week($day, $month, $year);

       $time = self::mktime(12, 0, 0, $month, $tmparray['first_day'], $year, 1, 0);
       $time-=24 * 60 * 60 * 7;
       $tmparray = self::getDateTms($time, true);
       return array('year' => $tmparray['year'], 'month' => $tmparray['mon'], 'day' => $tmparray['mday']);
   }
   
   /** 	Return next week
    *  @param      day     Day
    *  @param      week    Week
    *  @param      month   Month
    * 	@param		year	Year
    * 	@return		array	Next year,month,day
    */
    public static function getNextWeek($day, $week, $month, $year) {
       $tmparray = self::getFirstDay_week($day, $month, $year);

       $time = self::mktime(12, 0, 0, $month, $tmparray['first_day'], $year, 1, 0);
       $time+=24 * 60 * 60 * 7;
       $tmparray = self::getDateTms($time, true);

       return array('year' => $tmparray['year'], 'month' => $tmparray['mon'], 'day' => $tmparray['mday']);
   }
   
    /** 	Return GMT time for first day of a month or year
     * 	@param		year		Year
     * 	@param		month		Month
     * 	@param		gm			False = Return date to compare with server TZ, True to compare with GM date.
     *                          Exemple: Date::getFirstDay(1970,1,false) will return -3600 with TZ+1, after a Date::printDate will return 1970-01-01 00:00:00
     *                          Exemple: Date::getFirstDay(1970,1,true) will return 0 whatever is TZ, after a Date::printDate will return 1970-01-01 00:00:00
     *  @return		Timestamp	Date for first day
     */
     public static function getFirstDay($year, $month = 1, $gm = false) {
        return self::mktime(0, 0, 0, $month, 1, $year, $gm);
    }
   
    /** 	Return GMT time for last day of a month or year
     * 	@param		year		Year
     * 	@param		month		Month
     * 	@param		gm			False = Return date to compare with server TZ, True to compare with GM date.
     * 	@return		Timestamp	Date for first day
     */
    public static function getLastDay($year, $month = 12, $gm = false) {
        if ($month == 12) {
            $month = 1;
            $year += 1;
        } else {
            $month += 1;
        }

        // On se deplace au debut du mois suivant, et on retire un jour
        $datelim = self::mktime(23, 59, 59, $month, 1, $year, $gm);
        $datelim -= (3600 * 24);

        return $datelim;
    }
    
    /** 	Return first day of week for a date
     * 	@param		day			Day
     * 	@param		month		Month
     *  @param		year		Year
     * 	@param		gm			False = Return date to compare with server TZ, True to compare with GM date.
     * 	@return		array		year,month, week,first_day,prev_year,prev_month,prev_day
     */
    public static function getFirstDayWeek($day, $month, $year, $gm = false) {
        global $conf;

        $date = self::mktime(0, 0, 0, $month, $day, $year, $gm);

        //Checking conf of start week
        $start_week = (isset($conf->global->MAIN_START_WEEK) ? $conf->global->MAIN_START_WEEK : 1);

        $tmparray = self::getDateTms($date, true);

        //Calculate days to count
        $days = $start_week - $tmparray['wday'];
        if ($days >= 1)
            $days = 7 - $days;
        $days = abs($days);
        $seconds = $days * 24 * 60 * 60;

        //Get first day of week
        $tmpday = date($tmparray[0]) - $seconds;
        $tmpday = date("d", $tmpday);

        //Check first day of week is form this month or not
        if ($tmpday > $day) {
            $prev_month = $month - 1;
            $prev_year = $year;

            if ($prev_month == 0) {
                $prev_month = 12;
                $prev_year = $year - 1;
            }
        } else {
            $prev_month = $month;
            $prev_year = $year;
        }

        //Get first day of next week
        $tmptime = self::mktime(12, 0, 0, $month, $tmpday, $year, 1, 0);
        $tmptime-=24 * 60 * 60 * 7;
        $tmparray = self::getDateTms($tmptime, true);
        $prev_day = $tmparray['mday'];

        //Check first day of week is form this month or not
        if ($prev_day > $tmpday) {
            $prev_month = $month - 1;
            $prev_year = $year;

            if ($prev_month == 0) {
                $prev_month = 12;
                $prev_year = $year - 1;
            }
        }

        $week = date("W", self::mktime(0, 0, 0, $month, $tmpday, $year, $gm));

        return array('year' => $year, 'month' => $month, 'week' => $week, 'first_day' => $tmpday, 'prev_year' => $prev_year, 'prev_month' => $prev_month, 'prev_day' => $prev_day);
    }
    
    
    /**
     * 	Fonction retournant le nombre de jour entre deux dates
     * 	@param	   timestampStart      Timestamp de debut
     * 	@param	   timestampEnd        Timestamp de fin
     * 	@param     lastday             On prend en compte le dernier jour, 0: non, 1:oui
     * 	@return    nbjours             Nombre de jours
     */
    public static function numBetweenDay($timestampStart, $timestampEnd, $lastday = 0) {
        if ($timestampStart < $timestampEnd) {
            if ($lastday == 1) {
                $bit = 0;
            } else {
                $bit = 1;
            }
            $nbjours = round(($timestampEnd - $timestampStart) / (60 * 60 * 24) - $bit);
        }
        return $nbjours;
    }
    
    /**
    * 	Fonction retournant le nombre de jour entre deux dates sans les jours feries (jours ouvres)
    * 	@param	   timestampStart      Timestamp de debut
    * 	@param	   timestampEnd        Timestamp de fin
    * 	@param     inhour              0: sort le nombre de jour , 1: sort le nombre d'heure (72 max)
    * 	@param     lastday             On prend en compte le dernier jour, 0: non, 1:oui
    * 	@return    nbjours             Nombre de jours ou d'heures
    */
    public static function numOpenDay($timestampStart, $timestampEnd, $inhour = 0, $lastday = 0) {
        if ($timestampStart < $timestampEnd) {
            $bit = 0;
            if ($lastday == 1)
                $bit = 1;
            $nbOpenDay = self::numBetweenDay($timestampStart, $timestampEnd, $bit) - num_public_holiday($timestampStart, $timestampEnd);
            $nbOpenDay.= " " . Translate::trans("Days");
            if ($inhour == 1 && $nbOpenDay <= 3)
                $nbOpenDay = $nbOpenDay * 24 . Translate::trans("HourShort");
            return $nbOpenDay;
        }
        else {
            return Translate::trans("Error");
        }
    }
   
    /**
     * 	Return an array with date info
     *  PHP getdate is restricted to the years 1901-2038 on Unix and 1970-2038 on Windows.
     * 	@param		timestamp		Timestamp
     * 	@param		fast			Fast mode
     * 	@return		array			Array of informations
     * 				If no fast mode:
     * 				'seconds' => $secs,
     * 				'minutes' => $min,
     * 				'hours' => $hour,
     * 				'mday' => $day,
     * 				'wday' => $dow,
     * 				'mon' => $month,
     * 				'year' => $year,
     * 				'yday' => floor($secsInYear/$_day_power),
     * 				'weekday' => gmdate('l',$_day_power*(3+$dow)),
     * 				'month' => gmdate('F',mktime(0,0,0,$month,2,1971)),
     * 				If fast mode:
     * 				'seconds' => $secs,
     * 				'minutes' => $min,
     * 				'hours' => $hour,
     * 				'mday' => $day,
     * 				'mon' => $month,
     * 				'year' => $year,
     * 				'yday' => floor($secsInYear/$_day_power),
     * 				'leap' => $leaf,
     * 				'ndays' => $ndays
     */
    public static function getDateTms($timestamp, $fast = false) {
        $usealternatemethod = false;
        if ($timestamp <= 0)
            $usealternatemethod = true;    // <= 1970
        if ($timestamp >= 2145913200)
            $usealternatemethod = true;  // >= 2038

        if ($usealternatemethod) {
            $arrayinfo = adodb_getdate($timestamp, $fast);
        } else {
            $arrayinfo = getdate($timestamp);
        }

        return $arrayinfo;
    }
    
    /**
     * 	Returns formated date
     * 	@param		fmt				Format (Exemple: 'Y-m-d H:i:s')
     * 	@param		timestamp		Date. Example: If timestamp=0 and gm=1, return 01/01/1970 00:00:00
     * 	@param		gm				1 if timestamp was built with gmmktime, 0 if timestamp was build with mktime
     * 	@return		string			Formated date
     *  @deprecated Replaced by Date::printDate
     */
    public static function getDate($fmt, $timestamp, $gm = false) {
        $usealternatemethod = false;
        if ($timestamp <= 0)
            $usealternatemethod = true;
        if ($timestamp >= 2145913200)
            $usealternatemethod = true;

        if ($usealternatemethod || $gm) { // Si time gm, seule adodb peut convertir
            $string = adodb_date($fmt, $timestamp, $gm);
        } else {
            $string = date($fmt, $timestamp);
        }

        return $string;
    }
}

?>
