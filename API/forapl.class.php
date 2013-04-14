<?php
/**
 * Fora.pl API v0.3.0
 *
 * @author Jakub Konefał <jakub.konefal@studio85.pl>
 * @copyright Copyright (c) 2010-2013, Jakub Konefał
 * @link http://api.studio85.pl/
 */

if ( !defined('IN_API') ) exit("Hacking attempt");
if ( !defined('PATH') ) exit("Where's that script?!");
require_once(PATH."mainapi.class.php");

setlocale(LC_TIME, "pl_PL");

class ForaPL extends MainApi {

  private $_expTag = array();


  function __construct() {
    $this->clearData( true );
    $this->fileConfig = 'forapl.config.php';
    $this->setConvertData("ISO-8859-2","UTF-8");
    // Regular Expression Tags
    $this->_expTag['A'] = "<a[^']+'([^']+)','','POST','([^']+)[^>]+>([^<]+)</a>";
    $this->_expTag['P'] = "<p[^>]*>([^<]+)</p>";
    $this->_expTag['SPAN'] = "<span[^>]*>([^<]+)</span>";
    $this->_expTag['P_SPAN'] = "<p[^>]*>".$this->_expTag['SPAN']."</p>";
    $this->_expTag['P_A'] = "<p[^>]*>".$this->_expTag['A']."</p>";
    $this->_expTag['LI_P'] = "<li[^>]*>(<p class=\"[a-z]+\">.+</p>)</li>";
    $this->_expTag['MENU'] = "<li[^']+'([^']+)[^>]+>([^<]+)</a></li>";

    $this->_expTag['CATEGORIES'] = '<span class="cattitle"><a href="([^"]+)" class="cattitle">([^>]+)</a></span>';
    $this->_expTag['FORUMS'] = '<span class="forumlink">[^<]*<a href="([^"]+)" class="forumlink">([^>]+)</a><br />[^<]*</span>[^<]*<span class="genmed">([^<]*)<br />[^<]*</span>';
    $this->_expTag['TOPICS'] = '<span class="topictitle">[^<]*<a href="([^"]+)" class="topictitle">([^>]+)</a>[^<]*</span>';
    $this->_expTag['TOPICS_MOVED'] = '<span class="topictitle"><b>([^:]*):</b>[^<]*<a href="([^"]+)" class="topictitle">([^>]+)</a>[^<]*</span>';
    $this->_expTag['TOPICS_DETAILS'] = '<td[^>]*><span class="postdetails">([0-9]+)</span></td>[^<]*<td[^>]*><span class="name"><a href=".*u=([0-9]+)">[^<]*</a></span></td>[^<]*<td[^>]*><span class="postdetails">([0-9]+)</span></td>';
    $this->_expTag['PROFILES'] = '<a href="([^"]+)"><img src="[^"]+" alt="Zobacz profil autora"[^>]+></a>';
    $this->_expTag['POSTS'] = '<a href="[^\#]+\#([^"]+)"><img src="[^"]+" width="[0-9]+" height="[0-9]+" alt="Post"[^>]+></a><span class="postdetails">[^:]*: ([^<]*)<span class="gen">[^<]*</span>[^:]*: ([^<]*)</span>';
    $this->_expTag['TOPIC_PAGES'] = 'pl/[^,]+,[0-9]+/[^,]+,[0-9]+-([0-9]+).html';
    $this->_expTag['FORUM_PAGES'] = 'pl/[^,]+,[0-9]+-([0-9]+)/';
  }

  public function clearData( $all=false) {
    unset($this->config['username']);
    unset($this->config['password']);
    if( $all ) {
      $this->config = array();
      $this->cookies = array();
      $this->resHeader = '';
      $this->resContent = '';
    }
  }

  private function _phpsessid() {
    foreach( $this->cookies as $k=>$v ) {
      $exp = explode("=",$v);
      if( IsSet($exp[1]) && preg_match('#_sid$#i',$exp[0]) ) {
        $this->phpsessid = $exp[1];
        return true;
      }
    }
    $this->phpsessid = '';
    return false;
  }

