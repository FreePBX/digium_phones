<?php

/**
 * \file
 * FreePBX Digium Phones Config Module
 *
 * Copyright (c) 2016, Digium, Inc.
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
 * Phone model definitions
 */
class digium_phones_models {

	private $models;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->models=array(
			'd40' => array('name' => 'D40', 'logo' => '150x45', 'color' => 0),
			'd45' => array('name' => 'D45', 'logo' => '150x45', 'color' => 0),
			'd50' => array('name' => 'D50', 'logo' => '150x45', 'color' => 0),
			'd60' => array('name' => 'D60', 'logo' => '280x128', 'color' => 1),
			'd62' => array('name' => 'D62', 'logo' => '280x128', 'color' => 1),
			'd65' => array('name' => 'D65', 'logo' => '280x128', 'color' => 1),
			'd70' => array('name' => 'D70', 'logo' => '205x85', 'color' => 0),
		);

	}

	public function get_models() {
		return array_keys($this->models);
	}

	public function get_name($model) {
		if (array_key_exists($model, $this->models)) {
			return $this->models[$model]['name'];
		}
		return NULL;
	}

	public function get_logo_size($model) {
		if (array_key_exists($model, $this->models)) {
			return $this->models[$model]['logo'];
		}
		return NULL;
	}

	public function get_logo_color($model) {
		if (array_key_exists($model, $this->models)) {
			return $this->models[$model]['color'];
		}
		return NULL;
	}

}

