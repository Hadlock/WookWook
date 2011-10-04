/* 

this is supposed to be just the kick reason selector that then passes off to the lolcaust.py on the EC2 instance
right now it calls a curl function to pull the server state (i.e. who's logged in to the server) and then parses it
to give to the invoke_python.php file on the EC2. eventually most of this will be moved to the EC2 .py script

there is a bunch of hard coded, non-portable curl commands specific to our gameserver host. you will have to sort
this out on your own, but generally if you see "gameserverhost.com" or instance number 427, that's your clue that
you need to figure out how to pull the current server roster via html vial curl

again, eventually this should be handled by the lolocaust.py, but this is version 1.0, not 2.0

*/

<?php

/*
------------------------------------------------------------

lolocaust.php - Kick some fuckin' pubbie scum HOORAH

Not sure what __EVENTVALIDATION, ___VIEWSTATE,
etc. are but they seem to be constants. They are required!

------------------------------------------------------------
*/

$_GET['lolocaust'] = true;
require_once("../vip/include/funcs.php");

define("WEBPANEL_USERNAME", "username1"); // username for gameserverhost.com web panel control thing
define("WEBPANEL_PASSWORD", "password1"); // password for gameserverhost.com web panel control thing
define("RCON_PASSWORD", "123456"); // RCON password for gameserver
define("PYTHON_SCRIPT_URL", "http://ec2.amazonaws.com/lolocaust/invoke_python.php"); // location of invoke_python.py. used for amazon EC2
define("MAX_PLAYERS", 32);

$viewstate_scrape;
$players;
$pubbies;
$victim;

/* this keeps people from abusing the kick script; will only run once every minute */

if (!isset($_GET['force']))
{	
	if (lolocaust_init() == false)
		die("lolocaust failed: can only run once per minute");
}

/* 33% chance of using this reason */
if (rand(1, 3) == 3)
{
	$temp = get_vip_list();
	$cashier = $temp[rand(0, count($temp) - 1)];
	unset($temp);
	
	$kickReason = "reason: faggot | REPORT ADMIN ABUSE | your admin was: " . $cashier;
}
/* standard reasons */
else
{
	$kickReasons = get_kickreasons();
		
	$randMax = count($kickReasons) - 1;
	$kickReason = $kickReasons[rand(0, $randMax)];
}

/*

Ported RCON script from Python to PHP by XxMASTERUKxX
http://forums.electronicarts.co.uk/battlefield-bad-company-2-pc/924605-there-bc2-server-status-script.html

*/

$ip = '';
$query_port = 48888; // rcon query port

$clientSequenceNr = 0;

function EncodeClientRequest($words)
{
    global $clientSequenceNr;
    $packet = EncodePacket(False, False, $clientSequenceNr, $words);
    $clientSequenceNr++;
    return $packet;
}

function EncodeHeader($isFromServer, $isResponse, $sequence)
{
    $header = $sequence & 0x3fffffff;
    if ($isFromServer)
        $header += 0x80000000;
    if ($isResponse)
        $header += 0x40000000;

    // Not tested this bit
        
    return pack('I', $header);
}

function DecodeHeader($data)
{
    $header = unpack('I', $data);    
    return array($header & 0x80000000, $header & 0x40000000, $header & 0x3fffffff);
}


function EncodeInt32($size)
{
    return pack('I', $size);
}

function DecodeInt32($data)
{
    $decode = unpack('I', $data);
    return $decode[1];
}
    

function EncodeWords($words)
{
    $size = 0;
    $encodedWords = '';
    foreach ($words as $word)
    {
        $strWord = $word;
        $encodedWords .= EncodeInt32(strlen($strWord));
        $encodedWords .= $strWord;
        $encodedWords .= "\x00";
        $size += strlen($strWord) + 5;
    }
    return array($size, $encodedWords);
}
    
function DecodeWords($size, $data)
{
    $numWords = DecodeInt32($data);        
    $offset = 0;    
    while ($offset < $size)
    {
        $wordLen = DecodeInt32(substr($data,$offset,4));
        $word = substr($data,$offset+4,$wordLen);
        $words[] = $word;
        $offset += $wordLen + 5;        
    }

    return $words;
}