  private function _parseUsersList() {
    preg_match_all('#<option value="([^"]+)">([^<]+)</option>#imsU', $this->resContent, $matches);
    unset($matches[0]);
    //$this->debugResponse("_parseUsersList() :: matches",$matches,true,true);
    $this->usersList = ( IsSet($matches[1]) && count($matches[1])>0 ) ? $matches[1] : array() ;
    //$this->debugResponse("_parseUsersList() :: usersList",$this->usersList,true,true);
  }

  private function _parseUserData() {
    $this->userData = array();
    // Inputs
    preg_match_all('#<input[^>]+>#imsU',$this->resContent,$inputs);
    //$this->debugResponse("_parseUserData() :: inputs",$inputs,true,true);
    if( IsSet($inputs[0]) && count($inputs[0]) ) {
      foreach($inputs[0] as $k=>$input) {
        preg_match('#type="([^"]+)"#imsU',$input,$type);
        preg_match('#name="([^"]+)"#imsU',$input,$name);
        preg_match('#value="([^"]+)"#imsU',$input,$value);
        preg_match('#checked="([^"]+)"#imsU',$input,$checked);
        $this->_parseUserDataFormInput( $type[1] , $name[1] , $value[1] , $checked[1] );
      }
    }
    // Textareas
    preg_match_all('#<textarea([^>]*)>(.*)</textarea>#imsU',$this->resContent,$textareas);
    //$this->debugResponse("_parseUserData() :: textareas",$textareas,true,true);
    if( IsSet($textareas[2]) && count($textareas[2]) ) {
      foreach($textareas[1] as $k=>$textarea) {
        preg_match('#name="([^"]+)"#imsU',$textarea,$name);
        if( IsSet($name[1]) ) {
          $this->userData[ $name[1] ] = $textareas[2][$k];
        }
      }
    }
    // Selects
    preg_match_all('#<select name="([^"]+)"[^>]*>(.*)</select>#imsU',$this->resContent,$selects);
    //$this->debugResponse("_parseUserData() :: selects",$selects,true,true);
    if( IsSet($selects[2]) && count($selects[2]) ) {
      foreach($selects[2] as $k=>$select) {
        preg_match_all('#<option([^>]+)>([^<]+)</option>#imsU',$select,$options);
        if( IsSet($options[1]) && count($options[1]) ) {
          $notSelectedOption = '';
          $selectedOption = '';
          foreach($options[1] as $op=>$ov) {
            preg_match('#value="([^"]+)"#imsU',$ov,$value);
            preg_match('#selected="([^"]+)"#imsU',$ov,$selected);
            if( $op == 0 ) $notSelectedOption = $value[1];
            if( IsSet($selected[1]) && !empty($selected[1]) ) {
              $selectedOption = IsSet($value[1]) ? $value[1] : '';
            }
          }
          $this->userData[ $selects[1][$k] ] = ($selectedOption=='') ? $notSelectedOption : $selectedOption ;
        }
      }
    }
    $this->debugResponse("_parseUserData() :: userData",$this->userData,false,false);
    return true;
  }

  private function _parseUserDataFormInput( $type , $name , $value , $checked=false ) {
    switch($type) {
      case 'text':
        $this->userData[$name] = $value; break;
      case 'password':
        $this->userData[$name] = $value; break;
      case 'radio':
        if($checked=='checked') $this->userData[$name] = $value; break;
      case 'checkbox':
        break;
      case 'hidden':
        $this->userData[$name] = $value; break;
      case 'submit':
        break;
      case 'reset':
        break;
    }
    return false;
  }

