<?php

/*
 * snmp class - connects to a router and fetches info through SNMP according to RFC 1657
 * more info: http://rfc.net/rfc1657.html
 * (C) 2006 Martijn Bakker <martijn@insecure.nl>
 */

class snmp_rfc1657 {

    var $sock, $peers, $cachefile, $tmpdir;

    var $base_oid  = '.1.3.6.1.2.1.15.3.1';
    var $cachetime = 300;
    var $columns   = array('status');

    function snmp_rfc1657($config) {

        $this->tmpdir = BASEDIR . 'tmp';

        if (!$config['snmp_port'])
	    $config['snmp_port'] = 161;
	
	if (is_array($config['columns'])) 
	    $this->columns = $config['columns'];

	if (is_numeric($config['cachetime']))
	    $this->cachetime = $config['cachetime'];

	$statuslist = array(
	    '1' => 'idle',
	    '2' => 'connect',
	    '3' => 'active',
	    '4' => 'opensent',
	    '5' => 'openconfirm',
	    '6' => 'established'
	);

	$shortstatuslist = array(
	    '1' => 'down',
	    '2' => 'down',
	    '3' => 'down',
	    '4' => 'down',
	    '5' => 'down',
	    '6' => 'up'
	);
	
        $this->cachefile = $this->tmpdir. '/'. preg_replace('/[^a-zA-Z0-9]/', '', $config['hostname']). ':'. $config['snmp_port']. '.cache';
        if (@filemtime($this->cachefile) < (date('U') - $this->cachetime)) {
	    $this->_getpeersfromrouter($config);
	}
        $raw_snmp = unserialize(file_get_contents($this->cachefile));

	foreach ($raw_snmp as $oid => $info) {
	    $oid_sections = explode('.', $oid);

	    $ip = join('.', array_slice($oid_sections, -4, 4));
	    list($sub_oid) = array_slice($oid_sections, -5 ,1);
	    $raw_peers[$ip][$sub_oid] = $info;
	}

	foreach ($raw_peers as $ip => $peer) {
	    $this->peers[$ip] = array(
	        'ip'           => $ip,
		'asn'          => $peer[9],
		'status'       => $statuslist[$peer[2]],
		'_shortstatus' => $shortstatuslist[$peer[2]]
            );
	}
    }

    function _getpeersfromrouter($config) {

        if ($config['snmp_community'] == '') {
	    die('no community value set');
	}

	if (!$config['hostname']) {
	    die('no hostname value set');
	}
        snmp_set_oid_numeric_print(1);
        snmp_set_valueretrieval(SNMP_VALUE_PLAIN);

        if ($raw_snmp = snmpwalkoid($config['hostname']. ':'. $config['port'], $config['snmp_community'], $this->base_oid)) {

	    if ($rawfile = fopen($this->cachefile, 'w')) {
	        fputs($rawfile, serialize($raw_snmp));
            } else {
	        die('could not write to cache file! '. $this->cachefile);
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
