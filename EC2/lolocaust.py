# This is where all the magic happens. This parses the BC2 server output and compares it against the user whitelist (VIP)
# This will find one user at random who is not on the whitelist (VIP) and kick them from the server to make room
# for a VIP user. It also passes along a custom kick message pulled at random from a previous script in the chain.
# I didn't write this code, but I suspect a lot of it is extraneous. We use this code because for some reason PHP has issues
# Passing along kick messages without_including_underscores_where_spaces_should_be. Kick messages max out at 80 chars (i think)
# Server specific VARS are currently hard-coded starting at line 170-190. Line 265 is the actual kick command.

#!/usr/bin/python

###################################################################################
#
# START DICE CODE
#
# DO NOT DELETE
#
###################################################################################

# path used above is for godaddy
# previous path was: /usr/local/bin/python
from struct import *
import binascii
import socket
import sys
import shlex
import string
import threading
import md5
#import readline
import os
import urllib2 # Needed to retreive whitelist
import random

# This is an example program that connects to the Remote Administration port of a game server.
# Once logged in, you can use this to send commands to control the game server.
#
# There are much fancier clients available than this one; it has been the basis of all the other clients.


def EncodeHeader(isFromServer, isResponse, sequence):
	header = sequence & 0x3fffffff
	if isFromServer:
		header += 0x80000000
	if isResponse:
		header += 0x40000000
	return pack('<I', header)

def DecodeHeader(data):
	[header] = unpack('<I', data[0 : 4])
	return [header & 0x80000000, header & 0x40000000, header & 0x3fffffff]

def EncodeInt32(size):
	return pack('<I', size)

def DecodeInt32(data):
	return unpack('<I', data[0 : 4])[0]
	
	
def EncodeWords(words):
	size = 0
	encodedWords = ''
	for word in words:
		strWord = str(word)
		encodedWords += EncodeInt32(len(strWord))
		encodedWords += strWord
		encodedWords += '\x00'
		size += len(strWord) + 5
	
	return size, encodedWords
	
def DecodeWords(size, data):
	numWords = DecodeInt32(data[0:])
	words = []
	offset = 0
	while offset < size:
		wordLen = DecodeInt32(data[offset : offset + 4])		
		word = data[offset + 4 : offset + 4 + wordLen]
		words.append(word)
		offset += wordLen + 5

	return words

def EncodePacket(isFromServer, isResponse, sequence, words):
	encodedHeader = EncodeHeader(isFromServer, isResponse, sequence)
	encodedNumWords = EncodeInt32(len(words))
	[wordsSize, encodedWords] = EncodeWords(words)
	encodedSize = EncodeInt32(wordsSize + 12)
	return encodedHeader + encodedSize + encodedNumWords + encodedWords

# Decode a request or response packet
# Return format is:
# [isFromServer, isResponse, sequence, words]
# where
#	isFromServer = the command in this command/response packet pair originated on the server
#   isResponse = True if this is a response, False otherwise
#   sequence = sequence number
#   words = list of words
	
def DecodePacket(data):
	[isFromServer, isResponse, sequence] = DecodeHeader(data)
	wordsSize = DecodeInt32(data[4:8]) - 12
	words = DecodeWords(wordsSize, data[12:])
	return [isFromServer, isResponse, sequence, words]

###############################################################################

clientSequenceNr = 0

# Encode a request packet

def EncodeClientRequest(words):
	global clientSequenceNr
	packet = EncodePacket(False, False, clientSequenceNr, words)
	clientSequenceNr = (clientSequenceNr + 1) & 0x3fffffff
	return packet

# Encode a response packet
	
def EncodeClientResponse(sequence, words):
	return EncodePacket(True, True, sequence, words)

###################################################################################

def containsCompletePacket(data):
	if len(data) < 8:
		return False
	if len(data) < DecodeInt32(data[4:8]):
		return False
	return True

# Wait until the local receive buffer contains a full packet (appending data from the network socket),
# then split receive buffer into first packet and remaining buffer data
	
