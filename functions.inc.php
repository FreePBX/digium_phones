<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//  Copyright (c) 2011, Digium, Inc.
//

/**
 * database class
 */
global $db;

/**
 * configuration values from amportal.conf
 */
global $amp_conf;

/**
 * Sanitize path/file name input
 */
function digium_phones_sanitize_filepath($pathfile)
{
	$path = dirname($pathfile).'/';
	$file = basename($pathfile);

	$path = str_replace('..', '', $path);
	$path = str_replace('//', '/', $path);
	if (substr($path, 0, 2) == './') {
		$path = substr($path, 2);
	}
	return $path.preg_replace('/[^a-zA-Z0-9 _\-\.]/', '', $file);
}

/**
 * Get the path to the publicly accessible
 * http location to store files for phones
 * to download.
 * @return string path to the directory
 */
function digium_phones_get_http_path($url=False) {
	$path = "/digium_phones/";
	if ($url) {
		return $url . $path;
	}
	$webroot = $amp_conf['AMPWEBROOT'];
	if (!$webroot) {
		$webroot = '/var/www/html';
	}
	$path = $webroot . $path;
	if (!is_dir($path)) {
		mkdir($path, 0755, true);
	}
	return $path;
}

/**
 * Get list of status from presencestate module
 * @return array of types containing array of messages
 */
function digium_phones_presencestate_list() {
	$statuses=array();
	foreach (presencestate_list_get() as $state) {
		$type=$state['type'];
		if (empty($statuses[$type])) {
			$statuses[$type]=array();
		}
		if ($state['message']) {
			$statuses[$type][]=$state['message'];
		}
	}
	return $statuses;
}

/**
 * Configuration hook for retreive_conf.
 * Adds SIP and Queue functionality
*/
function digium_phones_get_config($engine) {
	global $core_conf;

	if (isset($core_conf) && is_a($core_conf, "core_conf")) {
		$core_conf->addSipGeneral('accept_outofcall_message','yes');
		$core_conf->addSipGeneral('auth_message_requests','no');
		$core_conf->addSipGeneral('outofcall_message_context','dpma_message_context');
	}

	if (function_exists('queues_list')) {
		global $queues_conf;
		$fqueues = queues_list();
		foreach ($fqueues as $queue) {
			$results = queues_get($queue[0]);
			if ($results['setinterfacevar'] == null) {
				global $db;

				$sql = "INSERT INTO queues_details VALUES(\"{$queue[0]}\", \"setinterfacevar\", \"yes\", 0);";

				$result = $db->query($sql);
				if (DB::IsError($result)) {
					echo $result->getDebugInfo();
					return false;
				}
				unset($result);
			}
		}
	}
}


/**
 * Configuration hook for retrieve_conf.
 * Adds dialplan to support redirect to voicemail feature
*/
function digium_phones_hookGet_config($engine) {
	global $ext;

	$execcond = '$[$["${REDIRECTING(reason)}" = "send_to_vm" | "${SIP_HEADER(X-Digium-Call-Feature)}" = "feature_send_to_vm" | "${PJSIP_HEADER(read,X-Digium-Call-Feature)}" = "feature_send_to_vm"] & "${ARG1}" != "novm"]';
	$ext->splice('macro-exten-vm', 's', 'checkrecord', new ext_execif($execcond, 'Macro', 'vm,${ARG1},DIRECTDIAL,${IVR_RETVM}'));
	$ext->splice('macro-exten-vm', 's', 'checkrecord', new ext_execif($execcond, 'MacroExit'));

	$execcond = '$["${SIP_HEADER(X-Digium-Call-Feature)}" = "feature_intercom" | "${PJSIP_HEADER(read,X-Digium-Call-Feature)}" = "feature_intercom"]';
	$ext->splice('macro-exten-vm', 's', 'checkrecord', new ext_execif($execcond, 'Gosub', 'ext-intercom,${INTERCOMCODE}${EXTTOCALL},1()'));
	$ext->splice('macro-exten-vm', 's', 'checkrecord', new ext_execif($execcond, 'MacroExit'));

	$execcond = '$["${SIP_HEADER(X-Digium-Call-Feature)}" = "feature_monitor" | "${PJSIP_HEADER(read,X-Digium-Call-Feature)}" = "feature_monitor"]';
	$ext->splice('macro-exten-vm', 's', 'checkrecord', new ext_execif($execcond, 'ChanSpy', '${DB(DEVICE/${EXTTOCALL}/dial},q'));
	$ext->splice('macro-exten-vm', 's', 'checkrecord', new ext_execif($execcond, 'MacroExit'));
}

/**
 * Configuration hook for core page init.
 * Adds configpageload and configprocess functions to users and extensions pages.
*/