  private function _parseCategoriesAndForums() {
    // Parser
    preg_match_all('#('.$this->_expTag['CATEGORIES'].')|('.$this->_expTag['FORUMS'].')#imsU', $this->resContent, $matches);
    unset($matches[0],$matches[1],$matches[4]);
    //$this->debugResponse("_parseCategoriesList() :: matches",$matches,true,true);
    $this->categoriesList = array();
    $this->forumsList = array();
    $categoryOldId = 0;
    if( IsSet($matches[7]) && count($matches[7])>0 ) {
      foreach($matches[2] as $k=>$v) {
        // Categories
        if( preg_match("#\?c=([0-9]+)#i",$matches[2][$k],$c) && IsSet($c[1]) ) {
          $categoryOldId = $c[1];
          $this->categoriesList[ $c[1] ] = array(
            'oldId' => $c[1]
            ,'newId' => 0
            ,'title' => $matches[3][$k]
          );
        }
        // Forums
        else if( empty($matches[2][$k]) ) {
          if( preg_match("#pl/([^,]+),([0-9]+)/#i",$matches[5][$k],$f) && IsSet($f[2]) ) {
            $this->forumsList[ $f[2] ] = array(
              'oldId' => $f[2]
              ,'newId' => 0
              ,'title' => $matches[6][$k]
              ,'subtitle' => $matches[7][$k]
              ,'seoUrl' => $f[1]
              ,'categoryOldId' => $categoryOldId
            );
          }
        }
        else {
        }
      }
    }
    // Sort categories
    $newId=1;
    //uasort($this->categoriesList,array($this,'_sortByTitle'));
    foreach($this->categoriesList as $k=>$v) {
      $this->categoriesList[$k]['newId'] = $newId++;
    }
    // Sort forums
    //uasort($this->forumsList,array($this,'_sortByTitle'));
    foreach($this->forumsList as $k=>$v) {
      $this->forumsList[$k]['newId'] = $newId++;
    }
  }


  private function _parseTopics( $fId ) {
    // Parser
    preg_match_all('#'.$this->_expTag['TOPICS_DETAILS'].'#imsU', $this->resContent, $details);
    unset($details[0]);
    //$this->debugResponse("_parseTopics() :: details",$details,true,true);
    preg_match_all('#('.$this->_expTag['TOPICS'].')|('.$this->_expTag['TOPICS_MOVED'].')#imsU', $this->resContent, $matches);
    unset($matches[0],$matches[1],$matches[4]);
    //$this->debugResponse("_parseTopics() :: matches",$matches,true,true);
    if( IsSet($matches[7]) && count($matches[7])>0 ) {
      foreach($matches[2] as $k=>$v) {
        if( !empty($v) ) {
          // Topic
          if( preg_match("#pl/[^,]+,[0-9]+/([^,]+),([0-9]+).html#i",$matches[2][$k],$t) && IsSet($t[2]) ) {
            if( IsSet($this->topicsList[ $t[2] ]) ) continue;
            $this->topicsList[ $t[2] ] = array(
              'oldId' => $t[2]
              ,'newId' => 0
              ,'title' => $matches[3][$k]
              ,'seoUrl' => $t[1]
              ,'forumOldId' => $fId
              ,'topic_replies' => ( isset($details[1][$k]) ? $details[1][$k] : 0 )
              ,'topic_poster' => ( isset($details[2][$k]) ? $details[2][$k] : 0 )
              ,'topic_views' => ( isset($details[3][$k]) ? $details[3][$k] : 0 )
            );
          }
        }
        else {
          // Topic moved
          if( preg_match("#[a-z]/[^,]+,([0-9]+)/([^,]+),([0-9]+).html#i",$matches[6][$k],$t) && IsSet($t[3]) ) {
            $this->topicsList[ $t[3] ] = array(
              'oldId' => $t[3]
              ,'newId' => 0
              ,'title' => $matches[7][$k]
              ,'seoUrl' => $t[2]
              ,'forumOldId' => $fId
              ,'topic_replies' => ( isset($details[1][$k]) ? $details[1][$k] : 0 )
              ,'topic_poster' => ( isset($details[2][$k]) ? $details[2][$k] : 0 )
              ,'topic_views' => ( isset($details[3][$k]) ? $details[3][$k] : 0 )
            );
            if( $f['oldId'] != $t[1] ) {
              $this->topicsList[ $t[3] ]['moved'] = 1;
              $this->topicsList[ $t[3] ]['movedForumId'] = $t[1];
              $this->topicsList[ $t[3] ]['movedNewId'] = 0;
            }
            else if( $matches[5][$k] == 'Ogłoszenie' ) {
              $this->topicsList[ $t[3] ]['announce'] = 1;
            }
            else {
              $this->topicsList[ $t[3] ]['sticky'] = 1;
            }
          }
        }
        //$this->debugResponse("_parseTopics() :: topicsList",$this->topicsList,true,true);
      }
    }
  }

