<?php

/*
 * brocadessh class - connects to brocade routers through ssh
 * (C) 2013 Wouter de Jong <wouter@widexs.nl> - based on ciscossh class
 * depends on: pecl-ssh2
 */

class brocadessh {

    var $ssh, $peers, $cachefile, $tmpdir;

    var $cachetime = 300;
    var $columns   = array('uptime', 'status');

    function brocadessh($config) {

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
  
        $this->cachefile = $this->tmpdir. '/'. preg_replace('/[^a-zA-Z0-9]/', '', $config['hostname']). ':'. $config['ssh_port']. '.v6.cache';
        if (@filemtime($this->cachefile) < (date('U') - $this->cachetime)) {
		$this->_getpeersfromrouter($config);
	}


        $rawpeerlist = split("\n", file_get_contents($this->cachefile));

        while (list($id, $rawpeer) = each($rawpeerlist)) {
            if (preg_match('/^\s*([a-fA-F0-9:\.]+)\s+([0-9]+)\s+([^\s]+)\s+(.+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s*$/', $rawpeer, $foo)) {

			$this->peers[strtolower($foo[1])] = array(
				'ip'           => strtolower($foo[1]),
				'bgpver'       => '4',
				'asn'          => $foo[2],
				'uptime'       => $foo[4],
				'status'       => $foo[3],
				'_shortstatus' => preg_match('/^ESTAB/', $foo[3])?'up':'down',
			);
            }
		}
	}

    function _getpeersfromrouter($config) {

		$rawpeerlist = $this->_connectandexecute($config['hostname'], $config['ssh_port'], $config['ssh_fingerprint'], $config['user'], $config['password'], 'sh ipv6 bgp summary');
		$rawpeerlist .= $this->_connectandexecute($config['hostname'], $config['ssh_port'], $config['ssh_fingerprint'], $config['user'], $config['password'], 'sh ip bgp summary');

        // wash, rinse and dry
	    $rawpeerlist = preg_replace('/\r/', '', $rawpeerlist);
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
                        die("fingerprint mismatch!");
                }

                if (! ssh2_auth_password($ssh, $user, $pass)) {
                        die("authentication failed!");
                }
		
		// shell, as Brocade really doesn't seem to like exec
		if (!($sock = ssh2_shell($ssh, 'vt102', null, 80, 40, SSH2_TERM_UNIT_CHARS))) {
			die("failed to establish shell!\n");
		}
		
		fwrite($sock, "terminal length 0" . PHP_EOL);
		fwrite($sock, $command . PHP_EOL);
		sleep(1);	// seems to be a magic trick...
		stream_set_blocking($sock, true);
		
		$data = "";
		while ($buf = fread($sock,4096)) {
			flush();
			if(preg_match('/SSH@.+#$/',$buf)) { break; }
			$data .= $buf;
		}
		
		fclose($sock);
		
		return $data;
    }

    function getpeerbyunique($ip) {
        return (is_array($this->peers[$ip])) ? $this->peers[$ip] : array('_shortstatus' => 'nopeer');
    }

    function getcolumns() {
        return $this->columns;
    }
}

