<?php

/*
 * juniperssh class - connects to juniper routers through ssh
 * (C) 2008 Martijn Bakker <martijn@insecure.nl>
 * depends on: pecl-ssh2
 */

include_once('xmltoarray.class.php');

class juniperssh {

    var $ssh, $peers, $cachefile, $tmpdir;

    var $cachetime = 300;
    var $columns   = array('uptime', 'status');

    function juniperssh($config) {

	if (!function_exists('ssh2_connect')) {
	    die("pecl-ssh2 not installed!");
	}


        $this->tmpdir = BASEDIR . 'tmp';

        if (!$config['ssh_port'])
			$config['ssh_port'] = 22;
   
        if (is_array($config['columns']))
			$this->columns = $config['columns'];
  
        if (is_numeric($config['cachetime']))
			$this->cachetime = $config['cachetime'];
  
        $this->cachefile = $this->tmpdir. '/'. preg_replace('/[^a-zA-Z0-9]/', '', $config['hostname']). ':'. $config['ssh_port']. '.cache';
        if (@filemtime($this->cachefile) < (date('U') - $this->cachetime)) {
		$this->_getpeersfromrouter($config);
	}


        $rawpeerlist = file_get_contents($this->cachefile);

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

		$rawpeerlist = $this->_connectandexecute($config['hostname'], $config['ssh_port'], $config['ssh_fingerprint'], $config['user'], $config['password'], 'sho bgp summary | display xml');

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
    }


    function _connectandexecute($hostname, $port, $fingerprint, $user, $pass, $command) {
		// connect via ssh2
                $ssh = ssh2_connect($hostname, $port);
                if (! $ssh) {
                    die("connection failed!");
                }

                $theirfingerprint = ssh2_fingerprint($ssh, SSH2_FINGERPRINT_MD5 | SSH2_FINGERPRINT_HEX);
                if (strtoupper($theirfingerprint) != strtoupper($fingerprint)) {
                        die("fingerprint mismatch: their: $theirfingerprint us: $fingerprint");
                }

                if (! ssh2_auth_password($ssh, $user, $pass)) {
                        die("authentication failed!");
                }

                $sock = ssh2_exec($ssh, $command);
                stream_set_blocking($sock, true);
                return stream_get_contents($sock);

    }

    function getpeerbyunique($ip) {
        return (is_array($this->peers[$ip])) ? $this->peers[$ip] : array('_shortstatus' => 'nopeer');
    }

    function getcolumns() {
        return $this->columns;
    }
}