  private function _sortByTitle( $a , $b ) {
    return strcmp( $a['title'] , $b['title'] );
  }

  private function _sortTopics() {
    // Sort topics
    $newId=1;
    uasort($this->topicsList,array($this,'_sortByTitle'));
    foreach($this->topicsList as $k=>$v) {
      $this->topicsList[$k]['newId'] = $newId++;
      if( IsSet($this->topicsList[$k]['moved']) ) {
        $this->topicsList[$k]['movedNewId'] = $newId++;
      }
    }
  }

  private function _parseForumPages() {
    // Parser
    preg_match_all('#'.$this->_expTag['FORUM_PAGES'].'#imsU', $this->resContent, $matches);
    $this->forumPages = array();
    if( isset($matches[1]) && count($matches[1]) ) {
      $this->forumPages = array_unique($matches[1]);
    }
    //$this->debugResponse("_parseForumPages() :: forumPages",$this->forumPages,true,true);
    return true;
  }

  private function _parseTopicPages() {
    // Parser
    preg_match_all('#'.$this->_expTag['TOPIC_PAGES'].'#imsU', $this->resContent, $matches);
    $this->topicPages = array();
    if( isset($matches[1]) && count($matches[1]) ) {
      $this->topicPages = array_unique($matches[1]);
    }
    //$this->debugResponse("_parseTopicPages() :: topicPages",$this->topicPages,true,true);
    return true;
  }

  private function _parsePosts( $tId ) {
    // Parser
    preg_match_all('#('.$this->_expTag['PROFILES'].')|('.$this->_expTag['POSTS'].')#imsU', $this->resContent, $matches);
    unset($matches[0],$matches[1],$matches[3]);
    //$this->debugResponse("_parsePosts() :: matches",$matches,true,true);
    if( IsSet($matches[6]) && count($matches[6])>0 ) {
      $lastId = 0;
      foreach($matches[4] as $k=>$v) {
        if( empty($v) && ($lastId > 0) ) {
          if( preg_match("#u=([0-9]+)#i",$matches[2][$k],$u) && IsSet($u[1]) ) {
            $this->postsList[ $tId ][ $lastId ]['userOldId'] = $u[1];
          }
          else {
            $this->postsList[ $tId ][ $lastId ]['userOldId'] = 1; // Anonymous
          }
        }
        else {
          $lastId = $matches[4][$k];
          $this->postsList[ $tId ][ $lastId ] = array(
            'oldId' => $lastId
            ,'newId' => 0
            ,'date' => $this->parseDateToTimestamp( $matches[5][$k] )
            ,'topicOldId' => $tId
          );
        }
      }
    }
    //$this->debugResponse("_parsePosts() :: postsList",$this->postsList[ $tId ],false,false);
  }

  public function getUserColour( $userlevel ) {
    $colour = '';
    switch( $userlevel ) {
      case 'admin':
        $colour = 'AA0000';
        break;
      case 'moderator':
        $colour = '00AA00';
        break;
    }
    return $colour;
  }

