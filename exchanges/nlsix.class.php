<?php

/*
 * exchange class for the NL Internet eXchange IPv6 VLAN
 * parses the NL-IX tech.php page
 * (C) 2008 Martijn Bakker <martijn@insecure.nl>
 */


include_once('toolbox.class.php');

class nlsix {

    var $localtsv, $toolbox, $localasn;

    var $exchange  = 'NL-IX (Netherlands Internet Exchange) - IPv6';
    var $exchange_short  = 'NL-IX';
    var $cachetime = 3600;
    var $columns   = array('asn', 'organisation', 'ip', 'location');
    var $peers     = array();

    function nlsix($config) {
        # class initialisation

	$this->tech_url = 'http://www.nl-ix.net/tech.php';
        $this->cachefile = BASEDIR . 'tmp/nlsix.cache';
        $this->localasn = $config['local_asn'];

	if (is_array($config['columns']))
	    $this->columns = $config['columns'];

	if (is_numeric($config['cachetime']))
	    $this->cachetime = $config['cachetime'];
	
	$this->toolbox = new toolbox();
	if (@filemtime($this->cachefile) < (date('U') - 300))  {
            $this->cachefile = $this->toolbox->fetchurl($this->tech_url, $this->cachefile, 0777);
	} 
	$sock = fopen($this->cachefile, 'r');
	$header = fgets($sock);
	while(!feof($sock)) {
	    $rawpeerstr = fgets($sock);

	    if (preg_match('/^.*<TR><TD>::(A50.:....:.)<TD>(.*)<TD>.+\.(.+)\.nlsix\.net.*$/', $rawpeerstr, $rawpeer) &&
		($config['ignore_notready'] != 'true' || in_array($rawpeer[6], array('Not ready', 'No')))) {

                $this->peers[] = array(
		    'ip'           => strtolower('2001:7F8:13::'. $rawpeer[1]),
		    'organisation' => $rawpeer[2],
		    'location'     => $rawpeer[3],
	            'asn'          => (int)preg_replace('/A50([0-6]):([0-9]{4}):.*/', "$1$2", $rawpeer[1]),
		    '_shortstatus' => 'nopeer'
	        );
	    }
	}
	fclose($sock);
    }

    function getpeers() {
        return $this->peers;
    }

    function getpeerbyip($ip) {
        foreach ($this->peers as $peer) {
	    if ($peer['ip'] == $ip) {
	        return $peer;
	    }
	}
	return false;
    }
    
    function getpeerbyas($as) {
        $aslist = array();
        foreach ($this->peers as $peer) {
	    if ($peer['asn'] == $as) {
	        $aslist[] = $peer;
            }
	}
	return $aslist;
    }

    function getcolumns() {
        return($this->columns);
    }

    function getascolumnname() {
        return 'asn';
    }

    function getuniquecolumnname() {
        return 'ip';
    }
}
