<?php

//----------------------------------------------------------------
// utility functions related to Skyrim calendar
//----------------------------------------------------------------

function dow2str_skyrim_day($s_dow_number) {
    $sRes = "";    

    $sdx = trim($s_dow_number);
    if (strlen($sdx > 1)) {
        $sdx = ltrim($sdx, '0'); 
    }
    if ($sdx == "1") {
        $sRes = "Sundas";
    } elseif($sdx == "2") {
        $sRes = "Morndas";
    } elseif($sdx == "3") {
        $sRes = "Tirdas";
    } elseif($sdx == "4") {
        $sRes = "Middas";
    } elseif($sdx == "5") {
        $sRes = "Furdas";
    } elseif($sdx == "6") {
        $sRes = "Fredas";
    } elseif($sdx == "7") {
        $sRes = "Loredas";
    } 
    return $sRes;
}


function dow2str_gregorian_day($s_dow_number) {
    $sRes = "";    

    $sdx = trim($s_dow_number);
    if (strlen($sdx > 1)) {
        $sdx = ltrim($sdx, '0'); 
    }
    if ($sdx == "1") {
        $sRes = "Sunday";
    } elseif($sdx == "2") {
        $sRes = "Monday";
    } elseif($sdx == "3") {
        $sRes = "Tuesday";
    } elseif($sdx == "4") {
        $sRes = "Wednesday";
    } elseif($sdx == "5") {
        $sRes = "Thursday";
    } elseif($sdx == "6") {
        $sRes = "Friday";
    } elseif($sdx == "7") {
        $sRes = "Saturday";
    } 
    return $sRes;
}


function month2str_skyrim_month($s_month_number) { 
    $sRes = "";
    $sdx = ltrim(trim($s_month_number),'0');
    if ($sdx == "1") { 
        $sRes = "Morning Star";
    } elseif($sdx == "2") {
        $sRes = "Sun's Dawn";
    } elseif($sdx == "3") {
        $sRes = "First Seed";
    } elseif($sdx == "4") {
        $sRes = "Rain's Hand";
    } elseif($sdx == "5") {
        $sRes = "Second Seed";
    } elseif($sdx == "6") {
        $sRes = "Mid Year";
    } elseif($sdx == "7") {
        $sRes = "Sun's Height";
    } elseif($sdx == "8") {
        $sRes = "Last Seed";
    } elseif($sdx == "9") {
        $sRes = "Hearthfire";
    } elseif($sdx == "10") {
        $sRes = "Frost Fall";
    } elseif($sdx == "11") {
        $sRes = "Sun's Dusk";
    } elseif($sdx == "12") {
        $sRes = "Evening Star";
    } else {
        $sRes = "unknown month";
    } 
    return $sRes;
}


function convert_dow_skyrim2dow_gregorian($s_dow_name_skyrim) {
    $sRes = "";
    $sdx = strtolower(trim($s_dow_name_skyrim));
    /*
    sundas (Sunday) 
    morndas (Monday) 
    tirdas (Tuesday) 
    middas (Wednesday) 
    turdas (Thursday) 
    fredas (Friday) 
    loredas (Saturday)
    */
    if ($sdx == "sundas") {
        $sRes = "Sunday";
    } elseif($sdx == "morndas") {
        $sRes = "Monday";
    } elseif($sdx == "tirdas") {
        $sRes = "Tuesday";
    } elseif($sdx == "middas") {
        $sRes = "Wednesday";
    } elseif($sdx == "turdas") {
        $sRes = "Thursday";
    } elseif($sdx == "fredas") {
        $sRes = "Friday";
    } elseif($sdx == "loredas") {
        $sRes = "Saturday";
    } 
    return $sRes;
}


function convert_dow_gregorian2dow_skyrim($s_dow_name_gregorian = "") {
    $sRes = "";
    $sdx = strtolower(trim($s_dow_name_gregorian));
    if ($sdx == "sunday") {
        $sRes = "Sundas";
    } elseif($sdx == "monday") {
        $sRes = "Morndas";
    } elseif($sdx == "tuesday") {
        $sRes = "Tirdas";
    } elseif($sdx == "wednesday") {
        $sRes = "Middas";
    } elseif($sdx == "thursday") {
        $sRes = "Furdas";
    } elseif($sdx == "friday") {
        $sRes = "Fredas";
    } elseif($sdx == "saturday") {
        $sRes = "Loredas";
    } 
    return $sRes;
}


//----------------------------------------------------------------
// convert_gamets2... functions mimic corresponding SQL functions:
//----------------------------------------------------------------

function convert_gamets2days($gamets) {
    $i_res = 0;
    $f_input = floatval($gamets) * 0.0000001; 
    if ($f_input > 0.0) {
        $i_res = floor($f_input);
    }
    return intval($i_res);
}