  private function _parsePostDetails( $fId , $tId , $pId , $postCounter , $postCountAll ) {
    preg_match('#<input.*name="username".*value="([^"]*)"[^>]*>#imsU',$this->resContent,$parsedUsername);
    $anonymous = isset($parsedUsername[1]) ? trim($parsedUsername[1]) : '';
    if( empty($anonymous) ) { $anonymous = 'Gość'; }
    //$this->debugResponse("_parseUserData() :: parsedUsername",$parsedUsername,true,true);
    preg_match('#<input.*name="subject".*value="([^"]*)"[^>]*>#imsU',$this->resContent,$parsedSubject);
    $subject = isset($parsedSubject[1]) ? trim($parsedSubject[1]) : '';
    //$this->debugResponse("_parseUserData() :: parsedSubject",$parsedSubject,true,true);
    preg_match('#<textarea[^>]*>(.*)</textarea>#imsU',$this->resContent,$parsedMessage);
    $message = isset($parsedMessage[1]) ? trim($parsedMessage[1]) : '';
    //$this->debugResponse("_parseUserData() :: parsedMessage",$parsedMessage,true,true);
    strlen($subject)." :: ".strlen($message)."<BR>";;
    $this->postsList[$tId][$pId]['subject'] = $subject;
    $this->postsList[$tId][$pId]['message'] = $message;
    //$this->debugResponse("_parseUserData() :: postsList[{$tId}][{$pId}]",$this->postsList[$tId][$pId],true,true);
    $post = $this->postsList[$tId][$pId];
    $username = $this->usersData[ $post['userOldId'] ]['details']['username'];
    if( isset($parsedUsername[1]) ) {
      $this->postsList[$tId][$pId]['userOldId'] = 1;
      $this->postsList[$tId][$pId]['userName'] = $anonymous;
      $post['userOldId'] = 1;
      $username = $anonymous;
    }
    if( !isset($this->postsList[$tId][$pId]['userOldId']) ) {
      $this->postsList[$tId][$pId]['userOldId'] = 1;
      $this->postsList[$tId][$pId]['userName'] = 'Gość';
    }
    $user_colour = $this->getUserColour( $this->usersData[ $post['userOldId'] ]['rights']['userlevel'] );
    if( $post['date']<=0 ) { echo "BRAK DATY !"; echo "<pre>"; print_r($this->post); exit; }
    if( $postCounter == 1 ) {
      $this->forumTopics[$fId][$tId]['topic_poster'] = $post['userOldId'];
      $this->forumTopics[$fId][$tId]['topic_time'] = $post['date'];
      $this->forumTopics[$fId][$tId]['topic_first_post_id'] = $pId;
      $this->forumTopics[$fId][$tId]['topic_first_poster_name'] = $username;
      $this->forumTopics[$fId][$tId]['topic_first_poster_colour'] = $user_colour;
      if( isset($this->forumTopics[$fId][$tId]['movedForumId']) ) {
        $fNewId = $this->forumTopics[$fId][$tId]['movedForumId'];
        $this->forumTopics[$fNewId][$tId]['topic_poster'] = $post['userOldId'];
        $this->forumTopics[$fNewId][$tId]['topic_time'] = $post['date'];
        $this->forumTopics[$fNewId][$tId]['topic_first_post_id'] = $pId;
        $this->forumTopics[$fNewId][$tId]['topic_first_poster_name'] = $username;
        $this->forumTopics[$fNewId][$tId]['topic_first_poster_colour'] = $user_colour;
      }
    }
    if( $postCounter == $postCountAll ) {
      $this->forumTopics[$fId][$tId]['topic_last_post_id'] = $pId;
      $this->forumTopics[$fId][$tId]['topic_last_poster_id'] = $post['userOldId'];
      $this->forumTopics[$fId][$tId]['topic_last_poster_name'] = $username;
      $this->forumTopics[$fId][$tId]['topic_last_poster_colour'] = $user_colour;
      $this->forumTopics[$fId][$tId]['topic_last_post_subject'] = $subject;
      $this->forumTopics[$fId][$tId]['topic_last_post_time'] = $post['date'];
      $this->forumTopics[$fId][$tId]['topic_last_view_time'] = $post['date'];
      if( isset($this->forumTopics[$fId][$tId]['movedForumId']) ) {
        $fNewId = $this->forumTopics[$fId][$tId]['movedForumId'];
        $this->forumTopics[$fNewId][$tId]['topic_last_post_id'] = $pId;
        $this->forumTopics[$fNewId][$tId]['topic_last_poster_id'] = $post['userOldId'];
        $this->forumTopics[$fNewId][$tId]['topic_last_poster_name'] = $username;
        $this->forumTopics[$fNewId][$tId]['topic_last_poster_colour'] = $user_colour;
        $this->forumTopics[$fNewId][$tId]['topic_last_post_subject'] = $subject;
        $this->forumTopics[$fNewId][$tId]['topic_last_post_time'] = $post['date'];
        $this->forumTopics[$fNewId][$tId]['topic_last_view_time'] = $post['date'];
      }
    }
    //$this->debugResponse("_parsePostDetails() :: forumTopics",$this->forumTopics,true,false);
    //$this->debugResponse("_parsePostDetails() :: postsList",$this->postsList,true,true);
    return true;
  }



