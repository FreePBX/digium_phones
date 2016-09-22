<?php
$passthrough_options=array(
	'' => 'Disabled (Default)',
	'1' => 'Enabled'
);


$eapol_options=array(
	'' => 'Disabled (Default)',
	'1' => 'Enabled'
);

$method_options=array(
	'' => 'None (disabled)',
	'eap-md5' => 'EAP-MD5',
	'eap-tls' => 'EAP-TLS',
	'peap-mschap' => 'PEAP-MSCHAP',
	'peap-gtc' => 'PEAP-GTC',
	'ttls-mschap' => 'TTLS-MSCHAP',
	'ttls-gtc' => 'TTLS-GTC'
);

function selector($name, $value, $options) {
	$select = '<select id="'.$name.'" name="'.$name.'">';
	foreach ($options as $optval => $text) {
		$select .= '<option value="'. $optval.'"';
		if ($value == $optval) {
			$select .= ' selected';
		}
		$select .= '>'. htmlentities($text).'</option>';
	}
	$select .= '</select>' . "\n";
	return $select;
}

?>
<h2>802.1X Configuration</h2>
<hr />

<form name="digium_phones_pnacs" method="post" action="config.php?type=setup&display=digium_phones&digium_phones_form=pnacs_edit">
<script>
$().ready(function() {
<?php
$pnacs = $digium_phones->get_pnacs();

if (isset($_GET['pnac']) and !isset($_GET['deletepnac_submit'])) {
	$editpnac = htmlspecialchars($_GET['pnac']);
}

if ($editpnac != null) {
	if ($editpnac == 0) {
?>
		$('#pnacname').val("New 802.1X Configuartion");
<?php
	} else {
?>
		$('#pnacname').val($('#pnac<?php echo $editpnac?>name').text());
<?php
	}
?>
	$('#pnac').val(<?php echo $editpnac?>);

	$('div[id=editingpnac]').show();
<?php
}

foreach ($pnacs as $pnacid=>$pnac) {
	if ($editpnac == $pnacid) {
		foreach ($pnac['settings'] as $key=>$val) {
?>
			if ($('#<?php echo $key?>') != null) {
				$('#<?php echo $key?>').val('<?php echo $val?>');
			}
<?php
		}
	}
}
?>
});
$('form').submit(function() {
	if ($.trim($('#pnacname').val()).length <= 0) {
		alert('Name cannot be blank.');
		return false;
	}
});
</script>
<input type="button" value="Add 802.1X Configuration" onclick="location.href='config.php?type=setup&display=digium_phones&digium_phones_form=pnacs_edit&pnac=0'" />
<p>

<table style="border-collapse:collapse; border-style:outset; border-width: 1px; ">
<tr>
<th style="border-style:inset; border-width:1px; "><a href="#" class="info">Name<span>A name to identify this configuration.  It is not used by the phone.
</span></a></th>

<th style="border-style:inset; border-width:1px; width:75px; "><a href="#" class="info">Passthrough<span>Enable pass through mode.</span></a></th>
<th style="border-style:inset; border-width:1px; width:75px; "><a href="#" class="info">Method<span>The 802.1X authentication method.
</span></a></th>

<th style="border-style:inset; border-width:1px; "><a href="#" class="info">Actions<span>"Edit" provides additional editing control over a selected 802.1X Configuration. "Delete" removes the specified 802.1X Configuration.</span></a></th>
</tr>
<?php
foreach ($pnacs as $pnacid=>$pnac) {
?>
<tr>
<td style="width: 200px; border-style:inset; border-width: 1px; ">
	<span id="pnac<?php echo $pnacid?>name"><?php echo $pnac['name']?></span>
</td>
<td style="border-style:inset; border-width:1px; ">
	<?php echo $pnac['settings']['passthrough'] ? 'Enabled' : 'Disabled' ?>
</td>
<td style="border-style:inset; border-width:1px; ">
	<?php echo $method_options[$pnac['settings']['method']] ?>
</td>
<td style="border-style:inset; border-width:1px; white-space: nowrap; ">
	<input type="button" value="Edit" onClick="parent.location='config.php?type=setup&display=digium_phones&digium_phones_form=pnacs_edit&pnac=<?php echo $pnacid?>'">
<?php
	if ($pnacid != -1) {
?>
	<input type="button" value="Delete" onClick="parent.location='config.php?type=setup&display=digium_phones&digium_phones_form=pnacs_edit&deletepnac_submit=Delete&pnac=<?php echo $pnacid?>'">
<?php
	}
?>
</td>
</tr>
<?php
}
?>
</table>