function convert_gamets2hours($gamets) {
    $i_res = 0;
    $f_input = floatval($gamets) * 0.0000024; 
    if ($f_input > 0.0) {
        $i_res = floor($f_input);
    }
    return intval($i_res);
}


function convert_gamets2minutes($gamets) {
    $i_res = 0;
    $f_input = floatval($gamets) * 0.000144; 
    if ($f_input > 0.0) {
        $i_res = floor($f_input);
    }
    return intval($i_res);
}


function convert_gamets2seconds($gamets) {
    $i_res = 0;
    $f_input = floatval($gamets) * 0.00864; 
    if ($f_input > 0.0) {
        $i_res = floor($f_input);
    }
    return intval($i_res);
}


function convert_gamets2gregorian_date($gamets) {
    $sRes = "";
    $gregorian_start_timestamp = strtotime('1577-08-17 00:00:00'); 
    $f_gamets = floatval($gamets); 
    if ($f_gamets > 0.0) {
        $f_seconds = floatval($gamets) * 0.00864; 
        $ts_time = $gregorian_start_timestamp + intval($f_seconds);
        $sRes = date('Y-m-d H:i', $ts_time);
    }
    return $sRes;
}


function convert_gamets2skyrim_long_date($gamets) {
    $s_result = "";

    $skyrim_start_timestamp = strtotime('0201-08-17 00:00:00'); 
    $f_gamets = floatval($gamets); 

    if ($f_gamets > 0.0) {
        $f_seconds = floatval($gamets) * 0.00864; 
        $ts_time = $skyrim_start_timestamp + intval($f_seconds);

        $s_month_number = date('m', $ts_time); // m	- Numeric representation of a month, with leading zeros	01 through 12
        $s_longm = month2str_skyrim_month($s_month_number);
        
        $s_dow_number = date('N', $ts_time); //N - 1 (for Monday) through 7 (for Sunday)
        $s_dayname = dow2str_skyrim_day($s_dow_number);

        $s_year = ltrim(date('Y', $ts_time), '0');

        $s_date1 = date( 'g:i A', $ts_time);
        $s_date2 = date( 'j', $ts_time);
        $s_date3 = ', 4E '. $s_year;

        $s_result = $s_dayname . ', ' . $s_date1 . ', ' . $s_date2 . 'th of ' . $s_longm . $s_date3;
    }
    return $s_result;
}


function convert_gamets2skyrim_long_date2($gamets) {
    $s_result = "";

    $skyrim_start_timestamp = strtotime('0201-08-17 00:00:00'); 
    $f_gamets = floatval($gamets); 

    if ($f_gamets > 0.0) {
        $f_seconds = floatval($gamets) * 0.00864; 
        $ts_time = $skyrim_start_timestamp + intval($f_seconds);

        $s_month_number = date('m', $ts_time); // m	- Numeric representation of a month, with leading zeros	01 through 12
        $s_longm = month2str_skyrim_month($s_month_number);
        
        //$s_dow_number = date('N', $ts_time); //N - 1 (for Monday) through 7 (for Sunday)
        //$s_dayname = dow2str_skyrim_day($s_dow_number);

        $s_year = ltrim(date('Y', $ts_time), '0');

        $s_date1 = date( 'd', $ts_time);
        $s_date2 = ', 4E '. $s_year . ', ' . date('H:i', $ts_time);

        //s_date1 := to_char(ts_base + f_hours * INTERVAL '1 hour', 'DD');
        //s_date2 := to_char(ts_base + f_hours * INTERVAL '1 hour', ' 4E FMYYYY, HH24:MI');

        $s_result = $s_date1 . 'th of ' . $s_longm . $s_date2;

    }
    return $s_result;
}

function convert_gamets2skyrim_long_date_no_time($gamets) {
    $s_result = "";

    $skyrim_start_timestamp = strtotime('0201-08-17 00:00:00'); 
    $f_gamets = floatval($gamets); 

    if ($f_gamets > 0.0) {
        $f_seconds = floatval($gamets) * 0.00864; 
        $ts_time = $skyrim_start_timestamp + intval($f_seconds);

        $s_month_number = date('m', $ts_time); // m	- Numeric representation of a month, with leading zeros	01 through 12
        $s_longm = month2str_skyrim_month($s_month_number);
        
        $s_year = ltrim(date('Y', $ts_time), '0');

        $s_date1 = date( 'd', $ts_time);
        $s_date2 = ', 4E '. $s_year;

        $s_result = $s_date1 . 'th of ' . $s_longm . $s_date2;

    }
    return $s_result;
}

function convert_gamets2skyrim_date($gamets) {
    $sRes = "";
    $skyrim_start_timestamp = strtotime('0201-08-17 00:00:00'); 
    $f_gamets = floatval($gamets); 
    if ($f_gamets > 0.0) {
        $ts_datetime = gamets2timestamp($f_gamets);
        $f_seconds = floatval($gamets) * 0.00864; 
        $ts_time = $skyrim_start_timestamp + intval($f_seconds);
        $sRes = date('Y-m-d H:i:s', $ts_time); //'YYYY-MM-DD HH24:MI:SS');
    }
    return $sRes;
}

