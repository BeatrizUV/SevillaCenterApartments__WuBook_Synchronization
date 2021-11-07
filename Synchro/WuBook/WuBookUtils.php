<?php
/**
 * TÍTULO: Tarea cron para actualizar los iCals de WuBook con los bloqueos de los diferentes canales por iCal
 * EMPRESA: S&L Apartamentos
 * AUTOR: Beatriz Urbano Vega
 * FECHA: 06/11/2015
 * CÓDIGOS DE ERROR WUBOOK:
 *      0	Ok
 *      -1	Authentication Failed
 *      -2	Invalid Token
 *      -3	Server is busy: releasing tokens is now blocked. Please, retry again later
 *      -4	Token Request: requesting frequence too high
 *      -5	Token Expired
 *      -6	Lodging is not active
 *      -7	Internal Error
 *      -8	Token used too many times: please, create a new token
 *      -9	Invalid Rooms for the selected facility
 *      -10	Invalid lcode
 *      -11	Shortname has to be unique. This shortname is already used
 *      -12	Room Not Deleted: Special Offer Involved
 *      -13	Wrong call: pass the correct arguments, please
 *      -14	Please, pass the same number of days for each room
 *      -15	This plan is actually in use
 *      -100	Invalid Input
 *      -101	Malformed dates or restrictions unrespected
 *      -1000	Invalid Lodging/Portal code
 *      -1001	Invalid Dates
 *      -1002	Booking not Initialized: use facility_request()
 *      -1003	Objects not Available
 *      -1004	Invalid Customer Data
 *      -1005	Invalid Credit Card Data or Credit Card Rejected
 *      -1006	Invalid Iata
 *      -1007	No room was requested: use rooms_request()
 */

global $log;

/**
 * Clase encargada de bloquear todas las fechas de los iCals en WuBook obtenidas mediante la clase CalendarUtils
 *
 * @author Beatriz Urbano Vega
 */
class WuBookUtils {
    
    /**
     * URL de acceso al Wired de WuBook
     * @var string
     */
    const _WIRED = 'https://wubook.net/xrws/';
    
    /**
     * Usuario para conectarse a WuBook
     * @var string
     */
    const _USER = 'SS172'; //'BU003';
    
    /**
     * Clave para coenctarse a WuBook
     * @var string 
     */
    const _PASSWORD = '43877'; //'86759';
    
    /**
     * Código PKEY para autenticarnos en WuBook
     * @var string 
     */
    const _PKEY = '$*GdhHRrEKjahPq$zmVv';
    
    /**
     * Código LCODE necesario para el funcionamiento de la aplicación
     * @var string 
     */
    const _LCODE = '1443091911'; //'1442482536';
    
    /**
     * Código de respuesta de WuBook tras enviar el XML (0 = ok, <0 = error)
     * @var int 
     */
    private static $returnCode;
    
    /**
     * Mensaje asociado a código de respuesta de WuBook ('Ok' = ok)
     * @var string
     */
    private static $info;
    
    /**
     * Token para asegurar las transacciones con el servidor
     * @var string 
     */
    private static $token;
    
    /**
     * Conexión establecida con el cliente.
     * @var Object 
     */
    private static $server;
    
    /**
     * Método que se encarga de crear una conexión con WuBook
     * @throws Exception
     */
    private static function connect() {
        $log = new Logger('synchro.log');
        self::$server = new xmlrpc_client(self::_WIRED);
        $args= array(new xmlrpcval(self::_USER, 'string'), new xmlrpcval(self::_PASSWORD, 'string'), new xmlrpcval(self::_PKEY, 'string'));
        $message = new xmlrpcmsg('acquire_token', $args);
        $log->register('ok', 'Enviada solicitud de conexion a WuBook Wired');
        return self::$server->send($message)->value();
    }
    
