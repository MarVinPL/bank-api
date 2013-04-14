<?php
/**
 * Salon.pl API v0.2.0
 *
 * @author Jakub Konefał <jakub.konefal@studio85.pl>
 * @copyright Copyright (c) 2010-2013, Jakub Konefał
 * @link http://api.studio85.pl/
 */

define('IN_API', TRUE);

//define('PATH', dirname($_SERVER['SCRIPT_FILENAME']).'/');
//define('PATH', '/homez.170/lukaszwo/www.api.studio85.pl/');
define('PATH', '/home/studio85/ftp/domains/studio85.pl/api/');

require_once(PATH . "salonpl.class.php");


$phpbb_root_path = '/home/studio85/ftp/domains/komandorskie-wzgorze.pl/forum/';
define('IN_PHPBB', true);
$phpEx = substr(strrchr(__FILE__, '.'), 1);
include($phpbb_root_path . 'common.' . $phpEx);
include($phpbb_root_path . 'includes/bbcode.' . $phpEx);
include($phpbb_root_path . 'includes/functions_display.' . $phpEx);
include($phpbb_root_path . 'includes/functions_posting.' . $phpEx);
$user->session_begin();

$salonpl = new SalonPL();
$salonpl->debug = false;
$salonpl->userId = 55; // ID użytkownika, który będzie tworzył tematy/posty
$salonpl->forumId = 18; // ID forum, gdzie będą dodawane nowe tematy/posty
$salonpl->readConfigFile();
$salonpl->getUserByID($salonpl->userId); // Logowanie się na forum jako użytkownik SalonPL
$salonpl->readForumTopics(); // Odczytywanie tematów z forum
$salonpl->readForumTopicPosts(); // Odczytywanie postów z forum
$salonpl->loginAsUser(); // Logowanie się na forum salon.pl
$salonpl->getLastModifiedList(); // Odczytywanie tematów ze strony głównej forum salon.pl
$salonpl->getTopicDetails();