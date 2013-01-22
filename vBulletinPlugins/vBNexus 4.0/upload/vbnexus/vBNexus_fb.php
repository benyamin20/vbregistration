<?php

require_once(dirname(__FILE__) . '/vBNexus_user.php');
require_once(VBNEXUS_DIR . '/facebook-php-sdk/facebook.php');


class vBNexus_fb extends vBNexus_user {

    public function getServiceName() {
        return 'fb';
    }

    /**
     * Returns the id of the user currently online, or 0 if none
     */
    public function getUserOnline() {
        return $this->getFB()->getUser();
    }

    public function getUserData() {
        $data = $this->readGraph();
        if(!$data) return array();

        // Fix a few keys to fit the keys expected in returned array
        $data['service'] = 'Facebook';
        $data['userid'] = $data['id'];
        $data['profile_url'] = $data['link'];
        $data['avatar'] = "https://graph.facebook.com/{$data['id']}/picture";

        return $data;
    }

    private function exec_fql($fql) {
        try {
            $response = $this->getFB()->api(array(
                    'access_token' => $this->getFB()->getAccessToken(),
                    'method'       => 'fql.query',
                    'query'        => $fql,
            ));
        } catch(FacebookApiException $e) {
            // A user can be logged in on facebook and the application but still
            // the access_token can become invalid (expired or user changed
            // the permissions granted to the application from facebook.com)
            return false;
        }

        return $response;
    }

    public function readGraph($query='') {
        try {
            $access_token = $this->getFB()->getAccessToken();
            $url = "https://graph.facebook.com/me{$query}?access_token={$access_token}";
            return @json_decode(file_get_contents($url), true);
        } catch(FacebookApiException $e) {
            // A user can be logged in on facebook and the application but still
            // the access_token can become invalid (expired or user changed
            // the permissions granted to the application from facebook.com)
            return array();
        }
    }

    /**
     * Initializes a Facebook object (Facebook's PHP-SDK class) and returns it
     * Future calls get the chached object to avoid unnecessary processing
     */
    private function getFB() {
        static $FB;

        if (!$FB) {
            $vBNexus = vBNexus::getInstance();
            $FB = new Facebook(array(
                'appId'  => $vBNexus->getConfig('fb_appId'),
                'secret' => $vBNexus->getConfig('fb_secret'),
                'cookie' => true,
            ));
        }

        return $FB;
    }

    public function hasFeedOptions() {
        return true;
    }

    public function canPublish() {
        $permissions = $this->readGraph('/permissions');
        return ($permissions && !empty($permissions['data'][0]['publish_stream']));
    }

    /**
     * Adds the HTML of publish options in the template named $template, by
     * calling preg_replace with the given parameters. $replacement must have
     * the string '$options' in it.
     *
     * @param string $template      Name of the template to write the options into
     * @param string $start         First parameter for preg_replace
     * @param string $replacement   Second parameter for preg_replace
     */
    public function addFeedOptions($event) {
        global $vbulletin, $vbphrase;

        switch ($event) {
            case 'newthread':           // newthread_form_complete
                $template = 'newthread';
                $pattern = '/\<\!\-\- \/ message area \-\-\>/';
                $replacement = '\\0$options';
                break;
            case 'newpost':             // newreply_form_complete
                $template = 'newreply';
                $pattern = '/\<\!\-\- \/ message area \-\-\>/';
                $replacement = '\\0$options';
                break;
            case 'quickreply':          // showthread_complete
                $template = 'SHOWTHREAD';
                $pattern = '/\<fieldset class=\\\"fieldset\\\"/';
                $replacement = '$options\\0';
                break;
            case 'newalbum':            // album_album_edit
                $template = 'album_edit';
                $pattern = '/\<\/td\>\<\/tr\>\<\/table\>/';
                $replacement = '$options\\0';
                break;
            case 'newphoto':            // album_picture_add
                $template = 'album_picture_upload';
                $pattern = '/"\.\(\(\$show\[\'limit_info\'\]\) \? \("/';
                $replacement = '$options\\0';
                break;
            default:
                return false;
        }

        // Whether to have checkbox checked by default or not
        // Override admin option if user already checked/unchecked the box
        // (page is reloading, most likely due to an error, i.e. message too short)
        if (!empty($_REQUEST['hdn_vbnexus_publish'])) {
            $checked = $_REQUEST['vbnexus_publish'] ? 'checked="checked"' : '';
        } elseif (vBNexus::getInstance()->getOption('postsfeeds_checked')) {
            $checked = 'checked="checked"';
        }

        // Turn $options in $replacement into the options HTML
        eval('$options = "'.fetch_template('vbnexus_fb_publish_options').'";');
        $replacement = str_replace('$options', addslashes($options), $replacement);

        $subject = $vbulletin->templatecache[$template];
        $vbulletin->templatecache[$template] = preg_replace($pattern, $replacement, $subject);

        return true;
    }

