<?php
/**
 * API Bank v0.4
 *
 * @author Jakub Konefał <jakub.konefal@studio85.pl>
 * @copyright Copyright (c) 2010-2010, Jakub Konefał
 * @link http://api.studio85.pl/
 */
if (!defined('IN_API')) exit("Hacking attempt");
if (!defined('APP_PATH')) exit("Where's that script?!");

/**
 * API Bank
 */
class API_Bank
{

    /**
     * @var bool
     */
    public $debug = false;
    /**
     * @var bool
     */
    public $clearTemp = false;
    /**
     * @var string
     */
    private $_convertFrom = '';
    /**
     * @var string
     */
    private $_convertTo = '';
    /**
     * @var
     */
    public $fp;
    /**
     * @var
     */
    public $ch;
    /**
     * @var array
     */
    public $config = array();
    /**
     * @var array
     */
    public $cookies = array();
    /**
     * @var int
     */
    public $contentLength = 0;
    /**
     * @var int
     */
    public $ignoreCookies = 0;
    /**
     * @var string
     */
    public $resHeader = '';
    /**
     * @var string
     */
    public $resContent = '';
    /**
     * @var string
     */
    public $fileConfig = '';
    /**
     * @var string
     */
    public $fileCache = '';


    /**
     * Konstruktor
     */
    function __construct()
    {
        $this->clearData(true);
    }

    /**
     * Odczytanie pliku konfiguracyjnego
     *
     * @return bool
     */
    public function readConfigFile()
    {
        if (!file_exists($this->fileConfig)) exit(
        <<<EOF
        There's no config file.<br>
<br>Launch the script once using this example:
<pre>\$bank = new API_Bank();
\$bank-&gt;saveConfigFile(array('USERNAME'=>'user','PASSWORD'=>'pass'), true);
</pre>
EOF
        );
        $cfile = implode('', file($this->fileConfig));
        for ($i = 0, $c = 1; $i < strlen($cfile); $i += 13, $c++) $cfile[$i] = chr(ord($cfile[$i]) - $c);
        $config = base64_decode(str_rot13($cfile));
        preg_match_all('#([a-z]+):"([^;]+)";#i', $config, $match);
        if (!IsSet($match[2])) {
            exit("Please create config file");
        }
        $this->config = array();
        foreach ($match[2] as $k => $v) {
            $this->config[$match[1][$k]] = stripslashes($v);
        }
        return true;
    }

    /**
     * Utworzenie pliku konfiguracyjnego
     *
     * @param array $config
     * @param bool $createConfigFile
     * @return bool
     */
    public function saveConfigFile($config = array(), $createConfigFile = false)
    {
        if (!$createConfigFile) return false;
        if (!file_exists($this->fileConfig)) {
            if (!touch($this->fileConfig)) {
                echo "I can't create the config file";
                exit;
            }
        }
        $content = '';
        foreach ($config as $k => $v) {
            $content .= $k . ':"' . addslashes($v) . '";';
        }
        $content = str_rot13(base64_encode($content));
        for ($i = 0, $c = 1; $i < strlen($content); $i += 13, $c++) $content[$i] = chr(ord($content[$i]) + $c);
        if ($fh = fopen($this->fileConfig, 'w')) {
            fwrite($fh, $content);
            fclose($fh);
        }
        return true;
    }

    /**
     * Wyczyszczenie danych tymczasowych
     *
     * @param bool $all
     */
    public function clearData($all = false)
    {
        if ($all) {
            $this->config = array();
            $this->cookies = array();
        }
    }

    /**
     * Ustawienie konwersji danych odczytywanych z serwera
     *
     * @param string $from
     * @param string $to
     */
    public function setConvertData($from = "ISO-8859-2", $to = "UTF-8")
    {
        $this->_convertFrom = $from;
        $this->_convertTo = $to;
    }

    /**
     * Konwersja odczytanych danych
     *
     * @param $data
     * @return string
     */
    public function convertData($data)
    {
        if (empty($this->_convertFrom) || empty($this->_convertTo)) return $data;
        return iconv($this->_convertFrom, $this->_convertTo, $data);
    }

    /**
     * Kodowanie danych (urlencode)
     *
     * @param $data
     * @return string
     */
    public function encodeUrlData($data)
    {
        if (empty($this->_convertFrom) || empty($this->_convertTo)) return $data;
        return urlencode(iconv($this->_convertTo, $this->_convertFrom, $data));
    }

    /**
     * Debugowanie odpowiedzi
     *
     * @param $title
     * @param $resp
     * @param bool $forceDisplay
     * @param bool $exit
     * @return mixed
     */
    public function debugResponse($title, $resp, $forceDisplay = false, $exit = false)
    {
        if (!$this->debug && !$forceDisplay) return;
        if (is_array($resp)) $title .= " (count: " . count($resp) . ")";
        echo "<hr><i>{$title}</i><pre>";
        print_r($resp);
        echo "</pre>";
        if ($exit) exit;
    }

