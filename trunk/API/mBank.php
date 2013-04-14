<?php
/**
 * mBank API v0.5.1
 *
 * @author Jakub Konefał <jakub.konefal@studio85.pl>
 * @copyright Copyright (c) 2010-2013, Jakub Konefał
 * @link http://api.studio85.pl/
 */

if (!defined('IN_API')) exit("Hacking attempt");
if (!defined('APP_PATH')) exit("Where's that script?!");

/**
 * API mBank
 *
 * @property mixed menu
 * @property mixed accountList
 * @property mixed accountListOper
 */
class API_mBank extends API_Bank
{

    /**
     * @var string
     */
    private $_params = '';
    /**
     * @var array
     */
    private $_postInputs = array();
    /**
     * @var array
     */
    private $_expTag = array();
    /**
     * @var int
     */
    public $allowHourFrom = 0;
    /**
     * @var int
     */
    public $allowHourTo = 24;


    /**
     * Konstruktor
     */
    function __construct()
    {
        if ($this->canThisScriptRun() == false) {
            $this->exitMsg("Script is allowed to run between hours {$this->allowHourFrom}:00 - {$this->allowHourTo}:00");
        }

        // Start application
        parent::__construct();
        $this->fileCache = DIR_TEMP . 'mbank.cache.php';
        $this->fileConfig = DIR_CONFIG . 'mbank.cfg.php';
        $this->setConvertData("ISO-8859-2", "UTF-8");

        // Regular Expression Tags
        $this->_expTag['A'] = "<a[^']+'([^']+)','','POST','([^']+)[^>]+>([^<]+)</a>";
        $this->_expTag['P'] = "<p[^>]*>([^<]+)</p>";
        $this->_expTag['SPAN'] = "<span[^>]*>([^<]+)</span>";
        $this->_expTag['P_SPAN'] = "<p[^>]*>" . $this->_expTag['SPAN'] . "</p>";
        $this->_expTag['P_A'] = "<p[^>]*>" . $this->_expTag['A'] . "</p>";
        $this->_expTag['LI_P'] = "<li[^>]*>(<p class=\"[a-z]+\">.+</p>)</li>";
        $this->_expTag['MENU'] = "<li[^']+'([^']+)[^>]+>([^<]+)</a></li>";
        $this->_expTag['SPAN_WBR'] = "<span>(.+)<wbr />(.+)</span>";

    }

    /**
     * Utworzenie pliku konfiguracyjnego dla www.mbank.pl
     *
     * @param $customer
     * @param $password
     */
    public function createConfigFile($customer, $password)
    {
        $this->saveConfigFile(array(
            'customer' => $customer,
            'password' => $password
        ), TRUE);

    }

    /**
     * Sprawdzenie czy skrypt można uruchomić pomiędzy zdefiniowanymi godzinami
     *
     * @return bool
     */
    public function canThisScriptRun()
    {
        $hour = date("H");
        if (($hour >= $this->allowHourFrom) && ($hour < $this->allowHourTo))
            return true;
        else
            return false;
    }

    /**
     * Czyszczenie parametrów wejściowych
     *
     * @param bool $all
     */
    public function clearData($all = false)
    {
        $this->_params = '';
        unset($this->config['customer']);
        unset($this->config['password']);
        if ($all) {
            $this->config = array();
            $this->cookies = array();
            $this->resHeader = '';
            $this->resContent = '';
        }
    }

    /**
     * Sprawdzenie czy odpowiedź z serwera jest prawidłowa
     *
     * @param bool $login
     */
    public function verifyContent($login = false)
    {
        // Check if there's any alert/error
        if (preg_match("#Alarm bezpieczeństwa!#i", $this->resContent)) {
            $this->disconnectFromHost();
            if ($login)
                $this->exitMsg("Alarm bezpieczeństwa! Nieprawidłowy Identyfikator lub Hasło...");
            else
                $this->exitMsg("Alarm bezpieczeństwa! Prawdopodobnie wysłane złe polecenie do systemu bankowego...");
        } else if (!preg_match("#__STATE#i", $this->resContent) && !preg_match("#Object moved#i", $this->resContent)) {
            $this->disconnectFromHost();
            $this->exitMsg("Brak formularza!");
        } else if ($login && !preg_match("#Object moved#i", $this->resContent)) {
            $this->disconnectFromHost();
            $this->exitMsg("Nieprawidłowy login lub hasło!");
        }
    }