//----------------------------------------------------------------------------
// miscellaneous date time functions related to Skyrim game timestamp (gamets)
//----------------------------------------------------------------------------

function gamets2timestamp($gamets) {
    $skyrim_start_timestamp = strtotime('0201-08-17 00:00:00'); 
    $f_seconds = floatval($gamets) * 0.00864; 
    $ts_time = $skyrim_start_timestamp + intval($f_seconds );
    return $ts_time;
}


function gamets2time_part($gamets) {
    $f_res = 0.0;
    $f_input = floatval($gamets) * 0.0000001; 
    if ($f_input > 0.0) {
        $f_res = $f_input - floor($f_input);
    }
    return $f_res;
}


function gamets2day_part($gamets) {
    $f_res = 0.0;
    $f_input = floatval($gamets) * 0.0000001; 
    if ($f_input > 0.0) {
        $f_res = floor($f_input);
    }
    return $f_res;
}


function gamets2days_between($gamets_start, $gamets_end) {
    return convert_gamets2days(floatval($gamets_end - $gamets_start));
}


function gamets2hours_between($gamets_start, $gamets_end) {
    return convert_gamets2hours(floatval($gamets_end - $gamets_start));
}


function gamets2minutes_between($gamets_start, $gamets_end) {
    return convert_gamets2minutes(floatval($gamets_end - $gamets_start));
}


function gamets2seconds_between($gamets_start, $gamets_end) {
    return convert_gamets2seconds(floatval($gamets_end - $gamets_start));
}


function gamets2str_format_date($gamets, $dt_format = 'Y-m-d H:i:s') {
    $skyrim_start_timestamp = strtotime('0201-08-17 00:00:00'); 
    $f_seconds = floatval($gamets) * 0.00864; 
    $ts_time = $skyrim_start_timestamp + intval($f_seconds );
    return date($dt_format, $ts_time);
}


function gamets2str_format_gregorian_date($gamets, $dt_format = 'Y-m-d H:i:s') { 
    $gregorian_start_timestamp = strtotime('1577-08-17 00:00:00'); // Gregorian year 0 is 2E 47
    $f_gamets = floatval($gamets); 
    $f_seconds = $f_gamets * 0.00864; 
    $ts_time = $gregorian_start_timestamp + intval($f_seconds);
    return date($dt_format, $ts_time);
}


function gamets2str_dow_gregorian($gamets) {
    $skyrim_start_timestamp = strtotime('0201-08-17 00:00:00'); 
    $f_seconds = floatval($gamets) * 0.00864; 
    $ts_time = $skyrim_start_timestamp + intval($f_seconds);
    // date('l', $ts_time) is wrong, Skyrim week is not ISO standard
    $s_dow_number = date('N', $ts_time);
    return dow2str_gregorian_day($s_dow_number);
}


function gamets2str_dow_skyrim($gamets) {
    $skyrim_start_timestamp = strtotime('0201-08-17 00:00:00'); 
    $f_seconds = floatval($gamets) * 0.00864; 
    $ts_time = $skyrim_start_timestamp + intval($f_seconds);
    // date('l', $ts_time) is wrong, Skyrim week is not ISO standard
    $s_dow_number = date('N', $ts_time);
    return dow2str_skyrim_day($s_dow_number);
}


function gamets2str_gregorian_month($gamets) { 
    $sRes = "";
    $ts_datetime = gamets2timestamp($gamets);
    return date('F', $ts_datetime);
}


function gamets2str_skyrim_month($gamets) { 

    $sRes = "";

    $f_gamets = floatval($gamets); 
    if ($f_gamets > 0.0) {
        $ts_datetime = gamets2timestamp($f_gamets);
        $sdx = date('m', $ts_datetime);
        /*
        Morning Star (January) 
        Sun's Dawn (February) 
        First Seed (March) 
        Rain's Hand (April) 
        Second Seed (May) 
        Mid Year (June) 
        Sun's Height (July) 
        Last Seed (August) 
        Hearthfire (September) 
        Frost Fall (October) 
        Sun's Dusk (November) 
        Evening Star (December)
        */
        if ($sdx == "01") { 
            $sRes = "Morning Star";
        } elseif($sdx == "02") {
            $sRes = "Sun's Dawn";
        } elseif($sdx == "03") {
            $sRes = "First Seed";
        } elseif($sdx == "04") {
            $sRes = "Rain's Hand";
        } elseif($sdx == "05") {
            $sRes = "Second Seed";
        } elseif($sdx == "06") {
            $sRes = "Mid Year";
        } elseif($sdx == "07") {
            $sRes = "Sun's Height";
        } elseif($sdx == "08") {
            $sRes = "Last Seed";
        } elseif($sdx == "09") {
            $sRes = "Hearthfire";
        } elseif($sdx == "10") {
            $sRes = "Frost Fall";
        } elseif($sdx == "11") {
            $sRes = "Sun's Dusk";
        } elseif($sdx == "12") {
            $sRes = "Evening Star";
        } else {
            $sRes = "unknown month";
        } 
    }
    return $sRes;
}