    /**
     * Utworzenie pliku tymczasowego + weryfikacja
     *
     * @param $arrayName
     * @return bool|string
     */
    public function checkTempFile($arrayName)
    {
        $tempFile = DIR_TEMP . 'temp.' . $arrayName . '.php';
        if (!file_exists($tempFile)) {
            if (!touch($tempFile)) {
                exit("I can't create the temp file for " . $arrayName);
            }
            return false;
        }
        return $tempFile;
    }

    /**
     * Wyczyszczenie pliku tymczasowego
     *
     * @param $arrayName
     * @param bool $forceClear
     * @return bool
     */
    public function clearTempFile($arrayName, $forceClear = false)
    {
        if (!$forceClear) {
            return false;
        }
        if ($tempFile = $this->checkTempFile($arrayName)) {
            if ($fh = fopen($tempFile, 'w')) {
                fclose($fh);
                $this->debugResponse("clearTempFile :: {$arrayName} :: FILE HAS BEEN CLEARED", '', true, false);
                return true;
            }
        }
        $this->debugResponse("clearTempFile :: {$arrayName} :: FAILED", '', true, false);
        return false;
    }

    /**
     * Zapisanie (tablicy) zaszyfrowanych danych do pliku tymczasowego
     *
     * @param $arrayName
     * @param array $arrayData
     * @return bool
     */
    public function saveArrayToTempFile($arrayName, $arrayData = array())
    {
        if ($tempFile = $this->checkTempFile($arrayName)) {
            $content = str_rot13(base64_encode(serialize($arrayData)));
            for ($i = 0, $c = 1; $i < strlen($content); $i += 13, $c++) $content[$i] = chr(ord($content[$i]) + $c);
            if ($fh = fopen($tempFile, 'w')) {
                $this->debugResponse("saveArrayToTempFile :: {$arrayName} (count: " . count($arrayData) . ")", '', true, false);
                fwrite($fh, $content);
                fclose($fh);
                return true;
            }
        }
        return false;
    }

    /**
     * Czytanie zaszyfrowanych danych z pliku tymczasowego
     *
     * @param $arrayName
     * @return bool|mixed
     */
    public function readArrayFromTempFile($arrayName)
    {
        if ($tempFile = $this->checkTempFile($arrayName)) {
            $cfile = implode('', file($tempFile));
            for ($i = 0, $c = 1; $i < strlen($cfile); $i += 13, $c++) $cfile[$i] = chr(ord($cfile[$i]) - $c);
            return unserialize(base64_decode(str_rot13($cfile)));
        }
        return false;
    }

    /**
     * Nawiązanie połączenia z serwerem
     */
    public function connectToHost()
    {
        $this->fp = fsockopen($this->config['protocol'] . $this->config['host'], $this->config['port'], $errno, $errstr, 15);
        if (!$this->fp) {
            exit("ERROR : {$errno} :: {$errstr}");
        }
    }


    /**
     * Ustawienie protokołów komunikacyjnych
     *
     * @param string $protocol
     * @param string $host
     * @param string $port
     * @param string $url
     */
    public function setConnectionInfo($protocol = '', $host = '', $port = '', $url = '')
    {
        if (!empty($protocol)) $this->config['protocol'] = $protocol;
        if (!empty($host)) $this->config['host'] = $host;
        if (!empty($port)) $this->config['port'] = $port;
        if (!empty($url)) $this->config['url'] = $url;
    }


