<?php

/*
 * exchange class for the FreeBIX (Free Belgian Internet Exchange)
 * parses the tab separated file supplied by the FreeBIX
 * (C) 2006 Geert Hauwaerts <geert@hauwaerts.be>
 */


include_once('toolbox.class.php');

class freebix {

    var $localtsv, $toolbox, $localasn;

    var $tsv_url       = 'http://www.freebix.be/tsv';
    var $exchange      = 'FreeBIX (Free Belgian Internet Exchange)';
    var $exchange_short= 'FreeBIX';
    var $cachetime     = 3600;
    var $columns       = array('asn', 'organisation', 'contact', 'ip', 'location');
    var $peers         = array();
    var $v4range_match = '/^195\.85\.203/';
    var $v6range_match = '/^2001:7f8:1b::/';

    function freebix($config) {
        # class initialisation

        $this->localtsv = BASEDIR .'tmp/freebix.tsv';
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

  	    if (preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $rawpeer[3])) {
                if (preg_match($this->v4range_match, $rawpeer[3]) && $config['show_v4'] != 'off' && !in_array($rawpeer[3], $config['ignore'])) {

	            # add IPV4 peer
	            $this->peers[] = array(
		        'organisation' => $rawpeer[0],
		        'contact'      => $rawpeer[1],
	                'asn'          => $rawpeer[2],
		        'ip'           => $rawpeer[3],
			'multicast'    => $rawpeer[4],
		        'location'     => $rawpeer[6],
			'connection'   => $rawpeer[7],
                        '_shortstatus' => 'nopeer'
	            );
		}
	    }

	    if (preg_match($this->v6range_match, $rawpeer[5]) && $config['show_v6'] != 'off') {
  	        if (preg_match('/^([0-9a-f]{1,4}:){1,8}(:[0-9a-f]{1,4}){1,8}$/', $rawpeer[5])) {
	            # add IPV6 peer
	            $this->peers[] = array(
		        'organisation' => $rawpeer[0],
		        'contact'      => $rawpeer[1],
	                'asn'          => $rawpeer[2],
		        'ip'           => $rawpeer[5],
			'multicast'    => $rawpeer[4],
		        'location'     => $rawpeer[6],
			'connection'   => $rawpeer[7],
                        '_shortstatus' => 'nopeer'
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
