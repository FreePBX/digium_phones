<?php

if (!extension_loaded('digium_register')) {
	echo '<h3>Error: the register utility did not successfully load.</h3>';
	echo '<p>DPMA license can be added using command-line register utility instead.</p>';
	return;
}

require_once(__DIR__.'/register_functions.php');

require_once(__DIR__.'/digium_register.php');

function escapeSimple($value)
{
	return $value;
}


if (false) //!extension_loaded('digium_register'))
{

	echo '<h1 style="color:red">This Module Requires The Digium RPM to be installed (php-digium_register-3.0.5-1_centos6.i686.rpm)</h1><br/>Please see this page for more information: <a target="_blank" href="http://wiki.freepbx.org/display/F2/Digium+Addons">http://wiki.freepbx.org/display/F2/Digium+Addons</a>';
	return;
}

	$page = (isset($_GET['page'])) ? $_GET['page'] : 'default';
	$digium_addons = new digium_license();
	$error_msg = '';

	if (isset($_POST['add_license_submit']) && $_POST['add_license_submit']) {
		$page = 'eula-form';

		$addon = $digium_addons->get_addon_by_name('dpma');
		$digium_addons->register_load_product($addon['product_index']);
		$product = $digium_addons->register_get_product();
		$prefix = $digium_addons->register_get_key_prefix();

		$product_key = escapeSimple($_POST['add_license_key']);
		if ( !$product_key || (strpos($product_key, $prefix) !== 0)) {
			$key_error_msg = "Invalid key.";
			$page = "add-license-form";
		}

		$submitted_ufs = array();
		foreach ($product['userfields'] as $uf) {
			if ($_POST['add_license_'.$uf['name']] == '' && $uf['required']) {
				$fields_error_msg = "Please enter values into the required fields.";
				$page = 'add-license-form';
			}

			$submitted_ufs[$uf['name']] = escapeSimple($_POST['add_license_'.$uf['name']]);
		}
	} else if (isset($_POST['eula-submit']) && $_POST['eula-submit']) {
		$page = 'completed';

		$addon = $digium_addons->get_addon_by_name('dpma');
		$digium_addons->register_load_product($addon['product_index']);
		$product = $digium_addons->register_get_product();

		$product_key = escapeSimple($_POST['add_license_key']);
		if ( !$product_key || substr($product_key, 0, 4) != "DPMA" ) {
			$key_error_msg = "Invalid key.";
			$page = "add-license-form";
		}

		$submitted_ufs = array();
		foreach ($product['userfields'] as $uf) {
			if (isset($_POST['add_license_'.$uf['name']]) && $_POST['add_license_'.$uf['name']] == '' && $uf['required']) {
				$page = 'add-license-form';
			}

			if (isset($_POST['add_license_'.$uf['name']])) {
				$submitted_ufs[$uf['name']] = escapeSimple($_POST['add_license_'.$uf['name']]);
			} else {
				$submitted_ufs[$uf['name']] = null;
			}
		}

		$register_result = $digium_addons->register($addon['product_index'], $submitted_ufs, $product_key);
		if ($register_result == false && $digium_addons->register_get_error() == 'bad-key') {
			$key_error_msg = "This is an invalid key.";
			$page = 'add-license-form';
		} else if ($register_result == false) {
			$error_msg = "There was an error attempting to register this product.";
			$page = 'eula-form';
		}
	}

	?>
	<style type="text/css">
		.add_license_field { padding-bottom: 5px; }
		.error_msg { color: red }
		label { display: block; float: left; padding-right: 5px; text-align: right; width: 300px;}
	</style>
	<?php
	switch ($page) {
	default:
	case 'default':
	case 'add-license-form':
			$addon = $digium_addons->get_addon_by_name('dpma');
			$digium_addons->register_load_product($addon['product_index']);
			$product = $digium_addons->register_get_product();
			include(__DIR__.'/views/add-license-form.php');
			break;
	case 'eula-form';
			$addon = $digium_addons->get_addon_by_name('dpma');
			$digium_addons->register_load_product($addon['product_index']);
			$product = $digium_addons->register_get_product();
			$eula = $digium_addons->register_get_eula($product_key);
			include(__DIR__.'/views/eula-form.php');
			break;
	case 'completed':
			echo '<h2>License Registered</h2>';
			break;
	}
?>
