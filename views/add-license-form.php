<h3><?php echo $addon['name']?></h3>
<?php
// Avoid XSS
$addon = htmlentities($_GET['addon'], ENT_QUOTES, "UTF-8");
$key = htmlentities($_POST['add_license_key'], ENT_QUOTES, "UTF-8");
$aln = htmlentities($_POST[$name], ENT_QUOTES, "UTF-8");
?>


<form name="add_license_form" method="post" action="config.php?display=digium_phones&page=add-license-form">
<fieldset>
<div class="error_msg"><?php echo $key_error_msg?></div>
<legend> Key </legend>
<div class="add_license_field">
	<label for="add_license_key_get"></label>
	<input type="button" value="Get Free License" onclick="window.open('https://www.digium.com/products/software/digium-phone-module-for-asterisk');" />
</div>
<div class="add_license_field">
	<label for="add_license_key">Key: </label>
	<input type="text" name="add_license_key" value="<?php echo $key; ?>" />
	*
</div>

</fieldset>
<fieldset>
<div class="error_msg"><?php echo $fields_error_msg?></div>
<legend>User Fields</legend>
<?php foreach ($product['userfields'] as $uf):
	$name = "add_license_".htmlentities($uf['name'], ENT_QUOTES, "UTF-8");
?>
<div class="add_license_field">
	<label for="<?php echo $name; ?>"><?php echo $uf['desc']?></label>
	<input type="text" name="<?php echo $name; ?>" value="<?php echo $aln; ?>" <?php echo ($uf['required'])?'required':'';?> />
	<?php echo (($uf['required'])?"*":"")?>
</div>
<?php endforeach; ?>
</fieldset>
<div class="add_license_field">
<label for="submit"></label>
<input type="submit" name="add_license_submit" value="Submit" />
<input id="add_license_cancel" type="button" value="Cancel" />
</div>
</form>
<script type="text/javascript">
	$('#add_license_cancel').click(function() {
		window.location = "config.php?display=digium_phones";
	});
</script>