  public function loginAsUser() {
    $this->readConfigFile();
    $this->setConnectionInfo( '' , 'www.'.$this->config['account'].'.fora.pl' , 80 , 'http://www.'.$this->config['account'].'.fora.pl' );
    if( empty($this->config['username']) || empty($this->config['password']) ) {
      exit("Nieprawidłowy login lub hasło");
    }
    $this->ignoreCookies = 0;
    $addParams = 'username='.$this->encodeUrlData($this->config['username']).'&password='.$this->encodeUrlData($this->config['password']).'&redirect=admin%2Findex.php&login=Zaloguj';
    $this->sendToHostAndDisconnect( '/login.php' , '/login.php?redirect=admin/index.php' , $addParams );
    $this->_phpsessid();
    $this->ignoreCookies = 1;
  }


  public function loginAsAdmin() {
    $this->ignoreCookies = 0;
    $addParams = 'username='.$this->encodeUrlData($this->config['username']).'&password='.$this->encodeUrlData($this->config['password']).'&redirect=admin%2Findex.php%3Fadmin%3D1&admin=1&login=Zaloguj';
    $this->sendToHostAndDisconnect( '/login.php' , '/login.php?redirect=admin/index.php&admin=1&sid='.$this->phpsessid , $addParams );
    $this->_phpsessid();
    $this->ignoreCookies = 1;
  }


  public function setDefaultStyle( $defaultStyle='subSilver' ) {
    $this->sendToHostAndDisconnect( '/admin/admin_board.php?sid='.$this->phpsessid , '/admin/index.php?pane=left&sid='.$this->phpsessid );
    preg_match('#<select name="default_style">(.*)</select>#imsU',$this->resContent,$select);
    if( IsSet($select[1]) && count($select[1]) ) {
      preg_match_all('#<option value="([^"]+)"[^>]*>([^<]+)</option>#imsU',$select[1],$options);
      if( IsSet($options[2]) && count($options[2]) ) {
        foreach($options[2] as $k=>$v) {
          if( $v == $defaultStyle ) {
            $addParams = 'default_style='.$options[1][$k].'&submit=Wy%B6lij';
            $this->sendToHostAndDisconnect( '/admin/admin_board.php?sid='.$this->phpsessid , '/admin/admin_board.php?sid='.$this->phpsessid , $addParams );
            return true;
          }
        }
      }
    }
    exit("Default style hasn't been changed! There's no style <i>{$defaultStyle}</i> in the configuration!");
  }