<hr />

<div id="editingpnac" style="display: none;">
	<input type="hidden" id="pnac" name="pnac" />
	<?php
	dbug($pnacs[$editpnac]);
	$table = new CI_Table();
	$table->add_row(fpbx_label('Name:', 'Sets an identifier for the 802.1X Configuration.  This is not used by the phone.'),
			array( 'data' => '<input type="text" id="pnacname" name="pnacname" value="' . $pnacs[$editpnac]['name'] . '" />'));
	$table->add_row(fpbx_label('Passthrough:', 'Enables 802.1X pass-through.'),
			array( 'data' => selector('passthrough', $pnacs[$editpnac]['passthrough'], $passthrough_options)));
	$table->add_row(fpbx_label('EAPOL on Disconnect:', 'Enables EAPOL on disconnect.'),
			array( 'data' => selector('eapol_on_disconnect', $pnacs[$editpnac]['eapol_on_disconnect'], $eapol_options)));
	$table->add_row(fpbx_label('Method:', 'Select the authentication method.'),
			array( 'data' => selector('method', $pnacs[$editpnac]['method'], $method_options)));
	$table->add_row(fpbx_label('Identity:', 'Sets the username for authentication.'),
			array( 'data' => '<input type="text" id="interrupt" name="identity" value="' . $pnacs[$editpnac]['settings']['identity'] . '" />'));

	$table->add_row(fpbx_label('Anonymous Identity:', 'Sets the anonymous username for authentication.'),
			array( 'data' => '<input type="text" id="interrupt" name="anonymous_identity" value="' . $pnacs[$editpnac]['settings']['anonymous_identity'] . '" />'));
	$table->add_row(fpbx_label('Password:', 'Sets the password for authentication.'),
			array( 'data' => '<input type="text" id="interrupt" name="password" value="' . $pnacs[$editpnac]['settings']['password'] . '" />'));
	$table->add_row(fpbx_label('Client Cert URL:', 'Sets the URL of client certificate for authentication.'),
			array( 'data' => '<input type="text" id="interrupt" name="client_cert_url" value="' . $pnacs[$editpnac]['settings']['client_cert_url'] . '" />'));
	$table->add_row(fpbx_label('Client Cert Value:', 'Sets the value of client certificate for authentication.  This arbitrary value must be changed when content at URL changes to cause the phone to reload the certificate file.'),
			array( 'data' => '<input type="text" id="interrupt" name="client_cert_value" value="' . $pnacs[$editpnac]['settings']['client_cert_value'] . '" />'));
	$table->add_row(fpbx_label('Root Cert URL:', 'Sets the URL of root certificate for authentication.'),
			array( 'data' => '<input type="text" id="interrupt" name="root_cert_url" value="' . $pnacs[$editpnac]['settings']['root_cert_url'] . '" />'));
	$table->add_row(fpbx_label('Root Cert Value:', 'Sets the value of the root certificate for authentication.  This arbitrary value must be changed when content at URL changes to cause the phone to reload the certificate file.'),
			array( 'data' => '<input type="text" id="interrupt" name="root_cert_value" value="' . $pnacs[$editpnac]['settings']['root_cert_value'] . '" />'));

	echo $table->generate();
	$table->clear();

	?>

	<input type="button" value="Cancel" onclick="location.href='config.php?type=setup&display=digium_phones&digium_phones_form=pnacs_edit'"/>
	<input type="submit" name="editpnac_submit" value="Save"/>
</div>
</form>
