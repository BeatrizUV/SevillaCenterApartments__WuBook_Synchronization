<?php
/**
 * T√çTULO: Clase encargada de obtener las fechas bloquedas de todos los iCals de la API
 * EMPRESA: S&L Apartamentos
 * AUTOR: Beatriz Urbano Vega
 * FECHA: 23/10/2015
 */

/**
 * Clase encargada de gestionar todos los calendarios en formato iCal y prepararlos para integrarlos en WuBook
 *
 * @author Beatriz Urbano Vega
 */
class CalendarUtils {     
    /**
      * M√©todo que recoge las fechas bloqueadas de cada apartamento en cada canal
      * @param string $xml
      * @return array
      * @throws Exception
      */
    public static function getChannelsICals($xml, $today) {
        $log = new Logger('synchro.log');
        $calendars = array();
        /**** ################### REVISAR ARRAYS ########################## *****/
        foreach ($xml->apartamento as $apartment){ 
            $apName = $apartment['nombre'];
            $wimdu = self::getICalTokens($apartment->wimdu, 'wimdu'); // DÌa a dÌa sin perÌodos
            $log->register('ok', 'Obtenidas fechas bloqueadas del apartamento '. $apName . ' en Wimdu');
            $houseTrip = self::getDaysByPeriod(self::getICalTokens($apartment->housetrip, 'housetrip'));
            $log->register('ok', 'Obtenidas fechas bloqueadas del apartamento '. $apName . ' en HouseTrip');
            $homeAway = self::getDaysByPeriod(self::getICalTokens($apartment->homeaway, 'homeaway'));
            $log->register('ok', 'Obtenidas fechas bloqueadas del apartamento '. $apName . ' en HomeAway');
            $calendar = self::getFutureDays(self::mergeCalendars($wimdu, $houseTrip, $homeAway), $today);
            $log->register('ok', 'Filtradas fechas bloqueadas duplicadas para todos los canales del apartamento '. $apName);
            $calendars["$apName"] = $calendar;
        }
       
        return $calendars;
    }
    
    /**
      * M√©todo que recoge las fechas bloqueadas de cada apartamento en cada canal.
      * @param string $xml
      * @return array
      * @throws Exception
      */
    public static function getWuBookICals($xml) {
        $log = new Logger('synchro.log');
        $calendars = array();
        
        foreach ($xml->apartamento as $apartment){ 
            $apName = $apartment['nombre'];
            $calendar['dates'] = self::getFutureDays(self::getDaysByPeriod(self::getICalTokens($apartment->wubook, 'wubook')));
            $log->register('ok', 'Obtenidas fechas bloqueadas del apartamento '. $apName . ' en WuBook');
            $calendar['wubook_id'] = $apartment->wubook_id + 0;
            $calendars["$apName"] = $calendar;
        }
        
        return $calendars;
    } 
    
    private static function getFutureDays($calendar, $today) {
        $size = count($calendar);
        $cont = 0;
        $dates = array();
        
        for($cont == 0; $cont < $size; $cont++) {
            if ($calendar[$cont] >= $today) {
                $dates[] = self::formatDate($calendar[$cont]);
            }
        }
        
        return $dates;
    }
     
    /**
     * M√©todo encargado de obtener las fechas bloqueadas en los calendarios de los canales que no lo est√°n en los calendarios de WuBook.
     * @param array $WuBookCalendars
     * @param array $channelCalendars
     * @return array
     * @throws Exception
     */
    public static function getDatesToUpdate($WuBookCalendars, $channelCalendars) {
        $log = new Logger('synchro.log');
        $datesToUpdate = false;
        
        foreach($WuBookCalendars as $apName => $WuBookApartment) {
            $WuBookCalendar = $WuBookApartment['dates'];
            $channelCalendar = $channelCalendars[$apName];
            
            $datesToUpdate["$apName"]['dates'] = array_diff($channelCalendar, $WuBookCalendar);            
            $datesToUpdate["$apName"]['wubook_id'] = $WuBookApartment['wubook_id'];
            $log->register('ok', 'Obtenidas fechas pendientes de bloquear en WuBook del apartamento '. $apName);
        }
         
        return $datesToUpdate;
    }
     
