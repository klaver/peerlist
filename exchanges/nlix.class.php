<?php

/*
 * exchange class for the NL Internet eXchange
 * parses the tab separated file supplied by the NL-IX
 * (C) 2006 Martijn Bakker <martijn@insecure.nl>
 */


include_once('toolbox.class.php');

class nlix {

    var $localtsv, $toolbox, $localasn;

    var $tsv_url   = 'http://www.nl-ix.net/tsvmembers.php';
    var $exchange  = 'NL-IX (Netherlands Internet Exchange)';
    var $exchange_short  = 'NL-IX';
    var $cachetime = 3600;
    var $columns   = array('asn', 'organisation', 'contact', 'asmacro', 'ip', 'location', 'policy');
    var $peers     = array();

    function nlix($config) {
        # class initialisation

        $this->localtsv = BASEDIR . 'tmp/nlix.tsv';
        $this->localasn = $config['local_asn'];

	if (is_array($config['columns']))
	    $this->columns = $config['columns'];

	if (is_numeric($config['cachetime']))
	    $this->cachetime = $config['cachetime'];
	
        if ($config['tsv_url'] != '')
	    $this->tsv_url = $config['tsv_url'];

	
	$this->toolbox = new toolbox();
	if (@filemtime($this->localtsv) < (date('U') - 300))  {
            $this->localtsv = $this->toolbox->fetchurl($this->tsv_url, $this->localtsv, 0777);
	} 
	$sock = fopen($this->localtsv, 'r');
	$header = fgets($sock);
	while(!feof($sock)) {
	    $rawpeerstr = fgets($sock);
	    $rawpeerstr = preg_replace('/[\r\n]/', '', $rawpeerstr);
	    $rawpeer = $this->toolbox->strip_quotes(split("\t", $rawpeerstr));

		if ($rawpeer[8] == 'Yes') $shortstatus = 'up_other';
		else $shortstatus = '';

            if ($rawpeer[3] != '-' && $rawpeer[1] != '-' && $rawpeer[1] != '' && !in_array($rawpeer[1], $config['ignore']) &&
		($config['ignore_notready'] != 'true' || in_array($rawpeer[6], array('Not ready', 'No')))) {
                $this->peers[] = array(
		    'organisation' => $rawpeer[0],
		    'ip'           => $rawpeer[1],
		    'location'     => $rawpeer[2],
	            'asn'          => substr($rawpeer[3],2),
	   	    'asmacro'      => $rawpeer[4],
		    'contact'      => $rawpeer[5],
		    'policy'       => $rawpeer[6],
		    'mlpa'         => $rawpeer[7],
		    'routeserver'  => $rawpeer[8],
		    '_shortstatus' => $shortstatus
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