function digium_phones_configpageinit($pagename) {
	global $currentcomponent;
	global $amp_conf;
	global $astman;

	if (!is_object($astman) || !$astman->connected()) { // Called in a 'reload', astman explicitly undefined.
		return;
	}

	$dpmalicensestatus = $astman->send_request('DPMALicenseStatus');
	if (empty($dpmalicensestatus['Response']) || $dpmalicensestatus['Response'] != "Success") {
		return;
	}

	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$extdisplay = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extension = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$tech_hardware = isset($_REQUEST['tech_hardware'])?$_REQUEST['tech_hardware']:null;

	// We only want to hook 'users' or 'extensions' pages.
	if ($pagename != 'users' && $pagename != 'extensions')  {
		return true;
	}

	// On a 'new' user, 'tech_hardware' is set, and there's no extension. Hook into the page.
	if ($tech_hardware != null ) {
		$currentcomponent->addguifunc('digium_phones_configpageload');
	} elseif ($action=="add") {
		// We don't need to display anything on an 'add', but we do need to handle returned data.
		if ($_REQUEST['display'] == 'users') {
			$usage_arr = framework_check_extension_usage($_REQUEST['extension']);
			if (empty($usage_arr)) {
				$currentcomponent->addprocessfunc('digium_phones_configprocess', 1);
			} else {
				$currentcomponent->addguifunc('digium_phones_configpageload');
			}
		} else {
			$currentcomponent->addprocessfunc('digium_phones_configprocess', 1);
		}
	} elseif ($extdisplay != '' || $pagename == 'users') {
		// We're now viewing an extension, so we need to display _and_ process.
		$currentcomponent->addguifunc('digium_phones_configpageload');
		$currentcomponent->addprocessfunc('digium_phones_configprocess', 1);
	}
}

/**
 * Configruation hook for page load.
 * Adds digium phones specific fields to pages.
*/
function digium_phones_configpageload() {
	global $currentcomponent;
	global $amp_conf;

	// Init vars from $_REQUEST[]
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$ext = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extn = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$display = isset($_REQUEST['display'])?$_REQUEST['display']:null;

	if ($ext==='') {
		$extdisplay = $extn;
	} else {
		$extdisplay = $ext;
	}

	include_once dirname(__FILE__).'/classes/digium_phones.php';
	$digium_phones = new digium_phones();

	if ($action != 'del') {
		$line = $digium_phones->get_extension_settings($extdisplay);

		$section = _("Digium Phones Contacts Options");
		$currentcomponent->addguielem($section, new gui_textbox('dp_prefix', $line['settings']['prefix'], _('Prefix'), _("The Prefix for use in Contacts application."), '', '', true, 0, false));
		$currentcomponent->addguielem($section, new gui_textbox('dp_first_name', $line['settings']['first_name'], _('First Name'), _("The First Name for use in Contacts application."), '', '', true, 0, false));
		$currentcomponent->addguielem($section, new gui_textbox('dp_second_name', $line['settings']['second_name'], _('Middle Name'), _("The Middle Name for use in Contacts application."), '', '', true, 0, false));
		$currentcomponent->addguielem($section, new gui_textbox('dp_last_name', $line['settings']['last_name'], _('Last Name'), _("The Last Name for use in Contacts application."), '', '', true, 0, false));
		$currentcomponent->addguielem($section, new gui_textbox('dp_suffix', $line['settings']['suffix'], _('Suffix'), _("The Suffix for use in Contacts application."), '', '', true, 0, false));
		$currentcomponent->addguielem($section, new gui_textbox('dp_organization', $line['settings']['organization'], _('Organization'), _("The Organization for use in Contacts application."), '', '', true, 0, false));
		$currentcomponent->addguielem($section, new gui_textbox('dp_job_title', $line['settings']['job_title'], _('Job Title'), _("The Job Title for use in Contacts application."), '', '', true, 0, false));
		$currentcomponent->addguielem($section, new gui_textbox('dp_location', $line['settings']['location'], _('Location'), _("The Location for use in Contacts application."), '', '', true, 0, false));
		$currentcomponent->addguielem($section, new gui_textbox('dp_email', $line['settings']['email'], _('E-Mail Address'), _("The E-Mail Address for use in Contacts application."), '', '', true, 0, false));
		$currentcomponent->addguielem($section, new gui_textbox('dp_notes', $line['settings']['notes'], _('Notes'), _("Notes about the user, for use in Contacts application."), '', '', true, 0, false));

		$section = _("Digium Phones Line Options");
		$currentcomponent->addguielem($section, new gui_textbox('dp_line_label', $line['settings']['line_label'], _('Line Label'), _("The Line Label for this extension."), '', '', true, 0, false));
		$currentcomponent->addguielem($section, new gui_textbox('dp_digit_map', $line['settings']['digit_map'], _('Digit Map'), _("The Digit Map for this extension."), '', '', true, 0, false));
		$currentcomponent->addguielem($section, new gui_textbox('dp_voicemail_uri', $line['settings']['voicemail_uri'], _('Voicemail URI'), _("The Voicemail URI for this extension.  Note that setting this option on a phone's primary line will disable visual voicemail."), '', '', true, 0, false));

		$currentcomponent->addoptlistitem('dp_transport', '', _("UDP (Default) or value from Network setting)"));
		$currentcomponent->addoptlistitem('dp_transport', 'tcp', _("TCP"));
		$currentcomponent->addoptlistitem('dp_transport', 'tls', _("TLS"));
		$currentcomponent->setoptlistopts('dp_transport', 'sort', false);
		$currentcomponent->addguielem($section, new gui_selectbox('dp_transport', $currentcomponent->getoptlist('dp_transport'), $line['settings']['transport'], _('Transport'), _("The Transport for this extension."), false));

		$currentcomponent->addoptlistitem('dp_media_encryption', '', _("None (Default)"));
		$currentcomponent->addoptlistitem('dp_media_encryption', 'sdes', _("SDES"));
		$currentcomponent->setoptlistopts('dp_media_encryption', 'sort', false);
		$currentcomponent->addguielem($section, new gui_selectbox('dp_media_encryption', $currentcomponent->getoptlist('dp_media_encryption'), $line['settings']['media_encryption'], _('Media Encryption'), _("The media encryption for this extension."), false));


		$currentcomponent->addguielem($section, new gui_textbox('dp_reregistration_timeout', $line['settings']['reregistration_timeout'], _('Re-registration TImeout'), _("The Re-registration Timeout for this extension."), '', '', true, 0, false));
		$currentcomponent->addguielem($section, new gui_textbox('dp_registration_retry_interval', $line['settings']['registration_retry_interval'], _('Registration Retry Interval'), _("The Registration Retry Interval for this extension."), '', '', true, 0, false));
		$currentcomponent->addguielem($section, new gui_textbox('dp_registration_max_retries', $line['settings']['registration_max_retries'], _('Registration Max Retries'), _("The Registration Max Retries for this extension."), '', '', true, 0, false));
	}
}