    /**
     * Odczytanie kwoty i waluty
     *
     * @param $resources
     * @return array
     */
    private function _explodeResources($resources)
    {
        return array(
            'amount' => substr($resources, 0, -4),
            'currency' => substr($resources, -3)
        );
    }

    /**
     * Odczytanie szczegółów rachunku
     *
     * @param $account_details
     * @return array
     */
    private function _explodeAccountDetails($account_details)
    {
        preg_match("#(.*) ([0-9]{2,2}[ ]{1,1}[0-9]{4,4}[ ]{1,1}[0-9]{4,4}[ ]{1,1}[0-9]{4,4}[ ]{1,1}[0-9]{4,4}[ ]{1,1}[0-9]{4,4}[ ]{1,1}[0-9]{4,4})#", $account_details, $match);
        if (count($match) != 3) {
            return array('title' => '', 'number' => '');
        }
        return array(
            'title' => $match[1],
            'number' => str_replace(" ", "", $match[2])
        );
    }

    /**
     * Przygotowanie parametrów zapytania
     *
     * @param string $parameters
     * @param array $addParams
     */
    private function _prepareParams($parameters = '', $addParams = array())
    {
        $_inputs = array_merge($this->_postInputs, $addParams);
        if (!empty($parameters)) $_inputs['__PARAMETERS'] = $parameters;
        $this->_params = "";
        foreach ($_inputs as $k => $v) {
            $this->_params .= "{$k}=" . urlencode($v) . "&";
        }
        // Fix params
        $this->_params = substr($this->_params, 0, -1);
        $this->debugResponse("_prepareParams() :: params", $this->_params, false, false);
    }

    /**
     * Parsowanie głównych input-ów
     */
    private function _parseMainFormInputs()
    {
        preg_match_all('#<input type="hidden" name="([^"]*)" id="[^"]*" value="([^"]*)" />#iU', $this->resContent, $firstPageMatch);
        array_shift($firstPageMatch);
        $this->_postInputs = array();
        foreach ($firstPageMatch[0] as $k => $v) {
            $key = trim($firstPageMatch[0][$k]);
            $value = trim($firstPageMatch[1][$k]);
            $this->_postInputs[$key] = $value;
        }
        if (IsSet($this->_postInputs['localDT'])) $this->_postInputs['localDT'] = trim(strftime("%e %B %Y %H:%M:%S", time() - 5 * 60));
        $this->debugResponse("_parseMainFormInputs() :: _postInputs", $this->_postInputs, false, false);
    }

    /**
     * Parsowanie menu
     */
    private function _parseMenu()
    {
        preg_match_all("#" . $this->_expTag['MENU'] . "#i", $this->resContent, $matches);
        //$this->debugResponse("_parseMenu() :: matches",$matches,false,false);
        $this->menu = array();
        if (IsSet($matches[1]) && count($matches[1]) > 0) {
            foreach ($matches[1] as $k => $v) {
                $this->menu[] = array('url' => $matches[1][$k], 'title' => $matches[2][$k]);
            }
        }
    }

