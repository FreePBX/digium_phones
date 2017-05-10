<?php
//	License for all code of this FreePBX module can be found in the license file inside the module directory
//	Copyright 2013 Schmooze Com Inc.
//  Copyright (c) 2009, Digium, Inc.
//


/*
if(!extension_loaded('digium_register')) {
	return;
}
*/


	/**
	 * Digium Addons
	 *
	 * This class is used to manage addon information, (un)install addons, and register
	 * addons.
	 */
	class digium_license {
		private $addons = array();		// The main addons array
		private $ast_version = '';		// The version of Asterisk
		private $bit = '';			// The server's bit
		private $downloads_addons_url = 'http://downloads.digium.com/pub/telephony/addons.json';
		private $hasinited = false;		// Has the module been initialized
		private $module_version = '0.1';	// Version of the Digium Addons Module
		private $register = null;		// The license_register object

		/**
		 * Constructor
		 *
		 * Load all needed information to use this class
		 */
		public function digium_license() {
			$this->register = new license_register();
			$this->get_ast_version();
			$this->load_addons();
			$this->check_for_updates();
		}

		/**
		 * Addon Exists
		 *
		 * Determine if the addon is already in the database
		 */
		public function addon_exists($name) {
			if ( ! isset($this->addons[$name])) {
				return false;
			} else {
				return true;
			}
		}

		/**
		 * Check For Updates
		 *
		 * Pull the latest from the Digium server and check for any addon updates
		 */
		public function check_for_updates($addon=null) {

			$tobechecked = $this->pull_addons_list();

			foreach ($tobechecked as $name=>$add) {
				if ($name != 'dpma') continue;

				//pull info from downloads server
				$addon = $this->pull_addon($add);
				$this->addons[$name] = $addon;
			}
		}

		/**
		 * Get Asterisk Version
		 *
		 * Get the version of Asterisk
		 */
		public function get_ast_version() {
			$full = `asterisk -V`;
			if (preg_match("/1\.[2468](\.[0-9][0-9]?(\.[0-9][0-9]?)?)?/", $full, $matches)) {
				$this->ast_version = $matches[0];
			} else if (strpos('branch', $full)) {
				$this->ast_version = "team-branch";	// most likely at least.
			} else {
				$this->ast_version = '';
			}
			return $this->ast_version;	// something like "1.6.1.5"
		}

		public function get_addon_by_name($name) {
			if ( ! isset($this->addons[$name])) {
				return false;
			}

			return $this->addons[$name];
		}

		/**
		 * Get Addons
		 *
		 * Get an array of the available addons
		 */
		public function get_addons() {
			return $this->addons;
		}

		/**
		 * Load Addons
		 *
		 * Get addon information from the database
		 */
		public function load_addons() {

			unset($this->addons);
			$this->addons = array();

		}

		/**
		 * Pull Addon
		 *
		 * Pull addon info from the Digium downloads server
		 */
		public function pull_addon($url) {
			$request = file_get_contents($url);
			$request = str_replace(array("\n", "\t"), "", $request);
			return json_decode($request, true);
		}

		/**
		 * Pull Addons List
		 *
		 * Pull the list of available addons from the Digium downloads server
		 */
		public function pull_addons_list() {
			$request = file_get_contents($this->downloads_addons_url);
			$request = str_replace(array("\n", "\t"), "", $request);
			return json_decode($request, true);
		}

		public function register($id, $ufs, $key) {

			global $astman;

			$retval = $this->register_register($ufs, $key, $id);
			if (!$retval) {
				return $retval;
			}

			// tell asterisk to reload dpma so it can check the new license
			$response = $astman->send_request('Command',array('Command'=>"module reload res_digium_phone.so"));
			sleep(3);
			return true;
		}

		public function register_check_key($key) {
			return $this->register->check_key($key);
		}

		public function register_get_eula($key) {
			return $this->register->get_eula($key);
		}

		public function register_get_error() {
			return $this->register->get_error();
		}

		public function register_get_key_prefix() {
			return $this->register->get_key_prefix();
		}

		public function register_get_product() {
			return $this->register->get_product();
		}

		public function register_load_product($index) {
			return $this->register->load_product($index);
		}

		private function register_register($userfields, $key, $id) {
			return $this->register->register($userfields, $key, $id);
		}
	}

	class license_register {
		private $category = 0;			// The register category default is "Digium Products"
		private $cat_res = null;		// Category resource
		private $error = '';			// Error when registering
		private $key_prefix = null;		// The Prefix required for the license key
		private $license_res = null;		// An array of license resources
		private $licenses = array();		// An array of licenses' info
		private $product = array();		// Assoc array to store product info
		private $product_index = null;		// Product index must be selected by user
		private $product_key = null;		// The Product Key
		private $product_res = null;		// Product resource
		private $status = array();		// Status
		private $status_res = null;		// Status resource
		private $userfield_list_res = null;	// Userfield List resource

		public function __construct() {
			$r = dreg_get_product_categories();
			$this->cat_res = dreg_find_category_by_index($r, $this->category);
		}

		public function check_key($key=null) {
			if (!isset($key) && !isset($this->product_key)) {
				die_freepbx('Key is cannot be null when checking');
				return false;
			} else if ( ! isset($this->product)) {
				die_freepbx('Please load a product before attempting to check a key');
			}

			$this->product_key = $key;

			$this->status_res = new_status();
			status_check_key($this->status_res, $this->product['id'] ,$key);

			$status['code'] = status_code_get($this->status_res);
			$status['message'] = status_message_get($this->status_res);
		}

		public function get_eula($key=null) {
			if (!isset($key) && !isset($this->product_key)) {
				die_freepbx('Key is cannot be null when obtaining a eula');
				return false;
			} else if ( ! isset($this->product)) {
				die_freepbx('Please load a product before attempting to get a eula');
			} else if (isset($this->product['eula'])) {
				return $this->product['eula'];
			}

			$this->product_key = (isset($key)) ? $key : $this->product_key;

			$this->product['eula'] = dreg_get_eula($this->product_res, $this->product_key, "en");
			return $this->product['eula'];
		}

		public function get_error() {
			return $this->error;
		}

		public function get_key_prefix() {
			$this->key_prefix = dreg_product_key_prefix_get($this->product_res);
			return $this->key_prefix;
		}

		public function get_product() {
			if ( ! isset($this->product)) {
				die_freepbx('Please load the product before attempting to get it');
				return false;
			}

			return $this->product;
		}

		public function load_product($index) {
			if ( ! is_numeric($index)) {
echo "index='$index'\n";
				die_freepbx('Index not numeric when loading Digium Product');
				return false;
			}

			unset($this->product);
			unset($this->product_index);
			unset($this->product_key);
			unset($this->product_res);

			$this->product_index = $index;

			$pl_res = dreg_get_products($this->cat_res);
			$this->product_res = dreg_find_product_by_index($pl_res, $this->product_index);
			$this->product['id'] = dreg_product_id_get($this->product_res);
			$this->product['name'] = dreg_product_name_get($this->product_res);

			$this->product['userfields'] = array();
			$this->userfield_list_res = dreg_get_product_reg_requirements($this->product_res, "en");
			for (
			     $uf_res = dreg_userfield_list_first_get($this->userfield_list_res);
			     $uf_res;
			     $uf_res = dreg_userfield_entry_next_get(dreg_userfield_entry_get($uf_res))
			) {
				$uf = array();
				$uf['name'] = dreg_userfield_field_name_get($uf_res);
				$uf['desc'] = dreg_userfield_desc_get($uf_res);
				$uf['required'] = dreg_userfield_required_get($uf_res);

				$this->product['userfields'][] = $uf;
			}
		}

		public function register($ufs, $key=null, $addon) {

			if ($this->product_res == null && $this->product_index != null) {
				$this->load_product($this->product_index);
			} else if ($key == null && $this->product_key == null) {
				die_freepbx('Please provide a key before attempting to register.');
				return false;
			} if ($key != null) {
				$this->product_key = $key;
			}

			if ($this->product_res == null) {
				die_freepbx('Please provide a product before attempting to register.');
				return false;
			}

			$this->userfield_list_res = dreg_get_product_reg_requirements($this->product_res, "en");
			for (
			     $uf_res = dreg_userfield_list_first_get($this->userfield_list_res);
			     $uf_res;
			     $uf_res = dreg_userfield_entry_next_get(dreg_userfield_entry_get($uf_res))
			) {
				$name = dreg_userfield_field_name_get($uf_res);

				dreg_userfield_data_set($uf_res, $ufs[$name]);
			}

			$userfield_obj = new license_userfield_list($this->userfield_list_res);

			$license_list_res = dreg_register_product($this->product_res, $this->userfield_list_res, dreg_get_hostid(), $this->product_key, "linux", 0);
			if (!isset($license_list_res) || $license_list_res  == '') {
				$this->error = 'bad-key';
				return false;
			}

			for (
			     $license_res = dreg_license_list_first_get($license_list_res);
			     $license_res;
			     $license_res = dreg_license_entry_next_get(dreg_license_entry_get($license_res))
			) {
				$this->licenses[$i] = array();
				$this->licenses[$i]['path'] = dreg_license_path_get($license_res);
				$this->licenses[$i]['filename'] = dreg_license_filename_get($license_res);
				$this->licenses[$i]['data'] = dreg_license_data_get($license_res);
				$this->licenses[$i]['status'] = dreg_license_status_get($license_res);

				$status_code = trim(status_code_get($this->licenses[$i]['status']));
				if ($status_code != '200' && $status_code != '210') {
					die_freepbx('Product Registration Error: '.trim(status_code_get($this->licenses[$i]['status'])));
					return false;
				}

				$fh = fopen($this->licenses[$i]['path'] . '/' . $this->licenses[$i]['filename'], 'w');
				if ( ! $fh) {
					die_freepbx('Failed to open file for license. Do you have the right permissions?');
					return false;
				}

				fwrite($fh, $this->licenses[$i]['data']);
				fclose($fh);
				unset($fh);

			}

			return true;
		}
	}

	class license_userfield_list {
		public $ptr = null;

		public function __construct($p) {
			$this->ptr = $p;
		}

		public function __get($var) {
			if ($var == 'first') return dreg_userfield_list_first_get($this->_cPtr);
			if ($var == 'last_elm') return dreg_userfield_list_last_elm_get($this->_cPtr);
			return null;
		}

		public function num_userfields() {
			return dreg_userfield_list_num_userfields($this->_cPtr);
		}

		public function get_userfield($index) {
			$r=dreg_userfield_list_userfield_get($this->_cPtr,$index);
			return is_resource($r) ? new dreg_userfield($r) : $r;
		}

	}

//echo "################################ END functions\n";