function gamets2str_season($gamets) { 

    $sRes = "";

    $f_gamets = floatval($gamets); 
    if ($f_gamets > 0.0) {
        $ts_datetime = gamets2timestamp($f_gamets);
        $sdx = date('m', $ts_datetime);

        if ($sdx == "01") { 
            $sRes = "Winter";
        } elseif($sdx == "02") {
            $sRes = "Winter";
        } elseif($sdx == "03") {
            $sRes = "Spring";
        } elseif($sdx == "04") {
            $sRes = "Spring";
        } elseif($sdx == "05") {
            $sRes = "Spring";
        } elseif($sdx == "06") {
            $sRes = "Summer";
        } elseif($sdx == "07") {
            $sRes = "Summer";
        } elseif($sdx == "08") {
            $sRes = "Summer";
        } elseif($sdx == "09") {
            $sRes = "Fall";
        } elseif($sdx == "10") {
            $sRes = "Fall";
        } elseif($sdx == "11") {
            $sRes = "Fall";
        } elseif($sdx == "12") {
            $sRes = "Winter";
        } else {
            $sRes = "unknown month";
        } 
    }
    return $sRes;
}


function hour2part_of_day($s_Hour) {
   
    $day_Morning = "morning";  //5 11
    $day_EarlyMorning = "early morning"; //5  8
    $day_BeforeSunrise = "right before sunrise"; // sunrise at 5 am
    $day_AfterSunrise = "right after sunrise"; // sunrise at 5 am
    $day_LateMorning = "late morning"; //11, 12
    $day_Noon = "noon"; //12
    $day_EarlyAfternoon = "early afternoon"; //13 14
    $day_Afternoon = "afternoon"; //12 16
    $day_LateAfternoon = "late afternoon"; //17
    $day_EarlyEvening = "early evening"; //17 18
    $day_Evening = "evening"; //17 21
    $day_BeforeSunset = "right before sunset"; // sunset at 19
    $day_AfterSunset = "right after sunset"; // sunset at 19
    $day_Night = "night"; //21 4
    $day_Midnight = "midnight"; //0  
    $day_AfterMidnight = "after midnight"; // 1

    $sRes = "";
    $sH = substr(trim($s_Hour), 0, 2);
        
    if (strlen($sH) > 0) {
        if (strlen($sH) < 2) $sH = '0' . $sH;
        switch ($sH) {
            case '00': $sRes = $day_Midnight; break;
            case '01': $sRes = $day_AfterMidnight; break;
            case '02': $sRes = $day_Night; break;
            case '03': $sRes = $day_Night; break;
            case '04': $sRes = $day_Night . ", " . $day_BeforeSunrise; break;
            case '05': $sRes = $day_EarlyMorning . ", " . $day_AfterSunrise; break;
            case '06': $sRes = $day_EarlyMorning; break;
            case '07': $sRes = $day_EarlyMorning; break;
            case '08': $sRes = $day_Morning; break;
            case '09': $sRes = $day_Morning; break;
            case '10': $sRes = $day_Morning; break;
            case '11': $sRes = $day_LateMorning; break;
            case '12': $sRes = $day_Noon; break;
            case '13': $sRes = $day_EarlyAfternoon; break;
            case '14': $sRes = $day_EarlyAfternoon; break;
            case '15': $sRes = $day_Afternoon; break;
            case '16': $sRes = $day_Afternoon; break;
            case '17': $sRes = $day_LateAfternoon; break;
            case '18': $sRes = $day_EarlyEvening . ", " . $day_BeforeSunset; break;
            case '19': $sRes = $day_Evening . ", " . $day_AfterSunset; break;
            case '20': $sRes = $day_Evening; break;
            case '21': $sRes = $day_Evening; break;
            case '22': $sRes = $day_Night; break;
            case '23': $sRes = $day_Night; break;
            case '24': $sRes = $day_Midnight; break;
            default: $sRes = "";
        }
    } 
    return $sRes;
} 


