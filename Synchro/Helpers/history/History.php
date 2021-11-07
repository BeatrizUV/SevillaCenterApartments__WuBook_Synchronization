<?php

/*
 * T�TULO: Clase encargada de registrar un log de los d�as bloqueados para cada apartamento durante todo el d�a
 * EMPRESA: S&L Apartamentos
 * AUTOR: Beatriz Urbano Vega
 * FECHA: 08/06/2016
 */

/**
 * Clase encargada de registrar un log de los d�as bloqueados para cada apartamento durante todo el d�a 
 * PATR�N: XX:XX>APT=XX/XX/XXXX,XX/XX/XXXX;APT=XX/XX/XXXX,XX/XX/XXXX|
 *
 * @author Beatriz Urbano Vega
 */
class History {
    
    /**
     * M�todo encargado de escribir en el archivo el historial de bloqueos del d�a.
     * @param string $datesToUpdate
     */
    public function save($xmlResult, $path, $datesToUpdate) {
        $log = new Logger();
        $flag = true;
        $line = '';
        
        if ($datesToUpdate != false) {
            if ($xmlResult['returnCode'] >= 0) {
                $filename = $path . '/' . date('Y-m-d', time()) . '.log'; // Grabamos la fecha as� para ordenar por nombre m�s f�cilmente
                //$body = self::getBodyOk($datesToUpdate);
                $line = self::parse($datesToUpdate);

                // Si hay nuevos apartamentos bloqueados procedemos a grabarlo en el archivo
                if ($line != '') {
                    $flag = self::record($line, $filename, $log);
                }
                $log->register('ok', 'Sincronizacion realizada correctamente');
            }
            else {
                // Error en la sincronizaci�n
                $log->register('er', 'Error al realizar la sincronizacion con WuBook Wired (' . $xmlResult['returnCode'] . ': ' . $xmlResult['message'] . ')');
                self::send($log, Mailer::getBodyError($xmlResult));
                $flag = true;
            }
        }
        else {
            $log->register('wa', 'No hay fechas pendientes de bloquear en WuBook Wired');
        }
        
        return $flag;
    }
    
    private function parse($datesToUpdate) {
        $line = '';
        foreach($datesToUpdate as $apName => $apartment) {
            // Si hay fechas para grabar en cada apartamento los agregamos a la l�nea que grabaremos m�s adelante
            if (count($apartment['dates']) > 0) {
                $line .= strtoupper($apName) . '=' . implode(',', self::formatDate($apartment['dates'])) . ';';            
            }
        }
        
        return $line;
    }
    
    /**
     * M�todo encargado de escribir en el archivo el historial y de formatearlo
     * @param type $line
     * @param type $filename
     * @return boolean
     */
    private function record($line, $filename, $log) {
        $flag = true;
        
        try {
            // A�adimos la hora de la grabaci�n en el fichero
            $line = date('h:i', time()) . '>' . $line;
            // Quitamos el �ltimo semicolon del fichero
            $line = substr($line, 0, -1);
           
            // Si el archivo ya existe es porque ya est� escrito, asi que a�adimos el pipe al principio de la l�nea para delimitar
            if (file_exists($filename)) {
               // A�adimos el pipe para cerrar la l�nea anterior y separarla de la nueva l�nea que quereos grabar en el fichero
               $line = '|' . $line;
            }
           
            // Abrimos el archivo por el final y agregamos contenido nuevo
            $historyFile = fopen($filename, 'a');
            if (!fwrite($historyFile, $line)) {
               $flag = false;
               $log->register('er', 'Problemas al intentar grabar el historial correspondiente.');
            }    
            fclose($historyFile);   
           
            return $flag;
        } catch (Exception $ex) {
            $log->register('er', 'Problemas al intentar grabar el historial correspondiente (' . $ex->getMessage() . ').');
            return false;
        }
    }
    
    public function recover($filename) {
        $log = new Logger();
        
        try {
            $dates = false;

            if (file_exists($filename)) {
                $dates = file_get_contents($filename);
            }
        } catch (Exception $ex) {
            $log->register('er', 'Problemas al intentar grabar el historial correspondiente (' . $ex->getMessage() . ').');
            return false;
        }
        return $dates;
    }
    
    private function formatDate($dateList) {
        $dates = array();
        foreach($dateList as $date) {
            $tokens = explode('-', $date);
            $date = $tokens[2] . '/' . $tokens[1] . '/' . $tokens[0];
            $dates[] = $tokens[2] . '/' . $tokens[1] . '/' . $tokens[0];
        }
        
        return $dates;
    }
}
?>