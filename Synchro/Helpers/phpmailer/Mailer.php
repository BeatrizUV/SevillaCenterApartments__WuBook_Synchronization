<?php
/**
 * TíTULO: Clase encargada de enviar un reporte por email del proceso de sincronización de los canales con WuBook.
 * EMPRESA: S&L Apartamentos
 * AUTOR: Beatriz Urbano Vega
 * FECHA: 20/11/2015
 */
include '../Synchro/Helpers/logger/Logger.php';

/**
 * Clase encargada de enviar emails.
 *
 * @author Beatriz Urbano Vega
 */
class Mailer {
    /**
     * Lista de emails para las notificaciones
     * @var array 
     */
    private static $_EMAILS = array('boss'    => '',
                                    'support' => _SUPPORT_EMAIL); 
    
    /**
     * Nombre del remitente
     */
    const _NAME = 'Sevilla Center Aparments';
    
    /**
     * Método encargado de enviar un reporte del proceso de sincronización por email.
     * @param array $today
     * @param array $lockedDates
     * @return boolean
     * @throws Exception
     */
    public static function report($today, $lockedDates) {
        $log = new Logger('../synchro.log');
        $body = '';
        
        if ($lockedDates != false) {
            $body = self::getBodyOk($today, $lockedDates);
        }
        else {
            $body = self::getBodyNoChanges($today);
        }
        
        $log->register('ok', 'Sincronizacion realizada correctamente');
        
        self::send($log, $body);
    }
    
    /**
     * Método encargado de crear el cuerpo del mensaje si todo está correcto.
     * PATRÓN: XX:XX>APT=XX/XX/XXXX,XX/XX/XXXX;APT=XX/XX/XXXX,XX/XX/XXXX|
     * @param array $lockedDates
     * @return string
     * @throws Exception
     */
    private static function getBodyOk($today, $lockedDates) {
        $body = '<center>';
        $body .= '<h1>FECHAS BLOQUEADAS EN WUBOOK</h1>';
        $body .= '<h2>(Sincronizacion realizada el ' . $today . ' a las ' . date('H:i:s', time()) . ')</h2>';
        $body .= '</center>';
        $body .= '<hr />';
        
        $updates = explode('|', $lockedDates);
        
        foreach($updates as $update) {
            $updateTokens = explode('>', $update);
            $body .= '<h3><u>' . $updateTokens[0] . ' H</u></h3>';
            $apartments = explode(';', $updateTokens[1]);            
            foreach($apartments as $apartmentToken) {
                $aptTokens = explode('=', $apartmentToken);
                $body .= '<p><b>' . strtoupper($aptTokens[0]) . ':</b> ' . str_replace(',', ', ', $aptTokens[1]) . '</p>';
            }            
            $body .= '<hr />';
        }
        
        return $body;
    }
    
    /**
     * Método encargado de crear el cuerpo del mensaje si hay un error.
     * @param array $result
     * @return string
     * @throws Exception
     */
    private static function getBodyError($result) {
        $body = '<center>';
        $body .= '<h1>ERROR AL REALIZAR LA SINCRONIZACION CON WUBOOK WIRED</h1>';
        $body .= '</center>';
        $body .= '<hr />';
        $body .= '<p><b>CODIGO DE RETORNO:</b> ' . $result['returnCode'] . '</p>';
        $body .= '<p><b>MENSAJE:</b> ' . $result['message'] . '</p>';
        
        return $body;
    }
    
    /**
     * Método encargado de crear el cuerpo del mensaje si no hay cambios.
     * @return string
     * @throws Exception
     */
    private static function getBodyNoChanges() {
        $body = '<center>';
        $body .= '<h1>FECHAS BLOQUEADAS EN WUBOOK</h1>';
        $body .= '<h2>(Sincronizacion realizada el ' . $today . ' a las ' . date('H:i:s', time()) . ')</h2>';
        $body .= '</center>';
        $body .= '<hr />';
        $body .= '<p>No hay fechas pendientes de bloquear en ninguno de los iCals de los canales sincronizados</p>';
        
        return $body;
    }
    
    /**
     * Método encargado de enviar el email con la notificación.
     * @param Logger $log
     * @param string $body
     * @throws Exception
     */
    private static function send($log, $body) {
        # Crea objeto PHPMailer
        $mail = new phpmailer;
        $mail->CharSet = 'ISO-8859-1';

        $mail->From = self::$_EMAILS['support'];
        $mail->FromName = self::_NAME;
        $mail->AddAddress(self::$_EMAILS['support'], self::_NAME);
        $mail->IsHTML(true);
        $mail->Subject = '[Sevilla Center Aparments] Bloqueo automatico de fechas en WuBook';
        $mail->Body = $body;

        if ($mail->Send()) {
            $log->register('ok', 'Notificacion enviada por email correctamente');
        }
        else {
            $log->register('er', 'Problemas al intentar enviar la notificacion por email');
        }
    }
}
?>
