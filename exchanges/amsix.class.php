<?php

/*
 * exchange class for the Amsterdam Internet eXchange
 * parses the tab separated file supplied by the Ams-IX
 * (C) 2006 Martijn Bakker <martijn@insecure.nl>
 */


include_once('toolbox.class.php');

class amsix {

    var $localtsv, $toolbox, $localasn;

    var $tsv_url       = 'https://my.ams-ix.net/api/v1/members.tsv';
    var $exchange      = 'AMS-IX (Amsterdam Internet Exchange)';
    var $exchange_short= 'AMS-IX';
    var $cachetime     = 3600;
    var $columns       = array('asn', 'organisation', 'contact', 'lan', 'ip', 'location');
    var $peers         = array();
    var $v4range_match = '/^195\.69\.14[45]/';
    var $v6range_match = '/^2001:7f8:1::/';

    function amsix($config) {
        # class initialisation

        $this->localtsv = BASEDIR .'tmp/amsix.tsv';
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
	    $rawpeer = $this->toolbox->strip_quotes(split("\t", $rawpeerstr));
		$rawpeer[5] = substr($rawpeer[5], 0, strpos($rawpeer[5], '/'));
		$rawpeer[6] = substr($rawpeer[6], 0, strpos($rawpeer[6], '/'));

		if ($rawpeer[9] == 'yes') $shortstatus = 'up_other';
		else $shortstatus = '';

            if (preg_match($this->v4range_match, $rawpeer[5]) && $config['show_v4'] != 'off' && !in_array($rawpeer[5], $config['ignore'])) {
  	        if (preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $rawpeer[5])) {
	            # add IPV4 peer
	            $this->peers[] = array(
		        'organisation' => $rawpeer[0],
		        'contact'      => $rawpeer[1],
			'switch'       => $rawpeer[2],
	                'asn'          => $rawpeer[3],
		        'lan'          => $rawpeer[4],
		        'ip'           => $rawpeer[5],
			'ip6'	       => $rawpeer[6],
			'multicast'    => $rawpeer[7],
			'mdx'          => $rawpeer[8],
			'routeserver'  => $rawpeer[9],
		        'location'     => $rawpeer[10],
			'connection'   => $rawpeer[11],
			'policy'       => $rawpeer[12],
			'speed'        => $rawpeer[13],
			'_shortstatus' => $shortstatus,
	            );
		}
	    }

	    if (preg_match($this->v6range_match, $rawpeer[6]) && $config['show_v6'] != 'off') {
  	        if (preg_match('/^([0-9a-f]{1,4}:){1,8}(:[0-9a-f]{1,4}){1,8}$/', $rawpeer[6])) {
	            # add IPV6 peer
	            $this->peers[] = array(
		        'organisation' => $rawpeer[0],
		        'contact'      => $rawpeer[1],
			'switch'       => $rawpeer[2],
	                'asn'          => $rawpeer[3],
		        'lan'          => $rawpeer[4],
		        'ip'           => $rawpeer[6],
			'multicast'    => $rawpeer[7],
			'mdx'          => $rawpeer[8],
			'routeserver'  => $rawpeer[9],
		        'location'     => $rawpeer[10],
			'connection'   => $rawpeer[11],
			'policy'       => $rawpeer[12],
			'speed'        => $rawpeer[13],
			'_shortstatus' => $shortstatus,
	            );
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