/**
 * Configuration hook for page processing.
 * Updates changes in custom fields to digium_phones databases.
*/
function digium_phones_configprocess() {
	$action = isset($_REQUEST['action'])?htmlspecialchars($_REQUEST['action']):null;
	$ext = isset($_REQUEST['extdisplay'])?htmlspecialchars($_REQUEST['extdisplay']):null;
	$extn = isset($_REQUEST['extension'])?htmlspecialchars($_REQUEST['extension']):null;
	$display = isset($_REQUEST['display'])?htmlspecialchars($_REQUEST['display']):null;

	if ($action == null) {
		return true;
	}

	if ($ext==='') {
		$extdisplay = $extn;
	} else {
		$extdisplay = $ext;
	}

	include_once dirname(__FILE__).'/classes/digium_phones.php';
	$digium_phones = new digium_phones();

	$line = $digium_phones->get_extension_settings($extdisplay);
	if ($line == null) {
		$line = array();
		$line['extension'] = $extdisplay;
	}

	$configkeys = array(
		// Contacts Options
		'prefix',
		'first_name',
		'second_name',
		'last_name',
		'suffix',
		'organization',
		'job_title',
		'location',
		'email',
		'notes',

		// Line Options
		'line_label',
		'digit_map',
		'voicemail_uri',
		'transport',
		'media_encryption',
		'reregistration_timeout',
		'registration_retry_interval',
		'registration_max_retries'
	);
	foreach ($configkeys as $key) {
		$line['settings'][$key] = isset($_REQUEST['dp_' . $key])?$_REQUEST['dp_' . $key]:null;
	}

	$digium_phones->update_extension_settings($line);
}

/**
 * This class contains all the functions to configure digium_phones via freepbx.
 * It is instantiated by retrieve_conf and used to build configuration files.
 */
class digium_phones_conf {
	var $use_warning_banner = false;
	var $digium_phones;
	var $autohint;
	var $sorted_users;

	/**
	 * Constructor: load main digium phones class and sort userlist
	 */
	public function digium_phones_conf() {
		include_once dirname(__FILE__).'/classes/digium_phones.php';
		$this->digium_phones = new digium_phones();
		$this->autohint = array();

		$this->sorted_users = $this->digium_phones->get_core_devices();
		if ($this->digium_phones->get_general('internal_phonebook_sort') == "description") {
			usort($this->sorted_users, array($this, "desccmp"));
		} else {
			usort($this->sorted_users, array($this, "extencmp"));
		}
	}

