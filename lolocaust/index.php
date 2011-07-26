/*

This is the front end web interface for adding kick reasons that the kicked player will see after exiting the game. The purpose of this is to create odd, non-sensical, or often just plain wrong. It then blames the kick on a random user selected from the VIP list to incite the maximum amount of drama in the (very likely) chance they reconnect to complain. This is the beating heart of the lolocaust command, and the reason for it's name. Random lyrics are a good choice, but this is my favorite kick message:

Oh God how did this get here? I am not good with computer.

Kick reasons are stored in the imaginatively named "kickreasons" file in the /lolocaust/ directory.

*/

<?php

$_GET['lolocaust'] = true;
require_once("../vip/include/funcs.php");

if (isset($_POST['submit']))
{
	if (!push_kickreasons($_POST['newReason']))
	{
		die("there was a terrible problem with your terrible input, try again (also duplicate entries not accepted)");
	}
}

?>

<br />this is the list of kick reasons that someone will see when !lolocaust kicks them the fuck out. randomly chosen.<br /><br />

<form method="post" action="index.php">
	<input type="text" name="newReason" maxlength="80" /> kick reason (max length 80 characters)<br />
	<input type="submit" name="submit" value="submit" />
</form>

<?php

	$reasons = get_kickreasons();
	sort($reasons);
	
	$count = count($reasons);
	
	echo "<strong>Total: " . ($count + 1) . "</strong><br /><br /><ul>";
	
	echo "<li />reason: camper | REPORT ADMIN ABUSE | your admin was: {RANDOM VIP LIST NAME GETS INSERTED HERE}<br />";
	
	foreach ($reasons as $reason)
	{
		echo "<li />" .$reason. "<br />";
	}	
	
	echo "</ul>";

?>