    /**
     * Wysłanie zapytania do serwera
     *
     * @param $path
     * @param $referer
     * @param string $params
     * @return bool
     */
    public function sendToHost($path, $referer, $params = '')
    {
        $post = empty($params) ? false : true;
        // Prepare header
        $header = ($post ? 'POST' : 'GET') . ' ' . $path . ' HTTP/1.1' . "\r\n";
        $header .= 'Host: ' . $this->config['host'] . "\r\n";
        $header .= 'User-Agent: User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; pl; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2 (.NET CLR 3.5.30729)' . "\r\n";
        $header .= 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8' . "\r\n";
        $header .= 'Accept-Language: pl,en-us;q=0.7,en;q=0.3' . "\r\n";
        $header .= 'Accept-Encoding: gzip,deflate' . "\r\n";
        $header .= 'Accept-Charset: ISO-8859-2,utf-8;q=0.7,*;q=0.7' . "\r\n";
        $header .= 'Keep-Alive: 115' . "\r\n";
        $header .= 'Connection: keep-alive' . "\r\n";
        $header .= 'Referer: ' . $this->config['url'] . $referer . "\r\n";
        if (count($this->cookies)) {
            $header .= 'Cookie: ' . implode('; ', $this->cookies) . "\r\n";
        }
        if ($post) {
            $header .= 'Content-Type: application/x-www-form-urlencoded' . "\r\n";
            $header .= 'Content-length: ' . strlen($params) . "\r\n";
        }
        $header .= 'Connection: close' . "\r\n\r\n";
        if ($post) $header .= $params;
        // Send header
        fputs($this->fp, $header);
        // Read the header and Content-Length
        $this->readHeaderFromHost();
        $this->parseHeader();
        if ($this->contentLength > 0) {
            // Read the content
            $this->readContentFromHost($this->contentLength);
        }
        // Debug
        $this->debugResponse("<h3>{$this->config['host']}{$path}</h3>", "", false, false);
        $this->debugResponse("_sendToHost() :: header", $header, false, false);
        $this->debugResponse("_sendToHost() :: Responce from {$path} :: Cookies", $this->cookies, false, false);
        $this->debugResponse("_sendToHost() :: Responce from {$path} :: resHeader", $this->resHeader, false, false);
        $this->debugResponse("_sendToHost() :: Responce from {$path} :: resContent", "<textarea style='width:100%;height:300px'>" . htmlspecialchars($this->resContent) . "</textarea>", false, false);
        return true;
    }

    /**
     * Rozłączenie się z serwerem
     */
    public function disconnectFromHost()
    {
        fclose($this->fp);
    }

    /**
     * Zakończenie wykonywania skryptu
     *
     * @param $msg
     * @param bool $header
     */
    public function exitMsg($msg, $header = true)
    {
        if ($header)
            @header('Content-Type: text/html; charset=utf-8');
        exit($msg);
    }


