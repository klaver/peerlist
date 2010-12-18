<?php

/*
 * exchange class for a local network TSV
 * parses the tab separated file supplied by the Ams-IX
 * (C) 2006 Martijn Bakker <martijn@insecure.nl>
 */


include_once('toolbox.class.php');

class local {

    var $tsvfile = 'local.tsv';
    var $columns = array('router', 'function', 'ip', 'location');
    var $peers = array();
    var $exchange = "Local exchange";
    var $exchange_short = "Local";
    var $toolbox;

    function local($config) {
        # class initialisation
	$this->toolbox = new toolbox();
	$this->tsvfile = $config['tsv_file'];

        if (is_array($config['columns']))
	    $this->columns = $config['columns'];

	if (!file_exists($this->tsvfile)) {
	  echo "not a valid TSV file given!";
	  exit(1);
	}
	
	$sock = fopen($this->tsvfile, 'r');
	$header = fgets($sock);
	while(!feof($sock)) {
	    $rawpeerstr = fgets($sock);
	    $rawpeerstr = preg_replace('/[\r\n]/', '', $rawpeerstr);
	    $rawpeer = $this->toolbox->strip_quotes(split("\t", $rawpeerstr));

	    if ((preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $rawpeer[2]) || preg_match('/^([0-9a-f]{1,4}:){1,8}(:[0-9a-f]{1,4}){1,8}$/', $rawpeer[2])) && !in_array($rawpeer[2], $config['ignore'])) {
	        # add peer
	        $this->peers[] = array(
	            'router'       => $rawpeer[0],
	            'function'     => $rawpeer[1],
		    'ip'           => $rawpeer[2],
		    'location'     => $rawpeer[3],
		    'asn'          => '0',
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