function skyrim_month2gregorian($s_skyrim_month_name) {
    /*
    Morning Star (January) 
    Sun's Dawn (February) 
    First Seed (March) 
    Rain's Hand (April) 
    Second Seed (May) 
    Mid Year (June) 
    Sun's Height (July) 
    Last Seed (August) 
    Hearthfire (September) 
    Frost Fall (October) 
    Sun's Dusk (November) 
    Evening Star (December) 
    */

    $sRes = "";
    $sdx = trim($s_skyrim_month_name);
     
    if (!(stripos($sdx, "Morning Star") === false)) { 
        $sRes = "January";
    } elseif(!(stripos($sdx, "Morningstar") === false)) {
        $sRes = "January";
    } elseif(!(stripos($sdx, "Sun's Dawn") === false)) { 
        $sRes = "Februry";
    } elseif(!(stripos($sdx, "First Seed") === false)) { 
        $sRes = "March";
    } elseif(!(stripos($sdx, "Rain's Hand") === false)) { 
        $sRes = "April";
    } elseif(!(stripos($sdx, "Second Seed") === false)) { 
        $sRes = "May";
    } elseif(!(stripos($sdx, "Midyear") === false)) { 
        $sRes = "June";
    } elseif(!(stripos($sdx, "Mid Year") === false)) { 
        $sRes = "June";
    } elseif(!(stripos($sdx, "Middle Yarr") === false)) { 
        $sRes = "June";
    } elseif(!(stripos($sdx, "Sun's Height") === false)) { 
        $sRes = "July";
    } elseif(!(stripos($sdx, "Last Seed") === false)) { 
        $sRes = "August";
    } elseif(!(stripos($sdx, "Hearthfire") === false)) { 
        $sRes = "September";
    } elseif(!(stripos($sdx, "Heartfire") === false)) {
        $sRes = "September";
    } elseif(!(stripos($sdx, "Hearth Fire") === false)) {
        $sRes = "September";
    } elseif(!(stripos($sdx, "Heart Fire") === false)) {
        $sRes = "September";
    } elseif(!(stripos($sdx, "Frostfall") === false)) { 
        $sRes = "October";
    } elseif(!(stripos($sdx, "Frost Fall") === false)) {
        $sRes = "October";
    } elseif(!(stripos($sdx, "Sun's Dusk") === false)) { 
        $sRes = "November";
    } elseif(!(stripos($sdx, "Evening Star") === false)) { 
        $sRes = "December";
    } 
    return $sRes;
}


function skyrim_era_explained($s_skyrim_year_name_with_era) {
/* Era
History is typically divided into six eras: 
Dawn Era DE
Merethic Era ME
First Era 1E
Second Era
Third Era
Fourth Era 4E */

    $sRes = "";
    $sEra = "";

    $sx = trim($s_skyrim_year_name_with_era);
    $ssplit = explode(" ", $sx);
    $sE = strtoupper($ssplit[0]);
    $sY = $ssplit[1];
    
    if ( (strlen($sY) > 0) && (strlen($sE) > 0) ) {

        if ($sE == "4E") { 
            $sEra = "Fourth Era";
        } elseif($sE == "3E") { 
            $sEra = "Third Era";
        } elseif($sE == "2E") { 
            $sEra = "Second Era";
        } elseif($sE == "1E") { 
            $sEra = "First Era";
        } elseif($sE == "ME") { 
            $sEra = "Merethic Era";
        } elseif($sE == "DE") { 
            $sEra = "Dawn Era";
        }

        if (strlen($sEra) > 0) {
            $sRes = "year {$sY} of {$sEra}";
        }
    }

    return $sRes;
}


function skyrim_year2gregorian($s_skyrim_year_name_with_era) {
/* Era
History is typically divided into six eras: 
Dawn Era DE - unknown
Merethic Era ME > 2500 years
First Era 1E - 1290 years
Second Era - 896 years  
Third Era - 433 years
Fourth Era 4E */

    $sRes = "";
    $sEra = "";

    $sx = trim($s_skyrim_year_name_with_era);
    $ssplit = explode(" ", $sx);
    $sE = strtoupper($ssplit[0]);
    $year = intval($ssplit[1]);
    $yearDelta = 0;
    
    if (strlen($sE) > 0) {

        if ($sE == "4E") { 
            $yearDelta = 1376;  
        } elseif($sE == "3E") { 
            $yearDelta = 943;   
        } elseif($sE == "2E") { 
            $yearDelta = 47;    
        } elseif($sE == "1E") { 
            $yearDelta = -1243; 
        } elseif($sE == "ME") { 
            $yearDelta = -3743;
        } elseif($sE == "DE") { 
            $yearDelta = -6084;
        }
    }

    $iRes = $yearDelta + $year;
    if ($iRes < 0) {
        $year = abs($iRes);
        $sRes = "{$year}BC";
    } else {
        $sRes = "{$iRes}";
    }    
    return $sRes;
}

