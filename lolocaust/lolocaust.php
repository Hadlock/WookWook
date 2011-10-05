<?php

$_GET['lolocaust'] = true;
require_once("../vip/include/funcs.php");

define("SERVER_ADDRESS", "0.0.0.0");
define("SERVER_PORT", 48888);
define("RCON_PASSWORD", "123456");
$players;
$pubbies;
$victim;

/* code commented as script will perform checks before modifying VIP list

if (!isset($_GET['force']))
{	
	if (lolocaust_init() == false)
		die("lolocaust failed: can only run once per minute");
}

*/

$kickReasons = get_kickreasons();
		
$randMax = count($kickReasons) - 1;
$kickReason = $kickReasons[rand(0, $randMax)];

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

// Hashed password helper functions
function hexstr($hexstr)
{
	$hexstr = str_replace(' ', '', $hexstr);
	$hexstr = str_replace('\x', '', $hexstr);
	$retstr = pack('H*', $hexstr);
	return $retstr;
}

function strhex($string)
{
	$hexstr = unpack('H*', $string);
	return array_shift($hexstr);
}

$sock = fsockopen( "tcp://" . SERVER_ADDRESS, SERVER_PORT);
if($sock != false)
{
    socket_set_timeout($sock, 0, 500000);
    fwrite($sock,EncodeClientRequest("login.hashed"));    
    list($isFromServer, $isResponse, $sequence, $words) = DecodePacket(fread($sock, 4096));
    
    $salt = hexstr($words[1]);
    $hashedPassword = md5($salt . RCON_PASSWORD, TRUE);
    $hashedPasswordInHex =strtoupper(strhex($hashedPassword));
    
    fwrite($sock, EncodeClientRequest("login.hashed " . $hashedPasswordInHex));
    list($isFromServer, $isResponse, $sequence, $words) = DecodePacket(fread($sock, 4096));
    
    fclose($sock);
}

/*

End ported RCON

*/

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