function EncodePacket($isFromServer, $isResponse, $sequence, $words)
{
    $words = explode(' ',$words);
    $encodedHeader = EncodeHeader($isFromServer, $isResponse, $sequence);        
    $encodedNumWords = EncodeInt32(count($words));    
    list($wordsSize, $encodedWords) = EncodeWords($words);
    $encodedSize = EncodeInt32($wordsSize + 12);    
    return $encodedHeader . $encodedSize . $encodedNumWords . $encodedWords;
}

function DecodePacket($data)
{
    list($isFromServer, $isResponse, $sequence) = DecodeHeader($data);
    $wordsSize = DecodeInt32(substr($data,4,4)) - 12;
    $words = DecodeWords($wordsSize, substr($data,12));
    return array($isFromServer, $isResponse, $sequence, $words);
}

$sock = fsockopen( "tcp://" . $ip, $query_port);
if($sock != false)
{
    socket_set_timeout($sock, 0, 500000);
    fwrite($sock,EncodeClientRequest("serverInfo")); // OK, serverName, current playercount, max playercount , gamemode, map    
    list($isFromServer, $isResponse, $sequence, $words) = DecodePacket(fread($sock, 4096));

    print_r($words);    
    
    /*
    //  If its your server you could add 
    //
    //  fwrite($sock,EncodeClientRequest("login.plainText PASSWORD"));
    //  list($isFromServer, $isResponse, $sequence, $words) = DecodePacket(fread($sock, 4096));
    //
    //  fwrite($sock,EncodeClientRequest("admin.listPlayers all));
    //  list($isFromServer, $isResponse, $sequence, $words) = DecodePacket(fread($sock, 4096)); // clantag, player name, squadID, teamID
    //
    //  Then $words contains an array of players on the server (I havn't tested this as I don't have a server to test on)
    */        
    
    fwrite($sock,EncodeClientRequest("quit"));
    list($isFromServer, $isResponse, $sequence, $words) =  DecodePacket(fread($sock, 4096));  
    fclose($sock);
}

/*

End ported RCON

*/


/*
------------------------------------------------------------
Step 0: Login
------------------------------------------------------------
*/

$referers[0] = "http://gameserverhost.com/login.aspx";
$urls[0] = "http://gameserverhost.com/login.aspx";

$fields[0]['ButtonLogin'] = "Login";
$fields[0]['CheckBoxRememberMe'] = "on";
$fields[0]['Password'] = urlencode(WEBPANEL_USERNAME);
$fields[0]['UserName'] = urlencode(WEBPANEL_PASSWORD);
$fields[0]['__EVENTARGUMENT'] = null;
$fields[0]['__EVENTTARGET'] = null;
$fields[0]['__EVENTVALIDATION'] = urlencode("/gameserverhostasdasdasdasdasdasd/asdasd");
$fields[0]['__LASTFOCUS'] = null;
$fields[0]['__VIEWSTATE'] = null;
$fields[0]['___VIEWSTATE'] = urlencode("gameserverhostasdasdasdasdasdasd/asdasd");
$fields[0]['scrollLeft'] = 0;
$fields[0]['scrollTop'] = 0;

/*
------------------------------------------------------------
Step 1: Navigate to rcon admin page
and scrape ___VIEWSTATE
------------------------------------------------------------
*/

$referers[1] = "http://gameserverhost.com/service_home.aspx?serviceid=427";
$urls[1] = "http://gameserverhost.com/gameserverwatcher.aspx?serviceid=427";

/*
------------------------------------------------------------
Step 2: Get current player list
------------------------------------------------------------
*/

$referers[2] = "http://gameserverhost.com/service_home.aspx?serviceid=427";
$urls[2] = "http://gameserverhost.com/gameserverwatcher.aspx?serviceid=427";

$fields[2]['RCONCommand'] = urlencode("admin.listPlayers all");
$fields[2]['RCONPassword'] = urlencode(RCON_PASSWORD);
$fields[2]['SubmitButtonExecute'] = "Execute";
$fields[2]['__EVENTVALIDATION'] = urlencode("/By8");
$fields[2]['__VIEWSTATE'] = null;
$fields[2]['scrollLeft'] = 0;
$fields[2]['scrollTop'] = 0;

/*
------------------------------------------------------------
Step 4: Kick random player
------------------------------------------------------------
*/

