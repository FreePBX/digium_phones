<h3><?php echo $addon['name']?></h3>

<form name="eula-form" method="post" action="config.php?display=digium_phones&page=eula-form">
	<input type="hidden" name="add_license_key" value="<?php echo $product_key?>" />
	<?php foreach ($submitted_ufs as $name=>$val): ?>
	<input type="hidden" name="add_license_<?php echo $name?>" value="<?php echo $val?>" />
	<?php endforeach; ?>

	<div class="error_msg"><?php echo $error_msg?></div>
	<textarea cols="100" rows="25" readonly="readonly"><?php echo $eula?></textarea>
	<br />

	<input type="submit" name="eula-submit" value="Accept" />
	<input type="button" id="eula-deny" value="Deny" />
</form>
<script type="text/javascript">
	$('#eula-deny').click(function() {
		window.location = 'config.php?display=digium_phones';
	});
</script>
