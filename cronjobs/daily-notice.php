<?php
    include '../Synchro/Helpers/history/History.php';
    include '../Synchro/Helpers/phpmailer/class.phpmailer.php';
    include '../Synchro/Helpers/phpmailer/Mailer.php';

    /**
    * TíTULO: Tarea cron para notificar las actualizaciones diarias (una vez al d�a)
    * EMPRESA: S&L Apartamentos
    * AUTOR: Beatriz Urbano Vega
    * FECHA: 08/06/2016
    */

    // Ruta de los archivos del historial
    $historyPath = '../history';
    $lockedDates = false;
    $today = date('Y-m-d', time());

    try {
        $filename = $historyPath . '/' . $today . '.log';
        $todayES = substr($today, 8, 2) . '/' . substr($today, 5, 2) . '/' . substr($today, 0, 4);
        
        if (file_exists($filename)) {
            $lockedDates = History::recover($filename);
        }
        
        Mailer::report($todayES, $lockedDates);
    } catch (Exception $ex) {
        abort();
    }
    
    function abort() {
        // Cambiamos el nombre al archivo ejecutado para evitar próximas ejecuciones hasta arreglar el problema
        rename('index.php', '.error');
        rename('daily-notice.php', '.error');
        // Y notificamos el error por email
        mail(_SUPPORT_EMAIL, '[SCA] PROBLEMAS DE NOTIFICACIÓN', 'Ha ocurrido un error al intentar notificar las actualizaciones de hoy.', 'From: ' . _SUPPORT_EMAIL);
        die();
    }
?>
