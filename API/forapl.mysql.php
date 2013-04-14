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

class ForaPLMySQL extends ForaPL {

  public $outputSql = false;

  public $bots = array(
    'name' => array(
      1=> 'AdsBot [Google]', 'Alexa [Bot]', 'Alta Vista [Bot]', 'Ask Jeeves [Bot]', 'Baidu [Spider]'
      , 'Exabot [Bot]', 'FAST Enterprise [Crawler]', 'FAST WebCrawler [Crawler]', 'Francis [Bot]', 'Gigabot [Bot]'
      , 'Google Adsense [Bot]', 'Google Desktop', 'Google Feedfetcher', 'Google [Bot]', 'Heise IT-Markt [Crawler]'
      , 'Heritrix [Crawler]', 'IBM Research [Bot]', 'ICCrawler - ICjobs', 'ichiro [Crawler]', 'Majestic-12 [Bot]'
      , 'Metager [Bot]', 'MSN NewsBlogs', 'MSN [Bot]', 'MSNbot Media', 'NG-Search [Bot]'
      , 'Nutch [Bot]', 'Nutch/CVS [Bot]', 'OmniExplorer [Bot]', 'Online link [Validator]', 'psbot [Picsearch]'
      , 'Seekport [Bot]', 'Sensis [Crawler]', 'SEO Crawler', 'Seoma [Crawler]', 'SEOSearch [Crawler]'
      , 'Snappy [Bot]', 'Steeler [Crawler]', 'Synoo [Bot]', 'Telekom [Bot]', 'TurnitinBot [Bot]'
      , 'Voyager [Bot]', 'W3 [Sitesearch]', 'W3C [Linkcheck]', 'W3C [Validator]', 'WiseNut [Bot]'
      , 'YaCy [Bot]', 'Yahoo MMCrawler [Bot]', 'Yahoo Slurp [Bot]', 'Yahoo [Bot]', 'YahooSeeker [Bot]'
    )
  );

  private function _prepareBotSQLTableData( $bot_id , $bot_name , $user_id , $bot_agent , $reg_date ) {
    $bot_name_clean = mb_strtolower($bot_name,'UTF-8');
    return <<<EOF
INSERT INTO `phpbb_bots` VALUES({$bot_id}, 1, '{$bot_name}', {$user_id}, '{$bot_agent}', '');
INSERT INTO `phpbb_users` VALUES({$user_id}, 2, 6, '', 0, '', {$reg_date}, '{$bot_name}', '{$bot_name_clean}', '', {$reg_date}, 0, '', 0, '', 0, {$reg_date}, 0, '', '', 0, 0, 0, 0, 0, 0, 0, 'pl', 0.00, 0, '|j M Y|, \\o H:i', 1, 0, '9E8DA7', 0, 0, 0, 0, -3, 0, 0, 't', 'd', 0, 't', 'a', 0, 1, 0, 1, 1, 1, 0, 230271, '', 0, 0, 0, '', '', '', '', '', '', '', '', '', '', '', '', '', '', '', 0, 0, 0);
INSERT INTO `phpbb_user_group` VALUES(6, {$user_id}, 0, 0);\n
EOF;
  }

  private function _prepareUserSQLTableData() {
    $reg_date = time();
    return <<<EOF
TRUNCATE `phpbb_bots`;
TRUNCATE `phpbb_users`;
TRUNCATE `phpbb_user_group`;
INSERT INTO `phpbb_users` VALUES (1, 2, 1, 0x30303030303030303030336b687261336e6b0a6931636a796f3030303030300a6931636a796f3030303030300a6931636a796f3030303030300a0a6931636a796f303030303030, 0, '', {$reg_date}, 'Anonymous', 'anonymous', '', 0, 0, '', 0, '', 0, 0, 0, '', '', 0, 0, 0, 0, 0, 0, 0, 'en', 0.00, 0, 'd M Y H:i', 1, 0, '', 0, 0, 0, 0, -3, 0, 0, 't', 'd', 0, 't', 'a', 0, 1, 0, 1, 1, 1, 0, 230271, '', 0, 0, 0, '', '', '', '', '', '', '', '', '', '', '', '', '', '', 'e8bd3e97b94f809e', 1, 0, 0);
INSERT INTO `phpbb_user_group` VALUES (1, 1, 0, 0);\n
EOF;
  }

