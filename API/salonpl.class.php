<?php
/**
 * Salon.pl API v0.2.0
 *
 * @author Jakub Konefał <jakub.konefal@studio85.pl>
 * @copyright Copyright (c) 2010-2013, Jakub Konefał
 * @link http://api.studio85.pl/
 */

if (!defined('IN_API')) exit("Hacking attempt");
if (!defined('PATH')) exit("Where's that script?!");
require_once(PATH . "mainapi.class.php");

setlocale(LC_TIME, "pl_PL");

class SalonPL extends MainApi
{

    public $fileConfig = 'salonpl.config.php';

    /**
     * Login as user by ID
     *
     * @param $userId
     */
    function getUserByID($userId)
    {
        global $db, $user, $auth;
        $result = $db->sql_query('SELECT * FROM ' . USERS_TABLE . ' WHERE user_id = ' . $userId);
        $user->data = $db->sql_fetchrow($result);
        $db->sql_freeresult($result);
        $user->ip = '0.0.0.0';
        $auth->acl($user->data);
    }

    function readForumTopics()
    {
        global $db, $user;
        $this->forumTopics = array();
        $result = $db->sql_query('SELECT t.forum_id, t.topic_id, t.topic_title, t.topic_time FROM ' . TOPICS_TABLE . ' t'
            . ' WHERE t.forum_id = ' . $this->forumId . ' AND t.topic_poster = ' . $user->data['user_id'] . ' AND t.topic_status <> ' . ITEM_MOVED . ' AND t.topic_approved = 1 ORDER BY t.topic_id DESC');
        while ($row = $db->sql_fetchrow($result)) {
            $row['salonTopicId'] = preg_match('# \[Salon\.pl\#([0-9]+)\]#', $row['topic_title'], $match) && isset($match[1]) ? $match[1] : 0;
            $row['salonTopicTitle'] = preg_replace('# \[Salon.pl[^\]]*]#', '', $row['topic_title']);
            $row['firstPostId'] = 0;
            $row['firstSalonPostId'] = 0;
            $row['lastPostId'] = 0;
            $row['lastSalonPostId'] = 0;
            $row['posts'] = array();
            $this->forumTopics[$row['topic_id']] = $row;
        }
        $db->sql_freeresult($result);
        $this->debugResponse("readForumTopics() :: \$this->forumTopics", $this->forumTopics, false, false);
    }

    function readForumTopicPosts()
    {
        global $db, $user;
        if (count($this->forumTopics) > 0) {
            $result = $db->sql_query('SELECT p.forum_id, p.topic_id, p.post_id, p.post_time, p.post_subject, p.post_text FROM ' . POSTS_TABLE . ' p'
                . ' WHERE p.topic_id IN (' . implode(', ', array_keys($this->forumTopics)) . ') AND p.poster_id = ' . $user->data['user_id'] . ' ORDER BY p.post_id DESC');
            while ($row = $db->sql_fetchrow($result)) {
                $row['salonPostId'] = preg_match('#/forum/post/([0-9]+)\#p#', $row['post_text'], $match) && isset($match[1]) ? $match[1] : 0;
                $row['salonPostDate'] = preg_match('#\](\d+\-\d+\-\d+ \d+:\d+)\[/b#', $row['post_text'], $match) && isset($match[1]) ? strtotime($match[1]) : 0;
                $row['salonPostTitle'] = preg_replace('# \[Salon.pl[^\]]*]#', '', $row['post_subject']);
                unset($row['post_text']);
                $this->forumTopics[$row['topic_id']]['posts'][$row['salonPostId']] = $row;
            }
            $db->sql_freeresult($result);

            // Odczytanie pierwszego i ostatniego klucza z forum
            foreach ($this->forumTopics as &$forumTopic) {
                $postKeys = array_keys($forumTopic['posts']);
                reset($postKeys);
                $lastKey = $postKeys[key($postKeys)];
                $forumTopic['lastPostId'] = $forumTopic['posts'][$lastKey]['post_id'];
                $forumTopic['lastSalonPostId'] = $forumTopic['posts'][$lastKey]['salonPostId'];
                end($postKeys);
                $firstKey = $postKeys[key($postKeys)];
                $forumTopic['firstPostId'] = $forumTopic['posts'][$firstKey]['post_id'];
                $forumTopic['firstSalonPostId'] = $forumTopic['posts'][$firstKey]['salonPostId'];
            }

            $this->debugResponse("readForumTopicPosts() :: \$this->forumTopics", $this->forumTopics, false, false);
        }
    }

