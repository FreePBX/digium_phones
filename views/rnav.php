<?php
$easymode = ($digium_phones->get_general('easy_mode') == "yes"?true:false);
$show['Phones']		= '<li><a ' 
				. ($_REQUEST['digium_phones_form'] == 'phones_edit' ? 'class="current ui-state-highlight" ' : '') 
				. 'href="config.php?type=setup&display=digium_phones&digium_phones_form=phones_edit">' . _("Phones") . '</a></li>'."\n";

if (!$easymode) {
	$show['Phonebooks']		= '<li><a ' 
				. ($_REQUEST['digium_phones_form'] == 'phonebooks_edit' ? 'class="current ui-state-highlight" ' : '') 
				. 'href="config.php?type=setup&display=digium_phones&digium_phones_form=phonebooks_edit">' . _("Phonebooks") . '</a></li>'."\n";

$show['Alerts']		= '<li><a ' 
				. ($_REQUEST['digium_phones_form'] == 'alerts_edit' ? 'class="current ui-state-highlight" ' : '') 
				. 'href="config.php?type=setup&display=digium_phones&digium_phones_form=alerts_edit">' . _("Alerts") . '</a></li>'."\n";

$show['Ringtones']		= '<li><a ' 
				. ($_REQUEST['digium_phones_form'] == 'ringtones_edit' ? 'class="current ui-state-highlight" ' : '') 
				. 'href="config.php?type=setup&display=digium_phones&digium_phones_form=ringtones_edit">' . _("Ringtones") . '</a></li>'."\n";

$show['Phone Applications']		= '<li><a ' 
				. ($_REQUEST['digium_phones_form'] == 'applications_edit' ? 'class="current ui-state-highlight" ' : '') 
				. 'href="config.php?type=setup&display=digium_phones&digium_phones_form=applications_edit">' . _("Phone Applications") . '</a></li>'."\n";

$show['Logos']		= '<li><a ' 
				. ($_REQUEST['digium_phones_form'] == 'logos_edit' ? 'class="current ui-state-highlight" ' : '') 
				. 'href="config.php?type=setup&display=digium_phones&digium_phones_form=logos_edit">' . _("Logos") . '</a></li>'."\n";
$show['Multicast Page']		= '<li><a ' 
				. ($_REQUEST['digium_phones_form'] == 'mcpages_edit' ? 'class="current ui-state-highlight" ' : '') 
				. 'href="config.php?type=setup&display=digium_phones&digium_phones_form=mcpages_edit">' . _("Multicast Page") . '</a></li>'."\n";
$show['802.1X']		= '<li><a ' 
				. ($_REQUEST['digium_phones_form'] == 'pnacs_edit' ? 'class="current ui-state-highlight" ' : '') 
				. 'href="config.php?type=setup&display=digium_phones&digium_phones_form=pnacs_edit">' . _("802.1X") . '</a></li>'."\n";
}
$show['Networks']		= '<li><a ' 
				. ($_REQUEST['digium_phones_form'] == 'networks_edit' ? 'class="current ui-state-highlight" ' : '') 
				. 'href="config.php?type=setup&display=digium_phones&digium_phones_form=networks_edit">' . _("Networks") . '</a></li>'."\n";
	
$show['External Lines']		= '<li><a ' 
				. ($_REQUEST['digium_phones_form'] == 'externallines_edit' ? 'class="current ui-state-highlight" ' : '') 
				. 'href="config.php?type=setup&display=digium_phones&digium_phones_form=externallines_edit">' . _("External Lines") . '</a></li>'."\n";

$show['General Settings']		= '<li><a ' 
				. ($_REQUEST['digium_phones_form'] == 'general_edit' ? 'class="current ui-state-highlight" ' : '') 
				. 'href="config.php?type=setup&display=digium_phones&digium_phones_form=general_edit">' . _("General Settings") . '</a></li>'."\n";

$show['Firmware']		= '<li><a ' 
				. ($_REQUEST['digium_phones_form'] == 'firmware_edit' ? 'class="current ui-state-highlight" ' : '') 
				. 'href="config.php?type=setup&display=digium_phones&digium_phones_form=firmware_edit">' . _("Firmware") . '</a></li>'."\n";


//show the page
echo '<div class="rnav"><ul>';
foreach ($show as $s) {
	echo $s;
}
echo '</ul><div style="width:251px; padding-top:220px;" >';
?>
<!--/* OpenX Javascript Tag v2.8.11 */-->

<!--/*
  * The backup image section of this tag has been generated for use on a
  * non-SSL page. If this tag is to be placed on an SSL page, change the
  *   'http://ads.schmoozecom.net/www/delivery/...'
  * to
  *   'https://ads.schmoozecom.net/www/delivery/...'
  *
  * This noscript section of this tag only shows image banners. There
  * is no width or height in these banners, so if you want these tags to
  * allocate space for the ad before it shows, you will need to add this
  * information to the <img> tag.
  *
  * If you do not want to deal with the intricities of the noscript
  * section, delete the tag (from <noscript>... to </noscript>). On
  * average, the noscript tag is called from less than 1% of internet
  * users.
  */-->

<script type='text/javascript'><!--//<![CDATA[
   var m3_u = (location.protocol=='https:'?'https://ads.schmoozecom.net/www/delivery/ajs.php':'http://ads.schmoozecom.net/www/delivery/ajs.php');
   var m3_r = Math.floor(Math.random()*99999999999);
   if (!document.MAX_used) document.MAX_used = ',';
   document.write ("<scr"+"ipt type='text/javascript' src='"+m3_u);
   document.write ("?zoneid=112");
   document.write ('&amp;cb=' + m3_r);
   if (document.MAX_used != ',') document.write ("&amp;exclude=" + document.MAX_used);
   document.write (document.charset ? '&amp;charset='+document.charset : (document.characterSet ? '&amp;charset='+document.characterSet : ''));
   document.write ("&amp;loc=" + escape(window.location));
   if (document.referrer) document.write ("&amp;referer=" + escape(document.referrer));
   if (document.context) document.write ("&context=" + escape(document.context));
   if (document.mmm_fo) document.write ("&amp;mmm_fo=1");
   document.write ("'><\/scr"+"ipt>");
//]]>--></script><noscript><a href='http://ads.schmoozecom.net/www/delivery/ck.php?n=af78148f&amp;cb=INSERT_RANDOM_NUMBER_HERE' target='_blank'><img src='http://ads.schmoozecom.net/www/delivery/avw.php?zoneid=112&amp;cb=INSERT_RANDOM_NUMBER_HERE&amp;n=af78148f' alt='' /></a></noscript>

</div></div>
