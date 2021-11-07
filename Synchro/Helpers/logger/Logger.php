<?php

/*
 * TÍTULO: Clase encargada de registrar un log de todos los procesos ejecutados durante la sincronización
 * EMPRESA: S&L Apartamentos
 * AUTOR: Beatriz Urbano Vega
 * FECHA: 20/11/2015
 */

/**
 * Clase encargada de registrar un log de todos los procesos ejecutados durante la sincronización 
 * PATRÓN: [Fri Nov 20 11:20:18 2015] [error] [client 213.129.161.147] Mensaje
 *
 * @author Beatriz Urbano Vega
 */
class Logger {
    
    private $filename;
    
    public function __construct($filename) {
        $this->filename = $filename;
    }
    
    /**
     * Método encargado de asignar la fecha del log.
     * @return string
     */
    private function getDate() {
        return '[' . date('D M d H:i:s Y', time()) . '] ';
    }
    
    /**
     * Método encargado de asignar el aviso de error.
     * @return string
     */
    private function getError() {
        return '[ERROR] ';        
    }
    
    /**
     * Método encargado de asignar el aviso de warning.
     * @return string
     */
    private function getWarning() {
        return '[WARNING] ';
    }
    
    /**
     * Método encargado de asignar el aviso de ok.
     * @return string
     */
    private function getInfo() {
        return '[OK] ';
    }
    
    /**
     * Método encargado de registrar la actividad en un archivo log.
     * @param string $type
     * @param string $message
     */
    public function register($type, $message) {
        $line = $this->getDate();
        
        switch($type) {
            case 'er': $line .= $this->getError();
                       break;
            case 'wa': $line .= $this->getWarning();
                       break;
            default: $line .= $this->getInfo();
        }
        
        $this->save($line . $message);
    }
    
    /**
     * Método encargado de escribir en el archivo log.
     * @param string $line
     */
    public function save($line) {
        $logFile = fopen($this->filename, 'a');
        fwrite($logFile, $line."\n");
        fclose($logFile);
    }
}
?>