function skyrim_month_explained($s_skyrim_month_name) {
    $sRes = "";
    $sdx = strtolower(trim($s_skyrim_month_name));
     
    if (!(stripos($sdx, "morning star") === false)) { //01
        $sRes = "first month of the year, second Winter month";
    } elseif(!(stripos($sdx, "morningstar") === false)) { //01
        $sRes = "first month of the year, second Winter month";
    } elseif(!(stripos($sdx, "sun's dawn") === false)) {  //02
        $sRes = "second month of the year, last Winter month";
    } elseif(!(stripos($sdx, "first seed") === false)) {  //03
        $sRes = "third month of the year, first Spring month";
    } elseif(!(stripos($sdx, "rain's hand") === false)) { //04
        $sRes = "fourth month of the year. second Spring month";
    } elseif(!(stripos($sdx, "second seed") === false)) { //05
        $sRes = "fifth month of the year, last Spring month";
    } elseif(!(stripos($sdx, "midyear") === false)) { //06
        $sRes = "sixth month of the year, first Summer month";
    } elseif(!(stripos($sdx, "mid year") === false)) { //06
        $sRes = "sixth month of the year, first Summer month";
    } elseif(!(stripos($sdx, "middle yarr") === false)) { //06
        $sRes = "sixth month of the year, first Summer month";
    } elseif(!(stripos($sdx, "sun's height") === false)) { //07
        $sRes = "seventh month of the year, second Summer month";
    } elseif(!(stripos($sdx, "last seed") === false)) { //08
        $sRes = "eighth month of the year, last Summer month";
    } elseif(!(stripos($sdx, "hearthfire") === false)) { //09
        $sRes = "ninth month of the year, first Fall month"; 
    } elseif(!(stripos($sdx, "heartfire") === false)) { //09
        $sRes = "ninth month of the year, first Fall month"; 
    } elseif(!(stripos($sdx, "hearth fire") === false)) { //09
        $sRes = "ninth month of the year, first Fall month"; 
    } elseif(!(stripos($sdx, "heart fire") === false)) { //09
        $sRes = "ninth month of the year, first Fall month"; 
    } elseif(!(stripos($sdx, "frostfall") === false)) { //10
        $sRes = "tenth month of the year, second Fall month";
    } elseif(!(stripos($sdx, "frost fall") === false)) { //10
        $sRes = "tenth month of the year, second Fall month";
    } elseif(!(stripos($sdx, "sun's dusk") === false)) { //11
        $sRes = "eleventh month of the year, last Fall month";
    } elseif(!(stripos($sdx, "evening star") === false)) { //12
        $sRes = "twelfth month of the year, first Winter month";
    } 

    return $sRes;
}

//--------------------------------------------------------------
// functions to retrieve Skyrim game timestamp from database
//--------------------------------------------------------------


function DataLastKnownGameTS() {
// retrieve gamets from eventlog
    global $db;

    $lastLoc=$db->fetchAll("SELECT MAX(gamets) AS m_gts FROM eventlog WHERE (gamets > 0)");
    if (!is_array($lastLoc) || sizeof($lastLoc)==0) {
        Logger::warn("DataLastKnownGameTS: NO record found");
    } else { // ok 
        if (isset($lastLoc[0]["m_gts"]) && (strlen($lastLoc[0]["m_gts"])>0)) {
            $i_gamets = intval($lastLoc[0]["m_gts"]);
            if ($i_gamets > 0) {
                return $i_gamets;
            }
        } else {
            Logger::error("DataLastKnownGameTS: NO game timestamp value found");
        }
    }
    return 0;
}

function DataLastKnownGameTS_record() {
// retrieve gamets and date in variuos formats from most recent record in eventlog
// require convert_gamets SQL functions in database
    global $db;

    $arr_result = [];
    $arr_result["exitcode"] = 0;

    $lastLoc=$db->fetchAll("SELECT gamets, ". 
        "convert_gamets2skyrim_date(gamets) as sk_date, ". 
        "convert_gamets2skyrim_long_date(gamets) as sk_long_date, ". 
        "convert_gamets2skyrim_long_date2(gamets) as sk_long_date2, ". 
        "convert_gamets2days(gamets) as sk_days, ". 
        "convert_gamets2hours(gamets) as sk_hours, ". 
        "convert_gamets2gregorian_date(gamets) as sk_gregorian_date, ". 
        "((gamets * 0.0000001) - floor(gamets * 0.0000001)) as sk_time, ". 
        "rowid ". 
        " FROM eventlog WHERE (gamets > 0) ORDER BY gamets desc, ts desc LIMIT 1");
    if (!is_array($lastLoc) || sizeof($lastLoc)==0) {
        $arr_result["exitcode"] = -1;
        Logger::warn("DataLastKnownGameTS_record: NO match found");
    } else { // ok 
        if (isset($lastLoc[0]["gamets"]) && (strlen($lastLoc[0]["gamets"])>0)) {
            $f_gamets = floatval($lastLoc[0]["gamets"]);
            if ($f_gamets > 0.0) {
                $arr_result["gamets"] = $f_gamets;
                $arr_result["sk_date"] = $lastLoc[0]["sk_date"];
                $arr_result["sk_long_date"] = $lastLoc[0]["sk_long_date"];
                $arr_result["sk_long_date2"] = $lastLoc[0]["sk_long_date2"];
                $arr_result["sk_days"] = intval($lastLoc[0]["sk_days"]);
                $arr_result["sk_hours"] = intval($lastLoc[0]["sk_hours"]);
                $arr_result["sk_time"] = floatval($lastLoc[0]["sk_time"]);
                $arr_result["sk_gregorian_date"] = $lastLoc[0]["sk_gregorian_date"];
                $arr_result["rowid"] = $lastLoc[0]["rowid"];
                //error_log(" dbg DataLastKnownGameTS_record: " . print_r($arr_result,true));
            }
        } else {
            $arr_result["exitcode"] = -2;
            Logger::warn("DataLastKnownGameTS_record: NO match found");
        }
    }
    return $arr_result;
}

