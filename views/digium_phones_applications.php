<br />

<?php
	if (!$easymode) {
		if (function_exists('queues_list')) {
?>
			<a href="config.php?type=setup&display=digium_phones&digium_phones_form=application_queues_edit"><input type="button" style="cursor: pointer;" value="Queues"/></a>
<?php
		}
?>
		<a href="config.php?type=setup&display=digium_phones&digium_phones_form=application_status_edit"><input type="button" style="cursor: pointer;" value="Status"/></a>

		<a href="config.php?type=setup&display=digium_phones&digium_phones_form=application_custom_edit"><input type="button" style="cursor: pointer;" value="Custom"/></a>
<?php
	}
?>
