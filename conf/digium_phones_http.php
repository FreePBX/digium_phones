<?php

/**
 * \file
 * FreePBX Digium Phones Config Module
 *
 * Copyright (c) 2017, Digium, Inc.
 *
 * Author: Scott Griepentrog <sgriepentrog@digium.com>
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
 * generate http accessible files that the phone may request
 */

function digium_phones_http_generate_all($conf)
{
	global $amp_conf;

	$http_path = digium_phones_get_http_path();

	foreach ($conf->digium_phones->get_phonebooks() as $id => $phonebook) {
		if ($id == -1) {
			$id = 'internal';
		}

		// generate contacts xml
		$filename = "contacts-$id.xml";

		$filepath = $http_path.'/'.$filename;
		file_put_contents($filepath, digium_phones_http_contacts($conf, $id, $phonebook));
		// if root ran fwconsole the file created may not be accessible by web user
		@chown($filepath, $amp_conf['AMPASTERISKWEBUSER']);

		// create blf generator as php script to fit blfs into requesting phone model
		$filename = "blf-$id.php";

		$filepath = $http_path.'/'.$filename;
		file_put_contents($filepath, digium_phones_http_blf_php($conf, $id, $phonebook));
		// if root ran fwconsole the file created may not be accessible by web user
		@chown($filepath, $amp_conf['AMPASTERISKWEBUSER']);
	}
}

function digium_phones_http_contacts($conf, $id, $phonebook)
{
	$contact_keys = array('prefix', 'first_name', 'second_name', 'last_name', 'suffix',
				'organization', 'job_title', 'location', 'notes');

	$xml = new SimpleXmlElement('<contacts/>');

	$xml->addAttribute('group_name', $id); //$phonebook['name']);
	$xml->addAttribute('editable', '0');
	$xml->addAttribute('id', rand(0, 10000));

	// for the internal phonebook, add users
	if ($id == 'internal') {
		$phonebook['entries'] = array();
		$phonebook['name'] = $id;

		foreach ($conf->sorted_users as $user) {

			$device = $conf->digium_phones->get_core_device($user['id']);
			$user_info = $conf->digium_phones->get_core_user($user['id']);

			$phonebook['entries'][] = array(
				'extension' => $user['id'],
				'settings' => array(
					'type' => 'internal',
					'label' => $device['description'],
					'subscription_url' => 'auto_hint_'.$user['id'],
					'has_voicemail' => ($user_info['voicemail'] != 'novm'),
				),
			);
		}
	}

	if (!empty($phonebook['entries'])) foreach($phonebook['entries'] as $entryid => $entry) {
		$contact = $xml->addChild('contact');
		$contact->addAttribute('id', $entry['extension']);

		$line = $conf->digium_phones->get_extension_settings($entry['extension']);
		if (!$line) {
			$line = array('settings');
			foreach ($contact_keys as $key) {
				$line['settings'][$key] = '';
			}
			$line['settings']['first_name'] = $entry['settings']['label'];
		}

		// custom phonebook internal entries are missing the label, look up the extension
		if (empty($line['settings']['first_name'])) {
			$device = $conf->digium_phones->get_core_device($entry['extension']);
			$line['settings']['first_name'] = $device['description'];
		}

		foreach ($contact_keys as $key) {
			$contact->addAttribute($key, $line['settings'][$key]);
		}

		$contact->addAttribute('contact_type', 'sip');
		$contact->addAttribute('account_id', $entry['extension']);

		$subto = $entry['extension'];
		if (!empty($entry['settings']['subscription_url'])) {
			$subto = $entry['settings']['subscription_url'];
		}
		$contact->addAttribute('subscribe_to', $subto);

		if (!empty($line['settings']['email'])) {
			$emails = $contact->addChild('emails');
			$email = $emails->addChild('email');
			$email->addAttribute('address', $line['settings']['email']);
			$email->addAttribute('label', 'Primary');
			$email->addAttribute('primary', '1');
		}

		$actions = $contact->addChild('actions');

		$primary = $actions->addChild('action');
		$primary->addAttribute('id', 'primary');
		$primary->addAttribute('dial', $entry['extension']);
		$primary->addAttribute('label', 'CL_ACTN_SIP');
		$primary->addAttribute('name', 'CN_ACTN_DIAL');
		$primary->addAttribute('transfer_name', 'CN_ACTN_TRANSFER');

		if ($entry['settings']['has_voicemail']) {
			$dial_vm = $actions->addChild('action');
			$dial_vm->addAttribute('id', 'dial_vm');
			$dial_vm->addAttribute('dial', $entry['extension']);
			$dial_vm->addAttribute('dial_prefix', '');
			$dial_vm->addAttribute('label', 'CL_ACTN_SIP');
			$dial_vm->addAttribute('name', 'CONTACT_ACT_DIAL_VM');
			$headers = $dial_vm->addChild('headers');
			$feature = $headers->addChild('header');
			$feature->addAttribute('key', 'X-Digium-Call-Feature');
			$feature->addAttribute('value', 'feature_send_to_vm');
			$diversion = $headers->addChild('header');
			$diversion->addAttribute('key', 'Diversion');
			$diversion->addAttribute('value', '<sip:%_ACCOUNT_USERNAME_%@%_ACCOUNT_SERVER_%:%_ACCOUNT_PORT_%>;reason="send_to_vm"');
		}
		if ($entry['settings']['can_intercom']) {
			$intercom = $actions->addChild('action');
			$intercom->addAttribute('id', 'intercom');
			$intercom->addAttribute('dial', $entry['extension']);
			$intercom->addAttribute('dial_prefix', '');
			$intercom->addAttribute('label', 'CL_ACTN_SIP');
			$intercom->addAttribute('name', 'CONTACT_ACT_INTERCOM');
			$headers = $intercom->addChild('headers');
			$feature = $headers->addChild('header');
			$feature->addAttribute('key', 'X-Digium-Call-Feature');
			$feature->addAttribute('value', 'feature_intercom');
		}
		if ($entry['settings']['can_monitor']) {
			$intercom = $actions->addChild('action');
			$intercom->addAttribute('id', 'monitor');
			$intercom->addAttribute('dial', $entry['extension']);
			$intercom->addAttribute('dial_prefix', '');
			$intercom->addAttribute('label', 'CL_ACTN_SIP');
			$intercom->addAttribute('name', 'CONTACT_ACT_MONITOR');
			$headers = $intercom->addChild('headers');
			$feature = $headers->addChild('header');
			$feature->addAttribute('key', 'X-Digium-Call-Feature');
			$feature->addAttribute('value', 'feature_monitor');
		}
	}

	$dom = new DOMDocument('1.0');
	$dom->preserveWhiteSpace = false;
	$dom->formatOutput = true;
	$dom->loadXML($xml->asXML());

	return $dom->saveXML();
}

/* build a php script that the phone will call to get correct blf for that model */
function digium_phones_http_blf_php($conf, $id, $phonebook)
{
	if ($id == 'internal') {
		$phonebook['entries'] = array();
		foreach ($conf->sorted_users as $user) {
			$phonebook['entries'][] = array('extension' => $user['id']);
		}
	}

	$blfs = array();
	if (!empty($phonebook['entries'])) foreach($phonebook['entries'] as $entryid => $entry) {
		$blfs[] = array('contact_id' => $entry['extension']);
	}

	$path_to_blf_generator = dirname(__FILE__).'/blf_generator.php';

	return '<'."?php\n\$blfs=".var_export($blfs, true).";\nrequire '$path_to_blf_generator';\n";
}