  public function getUsersList( $userNewId=2 ) {
    if( ($this->usersData = $this->readArrayFromTempFile('usersData')) && is_array($this->usersData) && count($this->usersData)>0 ) {
      return true;
    }
    // Download everything about users
    $this->sendToHostAndDisconnect( '/search.php?mode=searchuser' , '/search.php?mode=searchuser' , 'search_username=*&search=Szukaj' );
    $this->_parseUsersList();
    $this->debugResponse("getUsersList() :: usersList",$this->usersList,false,false);
    $this->usersData = array();
    // Anonymous
    $this->usersData[ 1 ] = array(
      'details' => array( 'username' => 'Gość' )
      ,'rights' => array( 'userlevel' => 'Anonymous' )
      ,'oldId' => 1
      ,'newId' => 1
    );
    foreach($this->usersList as $user) {
      // user_details
      $this->sendToHostAndDisconnect( '/admin/admin_users.php?sid='.$this->phpsessid , '/admin/admin_users.php?sid='.$this->phpsessid , 'username='.$this->encodeUrlData($user).'&mode=edit&submituser=Poka%BF+u%BFytkownika' );
      if( !$this->_parseUserData() || empty($this->userData['id']) ) continue;
      $this->usersData[ $this->userData['id'] ]['details'] = $this->userData;
      $this->usersData[ $this->userData['id'] ]['oldId'] = $this->userData['id'];
      $this->usersData[ $this->userData['id'] ]['newId'] = $userNewId++;
      // user_page
      $this->sendToHostAndDisconnect( '/profile.php?mode=viewprofile&u='.$this->userData['id'] , '/memberlist.php' );
      preg_match('#<b><span[^>]*>([0-9]{1,2} [a-z]{3} [0-9]{4})</span></b>#imsU',$this->resContent,$match);
      $this->usersData[ $this->userData['id'] ]['page']['regdate'] = IsSet($match[1]) ? $this->parseDateToTimestamp($match[1]) : time();
      // user_rights
      $this->sendToHostAndDisconnect( '/admin/admin_ug_auth.php?sid='.$this->phpsessid , '/admin/admin_ug_auth.php?mode=user&sid='.$this->phpsessid , 'username='.$this->encodeUrlData($user).'&mode=edit&mode=user&submituser=Wybierz+U%BFytkownika' );
      if( !$this->_parseUserData() || empty($this->userData['u']) ) continue;
      $this->usersData[ $this->userData['u'] ]['rights'] = $this->userData;
    }
    $this->saveArrayToTempFile( 'usersData' , $this->usersData );
    $this->debugResponse("getUsersList() :: usersData",$this->usersData,false,false);
  }

