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
$op = $_GET['op'];

switch ($op) {

case 'validate_site_account_details':
    $userdata = &datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
    $valid_entries = FALSE;
    $message = "";
    $vbulletin->input
            ->clean_array_gpc('p',
                    array('username' => TYPE_STR, 'password' => TYPE_STR,
                            'confirm_password' => TYPE_STR,
                            'security_code' => TYPE_STR));

    if (empty($vbulletin->GPC['username'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $error_type = "username";
        $message[$error_type] = $userdata->errors[0];
    }

    if (empty($vbulletin->GPC['password'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $error_type = "password";
        $message[$error_type] = $userdata->errors[0];
    }

    if (empty($vbulletin->GPC['confirm_password'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $error_type = "confirm_password";
        $message[$error_type] = $userdata->errors[0];
    }

    if (empty($vbulletin->GPC['security_code'])) {
        $valid_entries = FALSE;
        $userdata->error('fieldmissing');
        $error_type = "security_code";
        $message[$error_type] = $userdata->errors[0];
    }
    break;

//create site account on register.php
case 'create_site_account_first_step':
    $userdata = &datamanager_init('User', $vbulletin, ERRTYPE_ARRAY);
    $valid_entries = FALSE;
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

            $temp_table_query = "
            CREATE TEMPORARY TABLE IF NOT EXISTS " . TABLE_PREFIX
                    . "siteregistration_temp (
                email VARCHAR(128) NOT NULL DEFAULT '',
                birthday VARCHAR(12) NOT NULL DEFAULT ''
            )";

            $vbulletin->db->query_write($temp_table_query);

            /*insert query*/
            $vbulletin->db
                    ->query_write(
                            "
                INSERT IGNORE INTO " . TABLE_PREFIX
                                    . "siteregistration_temp
                (email,birthday)
                VALUES
                ('" . $vbulletin->db->escape_string($vbulletin->GPC['email'])
                                    . "',
                 '"
                                    . $vbulletin->db
                                            ->escape_string(
                                                    $vbulletin
                                                            ->GPC['birthdate'])
                                    . "')
            ");

            $rows = $vbulletin->db->affected_rows();

            $valid_entries = TRUE;
            $message = "OK";
            $url = "/register.php?step=site-account-details";

            $token = md5(uniqid(microtime(), true));
            $token_time = time();
            $form = "create_site_account_first_step";
            $_SESSION['validate'][$form . '_token'] = array('token' => $token,
                    'time' => $token_time);

            $_SESSION['validate']['email'] = $vbulletin->GPC['email'];
            $_SESSION['validate']['birthday'] = $vbulletin->GPC['birthdate'];
        }
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
            //$userdata->errors[0];
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

}

