<?php

require_once(dirname(__FILE__) . '/vBNexus_user.php');

class vBNexus_gfc extends vBNexus_user {

    public function getServiceName() {
        return 'gfc';
    }

    /**
     * Returns the id of the user currently online, or 0 if none
     */
    public function getUserOnline() {
        $userData = $this->getUserData();
        return $userData ? $userData['userid'] : NULL;
    }

    public function getUserData() {
        $appId = vBNexus::getInstance()->getConfig('gfc_key');
        $cookie = $_COOKIE["fcauth{$appId}"];
        if (!$cookie) return array();

        $ch = curl_init("http://www.google.com/friendconnect/api/people/@viewer/@self?fcauth={$cookie}");
        $options = array(
            CURLOPT_HEADER => false,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_MAXREDIRS => 3,
        );
        curl_setopt_array($ch, $options);
        $content = curl_exec($ch);
        if (curl_error($ch) || !$content) return array();

        $profile = @json_decode($content)->entry;
        if (!$profile) return array();  // $content wasn't a valid json string

        // The returned array needs to have certain keys to make it compatible
        // with other connection services like gfc's (Twitter, Yahoo, etc)
        $data = array(
            'service'     => 'Google Friend Connect',
            'profile_url' => NULL,
            'userid'      => $profile->id,
            'avatar'      => $profile->thumbnailUrl,
            'name'        => $profile->displayName,
            'gender'      => NULL,
            'email'       => NULL,
            'timezone'    => NULL,
            'locale'      => NULL,
        );

        return $data;
    }


    /**
     * Just an alias of associateAccount, since GFC cannot automatically fix
     * vbnexus3 mock emails, so user interaction is required. Two birds in one
     * stone, we request the new password and a valid email at once.
     */
    protected function fixOldEmail($user, $vbnexus_userid) {
        return $this->associateAccount($user, $vbnexus_userid);
    }

    /**
     * protected void associateAccount(array $user, int $vbnexus_userid)
     *	    Forces GFC users to choose a password (and a valid email too for
     *      users of vbnexus3). The change is then flagged in the database with
     *      field vbnexus_user.associated set to 2.
     *
     * @param array $user
     * @param int $vbnexus_userid
     * @return void
     */
    protected function associateAccount($user, $vbnexus_userid) {
        global $vbulletin, $vboptions, $vbphrase, $stylevar, $vbnexus_loc;

        if (!intval($user['userid'])) return false;

        // If the user is submitting email and/or password, process it
        if (isset($_POST['vbnexus_gfc_fix']))
        {
            // Validate input
            if (empty($_POST['email']))
            {
                $vbnexus_error = "A valid email is required";
            }
            elseif (empty($_POST['password']))
            {
                $vbnexus_error = "A valid password is required";
            }
            elseif (empty($_POST['password2']) || $_POST['password'] != $_POST['password2'])
            {
                $vbnexus_error = "Passwords do not match";
            }
            else
            {

                $require_activation = $vbulletin->options['verifyemail']
                                  && ($user['email'] != $_POST['email']);

                $userdata =& datamanager_init('user', $vbulletin, ERRTYPE_SILENT);
                $userdata->set_existing(fetch_userinfo($user['userid']));
                $userdata->set('password', $_POST['password']);

                // We can ignore validation of the email if it wasn't changed
                ($user['email'] == $_POST['email']) || $userdata->set('email', $_POST['email']);

                if ($require_activation)
                {
                    $userdata->set('usergroupid', 3);
                }

                if ($userdata->has_errors(false))
                {
                    $vbnexus_error = join('</li><li>', $userdata->errors);
                }
                elseif ($userdata->save())
                {
                    if ($require_activation) {
                        // Email phrase 'activateaccount' expects vars called $userid, $username
                        // and $activateid to be defined and meaningfull
                        $userid = $user['userid'];
                        $username = $user['username'];
                        $activateid = build_user_activation_id($userid, $user['usergroupid'], 0);
                        eval(fetch_email_phrases('activateaccount', $languageid));
                        // After eval'ing activateaccount we have vars $subject and $message set
                        vbmail($_POST['email'], $subject, $message, true);
                    }

                    // The user was updated, there's now a valid password and email, so let's flag it
                    $sql = "UPDATE `" . TABLE_PREFIX . "vbnexus_user`
                            SET `associated` = 2
                            WHERE `nonvbid` = '{$vbnexus_userid}'
                            AND `service` = 'gfc'";
                    $vbulletin->db->query_write($sql);

                    if ($vbulletin->db->query_write($sql))
                    {
                        // Returning since we're done here and execution should go on normally
                        return;
                    }
                    else
                    {
                        // This should never happen, it's mostly for debugging if something goes wrong
                        $errmsg = "An error occurred trying to update your GFC information. Please try again." .
                                  " If the problem persists please report it to an admin.";
                        return eval(standard_error($errmsg));    // Prints and exits
                    }
                }
                else
                {
                    // This should never happen, it's mostly for debugging if something goes wrong
                    $errmsg = "An error occurred trying to update the account information. Please try again." .
                              " If the problem persists please report it to an admin.";
                    return eval(standard_error($errmsg));        // Prints and exits
                }
            }

            $user['email'] = $_POST['email'];
        }

        $vBNexusUser = $user;

        // No need to show mock emails from old vbnexus (< 3)
        if (empty($_POST['email']) && preg_match("/apps\+|{$vbnexus_userid}[@\.]/", $user['email'])) {
            $vBNexusUser['email'] = '';
        }

        $vbnexus_loc = $_GET['loc'];

        // This will print a Message box (not really an error, but the actual form) and exit
        eval('$html = "' . fetch_template('vbnexus_3_gfc_invalid_email') . '";');
        eval(standard_error($html));
    }

}