    /**
     * Create new post/topic
     *
     * From: http://wiki.phpbb.com/Using_phpBB3%27s_Basic_Functions#1.4.7._Inserting_Posts_and_Private_Messages
     *
     * @param $forumId
     * @param $topicId
     * @param $subject
     * @param $postAuthor
     * @param $postTimestamp
     * @param $postText
     * @param int $salonTopicId
     * @param int $topicViews
     * @param int $salonPostId
     * @return array
     */
    function submitPost($forumId, $topicId, $salonTopicId, $salonPostId, $subject, $postAuthor, $postTimestamp, $postText, $topicViews = 0)
    {
        global $db, $auth;
        $this->debugResponse("submitPost() :: func_get_args()", func_get_args(), false, false);

        // defaults
        $poll = $uid = $bitfield = $options = '';
        $mode = empty($topicId) ? 'post' : 'reply';

        if (($mode == 'post' && !$auth->acl_get('f_post', $forumId)) || ($mode == 'reply' && !$auth->acl_get('f_reply', $forumId))) {
            return false;
        }

        // Ustawienie domyślnego tytułu i treści
        $topicSubject = utf8_normalize_nfc("{$subject} [Salon.pl#{$salonTopicId}]");
        $postSubject = utf8_normalize_nfc((empty($topicId) ? '' : 'Re: ') . "{$subject} [Salon.pl#{$salonPostId}]");
        $postUrl = "http://salon.pl/{$this->config['account']}/forum/post/{$salonPostId}#p{$salonPostId}";
        $newText = utf8_normalize_nfc("[quote=&quot;{$postAuthor}&quot;]{$postText}[/quote]\n[size=85]Powyższa treść została skopiowana ze strony: [url={$postUrl}]salon.pl[/url][/size]");

        generate_text_for_storage($topicSubject, $uid, $bitfield, $options, false, false, false);
        generate_text_for_storage($postSubject, $uid, $bitfield, $options, false, false, false);
        generate_text_for_storage($newText, $uid, $bitfield, $options, true, true, true);

        // Ustawienie opcji postu/tematu
        $data = array(
            'forum_id' => $forumId,
            'topic_id' => $topicId,
            'icon_id' => false,
            'enable_bbcode' => true,
            'enable_smilies' => false,
            'enable_urls' => true,
            'enable_sig' => false,
            'message' => $newText,
            'message_md5' => md5($newText),
            'bbcode_bitfield' => $bitfield,
            'bbcode_uid' => $uid,
            'post_edit_locked' => 1,
            'topic_title' => $topicSubject,
            'notify_set' => false,
            'notify' => false,
            'post_time' => 0,
            'forum_name' => '',
            'enable_indexing' => true
        );

        // Wysyłanie postu/tematu
        submit_post($mode, $postSubject, '', POST_NORMAL, $poll, $data);
        if (!isset($data['post_id'])) {
            exit('Nie udało się utworzyć tematu/postu:<br><pre>' . print_r($data, true));
        }

        // Aktualizacja ilości odwiedzin oraz rzeczywistego tytułu tematu
        if ($mode == 'post') {
            $db->sql_query("UPDATE " . POSTS_TABLE . " SET post_time = {$postTimestamp} WHERE post_id = {$data['post_id']}");
            $db->sql_query("UPDATE " . TOPICS_TABLE . " SET topic_title = '{$topicSubject}', topic_views = {$topicViews}, topic_time = {$postTimestamp}, topic_last_post_time = {$postTimestamp} WHERE topic_id = {$data['topic_id']}");
            $db->sql_query("UPDATE " . FORUMS_TABLE . " SET forum_last_post_time = {$postTimestamp} WHERE forum_id = {$data['forum_id']}");
            echo "Utworzono temat \"{$subject}\" (PID: {$data['post_id']}) w forum (FID: {$data['forum_id']})<br>";
        } else {
            $db->sql_query("UPDATE " . POSTS_TABLE . " SET post_time = {$postTimestamp} WHERE post_id = {$data['post_id']}");
            $db->sql_query("UPDATE " . TOPICS_TABLE . " SET topic_last_post_time = {$postTimestamp} WHERE topic_id = {$data['topic_id']}");
            $db->sql_query("UPDATE " . FORUMS_TABLE . " SET forum_last_post_time = {$postTimestamp} WHERE forum_id = {$data['forum_id']}");
            echo "Utworzono post \"{$subject}\" (TID: {$data['topic_id']} | PID: {$data['post_id']}) w forum (FID: {$data['forum_id']})<br>";
        }

        return array(
            'forumId' => $data['forum_id'],
            'topicId' => $data['topic_id'],
            'postId' => $data['post_id']
        );
    }