    /**
     * Wysłanie zapytania do serwera i rozłączenie się
     *
     * @param $path
     * @param $referer
     * @param string $params
     * @return bool
     */
    public function sendToHostAndDisconnect($path, $referer, $params = '')
    {
        $post = empty($params) ? false : true;
        $header = array();
        $header[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        $header[] = 'Accept-Language: pl,en-us;q=0.7,en;q=0.3';
        $header[] = 'Accept-Charset: ISO-8859-2,utf-8;q=0.7,*;q=0.7';
        $header[] = "Cache-Control: max-age=0";
        $header[] = "Connection: keep-alive";
        $header[] = "Keep-Alive: 115";
        //$header[] = "Pragma: ";
        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_URL, $this->config['url'] . $path);
        curl_setopt($this->ch, CURLOPT_USERAGENT, 'User-Agent: User-Agent: Mozilla/5.0 (Windows; U; Windows NT 5.1; pl; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2 (.NET CLR 3.5.30729)');
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($this->ch, CURLINFO_HEADER_OUT, true);
        curl_setopt($this->ch, CURLOPT_REFERER, $this->config['url'] . $referer);
        curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_TIMEOUT, 5);
        if (substr($this->config['url'], 0, 5) == 'https') {
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 2);
        }
        if ($post) {
            curl_setopt($this->ch, CURLOPT_POST, true);
            curl_setopt($this->ch, CURLOPT_POSTFIELDS, $params);
        }
        if (count($this->cookies)) {
            curl_setopt($this->ch, CURLOPT_COOKIE, implode(';', $this->cookies));
        }
        $chExec = curl_exec($this->ch);
        $chInfo = curl_getinfo($this->ch);
        curl_close($this->ch);
        $this->resHeader = $this->convertData(substr($chExec, 0, $chInfo["header_size"]));
        $this->parseHeader();
        $this->resContent = $this->convertData(substr($chExec, $chInfo["header_size"] + 1));
        // Debug
        $this->debugResponse("<h3>" . ($post ? 'POST' : 'GET') . " {$this->config['host']}{$path}</h3>", "", false, false);
        $this->debugResponse("_sendToHost() :: chInfo", $chInfo, false, false);
        $this->debugResponse("_sendToHost() :: Responce from {$path} :: resHeader", $this->resHeader, false, false);
        $this->debugResponse("_sendToHost() :: Responce from {$path} :: Cookies", $this->cookies, false, false);
        $this->debugResponse("_sendToHost() :: Responce from {$path} :: resContent", "<textarea style='width:100%;height:300px'>" . htmlspecialchars($this->resContent) . "</textarea>", false, false);
        return true;
    }


    /**
     * Parsowanie nagłówka
     *
     * @return bool
     */
    public function parseHeader()
    {
        preg_match("#Content-Length: ([0-9]+)#i", $this->resHeader, $match);
        $this->contentLength = (IsSet($match[1])) ? $match[1] : 0;
        if ($this->ignoreCookies) return false;
        // Parse header for some extra data
        preg_match_all("#Set-Cookie: ([^\n]+)#i", $this->resHeader, $matches);
        //$this->debugResponse("parseHeader() :: matches",$matches,true,true);
        foreach ($matches[1] as $k => $v) {
            $cookieData = '';
            $cookieArray = explode(';', $v);
            foreach ($cookieArray as $ck => $cv) {
                if ($ck == 0) {
                    $cookieData = $cv;
                    continue;
                }
                $cookieArraySplit = explode('=', trim($cv));
                if (IsSet($cookieArraySplit[1]) && ($cookieArraySplit[0] == 'expires')) {
                    $expires = strtotime($cookieArraySplit[1]);
                    if (time() > $expires) {
                        echo 'koniec!';
                        exit;
                    }
                }
            }
            $this->cookies[] = $cookieData;
        }
        /*
        if( IsSet($matches[1]) && count($matches[1])>0 ) {
          $this->cookies = array_merge($this->cookies,$matches[1]);
          $this->cookies = array_unique($this->cookies);
        }
        */
        return true;
    }

    /**
     * Odczytanie nagłówka z serwera
     */
    public function readHeaderFromHost()
    {
        $this->resHeader = '';
        while (($line = fgets($this->fp, 4096)) != "\r\n") {
            $this->resHeader .= $line;
        }
        $this->resHeader = $this->convertData($this->resHeader);
    }


    /**
     * Odczytanie treści z serwera
     *
     * @param $contentLength
     */
    public function readContentFromHost($contentLength)
    {
        $this->resContent = '';
        for ($i = 1; $i <= $contentLength; $i++) {
            $this->resContent .= fgetc($this->fp);
        }
        $this->resContent = $this->convertData($this->resContent);
    }

    /**
     * Formatowanie numeru rachunku bankowego
     *
     * @param $accountNumber
     * @return mixed
     */
    public function formatAccountNumber($accountNumber)
    {
        return preg_replace(
            "#([0-9]{2})([0-9]{4})([0-9]{4})([0-9]{4})([0-9]{4})([0-9]{4})([0-9]{4})#"
            , "\\1 \\2 \\3 \\4 \\5 \\6 \\7"
            , $accountNumber
        );
    }


    /**
     * Zapisanie pliku tymczasowego
     *
     * @param $fcache
     * @param array $saveData
     * @return array|bool
     */
    public function saveCacheFile($fcache, $saveData = array())
    {
        if (!file_exists($fcache)) {
            if (!touch($fcache)) {
                echo "Nie można utworzyć pliku cache!";
                exit;
            }
        }
        $content = <<<EOF
<?php
if ( !defined('IN_API') ) exit("Hacking attempt");
\$lastData = array();
EOF;
        $lastData = $this->readCacheFile($fcache);
        $sameData = array_intersect($lastData, $saveData);
        $newData = array();
        foreach ($saveData as $k => $v) {
            if (!in_array($v, $lastData)) $newData[] = $v;
        }
        //echo "<pre>"; echo "saveData"; print_r($saveData); echo "lastData"; print_r($lastData); echo "sameData"; print_r($sameData); echo "newData"; print_r($newData); //exit;
        if (count($newData)) {
            $saveData = array_merge($lastData, $newData);
            foreach ($saveData as $k => $v) {
                $content .= "\n\$lastData[] = '{$v}';";
            }
            // Zapisanie strony do pliku cache
            if ($fh = fopen($fcache, 'w')) {
                fwrite($fh, $content);
                fclose($fh);
                return $newData;
            }
        }
        return false;
    }


    /**
     * Odczytanie pliku tymczasowego
     *
     * @param $fcache
     * @return array
     */
    public function readCacheFile($fcache)
    {
        if (!file_exists($fcache)) return array();
        require_once($fcache);
        if (IsSet($lastData) && count($lastData)) return $lastData;
        return array();
    }

    /**
     * Parsowanie daty do znacznika czasu
     *
     * @param $date
     * @return int
     */
    public function parseDateToTimestamp($date)
    {
        $date = str_replace(
            array('Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec', 'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień')
            , array('January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December')
            , $date
        );
        $date = str_replace(
            array('Sty', 'Lut', 'Mar', 'Kwi', 'Maj', 'Cze', 'Lip', 'Sie', 'Wrz', 'Paź', 'Lis', 'Gru')
            , array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec')
            , $date
        );
        $date = str_replace(
            array('Poniedziałek', 'Wtorek', 'Środa', 'Czwartek', 'Piątek', 'Sobota', 'Niedziela')
            , array('Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
            , $date
        );
        $date = str_replace(
            array('Pon', 'Wto', 'Śro', 'Czw', 'Pią', 'Sob', 'Nie')
            , array('Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun')
            , $date
        );
        //echo $date.' :: '.strtotime($date).' :: '.date("Y-m-d H:i:s",strtotime($date));
        return strtotime($date);
    }

}