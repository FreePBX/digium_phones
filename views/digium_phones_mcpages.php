<h2>Multicast Pages</h2>
<hr />

<form name="digium_phones_mcpages" method="post" action="config.php?type=setup&display=digium_phones&digium_phones_form=mcpages_edit">
<script>
$().ready(function() {
<?php
$mcpages = $digium_phones->get_mcpages();

if (isset($_GET['mcpage']) and !isset($_GET['deletemcpage_submit'])) {
	$editmcpage = htmlspecialchars($_GET['mcpage']);
}

if ($editmcpage != null) {
	if ($editmcpage == 0) {
?>
		$('#mcpagename').val("New Multicast Page");
<?php
	} else {
?>
		$('#mcpagename').val($('#mcpage<?php echo $editmcpage?>name').text());
<?php
	}
?>
	$('#mcpage').val(<?php echo $editmcpage?>);

	$('div[id=editingmcpage]').show();
<?php
}

foreach ($mcpages as $mcpageid=>$mcpage) {
	if ($editmcpage == $mcpageid) {
		foreach ($mcpage['settings'] as $key=>$val) {
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
	if ($.trim($('#mcpagename').val()).length <= 0) {
		alert('Name cannot be blank.');
		return false;
	}
	if ($.trim($('#address').val()).length <= 0) {
		alert('Address cannot be blank.');
		return false;
	}
});
</script>
<input type="button" value="Add Multicast Page" onclick="location.href='config.php?type=setup&display=digium_phones&digium_phones_form=mcpages_edit&mcpage=0'" />
<p>

<table style="border-collapse:collapse; border-style:outset; border-width: 1px; ">
<tr>
<th style="border-style:inset; border-width:1px; "><a href="#" class="info">Name<span>A Multicast Page's named identifier.
</span></a></th>

<th style="border-style:inset; border-width:1px; width:75px; "><a href="#" class="info">Address<span>A Multicast Page's address.
</span></a></th>
<th style="border-style:inset; border-width:1px; width:75px; "><a href="#" class="info">Port<span>A Multicast Page's port.
</span></a></th>

<th style="border-style:inset; border-width:1px; "><a href="#" class="info">Actions<span>"Edit" provides additional editing control over a selected Multicast Page. "Delete" removes the specified Multicast Page.</span></a></th>
</tr>
<?php
foreach ($mcpages as $mcpageid=>$mcpage) {
?>
<tr>
<td style="width: 200px; border-style:inset; border-width: 1px; ">
	<span id="mcpage<?php echo $mcpageid?>name"><?php echo $mcpage['name']?></span>
</td>
<td style="border-style:inset; border-width:1px; ">
	<?php echo $mcpage['settings']['address']?>
</td>
<td style="border-style:inset; border-width:1px; ">
	<?php echo $mcpage['settings']['port']?>
</td>
<td style="border-style:inset; border-width:1px; white-space: nowrap; ">
	<input type="button" value="Edit" onClick="parent.location='config.php?type=setup&display=digium_phones&digium_phones_form=mcpages_edit&mcpage=<?php echo $mcpageid?>'">
<?php
	if ($mcpageid != -1) {
?>
	<input type="button" value="Delete" onClick="parent.location='config.php?type=setup&display=digium_phones&digium_phones_form=mcpages_edit&deletemcpage_submit=Delete&mcpage=<?php echo $mcpageid?>'">
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

<div id="editingmcpage" style="display: none;">
	<input type="hidden" id="mcpage" name="mcpage" />
	<?php
	dbug($mcpages[$editmcpage]);
	$table = new CI_Table();
	$table->add_row(fpbx_label('Page Name:', 'Sets an identifier for the Multicast Page.'),
			array( 'data' => '<input type="text" id="mcpagename" name="mcpagename" value="' . ($editmcpage == -1 ? "readonly" : $mcpages[$editmcpage]['name']) . '" />'));
	$table->add_row(fpbx_label('Multicast Address:', 'Defines a Multicast Page Address that the page audio will be sent from.'),
			array( 'data' => '<input type="text" id="address" name="address" value="' . ($editmcpage == -1 ? "readonly" : $mcpages[$editmcpage]['settings']['address']) . '" />'));
	$table->add_row(fpbx_label('Multicast Port:', 'Sets the Port that the page audio will be sent on.'),
			array( 'data' => '<input type="text" id="port" name="port" value="' . ($editmcpage == -1 ? "readonly" : $mcpages[$editmcpage]['settings']['port']) . '" />'));
	$table->add_row(fpbx_label('Priority:', 'Sets the priority of the page (lower number will be played in preference to higher number).'),
			array( 'data' => '<input type="text" id="priority" name="priority" value="' . ($editmcpage == -1 ? "readonly" : $mcpages[$editmcpage]['settings']['priority']) . '" />'));
	$table->add_row(fpbx_label('Interrupt:', 'Allow page to interrupt an active call.'),
			array( 'data' => '<input type="text" id="interrupt" name="interrupt" value="' . ($editmcpage == -1 ? "readonly" : $mcpages[$editmcpage]['settings']['interrupt']) . '" />'));

	echo $table->generate();
	$table->clear();

/*
	echo '<hr>';
	echo '<table style="border-spacing: 4px;"><tbody>';
	echo '<tr class="guielToggle" data-toggle_class="advanced"><td><h5><span class="guielToggleBut">+ </span>Advanced</h5><hr></td><td></td></tr>';

	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">File URL Prefix:<span>Defines the URL prefix used by the phone to retrieve firmware and ringtones.</span></a></td><td>
		<input type="text" id="file_url_prefix" name="file_url_prefix" /></td></tr>';
	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">Alternate Registration Address:<span>Optional.  Sets an alternate host to which the phone will register itself simultaneously.  DPMA Application function is not maintained with the alternate host, but basic call functionality is maintained.</span></a></td><td>
		<input type="text" id="alternate_registration_address" name="alternate_registration_address" /></td></tr>';	
	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">Alternate Registration Port:<span>Optional. Sets the port for the Alternate Registration Address.</span></a></td><td>
		<input type="text" id="alternate_registration_port" name="alternate_registration_port" /></td></tr>';
	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">NTP Server:<span>Defines the NTP server the phone will synchronize to in order to maintain its time.</span></a></td><td>
		<input type="text" id="ntp_server" name="ntp_server" /></td></tr>';

	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">Syslog Level:<span>If enabled, sets a logging level used by the phone to output syslog messages.</span></a></td><td>
		<select id="syslog_level" name="syslog_level">
				<option value="" selected>Disabled (Default)</option>
				<option value="debug">Debug</option>
				<option value="error">Error</option>
				<option value="warn">Warning</option>
				<option value="information">Infomation</option>
			</select></td></tr>';
	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">Syslog Server:<span>If Syslog is enabled, sets the server to which syslog messages are sent by the phone.</span></a></td><td>
		<input type="text" id="syslog_server" name="syslog_server" /></td></tr>';
	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">Syslog Port:<span>If Syslog is enabled, sets the port to which syslog messages are sent by the phone.</span></a></td><td>
		<input type="text" id="syslog_port" name="syslog_port" /></td></tr>';
	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">Multicast Page VLAN Discovery:<span>Digium phones default to VLAN discovery using LLDP.  If LLDP is not available on your switch, you may elect for Manual VLAN configuration, or VLANs may be disabled.</span></a></td><td>
		<select id="mcpage_vlan_discovery_mode" name="mcpage_vlan_discovery_mode">
				<option value="LLDP" selected>LLDP (Default)</option>
				<option value="NONE">None</option>
				<option value="MANUAL">Manual</option>
			</select></td></tr>';
	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">Multicast Page VLAN ID:<span>If a Digium phone is configured for manual VLAN Discovery, sets the VLAN ID to which the phone will bind.</span></a></td><td>
		<input type="text" id="mcpage_vlan_id" name="mcpage_vlan_id" /></td></tr>';
	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">Multicast Page QoS:<span>If a Digium phone is configured for manual VLAN Discovery, sets the QoS bit for the phones traffic to the mcpage.</span></a></td><td>
			<input type="text" id="mcpage_vlan_qos" name="mcpage_vlan_qos" /></td></tr>';
	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">PC VLAN ID:<span>Sets the VLAN ID to which the phone will bind, for the PC port.</span></a></td><td>
		<input type="text" id="pc_vlan_id" name="pc_vlan_id" /></td></tr>';
	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">PC QoS:<span>If a Digium phone is configured for manual VLAN Discovery, sets the QoS bit for traffic from the PC port to the mcpage.</span></a></td><td>
		<input type="text" id="pc_qos" name="pc_qos" /></td></tr>';
	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">Signalling DSCP:<span>Specifies the DSCP field of the DiffServ byte for SIP Signaling QoS, defaults to 24.</span></a></td><td>
		<input type="text" id="sip_dscp" name="sip_dscp" /></td></tr>';
	echo '<tr class="advanced"><td><a href="#" class="info" tabindex="-1">Media DSCP:<span>Specifies the DSCP field of the DiffServ byte for RTP Media QoS, defaults to 24.</span></a></td><td>
		<input type="text" id="rtp_dscp" name="rtp_dscp" /></td></tr>';
	echo '</table>';
*/

	?>

	<input type="button" value="Cancel" onclick="location.href='config.php?type=setup&display=digium_phones&digium_phones_form=mcpages_edit'"/>
	<input type="submit" name="editmcpage_submit" value="Save"/>
</div>
</form>
