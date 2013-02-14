<?php

require_once(DIR . '/includes/functions_login.php');


class vBNexus_user {

    /**
     * These methods should be implemented in children classes
     */
    public function getUserData(){ return false; }
    public function hasFeedOptions() { return false; }
    public function addFeedOptions() { return false; }
    public function canPublish() { return false; }
    protected function fixOldEmail() { return false; }
    protected function associateAccount() { return false; }

    /**
     * Validates login status on external service and logs in vBulletin
     */
    public function login() {
        global $vbulletin;

        $vbulletin->session = NULL;

        // Get and store vbnexus-id and vbnexus-srv
        $vBNexus = vBNexus::getInstance();
        $vbnexus_service = $vBNexus->getConfig('vbnexus_service');
        $vbnexus_userid = $vBNexus->getConfig('vbnexus_userid');

        if (!$vbnexus_userid) {
            $vbnexus_userid = $this->getUserOnline();
            $vBNexus->setConfig('vbnexus_userid', $vbnexus_userid);
        }

        // Returning null if authentication from service failed (unexpected error)
        // If this happens, there's likely cookies issues on the server or the
        // applications config is wrong/incomplete in fb or gfc
        if (!$vbnexus_userid) return NULL;

        // Get all available information on this user
        $sql = "SELECT `u`.`usergroupid`,
                       `u`.`username`,
                       `u`.`email`,
                       `n`.*
                FROM " . TABLE_PREFIX . "vbnexus_user `n`
                LEFT JOIN " . TABLE_PREFIX . "user `u` USING (`userid`)
                WHERE `n`.`service` = '{$vbnexus_service}'
                AND `n`.`nonvbid` = '{$vbnexus_userid}'";
        $res = $vbulletin->db->query_first($sql);

        // Returning false if user not registered yet with this external account
        if (!$res || !$res['userid']) return false;

        /************* Starts: fix proxied emails from vBNexus3 ***************/
        $oldemails = array('fb'  => '/@proxymail\.facebook\.com$/',
                           'gfc' => "/apps\+|{$vbnexus_userid}[@\.]/");
        if (preg_match($oldemails[$vbnexus_service], $res['email'])) {
            $this->fixOldEmail($res, $vbnexus_userid);
        }
        /*************** Ends: fix proxied emails from vBNexus3 ***************/
        /********* Starts: ask for a valid password for GFC accounts **********/
        elseif ($vbnexus_service == 'gfc' && !$res['associated']) {
            $this->associateAccount($res, $vbnexus_userid);
        }
        /********** Ends: ask for a valid password for GFC accounts ***********/

        // Process vBulletin login
        require_once(DIR . '/includes/functions_login.php');
        $vbulletin->userinfo = fetch_userinfo($res['userid']);
        $vbulletin->session->created = false;
        process_new_login('', false, '');

        // On login, store a cookie with vbnexus params
        if ($vbulletin->session->created) {
            $vBNexusInfo = array(
                'userid'      => $res['userid'],
                'service'     => $vbnexus_service,
                'nexusid'     => $vbnexus_userid,
                'can_publish' => $this->canPublish(),
            );
            setcookie(COOKIE_PREFIX . 'vbnexus', serialize($vBNexusInfo));
        }

        return !!$vbulletin->session->created;
    }

    public function register($data) {
        global $vbulletin;

        $vb_userid = NULL;

        // Validate (returns a phrase key on error, or true on success)
        $valid = $this->validateRegistration($data);
        
        if ($valid !== true) return $valid;

        // Get user id from database or create a new one, depending on registration type
        if ($data['type'] == 'new') {
            // Create new vb user and return true or an error string/phrase
            $userCreated = $this->createUser($data, $vb_userid);    // $vb_userid byRef
            if ($userCreated !== true) return $userCreated;
        } else {
            // Validate credentials if linking to an existing account
            $userExists = verify_authentication($data['username'], $data['password'], '', '', '', '');
            // If it succeeded, $vbulletin->userinfo is now populated
            $vb_userid = $vbulletin->userinfo['userid'];
        }

        $service = $data['service'];
        $nonvbid = $data['userid'];
        $associated = ($data['type'] == 'link') ? '1' : '0';

        if (!$vb_userid) {
            if ($data['type'] == 'link') {
                return 'vbnexus_registration_wrong_credentials';
            } else {
                return 'vbnexus_registration_linking_failed';
            }
        }

        // Insert new entry in vbnexus_user
        $sql = "INSERT INTO `" . TABLE_PREFIX . "vbnexus_user`
                (`service`, `nonvbid`, `userid`, `associated`)
                VALUES ('{$service}', '{$nonvbid}', '{$vb_userid}', '{$associated}')
                ON DUPLICATE KEY UPDATE `userid` = '{$vb_userid}', `associated` = '{$associated}'";
        $vbulletin->db->query_write($sql);

        return $vbulletin->db->errno() ? 'vbnexus_registration_linking_failed' : true;
    }

