<?php

//path setup
set_include_path(
        get_include_path() . PATH_SEPARATOR . realpath('../../../')
                . PATH_SEPARATOR . realpath('../../../includes/'));

/**
 *   Output valid JSON headers
 */
function json_headers($arr = null)
{
    header('Content-type: text/json');
    header('Content-type: application/json');
    echo json_encode($arr);
}

/**
 *   Avoids getting the download dialog in IE for some JSON requests
 */
function json_headers_ie_support($arr = null)
{
    header('Pragma: no-cache');
    header('Cache-Control: private, no-cache');
    header('Content-Disposition: inline; filename="files.json"');
    echo json_encode($arr);
}

/**
 *   Checks for a valid date
 */
function check_date($date)
{
    if (strlen($date) == 10) {
        $pattern = '/\.|\/|-/i'; // . or / or -
        preg_match($pattern, $date, $char);

        $array = preg_split($pattern, $date, -1, PREG_SPLIT_NO_EMPTY);

        if (strlen($array[2]) == 4) {
            // dd.mm.yyyy || dd-mm-yyyy
            if ($char[0] == "." || $char[0] == "-") {
                $month = $array[1];
                $day = $array[0];
                $year = $array[2];
            }
            // mm/dd/yyyy    # Common U.S. writing
            if ($char[0] == "/") {
                $month = $array[0];
                $day = $array[1];
                $year = $array[2];
            }
        }
        // yyyy-mm-dd    # iso 8601
        if (strlen($array[0]) == 4 && $char[0] == "-") {
            $month = $array[1];
            $day = $array[2];
            $year = $array[0];
        }
        if (checkdate($month, $day, $year)) { //Validate Gregorian date
            return TRUE;

        } else {
            return FALSE;
        }
    } else {
        return FALSE; // more or less 10 chars
    }
}

/*
    Setup temp dir for file upload if not specified
 */
if (!function_exists('sys_get_temp_dir')) {
    function sys_get_temp_dir()
    {
        if (!empty($_ENV['TMP'])) {
            return realpath($_ENV['TMP']);
        }
        if (!empty($_ENV['TMPDIR'])) {
            return realpath($_ENV['TMPDIR']);
        }
        if (!empty($_ENV['TEMP'])) {
            return realpath($_ENV['TEMP']);
        }
        $tempfile = tempnam(__FILE__, '');
        if (file_exists($tempfile)) {
            unlink($tempfile);
            return realpath(dirname($tempfile));
        }
        return null;
    }
}


/**
* get previous URL visited 
**/
function prev_url(){
    global $vbulletin;
    
    $string = $_SESSION['site_registration']['initial_page'];
    $search_str = $vbulletin->options['bburl'];

    if (empty($_SESSION['site_registration']['initial_page'])
            || stristr($string, $search_str) === FALSE) {
        $url = "index.php";
    } else {
        $url = $_SESSION['site_registration']['initial_page'];
    }

    return $url;
}


/*
    Script setup
 */
ini_set("display_errors", 1);
error_reporting(E_ALL & ~E_NOTICE & ~8192);

define('CSRF_PROTECTION', true);
define('THIS_SCRIPT', 'site-registration');

require_once("rfc822.php");
//include required files
chdir(realpath('../../../'));

require_once('global.php');
require_once('functions_user.php');
require_once('functions_misc.php');
require_once('functions_login.php');

if (!session_id()) {
    session_start();
}

/**
 * Operations
 **/
$vbulletin->input->clean_array_gpc('g', array('op' => TYPE_STR));

$op = $vbulletin->GPC['op'];

