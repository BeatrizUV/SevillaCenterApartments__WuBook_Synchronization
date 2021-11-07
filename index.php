<?php
    /**
     * MODOS: 
     *  - ALERT: S�lo notifica por email las fechas a bloquear.
     *  - SYNCHRO: Sincroniza los calendarios, bloquea fechas en WuBook y notifica por email de los cambios.
     */
    const _MODE = 'ALERT';
    const _SUPPORT_EMAIL = '';
    
    include 'Synchro/ICals/CalendarUtils.php';
    include 'Synchro/WuBook/WuBookUtils.php';
    include 'Synchro/Helpers/xmlrpc/xmlrpc.inc';
    include 'Synchro/Helpers/history/History.php';
    include 'Synchro/Helpers/logger/Logger.php';
    include 'Synchro/Helpers/phpmailer/class.phpmailer.php';
    include 'Synchro/Helpers/phpmailer/Mailer.php';

    /**
     * TÍTULO: Tarea cron para actualizar los iCals de WuBook con los bloqueos de los diferentes canales por iCal
     * EMPRESA: S&L Apartamentos
     * AUTOR: Beatriz Urbano Vega
     * FECHA: 23/10/2015
     */

    // Ruta del archivo donde se guardan todos los datos de los iCals de los apartamentos
    $xmlPath = 'xml/calendars.xml';
    $historyPath = 'history';

    try {
        $log = new Logger('synchro.log');
        $today = date('Ymd', time());
        
        $log->register('ok', '######################## INICIO ########################');
        
        // 1. Obtenemos todas las fechas bloquedas de los iCals de los canales, las fusionamos y las parseamos en un array
        $channelCalendars = CalendarUtils::getChannelsICals(CalendarUtils::getXML($xmlPath), $today);  

        // 2. Obtenemos todas las fechas bloqueadas de los iCals de WuBook
        $WuBookCalendars = CalendarUtils::getWuBookICals(CalendarUtils::getXML($xmlPath));

        // 3. Obtenemos las fechas bloqueadas de los canales que no están en los calendarios de WuBook para cada apartamento
        $datesToUpdate = CalendarUtils::getDatesToUpdate($WuBookCalendars, $channelCalendars);
        
        // 4. Conectamos con WuBook, actualizamos los registros de WuBook y desconectamos de WuBook
        if (_MODE == 'SYNCHRO') {
            //$result = WuBookUtils::updateAvailability($datesToUpdate);
        }

        // 5. Chequeamos el resultado de la conexión XML y si no hay errores guardamos los resultados de la operación en un archivo 
        // que enviaremos al final del día. Si hay errores se notifica por email y se bloquea el sistema para que no siga actualizandose
        if (!History::save($result, $historyPath, $datesToUpdate)) {
            abort($log, 'Errores durante el proceso de grabado del historial o durante la conexión XML "CODE ' . $result['returnCode'] . '"');
        }
        
        $log->register('ok', '######################## FIN ########################');
    } catch (Exception $ex) {
        abort($log, $ex->getMessage());
    }
    
    function abort($log, $message) {
        // Si hay algún error lo registramos en el log
        $log->register('er', $message);
        // Cambiamos el nombre al archivo ejecutado para evitar próximas ejecuciones hasta arreglar el problema
        rename('index.php', '.error');
        // Y notificamos el error por email
        mail(_SUPPORT_EMAIL, '[SCA] SINCRONIZACION CANCELADA', 'Se ha cancelado la tarea cron de sincronización automatica debido a un error', 'From: ' . _SUPPORT_EMAIL);
        die();
    }
?>