    /**
     * Parsowanie listy rachunków
     */
    private function _parseAccountList()
    {
        $ea = $this->_expTag['A'];
        $es = $this->_expTag['SPAN'];
        $epa = $this->_expTag['P_A'];
        $eps = $this->_expTag['P_SPAN'];
        $elp = $this->_expTag['LI_P'];
        // Parser
        preg_match_all("#" . $elp . "#iU", $this->resContent, $matches);
        //$this->debugResponse("_parseAccountList() :: matches",$matches,false,false);
        $_arr = array();
        if (IsSet($matches[1]) && count($matches[1]) > 0) {
            foreach ($matches[1] as $k => $v) {
                if (preg_match("#" . $epa . $epa . $eps . "<p[^>]+>" . $ea . $ea . $ea . $ea . $ea . "</p>#i", $v, $match)) {
                    $this->accountList[] = $this->_parseAccountListMatch($match, true);
                } else if (preg_match("#" . $epa . $epa . $eps . "<p[^>]+>" . $ea . $ea . $ea . $ea . "</p>#i", $v, $match)) {
                    $this->accountList[] = $this->_parseAccountListMatch($match, true);
                } else if (preg_match("#" . $epa . $epa . $eps . "<p[^>]+>" . $ea . $ea . $ea . "</p>#i", $v, $match)) {
                    $this->accountList[] = $this->_parseAccountListMatch($match, false);
                } else if (preg_match("#" . $epa . $epa . $eps . "<p[^>]+>" . $ea . $ea . "</p>#i", $v, $match)) {
                    $this->accountList[] = $this->_parseAccountListMatch($match, false);
                } else {
                }
            }
        }
    }

    /**
     * Parsowanie szczegółów dotyczących poszczególnych rachunków (url/parametry)
     *
     * @param $match
     * @param bool $transfer_exec
     * @return array
     */
    private function _parseAccountListMatch($match, $transfer_exec = true)
    {
        $sub = ($transfer_exec == true) ? 0 : 3;
        return array(
            'account_details' => array(
                'url' => $match[1],
                'parameters' => $match[2],
                'account' => $this->_explodeAccountDetails($match[3])
            ),
            'account_oper_list_last14days' => array(
                'url' => $match[4],
                'parameters' => $match[5]
            ),
            'resources' => array(
                'balance' => $this->_explodeResources($match[6]),
                'available_resources' => $this->_explodeResources($match[7])
            ),
            'transfer_exec' => (
            $transfer_exec ? array(
                'url' => $match[8],
                'parameters' => $match[9],
                'title' => $match[10]
            ) : array('url' => '', 'parameters' => '', 'title' => '')
            ),
            'transfer_self_exec' => array(
                'url' => $match[11 - $sub],
                'parameters' => $match[12 - $sub],
                'title' => $match[13 - $sub]
            ),
            'account_oper_list' => array(
                'url' => $match[14 - $sub],
                'parameters' => $match[15 - $sub],
                'title' => $match[16 - $sub]
            ),
            'defined_transfers_list' => array(
                'url' => $match[17 - $sub],
                'parameters' => $match[18 - $sub],
                'title' => $match[19 - $sub]
            )
        );
    }

    /**
     * Parsowanie listy operacji (informacje o identyfikatorze)
     *
     * @param $match
     * @return array
     */
    private function _parseListOperTransferId($match)
    {
        preg_match("#id=\"\w+_([0-9]+)\"#iU", $match, $m);
        return isset($m[1]) ? $m[1] : null;
    }

    /**
     * Parsowanie listy operacji (informacje o transferze)
     *
     * @param $match
     * @return array
     */
    private function _parseListOperTransferInfo($match)
    {
        $ret = array();
        preg_match_all("#<span>(.*)</span>#iU", $match, $m);
        if (IsSet($m[1])) {
            foreach ($m[1] as $k => $v) {
                $v = trim($v);
                if (empty($v)) continue;
                switch ($k) {
                    case 0:
                        $key = 'contact';
                        break;
                    case 1:
                        $key = 'number';
                        break;
                    case 2:
                        $key = 'title';
                        break;
                    default:
                        $key = $k;
                }
                $ret[$key] = preg_replace("#[ ]{2,}#", " ", $v);
                $ret[$key] = str_replace("&shy;", "", $ret[$key]);
                $ret[$key] = strip_tags($ret[$key]);
            }
        }
        return $ret;
    }