switch ($op) {

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

    $arr = array('token' => $security_token,);

    json_headers($arr);
    break;

//regenerate ajax token
case 'regenerate_token':
//generate captcha value

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
    $userdata = &datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
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

    if (empty($vbulletin->GPC['secret_question'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $error_type = "secret_question";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = $userdata->errors[0];
    } else {

    }

    if (empty($vbulletin->GPC['secret_answer'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $error_type = "secret_answer";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = $userdata->errors[0];
    } else {

    }

    if (empty($vbulletin->GPC['timezone'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
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

    if ($_FILES['photoimg']['name'] != "") {
        //do not use default image
        $valid_formats = array("jpg", "png", "gif", "bmp", "jpeg");

        if (isset($_POST) and $_SERVER['REQUEST_METHOD'] == "POST") {
            $name = $_FILES['photoimg']['name'];
            $size = $_FILES['photoimg']['size'];

            if (strlen($name)) {
                list($txt, $ext) = explode(".", $name);
                if (in_array($ext, $valid_formats)) {
                    if ($size < (1024 * 100)) {
                        $actual_image_name = time() . mt_rand() . "." . $ext;

                        $uploaded = sys_get_temp_dir() . DIRECTORY_SEPARATOR
                                . $actual_image_name;

                        move_uploaded_file($_FILES["photoimg"]["tmp_name"],
                                $uploaded);

                        list($width, $height, $type, $attr) = getimagesize(
                                $uploaded);

                        if ($width > 100) {
                            $valid_entries = FALSE;
                            $error_type = "photoimg";
                            $messages['fields'][] = $error_type;
                            $messages['errors'][] = "Image width too large (try 100x100).";
                            @unlink($uploaded);
                            $error_w = TRUE;
                        }

                        if ($height > 100) {
                            $valid_entries = FALSE;
                            $error_type = "photoimg";
                            $messages['fields'][] = $error_type;
                            $messages['errors'][] = "Image height too large (try 100x100).";
                            @unlink($uploaded);
                            $error_h = TRUE;
                        }

                        if (!$error_h && !$error_w && $valid_entries) {
                            //image is valid copy to DB

                            $userid = $_SESSION['site_registration']['userid'];
                            $filedata = file_get_contents($uploaded);
                            $dateline = time();
                            $filename = $uploaded;
                            $visible = 1;
                            $filesize = filesize($uploaded);

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
                                 '" . $vbulletin->db->escape_string($width)
                                    . "',
                                 '" . $vbulletin->db->escape_string($height)
                                    . "'
                                 )
                            ";

                            /*insert query*/
                            $vbulletin->db->query_write($sql);

                            $rows = $vbulletin->db->affected_rows();
                        }

                    } else {
                        $valid_entries = FALSE;
                        $error_type = "photoimg";
                        $messages['fields'][] = $error_type;
                        $messages['errors'][] = "Image size too large (try < 100kb).";
                    }
                } else {
                    $valid_entries = FALSE;
                    $error_type = "photoimg";
                    $messages['fields'][] = $error_type;
                    $messages['errors'][] = "Invalid format: jpg, png, gif, bmp, jpeg only.";
                }

            } else {
                $valid_entries = FALSE;
                $error_type = "photoimg";
                $messages['fields'][] = $error_type;
                $messages['errors'][] = "Please select an image.";
            }
        }
    } else {
        //use default image
        $default_image = getcwd() . "/images/misc/unknown.gif";
        list($width, $height, $type, $attr) = getimagesize($default_image);

        $userid = $_SESSION['site_registration']['userid'];
        $filedata = file_get_contents($default_image);
        $dateline = time();
        $filename = $default_image;
        $visible = 1;
        $filesize = filesize($default_image);

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
             '" . $vbulletin->db->escape_string($width) . "',
             '" . $vbulletin->db->escape_string($height)
                . "'
             )
        ";

        /*insert query*/
        $vbulletin->db->query_write($sql);

        $rows = $vbulletin->db->affected_rows();
    }

    if ($valid_entries) {
        //update timezone

        $sql = "UPDATE " . TABLE_PREFIX . "user SET timezoneoffset = '"
                . $vbulletin->db->escape_string($vbulletin->GPC['timezone'])
                . "' WHERE userid = '" . $vbulletin->db->escape_string($userid)
                . "' ";

        /*insert query*/
        $vbulletin->db->query_write($sql);

        $rows = $vbulletin->db->affected_rows();

    }

    if ($valid_entries) {
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

    if ($valid_entries) {
        //update who can contact you
        /*
        $query = "UPDATE " . TABLE_PREFIX . "user SET options = options + "
                . $vbulletin->GPC['adminemail']
                . " WHERE NOT (options & "
                . $vbulletin->GPC['adminemail']
                . ")";
        
        $vbulletin->db->query_write($query);
        
        $query = "UPDATE " . TABLE_PREFIX . "user SET options = options + "
                . $vbulletin->GPC['showemail']
                . " WHERE NOT (options & "
                . $vbulletin->GPC['showemail']
                . ")";
        
        $vbulletin->db->query_write($query);
        
         */

        $user_data = &datamanager_init('User', $vbulletin, ERRTYPE_STANDARD);
        $vbulletin->userinfo = fetch_userinfo($userid);
        $user_data->set_existing($vbulletin->userinfo);

        $user_data->set_bitfield('options', "adminemail", $adminemail);
        $user_data->set_bitfield('options', "showemail", $showemail);

        $user_data->save();

        //start new session
        $vbulletin->userinfo = $vbulletin->db
                ->query_first(
                        "SELECT userid, usergroupid, membergroupids, infractiongroupids, 
            username, password, salt FROM " . TABLE_PREFIX
                                . "user 
            WHERE userid = " . $userid);

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
                    array('username' => TYPE_NOHTML, 'password' => TYPE_STR,
                            'confirm_password' => TYPE_STR,
                            'security_code' => TYPE_STR,
                            'terms_and_conditions' => TYPE_INT));

    if (empty($vbulletin->GPC['password'])
            || $vbulletin->GPC['password'] == md5("")) {

        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $error_type = "password";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = $userdata->errors[0];
    }

    if (empty($vbulletin->GPC['confirm_password'])
            || $vbulletin->GPC['confirm_password'] == md5("")) {
        unset($userdata->errors);
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $error_type = "confirm-password";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = $userdata->errors[0];
    }

    if (empty($vbulletin->GPC['security_code'])) {
        unset($userdata->errors);
        $valid_entries = FALSE;
        $error_type = "security-code";
        $userdata->error('fieldmissing');
        $messages['fields'][] = $error_type;
        $messages['errors'][] = $userdata->errors[0];
    }

    if ($vbulletin->GPC['terms_and_conditions'] != 1) {
        unset($userdata->errors);
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $error_type = "terms-and-conditions";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = "Please agree to the Terms & Conditions.";
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

    //$regex_username = '/^[a-zA-Z0-9]+([a-zA-Z0-9](_|-| )[a-zA-Z0-9])*[a-zA-Z0-9]+$/';
    unset($userdata->errors);
    if (!$userdata->verify_username($vbulletin->GPC['username'])) {
        $valid_entries = FALSE;

        $error_type = "username";
        $messages['fields'][] = $error_type;

        if (strlen($userdata->errors[0]) > 45) {
            $messages['errors'][] = "The username you chose is not valid.";
        } else {
            $messages['errors'][] = $userdata->errors[0];
        }

    }

    /*if (strlen($vbulletin->GPC['username']) > $vbulletin->options['maxuserlength']) {
        $valid_entries = FALSE;
    
        $error_type = "username";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = "The username you chose is not valid.";
    
    }*/

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
        $messages['errors'][] = "Sorry, this username is already taken.";
    }

    if (fetch_require_hvcheck('register')) {
        //check if CAPTCHA value is correct
        if (strtoupper($vbulletin->GPC['security_code'])
                != strtoupper(
                        $_SESSION['site_registration']['captcha']['answer'])) {
            $valid_entries = FALSE;

            $error_type = "security-code";
            $messages['fields'][] = $error_type;
            $messages['errors'][] = "Invalid security code.";
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
        $userdata->set('username', $_SESSION['site_registration']['username']);
        $userdata->set('password', $_SESSION['site_registration']['password']);
        //$userdata->set('referrerid', $vbulletin->GPC['referrername']);

        // set languageid
        $userdata->set('languageid', $vbulletin->userinfo['languageid']);

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

        // assign user to usergroup 3 if email needs verification
        if ($vbulletin->options['verifyemail']) {
            $newusergroupid = 3;
        } else if ($vbulletin->options['moderatenewmembers']
                OR $_SESSION['site_registration']['coppauser']) {
            $newusergroupid = 4;
        } else {
            $newusergroupid = 2;
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
        //$userdata->set('parentemail', $vbulletin->GPC['parentemail']);

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
            $username = $_SESSION['site_registration']['username'];
            $email = $_SESSION['site_registration']['email'];

            if ($vbulletin->options['verifyemail']) {
                $activateid = build_user_activation_id($userid,
                        (($vbulletin->options['moderatenewmembers']
                                OR $_SESSION['site_registration']['coppauser']) ? 4
                                : 2), 0);

                eval(fetch_email_phrases('activateaccount'));

                if (empty($subject)) {
                    $subject = "Please activate your account";
                }

                vbmail($email, $subject, $message, false);
            }
            
            
            if ($newusergroupid == 2)
			{
				if ($vbulletin->options['welcomemail'])
				{
					eval(fetch_email_phrases('welcomemail'));
					vbmail($email, $subject, $message);
				}
			}



            if ($vbulletin->options['verifyemail']) {
                //Redirect user to Activation Screen
                $url = "register.php?step=activate";
            }else{
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
                $subject = "Please activate your account";
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

    //check if variables are set
    if ($vbulletin->options['reqbirthday']
            || !empty($vbulletin->GPC['birthdate'])) {
        if (empty($vbulletin->GPC['birthdate'])) {
            $valid_entries = FALSE;
            $userdata->error('fieldmissing');
            $messages['errors'][] = $message = "Invalid date.";
            $messages['fields'][] = $error_type = "datepicker";

        } else {
            //validate if 13+
            $current['year'] = date('Y');
            $current['month'] = date('m');
            $current['day'] = date('d');

            //mm/dd/yyyy
            $date_parts = explode("/", $vbulletin->GPC['birthdate']);

            $month = $date_parts[0];
            $year = $date_parts[2];
            $day = $date_parts[1];

            if (check_date($vbulletin->GPC['birthdate'])) {

                if ($vbulletin->options['usecoppa']) {
                    if ($year > 1970
                            AND mktime(0, 0, 0, $month, $day, $year)
                                    > mktime(0, 0, 0, $current['month'],
                                            $current['day'],
                                            $current['year'] - 13)) {
                        $valid_entries = FALSE;
                        $messages['errors'][] = $message = "You must be over 13 to register";
                        $messages['fields'][] = $error_type = "datepicker";
                        //fetch_error('under_thirteen_registration_denied');

                        if ($vbulletin->options['checkcoppa']) {
                            vbsetcookie('coppaage',
                                    $month . '-' . $day . '-' . $year, 1);
                        }

                        if ($vbulletin->options['usecoppa'] == 2) {
                            $valid_entries = FALSE;
                            $messages['errors'][] = $message = "You must be over 13 to register";
                            $messages['fields'][] = $error_type = "datepicker";
                            //fetch_error('under_thirteen_registration_denied');
                        }

                        $_SESSION['site_registration']['coppauser'] = true;

                    } else {
                        $_SESSION['site_registration']['coppauser'] = false;
                    }
                }

            } else {
                $valid_entries = FALSE;
                $messages['errors'][] = $message = "Invalid date.";
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
            $messages['errors'][] = $message = "Invalid email address. No MX records found for domain.";
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
                        $messages['errors'][] = $message = "The email address you entered is already in use.";
                        $messages['fields'][] = $error_type = "email";
                    }
                }
            //}
        }

    } else {
        $valid_entries = FALSE;
        $messages['errors'][] = $message = "Invalid email.";
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

        $temp_table_query = "
            CREATE TABLE IF NOT EXISTS " . TABLE_PREFIX
                . "siteregistration_temp (
                email VARCHAR(128) NOT NULL DEFAULT '',
                birthday VARCHAR(12) NOT NULL DEFAULT '',
                initialpage VARCHAR(255) NOT NULL DEFAULT ''
            )";

        $vbulletin->db->query_write($temp_table_query);

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
        $userdata->error('fieldmissing');
        if (count($userdata->errors) > 1) {
            $message = $userdata->errors;
        } else {
            $message = "Sorry please check your username and password";
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

            if(preg_match("/register/i", $url)){
                $url = "login.php?do=login";
            }


            unset($_SESSION['site_registration']['initial_page']);

            $valid_login = TRUE;
            $message = "OK";

        }

    }

    //rewrite message

    $message = strip_tags($message);

    $search[0] = "/You have entered an invalid username or password./";
    $replace[0] = "<b>You have entered an invalid username or password.</b>";

    $search[1] = "/Please press the back button, enter the correct details and try again./";
    $replace[1] = "";

    $search[2] = "/Don't forget that the password is case sensitive./";
    $replace[2] = "Your password is case sensitive.";

    $search[3] = "/Forgotten your password\? Click here\!/";
    $replace[3] = "<br /><br />";

    $search[4] = "/out of 5 login attempts. After all 5 have been used, you will be unable to login for 15 minutes./";
    $replace[4] = "out of 5 login attempts, and you will be unable to log in for 15 minutes after all five have been used.";

    $message = preg_replace($search, $replace, $message);

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

    if (!$userdata->verify_username($vbulletin->GPC['username'])) {
        $valid_entries = FALSE;

        $error_type = "username";
        $messages['fields'][] = $error_type;

        if (strlen($userdata->errors[0]) > 45) {
            $messages['errors'][] = "The username you chose is not valid.";
        } else {
            $messages['errors'][] = $userdata->errors[0];
        }

    }

    /*if (strlen($vbulletin->GPC['username']) > $vbulletin->options['maxuserlength']) {
        $valid_entries = FALSE;
    
        $error_type = "username";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = "The username you chose is not valid.";
    
    }*/

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
        $messages['errors'][] = "Sorry, this username is already taken.";
    }

    if (empty($vbulletin->GPC['terms_and_conditions'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $messages['errors'][] = $message = "Please agree to the Terms & Conditions.";
        $messages['fields'][] = $error_type = "terms_and_conditions";
    }

    //$regexp = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/';

    //validate email
    if (is_valid_email_address($vbulletin->GPC['email'])) {

        list($email_name, $email_domain) = preg_split("/@/",
                $vbulletin->GPC['email']);

        if (!checkdnsrr($email_domain, "MX")) {
            $valid_entries = FALSE;
            $messages['errors'][] = $message = "Invalid email address. No MX records found for domain.";
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
                        $messages['errors'][] = $message = "The email address you entered is already in use.";
                        $messages['fields'][] = $error_type = "email";
                    }
                }
            //}
        }

    } else {
        $valid_entries = FALSE;
        $messages['errors'][] = $message = "Invalid email";
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

    //check if variables are set
    if ($vbulletin->options['reqbirthday']
            || !empty($vbulletin->GPC['birthdate'])) {
        if (empty($vbulletin->GPC['birthdate'])) {
            $valid_entries = FALSE;
            $userdata->error('fieldmissing');
            $messages['errors'][] = $message = "Please enter a valid date.";
            $messages['fields'][] = $error_type = "datepicker";
        } else {
            //validate if 13+
            $current['year'] = date('Y');
            $current['month'] = date('m');
            $current['day'] = date('d');

            //mm/dd/yyyy
            $date_parts = explode("/", $vbulletin->GPC['birthdate']);

            $month = $date_parts[0];
            $year = $date_parts[2];
            $day = $date_parts[1];

            if (check_date($vbulletin->GPC['birthdate'])) {

                if ($vbulletin->options['usecoppa']) {
                    if ($year > 1970
                            AND mktime(0, 0, 0, $month, $day, $year)
                                    > mktime(0, 0, 0, $current['month'],
                                            $current['day'],
                                            $current['year'] - 13)) {
                        $valid_entries = FALSE;
                        $messages['errors'][] = $message = "You must be over 13 to register";
                        $messages['fields'][] = $error_type = "datepicker";
                        //fetch_error('under_thirteen_registration_denied');

                        if ($vbulletin->options['checkcoppa']) {
                            vbsetcookie('coppaage',
                                    $month . '-' . $day . '-' . $year, 1);
                        }

                        if ($vbulletin->options['usecoppa'] == 2) {
                            $valid_entries = FALSE;
                            $messages['errors'][] = $message = "You must be over 13 to register";
                            $messages['fields'][] = $error_type = "datepicker";
                            //fetch_error('under_thirteen_registration_denied');
                        }

                        $_SESSION['site_registration']['coppauser'] = true;

                    } else {
                        $_SESSION['site_registration']['coppauser'] = false;
                    }
                }

            } else {
                $valid_entries = FALSE;
                $messages['errors'][] = $message = "Invalid date.";
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

        if ($fbID) {
            /*insert query*/
            $vbulletin->db
                    ->query_write(
                            "INSERT IGNORE INTO " . TABLE_PREFIX
                                    . "user (usergroupid, email, birthday, username) VALUES ('2', '"
                                    . $vbulletin->db
                                            ->escape_string(
                                                    $vbulletin->GPC['email'])
                                    . "', '" . $birthday
                                    . "',
                 '" . $vbulletin->GPC['username'] . "')");
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
        
		

        if (strlen($activationid) === 40) {
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
            
            if ($vbulletin->options['welcomemail']){
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
        $userdata->error('fieldmissing');
        $messages['errors'][] = $message = $userdata->errors[0];
        $messages['fields'][] = $error_type = "username-member";

    }

    //check if variables are set
    if (empty($vbulletin->GPC['password'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $messages['errors'][] = $message = $userdata->errors[0];
        $messages['fields'][] = $error_type = "password-member";
    }

    //check if variables are set
    if ($vbulletin->GPC['password'] == md5("")) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $messages['errors'][] = $message = $userdata->errors[0];
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

                    if (strlen($activationid) == 40) {
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

                    $message = strip_tags($message);

                    $search[0] = "/You have entered an invalid username or password./";
                    $replace[0] = "<b>You have entered an invalid username or password.</b>";

                    $search[1] = "/Please press the back button, enter the correct details and try again./";
                    $replace[1] = "";

                    $search[2] = "/Don't forget that the password is case sensitive./";
                    $replace[2] = "Your password is case sensitive.";

                    $search[3] = "/Forgotten your password\? Click here\!/";
                    $replace[3] = "<br /><br />";

                    $search[4] = "/out of 5 login attempts. After all 5 have been used, you will be unable to login for 15 minutes./";
                    $replace[4] = "out of 5 login attempts, and you will be unable to log in for 15 minutes after all five have been used.";

                    $message = preg_replace($search, $replace, $message);

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

