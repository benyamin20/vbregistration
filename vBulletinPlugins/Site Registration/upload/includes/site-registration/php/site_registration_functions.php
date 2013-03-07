<?php

if (THIS_SCRIPT != 'site-registration') {
	exit;
}

/**
 *   Output valid JSON headers
 */
function json_headers($arr = null) {
	header('Content-type: text/json');
	header('Content-type: application/json');
	echo json_encode($arr);
}

/**
 *   Avoids getting the download dialog in IE for some JSON requests
 */
function json_headers_ie_support($arr = null) {
	header('Pragma: no-cache');
	header('Cache-Control: private, no-cache');
	header('Content-Disposition: inline; filename="files.json"');
	echo json_encode($arr);
}

/**
 *   Checks for a valid date
 */
function check_date($date) {
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
	function sys_get_temp_dir() {
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
function prev_url ()
{
    global $vbulletin;

    $string = $_SESSION['site_registration']['initial_page'];
    $search_str = $vbulletin->options['bburl'];

    if (empty($_SESSION['site_registration']['initial_page']) ||
             stristr($string, $search_str) === FALSE) {
        $url = "index.php";
    } else {
        $url = $_SESSION['site_registration']['initial_page'];
    }

    $sql = "SELECT initialpage FROM " . TABLE_PREFIX .
             "siteregistration_temp
                            WHERE email = '" .
             $vbulletin->db->escape_string(
                    $_SESSION['site_registration']['email']) .
             "'
                            AND   birthday = '" .
             $vbulletin->db->escape_string(
                    $_SESSION['site_registration']['birthday']) . "' ";

    $rs = $vbulletin->db->query_first($sql);

    if (is_array($rs)) {
        $url = $rs['initialpage'];
    }

    return $url;
}

/**
 * Rewrite default error message if found
 **/
function rewrite_error($message) {
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
	$last = strtolower($val[strlen($val) - 1]);
	switch ($last) {
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

/**
 * Create a thumbnail image from $inputFileName no taller or wider than
 * $maxSize. Returns the new image resource or false on error.
 * Author: mthorn.net
 */
function thumbnail($inputFileName, $maxSize = 100) {
	$info = getimagesize($inputFileName);

	$type = isset($info['type']) ? $info['type'] : $info[2];

	// Check support of file type
	if (!(imagetypes() & $type)) {
		// Server does not support file type
		return false;
	}

	$width = isset($info['width']) ? $info['width'] : $info[0];
	$height = isset($info['height']) ? $info['height'] : $info[1];

	// Calculate aspect ratio
	$wRatio = $maxSize / $width;
	$hRatio = $maxSize / $height;

	// Using imagecreatefromstring will automatically detect the file type
	$sourceImage = imagecreatefromstring(file_get_contents($inputFileName));

	// Calculate a proportional width and height no larger than the max size.
	if (($width <= $maxSize) && ($height <= $maxSize)) {
		// Input is smaller than thumbnail, do nothing
		return $sourceImage;
	} elseif (($wRatio * $height) < $maxSize) {
		// Image is horizontal
		$tHeight = ceil($wRatio * $height);
		$tWidth = $maxSize;
	} else {
		// Image is vertical
		$tWidth = ceil($hRatio * $width);
		$tHeight = $maxSize;
	}

	$thumb = imagecreatetruecolor($tWidth, $tHeight);

	if ($sourceImage === false) {
		// Could not load image
		return false;
	}

	// Copy resampled makes a smooth thumbnail
	imagecopyresampled($thumb, $sourceImage, 0, 0, 0, 0, $tWidth, $tHeight,
			$width, $height);
	imagecolortransparent($thumb, imagecolorallocate($thumb, 0, 0, 0));
	imagedestroy($sourceImage);

	return $thumb;
}

/**
 * Save the image to a file. Type is determined from the extension.
 * $quality is only used for jpegs.
 * Author: mthorn.net
 */
function imageToFile($im, $fileName, $quality = 75) {
	if (!$im || file_exists($fileName)) {
		return false;
	}

	$ext = strtolower(substr($fileName, strrpos($fileName, '.')));

	switch ($ext) {
	case '.gif':
		imagegif($im, $fileName);
		break;
	case '.jpg':
	case '.jpeg':
		imagejpeg($im, $fileName, $quality);
		break;
	case '.png':
		imagealphablending($im, false);
		imagesavealpha($im, true);
		imagepng($im, $fileName);
		break;
	case '.bmp':
		imagewbmp($im, $fileName);
		break;
	default:
		return false;
	}

	return true;
}



/**
 *     Get a text between tags
 **/
function getTextBetweenTags($string, $tagname) {
	$pattern = "/<$tagname ?.*>(.*)<\/$tagname>/";
	preg_match($pattern, $string, $matches);
	return $matches[1];
}


/**
 *    Check if a file is animated or not.
 **/
function is_ani ($filename){
    if (! ($fh = @fopen($filename, 'rb')))
        return false;
    $count = 0;
    // an animated gif contains multiple "frames", with each frame having a
    // header made up of:
    // * a static 4-byte sequence (\x00\x21\xF9\x04)
    // * 4 variable bytes
    // * a static 2-byte sequence (\x00\x2C)

    // We read through the file til we reach the end of the file, or we've found
    // at least 2 frame headers
    while (! feof($fh) && $count < 2) {
        $chunk = fread($fh, 1024 * 100); // read 100kb at a time
        $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00\x2C#s', $chunk,
                $matches);
    }

    fclose($fh);
    return $count > 1;
}

