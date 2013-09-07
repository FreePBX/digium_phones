<h2>Logos</h2>
<hr />

<script type="text/javascript">
<?php
// we need our logo stash
$logos = $digium_phones->get_logos();

$js_logos= json_encode($logos);
echo "var logos = ". $js_logos. ";\n";
?>

function add_logo_clicked()
{
	$('#diveditlogo').slideUp('fast');
	$('#divaddlogo').slideToggle('fast');
}
function edit_logo_clicked(id)
{
	$('#divaddlogo').slideUp('fast');
	if ('undefined' == typeof id) { // cancel button
		$('#diveditlogo').slideUp('fast');
	} else {
		$('#diveditlogo').slideDown('fast');
	}

	for (var i=0; i < logos.length; i++) {
		if (id == logos[i]['id']) {
            $('#edit_logo_name').val(logos[i]['name']);
            $('#edit_logo_model').val(logos[i]['model']);
            $('#edit_logo_id').val(logos[i]['id']);
		}

	}
}
</script>

<?php
// deal with uploaded images
if (isset($_GET['logo_upload']) && isset($_FILES['logo_upload']) && $_FILES['logo_upload']['size'] > 0) {
	$file = $_FILES['logo_upload'];
	$filename = basename($_GET['logo_upload']);

	// we need to get the id for our new logo
	foreach ($logos as $logo) {
		// original upload
		if ($_POST['logo_name'] == $logo['name']) {
			$filename = $logo['id'];
		}
		// edit upload
		if ($_POST['edit_logo_name'] == $logo['name']) {
			$filename = $logo['id'];
		}
	}

	if (!preg_match('/\.png$/', $file['name'])) {
?>
		<span style="color: red; ">Uploaded files must be in png format.</span>
		<br />
<?php
	} else {
		if (!move_uploaded_file($file['tmp_name'], $amp_conf['ASTETCDIR']."/digium_phones/user_image_".$filename.".png")) {
?>
			<span style="color: red; ">Uploaded file is not valid.</span>
			<br />
<?php
		} else {
			needreload();
?>
			<span style="color: green; ">File uploaded successfully.</span>
			<br />
			<br />
<?php
		}
	}
}
?>

<table style="border-collapse:collapse; border-style:outset; border-width: 1px; margin-bottom: 20px;" cellpadding="5" cellspacing="0">
<tr>
<th style="border-style:inset; border-width:1px; "><a href="#" class="info">Logo Name<span>A Logo's named identifier.</span></a></th>
<th style="border-style:inset; border-width:1px; "><a href="#" class="info">Phone Model<span>The Digium phone model to which this logo may apply</span></a></th>
<th style="border-style:inset; border-width:1px; width: 210px; "><a href="#" class="info">Image<span>A preview of the logo image</span></a></th>
<th style="border-style:inset; border-width:1px; "><a href="#" class="info">Actions<span>"Edit" provides additional editing control over a selected logo. "Delete" removes the specified logo.</span></a></th>
</tr>
<?php
if (empty($logos)) {
?>
	<tr><td colspan="4">No custom logos configured.</td></tr>
<?php
}
foreach ($logos as $logo) {
?>
<tr>
	<td><?php echo $logo['name']?></td>
	<td><?php echo strtoupper($logo['model'])?></td>
<?php
	if (file_exists("{$amp_conf['ASTETCDIR']}/digium_phones/user_image_{$logo['id']}.png")) {
?>
		<td><img src="config.php?type=setup&display=digium_phones&user_image=<?php echo $logo['id']?>&quietmode=1" /></td>
<?php
	} else {
?>
		<td>not available</td>
<?php
	}
?>
	<td>
		<button type="button" onclick="edit_logo_clicked(<?php echo $logo['id']?>);">Edit</button>
		<form name="digium_phones_logos" method="post" action="config.php?type=setup&display=digium_phones&digium_phones_form=logos_edit&deletepng=<?php echo $logo['id']?>"><input type="submit" value="Delete"></form>
	</td>
</tr>
<?php
}
?>
</table>
<input type="submit" name="add_logo_submit" value="Add Logo" onclick="add_logo_clicked();"/>

<div id="divaddlogo" style="display: none;">
<hr style="margin-top: 30px;"/>
<h2>Add New Logo</h2>
<form name="digium_phones_logos" method="post" enctype="multipart/form-data" action="config.php?type=setup&display=digium_phones&digium_phones_form=logos_edit&logo_upload=yes">
<table cellpadding="5">
<tr>
	<td><a href="#" class="info">Logo Name<span>A named identifier for the Logo.</span></a></td>
	<td><input type="text" id="logo_name" name="logo_name" /></td>
</tr>
<tr>
	<td><a href="#" class="info">Phone Model<span>Select the Digium phone model which can use this logo.  Logo files should be PNG format, 8-bit, no transparency, and less than 10k in size.  For D40 and D50: 150x45.  For D70: 205x85.</span></a></td>
	<td>
		<select id="logo_model" name="logo_model" />
			<option value="d40">D40</option>
			<option value="d50">D50</option>
			<option value="d70">D70</option>
		</select>
	</td>
</tr>
<tr>
	<td colspan="2">
	<input type="file" name="logo_upload" />
	</td>
</tr>
<tr>
	<td colspan="2">

	<input type="submit" name="logo_upload_submit" value="Upload"/>
	</form>
	<button type="button" onclick="add_logo_clicked();">Cancel</button>
	</td>
</tr>
</table>
</div>


<div id="diveditlogo" style="display: none;">
<hr style="margin-top: 30px;"/>
<h2>Edit Logo</h2>
<form name="digium_phones_edit_logos" method="post" enctype="multipart/form-data" action="config.php?type=setup&display=digium_phones&digium_phones_form=logos_edit&logo_upload=yes">
<table cellpadding="5">
<tr>
	<td><a href="#" class="info">Logo Name<span>Named identifier for the Logo.</span></a></td>
	<td><input type="text" id="edit_logo_name" name="edit_logo_name" /></td>
</tr>
<tr>
	<td><a href="#" class="info">Phone Model<span>Select the Digium phone model which can use this logo.  Logo files should be PNG format, 8-bit, no transparency, and less than 10k in file size.  Dimensions for D40 and D50 logos: 150x45 pixels.  Dimensions for D70 logos: 205x85 pixels.</span></a></td>
	<td>
		<select id="edit_logo_model" name="edit_logo_model" />
			<option value="d40">D40</option>
			<option value="d50">D50</option>
			<option value="d70">D70</option>
		</select>
	</td>
</tr>
<tr>
	<td colspan="2">
	<input type="hidden" id="edit_logo_id" name="edit_logo_id"/>
	<input type="file" id="logo_upload" name="logo_upload" value="Upload"/>
	</td>
</tr>
<tr>
	<td colspan="2">
	<input type="submit" name="edit_logo_upload_submit" value="Save"/>
	</form>
	<button type="button" onclick="edit_logo_clicked();">Cancel</button>
	</td>
</tr>
</table>
</div>