    protected function validateRegistration($data) {
        global $vbulletin;

        // Stop new user registration if allowregistration option set to false
        if ($data['type'] == 'new' && !$vbulletin->options['allowregistration']) {
            eval(standard_error(fetch_error('noregister')));
        }

        // Validate integrity of input
        if (!$data['type'] || !$data['service'] || $data['service'] != $this->getServiceName()) { 
            return 'vbnexus_registration_failed';
        }
        if (!$data['username']) {
            return 'vbnexus_invalid_login';
        }
        if ($data['type'] == 'link' && !$data['password']) {
            return 'vbnexus_invalid_login';
        }
        if ($data['type'] == 'new' && !$data['email']) {
            return 'vbnexus_invalid_login';
        }

        // For validation of 'new' usernames, use vB built-in function
        if ($data['type'] == 'new') {
            $userdata =& datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
            $userdata->verify_username(htmlspecialchars_uni($data['username']));
            if ($userdata->has_errors(false)) {     // $die := false
                return join('</li><li>', $userdata->errors);
            }
        }

        return true;
    }

    /**
     * For registration without existing account, create a new vb user
     * If a user is successfully created, her userid is written to $userid
     */
    private function createUser($data, &$userid) {
        global $vbulletin;

        $moderated = $vbulletin->options['moderatenewmembers'];
        $languageid = $vbulletin->userinfo['languageid'];

        $require_activation = ($vbulletin->options['verifyemail']
                           && ($data['default_email'] != $data['coded_email']));

        // Create a vB user with default permissions -- code from register.php
        if (!$vbulletin->options['allowregistration']) {
            eval(standard_error(fetch_error('noregister')));
        }

        // Init user datamanager class
        $userdata =& datamanager_init('User', $vbulletin, ERRTYPE_SILENT);
        $userdata->set_info('coppauser', false);
        $userdata->set_info('coppapassword', '');
        $userdata->set_bitfield('options', 'coppauser', '');
        $userdata->set('username', $data['username']);
        $userdata->set('password', md5($this->genPasswd()));
        $userdata->set('email', $data['email']);
        $userdata->set('birthday', $data['birthday']);
        $userdata->set('languageid', $languageid);
        $userdata->set('ipaddress', IPADDRESS);

        // UserGroupId: Registered Users (2) or Users Awaiting Email Confirmation (3)
        $userdata->set('usergroupid', $require_activation ? 3 : 2);
        $userdata->set_usertitle('', false, $vbulletin->usergroupcache["$newusergroupid"], false, false);

        $userdata->presave_called = true;

        // If any error happened, we abort and return the error message(s)
        if ($userdata->has_errors(false)) {      // $die := false
            return join('</li><li>', $userdata->errors);
        }

        // Save the data
        $userid = $userdata->save();
        
        // Did we get a valid vb userid?
        if (!$userid) return 'vbnexus_registration_failed';

        // If the user changed the email given by the external service, we follow
        // the regular steps for email activation
        if ($require_activation) {
            // Email phrase 'activateaccount' expects vars called $userid, $username
            // and $activateid to be defined and meaningfull
            $username = $data['username'];
            $activateid = build_user_activation_id($userid, $moderated ? 4 : 2, 0);
            eval(fetch_email_phrases('activateaccount', $languageid));
            // After eval'ing activateaccount we have vars $subject and $message set
            vbmail($data['email'], $subject, $message, true);
        }

        // Force a new session to prevent potential issues with guests from the same IP, see bug #2459
        $vbulletin->session->created = false;

        return true;
    }

    /**
     * genPasswd(): generates passwords randomly
     *
     * @author Andrew Johnson <www.itnewb.com>
     * @link bhttp://www.itnewb.com/v/Generating-Session-IDs-and-Random-Passwords-with-PHP
     */
    private static function genPasswd($len=8, $special=true) {
        # Seed random number generator
        # Only needed for PHP versions prior to 4.2
        mt_srand( (double)microtime()*1000000 );

        # Array of digits, lower and upper characters; empty passwd string
        $passwd = '';
        $chars = array(
            'digits' => array(0, 1, 2, 3, 4, 5, 6, 7, 8, 9),
            'lower' => array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l', 'm',
                             'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x', 'y', 'z'),
            'upper' => array('A', 'B', 'C', 'D', 'E', 'F', 'G', 'H', 'I', 'J', 'K', 'L', 'M',
                             'N', 'O', 'P', 'Q', 'R', 'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z')
        );

        # Add special chars to array, if permitted; adjust as desired
        if ($special) $chars['special'] = array('!', '@', '#', '$', '%', '^', '&', '*', '_', '+');

        # Array indices (ei- digits, lower, upper)
        $charTypes = array_keys($chars);
        # Array indice friendly number of char types
        $numTypes  = count($charTypes) - 1;

        # Create random password
        for ($i=0 ; $i<$len ; $i++) {
            # Random char type
            $charType = $charTypes[ mt_rand(0, $numTypes) ];
            # Append random char to $passwd
            $passwd .= $chars[$charType][mt_rand(0, count($chars[$charType]) - 1 )];
        }

        return $passwd;
    }

}