//--------------------------------------------------------------
// functions to inject date time related info in prompts
//--------------------------------------------------------------


function get_datetime_for_prompt() {
// always get most recent value of gamets from database    
// require convert_gamets SQL functions in database

    $s2ins = "";

    $gamets_record = DataLastKnownGameTS_record();
    if ((!is_array($gamets_record) || sizeof($gamets_record)==0) || ($gamets_record["exitcode"] < 0)) {
        $s2ins = "";
    } else {
        $s2ins = 
            "\n Current date and time in Skyrim: ".
            $gamets_record["sk_long_date2"] . 
            " or briefly ".
            $gamets_record["sk_date"] . 
            "\n Equivalent Gregorian date: ".
            $gamets_record["sk_gregorian_date"] . "\n";
    }

    return $s2ins;
}


//--------------------------------------------------------------


function gamets2str_datetime_for_prompt_explained($gamets, $b_include_gregorian=true, $b_include_dow=true, $b_include_month_explain=true) {
// return date time context for specific gamats parameter
// output detailed context
// control output parts with parameter flags

    $s2ins = "";
    $f_gamets = floatval($gamets); 
    if ($f_gamets > 0.0) {
        $ts_datetime = gamets2timestamp($f_gamets);
        $s_datetime = date('Y-m-d H:i', $ts_datetime);

        $s_date = date('Y-m-d', $ts_datetime);
        $s_gregorian_date = gamets2str_format_gregorian_date($f_gamets,'Y-m-d H:i');
        $s_time = date('H:i', $ts_datetime);
        $s_hour = date('H', $ts_datetime);
        $s_day_part = hour2part_of_day($s_hour);

        $s_date_long = convert_gamets2skyrim_long_date_no_time($gamets);
        $s_day_of_week_gregorian = gamets2str_dow_gregorian($f_gamets);
        $s_day_of_week_skyrim = gamets2str_dow_skyrim($f_gamets);

        $s_month = date('m', $ts_datetime);
        $s_skyrim_month = gamets2str_skyrim_month($f_gamets);
        $s_gregorian_month = gamets2str_gregorian_month($f_gamets); 
        $s_month_explained = skyrim_month_explained($s_skyrim_month);
        //
        $s2ins .= "Current date in Skyrim: {$s_date_long}. Short date: {$s_date}";
        $s2ins .= "\n{$s_skyrim_month} ({$s_gregorian_month})";
        if ($b_include_month_explain) 
            $s2ins .= " is {$s_month_explained}.";
        if ($b_include_dow)  
            $s2ins .= "\nDay of week is {$s_day_of_week_skyrim} ({$s_day_of_week_gregorian}).";
        $s2ins .= "\nCurrent time: {$s_time}, {$s_day_part}.";
        if ($b_include_gregorian) 
            $s2ins .= "\nEquivalent Gregorian date and time is {$s_gregorian_date}.";
        $s2ins .= "\n";
    }
    return $s2ins;
}

function get_datetime_for_prompt_explained($gamets=0, $b_include_gregorian=true, $b_include_dow=true, $b_include_month_explain=true) {
// if $gamets is 0 or missing, get most recent value from database    

    $s2ins = "";

    if ($gamets > 0) { //use parameter
        $f_gamets = floatval($gamets);
    } else { // get from database
        $f_gamets = DataLastKnownGameTS(); 
    }
    if ($f_gamets > 0.0) {
        $s2ins = gamets2str_datetime_for_prompt_explained($f_gamets, $b_include_gregorian, $b_include_dow, $b_include_month_explain);
    }

    return $s2ins;
}

//--------------------------------------------------------------

