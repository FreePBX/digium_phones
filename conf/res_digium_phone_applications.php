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
 * generate res_digium_phone_applications.conf file
 */
function res_digium_phone_applications($conf) {

	global $amp_conf;

	$output = array();
	$locales = array();

	foreach ($conf->digium_phones->get_devices() as $deviceid=>$device) {
		if (isset($device['settings']['active_locale']) === FALSE) {
			continue;
		}
		$locale = $device['settings']['active_locale'];
		$table = $conf->digium_phones->get_voicemail_translations($locale);
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

	foreach ($conf->digium_phones->get_queues() as $queueid=>$queue) {
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

	foreach ($conf->digium_phones->get_statuses() as $statusid=>$status) {
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
	foreach ($conf->digium_phones->get_customapps() as $customappid=>$customapp) {
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
}

