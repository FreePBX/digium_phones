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

	?>

	<input type="button" value="Cancel" onclick="location.href='config.php?type=setup&display=digium_phones&digium_phones_form=mcpages_edit'"/>
	<input type="submit" name="editmcpage_submit" value="Save"/>
</div>
</form>
