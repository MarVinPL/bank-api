<?php
/**
 * Główna ścieżka do aplikacji
 */
define('APP_PATH', dirname($_SERVER['SCRIPT_FILENAME']) . DIRECTORY_SEPARATOR);

/**
 * Ścieżka do plików konfiguracyjnych
 */
define('DIR_CONFIG', APP_PATH . 'config' . DIRECTORY_SEPARATOR);

/**
 * Ścieżka do plików tymczasowych
 */
define('DIR_TEMP', APP_PATH . 'temp' . DIRECTORY_SEPARATOR);

/**
 * Zabezpieczenie przed załączeniem pliku
 */
define('IN_API', true);

setlocale(LC_TIME, "pl_PL");

header('Content-Type: text/html; charset=utf-8');

/**
 * Autoloader klas PHP
 * @param $className
 */
function __autoload($className) {
    require_once(APP_PATH . str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php');
}