<?php

//path setup
set_include_path(
        get_include_path() . PATH_SEPARATOR . realpath('../../../')
                . PATH_SEPARATOR . realpath('../../../includes/'));

function json_headers($arr = null)
{
    header('Content-type: text/json');
    header('Content-type: application/json');
    echo json_encode($arr);
}

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

ini_set("display_errors", 1);
error_reporting(E_ALL & ~E_NOTICE & ~8192);

define('CSRF_PROTECTION', true);
define('THIS_SCRIPT', 'site-registration');

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


case 'complete_your_profile':
    $userdata = &datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
    $valid_entries = TRUE;
    $messages = "";
 
    $vbulletin->input
            ->clean_array_gpc('p',
                    array('secret_question' => TYPE_STR,
                            'secret_answer' => TYPE_STR,
                            'receive_emails_from_administrators' => TYPE_INT,
                            'receive_emails_from_other_members' => TYPE_INT,
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

    if ($vbulletin->GPC['use_default_image'] == "") {
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

                        $uploaded = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $actual_image_name;

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
        $default_image = realpath(
                $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR
                        . "images/misc/unknown.gif");
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

        $sql = "REPLACE INTO " . TABLE_PREFIX
                . "user
                (timezoneoffset)
                VALUES
                ('"
                . $vbulletin->db->escape_string($vbulletin->GPC['timezone'])
                . "')";

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
        
        $userid     = $_SESSION['site_registration']['userid'];
        $question   = $vbulletin->GPC['secret_question'];
        $answer     = $vbulletin->GPC['secret_answer'];
        $salt       = $vbulletin->userinfo['salt'];
    

        /*insert query*/
        $vbulletin->db
                ->query_write(
                        "
            REPLACE INTO " . TABLE_PREFIX
                                . "siteregistration_security_details
            (userid,question,answer)
            VALUES
            (   '" . $vbulletin->db->escape_string($userid). "',
                AES_ENCRYPT('" . $vbulletin->db->escape_string($question). "','" . $salt . "'),
                AES_ENCRYPT('" . $vbulletin->db->escape_string($answer). "','" . $salt . "')
             )
        ");

    }

    if ($valid_entries) {
        //update who can contact you
        if (!empty($vbulletin->GPC['receive_emails_from_administrators'])) {
            $query = "UPDATE " . TABLE_PREFIX . "user SET options = options + "
                    . $vbulletin->GPC['receive_emails_from_administrators']
                    . " WHERE NOT (options & "
                    . $vbulletin->GPC['receive_emails_from_administrators']
                    . ")";

            $vbulletin->db->query_write($query);

        }

        if (!empty($vbulletin->GPC['receive_emails_from_other_members'])) {
            $query = "UPDATE " . TABLE_PREFIX . "user SET options = options + "
                    . $vbulletin->GPC['receive_emails_from_other_members']
                    . " WHERE NOT (options & "
                    . $vbulletin->GPC['receive_emails_from_other_members']
                    . ")";

            $vbulletin->db->query_write($query);
        }

    }

    $arr = array("valid_entries" => $valid_entries, "messages" => $messages,
            "rows" => $rows);

    json_headers($arr);

    break;

case 'validate_site_account_details':
    $userdata = &datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
    $valid_entries = TRUE;
    $messages = "";
    $vbulletin->input
            ->clean_array_gpc('p',
                    array('username' => TYPE_STR, 'password' => TYPE_STR,
                            'confirm_password' => TYPE_STR,
                            'security_code' => TYPE_STR,
                            'terms_and_conditions' => TYPE_INT));

    if (empty($vbulletin->GPC['username'])) {
        $valid_entries = FALSE;
    } else {

    }

    if (empty($vbulletin->GPC['password'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $error_type = "password";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = $userdata->errors[0];
    }

    if (empty($vbulletin->GPC['confirm_password'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $error_type = "confirm-password";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = $userdata->errors[0];
    }

    if (empty($vbulletin->GPC['security_code'])) {
        $valid_entries = FALSE;
        $error_type = "security-code";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = $userdata->errors[0];
    }

    if ($vbulletin->GPC['terms_and_conditions'] != 1) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $error_type = "terms-and-conditions";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = $userdata->errors[0];
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

    $regex_username = '/^[a-zA-Z0-9]+([a-zA-Z0-9](_|-| )[a-zA-Z0-9])*[a-zA-Z0-9]+$/';

    if (!preg_match($regex_username, $vbulletin->GPC['username'])) {
        $valid_entries = FALSE;

        $error_type = "username";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = "The username you chose is not valid.";

    }

    if (strlen($vbulletin->GPC['username']) > 25) {
        $valid_entries = FALSE;

        $error_type = "username";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = "The username you chose is not valid.";

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
        $messages['errors'][] = "Sorry, this username is already taken.";
    }

    //check if CAPTCHA value is correct
    if (strtoupper($vbulletin->GPC['security_code'])
            != strtoupper($_SESSION['site_registration']['captcha']['answer'])) {
        $valid_entries = FALSE;

        $error_type = "security-code";
        $messages['fields'][] = $error_type;
        $messages['errors'][] = "Invalid security code.";
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

        // set birthday
        $userdata->set('showbirthday', $vbulletin->GPC['showbirthday']);

        //mm/dd/yyyy
        $date_parts = explode("/", $_SESSION['site_registration']['birthday']);

        $month = $date_parts[0];
        $year = $date_parts[2];
        $day = $date_parts[1];

        $userdata
                ->set('birthday',
                        array('day' => $day, 'month' => $month, 'year' => $year));

        // assign user to usergroup 3 if email needs verification
        if ($vbulletin->options['verifyemail']) {
            $newusergroupid = 3;
        } else if ($vbulletin->options['moderatenewmembers']
                OR $vbulletin->GPC['coppauser']) {
            $newusergroupid = 4;
        } else {
            $newusergroupid = 2;
        }
        // set usergroupid
        $userdata->set('usergroupid', $newusergroupid);

        // set time options
        //$userdata->set_dst($vbulletin->GPC['dst']);
        //$userdata->set('timezoneoffset', $vbulletin->GPC['timezoneoffset']);

        // register IP address
        $userdata->set('ipaddress', IPADDRESS);

        $userdata->pre_save();

        if (!empty($userdata->errors)) {
            //errors?
            $valid_entries = FALSE;
            $messages = "An error ocurred please try again later.";
            // . var_export( $userdata->errors, true);

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

            $vbulletin->session->created = false;
            //process_new_login('', false, '');
            process_new_login('', '', '');

            //Send Activation Email: Refer to Automated Emails
            // send new user email
            $username = $_SESSION['site_registration']['username'];
            $email = $_SESSION['site_registration']['email'];

            $activateid = build_user_activation_id($userid,
                    (($vbulletin->options['moderatenewmembers']
                            OR $vbulletin->GPC['coppauser']) ? 4 : 2), 0);

            eval(fetch_email_phrases('activateaccount'));

            if (empty($subject)) {
                $subject = "Please activate your account";
            }

            vbmail($email, $subject, $message, true);

            //Redirect user to Activation Screen
            $url = "register.php?step=activate";
        }

    }

    $arr = array("valid_entries" => $valid_entries, "messages" => $messages,
            "url" => $url);

    json_headers($arr);

    break;

//case 'test':
//    echo (fetch_email_phrases('newuser', 0));
//break;

case 'resend_email':
    if (isset($_SESSION['site_registration']['email'])) {
        $username = $_SESSION['site_registration']['username'];
        $email = $_SESSION['site_registration']['email'];
        $userid = $_SESSION['site_registration']['userid'];

        $activateid = build_user_activation_id($userid,
                (($vbulletin->options['moderatenewmembers']
                        OR $vbulletin->GPC['coppauser']) ? 4 : 2), 0);

        eval(fetch_email_phrases('activateaccount'));

        if (empty($subject)) {
            $subject = "Please activate your account";
        }

        vbmail($email, $subject, $message, true);

        $messages = "Email sent!";

    } else {
        $messages = "Unable to send email, please try again later.";
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

    //check if variables are set
    if (empty($vbulletin->GPC['email'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $message = $userdata->errors[0];
        $error_type = "email";

    }

    $regexp = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/';

    //validate email
    if (preg_match($regexp, $vbulletin->GPC['email'])) {

        //check if email already exists on DB
        $user_exists = $db
                ->query_read_slave(
                        "
            SELECT userid, username, email, languageid
            FROM " . TABLE_PREFIX . "user
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
            $message = "The email address you entered is already in use.";
            $error_type = "email";
        }

    } else {
        $valid_entries = FALSE;
        $message = "Invalid email";
        $error_type = "email";
    }

    //check if variables are set
    if (empty($vbulletin->GPC['birthdate'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $message = $userdata->errors[0];
        $error_type = "datepicker";

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

        if ($year > 1970
                AND mktime(0, 0, 0, $month, $day, $year)
                        > mktime(0, 0, 0, $current['month'], $current['day'],
                                $current['year'] - 13)) {
            $valid_entries = FALSE;
            $message = "You must be over 13 to register";
            //fetch_error('under_thirteen_registration_denied');
        } else {

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
            INSERT IGNORE INTO " . TABLE_PREFIX
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
        $url = "/register.php?step=site-account-details";

        $token = md5(uniqid(microtime(), true));
        $token_time = time();
        $form = "create_site_account_first_step";
        $_SESSION['site_registration'][$form . '_token'] = array(
                'token' => $token, 'time' => $token_time);

        $_SESSION['site_registration']['email'] = $vbulletin->GPC['email'];
        $_SESSION['site_registration']['birthday'] = $vbulletin
                ->GPC['birthdate'];

    }

    $arr = array("valid_entries" => $valid_entries,
            "error_type" => $error_type, "message" => $message, "url" => $url,
            "rows" => $rows);

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
                    array('vb_login_username' => TYPE_STR,
                            'vb_login_password' => TYPE_STR,
                            'vb_login_md5password' => TYPE_STR,
                            'vb_login_md5password_utf' => TYPE_STR));

    //check if variables are set
    if (empty($vbulletin->GPC['vb_login_username'])
            OR empty($vbulletin->GPC['vb_login_password'])) {
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
                        array('vb_login_username' => TYPE_STR,
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
                ->GPC['vb_login_md5password_utf'] = md5(
                $vbulletin->GPC['vb_login_password']);

        if (!verify_authentication($vbulletin->GPC['vb_login_username'],
                $vbulletin->GPC['vb_login_password'],
                $vbulletin->GPC['vb_login_md5password'],
                $vbulletin->GPC['vb_login_md5password_utf'], 1, true)) {

            // check password
            exec_strike_user($vbulletin->userinfo['username']);

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
        } else {
            // create new session
            exec_unstrike_user($vbulletin->GPC['vb_login_username']);
            process_new_login('', '', '');

            $url = "login.php?do=login";

            $valid_login = TRUE;
            $message = "OK";

        }

    }

    $arr = array("valid_login" => $valid_login, "message" => $message,
            "url" => $url);

    json_headers($arr);

    break;

case 'activate':
    $userdata = &datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
    $valid_entries = TRUE;
    $message = "";

    //clean variables
    $vbulletin->input->clean_array_gpc('p', array('email' => TYPE_STR, 'birthdate' => TYPE_STR, 'username' => TYPE_STR, 'avatar' => TYPE_STR));

    //check if variables are set
    if(empty($vbulletin->GPC['email'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $message = $userdata->errors[0];
        $error_type = "email";
    }

    $regexp = '/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9-]+(?:\.[a-zA-Z0-9-]+)*$/';

    //validate email
    if (preg_match($regexp, $vbulletin->GPC['email'])) {
        //check if email already exists on DB
        $user_exists = $db->query_read_slave("SELECT userid, username, email, languageid FROM " . TABLE_PREFIX . "user WHERE UPPER(email) = '". strtoupper($db->escape_string($vbulletin->GPC['email'])) . "'");

        if($db->num_rows($user_exists)) {
            $valid_entries = FALSE;
            $message = "The email address you entered is already in use.";
            $error_type = "email";
        }
    } else {
        $valid_entries = FALSE;
        $message = "Invalid email";
        $error_type = "email";
    }

    //check if variables are set
    if (empty($vbulletin->GPC['birthdate'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $message = $userdata->errors[0];
        $error_type = "datepicker";
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

        if($year > 1970 AND mktime(0, 0, 0, $month, $day, $year) > mktime(0, 0, 0, $current['month'], $current['day'], $current['year'] - 13)) {
            $valid_entries = FALSE;
            $message = "You must be over 13 to register";
            //fetch_error('under_thirteen_registration_denied');
        } else {}
    }

    if($valid_entries) {
        /*insert query*/
        $vbulletin->db->query_write("INSERT IGNORE INTO ". TABLE_PREFIX ."user (email, birthday, username) VALUES ('". $vbulletin->db->escape_string($vbulletin->GPC['email']) ."', '" . $vbulletin->db->escape_string($vbulletin->GPC['birthdate']) . "',
             '". $vbulletin->GPC['username'] . "')");


        $rows = $vbulletin->db->affected_rows();
        $valid_entries = TRUE;
        $message = "OK";
        $url = "register.php?step=activate";

        $parts = explode(".", $avatar);
        $extension = end($parts);
        $filedata = file_get_contents($avatar);
        $dateline = time();        
        $visible  = 1;
        $filesize = filesize($avatar);
        $filename = substr(md5(time()), 0, 10) .".". $extension;

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


        $token = md5(uniqid(microtime(), true));
        $token_time = time();
        $form = "site-account-details";
        $_SESSION['site_registration'][$form . '_token'] = array('token' => $token, 'time' => $token_time);

        $email = $vbulletin->db->escape_string($vbulletin->GPC['email']);

        //Verify if the account already exists...                                
        $sql = "SELECT userid FROM " . TABLE_PREFIX . "user WHERE email = '$email'";

        $data = $vbulletin->db->query_first($sql);

        $userid = $data["userid"];

        //Send Activation Email: Refer to Automated Emails
        // send new user email
        $_SESSION['site_registration']['userid'] = $userid;
        $username = $_SESSION['site_registration']['username'] = $vbulletin->GPC['username'];
        $email    = $_SESSION['site_registration']['email'] = $vbulletin->GPC['email'];
        $_SESSION['site_registration']['birthday'] = $vbulletin->GPC['birthdate'];

        $activateid = build_user_activation_id($userid, (($vbulletin->options['moderatenewmembers'] OR $vbulletin->GPC['coppauser']) ? 4 : 2), 0);

        eval(fetch_email_phrases('activateaccount'));

        if (empty($subject)) {
            $subject = "Please activate your account";
        }

        vbmail($email, $subject, $message, true);
    }

    $arr = array("valid_entries" => $valid_entries,
            "error_type" => $error_type, "message" => $message, "url" => $url,
            "rows" => $rows);

    json_headers($arr);

break;

}