	/**
	 * Get list of configuration files to be written
	 * Called by retrieve_conf.
	*/
	public function get_filename() {
		global $amp_conf;
		global $astman;

		if (!is_object($astman) || !$astman->connected()) { // Called in a 'reload', astman explicitly undefined.
			return array();
		}

		$dpmalicensestatus = $astman->send_request('DPMALicenseStatus');
		if (empty($dpmalicensestatus['Response']) || $dpmalicensestatus['Response'] != "Success") {
			return array();
		}

		if ($this->digium_phones->get_general('easy_mode') == "yes") {
			foreach ($this->digium_phones->get_devices() as $deviceid=>$device) {
				$this->digium_phones->delete_device($device);
			}

			foreach ($this->digium_phones->get_core_devices() as $user) {
				if (strtolower($user['tech']) != 'sip' && strtolower($user['tech']) != 'pjsip') {
					continue;
				}
				$device = array();
				$device['lines'] = array();
				$device['phonebooks'] = array();
				$device['settings'] = array();
				$device['id'] = $user['id'];
				$device['name'] = $user['description'];
				$l = array();
				$l['id'] = 0;
				$l['extension'] = $user['id'];
				$l['settings'] = array();
				$device['lines'][] = $l;
				$pb = array();
				$pb['phonebookid'] = -1;
				$device['phonebooks'][] = $pb;
				$device['settings']['rapiddial'] = -1;
				$device['settings']['record_own_calls'] = "yes";
				$device['settings']['send_to_vm'] = "yes";
				$device['settings']['vm_require_pin'] = "no";
				$this->digium_phones->add_device($device);
			}
			$this->digium_phones->read_devices();

			foreach ($this->digium_phones->get_queues() as $queueid=>$oldqueue) {
				$this->digium_phones->delete_queue($oldqueue);

				$queue = array();
				$queue['id'] = $queueid;
				if (!empty($oldqueue['entries'])) {
					foreach ($oldqueue['entries'] as $entryid=>$oldentry) {
						if ($oldentry['member'] == false) {
							/* Purge all the managers */
							continue;
						}
						$entry = array();
						$entry['deviceid'] = $entryid;
						$entry['permission'] = "details";
						$queue['entries'][] = $entry;
					}
				}
				$this->digium_phones->add_queue($queue);
			}

			$this->digium_phones->read_queues();
		}

		$files = array();
		$files[] = 'res_digium_phone_general.conf';
		$files[] = 'res_digium_phone_devices.conf';
		$files[] = 'res_digium_phone_applications.conf';
		$files[] = 'res_digium_phone_firmware.conf';

		@mkdir("{$amp_conf['ASTETCDIR']}/digium_phones/", 0755);
		// remove obsoleted contacts xml files
		foreach (glob("{$amp_conf['ASTETCDIR']}/digium_phones/contacts-*.xml") as $file) {
			unlink($file);
		}
		return $files;
	}

	/**
	 * Callback for sorting extensions by name
	*/
	function desccmp($a, $b) {
		return strcmp($a["description"], $b["description"]);
	}

	/**
	 * Callback for sorting extensions by number
	 */
	function extencmp($a, $b) {
		$aexten = $a["id"];
		$bexten = $b["id"];
		if (is_numeric($aexten) && is_numeric($bexten)) {
			return $aexten > $bexten;
		} else {
			return strcmp($aexten, $bexten);
		}
	}

	/**
	 * Generate individual configuration files.
	 * Called by retrieve_conf for each file specified by get_filename.
	 */
	public function generateConf($file) {
		switch($file) {
		case 'res_digium_phone_general.conf':
			// also generate http files
			include_once dirname(__FILE__).'/conf/digium_phones_http.php';
			digium_phones_http_generate_all($this);

			include_once dirname(__FILE__).'/conf/res_digium_phone_general.php';
			return res_digium_phone_general($this);

		case 'res_digium_phone_devices.conf':
			include_once dirname(__FILE__).'/conf/res_digium_phone_devices.php';
			return res_digium_phone_devices($this);

		case 'res_digium_phone_applications.conf':
			include_once dirname(__FILE__).'/conf/res_digium_phone_applications.php';
			return res_digium_phone_applications($this);

		case 'res_digium_phone_firmware.conf':
			include_once dirname(__FILE__).'/conf/res_digium_phone_firmware.php';
			return res_digium_phone_firmware($this);

		default:
			return '';
		}
	}
}
