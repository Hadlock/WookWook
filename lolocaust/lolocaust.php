<?php

define("SERVER_ADDRESS", "0.0.0.0");
define("SERVER_PORT", 48888);
define("RCON_PASSWORD", "123456");

/*

Ported RCON script from Python to PHP by XxMASTERUKxX
http://forums.electronicarts.co.uk/battlefield-bad-company-2-pc/924605-there-bc2-server-status-script.html

*/

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

/*

END PORTED RCON

*/

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
    // Connect and authenticate to server
    socket_set_timeout($sock, 0, 500000);
    fwrite($sock,EncodeClientRequest("login.hashed"));    
    list($isFromServer, $isResponse, $sequence, $words) = DecodePacket(fread($sock, 4096));
    
    $salt = hexstr($words[1]);
    $hashedPassword = md5($salt . RCON_PASSWORD, TRUE);
    $hashedPasswordInHex = strtoupper(strhex($hashedPassword));
    
    fwrite($sock, EncodeClientRequest("login.hashed " . $hashedPasswordInHex));
    list($isFromServer, $isResponse, $sequence, $words) = DecodePacket(fread($sock, 4096));
    
    switch ($words[0])
	{
		case "OK":
			break;
		case "PasswordNotSet":
			exit("Server reports RCON password is not set.");
			break;
		case "InvalidPasswordHash":
			exit("Server reports password is incorrect.");
			break;
		case "InvalidArguments":
			exit("Server reports invalid arguments.");
			break;
		default:
			exit("Unexpected problem while connecting to server.");
	}
	
	// Get server information
	fwrite($sock, EncodeClientRequest("serverInfo"));
	list($isFromServer, $isResponse, $sequence, $words) = DecodePacket(fread($sock, 4096));
	
	// Return if server is not full
	list($currentNumOfPlayers, $currentMaxOfPlayers) = ($words[2], $words[3]);
	
	if($currentNumOfPlayers < $currentMaxOfPlayers)
	{
		fwrite($sock, EncodeClientRequest("logout"));
		fclose($sock);
		return "Server is not full";
	}
	
	fwrite($sock, EncodeClientRequest("logout"));
    fclose($sock);
}

?>