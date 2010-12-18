<?php

/*
 * junipertelnet class - connects to Juniper routers through telnet
 * (C) 2006 Geert Hauwaerts <geert@hauwaerts.be>
 */

class junipertelnet {


    var $sock, $peers, $cachefile, $tmpdir;

    var $cachetime = 300;
    var $columns   = array('uptime', 'status');

    function junipertelnet($config) {

        $this->tmpdir = BASEDIR . 'tmp';

        if (!$config['telnet_port'])
	    $config['telnet_port'] = 23;
   
        if (is_array($config['columns']))
	    $this->columns = $config['columns'];
  
        if (is_numeric($config['cachetime']))
	    $this->cachetime = $config['cachetime'];

        $rawpeerlist = '';

        while (!preg_match("/^\<rpc-reply/", $rawpeerlist) && !preg_match("/\<\/rpc-reply\>$/", $rawpeerlist)) {

            $this->cachefile = $this->tmpdir. '/'. preg_replace('/[^a-zA-Z0-9]/', '', $config['hostname']). ':'. $config['telnet_port']. '.cache';

            if (@filemtime($this->cachefile) < (date('U') - $this->cachetime)) {
    	        $this->_getpeersfromrouter($config);
	    }

            $rawpeerlist = file_get_contents($this->cachefile);

            if (!preg_match("/^\<rpc-reply/", $rawpeerlist) && !preg_match("/\<\/rpc-reply\>$/", $rawpeerlist))
                unlink($this->cachefile);
        }
            
        $xml2a = new XMLToArray();
        $root_node = $xml2a->parse($rawpeerlist);
        $out = array_shift($root_node["_ELEMENTS"]);

        foreach($out['_ELEMENTS'][0]['_ELEMENTS'] as $c) {

            if ($c['_NAME'] == 'bgp-peer') {

                $mydata = array();

                foreach($c['_ELEMENTS'] as $e) {
                    $mydata[$e['_NAME']] = $e['_DATA'];
                }

                if ($mydata['peer-state'] == "Established")
                    $status = 'up';
                else 
                    $status = 'down';

                $this->peers[$mydata['peer-address']] = array(
                    'ip'           => $mydata['peer-address'],
                    'asn'          => $mydata['peer-as'],
                    'msgrcvd'      => $mydata['input-messages'],
                    'msgsent'      => $mydata['output-messages'],
                    'outq'         => $mydata['route-queue-count'],
                    'uptime'       => $mydata['elapsed-time'],
                    'status'       => $mydata['peer-state'],
                    'flaps'        => $mydata['flap-count'],
                    '_shortstatus' => $status
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

            // get login prompt
	    $buffer = '';

	    while (!preg_match('/login: $/', $buffer)) {
	        $buffer .= fread($this->sock, 1024);
            }
	    
	    // send user name
	    fwrite($this->sock, $config['user']. "\n");
	    $buffer = '';
	    while (!preg_match('/Password:$/', $buffer)) {
	        $buffer .= fread($this->sock, 1024);
            }

	    // get password prompt
	    fwrite($this->sock, $config['password']. "\n");
	    $buffer = '';

	    while (!preg_match('/\> $/', $buffer)) {
	        $buffer .= fread($this->sock, 1024);
	    }

	    // set terminal length to 0 (== inf)
	    fwrite($this->sock, "set cli screen-length 0\n");
	    $buffer = '';
	    while (!preg_match('/\> $/', $buffer)) {
	        $buffer .= fread($this->sock, 1024);
            }
	    
	    // show ip bgp summary
	    fwrite($this->sock, 'show bgp summary | display xml'. "\n");
	    $rawpeerlist = '';

	    while (!preg_match('/\<\/rpc-reply\>/', $rawpeerlist)) {

                $temp = fread($this->sock, 4096);
                $rawpeerlist .= $temp;
            }

            // quit
            fwrite($this->sock, 'exit'. "\n");
	    fclose($this->sock);


            // wash, rinse and dry
	    $rawpeerlist = preg_replace('/[\r\n\000]+/', '', $rawpeerlist);
	    $rawpeerlist = preg_replace('/>\s+\</', '><', $rawpeerlist);
	    $rawpeerlist = preg_replace('/show bgp summary \| display xml /', '', $rawpeerlist);
	    $rawpeerlist = preg_replace('/<\/rpc-reply>.*$/', '</rpc-reply>', $rawpeerlist);

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

