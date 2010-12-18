<?php

/*
 * ciscossh class - connects to cisco routers through ssh
 * (C) 2008 Martijn Bakker <martijn@insecure.nl>
 * depends on: pecl-ssh2
 */

class ciscossh {

    var $ssh, $peers, $cachefile, $tmpdir;

    var $cachetime = 300;
    var $columns   = array('uptime', 'status');

    function ciscossh($config) {

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
	    if (preg_match('/^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))$/', $rawpeer, $foo)) {
		$rawpeer = $foo[0]. current($rawpeerlist);
	    }

            if (preg_match('/([a-fA-f0-9:\.]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([0-9]+)\s+([^\s]+)\s+(.+)$/', $rawpeer, $foo)) {

			$this->peers[strtolower($foo[1])] = array(
				'ip'           => strtolower($foo[1]),
				'bgpver'       => $foo[2],
				'asn'          => $foo[3],
				'msgrcvd'      => $foo[4],
				'msgsent'      => $foo[5],
				'tblver'       => $foo[6],
				'inq'          => $foo[7],
				'outq'         => $foo[8],
				'uptime'       => $foo[9],
				'status'       => $foo[10],
				'_shortstatus' => preg_match('/^[0-9]+$/', $foo[10])?'up':'down',
			);
            }
		}
	}

    function _getpeersfromrouter($config) {

		$rawpeerlist = $this->_connectandexecute($config['hostname'], $config['ssh_port'], $config['ssh_fingerprint'], $config['user'], $config['password'], 'sho bgp ipv6 unicast summary');
		$rawpeerlist .= $this->_connectandexecute($config['hostname'], $config['ssh_port'], $config['ssh_fingerprint'], $config['user'], $config['password'], 'sho bgp ipv4 unicast summary');

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

