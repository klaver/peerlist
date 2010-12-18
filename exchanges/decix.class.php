<?php

/*
 * exchange class for the Deutscher Commercial Internet Exchange
 * parses the tab separated file supplied by the decix
 * (C) 2006 Martijn Bakker <martijn@insecure.nl>
 */


include_once('toolbox.class.php');

class decix {

    var $localtsv, $toolbox, $localasn;

    var $tsv_url   = '';
    var $exchange  = 'DE-CIX (Deutscher Commercial Internet Exchange)';
    var $exchange_short  = 'DE-CIX';
    var $cachetime = 3600;
    var $columns   = array('asn', 'organisation', 'peermail', 'ip');
    var $peers     = array();

    function decix($config) {
        # class initialisation

        $this->localtsv = BASEDIR . 'tmp/decix.tsv';
        $this->localasn = $config['local_asn'];

	if (is_array($config['columns']))
	    $this->columns = $config['columns'];

	if (is_numeric($config['cachetime']))
	    $this->cachetime = $config['cachetime'];
	
	if ($config['tsv_url'] != '')
	    $this->tsv_url = $config['tsv_url'];
	
	$this->toolbox = new toolbox();
	if (@filemtime($this->localtsv) < (date('U') - $this->cachetime))  {
            $this->localtsv = $this->toolbox->fetchurl($this->tsv_url, $this->localtsv, 0777);
	} 
	$sock = fopen($this->localtsv, 'r');
	$header = fgets($sock);
	while(!feof($sock)) {
	    $rawpeerstr = fgets($sock);
	    $rawpeerstr = preg_replace('/[\r\n]/', '', $rawpeerstr);
	    if (preg_match('/^#/', $rawpeerstr))
	        continue;
		
	    $rawpeer = split(";", $rawpeerstr);

  	    if (((preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $rawpeer[2]) && $config['show_v4'] != 'no') ||
	        (preg_match('/^2001:7f8:/', $rawpeer[2]) && $config['show_v6'] != 'no')) && !in_array($rawpeer[2], $config['ignore'])) {
	        // add peer
	        $this->peers[] = array(
		    'port'         => $rawpeer[0],
		    'asn'          => $rawpeer[1],
		    'ip'           => $rawpeer[2],
	            'phone'        => $rawpeer[3],
		    'contact'      => $rawpeer[4],
		    'peermail'     => ($rawpeer[5] != "")?$rawpeer[5]:$rawpeer[4],
		    'organisation' => $rawpeer[6],
		    'custid'       => $rawpeer[7],
		    'mask'         => $rawpeer[8],
		    'asset'        => $rawpeer[9],
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
        return $this->columns;
    }

    function getascolumnname() {
        return 'asn';
    }
    
    function getuniquecolumnname() {
        return 'ip';
    }


}
