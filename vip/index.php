<?php

require_once("include/funcs.php");

if (isset($_GET['view_viplist']))
{
	$vips = get_vip_list();
	sort($vips);
	
	$count = count($vips);
		
	echo "<strong>Total: " . $count . "</strong><br /><br />";
	
	foreach ($vips as $vip)
	{
		echo $vip . "<br />";
	}
	
	die();
}
else if (isset($_POST['submit']))
{
	if (!push_queue($_POST))
	{
		die("there was a terrible problem with your terrible input, try again");
	}
}

?>

<form method="post" action="index.php">
	<input type="text" name="username" /> your name here! don't include [clantag] - <img src="casesensitive.jpg"><br />
	<input type="submit" name="submit" value="bribe the bouncer" />
</form>

<?php

$queued = get_queue_list();
$count = count($queued);
	
echo "waitin' in line at da club: " . $count . "<br /><br /><ul>";
	
foreach ($queued as $q)
{
	echo "<li />" . $q . "<br />";
}
/* you will have to set up your own cronjob to update the vip list every day/hour/week/whatever */
/* also, this is the primary access to the kick messages for users. you may want to turn this off */
echo "</ul><br />club doors open err day @ 8am EST<br /><br /><a href=\"index.php?view_viplist\">TMZ breaking news: confirmed WookWook A-listers</a><br /><br /><a href=\"../lolocaust/\">want to add a WACKY lolocaust kick message to the rotation? then click here</a>";

?>