  private function _prepareUserSQL( $id=0 ) {
    if( !IsSet($this->usersData[$id]) ) return '';
    $v = $this->usersData[$id];
    switch( $v['rights']['userlevel'] ) {
      case 'admin':
        $user_type = 3;
        $group_id = 5;
        $user_permissions = '0x7a696b307a6a7a696b307a6a7a696b3078730a6931636a796f3030303030300a7a696b307a6a7a68623274630a6931636a796f3030303030300a0a7a696b307a6a7a6862327463';
        $user_colour = 'AA0000';
        $user_rank = 1;
        $user_group_sql = "INSERT INTO `phpbb_user_group` VALUES (2, {$v['newId']}, 0, 0) , (4, {$v['newId']}, 0, 0) , (5, {$v['newId']}, 1, 0);\n";
        break;
      default:
        $user_type = 0;
        $group_id = 2;
        $user_permissions = '0x3030303030303030303036787271656977770a6931636a796f3030303030300a716c617135323030303030300a6931636a796f3030303030300a0a716c61713532303030303030';
        $user_colour = '';
        $user_rank = 0;
        $user_group_sql = "INSERT INTO `phpbb_user_group` VALUES (2, {$v['newId']}, 0, 0);\n";
        break;
    }
    $user_lang = ($v['details']['language']=='english') ? 'en' : 'pl' ;
    require_once(PATH."forapl.phpbb.php");
    $user_password = phpbb_hash($v['details']['email']);
    //$user_dateformat = ($v['details']['dateformat']=='d M Y h:i a') ? $v['details']['dateformat'] : '|j M Y|, \o H:i' ;
    $user_dateformat = '|j M Y|, \o H:i';
    // Return SQL
    //$this->debugResponse("_prepareUserSQL() :: User array ",$this->usersData[$id],true,false);
    return "INSERT INTO `phpbb_users` ("
      . "`user_id`, `user_type`, `group_id`, `user_permissions`, `user_ip`"
      . ", `user_regdate`, `username`, `username_clean`, `user_password`, `user_email`"
      . ", `user_birthday`, `user_lastpage`, `user_last_confirm_key`, `user_lang`, `user_timezone`"
      . ", `user_dateformat`, `user_rank`, `user_colour`, `user_notify`, `user_notify_pm`"
      . ", `user_sig`, `user_sig_bbcode_uid`, `user_sig_bbcode_bitfield`, `user_from`, `user_icq`"
      . ", `user_aim`, `user_yim`, `user_msnm`, `user_jabber`, `user_website`"
      . ", `user_occ`, `user_interests`, `user_actkey`, `user_newpasswd`, `user_form_salt`"
      . ")\n VALUES ("
      . "{$v['newId']}, {$user_type}, {$group_id}, {$user_permissions}, ''"
      . ", {$v['page']['regdate']}, '{$v['details']['username']}', '".mb_strtolower($v['details']['username'],'UTF-8')."', '{$user_password}', '{$v['details']['email']}'"
      . ", '', '', '', '{$user_lang}', {$v['details']['timezone']}.00"
      . ", '{$user_dateformat}', {$user_rank}, '{$user_colour}', {$v['details']['notifyreply']}, {$v['details']['notifypm']}"
      . ", '{$v['details']['signature']}', '', '', '{$v['details']['location']}', '{$v['details']['icq']}'"
      . ", '{$v['details']['aim']}', '{$v['details']['yim']}', '{$v['details']['msn']}', '', '{$v['details']['website']}'"
      . ", '{$v['details']['occupation']}', '{$v['details']['interests']}', '', '', ''"
    .");\n" . $user_group_sql;
  }

  private function _prepareCategoryAndForumSQL( $forum_old_id , $forum_id , $parent_id , $left_id , $right_id , $forum_name , $forum_desc , $forum_type , $forum_flags=48 ) {
    $sqlColumns = '';
    $sqlData = '';
    if( $forum_type == 1 ) {
      $topic_last_post_time = 0;
      $tmp = array();
      $forum_posts = 0;
      //echo "<pre>"; print_r($this->forumTopics[$forum_old_id]); return;
      if( isset($this->forumTopics[$forum_old_id]) && count($this->forumTopics[$forum_old_id]) ) {
        foreach($this->forumTopics[$forum_old_id] as $tId=>$t) {
          $forum_posts += count($this->postsList[ $tId ]);
          if( $t['topic_last_post_time'] > $topic_last_post_time ) {
            $tmp = $t;
          }
        }
      }
      if( isset($tmp['topic_last_post_time']) ) {
        $forum_topics = count($this->forumTopics[$forum_old_id]);
        $sqlColumns = ", `forum_posts`, `forum_topics`, `forum_topics_real`, `forum_last_post_id`, `forum_last_poster_id`"
          . ", `forum_last_post_subject`, `forum_last_post_time`, `forum_last_poster_name`, `forum_last_poster_colour`";
        $sqlData = ", {$forum_posts}, {$forum_topics}, {$forum_topics}, {$tmp['topic_last_post_id']}, {$tmp['topic_last_poster_id']}"
          . ", '{$tmp['topic_last_post_subject']}', {$tmp['topic_last_post_time']}, '{$tmp['topic_last_poster_name']}', '{$tmp['topic_last_poster_colour']}'";
      }
    }
    return <<<EOF
INSERT INTO `phpbb_forums` (`forum_id`, `parent_id`, `left_id`, `right_id`, `forum_name`, `forum_desc`, `forum_type`, `forum_flags`{$sqlColumns})
 VALUES ({$forum_id}, {$parent_id}, {$left_id}, {$right_id}, '{$forum_name}', '{$forum_desc}', {$forum_type}, {$forum_flags}{$sqlData});
INSERT INTO `phpbb_acl_groups` VALUES(1, {$forum_id}, 0, 18, 0);
INSERT INTO `phpbb_acl_groups` VALUES(2, {$forum_id}, 0, 21, 0);
INSERT INTO `phpbb_acl_groups` VALUES(4, {$forum_id}, 0, 20, 0);
INSERT INTO `phpbb_acl_groups` VALUES(5, {$forum_id}, 0, 14, 0);
INSERT INTO `phpbb_acl_groups` VALUES(6, {$forum_id}, 0, 19, 0);
INSERT INTO `phpbb_acl_groups` VALUES(7, {$forum_id}, 0, 18, 0);\n
EOF;
  }

