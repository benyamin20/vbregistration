<?php


if (THIS_SCRIPT != 'site-registration')
{
    exit;
}

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
function prev_url()
{
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

/**
* Rewrite default error message if found
**/
function rewrite_error($message){
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
    
    return $message;
}
 
 
 /**
 *  get value in bytes
 **/
 function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch($last) {
        // The 'G' modifier is available since PHP 5.1.0
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
}