$referers[3] = "http://gameserverhost.com/service_home.aspx?serviceid=427";
$urls[3] = "http:///gameserverwatcher.aspx?serviceid=427";

$ch = curl_init();

curl_setopt($ch, CURLOPT_COOKIEJAR, "/tmp/wwvip-cookie.txt");
curl_setopt($ch, CURLOPT_COOKIEFILE, "/tmp/wwvip-cookie.txt");
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, Array("Content-Type: application/x-www-form-urlencoded"));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/534.24 (KHTML, like Gecko) Chrome/11.0.696.57 Safari/534.24");

$count = count($urls);

for ($i = 0; $i < $count; $i++)
{
	if ($i == 1) /* Scrape ___VIEWSTATE */
	{
		curl_setopt($ch, CURLOPT_POST, false);
	}
	else
	{
		curl_setopt($ch, CURLOPT_POST, true);
	}

	if ($i == 2)
	{
		$fields[2]['___VIEWSTATE'] = urlencode($viewstate_scrape[1]);
	}

	curl_setopt($ch, CURLOPT_REFERER, $referers[$i]);
	curl_setopt($ch, CURLOPT_URL, $urls[$i]);

	if ($i == 3)
	{
		$vips = array_merge(get_vip_list(), get_queue_list());

		foreach ($players[1] as $p)
		{
			/* Pubbie scum found, lock on */
			if (!in_array($p, $vips))
				$pubbies[] = $p;			
		}
		
		if (empty($pubbies))
		{
			die("<strong>lolocaust done</strong>. no pubbies found!");
		}

		/* Select the lucky winner */
		$victim = rand(0, count($pubbies) - 1);
		
		/*
			Request:  admin.kickPlayer  <soldier name: player name> [reason: string] 
			Response:  OK    - Player did exist, and got kicked 
			Response:  InvalidArguments 
			Response:  PlayerNotFound  - Player name doesn't exist on server 
			Effect:  Kick player <soldier name> from server 
			Comments:  Reason text is optional. Default reason is “Kicked by administrator”. 
		*/
		
		if (fopen(PYTHON_SCRIPT_URL ."?victim=". urlencode($pubbies[$victim]) ."&kickReason=". urlencode($kickReason), "r"))
			echo "<br /><br /><strong>lolocaust done</strong>. your victim was: " . $pubbies[$victim] . ", and the *~ZANY~* kick reason was: <br /><br />" . $kickReason;
		else
			die("fatal error: couldn't invoke lolocaust.py");
			
	}
 
	$fields_concatenated = "";

	if (isset($fields[$i]))
	{
		foreach($fields[$i] as $key => $value)
		{
			$fields_concatenated .= $key . "=" . $value . "&";
		}
	}

	$fields_concatenated = rtrim($fields_concatenated, "&");

	curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_concatenated);
	
	if ($i != 3)
		$result = curl_exec($ch);

	if ($result == false)
		die("fatal error on step " . $i + 1);

	if ($i == 1)
	{
		/*
		Scrape ___VIEWSTATE

		id="___VIEWSTATE" value="{token}"

		*/

		preg_match("/id=\"___VIEWSTATE\" value=\"(.*)\"/", $result, $viewstate_scrape);

	}
	if ($i == 2)
	{
		/* Scrape player list */
		
		/*
		A player has a name from 4 to 16 characters in length, inclusive. The allowed characters are: 
ABCDEFGHIJKLMNOPQRSTUVWXYZ  
abcdefghijklmnopqrstuvwxyz  
0123456789  
_ - & ( ) * + . / : ; < = > ? [ ] ^ { | } ~ <space> 
When a player is creating a new persona, it is compared against all other persona names; the new name must be 
unique. The following characters are ignored during the comparison: 
- _ <space> 
*/
		/* fix: need to allow < and > in player names */
		preg_match_all("/<BR>([a-zA-Z0-9_\-&()*+.\/:;=?\[\]^{|}~ ]*)<BR>EA_/", $result, $players);
		
		// old, just keeping it around in case:
		// preg_match_all("/\<BR\>([a-zA-Z0-9\.`~!@#$%^&*\(\)\-\_\[\] ]*)<BR\>EA_/", $result, $players);
	}
}

curl_close($ch);

?>