  public function generateUsersSQL() {
    if( empty($this->usersData) ) {
      exit("There's no users on this board!");
    }
    $this->sql = $this->_prepareUserSQLTableData();
    //$this->debugResponse("generateUsersSQL() :: usersData",$this->usersData,true,true);
    foreach( $this->usersData as $id=>$user ) {
      if( $id < 2 ) continue;
      $this->sql .= $this->_prepareUserSQL($id);
    }
    echo "<pre>{$this->sql}</pre>";
  }

  public function generateCategoryAndForumSQL() {
    if( empty($this->categoriesList) ) {
      exit("There's no categories on this board!");
    }
    $this->sql = <<<EOF
TRUNCATE `phpbb_forums`;
TRUNCATE `phpbb_forums_access`;
TRUNCATE `phpbb_forums_track`;
TRUNCATE `phpbb_forums_watch`;
TRUNCATE `phpbb_acl_groups`;
INSERT INTO `phpbb_acl_groups` VALUES(4, 0, 0, 5, 0);
INSERT INTO `phpbb_acl_groups` VALUES(4, 0, 0, 10, 0);
INSERT INTO `phpbb_acl_groups` VALUES(5, 0, 0, 4, 0);
INSERT INTO `phpbb_acl_groups` VALUES(5, 0, 0, 5, 0);
INSERT INTO `phpbb_acl_groups` VALUES(5, 0, 0, 10, 0);
INSERT INTO `phpbb_acl_groups` VALUES(7, 0, 0, 6, 0);\n
EOF;
    $tmpTree = array();
    foreach( $this->forumsList as $k=>$v ) {
      $tmpTree[ $v['categoryOldId'] ][] = $v;
    }
    //$this->debugResponse("generateCategoryAndForumSQL() :: tmpTree",$tmpTree,true,true);
    $left_id = 0;
    $right_id = 0;
    foreach( $this->categoriesList as $cId=>$c ) {
      $left_id = $right_id + 1;
      $right_id = $left_id + 1;
      $right_id_all = $right_id + 2*count( $tmpTree[$c['oldId']] );
      $this->sql .= $this->_prepareCategoryAndForumSQL( $c['oldId'] , $c['newId'] , 0 , $left_id , $right_id_all , $c['title'] , '' , 0 );
      $left_id--;
      foreach( $tmpTree[$c['oldId']] as $k=>$f ) {
        $left_id += 2;
        $right_id = $left_id + 1;
        $this->sql .= $this->_prepareCategoryAndForumSQL( $f['oldId'] , $f['newId'] , $c['newId'] , $left_id , $right_id , $f['title'] , $f['subtitle'] , 1 );
      }
      $right_id = $right_id_all;
    }
    echo "<pre>{$this->sql}</pre>";
  }