def receivePacket(socket, receiveBuffer):

	while not containsCompletePacket(receiveBuffer):
		receiveBuffer += socket.recv(4096)

	packetSize = DecodeInt32(receiveBuffer[4:8])

	packet = receiveBuffer[0:packetSize]
	receiveBuffer = receiveBuffer[packetSize:len(receiveBuffer)]

	return [packet, receiveBuffer]

###################################################################################

# Display contents of packet in user-friendly format, useful for debugging purposes
	
def printPacket(packet):

	if (packet[0]):
		print "IsFromServer, ",
	else:
		print "IsFromClient, ",
	
	if (packet[1]):
		print "Response, ",
	else:
		print "Request, ",

	print "Sequence: " + str(packet[2]),

	if packet[3]:
		print " Words:",
		for word in packet[3]:
			print "\"" + word + "\"",

	print ""

###################################################################################

def generatePasswordHash(salt, password):
	m = md5.new()
	m.update(salt)
	m.update(password)
	return m.digest()

###################################################################################
#
# END DICE CODE
#
###################################################################################

###################################################################################
#
# START BFGOONS CUSTOM CODE
#
###################################################################################

def getWhitelist():
    theurl = ''
    username = ''
    password = ''

    passman = urllib2.HTTPPasswordMgrWithDefaultRealm()
    passman.add_password(None, theurl, username, password)

    authhandler = urllib2.HTTPBasicAuthHandler(passman)

    opener = urllib2.build_opener(authhandler)

    urllib2.install_opener(opener)

    pagehandle = urllib2.urlopen(theurl)

    unfilteredWhitelist = pagehandle.read()

    filteredWhitelist = unfilteredWhitelist.splitlines()
    filteredWhitelist = frozenset(filteredWhitelist) # removes duplicates
    
    return filteredWhitelist
    
def getKickReasons():    
	theurl = ''
	username = ''
	password = ''
	
	passman = urllib2.HTTPPasswordMgrWithDefaultRealm()
	passman.add_password(None, theurl, username, password)
	
	authhandler = urllib2.HTTPBasicAuthHandler(passman)
	
	opener = urllib2.build_opener(authhandler)
	
	urllib2.install_opener(opener)
	
	pagehandle = urllib2.urlopen(theurl)
	
	unfilteredKickReasons = pagehandle.read()
	
	filteredKickReasons = unfilteredKickReasons.splitlines()
	
	# filter out reasons longer than 80 characters
	tmpList = []
	for reason in filteredKickReasons:
		if len(reason) < 80 : tmpList.append(reason)
	filteredKickReasons = tmpList
	tmpList = []
	
	filteredKickReasons = frozenset(filteredKickReasons) # removes duplicates
	
	return filteredKickReasons
    
def getRandomKickReason():
	listOfKickReasons = getKickReasons()
	reason = random.sample(listOfKickReasons, 1)
	return ''.join(reason)
    
###################################################################################
#
# END BFGOONS CUSTOM CODE
#
###################################################################################

###################################################################################
# Main loop
# 

if __name__ == '__main__':
	from getopt import getopt
	import sys

#	print "Remote administration console for BFBC2"
#	history_file = os.path.join( os.environ["HOME"], ".bfbc2_rcon_history" )

	host = "127.0.0.1"  # server ip address (NOT procon server IP)
	port = 48888        # server RCON port (NOT game server port)
	pw = "123456"       # server RCPM password
	user = ""           # typically left blank
	# no need for reason anymore, it's grabbed by this script
	x = "" # unused, see below

	receiveBuffer = ""

	serverSocket = None

	# HACK HACK HACK: For some reason the last argument never gets parsed, so we add a junk argument at the end
	opts, args = getopt(sys.argv[1:], 'h:p:a:u:r:x')
	for k, v in opts:
		if k == '-h':
			host = v
		elif k == '-p':
			port = int(v)
		elif k == '-a':
			pw = v
		elif k == '-u':
			user = v
		elif k == '-x':
			x = v

	try:
		try:
