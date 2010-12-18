<?php

/*
 * exchange class for the London Internet Exchange
 * parses the colon-separated file supplied by the Linx
 * (C) 2006 Martijn Bakker <martijn@insecure.nl>
 */


include_once('toolbox.class.php');

class linx {

    var $localtsv, $toolbox, $localasn;

    var $tsv_url     = '';
    var $exchange    = 'LINX (London Internet Exchange)';
    var $exchange_short= 'LINX';
    var $cachetime   = 3600;
    var $columns     = array('asn', 'organisation', 'peermail', 'ip');
    var $peers       = array();
    var $lan_regexes = array('A' => '/^195\.66\.224\.[0-9]+$/', 'B' => '/^195\.66\.226\.[0-9]+$/');

    function linx($config) {
        # class initialisation

        $this->localtsv = BASEDIR . 'tmp/linx.tsv';
        $this->localasn = $config['local_asn'];

	if (!is_array($config['lans']))
	    $config['lans'] = array('A', 'B');

	if (is_array($config['columns']))
	    $this->columns = $config['columns'];

	if (is_numeric($config['cachetime']))
	    $this->cachetime = $config['cachetime'];
	
	if ($config['tsv_url'] != '')
	    $this->tsv_url = $config['tsv_url'];
	else 
	    die('tsv_url not set for this router! You need to set this yourself...');
	
	$this->toolbox = new toolbox();
	if (@filemtime($this->localtsv) < (date('U') - $this->cachetime))  {
            $this->localtsv = $this->toolbox->fetchurl($this->tsv_url, $this->localtsv, 0777);
	} 
	$sock = fopen($this->localtsv, 'r');
	$header = fgets($sock);
	$header .= fgets($sock);

	//print_r($config);
	
	while(!feof($sock)) {
	    $rawpeerstr = fgets($sock);
	    $rawpeerstr = preg_replace('/[\r\n]/', '', $rawpeerstr);
	    $rawpeer = split(":", $rawpeerstr);

	    foreach (split(',', $rawpeer[7]) as $ip) {
	        $rawpeer[7] = $ip;
		foreach ($this->lan_regexes as $lan_id => $regex) {
		    if (array_search($lan_id, $config['lans']) > -1 && preg_match($regex, $ip) && !in_array($ip, $config['ignore'])) {
  	    		if (!isset($this->peers[$ip])) {
	   	    	    // add peer
	                    $this->peers[$ip] = array(
		        	'uid'          => $rawpeer[0],
		        	'organisation' => $rawpeer[1],
		        	'nocmail'      => $rawpeer[2],
	                	'phone'        => $rawpeer[3],
		        	'fax'          => $rawpeer[4],
		        	'peermail'     => $rawpeer[5],
		        	'asn'          => $rawpeer[6],
		        	'ip'           => $ip,
				'lanid'	       => $lan_id,
		        	'_shortstatus' => 'nopeer'
	                    );
			}
		    }
		}
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
