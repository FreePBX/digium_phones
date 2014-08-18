<?php

/**
 * \file
 * FreePBX Digium Phones Config Module
 *
 * Copyright (c) 2011, Digium, Inc.
 *
 * Author: Jason Parker <jparker@digium.com>
 *
 * This program is free software, distributed under the terms of
 * the GNU General Public License Version 2. 
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * This module is included by module_admin prior to retrieve_conf
 * generating new configuration files.
 */

/**
 * database class
 */
global $db;

/**
 * configuration values from amportal.conf
 */
global $amp_conf;

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
 * Configuration hook for retreive_conf.
 * Adds SIP and Queue functionality
*/
function digium_phones_get_config($engine) {
	global $core_conf;

	if (isset($core_conf) && is_a($core_conf, "core_conf")) {
		$core_conf->addSipGeneral('accept_outofcall_messages','yes');
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

	$execcond = '$[$["${REDIRECTING(reason)}" = "send_to_vm" | "${SIP_HEADER(X-Digium-Call-Feature)}" = "feature_send_to_vm"] & "${ARG1}" != "novm"]';
	$ext->splice('macro-exten-vm', 's', 'checkrecord', new ext_execif($execcond, 'Macro', 'vm,${ARG1},DIRECTDIAL,${IVR_RETVM}'));
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

	if (!isset($astman)) { // Called in a 'reload', astman explicitly undefined.
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
 * Adds DPMA specific fields to pages.
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
		$currentcomponent->addoptlistitem('dp_transport', '', _("UDP (Default)"));
		$currentcomponent->addoptlistitem('dp_transport', 'tcp', _("TCP"));
		$currentcomponent->setoptlistopts('dp_transport', 'sort', false);
		$currentcomponent->addguielem($section, new gui_selectbox('dp_transport', $currentcomponent->getoptlist('dp_transport'), $line['settings']['transport'], _('Transport'), _("The Transport for this extension."), false));
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
	$action = isset($_REQUEST['action'])?$_REQUEST['action']:null;
	$ext = isset($_REQUEST['extdisplay'])?$_REQUEST['extdisplay']:null;
	$extn = isset($_REQUEST['extension'])?$_REQUEST['extension']:null;
	$display = isset($_REQUEST['display'])?$_REQUEST['display']:null;

	if ($action == null) {
		return true;
	}

	if ($ext==='') {
		$extdisplay = $extn;
	} else {
		$extdisplay = $ext;
	}

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

	public function digium_phones_conf() {
		require_once dirname(__FILE__).'/classes/digium_phones.php';
		$this->digium_phones = new digium_phones();
		$this->autohint = array();

		$this->sorted_users = $this->digium_phones->get_core_devices();
		if ($this->digium_phones->get_general('internal_phonebook_sort') == "description") {
			usort($this->sorted_users, array($this, "desccmp"));
		} else {
			usort($this->sorted_users, array($this, "extencmp"));
		}
	}

	public function get_filename() {
		global $amp_conf;
		global $astman;

		$dpmalicensestatus = $astman->send_request('DPMALicenseStatus');
		if (empty($dpmalicensestatus['Response']) || $dpmalicensestatus['Response'] != "Success") {
			return array();
		}

		if ($this->digium_phones->get_general('easy_mode') == "yes") {
			foreach ($this->digium_phones->get_devices() as $deviceid=>$device) {
				$this->digium_phones->delete_device($device);
			}

			foreach ($this->digium_phones->get_core_devices() as $user) {
				if (strtolower($user['tech']) != 'sip') {
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
				$this->digium_phones->add_device($device);
			}
			$this->digium_phones->read_devices();

			foreach ($this->digium_phones->get_queues() as $queueid=>$oldqueue) {
				$this->digium_phones->delete_queue($oldqueue);

				$queue = array();
				$queue['id'] = $queueid;
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
				$this->digium_phones->add_queue($queue);
			}

			$this->digium_phones->read_queues();
		}

		$files = array();
		$files[] = 'res_digium_phone_general.conf';
		$files[] = 'res_digium_phone_devices.conf';
		$files[] = 'res_digium_phone_applications.conf';
		$files[] = 'res_digium_phone_firmware.conf';
		foreach ($this->digium_phones->get_phonebooks() as $phonebookid=>$phonebook) {
			if ($phonebookid == -1) {
				continue;
			}
			$files[] = 'digium_phones/contacts-' . $phonebook['id'] . '.xml';
		}
		foreach ($this->digium_phones->get_devices() as $deviceid=>$device) {
			$files[] = 'digium_phones/contacts-internal-' . $device['id'] . '.xml';
		}

		mkdir("{$amp_conf['ASTETCDIR']}/digium_phones/", 0755);
		foreach (glob("{$amp_conf['ASTETCDIR']}/digium_phones/contacts-internal-*.xml") as $file) {
			unlink($file);
		}
		return $files;
	}

	function desccmp($a, $b) {
		return strcmp($a["description"], $b["description"]);
	}

	function extencmp($a, $b) {
		$aexten = $a["id"];
		$bexten = $b["id"];
		if (is_numeric($aexten) && is_numeric($bexten)) {
			return $aexten > $bexten;
		} else {
			return strcmp($aexten, $bexten);
		}
	}

	public function generateConf($file) {
		if (preg_match('/^digium_phones\/contacts-(internal-)?(\d+).xml/', $file, $matches)) {
			global $amp_conf;

			$output = array();
			$extension = $matches[2];

			if ($matches[1] == "internal-") {
				$phonebook = array();
				$phonebook['entries'] = array();
				$phonebook['name'] = $matches[1] . $extension;

				foreach ($this->sorted_users as $user) {
					$hasline = false;
					$device = $this->digium_phones->get_device($extension);
					foreach ($device['lines'] as $lineid=>$line) {
						if ($line['extension'] == $user['id']) {
							$hasline = true;
							break;
						}
					}
					if (!$hasline) {
						$e = array();
						$e['extension'] = $user['id'];
						$e['settings']['type'] = 'internal';
						$phonebook['entries'][] = $e;
					}
				}
			} else {
				$phonebooks = $this->digium_phones->get_phonebooks();
				$phonebook = $phonebooks[$extension];
			}

			$output[] = '<contacts ';
			$output[] = '  group_name="' . $phonebook['name'] . '"';
			$output[] = '  editable="0"';
			$output[] = '>';

			foreach ($phonebook['entries'] as $entryid=>$entry) {
				$extension=$entry['extension'];

				if (!array_key_exists($extension,$this->autohint)) {
					$this->autohint[$extension] = false;
					foreach ($this->digium_phones->get_devices() as $device) {
						foreach ($device['lines'] as $l) {
							if ($entry['extension'] == $l['extension']) {
								/* This is a Digium Phone. */
								$this->autohint[$extension] = true;
							}
						}
					}
				}
				$customexten = false;

				if ($entry['settings']['type'] == 'internal') {
					$user = $this->digium_phones->get_core_device($entry['extension']);
					if ($user != null) {
						$label = $user['description'];
					} else {
						$label = '';
					}
				} else {
					$customexten = true;

					$label = $entry['settings']['label'];
				}

				$line = $this->digium_phones->get_extension_settings($entry['extension']);

				$output[] = '  <contact';

				if ($line != null) {
					$output[] = '    prefix="' . htmlspecialchars($line['settings']['prefix']) . '"';
					$output[] = '    first_name="' . htmlspecialchars(($line['settings']['first_name'] != null)?$line['settings']['first_name']:$label) . '"';
					$output[] = '    second_name="' . htmlspecialchars($line['settings']['second_name']) . '"';
					$output[] = '    last_name="' . htmlspecialchars($line['settings']['last_name']) . '"';
					$output[] = '    suffix="' . htmlspecialchars($line['settings']['suffix']) . '"';
					$output[] = '    organization="' . htmlspecialchars($line['settings']['organization']) . '"';
					$output[] = '    job_title="' . htmlspecialchars($line['settings']['job_title']) . '"';
					$output[] = '    location="' . htmlspecialchars($line['settings']['location']) . '"';
					$output[] = '    notes="' . htmlspecialchars($line['settings']['notes']) . '"';
				} else {
					$output[] = '    first_name="' . htmlspecialchars($label) . '"';
					$output[] = '    last_name=""';
					$output[] = '    organization=""';
				}

				if ($customexten == false) {
					// TODO: Not all contacts are SIP.  Or maybe it doesn't matter because it's SIP to Asterisk.  Who knows?
					$output[] = '    contact_type="sip"';
					$output[] = '    account_id="' . htmlspecialchars($entry['extension']) . '"';
					if ($this->autohint[$extension]) {
						$output[] = '    subscribe_to="auto_hint_' . htmlspecialchars($entry['extension']) . '"';
					} else {
						$output[] = '    subscribe_to="' . htmlspecialchars($entry['extension']) . '"';
					}

					$user = $this->digium_phones->get_core_user($entry['extension']);
					if ($user != null && $user['voicemail'] != null && $user['voicemail'] != "novm") {
						$output[] = '    has_voicemail="1"';
					}
					if ($entry['settings']['can_intercom'] != null) {
						$output[] = '    can_intercom="1"';
					}
					if ($entry['settings']['can_monitor'] != null) {
						$output[] = '    can_monitor="1"';
					}
				} else {
					$output[] = '    contact_type="sip|external"';
					if ($entry['settings']['subscribe_to'] != null && $entry['settings']['subscribe_to'] == 'on') {
						if ($entry['settings']['subscription_url'] != null) {
							$output[] = '    subscribe_to="' . htmlspecialchars($entry['settings']['subscription_url']) . '"';
						} else {
							$output[] = '    subscribe_to="' . htmlspecialchars($entry['extension']) . '"';
						}
					}
				}
				$output[] = '  >';

				$output[] = '    <numbers>';
				$output[] = '      <number dial="' . htmlspecialchars($entry['extension']) . '" label="Extension" primary="1" />';
				$output[] = '    </numbers>';

				if ($line != null) {
					$output[] = '    <emails>';
					$output[] = '      <email address="' . htmlspecialchars($line['settings']['email']) . '" label="Primary" primary="1" />';
					$output[] = '    </emails>';
				}

				$output[] = '  </contact>';
			}

			$output[] = '</contacts>';
			$output[] = '';

			return implode("\n", $output);
		}

		switch($file) {
		case 'res_digium_phone_general.conf':
			global $amp_conf;

			$output = array();

			$output[] = "file_directory={$amp_conf['ASTETCDIR']}/digium_phones/";
			$output[] = "globalpin={$this->digium_phones->get_general('globalpin')}";
			$output[] = "userlist_auth={$this->digium_phones->get_general('userlist_auth')}";
			$output[] = "config_auth={$this->digium_phones->get_general('config_auth')}";
			$output[] = "mdns_address={$this->digium_phones->get_general('mdns_address')}";
			$output[] = "mdns_port={$this->digium_phones->get_general('mdns_port')}";
			$output[] = "service_name={$this->digium_phones->get_general('service_name')}";
			/* note: option firmware_package_directory is deprecated in dpma, but leaving this for now */
			$output[] = "firmware_package_directory=" . digium_phones_get_http_path();

			$output[] = "";

			return implode("\n", $output);

		case 'res_digium_phone_devices.conf':
			global $amp_conf;

			$queues = $this->digium_phones->get_queues();
			$firmware_manager = $this->digium_phones->get_firmware_manager();
			$default_locale = $this->digium_phones->get_general('active_locale');
			$output = array();

			foreach ($this->digium_phones->get_devices() as $deviceid=>$device) {
				$doutput[] = "[{$deviceid}]";
				$doutput[] = "type=phone";
				$doutput[] = "full_name={$device['name']}";

				$parkext = "";
				if (function_exists('parking_getconfig')) {
					// Old and busted parking module
					$parking = parking_getconfig();
					if ($parking != null && $parking['parkingenabled'] != "" && $parking['parkext'] != "") {
						$parkext = $parking['parkext'];
					}
				} else if (function_exists('parking_get')) {
					// Fancy new 'park plus' module
					$parking = parking_get();
					if ($parking != null && $parking['parkext'] != "") {
						$parkext = $parking['parkext'];
					}
				}
				$doutput[] = "parking_exten={$parkext}";
				$doutput[] = "parking_transfer_type=blind";

				if (isset($device['settings']['active_locale']) === FALSE) {
					$doutput[] = "active_locale={$default_locale}";
				} else {
					$locale = $device['settings']['active_locale'];
					$table = $this->digium_phones->get_voicemail_translations($locale);
					if ($table !== NULL) {
						$doutput[] = "application=voicemail-{$locale}";
					}
					unset($table);
				}
				foreach ($device['settings'] as $key=>$val) {
					if ($key == 'rapiddial') {
						if ($val == '') {
							continue;
						}
						foreach ($this->digium_phones->get_phonebooks() as $phonebook) {
							if ($val == $phonebook['id']) {
								if ($phonebook['id'] == -1) {
									$doutput[] = "contact=contacts-internal-{$device['id']}.xml";
									$doutput[] = "blf_contact_group=internal-{$device['id']}";
								} else {
									$doutput[] = "contact=contacts-{$phonebook['id']}.xml";
									$doutput[] = "blf_contact_group={$phonebook['name']}";
								}
								break;
							}
						}
						continue;
					} elseif ($key == 'firmware_package_id') {
						$package = $firmware_manager->get_package_by_id($val);
						if ($package !== null) {
							$doutput[] = $package->to_device_conf();
						} else {
							unset($device['settings'][$key]);
						}
						continue;
					} elseif ($key == 'active_ringtone') {
						$ringtone = $this->digium_phones->get_ringtone($val);
						if ($ringtone != null) {
							if ($val < 0) {
								/* Builtin ringtone */
								$doutput[] = "active_ringtone={$ringtone['name']}";
							} else {
								$doutput[] = "ringtone=ringtone-{$ringtone['id']}";
								$doutput[] = "active_ringtone=ringtone-{$ringtone['id']}";
							}
							continue;
						}
					}

					$doutput[] = "{$key}={$val}";
				}

				$doutput[] = "use_local_storage=yes";
				foreach ($device['phonebooks'] as $phonebook) {
					if ($phonebook['phonebookid'] == $device['settings']['rapiddial']) {
						continue;
					}
					if ($phonebook['phonebookid'] == -1) {
						$doutput[] = "contact=contacts-internal-{$device['id']}.xml";
					} else {
						$doutput[] = "contact=contacts-{$phonebook['phonebookid']}.xml";
					}
				}

				foreach ($device['networks'] as $network) {
					$doutput[] = "network=network-{$network['networkid']}";
				}

				foreach ($device['logos'] as $dl) {
					$logo = $this->digium_phones->get_logo($dl['logoid']);

					$doutput[] = "{$logo['model']}_logo_file=user_image_{$logo['id']}.png";
				}
				foreach ($device['alerts'] as $alert) {
					$doutput[] = "alert=alert-{$alert['alertid']}";
				}
/*
				if ($this->digium_phones->get_general('easy_mode') == "yes") {
					$doutput[] = "contact=contacts-internal-{$device['id']}.xml";
					$doutput[] = "blf_contact_group=internal-{$device['id']}";
				}
*/
				foreach ($device['lines'] as $lineid=>$line) {
					$doutput[] = "line={$line['extension']}";
					$loutput[] = "[{$line['extension']}]";
					$loutput[] = "type=line";

					if ($line['user']['devicetype'] == "fixed") {
						$user = $this->digium_phones->get_core_user($line['user']['user']);
						if ($user != null && $user['voicemail'] != null && $user['voicemail'] != "novm") {
							$loutput[] = "mailbox={$user['extension']}@{$user['voicemail']}";
						}
					}

					foreach ($line['settings'] as $key=>$val) {
						$loutput[] = "{$key}={$val}";
					}
					$loutput[] = "";
				}

				foreach ($device['externallines'] as $externalline) {
					$doutput[] = "external_line=externalline-{$externalline['externallineid']}";
				}

				foreach ($queues as $queueid=>$queue) {
					foreach ($queue['entries'] as $entry) {
						if ($entry['deviceid'] == $deviceid) {
							$doutput[] = "application=queue-{$queueid}-{$deviceid}";
						}
					}
				}

				foreach ($device['statuses'] as $status) {
					$doutput[] = "application=status-{$status['statusid']}";
				}

				foreach ($device['customapps'] as $customapp) {
					$doutput[] = "application=customapp-{$customapp['customappid']}";
				}

				$doutput[] = "";
			}

			foreach ($this->digium_phones->get_externallines() as $externallineid=>$externalline) {
				$loutput[] = "[externalline-{$externallineid}]";
				$loutput[] = "type=external_line";

				foreach ($externalline['settings'] as $key=>$val) {
					if ($key=='server_transport') {
						$key='transport';
					}
					$loutput[] = "{$key}={$val}";
				}
				$loutput[] = "";
			}
			foreach ($this->digium_phones->get_networks() as $networkid=>$network) {
				$output[] = "[network-{$networkid}]";
				$output[] = "type=network";
				$output[] = "alias={$network['name']}";

				foreach ($network['settings'] as $key=>$val) {
					$output[] = "{$key}={$val}";
				}

				$output[] = "";
			}

			foreach ($this->digium_phones->get_alerts() as $alertid=>$alert) {
				$output[] = "[alert-{$alertid}]";
				$output[] = "type=alert";
				$output[] = "alert_info={$alert['alertinfo']}";
				$output[] = "ring_type={$alert['type']}";
				if ($alert['ringtone_id'] < 0) {
					/* Builtin ringtone */
					$output[] = "ringtone={$alert['ringtone_name']}";
				} else {
					$output[] = "ringtone=ringtone-{$alert['ringtone_id']}";
				}

				$output[] = "";
			}

			foreach ($this->digium_phones->get_ringtones() as $ringtoneid=>$ringtone) {
				if ($ringtoneid < 0) {
					continue;
				}

				$output[] = "[ringtone-{$ringtoneid}]";
				$output[] = "type=ringtone";
				$output[] = "alias={$ringtone['name']}";
				$output[] = "filename=user_ringtone_{$ringtoneid}.raw";

				$output[] = "";
			}

			return implode("\n", $doutput) . "\n" . implode("\n", $loutput) . "\n" . implode("\n", $output);

		case 'res_digium_phone_applications.conf':
			global $amp_conf;

			$output = array();
			$locales = array();

			foreach ($this->digium_phones->get_devices() as $deviceid=>$device) {
				if (isset($device['settings']['active_locale']) === FALSE) {
					continue;
				}
				$locale = $device['settings']['active_locale'];
				$table = $this->digium_phones->get_voicemail_translations($locale);
				if ($table === NULL) {
					continue;
				}

				if (in_array($locale, $locales)) {
					unset($table);
					continue;
				}
				$locales[] = $locale;

				// We only need to print a voicemail table and its
				// corresponding translation table once.
				$output[] = "[voicemail-{$locale}]";
				$output[] = "type=application";
				$output[] = "application=voicemail";
				$output[] = "translation=translation-{$locale}";
				$output[] = "\n";

				$output[] = "[translation-{$locale}]";
				$output[] = "type=translation";
				foreach ($table as $key=>$value) {
					$output[] = "{$key}={$value}";
				}
				unset($table);
			}

			foreach ($this->digium_phones->get_queues() as $queueid=>$queue) {
				foreach($queue['entries'] as $entry) {
					if ($entry['deviceid'] == null) {
						continue;
					}
					$output[] = "[queue-{$queueid}-{$entry['deviceid']}]";
					$output[] = "type=application";
					$output[] = "application=queue";
					$output[] = "queue={$queueid}";
					$output[] = "permission={$entry['permission']}";
					if ($entry['member'] == null) {
						$output[] = "member=false";
					} else {
						if ($entry['location'] != null) {
							$output[] = "location={$entry['location']}";
						}
						/* Try to find the toggle feature code and use that */
						$fcc = new featurecode('queues', 'que_toggle');
						$toggle = $fcc->getCodeActive();
						unset($fcc);
						if ($toggle != "") {
							$output[] = "login_exten={$toggle}{$queueid}@ext-queues";
							$output[] = "logout_exten={$toggle}{$queueid}@ext-queues";
						} else if ($amp_conf['GENERATE_LEGACY_QUEUE_CODES']) {
							$output[] = "login_exten={$queueid}*@ext-queues";
							$output[] = "logout_exten={$queueid}**@ext-queues";
						}
					}
					$output[] = "";
				}
			}

			foreach ($this->digium_phones->get_statuses() as $statusid=>$status) {
				$output[] = "[status-{$statusid}]";
				$output[] = "type=application";
				$output[] = "application=status";

				foreach ($status['settings'] as $key=>$val) {
					$output[] = "{$key}={$val}";
				}

				foreach ($status['entries'] as $entry) {
					$output[] = "substatus={$entry}";
				}

				$output[] = "";
			}

			$http_path = digium_phones_get_http_path();
			foreach ($this->digium_phones->get_customapps() as $customappid=>$customapp) {
				$output[] = "[customapp-{$customappid}]";
				$output[] = "type=application";
				$output[] = "application=custom";
				$output[] = "name={$customapp['name']}";
				$output[] = "filename=application_{$customappid}.zip";
				$output[] = "md5sum=".md5_file($http_path . "application_{$customappid}.zip");

				foreach ($customapp['settings'] as $key=>$val) {
					$output[] = "{$key}={$val}";
				}

				$output[] = "";
			}

			return implode("\n", $output);
		case 'res_digium_phone_firmware.conf':
			$output = array();
			$firmware_manager = $this->digium_phones->get_firmware_manager();
			foreach ($firmware_manager->get_packages() as $package) {
				$output[] = $package->to_conf();
			}
			return implode("\n", $output);
		default:
			return '';
		}
	}
}

// End of File