    /**
     * Parsowanie listy operacji (szczegóły transferu)
     *
     * @param $match
     * @return array
     */
    private function _parseAccountListOperMatch($match)
    {
        $i = 1;
        return array(
            'date' => array(
                'operation' => $match[$i++],
                'accounting' => $match[$i++]
            ),
            'account_oper_details' => array(
                'tid' => $this->_parseListOperTransferId($match[$i++]),
                'url' => $match[$i++],
                'parameters' => $match[$i++],
            ),
            'transfer' => array(
                'type' => $match[$i++],
                'info' => $this->_parseListOperTransferInfo($match[$i++])
            ),
            'resources' => array(
                'operation_amount' => $this->_explodeResources($match[$i++]),
                'balance' => $this->_explodeResources($match[$i++])
            )
        );
    }


    /**
     * Odczytanie klucza na podstawie nr rachunku
     *
     * @param $accountNumber
     * @return int|string
     */
    private function _findKeyByAccountNumber($accountNumber)
    {
        $accountNumber = str_replace(" ", "", $accountNumber);
        foreach ($this->accountList as $k => $v) {
            if ($v['account_details']['account']['number'] == $accountNumber) {
                return $k;
            }
        }
        return -1;
    }

    /**
     * Parsowanie listy operacji
     */
    private function _parseAccountListOper()
    {
        $ea = $this->_expTag['A'];
        $ep = $this->_expTag['P'];
        $es = $this->_expTag['SPAN'];
        $eps = $this->_expTag['P_SPAN'];
        $elp = $this->_expTag['LI_P'];
        $esw = $this->_expTag['SPAN_WBR'];
        // Parser
        preg_match_all("#" . $elp . "#iU", $this->resContent, $matches);
        $this->debugResponse("_parseAccountListOper() :: matches", $matches, false, false);
        $_arr = array();
        if (IsSet($matches[1]) && count($matches[1]) > 0) {
            foreach ($matches[1] as $k => $v) {
                if (preg_match("#<p[^>]+>" . $es . $es . "</p><p[^>]+>(.*)</p><p[^>]+>" . $ea . "(.+)</p>" . $eps . $eps . "#i", $v, $match)) {
                    $this->accountListOper[] = $this->_parseAccountListOperMatch($match);
                }
            }
        }
    }


    /**
     * Logowanie do systemu bankowości elektronicznej
     */
    public function login()
    {
        if (!file_exists($this->fileConfig)) exit(
        <<<EOF
        There's no config file.<br>
<br>Launch the script once using this example:
<pre>\$mbank = new API_mBank();
\$mbank-&gt;createConfigFile("Identyfikator", "Haslo");
</pre>
EOF
        );
        $this->readConfigFile();
        $this->setConnectionInfo('ssl://', 'www.mbank.com.pl', 443, 'https://www.mbank.com.pl');
        $this->connectToHost();
        $this->sendToHost('/', '/');
        $this->verifyContent();
        $this->_parseMainFormInputs();
        if (empty($this->config['customer']) || empty($this->config['password'])) {
            $this->exitMsg("Nieprawidłowy login lub hasło");
        }
        $addParams = array('customer' => $this->config['customer'], 'password' => $this->config['password']);
        $this->_prepareParams("", $addParams);
        $this->sendToHost('/logon.aspx', '/', $this->_params);
        $this->verifyContent(true);
        $this->clearData(false);
    }

    /**
     * Prawidłowe wylogowanie z systemu
     */
    public function logout()
    {
        $this->sendToHost('/logout.aspx', '/frames.aspx');
        $this->clearData(true);
        $this->disconnectFromHost();
    }

    /**
     * Pobranie listy rachunków bankowych z serwera
     */
    public function getAccountsList()
    {
        $this->sendToHost('/accounts_list.aspx', '/frames.aspx');
        $this->verifyContent();
        $this->_parseMainFormInputs();
        $this->_parseMenu();
        $this->debugResponse("getAccountsList() :: menu", $this->menu, false, false);
        $this->_parseAccountList();
        $this->debugResponse("getAccountsList() :: accountList", $this->accountList, false, false);
    }

