<?php
/**
 * API mBank v0.5.0
 *
 * @author Jakub Konefał <jakub.konefal@studio85.pl>
 * @copyright Copyright (c) 2010-2010, Jakub Konefał
 * @link http://api.studio85.pl/
 */
require_once('config' . DIRECTORY_SEPARATOR . 'startup.php');

$mbank = new API_mBank();
//$mbank->createConfigFile("Identyfikator", "Hasło");
$mbank->login();
$mbank->getAccountsList();
$mbank->printAccountsList();
//$mbank->getAccountsOperList('xx xxxx xxxx xxxx xxxx xxxx xxxx');
$mbank->logout();
//$mbank->printAccountsListOper(true);