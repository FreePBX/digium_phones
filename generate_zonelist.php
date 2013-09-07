#!/usr/bin/php
<?php
if (!method_exists('DateTimeZone', 'listIdentifiers')) {
	print "DateTimeZone::listIdentifiers is not supported on this version of PHP.\n";
	print "Please run this on 5.2 or higher.\n";
	exit;
}

$zonelist = "<option value=\"\"></option>\n";
foreach (DateTimeZone::listIdentifiers() as $tz) {
	$zonelist .= "<option value=\"".$tz."\">".$tz."</option>\n";
}
file_put_contents("zonelist.html", $zonelist);
?>