    public function clearData($all = false)
    {
        unset($this->config['username']);
        unset($this->config['password']);
        if ($all) {
            $this->config = array();
            $this->cookies = array();
            $this->resHeader = '';
            $this->resContent = '';
        }
    }

    private function _phpsessid()
    {
        foreach ($this->cookies as $k => $v) {
            $exp = explode("=", $v);
            if (IsSet($exp[1]) && preg_match('#_sid$#i', $exp[0])) {
                $this->phpsessid = $exp[1];
                return true;
            }
        }
        $this->phpsessid = '';
        return false;
    }

    private function _parseTimestamp($dateString)
    {
        $timestamp = null;

        if (preg_match("#teraz|przed chwil.*#i", $dateString)) {
            $timestamp = now();
        }

        if (preg_match("#([0-9]*).*sekund.* temu#i", $dateString, $match)) {
            $seconds = empty($match[1]) ? 1 : $match[1];
            $timestamp = strtotime("-{$seconds} second");
            return empty($formatDate) ? $timestamp : date($formatDate, $timestamp);
        }

        if (preg_match("#([0-9]*).*minut.* temu#i", $dateString, $match)) {
            $minutes = empty($match[1]) ? 1 : $match[1];
            $timestamp = strtotime("-{$minutes} minute");
            return empty($formatDate) ? $timestamp : date($formatDate, $timestamp);
        }

        if (preg_match("#o godz. ([0-9]+):([0-9]+)#i", $dateString, $match)) {
            $timestamp = mktime($match[1], $match[2]);
            return empty($formatDate) ? $timestamp : date($formatDate, $timestamp);
        }

        if (preg_match("#wczoraj o ([0-9]+):([0-9]+)#i", $dateString, $match)) {
            $timestamp = strtotime("{$match[1]}:{$match[2]} -1 day");
            return empty($formatDate) ? $timestamp : date($formatDate, $timestamp);
        }

        if (preg_match("#([0-9]+) dni temu o ([0-9]+):([0-9]+)#i", $dateString, $match)) {
            $timestamp = strtotime("{$match[2]}:{$match[3]} -{$match[1]} day");
            return empty($formatDate) ? $timestamp : date($formatDate, $timestamp);
        }

        if (preg_match("#([0-9]+)\.([0-9]+)\.([0-9]+) ([0-9]+):([0-9]+)#i", $dateString, $match)) {
            $timestamp = mktime($match[4], $match[5], null, $match[2], $match[1], $match[3]);
            return empty($formatDate) ? $timestamp : date($formatDate, $timestamp);
        }

        if (is_null($timestamp)) {
            exit($dateString);
        }

//        exit($dateString . "<br>" . date("Y-m-d H:i:s", $timestamp));

        return $timestamp;
    }

    public function loginAsUser()
    {
        $this->readConfigFile();
        $this->setConnectionInfo('', 'salon.pl', 80, 'http://salon.pl');
        if (empty($this->config['username']) || empty($this->config['password'])) {
            exit("Nieprawidłowy login lub hasło");
        }
        $this->ignoreCookies = 0;
        $addParams = 'auth_mail=' . $this->encodeUrlData($this->config['username']) . '&auth_password=' . $this->encodeUrlData($this->config['password']) . '&go=Logowanie...&doAuth=1&backUrl=http%3A%2F%2Fsalon.pl%2F&do_auth=1&send=1';
        $this->sendToHostAndDisconnect('/login.html', '/login.html?backUrl=http%3A%2F%2Fsalon.pl%2F' . $this->config['account'] . '%2Fforum.html', $addParams);
        $this->_phpsessid();
        $this->ignoreCookies = 1;
    }