    /**
     * Método encargado de obtener el token de sesión a WuBook Wired.
     * @param array $struct
     * @throws Exception
     */
    private static function getToken($struct) {
        if (!$struct) {
            self::$token = -1;
        }
        
        $ires= self::_scal($struct, 0);
        
        if ($ires != 0) {
            self::$token = -1;
        }
        
        self::$token = self::_scal($struct, 1);
    }
    
    /**
     * Método encargado de leer los arrays estructurados devueltos por WuBook.
     * @param string $s
     * @param string $n
     * @return string
     * @throws Exception
     */
    private static function _scal($s, $n) {
        $t= $s->arraymem($n);
        return $t->scalarVal();
    }
    
    /**
     * Método encargado de actualizar la disponibilidad de los apartamentos en WuBook.
     * @param array $apartments
     * @return string
     * @throws Exception
     */
    public static function updateAvailability($apartments) {
        $log = new Logger('synchro.log');
        if ($apartments != false) {
            $log->register('ok', 'Hay fechas disponibles para bloquear en WuBook');
            $response = self::connect();
            self::getToken($response);
            if (self::$token > 0) {
                $log->register('ok', 'Conexion establecida correctamente con WuBook Wired');
                $response = self::updateChannels($apartments);
                self::releaseToken();     
            }
            self::$returnCode = self::_scal($response, 0);
            self::$info = self::_scal($response, 1);              
            $result = array('returnCode' => self::$returnCode, 'message' => self::$info);
        }
        else {
            $result = array('returnCode' => false, 'message' => 'No hay fechas pendientes de actualizar');
            $log->register('wa', 'No hay fechas disponibles para bloquear en WuBook');
        }
        
        return $result;
    }
    
    /**
     * Método encargado de enviar la petición de actualización a WuBook Wired.
     * @param array $apartments
     * @return array
     * @throws Exception
     */
    private static function updateChannels($apartments) {
        // {id:[{'avail':N, 'no_ota': N, 'date': 'dd/mm/yyyy'}], ...} 
        $log = new Logger('synchro.log');
        $array = array();       
        
        foreach($apartments as $apartment) {
            $dates = array();
            foreach($apartment['dates'] as $date) {
                $dates[] = new xmlrpcval(array('avail'  => new xmlrpcval(0, 'int'), 'date'   => new xmlrpcval(self::formatDate($date), 'string')), 'struct');
            }
            //'id' => new xmlrpcval('135361', 'string'),
            $array[] = new xmlrpcval(array('id' => new xmlrpcval($apartment['wubook_id'], 'string'), 'days' => new xmlrpcval($dates, 'array')), 'struct');
        }
        
        $args= array(new xmlrpcval(self::$token, 'string'), new xmlrpcval(self::_LCODE, 'int'), new xmlrpcval($array, 'array'));
        $log->register('ok', 'Asignados los argumentos a enviar a WuBook Wired');

        $message = new xmlrpcmsg('update_sparse_avail', $args);
        $log->register('ok', 'Mensaje XML para enviar a WuBook Wired creado correctamente');
        $response = self::$server->send($message)->value();
        $log->register('ok', '--- Mensaje XML enviado a WuBook Wired correctamente ---');
        
        return $response;        
    }
    
    /**
     * Método encargado de liberar el token de sesión conectado a WuBook Wired.
     * @return array
     * @throws Exception
     */
    private static function releaseToken() {
        $log = new Logger('synchro.log');
        $args= array(new xmlrpcval(self::$token, 'string'));
        $message = new xmlrpcmsg('release_token', $args);
        $log->register('ok', 'Conexion liberada con WuBook Wired');
        return self::$server->send($message)->value();
    }
    
    /**
     * Método encargado de formatear las fechas de formato anglosajón a español
     * @param string $date
     * @return string
     * @throws Exception
     */
    private static function formatDate($date) {
        return substr($date, 8, 2) . '/' . substr($date, 5, 2) . '/' . substr($date, 0, 4);
    }
}
?>