    /**
     * Pobranie listy operacji bankowych dla podanego rachunku bankowego
     *
     * @param $accountNumber
     * @return bool
     */
    public function getAccountsOperList($accountNumber)
    {
        $key = $this->_findKeyByAccountNumber($accountNumber);
        if ($key == -1) {
            echo "There's no such an account number";
            return false;
        }
        $this->_prepareParams($this->accountList[$key]['account_oper_list_last14days']['parameters']);
        $this->sendToHost('/account_oper_list.aspx', '/accounts_list.aspx', $this->_params);
        $this->verifyContent();
        $this->_parseMainFormInputs();
        $this->_parseAccountListOper();
        $this->debugResponse("getAccountsOperList() :: accountListOper", $this->accountListOper, false, false);
        return true;
    }


    /**
     * Wyświetlenie listy rachunków bankowych (przykładowo)
     */
    public function printAccountsList()
    {
        echo "<h2>Accounts list</h2>";
        foreach ($this->accountList as $v) {
            echo "<b>Account: " . $this->formatAccountNumber($v['account_details']['account']['number']) . "</b><br>";
            echo "&nbsp; Balance: {$v['resources']['balance']['amount']} {$v['resources']['balance']['currency']}<br>";
            echo "&nbsp; Available resources: {$v['resources']['available_resources']['amount']} {$v['resources']['available_resources']['currency']}<br><br>";

        }
    }


    /**
     * Wyświetlenie listy operacji dla wybranego rachunku bankowego (przykładowo)
     *
     * @param bool $display
     */
    public function printAccountsListOper($display = false)
    {
        if ($display) {
            echo "<h2>Account list operations</h2>";
        }
        $arr = array();
        if (count($this->accountListOper) == 0) {
            $this->exitMsg("Brak operacji dla wybranych kryteriów wyświetlania.");
        }
        foreach ($this->accountListOper as $v) {
            if (!IsSet($v['transfer']['info']['title'])) continue;
            $arr[] = json_encode(array(
                'date' => base64_encode($v['date']['accounting']),
                'title' => base64_encode($v['transfer']['info']['title']),
                'contact' => base64_encode($v['transfer']['info']['contact']),
                'amount' => base64_encode($v['resources']['operation_amount']['amount']),
                'currency' => base64_encode($v['resources']['operation_amount']['currency']),
                'tid' => base64_encode($v['account_oper_details']['tid'])
            ));
            if ($display) {
                echo "<b>Title: {$v['transfer']['info']['title']}</b> ({$v['transfer']['type']}) [TID: {$v['account_oper_details']['tid']}]<br>";
                echo "&nbsp; Date: {$v['date']['operation']} ({$v['date']['accounting']})<br>";
                echo "&nbsp; Amount: {$v['resources']['operation_amount']['amount']} {$v['resources']['operation_amount']['currency']}<br><br>";
            }
        }
        $newData = $this->saveCacheFile($this->fileCache, $arr);
        if ($newData && count($newData)) {
            foreach ($newData as $k => $v) {
                $arr = json_decode($v, true);
                foreach ($arr as $kk => $vv) $arr[$kk] = base64_decode($vv);
                if ($display) {
                    /*
                    echo "<br>Subject: {$subject}\n";
                    echo "<br>Message: {$msg}\n";
                    //echo "<br>SMS: {$sms}\n";
                    echo "<hr><br>";
                    */
                } else {
                    echo 'New transfer : ' . date("Y-m-d H:i:s") . "\n";
                    echo 'Title : ' . $arr['title'] . "\n";
                    echo 'Date : ' . $arr['date'] . "\n";
                    echo 'Amount : ' . $arr['amount'] . ' ' . $arr['currency'] . "\n";
                    //echo "SMS: {$sms}\n";
                    echo "\n\n";
                }
            }
        }
    }


}