  public function generateTopicSQL() {
    if( empty($this->forumTopics) ) {
      exit("There's no forum topics on this board!");
    }
    $this->sql = <<<EOF
TRUNCATE `phpbb_topics`;
TRUNCATE `phpbb_topics_posted`;
TRUNCATE `phpbb_topics_track`;
TRUNCATE `phpbb_topics_watch`;\n
EOF;
    foreach( $this->forumTopics as $fId=>$topics ) {
      foreach( $topics as $k=>$t ) {
        $forum_id = $this->forumsList[ $t['forumOldId'] ]['newId'];
        $topic_status = 0;
        $topic_moved_id = 0;
        if( IsSet($t['moved']) && ($t['moved']==1) ) {
          $topic_status = 2;
          $topic_moved_id = $t['movedNewId'];
        }
        $topic_poster = $this->usersData[ $t['topic_poster'] ]['newId'];
        $topic_first_post_id = $this->postsList[ $t['oldId'] ][ $t['topic_first_post_id'] ]['newId'];
        $topic_poster = $this->usersData[ $t['topic_poster'] ]['newId'];
        $topic_last_post_id = $this->postsList[ $t['oldId'] ][ $t['topic_last_post_id'] ]['newId'];
        $topic_last_poster_id = $this->usersData[ $t['topic_last_poster_id'] ]['newId'];
        $this->sql .= "INSERT INTO `phpbb_topics` ("
          . "`topic_id`, `forum_id`, `topic_title`, `topic_poster`, `topic_time`"
          . ", `topic_views`, `topic_replies`, `topic_replies_real`, `topic_status`, `topic_first_post_id`"
          . ", `topic_first_poster_name`, `topic_first_poster_colour`, `topic_last_post_id`, `topic_last_poster_id`, `topic_last_poster_name`"
          . ", `topic_last_poster_colour`, `topic_last_post_subject`, `topic_last_post_time`, `topic_last_view_time`, `topic_moved_id`"
          . ")\n VALUES ("
          . "{$t['newId']}, {$forum_id}, '{$t['title']}', {$topic_poster}, {$t['topic_time']}"
          . ", {$t['topic_views']}, {$t['topic_replies']}, {$t['topic_replies']}, {$topic_status}, {$topic_first_post_id}"
          . ", '{$t['topic_first_poster_name']}', '{$t['topic_first_poster_colour']}', {$topic_last_post_id}, {$topic_last_poster_id}, '{$t['topic_last_poster_name']}'"
          . ", '{$t['topic_last_poster_colour']}', '{$t['topic_last_post_subject']}', {$t['topic_last_post_time']}, {$t['topic_last_view_time']}, {$topic_moved_id}"
          . ");\n";
        if( isset($t['topic_first_post_id']) ) {
          $this->sql .= "INSERT INTO `phpbb_topics_posted` (`user_id`, `topic_id`, `topic_posted`) VALUES ({$topic_poster}, {$t['newId']}, 1);\n";
        }
      }
    }
    echo "<pre>{$this->sql}</pre>";
  }

  public function generatePostSQL() {
    if( empty($this->postsList) ) {
      exit("There's no forum topics on this board!");
    }
    $this->sql = <<<EOF
TRUNCATE `phpbb_posts`;\n
EOF;
    foreach( $this->postsList as $tId=>$t ) {
      foreach( $t as $pId=>$p ) {
        $forumOldId = $this->topicsList[ $tId ]['forumOldId'];
        $topic_id = $this->topicsList[ $tId ]['newId'];
        if( isset($this->topicsList[ $tId ]['movedNewId']) ) {
          $forumOldId = $this->topicsList[ $tId ]['movedForumId'];
          $topic_id = $this->topicsList[ $tId ]['movedNewId'];
        }
        $forum_id = $this->forumsList[ $forumOldId ]['newId'];
        $poster_id = $this->usersData[ $p['userOldId'] ]['newId'];
        $post_username = ($p['userOldId']==1) ? $p['userName'] : $this->postsList[ $p['userOldId'] ]['details']['username'];
        $post_subject = str_replace("'","\'",$p['subject']);
        $post_text = str_replace("'","\'",$p['message']);
        $post_checksum = md5($post_text);
        $this->sql .= "INSERT INTO `phpbb_posts` ("
          . "`post_id`, `topic_id`, `forum_id`, `poster_id`, `post_time`"
          . ", `post_username`, `post_subject`, `post_text`, `post_checksum`"
          . ")\n VALUES ("
          . "{$p['newId']}, {$topic_id}, {$forum_id}, {$poster_id}, {$p['date']}"
          . ", '{$post_username}', '{$post_subject}', '{$post_text}', '{$post_checksum}'"
          . ");\n";
      }
    }
    echo "<pre>{$this->sql}</pre>";
  }

  public function generateConfigSQL() {
    $this->sql = <<<EOF
UPDATE phpbb_config SET config_value=0 WHERE config_name='num_files';
UPDATE phpbb_config SET config_value={$this->counter['posts']} WHERE config_name='num_posts';
UPDATE phpbb_config SET config_value={$this->counter['topics']} WHERE config_name='num_topics';
UPDATE phpbb_config SET config_value={$this->counter['users']} WHERE config_name='num_users';\n
EOF;
    echo "<pre>{$this->sql}</pre>";
  }

} // Class