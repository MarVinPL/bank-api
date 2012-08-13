<?php
define('IN_API', TRUE);
define('PATH', dirname($_SERVER['SCRIPT_FILENAME']).'/');
set_time_limit(0);

header('Content-Type: text/html; charset=utf-8');
require_once(PATH."index.login.php");
require_once(PATH."forapl.class.php");
require_once(PATH."forapl.mysql.php");

$forapl = new ForaPL();
$forasql = new ForaPLMySQL();
/*
$forapl->saveConfigFile(array(
  'account' =>'api85'
  ,'username'=>'api'
  ,'password'=>'***'
));
$forapl->saveConfigFile(array(
  'account' =>'httpwwwdziecibciplstronystowar'
  ,'username'=>'Agnieszka'
  ,'password'=>'***'
));
*/
$forapl->debug = false;
$forapl->loginAsUser();
$forapl->loginAsAdmin();
//$forapl->setDefaultStyle();

// Clear the temp file arrays
$forapl->clearTemp = false;
$forapl->clearTempFile('usersData',$forapl->clearTemp);
$forapl->clearTempFile('categoriesList',$forapl->clearTemp);
$forapl->clearTempFile('forumsList',$forapl->clearTemp);
$forapl->clearTempFile('topicsList',$forapl->clearTemp);
$forapl->clearTempFile('forumTopics',$forapl->clearTemp);
$forapl->clearTempFile('postsList',$forapl->clearTemp);

// Read data from host
$forapl->getUsersList(2);
$forapl->getCategoriesForumsTopicsPosts();

// Display counter
$forapl->displayCounter();
exit;

// Transform to SQL
$forasql->usersData = &$forapl->usersData;
$forasql->categoriesList = &$forapl->categoriesList;
$forasql->forumsList = &$forapl->forumsList;
$forasql->topicsList = &$forapl->topicsList;
$forasql->forumTopics = &$forapl->forumTopics;
$forasql->postsList = &$forapl->postsList;
$forasql->counter = &$forapl->counter;

// SQL
$forasql->generateConfigSQL();
$forasql->generateUsersSQL();
$forasql->generateCategoryAndForumSQL();
$forasql->generateTopicSQL();
$forasql->generatePostSQL();
echo "<br><br><br>";