    public function publishFeed($event) {
        global $vbulletin, $newpost, $threadinfo, $albumdata, $albuminfo;

        // These are all valid fields we might use for a feed ('caption' and
        // 'source' are not used)
        $fields = array('message', 'picture', 'link', 'name', 'description');

        $forumurl = $vbulletin->options['bburl'];
        $forumtitle = $vbulletin->options['bbtitle'];

        switch ($event) {
            case 'joined':
                $message = "I joined {$forumtitle}";
                $link = $forumurl;
                $name = $forumtitle;
                break;
            case 'newthread':           // newthread_post_complete
                $message = "I started a new thread in {$forumtitle}";
                $link = $newpost['postpoll']
                    ? "{$forumurl}/poll.php?t={$newpost['threadid']}&polloptions={$newpost['polloptions']}"
                    : "{$forumurl}/showthread.php?p={$newpost['postid']}#post{$newpost['postid']}";
                $name = $newpost['title'];
                $description = strip_tags($newpost['message']);
                break;
            case 'newpost':             // newreply_post_complete
                $message = "I posted a reply in {$forumtitle}";
                $link = "{$forumurl}/{$vbulletin->url}";
                $name = $threadinfo['title'];
                $description = strip_tags($newpost['message']);
                break;
            case 'quickreply':          // newreply_post_ajax
                $message = "I posted a reply in {$forumtitle}";
                $link = "{$forumurl}/{$vbulletin->url}&p={$newpost['postid']}#post{$newpost['postid']}";
                $name = $threadinfo['title'];
                $description = strip_tags($newpost['message']);
                break;
            case 'newalbum':            // albumdata_postsave
                $message = "I created a photo album in {$forumtitle}";
                $link = "{$forumurl}/album.php?albumid={$albumdata->album['albumid']}";
                $name = $albumdata->album['title'];
                break;
            case 'newphoto':            // album_picture_upload_complete
                $message = "I uploaded a photo in {$forumtitle}";
                $query_string = "albumid={$albuminfo['albumid']}&pictureid={$pictureids["{$uploadid}"]}";
                $picture = $link = "{$forumurl}/album.php?{$query_string}";
                $name = $albuminfo['title'];
                break;
            default:
                return false;
        }

        try{
            $token = $this->getFB()->getAccessToken();
            if ($token) {
                $api_url = "/me/feed?access_token={$token}";
                if (strlen($description) > 300) $description = substr($description, 0, 297).'...';
                $result = $this->getFB()->api($api_url, 'POST', compact($fields));
            }
        } catch(FacebookApiException $e) {
            // A user can be logged in on facebook and the application but still
            // the access_token can become invalid (expired or user changed
            // the permissions granted to the application from facebook.com)
            return false;
        }

        return !!$result;
    }

    /**
     * Replace Facebook proxied emails from previous vB Nexus versions with the
     * user's real email address
     */
    protected function fixOldEmail($user) {
        if (!intval($user['userid'])) return false;
        $userid = $user['userid'];

        // No need to validate email, just see if we got an answer from FB
        $data = $this->getUserData();
        if (!$data || !$data['email']) return false;

        $res = $GLOBALS['vbulletin']->db->query_write("
            UPDATE " . TABLE_PREFIX . "user
            SET `email` =  '{$data['email']}'
            WHERE `userid` = '{$userid}'
        ");

        return !!$res;
    }

}