<?php

/**
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
 */

global $db;

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

function digium_phones_hookGet_config($engine) {
	global $ext;
	$execcond = '$[$["${REDIRECTING(reason)}" = "send_to_vm" | "${SIP_HEADER(X-Digium-Call-Feature)}" = "feature_send_to_vm"] & "${ARG1}" != "novm"]';
	$ext->splice('macro-exten-vm', 's', 'checkrecord', new ext_execif($execcond, 'Macro', 'vm,${ARG1},DIRECTDIAL,${IVR_RETVM}'));
	$ext->splice('macro-exten-vm', 's', 'checkrecord', new ext_execif($execcond, 'MacroExit'));
}

function digium_phones_configpageinit($pagename) {
	global $currentcomponent;
	global $amp_conf;
	global $astman;

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
 * This class contains all the functions to configure digium_phones via freepbx
 */
class digium_phones_conf {
	var $use_warning_banner = false;
	var $digium_phones;
	var $autohint;
	var $sorted_users;

	public function digium_phones_conf() {
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
			$output[] = "firmware_package_directory=" . dirname(dirname(__FILE__)) . "/digium_phones/firmware_package/";

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

			foreach ($this->digium_phones->get_customapps() as $customappid=>$customapp) {
				$output[] = "[customapp-{$customappid}]";
				$output[] = "type=application";
				$output[] = "application=custom";
				$output[] = "name={$customapp['name']}";
				$output[] = "filename=application_{$customappid}.zip";
				$output[] = "md5sum=".md5_file(dirname(dirname(__FILE__)) . "/digium_phones/firmware_package/application_{$customappid}.zip");

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

/**
 * A firmware configuration file that comes with a tarball.
 */
class firmware_conf {

	/**
	 * Constructor
	 * @param string @path Full path to the firmware config file to load
	 */
	public function __construct($path) {
		$this->path = $path;
		$this->contexts = array();
		if (file_exists($path)) {
			$this->read_file();
		}
	}

	/**
	 * Get the directory location of the conf file
	 * @return string The path to the conf file
	 */
	public function get_directory() {
		return dirname($this->path);
	}

	/**
	 * Get the last version read from the file.
	 * @return string The version of the config file.
	 */
	public function get_version() {
		return $this->version;
	}

	/**
	 * Add firmware for some digium phone type
	 * @param string $phone_type The model of phone the firmware is for
	 * @param string $version The version of firmware
	 * @param string $file The name of the firmware file
	 */
	public function add_firmware($phone_type, $version, $file) {
		if (in_array($phone_type, $this->contexts)) {
			unset($this->contexts[$phone_type]);
		}
		$context = array();
		$context['version'] = $version;
		$context['file'] = $file;
		$this->contexts[$phone_type] = $context;
		$this->version = $version;
	}

	/**
	 * Get the conf file contexts
	 * @return string The file contexts
	 */
	public function get_contexts() {
		return $this->contexts;
	}

	/**
	 * Synchronize the current contexts to disk
	 * @return boolean True on success, false on error
	 */
	public function synchronize() {
		if (count($this->contexts) != 0) {
			write_file();
			return true;
		}
		return false;
	}

	private function write_file() {
		$output = array();
		foreach ($this->contexts as $key => $context) {
			$output[] = $key;
			$output[] = 'version='.$context['version'];
			$output[] = 'file='.$context['file'];
			$output[] = '\n';
		}
		file_put_contents($this->path, implode('\n', $output));
	}

	private function read_file() {
		$file_contents = file($this->path, FILE_IGNORE_NEW_LINES);
		$context = '';
		$version = '';
		$file = '';
		foreach ($file_contents as $line) {
			if (strpos($line, '[') !== false) {
				$context = $line;
				continue;
			} elseif (($ind = strpos($line, '=')) !== false) {
				$type = substr($line, 0, $ind);
				if ($type == 'file') {
					$file = substr($line, $ind + 1);
				} elseif ($type == 'version') {
					$version = substr($line, $ind + 1);
				}
			}
			if ($context != '' and $version != '' and $file != '') {
				$this->add_firmware($context, $version, $file);
				$context = '';
				$version = '';
				$file = '';
			}
		}
	}

	private $contexts;
	private $path;
	private $version;
}

/**
 * A firmware package object. Note that a firmware package object
 * always consists of multiple firmware objects.
 */
class firmware_package {

	/**
	 * Constructor
	 * @param string $name The name of the firmware package.
	 * @param string $file_path The full path to the directory that contains the firmware
	 * @param string $version The version of firmware for this package
	 * @param string $uid A unique identifier. If the empty string or NULL, a unique ID will be generated.
	 */
	public function __construct($name, $file_path, $version, $uid) {
		if ($uid === '' or $uid === null) {
			$uid = uniqid('package_', true);
		}
		$this->unique_id = $uid;
		$this->name = $name;
		$this->file_path = $file_path;
		$this->version = $version;
	}

	/**
	 * Retrieve all packages from the database
	 * @return array The packages in the database
	 */
	public static function retrieve_packages() {
		global $db;
		$packages = array();

		$sql = "SELECT * FROM digium_phones_firmware_packages ORDER BY name";
		$presults = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($presults)) {
			die_freepbx($presults->getDebugInfo());
			return false;
		}

		foreach ($presults as $pindex => $prow) {
			$package = new firmware_package($prow['name'], $prow['file_path'], $prow['version'], $prow['unique_id']);

			// Bail if the location doesn't exist.
			if (!file_exists($package->get_file_path())) {

				// instead of complaining, just delete the missing package
				//echo "The firmware package location ".$package->get_file_path()." does not exist.";
				$sql = "DELETE from digium_phones_firmware_packages where unique_id=\"{$package->unique_id}\";";
				$result = $db->query($sql);
				if (DB::IsError($presults)) {
					die_freepbx($presults->getDebugInfo());
					return false;
				}
				$sql = "DELETE from digium_phones_firmware where package_id=\"{$package->unique_id}\";";
				$result = $db->query($sql);
				if (DB::IsError($presults)) {
					die_freepbx($presults->getDebugInfo());
					return false;
				}
				unset($package);
				unset($presults[$pindex]);
				continue;
			}

			$sql = "SELECT * FROM digium_phones_firmware WHERE package_id=\"{$package->unique_id}\" ORDER BY file_name";
			$fresults = $db->getAll($sql, DB_FETCHMODE_ASSOC);
			if (DB::IsError($fresults)) {
				die_freepbx($fresults->getDebugInfo());
				return array();
			}
			foreach ($fresults as $frow) {
				$firmware = new firmware($frow['file_name'], $frow['phone_model'], $frow['unique_id'], $frow['package_id']);
				$package->firmware[] = $firmware;
			}
			$packages[] = $package;
			unset($fresults);
		}
		unset($presults);
		return $packages;
	}

	public function get_firmware() {
		return $this->firmware;
	}

	/**
	 * Create a firmware and add it to this package
	 * @param string $name The name of the firmware.
	 * @param string $file_path The full path to the firmware file.
	 * @param string $phone_model The type of phone model this firmware corresponds to.
	 * @return True on success, false on error
	 */
	public function create_firmware($file_name, $phone_model) {
		global $db;

		$file_path = $this->file_path.'/'.$file_name;
		if (!file_exists($file_path)) {
			echo "The firmware ".$file_path." does not exist.";
			return false;
		}
		$firmware = new firmware($file_name, $phone_model, '', $this->unique_id);
		$sql = "INSERT INTO digium_phones_firmware (unique_id, file_name, phone_model, package_id) VALUES(\"{$firmware->get_unique_id()}\", \"{$db->escapeSimple($firmware->get_file_name())}\", \"{$db->escapeSimple($firmware->get_phone_model())}\", \"{$this->get_unique_id()}\")";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);
		$this->firmware[] = $firmware;
		needreload();
		return true;
	}

	public function get_unique_id() {
		return $this->unique_id;
	}

	public function get_file_path() {
		return $this->file_path;
	}

	public function set_file_path($value) {
		global $db;

		if ($value === $this->file_path) {
			return true;
		}

		if (!file_exists($value)) {
			if (!mkdir($value)) {
				echo "Could not create directory $value";
				return false;
			}
		}
		foreach ($this->firmware as $firmware) {
			$new_path = $value.'/'.$firmware->get_file_name();
			$old_path = $this->file_path.'/'.$firmware->get_file_name();
			if (rename($old_path, $new_path) === false) {
				echo "Failed to move firmware ".$firmware->get_file_name();
				return false;
			}
		}

		$sql = "UPDATE digium_phones_firmware_packages SET file_path=\"{$db->escapeSimple($value)}\" WHERE unique_id=\"{$this->unique_id}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);
		rmdir($this->file_path);
		$this->file_path = $value;
		needreload();
		return true;
	}

	public function get_name() {
		return $this->name;
	}

	public function set_name($value) {
		global $db;

		if ($value === $this->name) {
			return true;
		}

		$sql = "UPDATE digium_phones_firmware_packages SET name=\"{$db->escapeSimple($value)}\" WHERE unique_id=\"{$this->unique_id}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);
		$this->name = $value;
		needreload();
		return true;
	}

	public function get_version() {
		return $this->version;
	}

	public function set_version($value) {
		global $db;

		if ($value === $this->version) {
			return true;
		}

		$sql = "UPDATE digium_phones_firmware SET version=\"{$db->escapeSimple($value)}\" WHERE unique_id=\"{$this->unique_id}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);
		$this->version = $value;
		needreload();
		return true;
	}

	/**
	 * Convert this package into conf file format
	 * @return string The package contents formatted as a sequence of [firmware] contexts
	 */
	public function to_conf() {
		$output = array();
		foreach ($this->firmware as $firmware) {
			$output[] = "[".$firmware->get_phone_model()."-".$this->version."]";
			$output[] = "type=firmware";
			$output[] = "model=".$firmware->get_phone_model();
			$output[] = "version=".$this->version;
			$output[] = "file=".basename($this->file_path).'/'.$firmware->get_file_name();
			$output[] = "\n";
		}
		return implode("\n", $output);
	}

	/**
	 * Return the firmware keys for a device
	 * @return string The firmware key/value pairs for a device
	 */
	public function to_device_conf() {
		$output = array();
		foreach ($this->firmware as $firmware) {
			// Note that this assumes that the phone firmware is stored
			// as a subdirectory of the firmware_package directory. If this
			// gets more complex, we'll need to parse this out more.
			$output[] = "firmware=".$firmware->get_phone_model()."-".$this->version;
		}
		return implode("\n", $output);
	}

	private $unique_id;
	private $name;
	private $version;
	private $file_path;
	private $firmware;
}

/**
 * A firmware object. This controls the name of the actual firmware
 * file, which Digium phone model it applies to, and associates it with
 * a package.
 */
class firmware {

	/**
	 * Constructor
	 * @param string $name The name of the firmware
	 * @param string $file_path Full path to the firmware file
	 * @param string $phone_model The type of Digium phone this firmware applies to
	 * @param string $uid Unique identifier for the firmware. If '' or null, one will be generated.
	 * @param string $package_id Unique identifier for the package this firmware belongs to.
	 */
	public function __construct($file_name, $phone_model, $uid, $package_id) {
		if ($uid === '' or $uid === NULL) {
			$uid = uniqid('firmware_', true);
		}
		$this->unique_id = $uid;
		$this->file_name = $file_name;
		$this->phone_model = $phone_model;
	}

	public function get_file_name() {
		return $this->file_name;
	}

	public function get_phone_model() {
		return $this->phone_model;
	}

	public function get_unique_id() {
		return $this->unique_id;
	}

	public function get_package_id() {
		return $this->package_id;
	}

	private $file_name;
	private $unique_id;
	private $phone_model;
	private $package_id;
}

/**
 * An object that manages the firmware on the system.
 */
class firmware_manager {

	public function __construct($phones) {
		$this->packages = array();
		$this->versions = array();
		$this->phones = $phones;
	}

	/**
	 * Get the currently loaded firmware packages
	 * @return firmware_package An array of firmware package objects
	 */
	public function get_packages() {
		return $this->packages;
	}

	/**
	 * Refresh all firmware packages currently in the database
	 */
	public function refresh_packages() {
		global $db;

		$this->packages = firmware_package::retrieve_packages();
		foreach ($this->packages as $package) {
			if (!(in_array($package->get_version(), $this->versions))) {
				$this->versions[] = $package->get_version();
			}
		}
	}

	/**
	 * Create a new firmware package from a conf file.
	 * @param string $firmware_conf The full path and filename to the firmware conf file to load.
	 * @return A firmware object on success, NULL on failure
	 */
	public function create_package($firmware_conf) {
		global $db;

		$path_tokens = explode('/', $firmware_conf->get_directory());
		$package = new firmware_package($path_tokens[count($path_tokens) - 1],
			$firmware_conf->get_directory(),
			$firmware_conf->get_version(),
			'');
		foreach ($firmware_conf->get_contexts() as $key => $context) {
			$phone_model = trim(trim($key,"]"),"[");
			if (!($package->create_firmware($context['file'],
				$phone_model))) {
				echo 'Failed to create firmware for '.$context['file'];
				return NULL;
			}
		}
		$sql = "INSERT INTO digium_phones_firmware_packages (unique_id, name, version, file_path) VALUES(\"{$package->get_unique_id()}\", \"{$db->escapeSimple($package->get_name())}\", \"{$db->escapeSimple($package->get_version())}\", \"{$db->escapeSimple($package->get_file_path())}\")";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return NULL;
		}
		unset($result);
		$this->packages[] = $package;
		$this->versions[] = $package->get_version();
		needreload();
		return $package;
	}

	private function find_package_by_file_path($file_path) {
		foreach ($this->packages as $package) {
			if ($package->get_file_path() === $file_path) {
				return $package;
			}
		}
		return NULL;
	}

	/**
	 * Get a firmware by its unique id
	 * @return firmware_package NULL on error, firmware package object on success
	 */
	public function get_package_by_id($id) {
		foreach ($this->packages as $package) {
			if ($package->get_unique_id() === $id) {
				return $package;
			}
		}
		return NULL;
	}

	/**
	 * Get the package by its display name
	 * @param $name The name of the package
	 * @return firmware_package Package on success, NULL on failure
	 */
	public function get_package_by_name($name) {
		foreach ($this->packages as $package) {
			if ($package->get_name() === $name) {
				return $package;
			}
		}
		return NULL;
	}


	/**
	 * Take a location on the file system and synchronize the firmware residing there.
	 * Note that this assumes you have extracted a tarball containing a firmware conf
	 * file.
	 * @param $path Full path to the firmware
	 * @return boolean True on success, False on failure
	 */
	public function synchronize_file_location($path) {
		// See if we have a digium_phones_firmware.conf
		$conf_name = $path.'/digium_phones_firmware.conf';
		if (!file_exists($conf_name)) {
			return false;
		}
		$conf_file = new firmware_conf($conf_name);
		$package = $this->create_package($conf_file);

		if ($package === null) {
			return false;
		}

		// If what we're synchronizing isn't the firmware directory,
		// move the firmware objects over to it
		if ($path !== dirname(dirname(__FILE__)) . '/digium_phones/firmware_package/') {
			$package->set_file_path(dirname(dirname(__FILE__)) . '/digium_phones/firmware_package/' . trim($package->get_name(), '/'));
			unlink($conf_name);
			rmdir($path);
		}
		return true;
	}

	/**
	 * Delete a firmware package
	 * @param firmware_package $package The package object to delete
	 * @return True on success, False on failure
	 */
	public function delete_package($package) {
		global $db;

		$sql = "DELETE FROM digium_phones_firmware WHERE package_id=\"{$package->get_unique_id()}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_firmware_packages WHERE unique_id=\"{$package->get_unique_id()}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		// Remove the package from the devices
		$devices = $this->phones->get_devices();
		foreach ($devices as $device) {
			if (!in_array('settings', $device) or
				!in_array('firmware_package_id', $device['settings'])) {
				continue;
			}
			if ($device['settings']['firmware_package_id'] === $package->get_unique_id()) {
				unset($device['settings']['firmware_package_id']);
			}
		}

		foreach ($package->get_firmware() as $firmware) {
			unlink($package->get_file_path().'/'.$firmware->get_file_name());
		}
		rmdir($package->get_file_path());
		$pos = array_search($package, $this->packages);
		unset($this->packages[$pos]);
		needreload();
		return true;
	}

	public function version_exists($value) {
		foreach ($this->versions as $version) {
			if (strncmp($version, $value, strlen($value)) === 0) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Pull back the JSON object from the Digium server specifying
	 * what firmware is available
	 * @return array The JSON object.
	 */
	public function get_new_firmware_info() {
		$url = "http://downloads.digium.com/pub/telephony/res_digium_phone/firmware/dpma-firmware.json";
		$request = file_get_contents($url);
		$request = str_replace(array("\n", "\t"), "", $request);
		$json = json_decode($request, true);

		if ($json == null) {
			return null;
		}

		$json['tarball'] = str_replace('{version}', $json['version'], $json['tarball']);
		return $json;
	}

	private $versions;
	private $packages;
	private $phones;
}

class digium_phones {
	/**
	 * Constructor
	 */
	public function digium_phones () {
		$this->load();
	}

	/**
	 * Load
	 *
	 * Load all the information from the database
	 */
	public function load() {
		global $db;

		$this->cache_core_devices_list();
		$this->cache_core_users_list();

		$this->read_general();
		$this->read_devices();
		$this->read_extension_settings();
		$this->read_phonebooks();
		$this->read_queues();
		$this->read_statuses();
		$this->read_customapps();
		$this->read_logos();
		$this->read_networks();
		$this->read_alerts();
		$this->read_ringtones();
		$this->read_externallines();
		$this->read_firmware();
	}
	private $core_devices = array();
	private $core_users = array();

	private $general = array();
	private $devices = array();
	private $extension_settings = array();
	private $phonebooks = array();
	private $queues = array();
	private $statuses = array();
	private $customapps = array();
	private $logos = array();
	private $networks = array();
	private $alerts = array();
	private $ringtones = array();
	private $externallines = array();
	private $voicemail_translations = array();
	private $locales = NULL;
	private $firmware_manager = NULL;

	private $error_msg = '';		// The latest error message

	public function cache_core_devices_list() {
		foreach(core_devices_list('all', 'full') as $device) {
			$this->core_devices[$device['id']] = $device;
		}
	}

	public function get_core_devices() {
		return $this->core_devices;
	}

	public function get_core_device($param) {
		if (array_key_exists($param,$this->core_devices))
			return($this->core_devices[$param]);
		return null;
	}

	public function cache_core_users_list() {
		foreach(core_users_list() as $user) {
			$newuser['extension'] = $user[0];
			$newuser['name'] = $user[1];
			$newuser['voicemail'] = $user[2];
			$this->core_users[$newuser['extension']] = $newuser;
		}
	}

	public function get_core_users() {
		return $this->core_users;
	}

	public function get_core_user($param) {
		if (array_key_exists($param,$this->core_users))
			return($this->core_users[$param]);
		return null;
	}

	public function check_firmware() {
		$url = "http://downloads.digium.com/pub/telephony/res_digium_phone/firmware/dpma-firmware.json";
		$request = file_get_contents($url);
		$request = str_replace(array("\n", "\t"), "", $request);
		$json = json_decode($request, true);

		if ($json == null) {
			return null;
		}

		$json['tarball'] = str_replace('{version}', $json['version'], $json['tarball']);
		return $json;
	}

	public function download_firmware($tarball) {
		$json = check_firmware();
		if ($json == null) {
			return false;
		}

		$this->update_general(array('firmware_version'=>$json['version']));
		return true;
	}

	/**
	 * Get general
	 *
	 * Get a general parameter
	 */
	 public function get_general($param) {
		return $this->general[$param];
	 }

	/**
	 * Get All general
	 *
	 * Get all general parameters
	 */
	 public function get_all_general() {
		return $this->general;
	 }

	/**
	 * Get devices
	 *
	 * Get the devices
	 *
	 * @access public
	 * @return array
	 */
	public function get_devices() {
		return $this->devices;
	}

	/**
	 * Get device
	 * 
	 * Get a device and all its info
	 */
	public function get_device($deviceid) {
		return $this->devices[$deviceid];
	}

	/**
	 * Get extension settings
	 *
	 * Get the extension settings
	 *
	 * @access public
	 * @return array
	 */
	public function get_extensions_settings() {
		return $this->extensions_settings;
	}

	/**
	 * Get extension settings
	 * 
	 * Get an extension and all its settings
	 */
	public function get_extension_settings($extension) {
		return $this->extension_settings[$extension];
	}

	/**
	 * Get phonebooks
	 *
	 * Get the phonebooks
	 *
	 * @access public
	 * @return array
	 */
	public function get_phonebooks() {
		return $this->phonebooks;
	}

	/**
	 * Get phonebook
	 * 
	 * Get a phonebook and all its extensions
	 */
	public function get_phonebook($id) {
		return $this->phonebooks[$id];
	}

	/**
	 * Get queues
	 *
	 * Get the queues
	 *
	 * @access public
	 * @return array
	 */
	public function get_queues() {
		return $this->queues;
	}

	/**
	 * Get queue
	 * 
	 * Get a queue and all its settings
	 */
	public function get_queue($id) {
		return $this->queues[$id];
	}

	/**
	 * Get statuses
	 *
	 * Get the statuses
	 *
	 * @access public
	 * @return array
	 */
	public function get_statuses() {
		return $this->statuses;
	}

	/**
	 * Get status
	 * 
	 * Get a status and all its settings and entries
	 */
	public function get_status($id) {
		return $this->statuses[$id];
	}

	/**
	 * Get customapps
	 *
	 * Get the customapps
	 *
	 * @access public
	 * @return array
	 */
	public function get_customapps() {
		return $this->customapps;
	}

	/**
	 * Get customapp
	 * 
	 * Get a customapp and all its settings
	 */
	public function get_customapp($id) {
		return $this->customapps[$id];
	}

	/**
	 * Get networks
	 *
	 * Get the networks
	 *
	 * @access public
	 * @return array
	 */
	public function get_networks() {
		return $this->networks;
	}

	/**
	 * Get network
	 * 
	 * Get a network and all its settings
	 */
	public function get_network($id) {
		return $this->networks[$id];
	}

	/**
	 * Get external lines
	 *
	 * Get the external lines
	 *
	 * @access public
	 * @return array
	 */
	public function get_externallines() {
		return $this->externallines;
	}

	/**
	 * Get external line
	 *
	 * Get an external line and all its settings
	 */
	public function get_externalline($id) {
		return $this->externallines[$id];
	}

	/**
	 * Get logos
	 *
	 * Get the logos
	 *
	 * @access public
	 * @return array
	 */
	public function get_logos() {
		return $this->logos;
	}

	/**
	 * Get logo
	 * 
	 * Get a logo and all its settings
	 */
	public function get_logo($id) {
		return $this->logos[$id];
	}

	/**
	 *
	 * Get alerts
	 *
	 * Get the alerts
	 */
	public function get_alerts() {
		return $this->alerts;
	}

	/**
	 * Get alert
	 * 
	 * Get an alert
	 */
	public function get_alert($id) {
		return $this->alerts[$id];
	}

	/**
	 *
	 * Get all ringtones
	 *
	 * Get a list of built-in and user defined ringtones
	 */
	public function get_ringtones() {
		return $this->ringtones;
	}

	/**
	 * Get ringtone
	 * 
	 * Get a ringtone
	 */
	public function get_ringtone($id) {
		return $this->ringtones[$id];
	}

	/**
	 * Read Digium Phones general section
	 */
	public function read_general() {
		global $db;

		$sql = 'SELECT * FROM digium_phones_general';

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach($results as $result) {
			if ($result['keyword'] == 'mdns_address' && $result['val'] == '') {
				// We don't have an mDNS address set.  Default it to the address the user connected to.
				if (isset($_SERVER['SERVER_ADDR'])) {
					$result['val'] = $_SERVER['SERVER_ADDR'];
					$this->update_general(array('mdns_address'=>$_SERVER['SERVER_ADDR']));
				}
			}
			$this->general[$result['keyword']] = ($result['val']) ? $result['val'] : $result['default_val'];
		}
	}

	/**
	 * Update Digium Phones general section
	 *
	 * @access public
	 * @param array $params An array of parameters
	 * @return bool
	 */
	public function update_general($params) {
		global $db;

		foreach ($params as $keyword=>$val) {
			if ($val === null) {
				$sql = "UPDATE digium_phones_general SET val=null WHERE keyword=\"{$db->escapeSimple($keyword)}\"";
				$this->general[$keyword] = null;
			} else {
				$sql = "UPDATE digium_phones_general SET val=\"{$db->escapeSimple($val)}\" WHERE keyword=\"{$db->escapeSimple($keyword)}\"";
				$this->general[$keyword] = $val;
			}
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		needreload();
	}

	public function read_devices() {
		global $db;

		$devices = array();
		$this->devices = array();
		
		// Get all devices.
		$sql = "SELECT id as deviceid, name FROM digium_phones_devices ORDER BY id";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$d = $devices[$row['deviceid']];

			$d['id'] = $row['deviceid'];
			$d['name'] = $row['name'];

			$d['settings'] = array();
			$d['lines'] = array();
			$d['externallines'] = array();
			$d['phonebooks'] = array();
			$d['queues'] = array();
			$d['statuses'] = array();
			$d['customapps'] = array();

			$devices[$row['deviceid']] = $d;
		}

		// Get settings on devices.
		$sql = "SELECT ds.id as deviceid, dss.keyword, dss.val FROM digium_phones_devices AS ds ";
		$sql = $sql . "  LEFT JOIN digium_phones_device_settings AS dss ON (ds.id = dss.deviceid) ";
		$sql = $sql . "ORDER BY ds.id ";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$d = $devices[$row['deviceid']];

			if ($row['keyword'] != null) {
				$d['settings'][$row['keyword']] = $row['val'];
			}

			$devices[$row['deviceid']] = $d;
		}


		// Get lines on devices.
		$sql = "SELECT ds.id as deviceid, ls.id as lineid, ls.extension FROM digium_phones_devices AS ds ";
		$sql = $sql . "  LEFT JOIN digium_phones_lines AS ls ON (ds.id = ls.deviceid) ";
		$sql = $sql . "ORDER BY ds.id, ls.id ";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$d = $devices[$row['deviceid']];

			if ($row['lineid'] != null) {
				$l = $d['lines'][$row['lineid']];
				$l['id'] = $row['lineid'];
				$l['extension'] = $row['extension'];
				$l['settings'] = array();

				$d['lines'][$row['lineid']] = $l;
			}

			$devices[$row['deviceid']] = $d;
		}

		// Get settings on lines.
		$sql = "SELECT ds.id as deviceid, ls.id as lineid, ls.extension, lss.keyword, lss.val FROM digium_phones_devices AS ds ";
		$sql = $sql . "  LEFT JOIN digium_phones_lines AS ls ON (ds.id = ls.deviceid) ";
		$sql = $sql . "  LEFT JOIN digium_phones_extension_settings AS lss ON (ls.extension = lss.extension) ";
		$sql = $sql . "ORDER BY ds.id, ls.id";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$d = $devices[$row['deviceid']];

			if ($row['keyword'] != null) {
				$l = $d['lines'][$row['lineid']];

				$l['settings'][$row['keyword']] = $row['val'];

				$d['lines'][$row['lineid']] = $l;
			}

			$devices[$row['deviceid']] = $d;
		}

		// Get phonebooks on devices.
		$sql = "SELECT dps.id, ds.id as deviceid, dps.phonebookid FROM digium_phones_devices AS ds ";
		$sql = $sql . "  LEFT JOIN digium_phones_device_phonebooks AS dps ON (ds.id = dps.deviceid) ";
		$sql = $sql . "  LEFT JOIN digium_phones_phonebooks AS ps ON (dps.phonebookid = ps.id) ";
		$sql = $sql . "ORDER BY ds.id, dps.id ";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$d = $devices[$row['deviceid']];

			if ($row['id'] != null) {
				$p = $d['phonebooks'][$row['id']];
				$p['phonebookid'] = $row['phonebookid'];
				$d['phonebooks'][$row['id']] = $p;
			}

			$devices[$row['deviceid']] = $d;
		}

		// Get networks on devices.
		$sql = "SELECT dns.id, ds.id as deviceid, dns.networkid FROM digium_phones_devices AS ds ";
		$sql = $sql . "  LEFT JOIN digium_phones_device_networks AS dns ON (ds.id = dns.deviceid) ";
		$sql = $sql . "  LEFT JOIN digium_phones_networks AS ns ON (dns.networkid = ns.id) ";
		$sql = $sql . "ORDER BY ds.id, dns.id ";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$d = $devices[$row['deviceid']];

			if ($row['id'] != null) {
				$n = $d['networks'][$row['id']];
				$n['networkid'] = $row['networkid'];
				$d['networks'][$row['id']] = $n;
			} else {
				$n = $d['networks'][-1];
				$n['networkid'] = -1;
				$d['networks'][-1] = $n;
			}

			$devices[$row['deviceid']] = $d;
		}

		// Get external lines on devices.
		$sql = "SELECT dels.id, ds.id as deviceid, dels.externallineid FROM digium_phones_devices AS ds ";
		$sql = $sql . "  LEFT JOIN digium_phones_device_externallines AS dels ON (ds.id = dels.deviceid) ";
		$sql = $sql . "  LEFT JOIN digium_phones_externallines AS ns ON (dels.externallineid = ns.id) ";
		$sql = $sql . "ORDER BY ds.id, dels.id ";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$d = $devices[$row['deviceid']];

			if ($row['id'] != null) {
				$el = $d['externallines'][$row['id']];
				$el['externallineid'] = $row['externallineid'];
				$d['externallines'][$row['id']] = $el;
			}

			$devices[$row['deviceid']] = $d;
		}

		// Get logos on devices.
		$sql = "SELECT dls.id, ds.id as deviceid, dls.logoid FROM digium_phones_devices AS ds ";
		$sql = $sql . "  LEFT JOIN digium_phones_device_logos AS dls ON (ds.id = dls.deviceid) ";
		$sql = $sql . "  LEFT JOIN digium_phones_logos AS ls ON (dls.logoid = ls.id) ";
		$sql = $sql . "ORDER BY ds.id, dls.id ";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$d = $devices[$row['deviceid']];

			if ($row['id'] != null) {
				$l = $d['logos'][$row['id']];
				$l['logoid'] = $row['logoid'];
				$d['logos'][$row['id']] = $l;
			}

			$devices[$row['deviceid']] = $d;
		}

		// Get alerts on devices.
		$sql = "SELECT das.id, ds.id as deviceid, das.alertid FROM digium_phones_devices AS ds ";
		$sql = $sql . "  LEFT JOIN digium_phones_device_alerts AS das ON (ds.id = das.deviceid) ";
		$sql = $sql . "  LEFT JOIN digium_phones_alerts AS alerts ON (das.alertid = alerts.id) ";
		$sql = $sql . "ORDER BY ds.id, das.id ";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$d = $devices[$row['deviceid']];

			if ($row['id'] != null) {
				$a = $d['alerts'][$row['id']];
				$a['alertid'] = $row['alertid'];
				$d['alerts'][$row['id']] = $a;
			}

			$devices[$row['deviceid']] = $d;
		}

		// Get statuses on devices.
		$sql = "SELECT dss.id, ds.id as deviceid, dss.statusid FROM digium_phones_devices AS ds ";
		$sql = $sql . "  LEFT JOIN digium_phones_device_statuses AS dss ON (ds.id = dss.deviceid) ";
		$sql = $sql . "  LEFT JOIN digium_phones_statuses AS statuses ON (dss.statusid = statuses.id) ";
		$sql = $sql . "ORDER BY ds.id, dss.id ";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$d = $devices[$row['deviceid']];

			if ($row['id'] != null) {
				$a = $d['statuses'][$row['id']];
				$a['statusid'] = $row['statusid'];
				$d['statuses'][$row['id']] = $a;
			}

			$devices[$row['deviceid']] = $d;
		}

		// Get customapps on devices.
		$sql = "SELECT dcs.id, ds.id as deviceid, dcs.customappid FROM digium_phones_devices AS ds ";
		$sql = $sql . "  LEFT JOIN digium_phones_device_customapps AS dcs ON (ds.id = dcs.deviceid) ";
		$sql = $sql . "  LEFT JOIN digium_phones_customapps AS customapps ON (dcs.customappid = customapps.id) ";
		$sql = $sql . "ORDER BY ds.id, dcs.id ";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$d = $devices[$row['deviceid']];

			if ($row['id'] != null) {
				$a = $d['customapps'][$row['id']];
				$a['customappid'] = $row['customappid'];
				$d['customapps'][$row['id']] = $a;
			}

			$devices[$row['deviceid']] = $d;
		}

		foreach ($devices as $device) {
			$d = $device;
			$d['lines'] = array();
			foreach ($device['lines'] as $line) {
				$l = $line;
				$l['user'] = $this->get_core_device($line['extension']);
				$d['lines'][] = $l;
			}
			$this->devices[$d['id']] = $d;
		}
	}

	/**
	 * Update Digium Phones device
	 *
	 * @access public
	 * @param array $device The device to update.
	 * @return bool
	 */
	public function update_device($device) {
		$this->delete_device($device);
		$this->add_device($device);
	}

	public function delete_device($device) {
		global $db;

		$deviceid = $device['id'];

		$this->devices[$deviceid] = null;

		$sql = "DELETE FROM digium_phones_device_phonebooks WHERE deviceid = \"{$db->escapeSimple($device['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_device_networks WHERE deviceid = \"{$db->escapeSimple($device['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_device_externallines WHERE deviceid = \"{$db->escapeSimple($device['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_device_logos WHERE deviceid = \"{$db->escapeSimple($device['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_device_alerts WHERE deviceid = \"{$db->escapeSimple($device['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_queues WHERE deviceid = \"{$db->escapeSimple($device['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_device_statuses WHERE deviceid = \"{$db->escapeSimple($device['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_device_customapps WHERE deviceid = \"{$db->escapeSimple($device['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_lines WHERE deviceid = \"{$db->escapeSimple($device['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_device_settings WHERE deviceid = \"{$db->escapeSimple($device['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_devices WHERE id = \"{$db->escapeSimple($device['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		needreload();
	}


	public function add_device($device) {
		global $db;

		$deviceid = $device['id'];

		// Devices
		$sql = "INSERT INTO digium_phones_devices (id, name) VALUES(\"{$db->escapeSimple($device['id'])}\", \"{$db->escapeSimple($device['name'])}\")";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		if ($deviceid == 0) {
			$sql = "SELECT LAST_INSERT_ID()";

			$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
			if (DB::IsError($results)) {
				die_freepbx($results->getDebugInfo());
				return false;
			}

			foreach ($results as $row) {
				$deviceid = $row['LAST_INSERT_ID()'];
			}
		}

		$this->devices[$id] = $device;

		// Device settings
		$devicesettings = array();
		foreach ($device['settings'] as $key=>$val) {
			if ($val != '') {
				$devicesettings[] = '\''.$db->escapeSimple($deviceid).'\',\''.$db->escapeSimple($key).'\',\''.$db->escapeSimple($val).'\'';
			}
		}

		if (count($devicesettings) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_device_settings (deviceid, keyword, val) VALUES (" . implode('),(', $devicesettings) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		// Lines
		$lines = array();
		foreach ($device['lines'] as $lineid=>$line) {
			$lines[] = '\''.$db->escapeSimple($lineid).'\',\''.$db->escapeSimple($deviceid).'\',\''.$db->escapeSimple($line['extension']).'\'';
		}

		if (count($lines) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_lines (id, deviceid, extension) VALUES (" . implode('),(', $lines) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		// Device phonebooks
		$phonebooks = array();
		foreach ($device['phonebooks'] as $phonebookentryid=>$phonebook) {
			$phonebooks[] = '\''.$db->escapeSimple($phonebookentryid).'\',\''.$db->escapeSimple($deviceid).'\',\''.$db->escapeSimple($phonebook['phonebookid']).'\'';
		}

		if (count($phonebooks) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_device_phonebooks (id, deviceid, phonebookid) VALUES (" . implode('),(', $phonebooks) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		// Device networks
		$networks = array();
		foreach ($device['networks'] as $networkentryid=>$network) {
			$networks[] = '\''.$db->escapeSimple($networkentryid).'\',\''.$db->escapeSimple($deviceid).'\',\''.$db->escapeSimple($network['networkid']).'\'';
		}

		if (count($networks) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_device_networks (id, deviceid, networkid) VALUES (" . implode('),(', $networks) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		// Device external lines
		$externallines = array();
		foreach ($device['externallines'] as $externallineentryid=>$externalline) {
			$externallines[] = '\''.$db->escapeSimple($externallineentryid).'\',\''.$db->escapeSimple($deviceid).'\',\''.$db->escapeSimple($externalline['externallineid']).'\'';
		}

		if (count($externallines) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_device_externallines (id, deviceid, externallineid) VALUES (" . implode('),(', $externallines) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		// Device logos
		$logos = array();
		foreach ($device['logos'] as $logoentryid=>$logo) {
			$logos[] = '\''.$db->escapeSimple($logoentryid).'\',\''.$db->escapeSimple($deviceid).'\',\''.$db->escapeSimple($logo['logoid']).'\'';
		}

		if (count($logos) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_device_logos (id, deviceid, logoid) VALUES (" . implode('),(', $logos) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		// Device alerts
		$alerts = array();
		foreach ($device['alerts'] as $alertentryid=>$alert) {
			$alerts[] = '\''.$db->escapeSimple($alertentryid).'\',\''.$db->escapeSimple($deviceid).'\',\''.$db->escapeSimple($alert['alertid']).'\'';
		}

		if (count($alerts) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_device_alerts (id, deviceid, alertid) VALUES (" . implode('),(', $alerts) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		// Device statuses
		$statuses = array();
		foreach ($device['statuses'] as $statusentryid=>$status) {
			$statuses[] = '\''.$db->escapeSimple($statusentryid).'\',\''.$db->escapeSimple($deviceid).'\',\''.$db->escapeSimple($status['statusid']).'\'';
		}

		if (count($statuses) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_device_statuses (id, deviceid, statusid) VALUES (" . implode('),(', $statuses) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		// Device customapps
		$customapps = array();
		foreach ($device['customapps'] as $customappentryid=>$customapp) {
			$customapps[] = '\''.$db->escapeSimple($customappentryid).'\',\''.$db->escapeSimple($deviceid).'\',\''.$db->escapeSimple($customapp['customappid']).'\'';
		}

		if (count($customapps) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_device_customapps (id, deviceid, customappid) VALUES (" . implode('),(', $customapps) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		needreload();
	}

	public function read_extension_settings() {
		global $db;

		$extension_settings = array();
		$this->extension_settings = array();

		// Get extension settings;
		$sql = "SELECT extension, keyword, val FROM digium_phones_extension_settings ";
		$sql = $sql . "ORDER BY extension, keyword";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$l = $extension_settings[$row['extension']];
			$l['extension'] = $row['extension'];

			if ($row['keyword'] != null) {
				$l['settings'][$row['keyword']] = $row['val'];
			}

			$extension_settings[$row['extension']] = $l;
		}

		$this->extension_settings = $extension_settings;
	}

	public function update_extension_settings($line) {
		global $db;

		$sql = "DELETE FROM digium_phones_extension_settings WHERE extension = \"{$db->escapeSimple($line['extension'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$linesettings = array();
		foreach ($line['settings'] as $key=>$val) {
			if ($val != '') {
				$linesettings[] = '\''.$db->escapeSimple($line['extension']).'\',\''.$db->escapeSimple($key).'\',\''.$db->escapeSimple($val).'\'';
			}
		}

		if (count($linesettings) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_extension_settings (extension, keyword, val) VALUES (" . implode('),(', $linesettings) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		needreload();
	}

	/**
	 * Read in all the phonebook info from the database
	 */
	public function read_phonebooks() {
		global $db;

		$phonebooks = array();
		$this->phonebooks = array();
		
		$sql = "SELECT ps.id AS phonebookid, ps.name, pes.id AS entryid, pes.extension, pess.keyword, pess.val FROM digium_phones_phonebooks AS ps ";
		$sql = $sql . "  LEFT JOIN digium_phones_phonebook_entries AS pes ON (ps.id = pes.phonebookid) ";
		$sql = $sql . "  LEFT JOIN digium_phones_phonebook_entry_settings AS pess ON (pes.id = pess.phonebookentryid AND ps.id = pess.phonebookid) ";
		$sql = $sql . "ORDER BY ps.id, pes.id ";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$p = $this->phonebooks[$row['phonebookid']];

			$p['id'] = $row['phonebookid'];
			$p['name'] = $row['name'];
			if ($row['entryid'] != null) {
				$e = $p['entries'][$row['entryid']];

				$e['extension'] = $row['extension'];
				if ($row['keyword'] != null) {
					$e['settings'][$row['keyword']] = $row['val'];
				}

				$p['entries'][$row['entryid']] = $e;
			}

			$this->phonebooks[$row['phonebookid']] = $p;
		}
	}
	public function update_phonebook($phonebook) {
		$this->delete_phonebook($phonebook, false);
		$this->add_phonebook($phonebook);
	}

	public function delete_phonebook($phonebook, $deletefromdevice = true) {
		global $amp_conf;
		global $db;

		$phonebookid = $phonebook['id'];

		$this->phonebooks[$id] = $phonebook;

		if ($deletefromdevice) {
			$sql = "DELETE FROM digium_phones_device_phonebooks WHERE phonebookid = \"{$db->escapeSimple($phonebook['id'])}\"";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		$sql = "DELETE FROM digium_phones_phonebook_entry_settings WHERE phonebookid = \"{$db->escapeSimple($phonebook['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_phonebook_entries WHERE phonebookid = \"{$db->escapeSimple($phonebook['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_phonebooks WHERE id = \"{$db->escapeSimple($phonebook['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		if ($deletefromdevice) {
			unlink("{$amp_conf['ASTETCDIR']}/digium_phones/contacts-{$db->escapeSimple($phonebook['id'])}.xml");
		}
		needreload();
	}

	public function add_phonebook($phonebook) {
		global $db;

		$phonebookid = $phonebook['id'];

		// Phonebooks
		$sql = "INSERT INTO digium_phones_phonebooks (id, name) VALUES(\"{$db->escapeSimple($phonebook['id'])}\", \"{$db->escapeSimple($phonebook['name'])}\")";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		if ($phonebookid == 0) {
			$sql = "SELECT LAST_INSERT_ID()";

			$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
			if (DB::IsError($results)) {
				die_freepbx($results->getDebugInfo());
				return false;
			}

			foreach ($results as $row) {
				$phonebookid = $row['LAST_INSERT_ID()'];
			}
		}

		$this->phonebooks[$id] = $phonebook;

		// Phonebook entries
		$entries = array();
		$settings = array();
		$newid = 0;
		foreach ($phonebook['entries'] as $entryid=>$entry) {
			if ($entry == null) {
				continue;
			}

			$entries[] = '\''.$db->escapeSimple($newid).'\',\''.$db->escapeSimple($phonebookid).'\',\''.$db->escapeSimple($entry['extension']).'\'';

			foreach ($entry['settings'] as $key=>$val) {
				if ($val != '') {
					$settings[] = '\''.$db->escapeSimple($phonebookid).'\',\''.$db->escapeSimple($newid).'\',\''.$db->escapeSimple($key).'\',\''.$db->escapeSimple($val).'\'';
				}
			}

			$newid++;
		}

		if (count($entries) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_phonebook_entries (id, phonebookid, extension) VALUES (" . implode('),(', $entries) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);

			if (count($settings) > 0) {
				$sql = "INSERT INTO digium_phones_phonebook_entry_settings (phonebookid, phonebookentryid, keyword, val) VALUES (" . implode('),(', $settings) . ")";
				$result = $db->query($sql);
				if (DB::IsError($result)) {
					echo $result->getDebugInfo();
					return false;
				}
				unset($result);
			}
		}

		needreload();
	}

	public function read_ringtones() {
		global $db;

		$ringtones = array();
		$this->ringtones = array();

		$sql = "(SELECT * FROM digium_phones_ringtones WHERE builtin = 1 ORDER BY id ASC) ";
		$sql.= "UNION ";
		$sql.= "(SELECT * FROM digium_phones_ringtones WHERE builtin = 0 ORDER BY id DESC)";
		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$this->ringtones[$row['id']]['id'] = $row['id'];
			$this->ringtones[$row['id']]['name'] = $row['name'];
			$this->ringtones[$row['id']]['filename'] = $row['filename'];
		}

		unset($results);
	}

	public function add_ringtone($ringtone) {
		global $db;
		global $amp_conf;

		$sql = "INSERT INTO digium_phones_ringtones (id, name, filename) ";
		$sql.= "VALUES (NULL, '{$db->escapeSimple($ringtone['name'])}', '{$db->escapeSimple($ringtone['file']['name'])}')";
		$results = $db->query($sql);
		$id = mysql_insert_id();
		if (DB::IsError($results)) {
			echo $results->getDebugInfo();
			return false;
		}
		unset($results);

		if (!move_uploaded_file($ringtone['file']['tmp_name'], dirname(dirname(__FILE__)) . "/digium_phones/firmware_package/user_ringtone_".$id.".raw")) {
			?>
			<br>
			<span style="color: red; ">Uploaded file is not valid.</span>
			<br>
			<?php
		}

		needreload();
	}

	public function edit_ringtone($ringtone) {
		global $db;

		$sql = "UPDATE digium_phones_ringtones ";
		$sql.= "SET name = '{$db->escapeSimple($ringtone['name'])}' ";
		$sql.= "WHERE id = '{$db->escapeSimple($ringtone['id'])}'";
		$results = $db->query($sql);
		if (DB::IsError($results)) {
			echo $results->getDebugInfo();
			return false;
		}
		unset($results);

		needreload();
	}

	public function delete_ringtone($id) {
		global $amp_conf;
		global $db;

		unlink(dirname(dirname(__FILE__)) . "/digium_phones/firmware_package/user_ringtone_{$db->escapeSimple($id)}.raw");

		$sql = "DELETE FROM digium_phones_ringtones WHERE id = '{$db->escapeSimple($id)}'";
		$results = $db->query($sql);
		if (DB::IsError($results)) {
			echo $results->getDebugInfo();
			return false;
		}
		unset($results);

		needreload();
	}

	/**
	 * Initialize the firmware
	 */
	public function read_firmware() {
		if ($this->firmware_manager === NULL) {
			$this->firmware_manager = new firmware_manager($this);
		}
		$this->firmware_manager->refresh_packages();
	}

	/**
	 * Returns the firmware manager
	 */
	public function get_firmware_manager() {
		if ($this->firmware_manager === NULL) {
			$this->read_firmware();
		}
		return $this->firmware_manager;
	}

	public function get_locales() {
		global $db;

		if ($this->locales !== NULL) {
			return $this->locales;
		}

		$sql = "SELECT DISTINCT(`locale`) FROM digium_phones_voicemail_translations ORDER BY locale";
		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
		}

		$this->locales = array();
		foreach ($results as $row) {
			$this->locales[] = $row['locale'];
		}
		unset($results);
		return $this->locales;
	}

	public function get_voicemail_translations($locale) {
		global $db;

		if (isset($this->voicemail_translations[$locale])) {
			return $this->voicemail_translations[$locale];
		}

		$sql = "SELECT locale, keyword, val FROM digium_phones_voicemail_translations ";
		$sql .= "WHERE locale='{$db->escapeSimple($locale)}'";
		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return NULL;
		}

		if (count($results) === 0) {
			unset($results);
			return NULL;
		}

		$this->voicemail_translations[$locale] = array();
		foreach ($results as $row) {
			if ($row['keyword'] === 'IGNOREME') {
				// An ignored locale should never be returned as valid for the purposes
				// of voicemail translation tables
				unset($results);
				unset($this->voicemail_translations[$locale]);
				return NULL;
			}
			$this->voicemail_translations[$locale][$row['keyword']] = $row['val'];
		}
		unset($results);
		return $this->voicemail_translations[$locale];
	}

	public function read_alerts() {
		global $db;

		$alerts = array();
		$this->alerts = array();

		$sql = "SELECT alerts.id, alerts.name, alerts.alertinfo, alerts.type, alerts.ringtone AS ringtone_id, ringtones.name AS ringtone_name ";
		$sql.= "FROM digium_phones_alerts AS alerts ";
		$sql.= "LEFT OUTER JOIN digium_phones_ringtones AS ringtones ON alerts.ringtone = ringtones.id ";
		$sql.= "ORDER BY alerts.id";
		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$this->alerts[$row['id']]['id'] = $row['id'];
			$this->alerts[$row['id']]['name'] = $row['name'];
			$this->alerts[$row['id']]['alertinfo'] = $row['alertinfo'];
			$this->alerts[$row['id']]['type'] = $row['type'];
			$this->alerts[$row['id']]['ringtone_id'] = $row['ringtone_id'];
			$this->alerts[$row['id']]['ringtone_name'] = $row['ringtone_name'];
		}

		unset($results);
	}

	public function add_alert($alert) {
		global $db;

		$sql = "INSERT INTO digium_phones_alerts (name, alertinfo, type, ringtone) ";
		$sql.= "VALUES ('{$db->escapeSimple($alert['name'])}', '{$db->escapeSimple($alert['alertinfo'])}', '{$db->escapeSimple($alert['type'])}', '{$db->escapeSimple($alert['ringtone_id'])}')";
		$results = $db->query($sql);
		if (DB::IsError($results)) {
			echo $results->getDebugInfo();
			return false;
		}
		unset($results);

		needreload();
	}

	public function edit_alert($alert) {
		global $db;

		$sql = "UPDATE digium_phones_alerts SET ";
		$sql.= "name = '{$db->escapeSimple($alert['name'])}', ";
		$sql.= "alertinfo = '{$db->escapeSimple($alert['alertinfo'])}', ";
		$sql.= "type = '{$db->escapeSimple($alert['type'])}', ";
		$sql.= "ringtone = '{$db->escapeSimple($alert['ringtone_id'])}' ";
		$sql.= "WHERE id = '{$db->escapeSimple($alert['id'])}'";
		$results = $db->query($sql);
		if (DB::IsError($results)) {
			echo $results->getDebugInfo();
			return false;
		}
		unset($results);

		needreload();
	}

	public function delete_alert($id) {
		global $amp_conf;
		global $db;

		$sql = "DELETE FROM digium_phones_alerts WHERE id = '{$db->escapeSimple($id)}'";
		$results = $db->query($sql);
		if (DB::IsError($results)) {
			echo $results->getDebugInfo();
			return false;
		}
		unset($results);

		needreload();
	}

	/**
	 * Read in all the queue info from the database
	 */
	public function read_queues() {
		global $db;

		$queues = array();
		$this->queues = array();

		if (!function_exists('queues_list')) {
			return false;
		}
		$fqueues = queues_list();

		foreach ($fqueues as $queue) {
			$q = $this->queues[$queue[0]];
			$results = queues_get($queue[0]);
			if (empty($results)) {
				continue;
			}

			if ($q['id'] == null) {
				$q['id'] = $queue[0];
			}

			$q['name'] = $queue[1];

			foreach ($results['member'] as $member) {
				if (preg_match("/^(Local|Agent|SIP|DAHDI|ZAP|IAX2)\/([\d]+)(.*),([\d]+)$/", $member, $matches)) {
					$entry = $q['entries'][$matches[2]];
					$entry['location'] = $matches[1].'/'.$matches[2].$matches[3];
					$entry['dynamic'] = false;
					$entry['member'] = true;
					$q['entries'][$matches[2]] = $entry;
				}
			}

			$dynmembers = explode("\n", $results['dynmembers']);
			foreach ($dynmembers as $member) {
				if (preg_match("/^([\d]+),([\d]+)$/", $member, $matches)) {
					$entry = $q['entries'][$matches[1]];
					$entry['location'] = 'Local/'.$matches[1].'@from-queue/n';
					$entry['dynamic'] = true;
					$entry['member'] = true;
					$q['entries'][$matches[1]] = $entry;
				}
			}
			$this->queues[$queue[0]] = $q;
		}

		$sql = "SELECT * FROM digium_phones_queues ";
		$sql = $sql . "ORDER BY queueid, deviceid ";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$q = $this->queues[$row['queueid']];
			$q['id'] = $row['queueid'];

			$entry = $q['entries'][$row['deviceid']];
			$entry['deviceid'] = $row['deviceid'];
			$entry['permission'] = $row['permission'];
			$q['entries'][$row['deviceid']] = $entry;

			$this->queues[$row['queueid']] = $q;
		}
	}

	public function update_queue($queue) {
		$this->delete_queue($queue);
		$this->add_queue($queue);
	}

	public function delete_queue($queue) {
		global $amp_conf;
		global $db;

		$queueid = $queue['id'];
		$this->queues[$queueid] = $queue;

		$sql = "DELETE FROM digium_phones_queues WHERE queueid = \"{$db->escapeSimple($queue['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		needreload();
	}

	public function add_queue($queue) {
		global $db;

		$queueid = $queue['id'];
		$this->queues[$queueid] = $queue;

		$entries = array();
		foreach ($queue['entries'] as $entryid=>$entry) {
			$entries[] = '\''.$db->escapeSimple($queueid).'\',\''.$db->escapeSimple($entry['deviceid']).'\',\''.$db->escapeSimple($entry['permission']).'\'';
		}

		if (count($entries) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_queues (queueid, deviceid, permission) VALUES (" . implode('),(', $entries) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		needreload();
	}

	public function read_statuses() {
		global $db;

		$statuses = array();
		$this->statuses = array();

		$sql = "SELECT ss.id AS statusid, ss.name, sss.keyword, sss.val FROM digium_phones_statuses AS ss ";
		$sql = $sql . "  LEFT JOIN digium_phones_status_settings AS sss ON (ss.id = sss.statusid)";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$s = $this->statuses[$row['statusid']];
			$s['id'] = $row['statusid'];
			$s['name'] = $row['name'];
			if ($row['keyword'] != null) {
				$s['settings'][$row['keyword']] = $row['val'];
			}
			$this->statuses[$row['statusid']] = $s;
		}

		$sql = "SELECT ss.id AS statusid, ss.name, ses.id AS entryid, ses.text FROM digium_phones_statuses AS ss ";
		$sql = $sql . "  LEFT JOIN digium_phones_status_entries AS ses ON (ss.id = ses.statusid)";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$s = $this->statuses[$row['statusid']];
			$s['id'] = $row['statusid'];
			$s['name'] = $row['name'];
			if ($row['entryid'] != null) {
				$e = $row['text'];

				$s['entries'][$row['entryid']] = $e;
			}

			$this->statuses[$row['statusid']] = $s;
		}
	}

	public function update_status($status) {
		$this->delete_status($status, false);
		$this->add_status($status);
	}

	public function delete_status($status, $deletefromdevice = true) {
		global $amp_conf;
		global $db;

		$statusid = $status['id'];

		$this->statuses[$id] = $status;

		if ($deletefromdevice) {
			$sql = "DELETE FROM digium_phones_device_statuses WHERE statusid = \"{$db->escapeSimple($status['id'])}\"";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		$sql = "DELETE FROM digium_phones_status_settings WHERE statusid = \"{$db->escapeSimple($status['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_status_entries WHERE statusid = \"{$db->escapeSimple($status['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_statuses WHERE id = \"{$db->escapeSimple($status['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		needreload();
	}

	public function add_status($status) {
		global $db;

		$statusid = $status['id'];

		// Statuses
		$sql = "INSERT INTO digium_phones_statuses (id, name) VALUES(\"{$db->escapeSimple($status['id'])}\", \"{$db->escapeSimple($status['name'])}\")";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		if ($statusid == 0) {
			$sql = "SELECT LAST_INSERT_ID()";

			$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
			if (DB::IsError($results)) {
				die_freepbx($results->getDebugInfo());
				return false;
			}

			foreach ($results as $row) {
				$statusid = $row['LAST_INSERT_ID()'];
			}
		}

		$this->statuses[$id] = $status;

		// Status settings
		$statussettings = array();
		foreach ($status['settings'] as $key=>$val) {
			if ($val != '') {
				$statussettings[] = '\''.$db->escapeSimple($statusid).'\',\''.$db->escapeSimple($key).'\',\''.$db->escapeSimple($val).'\'';
			}
		}

		if (count($statussettings) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_status_settings (statusid, keyword, val) VALUES (" . implode('),(', $statussettings) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		$newid = 0;
		foreach ($status['entries'] as $entryid=>$entry) {
			if ($entry == null) {
				continue;
			}

			$entries[] = '\''.$db->escapeSimple($newid).'\',\''.$db->escapeSimple($statusid).'\',\''.$db->escapeSimple($entry).'\'';
			$newid++;
		}

		if (count($entries) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_status_entries (id, statusid, text) VALUES (" . implode('),(', $entries) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		needreload();
	}

	public function read_customapps() {
		global $db;

		$customapps = array();
		$this->customapps = array();

		$sql = "SELECT cs.id AS customappid, cs.name, css.keyword, css.val FROM digium_phones_customapps AS cs ";
		$sql = $sql . "  LEFT JOIN digium_phones_customapp_settings AS css ON (cs.id = css.customappid)";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$s = $this->customapps[$row['customappid']];
			$s['id'] = $row['customappid'];
			$s['name'] = $row['name'];
			if ($row['keyword'] != null) {
				$s['settings'][$row['keyword']] = $row['val'];
			}
			$this->customapps[$row['customappid']] = $s;
		}
	}

	public function update_customapp($customapp) {
		$this->delete_customapp($customapp, false);
		$this->add_customapp($customapp);
	}

	public function delete_customapp($customapp, $deletefromdevice = true) {
		global $amp_conf;
		global $db;

		$customappid = $customapp['id'];

		$this->customapps[$id] = $customapp;

		if ($deletefromdevice) {
			unlink($amp_conf['ASTETCDIR']."/digium_phones/application_{$db->escapeSimple($customappid)}.zip");

			$sql = "DELETE FROM digium_phones_device_customapps WHERE customappid = \"{$db->escapeSimple($customapp['id'])}\"";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		$sql = "DELETE FROM digium_phones_customapp_settings WHERE customappid = \"{$db->escapeSimple($customapp['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_customapps WHERE id = \"{$db->escapeSimple($customapp['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		needreload();
	}

	public function add_customapp($customapp) {
		global $db;
		global $amp_conf;

		$customappid = $customapp['id'];

		// Custom Applications
		$sql = "INSERT INTO digium_phones_customapps (id, name) VALUES(\"{$db->escapeSimple($customapp['id'])}\", \"{$db->escapeSimple($customapp['name'])}\")";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		if ($customappid == 0) {
			$sql = "SELECT LAST_INSERT_ID()";

			$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
			if (DB::IsError($results)) {
				die_freepbx($results->getDebugInfo());
				return false;
			}

			foreach ($results as $row) {
				$customappid = $row['LAST_INSERT_ID()'];
			}
		}

		$this->customapps[$customappid] = $customapp;

		// Custom Application settings
		$customappsettings = array();
		foreach ($customapp['settings'] as $key=>$val) {
			if ($val != '') {
				$customappsettings[] = '\''.$db->escapeSimple($customappid).'\',\''.$db->escapeSimple($key).'\',\''.$db->escapeSimple($val).'\'';
			}
		}

		if (count($customappsettings) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_customapp_settings (customappid, keyword, val) VALUES (" . implode('),(', $customappsettings) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		if (!move_uploaded_file($customapp['file']['tmp_name'], dirname(dirname(__FILE__)) . "/digium_phones/firmware_package/application_".$customappid.".zip")) {
			?>
			<br>
			<span style="color: red; ">Uploaded file is not valid.</span>
			<br>
			<?php
		}

		needreload();
	}

	public function read_networks() {
		global $db;

		$networks = array();
		$this->networks = array();

		$sql = "SELECT ns.id as networkid, ns.name, nss.keyword, nss.val FROM digium_phones_networks AS ns ";
		$sql = $sql . "  LEFT JOIN digium_phones_network_settings AS nss ON (ns.id = nss.networkid)";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$n = $this->networks[$row['networkid']];
			$n['id'] = $row['networkid'];
			$n['name'] = $row['name'];
			if ($row['keyword'] != null) {
				$n['settings'][$row['keyword']] = $row['val'];
			}

			if ($n['settings']['registration_address'] == '') {
				$n['settings']['registration_address'] = $this->get_general('mdns_address');
			}
			if ($n['settings']['registration_port'] == '') {
				$n['settings']['registration_port'] = $this->get_general('mdns_port');
			}
			if ($n['settings']['file_url_prefix'] == '') {
				$n['settings']['file_url_prefix'] = "http://{$this->get_general('mdns_address')}/admin/modules/digium_phones/firmware_package/";
			}
			if ($n['settings']['ntp_server'] == '') {
				$n['settings']['ntp_server'] = "0.digium.pool.ntp.org";
			}
			if ($n['settings']['syslog_server'] == '') {
				$n['settings']['syslog_server'] = $this->get_general('mdns_address');
			}
			if ($n['settings']['syslog_port'] == '') {
				$n['settings']['syslog_port'] = "514";
			}
			if ($n['settings']['sip_dscp'] == '') {
				$n['settings']['sip_dscp'] = "24";
			}
			if ($n['settings']['rtp_dscp'] == '') {
				$n['settings']['rtp_dscp'] = "46";
			}

			$this->networks[$row['networkid']] = $n;
		}
	}

	public function update_network($network) {
		$this->delete_network($network, false);
		$this->add_network($network);
	}

	public function delete_network($network, $deletefromdevice = true) {
		global $amp_conf;
		global $db;

		$networkid = $network['id'];

		$this->networks[$networkid] = $network;

		if ($deletefromdevice) {
			$sql = "DELETE FROM digium_phones_device_networks WHERE networkid = \"{$db->escapeSimple($network['id'])}\"";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		$sql = "DELETE FROM digium_phones_network_settings WHERE networkid = \"{$db->escapeSimple($network['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_networks WHERE id = \"{$db->escapeSimple($network['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		needreload();
	}

	public function add_network($network) {
		global $db;

		$networkid = $network['id'];

		// networks
		$sql = "INSERT INTO digium_phones_networks (id, name) VALUES(\"{$db->escapeSimple($network['id'])}\", \"{$db->escapeSimple($network['name'])}\")";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		if ($networkid == 0) {
			$sql = "SELECT LAST_INSERT_ID()";

			$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
			if (DB::IsError($results)) {
				die_freepbx($results->getDebugInfo());
				return false;
			}

			foreach ($results as $row) {
				$networkid = $row['LAST_INSERT_ID()'];
			}
		}

		$this->networks[$networkid] = $network;

		// Network settings
		$networksettings = array();
		foreach ($network['settings'] as $key=>$val) {
			if ($val != '') {
				$networksettings[] = '\''.$db->escapeSimple($networkid).'\',\''.$db->escapeSimple($key).'\',\''.$db->escapeSimple($val).'\'';
			}
		}

		if (count($networksettings) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_network_settings (networkid, keyword, val) VALUES (" . implode('),(', $networksettings) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		needreload();
	}

	public function read_externallines() {
		global $db;

		$externallines = array();
		$this->externallines = array();

		$sql = "SELECT ns.id as externallineid, ns.name, elss.keyword, elss.val FROM digium_phones_externallines AS ns ";
		$sql = $sql . "  LEFT JOIN digium_phones_externalline_settings AS elss ON (ns.id = elss.externallineid)";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$n = $this->externallines[$row['externallineid']];
			$n['id'] = $row['externallineid'];
			$n['name'] = $row['name'];
			if ($row['keyword'] != null) {
				$n['settings'][$row['keyword']] = $row['val'];
			}

			$this->externallines[$row['externallineid']] = $n;
		}
	}

	public function update_externalline($externalline) {
		$this->delete_externalline($externalline, false);
		$this->add_externalline($externalline);
	}

	public function delete_externalline($externalline, $deletefromdevice = true) {
		global $amp_conf;
		global $db;

		$externallineid = $externalline['id'];

		$this->externallines[$externallineid] = $externalline;

		if ($deletefromdevice) {
			$sql = "DELETE FROM digium_phones_device_externallines WHERE externallineid = \"{$db->escapeSimple($externalline['id'])}\"";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		$sql = "DELETE FROM digium_phones_externalline_settings WHERE externallineid = \"{$db->escapeSimple($externalline['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		$sql = "DELETE FROM digium_phones_externallines WHERE id = \"{$db->escapeSimple($externalline['id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		needreload();
	}

	public function add_externalline($externalline) {
		global $db;

		$externallineid = $externalline['id'];

		// external lines
		$sql = "INSERT INTO digium_phones_externallines (id, name) VALUES(\"{$db->escapeSimple($externalline['id'])}\", \"{$db->escapeSimple($externalline['name'])}\")";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		if ($externallineid == 0) {
			$sql = "SELECT LAST_INSERT_ID()";

			$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
			if (DB::IsError($results)) {
				die_freepbx($results->getDebugInfo());
				return false;
			}

			foreach ($results as $row) {
				$externallineid = $row['LAST_INSERT_ID()'];
			}
		}

		$this->externallines[$externallineid] = $externalline;

		// externalline settings
		$externalline_settings = array();
		foreach ($externalline['settings'] as $key=>$val) {
			if ($val != '') {
				$externalline_settings[] = '\''.$db->escapeSimple($externallineid).'\',\''.$db->escapeSimple($key).'\',\''.$db->escapeSimple($val).'\'';
			}
		}

		if (count($externalline_settings) > 0) {
			/* Multiple INSERT */
			$sql = "INSERT INTO digium_phones_externalline_settings (externallineid, keyword, val) VALUES (" . implode('),(', $externalline_settings) . ")";
			$result = $db->query($sql);
			if (DB::IsError($result)) {
				echo $result->getDebugInfo();
				return false;
			}
			unset($result);
		}

		needreload();
	}

	public function read_logos() {
		global $db;

		$logos = array();
		$this->logos = array();

		$sql = "SELECT * FROM digium_phones_logos ORDER BY id";

		$results = $db->getAll($sql, DB_FETCHMODE_ASSOC);
		if (DB::IsError($results)) {
			die_freepbx($results->getDebugInfo());
			return false;
		}

		foreach ($results as $row) {
			$s = $this->logos[$row['id']];
			$s['id'] = $row['id'];
			$s['name'] = $row['name'];
			$s['model'] = $row['model'];

			$this->logos[$row['id']] = $s;
		}
	}

	public function add_logo($logo) {
		global $db;

		$sql = "INSERT INTO digium_phones_logos (name, model) VALUES(\"{$db->escapeSimple($logo['logo_name'])}\", \"{$db->escapeSimple($logo['logo_model'])}\")";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		// logo is moved to the right spot in digium_phones/views/digium_phones_logos.php

		needreload();
	}

	public function edit_logos($logo) {
		global $db;

		$sql = "UPDATE digium_phones_logos SET name=\"{$db->escapeSimple($logo['logo_name'])}\", model=\"{$db->escapeSimple($logo['logo_model'])}\" WHERE id=\"{$db->escapeSimple($logo['logo_id'])}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		// logo is moved to the right spot in digium_phones/views/digium_phones_logos.php

		needreload();
	}

	public function delete_logo($logo_id) {
		global $amp_conf;
		global $db;

		// remove from db
		$sql = "DELETE FROM digium_phones_logos WHERE id = \"{$db->escapeSimple($logo_id)}\"";
		$result = $db->query($sql);
		if (DB::IsError($result)) {
			echo $result->getDebugInfo();
			return false;
		}
		unset($result);

		// remove from disk
		unlink($amp_conf['ASTETCDIR']."/digium_phones/user_image_{$db->escapeSimple($logo_id)}.png");

		needreload();

	}
}

if ( !function_exists('json_decode') ){
	function json_decode($content, $assoc=false){
		require_once 'Services/JSON.php';
		if ( $assoc ){
			$json = new Services_JSON(SERVICES_JSON_LOOSE_TYPE);
		} else {
			$json = new Services_JSON;
		}
		return $json->decode($content);
	}
}

function timezone(){
	$tz = '<option value=""></option>
<option value="Africa/Abidjan">Africa/Abidjan</option>
<option value="Africa/Accra">Africa/Accra</option>
<option value="Africa/Addis_Ababa">Africa/Addis_Ababa</option>
<option value="Africa/Algiers">Africa/Algiers</option>
<option value="Africa/Asmara">Africa/Asmara</option>
<option value="Africa/Bamako">Africa/Bamako</option>
<option value="Africa/Bangui">Africa/Bangui</option>
<option value="Africa/Banjul">Africa/Banjul</option>
<option value="Africa/Bissau">Africa/Bissau</option>
<option value="Africa/Blantyre">Africa/Blantyre</option>
<option value="Africa/Brazzaville">Africa/Brazzaville</option>
<option value="Africa/Bujumbura">Africa/Bujumbura</option>
<option value="Africa/Cairo">Africa/Cairo</option>
<option value="Africa/Casablanca">Africa/Casablanca</option>
<option value="Africa/Ceuta">Africa/Ceuta</option>
<option value="Africa/Conakry">Africa/Conakry</option>
<option value="Africa/Dakar">Africa/Dakar</option>
<option value="Africa/Dar_es_Salaam">Africa/Dar_es_Salaam</option>
<option value="Africa/Djibouti">Africa/Djibouti</option>
<option value="Africa/Douala">Africa/Douala</option>
<option value="Africa/El_Aaiun">Africa/El_Aaiun</option>
<option value="Africa/Freetown">Africa/Freetown</option>
<option value="Africa/Gaborone">Africa/Gaborone</option>
<option value="Africa/Harare">Africa/Harare</option>
<option value="Africa/Johannesburg">Africa/Johannesburg</option>
<option value="Africa/Juba">Africa/Juba</option>
<option value="Africa/Kampala">Africa/Kampala</option>
<option value="Africa/Khartoum">Africa/Khartoum</option>
<option value="Africa/Kigali">Africa/Kigali</option>
<option value="Africa/Kinshasa">Africa/Kinshasa</option>
<option value="Africa/Lagos">Africa/Lagos</option>
<option value="Africa/Libreville">Africa/Libreville</option>
<option value="Africa/Lome">Africa/Lome</option>
<option value="Africa/Luanda">Africa/Luanda</option>
<option value="Africa/Lubumbashi">Africa/Lubumbashi</option>
<option value="Africa/Lusaka">Africa/Lusaka</option>
<option value="Africa/Malabo">Africa/Malabo</option>
<option value="Africa/Maputo">Africa/Maputo</option>
<option value="Africa/Maseru">Africa/Maseru</option>
<option value="Africa/Mbabane">Africa/Mbabane</option>
<option value="Africa/Mogadishu">Africa/Mogadishu</option>
<option value="Africa/Monrovia">Africa/Monrovia</option>
<option value="Africa/Nairobi">Africa/Nairobi</option>
<option value="Africa/Ndjamena">Africa/Ndjamena</option>
<option value="Africa/Niamey">Africa/Niamey</option>
<option value="Africa/Nouakchott">Africa/Nouakchott</option>
<option value="Africa/Ouagadougou">Africa/Ouagadougou</option>
<option value="Africa/Porto-Novo">Africa/Porto-Novo</option>
<option value="Africa/Sao_Tome">Africa/Sao_Tome</option>
<option value="Africa/Tripoli">Africa/Tripoli</option>
<option value="Africa/Tunis">Africa/Tunis</option>
<option value="Africa/Windhoek">Africa/Windhoek</option>
<option value="America/Adak">America/Adak</option>
<option value="America/Anchorage">America/Anchorage</option>
<option value="America/Anguilla">America/Anguilla</option>
<option value="America/Antigua">America/Antigua</option>
<option value="America/Araguaina">America/Araguaina</option>
<option value="America/Argentina/Buenos_Aires">America/Argentina/Buenos_Aires</option>
<option value="America/Argentina/Catamarca">America/Argentina/Catamarca</option>
<option value="America/Argentina/Cordoba">America/Argentina/Cordoba</option>
<option value="America/Argentina/Jujuy">America/Argentina/Jujuy</option>
<option value="America/Argentina/La_Rioja">America/Argentina/La_Rioja</option>
<option value="America/Argentina/Mendoza">America/Argentina/Mendoza</option>
<option value="America/Argentina/Rio_Gallegos">America/Argentina/Rio_Gallegos</option>
<option value="America/Argentina/Salta">America/Argentina/Salta</option>
<option value="America/Argentina/San_Juan">America/Argentina/San_Juan</option>
<option value="America/Argentina/San_Luis">America/Argentina/San_Luis</option>
<option value="America/Argentina/Tucuman">America/Argentina/Tucuman</option>
<option value="America/Argentina/Ushuaia">America/Argentina/Ushuaia</option>
<option value="America/Aruba">America/Aruba</option>
<option value="America/Asuncion">America/Asuncion</option>
<option value="America/Atikokan">America/Atikokan</option>
<option value="America/Bahia">America/Bahia</option>
<option value="America/Bahia_Banderas">America/Bahia_Banderas</option>
<option value="America/Barbados">America/Barbados</option>
<option value="America/Belem">America/Belem</option>
<option value="America/Belize">America/Belize</option>
<option value="America/Blanc-Sablon">America/Blanc-Sablon</option>
<option value="America/Boa_Vista">America/Boa_Vista</option>
<option value="America/Bogota">America/Bogota</option>
<option value="America/Boise">America/Boise</option>
<option value="America/Cambridge_Bay">America/Cambridge_Bay</option>
<option value="America/Campo_Grande">America/Campo_Grande</option>
<option value="America/Cancun">America/Cancun</option>
<option value="America/Caracas">America/Caracas</option>
<option value="America/Cayenne">America/Cayenne</option>
<option value="America/Cayman">America/Cayman</option>
<option value="America/Chicago">America/Chicago</option>
<option value="America/Chihuahua">America/Chihuahua</option>
<option value="America/Costa_Rica">America/Costa_Rica</option>
<option value="America/Cuiaba">America/Cuiaba</option>
<option value="America/Curacao">America/Curacao</option>
<option value="America/Danmarkshavn">America/Danmarkshavn</option>
<option value="America/Dawson">America/Dawson</option>
<option value="America/Dawson_Creek">America/Dawson_Creek</option>
<option value="America/Denver">America/Denver</option>
<option value="America/Detroit">America/Detroit</option>
<option value="America/Dominica">America/Dominica</option>
<option value="America/Edmonton">America/Edmonton</option>
<option value="America/Eirunepe">America/Eirunepe</option>
<option value="America/El_Salvador">America/El_Salvador</option>
<option value="America/Fortaleza">America/Fortaleza</option>
<option value="America/Glace_Bay">America/Glace_Bay</option>
<option value="America/Godthab">America/Godthab</option>
<option value="America/Goose_Bay">America/Goose_Bay</option>
<option value="America/Grand_Turk">America/Grand_Turk</option>
<option value="America/Grenada">America/Grenada</option>
<option value="America/Guadeloupe">America/Guadeloupe</option>
<option value="America/Guatemala">America/Guatemala</option>
<option value="America/Guayaquil">America/Guayaquil</option>
<option value="America/Guyana">America/Guyana</option>
<option value="America/Halifax">America/Halifax</option>
<option value="America/Havana">America/Havana</option>
<option value="America/Hermosillo">America/Hermosillo</option>
<option value="America/Indiana/Indianapolis">America/Indiana/Indianapolis</option>
<option value="America/Indiana/Knox">America/Indiana/Knox</option>
<option value="America/Indiana/Marengo">America/Indiana/Marengo</option>
<option value="America/Indiana/Petersburg">America/Indiana/Petersburg</option>
<option value="America/Indiana/Tell_City">America/Indiana/Tell_City</option>
<option value="America/Indiana/Vevay">America/Indiana/Vevay</option>
<option value="America/Indiana/Vincennes">America/Indiana/Vincennes</option>
<option value="America/Indiana/Winamac">America/Indiana/Winamac</option>
<option value="America/Inuvik">America/Inuvik</option>
<option value="America/Iqaluit">America/Iqaluit</option>
<option value="America/Jamaica">America/Jamaica</option>
<option value="America/Juneau">America/Juneau</option>
<option value="America/Kentucky/Louisville">America/Kentucky/Louisville</option>
<option value="America/Kentucky/Monticello">America/Kentucky/Monticello</option>
<option value="America/Kralendijk">America/Kralendijk</option>
<option value="America/La_Paz">America/La_Paz</option>
<option value="America/Lima">America/Lima</option>
<option value="America/Los_Angeles">America/Los_Angeles</option>
<option value="America/Lower_Princes">America/Lower_Princes</option>
<option value="America/Maceio">America/Maceio</option>
<option value="America/Managua">America/Managua</option>
<option value="America/Manaus">America/Manaus</option>
<option value="America/Marigot">America/Marigot</option>
<option value="America/Martinique">America/Martinique</option>
<option value="America/Matamoros">America/Matamoros</option>
<option value="America/Mazatlan">America/Mazatlan</option>
<option value="America/Menominee">America/Menominee</option>
<option value="America/Merida">America/Merida</option>
<option value="America/Metlakatla">America/Metlakatla</option>
<option value="America/Mexico_City">America/Mexico_City</option>
<option value="America/Miquelon">America/Miquelon</option>
<option value="America/Moncton">America/Moncton</option>
<option value="America/Monterrey">America/Monterrey</option>
<option value="America/Montevideo">America/Montevideo</option>
<option value="America/Montreal">America/Montreal</option>
<option value="America/Montserrat">America/Montserrat</option>
<option value="America/Nassau">America/Nassau</option>
<option value="America/New_York">America/New_York</option>
<option value="America/Nipigon">America/Nipigon</option>
<option value="America/Nome">America/Nome</option>
<option value="America/Noronha">America/Noronha</option>
<option value="America/North_Dakota/Beulah">America/North_Dakota/Beulah</option>
<option value="America/North_Dakota/Center">America/North_Dakota/Center</option>
<option value="America/North_Dakota/New_Salem">America/North_Dakota/New_Salem</option>
<option value="America/Ojinaga">America/Ojinaga</option>
<option value="America/Panama">America/Panama</option>
<option value="America/Pangnirtung">America/Pangnirtung</option>
<option value="America/Paramaribo">America/Paramaribo</option>
<option value="America/Phoenix">America/Phoenix</option>
<option value="America/Port-au-Prince">America/Port-au-Prince</option>
<option value="America/Port_of_Spain">America/Port_of_Spain</option>
<option value="America/Porto_Velho">America/Porto_Velho</option>
<option value="America/Puerto_Rico">America/Puerto_Rico</option>
<option value="America/Rainy_River">America/Rainy_River</option>
<option value="America/Rankin_Inlet">America/Rankin_Inlet</option>
<option value="America/Recife">America/Recife</option>
<option value="America/Regina">America/Regina</option>
<option value="America/Resolute">America/Resolute</option>
<option value="America/Rio_Branco">America/Rio_Branco</option>
<option value="America/Santa_Isabel">America/Santa_Isabel</option>
<option value="America/Santarem">America/Santarem</option>
<option value="America/Santiago">America/Santiago</option>
<option value="America/Santo_Domingo">America/Santo_Domingo</option>
<option value="America/Sao_Paulo">America/Sao_Paulo</option>
<option value="America/Scoresbysund">America/Scoresbysund</option>
<option value="America/Shiprock">America/Shiprock</option>
<option value="America/Sitka">America/Sitka</option>
<option value="America/St_Barthelemy">America/St_Barthelemy</option>
<option value="America/St_Johns">America/St_Johns</option>
<option value="America/St_Kitts">America/St_Kitts</option>
<option value="America/St_Lucia">America/St_Lucia</option>
<option value="America/St_Thomas">America/St_Thomas</option>
<option value="America/St_Vincent">America/St_Vincent</option>
<option value="America/Swift_Current">America/Swift_Current</option>
<option value="America/Tegucigalpa">America/Tegucigalpa</option>
<option value="America/Thule">America/Thule</option>
<option value="America/Thunder_Bay">America/Thunder_Bay</option>
<option value="America/Tijuana">America/Tijuana</option>
<option value="America/Toronto">America/Toronto</option>
<option value="America/Tortola">America/Tortola</option>
<option value="America/Vancouver">America/Vancouver</option>
<option value="America/Whitehorse">America/Whitehorse</option>
<option value="America/Winnipeg">America/Winnipeg</option>
<option value="America/Yakutat">America/Yakutat</option>
<option value="America/Yellowknife">America/Yellowknife</option>
<option value="Antarctica/Casey">Antarctica/Casey</option>
<option value="Antarctica/Davis">Antarctica/Davis</option>
<option value="Antarctica/DumontDUrville">Antarctica/DumontDUrville</option>
<option value="Antarctica/Macquarie">Antarctica/Macquarie</option>
<option value="Antarctica/Mawson">Antarctica/Mawson</option>
<option value="Antarctica/McMurdo">Antarctica/McMurdo</option>
<option value="Antarctica/Palmer">Antarctica/Palmer</option>
<option value="Antarctica/Rothera">Antarctica/Rothera</option>
<option value="Antarctica/South_Pole">Antarctica/South_Pole</option>
<option value="Antarctica/Syowa">Antarctica/Syowa</option>
<option value="Antarctica/Vostok">Antarctica/Vostok</option>
<option value="Arctic/Longyearbyen">Arctic/Longyearbyen</option>
<option value="Asia/Aden">Asia/Aden</option>
<option value="Asia/Almaty">Asia/Almaty</option>
<option value="Asia/Amman">Asia/Amman</option>
<option value="Asia/Anadyr">Asia/Anadyr</option>
<option value="Asia/Aqtau">Asia/Aqtau</option>
<option value="Asia/Aqtobe">Asia/Aqtobe</option>
<option value="Asia/Ashgabat">Asia/Ashgabat</option>
<option value="Asia/Baghdad">Asia/Baghdad</option>
<option value="Asia/Bahrain">Asia/Bahrain</option>
<option value="Asia/Baku">Asia/Baku</option>
<option value="Asia/Bangkok">Asia/Bangkok</option>
<option value="Asia/Beirut">Asia/Beirut</option>
<option value="Asia/Bishkek">Asia/Bishkek</option>
<option value="Asia/Brunei">Asia/Brunei</option>
<option value="Asia/Choibalsan">Asia/Choibalsan</option>
<option value="Asia/Chongqing">Asia/Chongqing</option>
<option value="Asia/Colombo">Asia/Colombo</option>
<option value="Asia/Damascus">Asia/Damascus</option>
<option value="Asia/Dhaka">Asia/Dhaka</option>
<option value="Asia/Dili">Asia/Dili</option>
<option value="Asia/Dubai">Asia/Dubai</option>
<option value="Asia/Dushanbe">Asia/Dushanbe</option>
<option value="Asia/Gaza">Asia/Gaza</option>
<option value="Asia/Harbin">Asia/Harbin</option>
<option value="Asia/Hebron">Asia/Hebron</option>
<option value="Asia/Ho_Chi_Minh">Asia/Ho_Chi_Minh</option>
<option value="Asia/Hong_Kong">Asia/Hong_Kong</option>
<option value="Asia/Hovd">Asia/Hovd</option>
<option value="Asia/Irkutsk">Asia/Irkutsk</option>
<option value="Asia/Jakarta">Asia/Jakarta</option>
<option value="Asia/Jayapura">Asia/Jayapura</option>
<option value="Asia/Jerusalem">Asia/Jerusalem</option>
<option value="Asia/Kabul">Asia/Kabul</option>
<option value="Asia/Kamchatka">Asia/Kamchatka</option>
<option value="Asia/Karachi">Asia/Karachi</option>
<option value="Asia/Kashgar">Asia/Kashgar</option>
<option value="Asia/Kathmandu">Asia/Kathmandu</option>
<option value="Asia/Kolkata">Asia/Kolkata</option>
<option value="Asia/Krasnoyarsk">Asia/Krasnoyarsk</option>
<option value="Asia/Kuala_Lumpur">Asia/Kuala_Lumpur</option>
<option value="Asia/Kuching">Asia/Kuching</option>
<option value="Asia/Kuwait">Asia/Kuwait</option>
<option value="Asia/Macau">Asia/Macau</option>
<option value="Asia/Magadan">Asia/Magadan</option>
<option value="Asia/Makassar">Asia/Makassar</option>
<option value="Asia/Manila">Asia/Manila</option>
<option value="Asia/Muscat">Asia/Muscat</option>
<option value="Asia/Nicosia">Asia/Nicosia</option>
<option value="Asia/Novokuznetsk">Asia/Novokuznetsk</option>
<option value="Asia/Novosibirsk">Asia/Novosibirsk</option>
<option value="Asia/Omsk">Asia/Omsk</option>
<option value="Asia/Oral">Asia/Oral</option>
<option value="Asia/Phnom_Penh">Asia/Phnom_Penh</option>
<option value="Asia/Pontianak">Asia/Pontianak</option>
<option value="Asia/Pyongyang">Asia/Pyongyang</option>
<option value="Asia/Qatar">Asia/Qatar</option>
<option value="Asia/Qyzylorda">Asia/Qyzylorda</option>
<option value="Asia/Rangoon">Asia/Rangoon</option>
<option value="Asia/Riyadh">Asia/Riyadh</option>
<option value="Asia/Sakhalin">Asia/Sakhalin</option>
<option value="Asia/Samarkand">Asia/Samarkand</option>
<option value="Asia/Seoul">Asia/Seoul</option>
<option value="Asia/Shanghai">Asia/Shanghai</option>
<option value="Asia/Singapore">Asia/Singapore</option>
<option value="Asia/Taipei">Asia/Taipei</option>
<option value="Asia/Tashkent">Asia/Tashkent</option>
<option value="Asia/Tbilisi">Asia/Tbilisi</option>
<option value="Asia/Tehran">Asia/Tehran</option>
<option value="Asia/Thimphu">Asia/Thimphu</option>
<option value="Asia/Tokyo">Asia/Tokyo</option>
<option value="Asia/Ulaanbaatar">Asia/Ulaanbaatar</option>
<option value="Asia/Urumqi">Asia/Urumqi</option>
<option value="Asia/Vientiane">Asia/Vientiane</option>
<option value="Asia/Vladivostok">Asia/Vladivostok</option>
<option value="Asia/Yakutsk">Asia/Yakutsk</option>
<option value="Asia/Yekaterinburg">Asia/Yekaterinburg</option>
<option value="Asia/Yerevan">Asia/Yerevan</option>
<option value="Atlantic/Azores">Atlantic/Azores</option>
<option value="Atlantic/Bermuda">Atlantic/Bermuda</option>
<option value="Atlantic/Canary">Atlantic/Canary</option>
<option value="Atlantic/Cape_Verde">Atlantic/Cape_Verde</option>
<option value="Atlantic/Faroe">Atlantic/Faroe</option>
<option value="Atlantic/Madeira">Atlantic/Madeira</option>
<option value="Atlantic/Reykjavik">Atlantic/Reykjavik</option>
<option value="Atlantic/South_Georgia">Atlantic/South_Georgia</option>
<option value="Atlantic/St_Helena">Atlantic/St_Helena</option>
<option value="Atlantic/Stanley">Atlantic/Stanley</option>
<option value="Australia/Adelaide">Australia/Adelaide</option>
<option value="Australia/Brisbane">Australia/Brisbane</option>
<option value="Australia/Broken_Hill">Australia/Broken_Hill</option>
<option value="Australia/Currie">Australia/Currie</option>
<option value="Australia/Darwin">Australia/Darwin</option>
<option value="Australia/Eucla">Australia/Eucla</option>
<option value="Australia/Hobart">Australia/Hobart</option>
<option value="Australia/Lindeman">Australia/Lindeman</option>
<option value="Australia/Lord_Howe">Australia/Lord_Howe</option>
<option value="Australia/Melbourne">Australia/Melbourne</option>
<option value="Australia/Perth">Australia/Perth</option>
<option value="Australia/Sydney">Australia/Sydney</option>
<option value="Europe/Amsterdam">Europe/Amsterdam</option>
<option value="Europe/Andorra">Europe/Andorra</option>
<option value="Europe/Athens">Europe/Athens</option>
<option value="Europe/Belgrade">Europe/Belgrade</option>
<option value="Europe/Berlin">Europe/Berlin</option>
<option value="Europe/Bratislava">Europe/Bratislava</option>
<option value="Europe/Brussels">Europe/Brussels</option>
<option value="Europe/Bucharest">Europe/Bucharest</option>
<option value="Europe/Budapest">Europe/Budapest</option>
<option value="Europe/Chisinau">Europe/Chisinau</option>
<option value="Europe/Copenhagen">Europe/Copenhagen</option>
<option value="Europe/Dublin">Europe/Dublin</option>
<option value="Europe/Gibraltar">Europe/Gibraltar</option>
<option value="Europe/Guernsey">Europe/Guernsey</option>
<option value="Europe/Helsinki">Europe/Helsinki</option>
<option value="Europe/Isle_of_Man">Europe/Isle_of_Man</option>
<option value="Europe/Istanbul">Europe/Istanbul</option>
<option value="Europe/Jersey">Europe/Jersey</option>
<option value="Europe/Kaliningrad">Europe/Kaliningrad</option>
<option value="Europe/Kiev">Europe/Kiev</option>
<option value="Europe/Lisbon">Europe/Lisbon</option>
<option value="Europe/Ljubljana">Europe/Ljubljana</option>
<option value="Europe/London">Europe/London</option>
<option value="Europe/Luxembourg">Europe/Luxembourg</option>
<option value="Europe/Madrid">Europe/Madrid</option>
<option value="Europe/Malta">Europe/Malta</option>
<option value="Europe/Mariehamn">Europe/Mariehamn</option>
<option value="Europe/Minsk">Europe/Minsk</option>
<option value="Europe/Monaco">Europe/Monaco</option>
<option value="Europe/Moscow">Europe/Moscow</option>
<option value="Europe/Oslo">Europe/Oslo</option>
<option value="Europe/Paris">Europe/Paris</option>
<option value="Europe/Podgorica">Europe/Podgorica</option>
<option value="Europe/Prague">Europe/Prague</option>
<option value="Europe/Riga">Europe/Riga</option>
<option value="Europe/Rome">Europe/Rome</option>
<option value="Europe/Samara">Europe/Samara</option>
<option value="Europe/San_Marino">Europe/San_Marino</option>
<option value="Europe/Sarajevo">Europe/Sarajevo</option>
<option value="Europe/Simferopol">Europe/Simferopol</option>
<option value="Europe/Skopje">Europe/Skopje</option>
<option value="Europe/Sofia">Europe/Sofia</option>
<option value="Europe/Stockholm">Europe/Stockholm</option>
<option value="Europe/Tallinn">Europe/Tallinn</option>
<option value="Europe/Tirane">Europe/Tirane</option>
<option value="Europe/Uzhgorod">Europe/Uzhgorod</option>
<option value="Europe/Vaduz">Europe/Vaduz</option>
<option value="Europe/Vatican">Europe/Vatican</option>
<option value="Europe/Vienna">Europe/Vienna</option>
<option value="Europe/Vilnius">Europe/Vilnius</option>
<option value="Europe/Volgograd">Europe/Volgograd</option>
<option value="Europe/Warsaw">Europe/Warsaw</option>
<option value="Europe/Zagreb">Europe/Zagreb</option>
<option value="Europe/Zaporozhye">Europe/Zaporozhye</option>
<option value="Europe/Zurich">Europe/Zurich</option>
<option value="Indian/Antananarivo">Indian/Antananarivo</option>
<option value="Indian/Chagos">Indian/Chagos</option>
<option value="Indian/Christmas">Indian/Christmas</option>
<option value="Indian/Cocos">Indian/Cocos</option>
<option value="Indian/Comoro">Indian/Comoro</option>
<option value="Indian/Kerguelen">Indian/Kerguelen</option>
<option value="Indian/Mahe">Indian/Mahe</option>
<option value="Indian/Maldives">Indian/Maldives</option>
<option value="Indian/Mauritius">Indian/Mauritius</option>
<option value="Indian/Mayotte">Indian/Mayotte</option>
<option value="Indian/Reunion">Indian/Reunion</option>
<option value="Pacific/Apia">Pacific/Apia</option>
<option value="Pacific/Auckland">Pacific/Auckland</option>
<option value="Pacific/Chatham">Pacific/Chatham</option>
<option value="Pacific/Chuuk">Pacific/Chuuk</option>
<option value="Pacific/Easter">Pacific/Easter</option>
<option value="Pacific/Efate">Pacific/Efate</option>
<option value="Pacific/Enderbury">Pacific/Enderbury</option>
<option value="Pacific/Fakaofo">Pacific/Fakaofo</option>
<option value="Pacific/Fiji">Pacific/Fiji</option>
<option value="Pacific/Funafuti">Pacific/Funafuti</option>
<option value="Pacific/Galapagos">Pacific/Galapagos</option>
<option value="Pacific/Gambier">Pacific/Gambier</option>
<option value="Pacific/Guadalcanal">Pacific/Guadalcanal</option>
<option value="Pacific/Guam">Pacific/Guam</option>
<option value="Pacific/Honolulu">Pacific/Honolulu</option>
<option value="Pacific/Johnston">Pacific/Johnston</option>
<option value="Pacific/Kiritimati">Pacific/Kiritimati</option>
<option value="Pacific/Kosrae">Pacific/Kosrae</option>
<option value="Pacific/Kwajalein">Pacific/Kwajalein</option>
<option value="Pacific/Majuro">Pacific/Majuro</option>
<option value="Pacific/Marquesas">Pacific/Marquesas</option>
<option value="Pacific/Midway">Pacific/Midway</option>
<option value="Pacific/Nauru">Pacific/Nauru</option>
<option value="Pacific/Niue">Pacific/Niue</option>
<option value="Pacific/Norfolk">Pacific/Norfolk</option>
<option value="Pacific/Noumea">Pacific/Noumea</option>
<option value="Pacific/Pago_Pago">Pacific/Pago_Pago</option>
<option value="Pacific/Palau">Pacific/Palau</option>
<option value="Pacific/Pitcairn">Pacific/Pitcairn</option>
<option value="Pacific/Pohnpei">Pacific/Pohnpei</option>
<option value="Pacific/Port_Moresby">Pacific/Port_Moresby</option>
<option value="Pacific/Rarotonga">Pacific/Rarotonga</option>
<option value="Pacific/Saipan">Pacific/Saipan</option>
<option value="Pacific/Tahiti">Pacific/Tahiti</option>
<option value="Pacific/Tarawa">Pacific/Tarawa</option>
<option value="Pacific/Tongatapu">Pacific/Tongatapu</option>
<option value="Pacific/Wake">Pacific/Wake</option>
<option value="Pacific/Wallis">Pacific/Wallis</option>
<option value="UTC">UTC</option>';
return ($tz);
}


// End of File