    /**
     * M√©todo encargado de obtener las fechas bloqueadas de calendarios por per√≠odos.
     * @param array $calendar
     * @return array
     * @throws Exception
     */ 
    private static function getDaysByPeriod($calendar) {
        $days = array();
        $intervals = '';
         
        if ($calendar != false) {
            foreach($calendar as $event) {
               $intervals .= ';' . implode(';', self::getDaysInterval($event['start'], $event['end']));                
            }
                
            $days = explode(';', substr($intervals, 1, strlen($intervals)));
        }
         
        return $days;
    }
    
    /**
     * M√©todo encargado de obtener el intervalo de d√≠as que hay entre dos fechas.
     * @param string $start
     * @param string $end
     * @return array
     * @throws Exception
     */ 
    private static function getDaysInterval($start, $end) {
        $dates = array($start);
        
        while(end($dates) < $end) {
            $dates[] = date('Ymd', strtotime(self::formatDate(end($dates)) . ' +1 day'));
        }
        
        return $dates;
    }
     
    /**
     * MÈ√©todo encargado de cambiar el formato de las fechas de anglosaj√≥n a espa√±ol.
     * @param string $date
     * @return string
     * @throws Exception
     */ 
    private static function formatDate($date) {
        return substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);
    }
     
    /**
     * Funci√≥n que une en solo array, elimina d√≠as duplicados y ordena las fechas bloqueadas de todos los iCals
     * @param array $wimdu
     * @param array $houseTrip
     * @param array $homeAway
     * @param array $wuBook
     * @return array
     * @throws Exception
     */
    private static function mergeCalendars($wimdu, $houseTrip, $homeAway) {
        $array = array_unique(array_merge($wimdu, $houseTrip, $homeAway));
        sort($array, SORT_STRING);         
        return $array;
    }
     
    /**
     * Funci√≥n que recoge los datos de un iCal y los parsea en un array
     * @param string $url
     * @param string $channel
     * @return array
     * @throws Exception
     */                           
    private static function getICalTokens($url, $channel) {
        $dates = false;
        $choppedICal = '';
        $iCal = '';         
        $wimduLength = 114;
        $housetripLength = 106;
        $homeawayLength = 76;
        $wubookLength = 71;
        
        // Seleccionamos el calendario iCal correspondiente y parseamos el calendario
        if ($url != 'no') {
            $iCal = preg_replace('/[\n|\r|\n\r|\t|\0|\x0B]/', '', file_get_contents($url));           
            switch($channel) {
                case 'wimdu': self::validateHeaderWimduICal($iCal, $wimduLength);
                              $choppedICal = self::prepareForChop(substr($iCal, $wimduLength, strlen($iCal))); break;                           
                case 'housetrip': self::validateHeaderHouseTripICal($iCal, $housetripLength);
                                  $choppedICal = self::prepareForChop(substr($iCal, $housetripLength, strlen($iCal))); break;
                case 'homeaway': self::validateHeaderHomeAwayICal($iCal, $homeawayLength);
                                 $choppedICal = self::prepareForChop(substr($iCal, $homeawayLength, strlen($iCal))); break;
                case 'wubook': $choppedICal = self::prepareForChop(substr($iCal, $wubookLength, strlen($iCal))); break;
            }            
            if (strlen($choppedICal) > 0) {
                $calendarTokens = explode('_#TOKEN#_', $choppedICal);            
                switch($channel) {
                    case 'wimdu': $dates = self::getWimduICalParsed($calendarTokens); break;
                    case 'housetrip': $dates = self::getHouseTripICalParsed($calendarTokens); break;
                    case 'homeaway': $dates = self::getHomeAwayICalParsed($calendarTokens); break;
                    case 'wubook': $dates = self::getWuBookICalParsed($calendarTokens); break;
                }
            }
        }    

        return $dates;
    }
     
    /**
     * Funci√≥n que se encarga de dividir el iCal en un array de eventos.
     * @param string $choppedICal
     * @return string
     * @throws Exception
     */
    private static function prepareForChop($choppedICal) {
        $choppedICal = str_replace('END:VEVENTBEGIN:VEVENT', '_#TOKEN#_', $choppedICal);
        $choppedICal = str_replace('BEGIN:VEVENT', '', $choppedICal);
        $choppedICal = str_replace('END:VEVENTEND:VCALENDAR', '', $choppedICal);
        $choppedICal = str_replace('END:VCALENDAR', '', $choppedICal);
        return $choppedICal;
    }
     
    /**
     * Funci√≥n que se encarga de parsear el iCal de WuBook
     * @param array $calendarTokens
     * @return array
     * @throws Exception
     */
    private static function getWuBookICalParsed($calendarTokens) {
        $dates = array();
        
        // hacemos arrays con cada l√≠nea del array
        /**
         * === WUBOOK ===
         * BEGIN:VEVENT
         * 0) UID:20151028T091106Z-82952@ord4
         * 1) DTSTART:20151028T000000
         * 2) DTEND:20151029T000000
         * 3) SUMMARY:RoomId: 128535 - Room not available
         * END:VEVENT
         * ==============
         * 
         * ;UID:20151016T100351Z-25313@ord4;20151016;20151021;RoomId: 128535 - Room not available;
         */ 
        foreach($calendarTokens as $token) {
            $token = str_replace('DTSTART:', ';', $token);
            $token = str_replace('DTEND:', ';', $token);
            $token = str_replace('SUMMARY:', ';', $token);
            $token = str_replace('T000000', '', $token);
            $tokens = explode(';', $token);
            $cleanTokens['start'] = $tokens[1];
            $cleanTokens['end'] = $tokens[2];
            $dates[] = $cleanTokens;
        }
       
       return $dates;
    }
     
    /**
     * Funci√≥n que se encarga de parsear el iCal de Wimdu
     * @param array $calendarTokens
     * @return array
     * @throws Exception
     */
    private static function getWimduICalParsed($calendarTokens) {
        $dates = array();
        
        // hacemos arrays con cada l√≠nea del array
        // Wimdu guarda en su iCal las fechas 3 veces:
        // 1) BEGIN:VEVENT + DTEND + DTSTART + END:VEVNT (dÌa suelto)
        // 2) BEGIN:VEVENT + DTEND + DTSTART + SUMMARY:Not available + END:VEVNT (dÌa suelto)
        // 3) BEGIN:VEVENT + DTEND + DTSTART + SUMMARY:RoomId: XXXX - Room not available + END:VEVNT (intervalo)
        // 4) BEGIN:VEVENT + DTEND + DTSTART + SUMMARY:XXXX + DESCRIPTION: XXXX + LOCATION: XXXX + END:VEVENT (intervalo) 
        /**
         * === WIMDU ===
         * BEGIN:VEVENT
         * 0) DTEND;VALUE=DATE:20151203
         * 1) DTSTART;VALUE=DATE:20151203
         * 2) DESCRIPTION: ........
         * 3) SUMMARY: .........
         * 4) LOCATION: .........
         * END:VEVENT
         * =============
         * 
         * 20151203;20151203
         */ 
        
        self::validateWimduICals($calendarTokens[0]);
        
        foreach($calendarTokens as $token) {
            if ((!preg_match('/RoomId/i', $token)) && (!preg_match('/DESCRIPTION/i', $token))) {
                $token = str_replace('DTSTART;VALUE=DATE:', ';', $token);
                $token = str_replace('DTEND;VALUE=DATE:', '', $token);
                $token = str_replace('DESCRIPTION:', ';', $token);
                $token = str_replace('SUMMARY:', ';', $token);
                $token = str_replace('LOCATION:', ';', $token);
                $tokens = explode(';', $token);
                $dates[] = $tokens[1];
            }
        }

        return $dates;
    }
     
    /**
     * Funci√≥n que se encarga de parsear el iCal de HouseTrip
     * @param array $calendarTokens
     * @return array
     * @throws Exception
     */
    private static function getHouseTripICalParsed($calendarTokens) {
        $dates = array();
        
        // hacemos arrays con cada l√≠nea del array
        /**
         * === HOUSETRIP ===
         * BEGIN:VEVENT
         * 0) DTEND;VALUE=DATE:20160222
         * 1) DTSTART;VALUE=DATE:20160218
         * 2) UID:21483db3e40dd30327f4b5ca9fa6dfdd@housetrip.com
         * 3) SUMMARY:HouseTrip: NEW! Apartment in Plaza del Duque Unavailable
         * END:VEVENT
         * =================
         * 
         * 20160222;20160218;21483db3e40dd30327f4b5ca9fa6dfdd@housetrip.com;HouseTrip: NEW! Apartment in Plaza del Duque Unavailable
         */ 
        
        self::validateHouseTripICals($calendarTokens[0]);
        
        foreach($calendarTokens as $token) {
            $token = str_replace('DTSTART;VALUE=DATE:', ';', $token);
            $token = str_replace('DTEND;VALUE=DATE:', '', $token);
            $token = str_replace('UID:', ';', $token);			
            $token = str_replace('SUMMARY:', ';', $token);			
            $tokens = explode(';', $token);
            $cleanTokens['start'] = $tokens[1];
            $cleanTokens['end'] = $tokens[0];
            $dates[] = $cleanTokens;
        }

        return $dates;
    }
     
    /**
     * Funci√≥n que se encarga de parsear el iCal de HomeAway
     * @param array $calendarTokens
     * @return array
     * @throws Exception
     */
    private static function getHomeAwayICalParsed($calendarTokens) {
        $dates = array();
        
        // hacemos arrays con cada l√≠nea del array
        /**
         * === HOMEAWAY ===
         * BEGIN:VEVENT
         * 0) UID:018657a0d8764d4f813968d997200635
         * 1) DTSTAMP:20151204T110802Z
         * 2) DTSTART:20150924
         * 3) DTEND:20150929
         * 4) SUMMARY:NO EST¡ DISPONIBLE 
         * END:VEVENT
         * =================
         * 
         * UID:018657a0d8764d4f813968d997200635DTSTAMP:20151204T110802ZDTSTART:20150924DTEND:20150929SUMMARY:NO EST¡ DISPONIBLE 
         * UID:018657a0d8764d4f813968d997200635DTSTAMP:20151204T110802Z;20150924;20150929;NO EST¡ DISPONIBLE 
         */ 
        
        self::validateHomeAwayICals($calendarTokens[0]);
        
        foreach($calendarTokens as $token) {
            if (!preg_match('/Provisional/i', $token)) { // Provisional = Fechas canceladas
                $token = str_replace('DTSTART:', ';', $token);
                $token = str_replace('DTEND:', ';', $token);
                $token = str_replace('SUMMARY:', ';', $token);			
                $tokens = explode(';', $token);
                $cleanTokens['start'] = $tokens[1];
                $cleanTokens['end'] = $tokens[2];
                $dates[] = $cleanTokens;
            }
        }
       
        return $dates;
    }
    
    private static function validateWimduICals($token) {
        /**
         * === WIMDU ===
         * BEGIN:VEVENT
         * 0) DTEND;VALUE=DATE:20151203
         * 1) DTSTART;VALUE=DATE:20151203
         * 2) DESCRIPTION:Warm and Spacious Flat Plaza Duque https://www.wimdu.es/user/reservations/O8XS4TJO
         * 3) SUMMARY:Jos Rossignol
         * 4) LOCATION:Teniente Borges  7\, 41002 Sevilla\, EspaÒa
         * END:VEVENT
         * =============
         * 
         * DTEND;VALUE=DATE:20151203DTSTART;VALUE=DATE:20151203DESCRIPTION:Warm and Spacious Flat Plaza Duque https://www.wimdu.es/user/reservations/O8XS4TJOSUMMARY:Jos RossignolLOCATION:Teniente Borges  7\, 41002 Sevilla\, EspaÒa
         * 20151203;20151203;Warm and Spacious Flat Plaza Duque https://www.wimdu.es/user/reservations/O8XS4TJO;Jos Rossignol;Teniente Borges  7\, 41002 Sevilla\, EspaÒa
         * 
         * ;20151203;20151203;
         */ 
        
        $token = str_replace('DTSTART;VALUE=DATE:', ';', $token);
        $token = str_replace('DTEND;VALUE=DATE:', '', $token);
        $token = str_replace('DESCRIPTION:', ';', $token);
        $token = str_replace('SUMMARY:', ';', $token);
        $token = str_replace('LOCATION:', ';', $token);
        $tokens = explode(';', $token);
        
        $token_1 = $tokens[0] + 0;
        
        if ((!is_int($token_1)) || (strlen($token_1) != 8)) {
            self::stop('Wimdu');
        }
    }
    
    private static function validateHouseTripICals($token) {
        /**
         * === HOUSETRIP ===
         * BEGIN:VEVENT
         * 1) DTEND;VALUE=DATE:20160223
         * 2) DTSTART;VALUE=DATE:20160220
         * 3) UID:9b5986c7693e0160bfc39eb9bcf0ec49@housetrip.com
         * 4) SUMMARY:HouseTrip: Acogedor apartamento Plaza del Duque Unavailable
         * END:VEVENT
         * =================
         * 
         * DTEND;VALUE=DATE:20160223DTSTART;VALUE=DATE:20160220UID:9b5986c7693e0160bfc39eb9bcf0ec49@housetrip.comSUMMARY:HouseTrip: Acogedor apartamento Plaza del Duque Unavailable
         * 20160223;20160220;9b5986c7693e0160bfc39eb9bcf0ec49@housetrip.com;HouseTrip: Acogedor apartamento Plaza del Duque Unavailable
         */ 
        
        $token = str_replace('DTSTART;VALUE=DATE:', ';', $token);
        $token = str_replace('DTEND;VALUE=DATE:', '', $token);
        $token = str_replace('UID:', ';', $token);			
        $token = str_replace('SUMMARY:', ';', $token);			
        $tokens = explode(';', $token);
        
        $token_1 = $tokens[0] + 0;
        $token_2 = $tokens[1] + 0;
        
        if (((!is_int($token_1)) || (strlen($token_1) != 8)) || ((!is_int($token_2)) || (strlen($token_2) != 8))) {
            self::stop('HouseTrip');
        }
    }
    
    private static function validateHomeAwayICals($token) {
        /**
         * === HOMEAWAY ===
         * BEGIN:VEVENT
         * 0) UID:018657a0d8764d4f813968d997200635
         * 1) DTSTAMP:20151204T110802Z
         * 2) DTSTART:20150924
         * 3) DTEND:20150929
         * 4) SUMMARY:NO EST¡ DISPONIBLE 
         * END:VEVENT
         * =================
         * 
         * UID:018657a0d8764d4f813968d997200635DTSTAMP:20151204T110802ZDTSTART:20150924DTEND:20150929SUMMARY:NO EST¡ DISPONIBLE 
         * UID:018657a0d8764d4f813968d997200635DTSTAMP:20151204T110802Z;20150924;20150929;NO EST¡ DISPONIBLE 
         */ 
        
        $token = str_replace('DTSTART:', ';', $token);
        $token = str_replace('DTEND:', ';', $token);
        $token = str_replace('SUMMARY:', ';', $token);			
        $tokens = explode(';', $token);
        
        $token_1 = $tokens[1] + 0;
        $token_2 = $tokens[2] + 0;
        
        if (((!is_int($token_1)) || (strlen($token_1) != 8)) || ((!is_int($token_2)) || (strlen($token_2) != 8))) {
            self::stop('HomeAway');
        }
    }
    
    private static function validateHeaderWimduICal($iCal, $length) {
        $token = substr($iCal, 0, $length);
        
        if ($token != 'BEGIN:VCALENDARPRODID;X-RICAL-TZSOURCE=TZINFO:-//com.denhaven2/NONSGML ri_cal gem//ENCALSCALE:GREGORIANVERSION:2.0') {
            self::stop('Wimdu (header)');
        }
    }
    
    private static function validateHeaderHouseTripICal($iCal, $length) {
        $token = substr($iCal, 0, $length);
        
        if ($token != 'BEGIN:VCALENDARPRODID;X-RICAL-TZSOURCE=TZINFO:-//Housetrip//Housetrip.com//ENCALSCALE:GREGORIANVERSION:2.0') {
            self::stop('HouseTrip (header)');
        }
    }
    
    private static function validateHeaderHomeAwayICal($iCal, $length) {
        $token = substr($iCal, 0, $length);
        
        if ($token != 'BEGIN:VCALENDARVERSION:2.0CALSCALE:GREGORIANPRODID:-//HomeAway.com, Inc.//EN') {
            self::stop('HomeAway (header)');
        }
    }
    
    private static function stop($channel) {
        $log = new Logger('synchro.log');
        $log->register('er', 'SINCRONIZACION CANCELADA - iCal de ' . $channel);
        // Cambiamos el nombre al archivo ejecutado para evitar prÛximas ejecuciones hasta arreglar el problema
        rename('index.php', '.error');
        mail(_SUPPORT_EMAIL, 'SINCRONIZACION CANCELADA - iCal de ' . $channel, 'El iCal de ' . $channel . ' tiene un formato incorrecto y debe ser corregido', 'From:' . _SUPPORT_EMAIL);
        die();
    } 
     
    /**
     * Funci√≥n que devuelve un objeto SimpleXMLElement con el listado de calendarios
     * @param string $file
     * @return SimpleXMLElement
     * @throws Exception
     */
    public static function getXML($file) {
        $result = false;
         
        if (file_exists($file)) {
            $result = simplexml_load_file($file);
        }
         
        return $result;
    }
}
?>
