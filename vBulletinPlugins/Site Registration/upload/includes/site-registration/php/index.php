<?php

//path setup
set_include_path(
        get_include_path() . PATH_SEPARATOR . realpath('../../../')
                . PATH_SEPARATOR . realpath('../../../includes/'));

/*
    Script setup
 */
ini_set("display_errors", 1);
error_reporting(E_ALL & ~E_NOTICE & ~8192);

define('CSRF_PROTECTION', true);
define('THIS_SCRIPT', 'site-registration');


require_once("site_registration_functions.php");
require_once("rfc822.php");

if($show['vbnexus_button_fb']) {
    require_once('../../../includes/vbnexus4.1.5/vBNexus.php');
}

//include required files
chdir(realpath('../../../'));
require_once('global.php');
require_once('functions_user.php');
require_once('functions_misc.php');
require_once('functions_login.php');

if($show['vbnexus_button_fb']) {
    require_once('');
}

if (!session_id()) {
    session_start();
}

/**
 * Operations
 **/
$vbulletin->input->clean_array_gpc('g', array('op' => TYPE_STR));

$op = $vbulletin->GPC['op'];

switch ($op) {


// ajax check for coppa options
case 'check_coppa':
    $arr = array();
    $arr['use_coppa'] = $vbulletin->options['usecoppa'];

    json_headers($arr);

    break;
    
//regenerate securiy token for each page
case 'regenerate_security_token':
    $token_raw = sha1(
            $vbulletin->userinfo['userid'] . sha1($vbulletin->userinfo['salt'])
                    . sha1(COOKIE_SALT));
    $security_token = $_SESSION['site_registration']['securitytoken'] = TIMENOW
            . '-' . sha1(TIMENOW . $token_raw);

    if (empty($security_token)) {
        $security_token = $_SESSION['site_registration']['securitytoken'] = "guest";
    }

    $arr = array('token' => $security_token);

    json_headers($arr);
    break;

//generate captcha value
case 'regenerate_token':


    if (fetch_require_hvcheck('register')) {
        require_once(DIR . '/includes/class_humanverify.php');
        $verification = &vB_HumanVerify::fetch_library($vbulletin);
        $human_verify = $verification->generate_token();

        $_SESSION['site_registration']['captcha']['hash'] = $human_verify['hash'];
        $_SESSION['site_registration']['captcha']['answer'] = $human_verify['answer'];

        //register captcha value
        $hv_token = $human_verify['hash'];

        $arr = array('token' => $hv_token,
                'url' => $vbulletin->options['bburl']
                        . "/image.php?type=hv&hash=" . $hv_token);

    }

    json_headers($arr);

    break;

//complete your profile step

case 'complete_your_profile':
    $user_data = &datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
    $valid_entries = TRUE;
    $messages = "";

    $vbulletin->input
            ->clean_array_gpc('p',
                    array('secret_question' => TYPE_STR,
                            'secret_answer' => TYPE_STR,
                            'receive_emails_from_administrators' => TYPE_STR,
                            'receive_emails_from_other_members' => TYPE_STR,
                            'timezone' => TYPE_STR,
                            'use_default_image' => TYPE_STR));

    if (!empty($vbulletin->GPC['secret_question'])) {
        if (empty($vbulletin->GPC['secret_answer'])) {
            $valid_entries = FALSE;
            $user_data->error('fieldmissing');
            $error_type = "secret_answer";
            $messages['fields'][] = $error_type;
            $messages['errors'][] = $userdata->errors[0];
        }
    } else {

    }

    if (!empty($vbulletin->GPC['secret_answer'])) {
        
        if (empty($vbulletin->GPC['secret_question'])) {
            $valid_entries = FALSE;
            $user_data->error('fieldmissing');
            $error_type = "secret_question";
            $messages['fields'][] = $error_type;
            $messages['errors'][] = $userdata->errors[0];
        }
        
    } else {

    }

    if (empty($vbulletin->GPC['timezone'])) {
        $valid_entries = FALSE;
        $user_data->error('fieldmissing');
        $error_type = "timezone";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = $userdata->errors[0];
    } else {

    }

    if (empty($vbulletin->GPC['receive_emails_from_administrators'])) {
        $adminemail = 0;
    } else {
        $adminemail = 1;
    }

    if (empty($vbulletin->GPC['receive_emails_from_other_members'])) {
        $showemail = 0;
    } else {
        $showemail = 1;
    }
    
    
    //update avatar if option enabled
    if($vbulletin->options['avatarenabled']){
        $userinfo = fetch_userinfo($_SESSION['site_registration']['userid']);
        
        // init user datamanager
	    $userdata =& datamanager_init('User', $vbulletin, ERRTYPE_CP);
	    $userdata->set_existing($userinfo);
        
        $vbulletin->input->clean_gpc('f', 'upload', TYPE_FILE);     
        
        if(empty($vbulletin->GPC['upload'])){
            $vbulletin->GPC['avatarurl'] = $vbulletin->options['bburl'] . "/includes/site-registration/unknown.png";
        }
    
        require_once(DIR . '/includes/class_upload.php');
	    require_once(DIR . '/includes/class_image.php');
	    
	    $upload = new vB_Upload_Userpic($vbulletin); 

        $upload->data =& datamanager_init('Userpic_Avatar', $vbulletin, ERRTYPE_STANDARD, 'userpic');
        $upload->image =& vB_Image::fetch_library($vbulletin);
        $upload->maxwidth = $userinfo['permissions']['avatarmaxwidth'];
		$upload->maxheight = $userinfo['permissions']['avatarmaxheight'];
        $upload->maxuploadsize = $userinfo['permissions']['avatarmaxsize'];
        $upload->allowanimation = ($userinfo['permissions']['genericpermissions'] & $vbulletin->bf_ugp_genericpermissions['cananimateavatar']) ? true : false;

        if (!$upload->process_upload($vbulletin->GPC['avatarurl'])) {
			$valid_entries = FALSE;
            $error_type = "upload";
            $messages['fields'][] = $error_type;
            $messages['errors'][] = fetch_error( 'there_were_errors_encountered_with_your_upload_x', $upload->fetch_error());
		}

    }else{
		// predefined avatar
		$userpic =& datamanager_init('Userpic_Avatar', $vbulletin, ERRTYPE_CP, 'userpic');
		$userpic->condition = "userid = " . $userinfo['userid'];
		$userpic->delete();
	}
    
    
    if ($valid_entries) {
    
        if(!empty($vbulletin->GPC['secret_question']) && !empty($vbulletin->GPC['secret_answer'])){
            //update secret question and secret answer
            $temp_table_query = "
                CREATE  TABLE IF NOT EXISTS " . TABLE_PREFIX
                    . "siteregistration_security_details (
                    userid INT(128) NOT NULL,
                    question VARCHAR(255) NOT NULL,
                    answer VARCHAR(255) NOT NULL
                )";

            $vbulletin->db->query_write($temp_table_query);

            $userid = $_SESSION['site_registration']['userid'];
            $question = $vbulletin->GPC['secret_question'];
            $answer = $vbulletin->GPC['secret_answer'];
            $salt = $vbulletin->db->escape_string($vbulletin->userinfo['salt']);

            /*insert query*/
            $vbulletin->db
                    ->query_write(
                            "
                REPLACE INTO " . TABLE_PREFIX
                                    . "siteregistration_security_details
                (userid,question,answer)
                VALUES
                (   '" . $vbulletin->db->escape_string($userid)
                                    . "',
                    AES_ENCRYPT('" . $vbulletin->db->escape_string($question)
                                    . "','" . $salt
                                    . "'),
                    AES_ENCRYPT('" . $vbulletin->db->escape_string($answer) . "','"
                                    . $salt . "')
                 )
            ");    
        }
    
 
        //update who can contact you

        if(!isset($userdata)){
            $userdata = &datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
            $vbulletin->userinfo = fetch_userinfo($userid);
            $userdata->set_existing($vbulletin->userinfo);
        }
        
        $userdata->set_bitfield('options', "adminemail", $adminemail);
        $userdata->set_bitfield('options', "showemail", $showemail);
        
        $userdata->set('avatarid', $vbulletin->GPC['avatarid']);
        $userdata->set('timezoneoffset', $vbulletin->GPC['timezone']);

        $userdata->save();

        //start new session
        if(!isset($vbulletin->userinfo)){
            $vbulletin->userinfo = $vbulletin->db
                    ->query_first(
                            "SELECT userid, usergroupid, membergroupids, infractiongroupids, 
                username, password, salt FROM " . TABLE_PREFIX
                                    . "user 
                WHERE userid = " . $userid);
        }
        

        require_once(DIR . '/includes/functions_login.php');

        vbsetcookie('userid', $vbulletin->userinfo['userid'], true, true, true);
        vbsetcookie('password',
                md5($vbulletin->userinfo['password'] . COOKIE_SALT), true,
                true, true);

        process_new_login('', 1, $vbulletin->GPC['cssprefs']);

        cache_permissions($vbulletin->userinfo, true);

        $vbulletin->session->save();

    }

    $arr = array("valid_entries" => $valid_entries, "messages" => $messages);

    json_headers_ie_support($arr);

    break;

/**
 * register.php?step=site-account-details
 **/

case 'validate_site_account_details':
    $userdata = &datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
    $valid_entries = TRUE;
    $messages = "";
    $vbulletin->input
            ->clean_array_gpc('p',
                    array(  'username' => TYPE_NOCLEAN,
                            'parent-guardian-email' => TYPE_STR, 
                            'password' => TYPE_STR,
                            'confirm_password' => TYPE_STR,
                            'security_code' => TYPE_STR,
                            'terms_and_conditions' => TYPE_INT));

    if (empty($vbulletin->GPC['password'])
            || $vbulletin->GPC['password'] == md5("")) {

        $valid_entries = FALSE;
        //$userdata->error('enter_password_for_account');
        $error_type = "password";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = "Please enter a password for your user account."; //fetch_phrase('enter_password_for_account', 'global');
    }

    if (empty($vbulletin->GPC['confirm_password'])
            || $vbulletin->GPC['confirm_password'] == md5("")) {
        unset($userdata->errors);
        $valid_entries = FALSE;
        //$userdata->error('enter_password_for_account');
        $error_type = "confirm-password";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = "Please enter a password for your user account."; //fetch_phrase('enter_password_for_account', 'global');
    }

    
    
    if ( $_SESSION['site_registration']['coppauser'] === true) {
        if (empty($vbulletin->GPC['parent-guardian-email'])) {
            $valid_entries = FALSE;
            $error_type = "parent-guardian-email"; 
            $messages['fields'][] = $error_type;
            $messages['errors'][] = fetch_error('fieldmissing_parentemail');
        }else{
            if (is_valid_email_address($vbulletin->GPC['parent-guardian-email'])) {

                list($email_name, $email_domain) = preg_split("/@/",
                        $vbulletin->GPC['parent-guardian-email']);

                if (!checkdnsrr($email_domain, "MX")) {
                    $valid_entries = FALSE;
                    //$messages['errors'][] = $message = fetch_error('fieldmissing_parentemail')
                    //        . " No MX records found for domain.";
                    $messages['errors'][] = $message = "Invalid email." . " No MX records found for domain.";
                    $messages['fields'][] = $error_type = "parent-guardian-email";
                }
            }else{
                $valid_entries = FALSE;
                $messages['errors'][] = $message = fetch_error('fieldmissing_parentemail');
                $messages['fields'][] = $error_type = "parent-guardian-email";
            }
        }
    }

    if ($vbulletin->GPC['terms_and_conditions'] != 1) {
        unset($userdata->errors);
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $error_type = "terms-and-conditions";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = "Please agree to the "
                . fetch_phrase('forum_rules', 'register');

    }

    if ($vbulletin->GPC['confirm_password'] != $vbulletin->GPC['password']) {
        $valid_entries = FALSE;

        $error_type = "confirm-password";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = "Passwords don't match";

        $error_type = "password";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = "Passwords don't match";
    }

    unset($userdata->errors); 
 
    //ACP-494 decode js escaped unicode characters
    $vbulletin->GPC['username'] = preg_replace( "/%u([A-Fa-f0-9]{4})/", "&#x$1;", $vbulletin->GPC['username']);
    $vbulletin->GPC['username'] = html_entity_decode($vbulletin->GPC['username'], ENT_COMPAT, 'utf-8');
    
    if ($userdata->verify_username($vbulletin->GPC['username']) === FALSE) {
        $valid_entries = FALSE;

        $error_type = "username";
        $messages['fields'][] = $error_type;

        if (strlen($userdata->errors[0]) > 45) {
            $messages['errors'][] = "The username you chose is not valid.";
        } else {
            $messages['errors'][] = $userdata->errors[0];
        }

    }else{ 
    
    }
    
 
    //check if username already exists on DB
    $user_exists = $db
            ->query_first(
                    "
        SELECT userid, username, email, languageid
        FROM " . TABLE_PREFIX . "user
        WHERE username = '" . $db->escape_string($vbulletin->GPC['username'])
                            . "'
    ");

    if (!empty($user_exists['username'])) {
        $valid_entries = FALSE;
        $error_type = "username";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = "Sorry, this username is already taken.";//fetch_error('usernametaken', $user_exists['username'], '');
    }

    if (fetch_require_hvcheck('register')) {
        //check if CAPTCHA value is correct
        
        
        if (empty($vbulletin->GPC['security_code'])) {
            unset($userdata->errors);
            $valid_entries = FALSE;
            $error_type = "security-code";
            $userdata->error('fieldmissing');
            $messages['fields'][] = $error_type;
            $messages['errors'][] = $userdata->errors[0];
        }
        
        if (strtoupper($vbulletin->GPC['security_code'])
                != strtoupper(
                        $_SESSION['site_registration']['captcha']['answer'])) {
            $valid_entries = FALSE;

            $error_type = "security-code";
            $messages['fields'][] = $error_type;
            $messages['errors'][] = "Invalid Security Code"; //fetch_error('humanverify_image_wronganswer');
        }
    }

    if ($valid_entries) {
        $_SESSION['site_registration']['username'] = $vbulletin
                ->GPC['username'];
        $_SESSION['site_registration']['password'] = $vbulletin
                ->GPC['password'];

        $token = md5(uniqid(microtime(), true));
        $token_time = time();

        $form = "site-account-details";
        $_SESSION['site_registration'][$form . '_token'] = array(
                'token' => $token, 'time' => $token_time);

        //Create Site Account in database
        
        $userdata->set('email', $_SESSION['site_registration']['email']);
        $userdata->set('username', $vbulletin->GPC['username']);
        $userdata->set('password', $_SESSION['site_registration']['password']);
        
        
        //$userdata->set('referrerid', $vbulletin->GPC['referrername']);

        // set languageid
        $userdata->set('languageid', $vbulletin->userinfo['languageid']);
        
        // assign user to usergroup 3 if email needs verification
        if ($vbulletin->options['verifyemail']) {
            $newusergroupid = 3;
        } else if ($vbulletin->options['moderatenewmembers']
                OR $_SESSION['site_registration']['coppauser']) {
            $newusergroupid = 4;
        } else {
            $newusergroupid = 2;
        }

        // set user title
        $userdata
                ->set_usertitle('', false,
                        $vbulletin->usergroupcache["$newusergroupid"], false,
                        false);

        // set profile fields
        // $customfields = $userdata->set_userfields($vbulletin->GPC['userfield'], true, 'register');

        if ($vbulletin->options['reqbirthday']
                || !empty($_SESSION['site_registration']['birthday'])) {
            // set birthday
            $userdata->set('showbirthday', $vbulletin->GPC['showbirthday']);

            //mm/dd/yyyy
            $date_parts = explode("/",
                    $_SESSION['site_registration']['birthday']);

            $month = $date_parts[0];
            $year = $date_parts[2];
            $day = $date_parts[1];

            $userdata
                    ->set('birthday',
                            array('day' => $day, 'month' => $month,
                                    'year' => $year));
        }

 
        // set usergroupid
        $userdata->set('usergroupid', $newusergroupid);

        // set time options
        //$userdata->set_dst($vbulletin->GPC['dst']);
        //$userdata->set('timezoneoffset', $vbulletin->GPC['timezoneoffset']);

        $userdata
                ->set_info('coppauser',
                        $_SESSION['site_registration']['coppauser']);
        $userdata->set_info('coppapassword', $vbulletin->GPC['password']);
        $userdata
                ->set_bitfield('options', 'coppauser',
                        $_SESSION['site_registration']['coppauser']);
        
        
        //ACP-479
        if ($vbulletin->options['usecoppa'] > 0 && $_SESSION['site_registration']['coppauser']) {
            $userdata->set('parentemail', $vbulletin->GPC['parent-guardian-email']);
        }                
        

        // register IP address
        $userdata->set('ipaddress', IPADDRESS);

        $userdata->pre_save();

        if (!empty($userdata->errors)) {
            //errors?
            $valid_entries = FALSE;

            if (preg_match("/username/", $userdata->errors[0])) {
                $error_type = "username";
                $messages['fields'][] = $error_type;
                $messages['errors'][] = $userdata->errors[0];

            }

            if (preg_match("/password/", $userdata->errors[0])) {
                $error_type = "password";
                $messages['fields'][] = $error_type;
                $messages['errors'][] = $userdata->errors[0];

            }

        } else {
            // save the data
            $_SESSION['site_registration']['userid'] = $vbulletin
                    ->userinfo['userid'] = $userid = $userdata->save();

            $userinfo = fetch_userinfo($userid);
            $userdata_rank = &datamanager_init('User', $vbulletin,
                    ERRTYPE_SILENT);
            $userdata_rank->set_existing($userinfo);
            $userdata_rank->set('posts', 0);
            $userdata_rank->save();

            //start new session
            $vbulletin->userinfo = $vbulletin->db
                    ->query_first(
                            "SELECT userid, usergroupid, membergroupids, infractiongroupids, 
                username, password, salt FROM " . TABLE_PREFIX
                                    . "user 
                WHERE userid = " . $userid);

            require_once(DIR . '/includes/functions_login.php');

            vbsetcookie('userid', $vbulletin->userinfo['userid'], true, true,
                    true);
            vbsetcookie('password',
                    md5($vbulletin->userinfo['password'] . COOKIE_SALT), true,
                    true, true);

            process_new_login('', 1, $vbulletin->GPC['cssprefs']);

            cache_permissions($vbulletin->userinfo, true);

            $vbulletin->session->save();

            //Send Activation Email: Refer to Automated Emails
            // send new user email
            $username = $vbulletin->GPC['username'];
            $email = $_SESSION['site_registration']['email'];

            if ($vbulletin->options['verifyemail']) {
                $activateid = build_user_activation_id($userid,
                        (($vbulletin->options['moderatenewmembers']
                                OR $_SESSION['site_registration']['coppauser']) ? 4
                                : 2), 0);

                eval(fetch_email_phrases('activateaccount'));

                if (empty($subject)) {
                    $subject = fetch_phrase('activate_your_account',
                            'threadmanage');

                }

                vbmail($email, $subject, $message, false);
            }

            if ($newusergroupid == 2) {
                if ($vbulletin->options['welcomemail']) {
                    eval(fetch_email_phrases('welcomemail'));
                    vbmail($email, $subject, $message);
                }
            }

            if ($vbulletin->options['verifyemail']) {
                //Redirect user to Activation Screen
                $url = "register.php?step=activate";
            } else {
                //take user back to where he started

                $url = prev_url();

            }

        }

    }

    $arr = array("valid_entries" => $valid_entries, "messages" => $messages,
            "url" => $url);

    json_headers($arr);

    break;

case 'resend_email':
    if ($vbulletin->options['verifyemail']) {
        if (isset($_SESSION['site_registration']['email'])) {
            $username = $_SESSION['site_registration']['username'];
            $email = $_SESSION['site_registration']['email'];
            $userid = $_SESSION['site_registration']['userid'];

            $activateid = build_user_activation_id($userid,
                    (($vbulletin->options['moderatenewmembers']
                            OR $_SESSION['site_registration']['coppauser']) ? 4
                            : 2), 0);

            eval(fetch_email_phrases('activateaccount'));

            if (empty($subject)) {
                $subject = fetch_phrase('activate_your_account', 'threadmanage');
            }

            vbmail($email, $subject, $message, true);

            $messages = "Email sent!";

        } else {
            $messages = "Unable to send email, please try again later.";
        }
    }

    $arr = array("message" => $messages);

    json_headers($arr);

    break;

//create site account on register.php
case 'create_site_account_first_step':
    $userdata = &datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
    $valid_entries = TRUE;
    $message = "";

    //clean variables
    $vbulletin->input
            ->clean_array_gpc('p',
                    array('email' => TYPE_STR, 'birthdate' => TYPE_STR));

    if (!$vbulletin->options['allowregistration']) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $messages['errors'][] = $message = fetch_error('noregister');
        $messages['fields'][] = $error_type = "email";

    }

    if ($vbulletin->options['usecoppa'] > 0) {
        $vbulletin->options['reqbirthday'] = true;
    }

    //check if variables are set
    if ($vbulletin->options['reqbirthday']
            || !empty($vbulletin->GPC['birthdate'])) {

        if (empty($vbulletin->GPC['birthdate'])) {
            $valid_entries = FALSE;
            $userdata->error('fieldmissing');
            $messages['errors'][] = $message = fetch_error('birthdayfield');
            $messages['fields'][] = $error_type = "datepicker";

        } else {
            //validate if 13+
            $current['year'] = date('Y');
            $current['month'] = date('m');
            $current['day'] = date('d');

            //mm/dd/yyyy
            $date_parts = explode("/", $vbulletin->GPC['birthdate']);

            $month  = $date_parts[0];
            $day    = $date_parts[1];
            $year   = $date_parts[2];
            

            $coppaage = $vbulletin->input
                    ->clean_gpc('c', COOKIE_PREFIX . 'coppaage', TYPE_STR);

            if (check_date($vbulletin->GPC['birthdate'])) {

                if ($month == 0 OR $day == 0 OR !preg_match('#^\d{4}$#', $year)
                        OR $year < 1901 OR $year > $current['year']) {
                    $valid_entries = FALSE;
                    $messages['errors'][] = $message = fetch_error(
                            'select_valid_dob', $current['year']);
                    $messages['fields'][] = $error_type = "datepicker";
                }

                if ($vbulletin->options['usecoppa']
                        AND $vbulletin->options['checkcoppa'] AND $coppaage) {
                    $dob    = explode('-', $coppaage);
                    $month  = $dob[0];
                    $day    = $dob[1];
                    $year   = $dob[2];
                }

                if ($year < 1970
                        OR (mktime(0, 0, 0, $month, $day, $year)
                                <= mktime(0, 0, 0, $current['month'],
                                        $current['day'], $current['year'] - 13))) {
                    $_SESSION['site_registration']['coppauser'] = false;
                } else {
                    
                    
                    if ($vbulletin->options['checkcoppa']
                            AND $vbulletin->options['usecoppa']) {
                        vbsetcookie('coppaage',
                                $month . '-' . $day . '-' . $year, 1);
                    }
                    
                    

                    if ($vbulletin->options['usecoppa'] == 2) {
                        // turn away as they're under 13

                        $valid_entries = FALSE;
                        $messages['errors'][] = $message = fetch_error(
                                'under_thirteen_registration_denied');
                        $messages['fields'][] = $error_type = "datepicker";

                    } else {
                        $_SESSION['site_registration']['coppauser'] = true;
                    }
                }

            } else {
                $valid_entries = FALSE;
                $messages['errors'][] = $message = fetch_error('birthdayfield');
                $messages['fields'][] = $error_type = "datepicker";
            }

        }
    } else {
        $vbulletin->GPC['birthdate'] = '';
    }

    //check if variables are set
    if (empty($vbulletin->GPC['email'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $messages['errors'][] = $message = $userdata->errors[0];
        $messages['fields'][] = $error_type = "email";

    }

    //validate email
    if (is_valid_email_address($vbulletin->GPC['email'])) {

        list($email_name, $email_domain) = preg_split("/@/",
                $vbulletin->GPC['email']);

        if (!checkdnsrr($email_domain, "MX")) {
            $valid_entries = FALSE;
            $messages['errors'][] = $message = fetch_error('bademail')
                    . " No MX records found for domain.";
            $messages['fields'][] = $error_type = "email";

        } else {
            //if (!$vbulletin->options['allowmultiregs']) {
            if ($vbulletin->options['requireuniqueemail']) {
                //check if email already exists on DB
                $user_exists = $db
                        ->query_read_slave(
                                "
                        SELECT userid, username, email, languageid
                        FROM " . TABLE_PREFIX
                                        . "user
                        WHERE UPPER(email) = '"
                                        . strtoupper(
                                                $db
                                                        ->escape_string(
                                                                $vbulletin
                                                                        ->GPC['email']))
                                        . "'
                    ");

                if ($db->num_rows($user_exists)) {
                    $valid_entries = FALSE;
                    $messages['errors'][] = $message = fetch_error(
                            'emailtaken', '');
                    $messages['fields'][] = $error_type = "email";
                }
            }
            //}
        }

    } else {
        $valid_entries = FALSE;
        $messages['errors'][] = $message = fetch_error('bademail');
        $messages['fields'][] = $error_type = "email";
    }

    require_once(DIR . '/includes/functions_user.php');

    if (is_banned_email($vbulletin->GPC['email'])) {
        if (!$vbulletin->options['allowkeepbannedemail']) {
            $valid_entries = FALSE;
            $message = $error = fetch_error("banemail");
            $error_type = "email";

        }
    }

    if ($valid_entries) {

        //create table for storing registration data
        $temp_table_query = "
            CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX
                . "siteregistration_temp (
                email VARCHAR(128) NOT NULL DEFAULT '',
                birthday VARCHAR(12) NOT NULL DEFAULT '',
                initialpage VARCHAR(255) NOT NULL DEFAULT ''
            )";

        $vbulletin->db->query_write($temp_table_query);
        
        //clear any previous entries if available
        $sql = "DELETE FROM " . TABLE_PREFIX . "siteregistration_temp
                WHERE email='" . $vbulletin->db->escape_string($vbulletin->GPC['email']) . "' ";
        
        $vbulletin->db->query_write($sql);

        /*insert query*/
        $vbulletin->db
                ->query_write(
                        "
            REPLACE INTO " . TABLE_PREFIX
                                . "siteregistration_temp
            (email,birthday,initialpage)
            VALUES
            ('" . $vbulletin->db->escape_string($vbulletin->GPC['email'])
                                . "',
             '" . $vbulletin->db->escape_string($vbulletin->GPC['birthdate'])
                                . "',
             '"
                                . $vbulletin->db
                                        ->escape_string(
                                                $_SESSION['site_registration']['initial_page'])
                                . "'
             )
        ");

        $rows = $vbulletin->db->affected_rows();
        $valid_entries = TRUE;
        $message = "OK";
        $url = "register.php?step=site-account-details";

        $token = md5(uniqid(microtime(), true));
        $token_time = time();
        $form = "create_site_account_first_step";
        $_SESSION['site_registration'][$form . '_token'] = array(
                'token' => $token, 'time' => $token_time);

        $_SESSION['site_registration']['email'] = $vbulletin->GPC['email'];
        $_SESSION['site_registration']['birthday'] = $vbulletin
                ->GPC['birthdate'];

    }

    $arr = array("valid_entries" => $valid_entries, "messages" => $messages,
            "url" => $url);

    json_headers($arr);

    break;

// already have an account on register.php
case 'validate_login':
default:
// init user datamanager class
    $userdata = &datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
    $valid_login = FALSE;
    $message = "";

    //clean input variables
    $vbulletin->input
            ->clean_array_gpc('p',
                    array('vb_login_username' => TYPE_NOHTML,
                            'vb_login_password' => TYPE_STR,
                            'vb_login_md5password' => TYPE_STR,
                            'vb_login_md5password_utf' => TYPE_STR));

    //check if variables are set
    if (empty($vbulletin->GPC['vb_login_username'])
            OR empty($vbulletin->GPC['vb_login_password'])
            OR $vbulletin->GPC['vb_login_password'] == md5("")) {
        $valid_login = FALSE;
        //$userdata->error('enter_password_for_account');
        if (count($userdata->errors) > 1) {
            $message = "Please enter a password for your user account."; //fetch_phrase('enter_password_for_account', 'global');
        } else {
            $message = "Sorry, please check your username and password.";
        }

    } else {
        //check if username and password are valid
        $vbulletin->input
                ->clean_array_gpc('p',
                        array('vb_login_username' => TYPE_NOHTML,
                                'vb_login_password' => TYPE_STR,
                                'vb_login_md5password' => TYPE_STR,
                                'vb_login_md5password_utf' => TYPE_STR,
                                'postvars' => TYPE_BINARY,
                                'cookieuser' => TYPE_BOOL,
                                'logintype' => TYPE_STR,
                                'cssprefs' => TYPE_STR,));

        // can the user login?
        $strikes = verify_strike_status($vbulletin->GPC['vb_login_username']);

        // make sure our user info stays as whoever we were (
        // for example, we might be logged in via cookies already)
        $original_userinfo = $vbulletin->userinfo;

        $vbulletin->GPC['vb_login_md5password'] = $vbulletin
                ->GPC['vb_login_md5password_utf'] = ($vbulletin
                ->GPC['vb_login_password']);

        if (!verify_authentication($vbulletin->GPC['vb_login_username'],
                $vbulletin->GPC['vb_login_password'],
                $vbulletin->GPC['vb_login_md5password'],
                $vbulletin->GPC['vb_login_md5password_utf'], 1, true)) {

            // check password
            exec_strike_user($vbulletin->GPC['vb_login_username']);

            if ($vbulletin->GPC['logintype'] === 'cplogin'
                    OR $vbulletin->GPC['logintype'] === 'modcplogin') {
                // log this error if attempting to access the control panel
                require_once(DIR . '/includes/functions_log_error.php');
                log_vbulletin_error($vbulletin->GPC['vb_login_username'],
                        'security');
            }

            $vbulletin->userinfo = $original_userinfo;

            if ($vbulletin->options['usestrikesystem']) {

                $valid_login = FALSE;
                $message = fetch_error('badlogin_strikes',
                        $vbulletin->options['bburl'],
                        $vbulletin->session->vars['sessionurl'], $strikes);
            } else {
                $valid_login = FALSE;
                $message = fetch_error('badlogin',
                        $vbulletin->options['bburl'],
                        $vbulletin->session->vars['sessionurl']);
            }

            if (empty($message)
                    || $_SESSION['site_registration']['login_strikes'] > 4) {
                $message = "You have entered an invalid username or password.";
            }
        } else {
            // create new session
            exec_unstrike_user($vbulletin->GPC['vb_login_username']);
            process_new_login('', '', '');

            $url = prev_url();

            if (preg_match("/register/i", $url)) {
                $url = "login.php?do=login";
            }

            unset($_SESSION['site_registration']['initial_page']);

            $valid_login = TRUE;
            $message = "OK";

        }

    }

    $message = rewrite_error($message);

    $arr = array("valid_login" => $valid_login, "message" => $message,
            "url" => $url);

    json_headers($arr);

    break;

case 'activate':
    $userdata = &datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
    $valid_entries = TRUE;
    $message = "";

    //clean variables
    $vbulletin->input
            ->clean_array_gpc('p',
                    array('email' => TYPE_STR, 'birthdate' => TYPE_STR,
                            'username' => TYPE_NOHTML, 'avatar' => TYPE_STR,
                            'from' => TYPE_STR,
                            'terms_and_conditions' => TYPE_STR));

    //check if variables are set
    if (empty($vbulletin->GPC['email'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $messages['errors'][] = $message = $userdata->errors[0];
        $messages['fields'][] = $error_type = "email";
    }

    //ACP-494 decode js escaped unicode characters
    $vbulletin->GPC['username'] = preg_replace( "/%u([A-Fa-f0-9]{4})/", "&#x$1;", $vbulletin->GPC['username']);
    $vbulletin->GPC['username'] = html_entity_decode($vbulletin->GPC['username'], ENT_COMPAT, 'utf-8');
    
    if ($userdata->verify_username($vbulletin->GPC['username']) === FALSE) {
        $valid_entries = FALSE;

        $error_type = "username";
        $messages['fields'][] = $error_type;

        //maybe error is too large and we need to cut it?
        if (strlen($userdata->errors[0]) > 45) {
            $messages['errors'][] = "The username you chose is not valid.";
        } else {
            $messages['errors'][] = $userdata->errors[0];
        }

    }

    //check if username already exists on DB
    $user_exists = $db
            ->query_first(
                    "
        SELECT userid, username, email, languageid
        FROM " . TABLE_PREFIX . "user
        WHERE username = '" . $db->escape_string($vbulletin->GPC['username'])
                            . "'
    ");

    if (!empty($user_exists['username'])) {
        $valid_entries = FALSE;
        $error_type = "username";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = "Sorry, this username is already taken.";//fetch_error('usernametaken', $user_exists['username'], '');
    }

    if (empty($vbulletin->GPC['terms_and_conditions'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $messages['errors'][] = $message = "Please agree to the "
                . fetch_phrase('forum_rules', 'register');
        $messages['fields'][] = $error_type = "terms_and_conditions";
    }

    //validate email
    if (is_valid_email_address($vbulletin->GPC['email'])) {

        list($email_name, $email_domain) = preg_split("/@/",
                $vbulletin->GPC['email']);

        if (!checkdnsrr($email_domain, "MX")) {
            $valid_entries = FALSE;
            $messages['errors'][] = $message = fetch_error('bademail')
                    . " No MX records found for domain.";
            $messages['fields'][] = $error_type = "email";

        } else {

            if ($vbulletin->options['requireuniqueemail']) {
                //check if email already exists on DB
                $user_exists = $db
                        ->query_read_slave(
                                "
                        SELECT userid, username, email, languageid
                        FROM " . TABLE_PREFIX
                                        . "user
                        WHERE UPPER(email) = '"
                                        . strtoupper(
                                                $db
                                                        ->escape_string(
                                                                $vbulletin
                                                                        ->GPC['email']))
                                        . "'
                    ");

                if ($db->num_rows($user_exists)) {
                    $valid_entries = FALSE;
                    $messages['errors'][] = $message = fetch_error(
                            'emailtaken', '');
                    $messages['fields'][] = $error_type = "email";
                }
            }

        }

    } else {
        $valid_entries = FALSE;
        $messages['errors'][] = $message = fetch_error('bademail');
        $messages['fields'][] = $error_type = "email";
    }

    require_once(DIR . '/includes/functions_user.php');

    if (is_banned_email($vbulletin->GPC['email'])) {
        if (!$vbulletin->options['allowkeepbannedemail']) {
            $valid_entries = FALSE;
            $messages['errors'][] = $message = $error = fetch_error("banemail");
            $messages['fields'][] = $error_type = "email";

        }
    }

    if ($vbulletin->options['usecoppa'] > 0) {
        $vbulletin->options['reqbirthday'] = true;
    }

    //check if variables are set
    if ($vbulletin->options['reqbirthday']
            || !empty($vbulletin->GPC['birthdate'])) {

        if (empty($vbulletin->GPC['birthdate'])) {
            $valid_entries = FALSE;
            $userdata->error('fieldmissing');
            $messages['errors'][] = $message = fetch_error('birthdayfield');
            $messages['fields'][] = $error_type = "datepicker";

        } else {
            //validate if 13+
            $current['year'] = date('Y');
            $current['month'] = date('m');
            $current['day'] = date('d');

            //mm/dd/yyyy
            $date_parts = explode("/", $vbulletin->GPC['birthdate']);

            $month  = $date_parts[0];
            $day    = $date_parts[1];
            $year   = $date_parts[2];
            

            $coppaage = $vbulletin->input
                    ->clean_gpc('c', COOKIE_PREFIX . 'coppaage', TYPE_STR);

            if (check_date($vbulletin->GPC['birthdate'])) {

                if ($month == 0 OR $day == 0 OR !preg_match('#^\d{4}$#', $year)
                        OR $year < 1901 OR $year > $current['year']) {
                    $valid_entries = FALSE;
                    $messages['errors'][] = $message = fetch_error(
                            'select_valid_dob', $current['year']);
                    $messages['fields'][] = $error_type = "datepicker";
                }

                if ($vbulletin->options['usecoppa']
                        AND $vbulletin->options['checkcoppa'] AND $coppaage) {
                    $dob    = explode('-', $coppaage);
                    $month  = $dob[0];
                    $day    = $dob[1];
                    $year   = $dob[2];
                }

                if ($year < 1970
                        OR (mktime(0, 0, 0, $month, $day, $year)
                                <= mktime(0, 0, 0, $current['month'],
                                        $current['day'], $current['year'] - 13))) {
                     $_SESSION['site_registration']['coppauser'] = false;
                } else {
                    $_SESSION['site_registration']['coppauser'] = true;
                    if ($vbulletin->options['checkcoppa']
                            AND $vbulletin->options['usecoppa']) {
                        vbsetcookie('coppaage',
                                $month . '-' . $day . '-' . $year, 1);
                    }

                    if ($vbulletin->options['usecoppa'] == 2) {
                        // turn away as they're under 13

                        $valid_entries = FALSE;
                        $messages['errors'][] = $message = fetch_error(
                                'under_thirteen_registration_denied');
                        $messages['fields'][] = $error_type = "datepicker";

                    } else {
                    
                    }
                }

            } else {
                $valid_entries = FALSE;
                $messages['errors'][] = $message = fetch_error('birthdayfield');
                $messages['fields'][] = $error_type = "datepicker";
            }

        }
    } else {
        $vbulletin->GPC['birthdate'] = '';
    }

    if ($valid_entries) {
        $fbID = $_SESSION['site_registration']["fbID"];

        $birthday = preg_replace("/\//", "-",
                $vbulletin->db->escape_string($vbulletin->GPC['birthdate']));
                
        
        if ($vbulletin->options['verifyemail']) {
            $newusergroupid = 3;
        } else if ($vbulletin->options['moderatenewmembers']
                OR $_SESSION['site_registration']['coppauser']) {
            $newusergroupid = 4;
        } else {
            $newusergroupid = 2;
        }

        if ($fbID) {
            /************OLD VERSION *******************************
            $email = $vbulletin->db->escape_string($vbulletin->GPC['email']);
            $username = $vbulletin->GPC['username'];
            $time = time();

            //insert query
            $vbulletin->db->query_write("INSERT IGNORE INTO ". TABLE_PREFIX."user (usergroupid, email, birthday, username, reputation, joindate, ipaddress) VALUES ('$newusergroupid', '$email', '$birthday', '$username', '10', '$time', '". IPADDRESS ."')");
                
            $sql = "SELECT userid FROM ". TABLE_PREFIX."user WHERE email = '$email' AND username = '$username'";

            $data = $vbulletin->db->query_first($sql);

            $userid = $data["userid"];

            $vbulletin->db->query_write("INSERT IGNORE INTO ". TABLE_PREFIX."userfield (userid) VALUES ('$userid')");
            $vbulletin->db->query_write("INSERT IGNORE INTO ". TABLE_PREFIX."usertextfield (userid) VALUES ('$userid')");
            */

            /**************VBNEXUS*************************/
            $vBNexus = new vBNexus;

            $vBNexus->setConfig('vbnexus_service', "fb");                    
            $vBNexus->setConfig('vbnexus_userid', $fbID);               

            $email    = $vbulletin->db->escape_string($vbulletin->GPC['email']);
            $username = $vbulletin->GPC['username'];
            $time     = time(); 
            $publish  = $vbulletin->db->escape_string($vbulletin->GPC['vbnexus_fb_publish']);

            $vbnexus_regData = array(
                'type'          => "new",
                'service'       => "fb",
                'userid'        => $fbID,
                'username'      => $username,
                'password'      => NULL,
                'email'         => $email,
                'coded_email'   => $vBNexus->codedEmail($email),
                'default_email' => $email,
                'publish'       => $publish,
            );
            
            $vbnexus_result = $vBNexus->register($vbnexus_regData);
        }

        $avatar = $vbulletin->GPC['avatar'];
        $rows = $vbulletin->db->affected_rows();
        $valid_entries = TRUE;
        $message = "OK";

        $parts = explode(".", $avatar);
        $extension = end($parts);
        $filedata = file_get_contents($avatar);
        $dateline = time();
        $visible = 1;
        $filesize = strlen($filedata);
        $filename = substr(md5(time()), 0, 10) . "." . $extension;
        
        
        if($vbulletin->options['avatarenabled']){
            $sql = "
                REPLACE INTO " . TABLE_PREFIX
                    . "customprofilepic
                (userid, filedata, dateline, filename, visible, filesize, width, height)
                VALUES
                ('" . $vbulletin->db->escape_string($userid) . "',
                 '" . $vbulletin->db->escape_string($filedata)
                    . "',
                 '" . $vbulletin->db->escape_string($dateline)
                    . "',
                 '" . $vbulletin->db->escape_string($filename)
                    . "',
                 '" . $vbulletin->db->escape_string($visible) . "',
                 '" . $vbulletin->db->escape_string($filesize)
                    . "',
                 '" . $vbulletin->db->escape_string("50") . "',
                 '" . $vbulletin->db->escape_string("50")
                    . "'
                 )
            ";

            /*insert query*/
            $vbulletin->db->query_write($sql); 
        }



        $token = md5(uniqid(microtime(), true));
        $token_time = time();
        $form = "site-account-details";
        $_SESSION['site_registration'][$form . '_token'] = array(
                'token' => $token, 'time' => $token_time);

        $email = $vbulletin->db->escape_string($vbulletin->GPC['email']);

        //Verify if the account already exists...                                
        $sql = "SELECT userid FROM " . TABLE_PREFIX
                . "user WHERE email = '$email'";

        $data = $vbulletin->db->query_first($sql);

        $userid = $data["userid"];

        $vbulletin->db
                ->query_write(
                        "INSERT IGNORE INTO  " . TABLE_PREFIX
                                . "vbnexus_user (service, nonvbid, userid, associated) VALUES ('fb', '"
                                . $fbID . "', '" . $userid . "', '1')");

        //Send Activation Email: Refer to Automated Emails
        // send new user email

        // delete activationid
        /*$vbulletin->db
                ->query_write(
                        "DELETE FROM " . TABLE_PREFIX
                                . "useractivation 
                WHERE userid = '" . $userid . "' 
                AND type = 0");*/

        $userid = $data["userid"];
        $nonvbid = $fbID;

        if ($vbulletin->options['verifyemail']) {
            build_user_activation_id($userid,
                    (($vbulletin->options['moderatenewmembers']
                            OR $_SESSION['site_registration']['coppauser']) ? 4
                            : 2), 0);

            $sql = "SELECT activationid FROM useractivation WHERE userid = '"
                    . $userid . "'";
            $data = $vbulletin->db->query_first($sql);

            $activationid = $data["activationid"];
        }

        if (!empty($activationid)) {
            $url = "register.php?a=act&u=" . $userid . "&i=" . $activationid;
        } else {
            $url = prev_url();

            // Process vBulletin login
            $vbulletin->userinfo = $vbulletin->db
                    ->query_first(
                            "SELECT userid, usergroupid, membergroupids, infractiongroupids, 
                        username, password, salt FROM " . TABLE_PREFIX
                                    . "user 
                        WHERE userid = " . $userid);

            require_once(DIR . '/includes/functions_login.php');

            vbsetcookie('userid', $vbulletin->userinfo['userid'], true, true,
                    true);
            vbsetcookie('password',
                    md5($vbulletin->userinfo['password'] . COOKIE_SALT), true,
                    true, true);

            process_new_login('', 1, $vbulletin->GPC['cssprefs']);

            cache_permissions($vbulletin->userinfo, true);

            $vbulletin->session->save();

            if ($vbulletin->options['welcomemail']) {
                eval(fetch_email_phrases('welcomemail'));
                vbmail($email, $subject, $message);
            }
        }
    }

    $arr = array("valid_entries" => $valid_entries,
            "error_type" => $error_type, "messages" => $messages,
            "url" => $url);

    json_headers($arr);

    break;

case "linkaccount":
    $userdata = &datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);

    $valid_entries = TRUE;
    $message = "OK";

    //clean variables
    $vbulletin->input
            ->clean_array_gpc('p',
                    array('username' => TYPE_NOHTML, 'password' => TYPE_STR));

    //check if variables are set
    if (empty($vbulletin->GPC['username'])) {
        $valid_entries = FALSE;
        //$userdata->error('fieldmissing');
        $messages['errors'][] = $message = "Please enter a valid username.";
        $messages['fields'][] = $error_type = "username-member";

    }

    //check if variables are set
    if (empty($vbulletin->GPC['password'])) {
        $valid_entries = FALSE;
        //$userdata->error('enter_password_for_account');
        $messages['errors'][] = $message = "Please enter a password for your user account."; //fetch_phrase('enter_password_for_account', 'global');
        $messages['fields'][] = $error_type = "password-member";
    }

    //check if variables are set
    if ($vbulletin->GPC['password'] == md5("")) {
        $valid_entries = FALSE;
        //$userdata->error('enter_password_for_account');
        $messages['errors'][] = $message = "Please enter a password for your user account."; //fetch_phrase('enter_password_for_account', 'global');
        $messages['fields'][] = $error_type = "password-member";
    }

    if ($valid_entries) {

        $user = $vbulletin->db->escape_string($vbulletin->GPC['username']);
        $password = $vbulletin->db->escape_string($vbulletin->GPC['password']);

        $sql = "SELECT userid, username, password, salt FROM " . TABLE_PREFIX
                . "user WHERE username = '$user' ";

        $data = $vbulletin->db->query_first($sql);

        if (is_array($data)) {
            $userid = $data["userid"];
            $username = $data["username"];
            $dbPassword = $data["password"];
            $password = md5($vbulletin->GPC['password'] . $data["salt"]);
            $fbID = $_SESSION['site_registration']["fbID"];
            $avatar = $_SESSION['site_registration']["fbPicture"];

            if ($dbPassword != $password) {
                $messages['errors'][] = $message = "Please check your username and password.";
                $messages['fields'][] = $error_type = "username-member";
                $messages['errors'][] = $message = "Please check your username and password.";
                $messages['fields'][] = $error_type = "password-member";
                $valid_entries = false;

            } else {
                $sql = "SELECT nonvbid, userid FROM " . TABLE_PREFIX
                        . "vbnexus_user WHERE nonvbid = '$fbID' AND userid = '$userid'";

                $data = $vbulletin->db->query_first($sql);

                if (!$data and strlen($fbID) > 1) {
                    $vbulletin->db
                            ->query_write(
                                    "INSERT IGNORE INTO " . TABLE_PREFIX
                                            . "vbnexus_user (service, nonvbid, userid, associated) VALUES ('fb', '"
                                            . $fbID . "', '" . $userid
                                            . "', '1')");

                    $parts = explode(".", $avatar);
                    $extension = end($parts);
                    $filedata = file_get_contents($avatar);
                    $dateline = time();
                    $visible = 1;
                    $filesize = strlen($filedata);
                    $filename = substr(md5(time()), 0, 10) . "." . $extension;

                    $sql = "
                        REPLACE INTO " . TABLE_PREFIX
                            . "customprofilepic
                        (userid, filedata, dateline, filename, visible, filesize, width, height)
                        VALUES
                        ('" . $vbulletin->db->escape_string($userid)
                            . "',
                         '" . $vbulletin->db->escape_string($filedata)
                            . "',
                         '" . $vbulletin->db->escape_string($dateline)
                            . "',
                         '" . $vbulletin->db->escape_string($filename)
                            . "',
                         '" . $vbulletin->db->escape_string($visible)
                            . "',
                         '" . $vbulletin->db->escape_string($filesize)
                            . "',
                         '" . $vbulletin->db->escape_string("50")
                            . "',
                         '" . $vbulletin->db->escape_string("50")
                            . "'
                         )
                    ";

                    /*insert query*/
                    $vbulletin->db->query_write($sql);

                    //Send Activation Email: Refer to Automated Emails
                    // send new user email

                    // delete activationid
                    /*$vbulletin->db
                            ->query_write(
                                    "DELETE FROM " . TABLE_PREFIX
                                            . "useractivation 
                            WHERE userid = '" . $userid . "' 
                            AND type = 0");*/

                    $nonvbid = $fbID;

                    $sql = "SELECT activationid FROM useractivation WHERE userid = '"
                            . $userid . "'";
                    $data = $vbulletin->db->query_first($sql);

                    $activationid = $data["activationid"];

                    if (!empty($activationid)) {
                        $url = "register.php?a=act&u=" . $userid . "&i="
                                . $activationid;
                    } else {
                        $url = "index.php";

                        $token = md5(uniqid(microtime(), true));
                        $token_time = time();
                        $form = "site-account-details";
                        $_SESSION['site_registration'][$form . '_token'] = array(
                                'token' => $token, 'time' => $token_time);

                        //start new session
                        $vbulletin->userinfo = $vbulletin->db
                                ->query_first(
                                        "SELECT userid, usergroupid, membergroupids, infractiongroupids, 
                            username, password, salt FROM " . TABLE_PREFIX
                                                . "user 
                            WHERE userid = " . $userid);

                        require_once(DIR . '/includes/functions_login.php');

                        vbsetcookie('userid', $vbulletin->userinfo['userid'],
                                true, true, true);
                        vbsetcookie('password',
                                md5(
                                        $vbulletin->userinfo['password']
                                                . COOKIE_SALT), true, true,
                                true);

                        if ($vbulletin->options['usestrikesystem']) {
                            exec_unstrike_user($vbulletin->GPC['username']);
                        }

                        process_new_login('', 1, $vbulletin->GPC['cssprefs']);

                        cache_permissions($vbulletin->userinfo, true);

                        $vbulletin->session->save();
                    }

                }
            }
        } else {
            $valid_entries = FALSE;
            $messages['errors'][] = $message = "Please check your username and password.";
            $messages['fields'][] = $error_type = "username-member";
            $messages['errors'][] = $message = "";
            $messages['fields'][] = $error_type = "password-member";

            if ($vbulletin->options['usestrikesystem']) {

                $strikes = verify_strike_status($vbulletin->GPC['username']);
                exec_strike_user($vbulletin->GPC['username']);

                if ($strikes >= 4) {
                    unset($messages);
                    $message = fetch_error('badlogin_strikes',
                            $vbulletin->options['bburl'],
                            $vbulletin->session->vars['sessionurl'], $strikes);

                    $message = rewrite_error($message);

                    $messages['errors'][] = $message;
                    $messages['fields'][] = $error_type = "username-member";
                    $messages['errors'][] = "";
                    $messages['fields'][] = $error_type = "password-member";
                }

            }

        }
    }

    $arr = array("valid_entries" => $valid_entries, "messages" => $messages,
            "url" => $url);

    json_headers($arr);

    break;

}