function gamets2str_datetime_full_test($gamets=986414848, $gamets2=16414848) {
// test all functions in this file
// reference: 986414848 -> Sundas, 3:23 PM, 23rd of Sun's Dusk, 4E 201

    $s2ins = "";
    $f_gamets = floatval($gamets); 

    if ($f_gamets > 0.0) {
        $ts_datetime = gamets2timestamp($f_gamets);
        $s_datetime = date('Y-m-d H:i', $ts_datetime);

        $s_date = date('Y-m-d', $ts_datetime);
        $s_time = date('H:i', $ts_datetime);
        $s_hour = date('H', $ts_datetime);
        $s_month = date('m', $ts_datetime);
        $s_date_long = convert_gamets2skyrim_long_date($gamets); 

        $s2ins .= "\r\n --- dbg gamets: {$gamets} {$gamets2}";
        //$s2ins .= "\r\n {$s_datetime} "; 
        //$s2ins .= "\r\n {$s_date_long} ";
        //$s2ins .= "\r\n Sundas, 3:23 PM, 23rd of Sun's Dusk, 4E 201 ";

        $s2ins .= "\r\n convert_gamets2skyrim_long_date: " . convert_gamets2skyrim_long_date($gamets);
        $s2ins .= "\r\n convert_gamets2skyrim_long_date2: " . convert_gamets2skyrim_long_date2($gamets);
        $s2ins .= "\r\n convert_gamets2skyrim_long_date_no_time: " . convert_gamets2skyrim_long_date_no_time($gamets);        
        $s2ins .= "\r\n convert_gamets2skyrim_date: " . convert_gamets2skyrim_date($gamets);
        $s2ins .= "\r\n convert_gamets2gregorian_date: " . convert_gamets2gregorian_date($gamets);

        $s2ins .= "\r\n gamets2timestamp: " . gamets2timestamp($gamets);
        $s2ins .= "\r\n gamets2time_part: " . gamets2time_part($gamets);
        $s2ins .= "\r\n gamets2day_part: " . gamets2day_part($gamets);

        $s2ins .= "\r\n convert_gamets2days: " . convert_gamets2days($gamets);
        $s2ins .= "\r\n convert_gamets2days: " . convert_gamets2days($gamets2);
        $s2ins .= "\r\n gamets2days_between: " . gamets2days_between($gamets2,$gamets);  

        $s2ins .= "\r\n convert_gamets2hours: " . convert_gamets2hours($gamets);
        $s2ins .= "\r\n convert_gamets2hours: " . convert_gamets2hours($gamets2);
        $s2ins .= "\r\n gamets2hours_between: " . gamets2hours_between($gamets2,$gamets);

        $s2ins .= "\r\n convert_gamets2minutes: " . convert_gamets2minutes($gamets);
        $s2ins .= "\r\n convert_gamets2minutes: " . convert_gamets2minutes($gamets2);
        $s2ins .= "\r\n gamets2minutes_between: " . gamets2minutes_between($gamets2,$gamets);

        $s2ins .= "\r\n convert_gamets2seconds: " . convert_gamets2seconds($gamets);
        $s2ins .= "\r\n convert_gamets2seconds: " . convert_gamets2seconds($gamets2);
        $s2ins .= "\r\n gamets2seconds_between: " . gamets2seconds_between($gamets2,$gamets);

        $s2ins .= "\r\n gamets2str_format_date: " . gamets2str_format_date($gamets);
        $s2ins .= "\r\n gamets2str_format_gregorian_date: " . gamets2str_format_gregorian_date($gamets);

        $s2ins .= "\r\n gamets2str_dow_gregorian: " . gamets2str_dow_gregorian($gamets);
        $s_dow_gregorian = gamets2str_dow_gregorian($gamets);
        $s_dow_skyrim = convert_dow_gregorian2dow_skyrim($s_dow_gregorian); 
        $s2ins .= "\r\n convert_dow_gregorian2dow_skyrim: " . convert_dow_gregorian2dow_skyrim($s_dow_gregorian);
        $s2ins .= "\r\n convert_dow_skyrim2dow_gregorian: " . convert_dow_skyrim2dow_gregorian($s_dow_skyrim);

        $s_gregorian_month = gamets2str_gregorian_month($gamets); 
        $s2ins .= "\r\n gamets2str_gregorian_month: " . gamets2str_gregorian_month($gamets);

        $s2ins .= "\r\n gamets2str_skyrim_month: " . gamets2str_skyrim_month($gamets);
        $s2ins .= "\r\n gamets2str_season: " . gamets2str_season($gamets);
        $s2ins .= "\r\n hour2part_of_day: " . hour2part_of_day($s_hour);

        $s2ins .= "\r\n skyrim_month2gregorian: " . skyrim_month2gregorian(gamets2str_skyrim_month($gamets));
        $s2ins .= "\r\n skyrim_era_explained: " . skyrim_era_explained("4E 201");
        $s2ins .= "\r\n skyrim_year2gregorian: " . skyrim_year2gregorian("4E 201");

        $s2ins .= "\r\n skyrim_month_explained: " . skyrim_month_explained(gamets2str_skyrim_month($gamets));
        /*
        $s2ins .= "\r\n  " . ($gamets);
        $s2ins .= "\r\n  " . ($gamets);
        $s2ins .= "\r\n  " . ($gamets);
        $s2ins .= "\r\n  " . ($gamets);
        */
        $s2ins .= "\r\n --- dbg end";
        
        Logger::debug($s2ins);
    }
    return $s2ins;
}

//--------------------------------------------------------------

?>