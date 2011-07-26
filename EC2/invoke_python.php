/*

This php code calls lolocaust.py, which is a variation of the dice example rcon server code. It passes the kick reason from the previous script along to lolocaust.py. It's primary purpose is to run lolocaust.py on an amazon.com EC3 instance because our hosting wouldn't do it

*/

<?php

	if (!empty($_GET['victim']) && !empty($_GET['kickReason']))
	{
		if (system("python /opt/bitnami/apache2/htdocs/lolocaust/lolocaust.py -u \"" . urldecode($_GET['victim']) . "\" -r \"" . urldecode($_GET['kickReason']) . "\""))
			echo "<br /><br /><strong>lolocaust done</strong>. your victim was: " . urldecode($_GET['victim']) . ", and the *~ZANY~* kick reason was: <br /><br />" . urldecode($_GET['kickReason']);
		
		else
			die("fatal error: couldn't invoke lolocaust.py");
	}
	else
		die("invalid arguments");
	
?>