    /**
     * Odczytanie listy podstron z tematami forum
     *
     * @return bool
     */
    private function _parseForumPagesList()
    {
        $this->forumPages = array();
        preg_match('#<div class="nav"><b class="box" title="1/([0-9]+)">1</b>#i', $this->resContent, $match);
        $forumPages = isset($match[1]) ? $match[1] : 1;
        for ($i = 2; $i <= $forumPages; $i++) {
            $this->forumPages[] = '/' . $this->config['account'] . '/forum/' . $i;
        }
        $this->debugResponse("_parseForumPagesList() :: \$this->forumPages", $this->forumPages, false, false);
    }

    /**
     * Odczytanie listy tematów z podstrony forum
     */
    private function _parseForumPagesTopicList()
    {
        $topicIds = array();

        preg_match_all('#<a href="(/' . $this->config['account'] . '/forum/temat/([0-9]+)[^"]+)"[ ]+title="Zobacz temat">([^<]+)</a>#', $this->resContent, $topics);
        preg_match_all('#<a href="(/' . $this->config['account'] . '/forum/temat/([0-9]+)/)[0-9]+([^"]+)"[ ]+title="1/([0-9]+)">#', $this->resContent, $topicPages);
        preg_match_all('#<a href="/' . $this->config['account'] . '/forum/post/([0-9]+)[^"]+">([^<]+)</a>#', $this->resContent, $posts);
        $this->debugResponse("_parseForumPagesTopicList() :: \$topics", $topics[2], false, false);
        $this->debugResponse("_parseForumPagesTopicList() :: \$topicPages", $topicPages, false, false);
        $this->debugResponse("_parseForumPagesTopicList() :: \$posts", $posts[2], false, false);

        if (!isset($topics[2]) || !isset($posts[2]) || count($topics[2]) !== count($posts[2])) {
            exit('Błąd przetwarzania danych tematów/postów');
        }

        // Odczytanie danych dotyczących tematów
        foreach ($topics[2] as $k => $tid) {
            $topicIds[$tid] = array(
                'topicId' => $tid,
                'topicTitle' => trim($topics[3][$k]),
                'topicViews' => 0,
                'lastPostId' => $posts[1][$k],
                'lastPostDate' => $this->_parseTimestamp($posts[2][$k]),
                'pageNum' => 1,
                'pageUrls' => array($topics[1][$k])
            );
        }

        // Odczytanie ilości podstron dla tematów
        foreach ($topicPages[2] as $k => $tid) {
            $topicIds[$tid]['pageNum'] = $topicPages[4][$k];
            for ($i = 2; $i <= $topicPages[4][$k]; $i++) {
                $topicIds[$tid]['pageUrls'][] = $topicPages[1][$k] . $i . $topicPages[3][$k];
            }
        }
        $this->debugResponse("_parseForumPagesTopicList() :: \$topicIds", $topicIds, false, false);
        $this->debugResponse("_parseForumPagesTopicList() :: \$this->forumTopics", $this->forumTopics, false, false);

        // Sprawdzenie, które dane należy zsynchronizować
        foreach ($this->forumTopics as $tid => &$tArray) {
            $salonTopicId = $tArray['salonTopicId'];
            if (isset($topicIds[$salonTopicId]) && ($topicIds[$salonTopicId]['lastPostId'] == $tArray['lastSalonPostId'])) {
                unset($topicIds[$salonTopicId]);
            }
        }
        // Ustawienie tematów do synchronizacji
        foreach ($topicIds as $tid => &$tArray) {
            $this->topicSync[$tid] = $tArray;
        }
        $this->debugResponse("_parseForumPagesTopicList() :: \$this->topicSync", $this->topicSync, false, false);
    }

