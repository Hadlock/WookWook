<?php

/*
------------------------------------------------------------

originally this did a whole lot more. now it just manually 
restarts the server every morning when called by a cronjob. 
however, dice limited their VIP list (reserved slots.txt)
to 499. when we hit that hard limit, we had to do a bunch
of other stuff. so a good portion of this script might 
actually be vegistal code. maybe you'll find some use
for it. gameserverhost.com links should be changed to your
own host.

good luck performing your own php-curl voodoo magic

process_queue.php - Automatically log in to the
	control panel, upload the new VIP list, and
	restart the server.
	
	Not sure what __EVENTVALIDATION, ___VIEWSTATE,
	etc. are but they seem to be constants. They
	are required!
		
------------------------------------------------------------
*/

require_once("include/funcs.php");

define("WEBPANEL_USERNAME", "username2");
define("WEBPANEL_PASSWORD", "password2");

$temp = merge_lists();
$new_list = "";

if ($temp == false)
{
	die("aborted: problem with merge_lists()!");
}
else
{
	foreach ($temp as $n)
	{
		$new_list .= $n . "\n";
	}
}

/*
------------------------------------------------------------
						Step 0: Login
------------------------------------------------------------
*/

$urls[0] = "http://gameserverhost.com/login.aspx";

$fields[0]['ButtonLogin'] = "Login";
$fields[0]['CheckBoxRememberMe'] = "on";
$fields[0]['Password'] = WEBPANEL_USERNAME;
$fields[0]['UserName'] = WEBPANEL_PASSWORD;
$fields[0]['__EVENTARGUMENT'] = null;
$fields[0]['__EVENTTARGET'] = null;
$fields[0]['__EVENTVALIDATION'] = urlencode("/asdf1324");
$fields[0]['__LASTFOCUS'] = null;
$fields[0]['__VIEWSTATE'] = null;
$fields[0]['___VIEWSTATE'] = urlencode("asdf1324=");
$fields[0]['scrollLeft'] = 0;
$fields[0]['scrollTop'] = 0;

/*
------------------------------------------------------------
					Step 1: Update VIP List
------------------------------------------------------------
*/

$urls[1] = "http://gameserverhost.com/filemanager_editor.aspx?defaultconfig=427&filepath=reservedslotslist.txt";

$fields[1]['DropDownListEncoding'] = 1252;
$fields[1]['SubmitButton1'] = "Save";
$fields[1]['TextBoxFile'] = urlencode($new_list);
$fields[1]['TextBoxSaveName'] = "reservedslotslist.txt";
$fields[1]['__EVENTARGUMENT'] = null;
$fields[1]['__EVENTTARGET'] = null;
$fields[1]['__EVENTVALIDATION'] = urlencode("/asdf1324");
$fields[1]['__LASTFOCUS'] = null;
$fields[1]['__VIEWSTATE'] = null;
$fields[1]['___VIEWSTATE'] = urlencode("/asdf13246");
$fields[1]['scrollLeft'] = 0;
$fields[1]['scrollTop'] = 0;


/*
------------------------------------------------------------
					Step 2: Restart Server
------------------------------------------------------------
*/

$urls[2] = "http://gameserverhost.com/service_home.aspx?serviceid=427&svc_short_desc=BC2+Retail+32+Slots&back=user_home.aspx";

$fields[2]['__EVENTARGUMENT'] = null;
$fields[2]['__EVENTTARGET'] = "LinkButtonRestart";
$fields[2]['__EVENTVALIDATION'] = urlencode("asdf1234");
$fields[2]['__LASTFOCUS'] = null;
$fields[2]['__VIEWSTATE'] = null;
$fields[2]['___VIEWSTATE'] = urlencode("asdf12345");
$fields[2]['scrollLeft'] = 0;
$fields[2]['scrollTop'] = 0;

$ch = curl_init();

curl_setopt($ch, CURLOPT_COOKIEJAR, "/tmp/wwvip-cookie.txt");
curl_setopt($ch, CURLOPT_COOKIEFILE, "/tmp/wwvip-cookie.txt");
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: application/x-www-form-urlencoded"));
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/534.24 (KHTML, like Gecko) Chrome/11.0.696.57 Safari/534.24");

$count = count($urls);
for ($i = 0; $i < $count; $i++)
{
	$fields_concatenated = "";

	foreach($fields[$i] as $key => $value)
	{
		$fields_concatenated .= $key . "=" . $value . "&";
	}
		
	$fields_concatenated = rtrim($fields_concatenated, "&");
	
	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_concatenated);
	curl_setopt($ch, CURLOPT_REFERER, ($i > 0) ? $urls[$i - 1] : $urls[0]);
	curl_setopt($ch, CURLOPT_URL, $urls[$i]);
		
	if ($i != 1) /* skip reservedslots.txt manipulation, but still reboot */
		$result = curl_exec($ch);
	
	if ($result == false)
		die("fatal error on step " . $i + 1);
}

curl_close($ch);

echo "vip list updated";

?>