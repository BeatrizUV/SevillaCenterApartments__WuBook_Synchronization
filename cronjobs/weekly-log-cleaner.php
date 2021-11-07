<?php
    /**
    * TÍTULO: Tarea cron para eliminar el archivo synchro.log
    * EMPRESA: S&L Apartamentos
    * AUTOR: Beatriz Urbano Vega
    * FECHA: 15/06/2016
    */

    // Ruta de los archivos del historial
    $filename = $_SERVER['DOCUMENT_ROOT'].'/wubook-synchro/synchro.log';

    try {        
        if (file_exists($filename)) {
            unlink($filename);
        }
    } catch (Exception $ex) {
        abort();
    }
    
    function abort() {
        // Cambiamos el nombre al archivo ejecutado para evitar próximas ejecuciones hasta arreglar el problema
        rename('weekly-log-cleaner.php', '.error');
        // Y notificamos el error por email
        mail(_SUPPORT_EMAIL, '[SCA] PROBLEMAS DE LIMPIEZA', 'Ha ocurrido un error al intentar eliminar registros antiguos de las actualizaciones del calendario.', 'From: ' . _SUPPORT_EMAIL);
        die();
    }
?>