    private function _parseForumTopicPosts(&$topicArray, $pageKey = 0)
    {
        $postIds = array();
        $topicArray['topicViews'] = preg_match('#Odsłony: <b>([0-9]+)</b>#', $this->resContent, $match) && isset($match[1]) ? $match[1] : 0;

        preg_match_all('#<span class="nick"><a href="/profil/([0-9]+)">([^<]+)</a></span>#', $this->resContent, $postNickIds);
        preg_match_all('#<span class="fl">[^<]*<a href="/' . $this->config['account'] . '/forum/post/([0-9]+)[^>]+>([^<]+)</a>[^<]*</span>#', $this->resContent, $postDateIds);
        preg_match_all('#<p class="post">(.*)</p>[^<]*</td>[^<]*</tr>[^<]*<tr>#Us', $this->resContent, $postTexts);

        if (!isset($postNickIds[2]) || !isset($postDateIds[2]) || !isset($postTexts[1]) || count($postNickIds[2]) !== count($postDateIds[2]) || count($postNickIds[2]) !== count($postTexts[1])) {
            exit('Błąd przetwarzania treści postów');
        }

        foreach ($postTexts[1] as $k => $text) {
            $postId = $postDateIds[1][$k];
            $postIds[$postId] = array(
                'postId' => $postId,
                'postTime' => $this->_parseTimestamp($postDateIds[2][$k]),
                'nickId' => $postNickIds[1][$k],
                'nickName' => $postNickIds[2][$k],
                'postText' => trim(strip_tags(preg_replace('#<div class="gr s mt20">.*</div>#', '', $text)))
            );
        }
        $this->debugResponse("_parseForumTopicPosts() :: \$postIds", $postIds, false, false);

        $forumTopicId = null;
        foreach ($this->forumTopics as $tid => &$tArray) {
            if ($topicArray['topicId'] == $tArray['salonTopicId']) {
                $forumTopicId = $tid;
                break;
            }
        }

        if (is_null($forumTopicId)) {
            reset($postIds);
            $firstPostKey = key($postIds);
            $ret = $this->submitPost($this->forumId, 0, $topicArray['topicId'], $postIds[$firstPostKey]['postId'], $topicArray['topicTitle'], $postIds[$firstPostKey]['nickName'], $postIds[$firstPostKey]['postTime'], $postIds[$firstPostKey]['postText'], $topicArray['topicViews']);
            unset($postIds[$firstPostKey]);
            $forumTopicId = $ret['topicId'];
            $this->readForumTopics(); // Odczytywanie tematów z forum
            $this->readForumTopicPosts(); // Odczytywanie postów z forum
        } else {
            foreach ($this->forumTopics[$forumTopicId]['posts'] as $pid => &$pArray) {
                if (isset($postIds[$pid])) {
                    unset($postIds[$pid]);
                }
            }
        }

        foreach ($postIds as $pid => &$pArray) {
            $ret = $this->submitPost($this->forumId, $forumTopicId, $topicArray['topicId'], $pArray['postId'], $topicArray['topicTitle'], $pArray['nickName'], $pArray['postTime'], $pArray['postText'], $topicArray['topicViews']);
        }
    }

    /**
     * Odczytanie listy ostatnio zmodyfikowanych tematów forum
     */
    public function getLastModifiedList()
    {
        if( ($this->topicSync = $this->readArrayFromTempFile('topicSync')) && is_array($this->topicSync) && count($this->topicSync)>0 ) {
            return true;
        }
        $this->topicSync = array();
        $this->sendToHostAndDisconnect('/' . $this->config['account'] . '/forum.html', '/' . $this->config['account'] . '/forum.html');
        $this->_parseForumPagesList();
        $this->_parseForumPagesTopicList();
        $this->_getMoreForumPages();
        $this->debugResponse("getLastModifiedList() :: \$this->topicSync", $this->topicSync, false, false);
        $this->saveArrayToTempFile('topicSync', $this->topicSync);
    }

    /**
     * Odczytanie listy tematów z pozostałych podstron forum
     */
    private function _getMoreForumPages()
    {
        $this->debugResponse("getMoreForumPages() :: \$this->topicSync", $this->topicSync, false, false);
        if (count($this->topicSync) == 20) {
            foreach ($this->forumPages as $forumPage) {
                $this->sendToHostAndDisconnect($forumPage, $forumPage);
                $this->_parseForumPagesTopicList();
            }
            ksort($this->topicSync);
            $this->debugResponse("getMoreForumPages() :: \$this->topicSync", $this->topicSync, false, false);
        }
    }

    /**
     * Odczytanie szczegółów dotyczących tematów forum
     */
    public function getTopicDetails()
    {
        $counter = 10;
        if (count($this->topicSync) > 0) {
            ksort($this->topicSync);
            foreach ($this->topicSync as $tid=>&$topicArray) {
                if($counter-- <= 0) {
                    exit;
                }
                foreach ($topicArray['pageUrls'] as $pageKey => $url) {
                    $this->sendToHostAndDisconnect($url, $url);
                    $this->_parseForumTopicPosts($topicArray, $pageKey);
                }
                unset($this->topicSync[$tid]);
                $this->saveArrayToTempFile('topicSync', $this->topicSync);
            }
        }
    }

} // Class