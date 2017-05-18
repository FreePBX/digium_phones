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
 * generate smart blf xml config on-the-fly to match request from phone
 *
 * note: this is included from blf-*.php files written out by digium_phones_http.php
 *       - blfs array must be already defined
 *       - lines in use by phones argument must be present
 *       - user agent must have known model number
 */

global $blfs;

$limits = array(
	'80' => array(
		'lines' => -1,
		'sides' => 0,
		'main_pages' => 0,
		'side_pages' => 0,
	),
	'70' => array(
		'lines' => 6,
		'sides' => 10,
		'main_pages' => 0,
		'side_pages' => 10,
	),
	'65' => array(
		'lines' => 6,
		'sides' => 10,
		'main_pages' => 20,
		'side_pages' => 0,
	),
	'62' => array(
		'lines' => 2,
		'sides' => 0,
		'main_pages' => 0,
		'side_pages' => 0,
	),
	'60' => array(
		'lines' => 2,
		'sides' => 0,
		'main_pages' => 0,
		'side_pages' => 0,
	),
	'50' => array(
		'lines' => 4,
		'sides' => 10,
		'main_pages' => 0,
		'side_pages' => 1,
	),
	'45' => array(
		'lines' => 2,
		'sides' => 0,
		'main_pages' => 0,
		'side_pages' => 0,
	),
	'40' => array(
		'lines' => 2,
		'sides' => 0,
		'main_pages' => 0,
		'side_pages' => 0,
	),
);

if (empty($blfs) || empty($_GET['lines']) || empty($_SERVER['HTTP_USER_AGENT'])) {
	header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
	echo '<h1>required parameter missing</h1>';
	return;
}

if (!empty($_GET['model'])) {
	// for testing
	$model = $_GET['model'];
} else {
	$model = 'unknown';
}

if (preg_match('/.*Digium.*[Dd](\d+)/', $_SERVER['HTTP_USER_AGENT'], $matches)) {
	if (!empty($matches[1])) {
		$model = $matches[1];
	}
}

if (empty($limits[$model])) {
	header($_SERVER['SERVER_PROTOCOL'].' 404 Not Found');
	echo '<h1>model "'.$model.'" not recognized</h1>';
	return;
}

$model_limits = $limits[$model];
$lines_in_use = $_GET['lines'];

$xml = new SimpleXmlElement('<config/>');
$smart_blf = $xml->addChild('smart_blf');
$blf_items = $smart_blf->addChild('blf_items');

$index = $lines_in_use;
$page = 0;
$location = 'main';
foreach($blfs as $blf) {
	if ($location == 'main') {
		if ($index >= $model_limits['lines'] && $model_limits['lines'] != -1) {
			if ($page < $model_limits['main_pages']) {
				++$page;
				$index = $lines_in_use;
			} else {
				$location = 'side';
				$index = 0;
				$page = 0;
			}
		}
	} /* not else if-ing on purpose */
	if ($location == 'side') {
		if ($index >= $model_limits['sides']) {
			++$page;
			$index = 0;
		}
		if ($page >= $model_limits['side_pages']) {
			break;
		}
	}

	$blf_item = $blf_items->addChild('blf_item');

	$blf_item->addAttribute('location', $location);
	$blf_item->addAttribute('index', $index++);
	$blf_item->addAttribute('contact_id', $blf['contact_id']);
}

$dom = new DOMDocument('1.0');
$dom->preserveWhiteSpace = false;
$dom->formatOutput = true;
$dom->loadXML($xml->asXML());

header('Content-type: text/xml');
echo $dom->saveXML();

// debugging
//file_put_contents('/tmp/phone-'.$_SERVER['REMOTE_ADDR'], print_r(array_merge(array('model'=>$model), $model_limits), true).$dom->saveXML());
