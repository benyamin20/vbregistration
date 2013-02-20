<?php

if (!is_array($_SESSION['site_registration'])) {
	exit;
}

$customfields_other = '';
$customfields_profile = '';
$customfields_option = '';

$profilefields = $db
		->query_read_slave(
				"
					        SELECT *
					        FROM " . TABLE_PREFIX
						. "profilefield
					        WHERE editable > 0 AND required <> 0
					        ORDER BY displayorder
					    ");

while ($profilefield = $db->fetch_array($profilefields)) {

	$profilefieldname = "field$profilefield[profilefieldid]";
	$optionalname = $profilefieldname . '_opt';
	$optionalfield = '';
	$optional = '';
	$profilefield['title'] = $vbphrase[$profilefieldname . '_title'];
	$profilefield['description'] = $vbphrase[$profilefieldname . '_desc'];

	if (!$errorlist) {
		unset($vbulletin->userinfo["$profilefieldname"]);
	} elseif (isset($vbulletin->GPC['userfield']["$profilefieldname"])) {
		$vbulletin->userinfo["$profilefieldname"] = $vbulletin
				->GPC['userfield']["$profilefieldname"];
	}

	$custom_field_holder = '';

	if ($profilefield['type'] == 'input') {
		if ($profilefield['data'] !== '') {
			$vbulletin->userinfo["$profilefieldname"] = $profilefield['data'];
		} else {
			$vbulletin->userinfo["$profilefieldname"] = htmlspecialchars_uni(
					$vbulletin->userinfo["$profilefieldname"]);
		}
		eval(
				'$custom_field_holder = "'
						. fetch_template('userfield_textbox') . '";');
	} else if ($profilefield['type'] == 'textarea') {
		if ($profilefield['data'] !== '') {
			$vbulletin->userinfo["$profilefieldname"] = $profilefield['data'];
		} else {
			$vbulletin->userinfo["$profilefieldname"] = htmlspecialchars_uni(
					$vbulletin->userinfo["$profilefieldname"]);
		}
		eval(
				'$custom_field_holder = "'
						. fetch_template('userfield_textarea') . '";');
	} else if ($profilefield['type'] == 'select') {
		$data = unserialize($profilefield['data']);
		$selectbits = '';
		foreach ($data AS $key => $val) {
			$key++;
			$selected = '';
			if (isset($vbulletin->userinfo["$profilefieldname"])) {
				if (trim($val) == $vbulletin->userinfo["$profilefieldname"]) {
					$selected = 'selected="selected"';
					$foundselect = 1;
				}
			} else if ($profilefield['def'] AND $key == 1) {
				$selected = 'selected="selected"';
				$foundselect = 1;
			}

			eval(
					'$selectbits .= "'
							. fetch_template('userfield_select_option') . '";');
		}

		if ($profilefield['optional']) {
			if (!$foundselect AND $vbulletin->userinfo["$profilefieldname"]) {
				$optional = htmlspecialchars_uni(
						$vbulletin->userinfo["$profilefieldname"]);
			}
			eval(
					'$optionalfield = "'
							. fetch_template('userfield_optional_input') . '";');
		}
		if (!$foundselect) {
			$selected = 'selected="selected"';
		} else {
			$selected = '';
		}
		$show['noemptyoption'] = iif($profilefield['def'] != 2, true, false);
		eval(
				'$custom_field_holder = "' . fetch_template('userfield_select')
						. '";');
	} else if ($profilefield['type'] == 'radio') {
		$data = unserialize($profilefield['data']);
		$radiobits = '';
		$foundfield = 0;
		$perline = 0;
		$unclosedtr = true;

		foreach ($data AS $key => $val) {
			$key++;
			$checked = '';
			if (!$vbulletin->userinfo["$profilefieldname"] AND $key == 1
					AND $profilefield['def'] == 1) {
				$checked = 'checked="checked"';
			} else if (trim($val) == $vbulletin->userinfo["$profilefieldname"]) {
				$checked = 'checked="checked"';
				$foundfield = 1;
			}
			if ($perline == 0) {
				$radiobits .= '<tr>';
			}
			eval(
					'$radiobits .= "'
							. fetch_template('userfield_radio_option') . '";');
			$perline++;
			if ($profilefield['perline'] > 0
					AND $perline >= $profilefield['perline']) {
				$radiobits .= '</tr>';
				$perline = 0;
				$unclosedtr = false;
			}
		}
		if ($unclosedtr) {
			$radiobits .= '</tr>';
		}
		if ($profilefield['optional']) {
			if (!$foundfield AND $vbulletin->userinfo["$profilefieldname"]) {
				$optional = htmlspecialchars_uni(
						$vbulletin->userinfo["$profilefieldname"]);
			}
			eval(
					'$optionalfield = "'
							. fetch_template('userfield_optional_input') . '";');
		}
		eval(
				'$custom_field_holder = "' . fetch_template('userfield_radio')
						. '";');
	} else if ($profilefield['type'] == 'checkbox') {
		$data = unserialize($profilefield['data']);
		$radiobits = '';
		$perline = 0;
		$unclosedtr = true;
		foreach ($data AS $key => $val) {
			if ($vbulletin->userinfo["$profilefieldname"] & pow(2, $key)) {
				$checked = 'checked="checked"';
			} else {
				$checked = '';
			}
			$key++;
			if ($perline == 0) {
				$radiobits .= '<tr>';
			}
			eval(
					'$radiobits .= "'
							. fetch_template('userfield_checkbox_option')
							. '";');
			$perline++;
			if ($profilefield['perline'] > 0
					AND $perline >= $profilefield['perline']) {
				$radiobits .= '</tr>';
				$perline = 0;
				$unclosedtr = false;
			}
		}
		if ($unclosedtr) {
			$radiobits .= '</tr>';
		}
		eval(
				'$custom_field_holder = "' . fetch_template('userfield_radio')
						. '";');
	} else if ($profilefield['type'] == 'select_multiple') {
		$data = unserialize($profilefield['data']);
		$selectbits = '';
		$selected = '';

		if ($profilefield['height'] == 0) {
			$profilefield['height'] = count($data);
		}

		foreach ($data AS $key => $val) {
			if ($vbulletin->userinfo["$profilefieldname"] & pow(2, $key)) {
				$selected = 'selected="selected"';
			} else {
				$selected = '';
			}
			$key++;
			eval(
					'$selectbits .= "'
							. fetch_template('userfield_select_option') . '";');
		}
		eval(
				'$custom_field_holder = "'
						. fetch_template('userfield_select_multiple') . '";');
	}

	if ($profilefield['required'] == 2) {
		// not required to be filled in but still show
		$profile_variable = &$customfields_other;
	} else {// required to be filled in
		if ($profilefield['form']) {
			$profile_variable = &$customfields_option;
		} else {
			$profile_variable = &$customfields_profile;
		}
	}

	eval('$profile_variable .= "' . fetch_template('userfield_wrapper') . '";');
}