#			try:
#				readline.read_history_file( history_file )
#			except IOError:
#				# No file init
#				hfile = file( history_file, "w+" )
#				close( hfile )
#				readline.read_history_file( history_file )

			serverSocket = socket.socket(socket.AF_INET, socket.SOCK_STREAM)

			# print 'Connecting to port: %s:%d...' % ( host, port )
			serverSocket.connect( ( host, port ) )
			serverSocket.setblocking(1)

			if pw is not None:
				print 'Logging in - 1: retrieving salt...'

				# Retrieve this connection's 'salt' (magic value used when encoding password) from server
				getPasswordSaltRequest = EncodeClientRequest( [ "login.hashed" ] )
				serverSocket.send(getPasswordSaltRequest)

				[getPasswordSaltResponse, receiveBuffer] = receivePacket(serverSocket, receiveBuffer)
				printPacket(DecodePacket(getPasswordSaltResponse))

				[isFromServer, isResponse, sequence, words] = DecodePacket(getPasswordSaltResponse)

				# if the server doesn't understand "login.hashed" command, abort
				if words[0] != "OK":
					sys.exit(3) # error

				# print 'Received salt: ' + words[1]

				# Given the salt and the password, combine them and compute hash value
				salt = words[1].decode("hex")
				passwordHash = generatePasswordHash(salt, pw)
				passwordHashHexString = string.upper(passwordHash.encode("hex"))

				print 'Computed password hash: ' + passwordHashHexString
				
				# Send password hash to server
				print 'Logging in - 2: sending hash...'

				loginRequest = EncodeClientRequest( [ "login.hashed", passwordHashHexString ] )
				serverSocket.send(loginRequest)

				[loginResponse, receiveBuffer] = receivePacket(serverSocket, receiveBuffer)
				printPacket(DecodePacket(loginResponse))

				[isFromServer, isResponse, sequence, words] = DecodePacket(loginResponse)

				# if the server didn't like our password, abort
				if words[0] != "OK":
					sys.exit(3) # error
				
				###
				# Get players currently on server
				###
				
				command = 'admin.listPlayers all'
				words = shlex.split(command)
				
				# Send request to server on command channel
				request = EncodeClientRequest(words)
				serverSocket.send(request)
				
				# Wait for response from server
				[packet, receiveBuffer] = receivePacket(serverSocket, receiveBuffer)
				
				[isFromServer, isResponse, sequence, words] = DecodePacket(packet)
				
				rconResponse = words
				playersOnServer = [rconResponse[i] for i in range(13, len(rconResponse), 9)]
				
				if len(playersOnServer) < 32: sys.exit(1) # Server not full
				
				###
				# Compare players on server to whitelist
				# Make list of pubbies
				###
				
				whitelist = getWhitelist()
			
				pubbies = set(playersOnServer).difference(whitelist)
				
				if len(pubbies) == 0: sys.exit(2) # No pubbies on server
				
				###
				# Kick a random pubby
				###
				unluckyPubby = ''.join(random.sample(pubbies, 1))
				kickReason = getRandomKickReason()
				
				command = "admin.kickPlayer " + "\"" + unluckyPubby + "\" " + "\"" + kickReason + "\""
				
				words = shlex.split(command)
				request = EncodeClientRequest(words)
				serverSocket.send(request)
				receivePacket(serverSocket, receiveBuffer)
				
				command = "admin.say " + "\"" + unluckyPubby + " was kicked: " + kickReason + "\" all"
				
				words = shlex.split(command)
				request = EncodeClientRequest(words)
				serverSocket.send(request)

		except socket.error, detail:
			print 'Network error:', detail[1]

		except EOFError, KeyboardInterrupt:
			pass

		except:
			raise

	finally:
		try:
#			readline.write_history_file( history_file )
			if serverSocket is not None:
				serverSocket.close()

			# print "Done"
		except:
			raise

	sys.exit(0)