  public function getCategoriesForumsTopicsPosts() {
    // Categories and forums
    if( ( ($this->categoriesList = $this->readArrayFromTempFile('categoriesList')) && is_array($this->categoriesList) && count($this->categoriesList)>0 ) && ( ($this->forumsList = $this->readArrayFromTempFile('forumsList')) && is_array($this->forumsList) && count($this->forumsList)>0 ) ) { } else {
      $this->sendToHostAndDisconnect( '/' , '/' );
      $this->_parseCategoriesAndForums();
      $this->saveArrayToTempFile( 'categoriesList' , $this->categoriesList );
      $this->saveArrayToTempFile( 'forumsList' , $this->forumsList );
    }
    //$this->debugResponse("getCategoriesAndForums() :: categoriesList",$this->categoriesList,true,true);
    //$this->debugResponse("getCategoriesAndForums() :: forumsList",$this->forumsList,true,true);
    // Topics
    if( ($this->topicsList = $this->readArrayFromTempFile('topicsList')) && is_array($this->topicsList) && count($this->topicsList)>0 ) { } else {
      $this->topicsList = array();
      foreach($this->forumsList as $fId=>$f) {
        $this->sendToHostAndDisconnect( '/'.$f['seoUrl'].','.$f['oldId'].'/' , '/' );
        $this->_parseForumPages();
        $this->_parseTopics( $f['oldId'] );
        if( count($this->forumPages) ) {
          foreach($this->forumPages as $kk=>$vv) {
            $this->sendToHostAndDisconnect( '/'.$f['seoUrl'].','.$f['oldId'].'-'.$vv.'/' , '/' );
            $this->_parseTopics( $f['oldId'] );
          }
        }
      }
      $this->_sortTopics();
      $this->saveArrayToTempFile( 'topicsList' , $this->topicsList );
    }
    //$this->debugResponse("getCategoriesAndForums() :: topicsList",$this->topicsList,true,true);
    // Topics for Forum
    if( ($this->forumTopics = $this->readArrayFromTempFile('forumTopics')) && is_array($this->forumTopics) && count($this->forumTopics)>0 ) { } else {
      $this->forumTopics = array();
      foreach( $this->topicsList as $k=>$v ) {
        $this->forumTopics[ $v['forumOldId'] ][ $v['oldId'] ] = $v;
        if( IsSet($v['moved']) && ($v['moved'] == 1) ) {
          $this->forumTopics[ $v['movedForumId'] ][ $v['oldId'] ] = array(
            'oldId' => $v['oldId']
            ,'newId' => $v['movedNewId']
            ,'title' => $v['title']
            ,'seoUrl' => $v['seoUrl']
            ,'forumOldId' => $v['movedForumId']
            ,'topic_replies' => 0
            ,'topic_poster' => $v['topic_poster']
            ,'topic_views' => $v['topic_views']
          );
        }
      }
      $this->saveArrayToTempFile( 'forumTopics' , $this->forumTopics );
    }
    //$this->debugResponse("getCategoriesAndForums() :: forumTopics",$this->forumTopics,true,true);
    // Posts
    if( ($this->postsList = $this->readArrayFromTempFile('postsList')) && is_array($this->postsList) && count($this->postsList)>0 ) { } else {
      $this->postsList = array();
      foreach($this->topicsList as $tId=>$t) {
        $fUrl = '/'.$this->forumsList[ $t['forumOldId'] ]['seoUrl'].','.$t['forumOldId'];
        $this->sendToHostAndDisconnect( $fUrl.'/'.$t['seoUrl'].','.$t['oldId'].'.html' , $fUrl.'/' );
        $this->_parseTopicPages();
        $this->_parsePosts( $t['oldId'] );
        if( count($this->topicPages) ) {
          foreach($this->topicPages as $kk=>$vv) {
            $this->sendToHostAndDisconnect( $fUrl.'/'.$t['seoUrl'].','.$t['oldId'].'-'.$vv.'.html' , $fUrl.'/' );
            $this->_parsePosts( $t['oldId'] );
          }
        }
        //$this->debugResponse("getCategoriesForumsTopicsPosts() :: postsList",$this->postsList,true,true);
        // Post details
        if( $postCountAll = count($this->postsList[ $t['oldId'] ]) ) {
          $postCounter = 1;
          foreach($this->postsList[ $t['oldId'] ] as $pId=>$p) {
            $this->sendToHostAndDisconnect( '/posting.php?mode=editpost&p='.$p['oldId'] , $fUrl.'/'.$t['seoUrl'].','.$t['oldId'].'.html' );
            $this->_parsePostDetails( $t['forumOldId'] , $t['oldId']  , $p['oldId'] , $postCounter++ , $postCountAll );
          }
        }
        //$this->debugResponse("getCategoriesForumsTopicsPosts() :: postsList",$this->postsList,true,false);
        //$this->debugResponse("getCategoriesForumsTopicsPosts() :: forumTopics",$this->forumTopics,true,true);
        //break;
      }
      $this->saveArrayToTempFile( 'postsList' , $this->postsList );
      $this->saveArrayToTempFile( 'forumTopics' , $this->forumTopics );
    }
    // New id for posts
    $newId = 1;
    $this->counter['posts'] = 0;
    foreach($this->postsList as $tId=>$t) {
      foreach($t as $pId=>$p) {
        $this->postsList[$tId][$p['oldId']]['newId'] = $newId++;
        $this->counter['posts']++;
      }
    }
    // Counting arrays
    $this->counter['users'] = count($this->usersData);
    $this->counter['categories'] = count($this->categoriesList);
    $this->counter['forums'] = count($this->forumsList);
    $this->counter['topics'] = 0;
    foreach($this->forumTopics as $fId=>$f) {
      foreach($f as $tId=>$t) {
        $this->counter['topics']++;
      }
    }
    // Debug
    $this->debugResponse("getCategoriesForumsTopicsPosts() :: postsList",$this->postsList,false,false);
    $this->debugResponse("getCategoriesForumsTopicsPosts() :: forumTopics",$this->forumTopics,false,false);
  }

  public function displayCounter() {
    $this->debugResponse(
      "displayCounter()"
      ,"<b>Users:</b> {$this->counter['users']}<br>"
      ."<b>Categories:</b> {$this->counter['categories']}<br>"
      ."<b>Forums:</b> {$this->counter['forums']}<br>"
      ."<b>Topics:</b> {$this->counter['topics']}<br>"
      ."<b>Posts:</b> {$this->counter['posts']}<br>"
      ,true
      ,false
    );
  }

} // Class