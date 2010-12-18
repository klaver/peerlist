<?php

/*
 * ciscotelnet class - connects to cisco routers through telnet
 * (C) 2006 Martijn Bakker <martijn@insecure.nl>
 */

class ciscotelnet {

    var $sock, $peers, $cachefile, $tmpdir;

    var $cachetime = 300;
    var $columns   = array('uptime', 'status');

    function ciscotelnet($config) {

        $this->tmpdir = BASEDIR . 'tmp';

        if (!$config['telnet_port'])
	    $config['telnet_port'] = 23;
   
        if (is_array($config['columns']))
	    $this->columns = $config['columns'];
  
        if (is_numeric($config['cachetime']))
	    $this->cachetime = $config['cachetime'];
  
        $this->cachefile = $this->tmpdir. '/'. preg_replace('/[^a-zA-Z0-9]/', '', $config['hostname']). ':'. $config['telnet_port']. '.cache';
        if (@filemtime($this->cachefile) < (date('U') - $this->cachetime)) {
	    $this->_getpeersfromrouter($config);
	}
        $rawpeerlist = file_get_contents($this->cachefile);

        foreach (split("\n", $rawpeerlist) as $rawpeer) {
            if (preg_match('/(([0-9]{1,3}\.){3}[0-9]{1,3})\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([^\s]+)\s+(.+)$/', $rawpeer, $foo)) {

                $this->peers[$foo[1]] = array(
		    'ip'           => $foo[1],
		    'bgpver'       => $foo[3],
                    'asn'          => $foo[4],
		    'msgrcvd'      => $foo[5],
		    'msgsent'      => $foo[6],
		    'tblver'       => $foo[7],
		    'inq'          => $foo[8],
		    'outq'         => $foo[9],
		    'uptime'       => $foo[10],
		    'status'       => $foo[11],
		    '_shortstatus' => preg_match('/^[0-9]+$/', $foo[11])?'up':'down',
	        );
            }
	}
    }

    function _getpeersfromrouter($config) {
        $header1 = chr(0xFF).chr(0xFB).chr(0x1F).chr(0xFF).chr(0xFB).
                   chr(0x20).chr(0xFF).chr(0xFB).chr(0x18).chr(0xFF).
		   chr(0xFB).chr(0x27).chr(0xFF).chr(0xFD).chr(0x01).
		   chr(0xFF).chr(0xFB).chr(0x03).chr(0xFF).chr(0xFD).
		   chr(0x03).chr(0xFF).chr(0xFC).chr(0x23).chr(0xFF).
		   chr(0xFC).chr(0x24).chr(0xFF).chr(0xFA).chr(0x1F).
		   chr(0x00).chr(0x50).chr(0x00).chr(0x18).chr(0xFF).
		   chr(0xF0).chr(0xFF).chr(0xFA).chr(0x20).chr(0x00).
		   chr(0x33).chr(0x38).chr(0x34).chr(0x30).chr(0x30).
		   chr(0x2C).chr(0x33).chr(0x38).chr(0x34).chr(0x30).
		   chr(0x30).chr(0xFF).chr(0xF0).chr(0xFF).chr(0xFA).
		   chr(0x27).chr(0x00).chr(0xFF).chr(0xF0).chr(0xFF).
		   chr(0xFA).chr(0x18).chr(0x00).chr(0x58).chr(0x54).
		   chr(0x45).chr(0x52).chr(0x4D).chr(0xFF).chr(0xF0);
		   
        $header2 = chr(0xFF).chr(0xFC).chr(0x01).chr(0xFF).chr(0xFC).
                   chr(0x22).chr(0xFF).chr(0xFE).chr(0x05).chr(0xFF).
		   chr(0xFC).chr(0x21);


        if ($this->sock = fsockopen($config['hostname'], $config['telnet_port'])) {
            fwrite($this->sock, $header1);
	    fwrite($this->sock, $header2);

            // wait for login prompt
	    $buffer = '';
	    while (!preg_match('/(Username|Password): $/', $buffer)) {
	        $buffer .= fread($this->sock, 1024);
            }

	    if (preg_match('/Username: $/', $buffer)) {

		if ($config['user'] == '')
		    die('this router needs a user name to log in');
	    
	        // send user name
	        fwrite($this->sock, $config['user']. "\n");

	        // wait for password prompt
	        $buffer = '';
	        while (!preg_match('/(Password: |\>)$/', $buffer)) {
	            $buffer .= fread($this->sock, 1024);
                }
            }

	    if (preg_match('/Password: $/', $buffer)) {

 	        // send password
	        fwrite($this->sock, $config['password']. "\n");

		// wait for prompt
	        $buffer = '';
	        while (!preg_match('/\>$/', $buffer)) {
	            $buffer .= fread($this->sock, 1024);
		    if (preg_match('/% Authentication failed\./', $buffer)) {
		        fclose($this->sock);
		        die('Wrong password given');
		    }
	        }
	    }
	    
	    // set terminal length to 0 (== inf)
	    fwrite($this->sock, "terminal length 0\n");
	    $buffer = '';
	    while (!preg_match('/\>/', $buffer)) {
	        $buffer .= fread($this->sock, 1024);
            }
	    
	    // show ip bgp summary
	    fwrite($this->sock, 'show ip bgp summary'. "\n");
	    $rawpeerlist = '';
	    while (!preg_match('/\>$/', $rawpeerlist)) {
                $rawpeerlist .= fread($this->sock, 4096);
            }
	    
            // quit
            fwrite($this->sock, 'exit'. "\n");
	    fclose($this->sock);

            // wash, rinse and dry
	    $rawpeerlist = preg_replace('/\r/', '', $rawpeerlist);
	    if ($rawfile = fopen($this->cachefile, 'w')) {
	        fputs($rawfile, $rawpeerlist);
            } else {
	        die('could not write to cache file!');
	    }
        } else {
	    die('connection to router failed!');
	}
    }

    function getpeerbyunique($ip) {
        return (is_array($this->peers[$ip])) ? $this->peers[$ip] : array('_shortstatus' => 'nopeer');
    }

    function getcolumns() {
        return $this->columns;
    }
}

