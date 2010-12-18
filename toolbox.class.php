<?php

/*
 * toolbox for peerlist v0.1
 * (c) Martijn Bakker <martijn@support.net>
 */


class toolbox {

    /*
     * class constructor
     */

    function toolbox() {
    }


    /*
     * fetch and store a file from the intarweb
     */

    function fetchurl($url, $localfile='', $perms=0750) {
	if ($localfile == '')
	    $localfile = BASEDIR . 'tmp/'. preg_replace('/[^a-z0-9-]+/', '', $url);
	    
	if ($sock = fopen($url, 'r')) {
	    if ($local = fopen($localfile, 'w')) {
     	        while (!feof($sock)) {
	            $line = fgets($sock, 1024);
		    fputs($local, $line);
		}
		fclose($local);
	    } else {
	        die('could not open local file for writing: '. $file);
	    }
	    fclose($sock);
	} else {
	    die('could not open url: '. $url);
	}
	chmod($localfile, $perms) || die('could not change permissions of '. $file);
	return($localfile);
    }


    /*
     * strip quotes off strings in an array
     */
     
    function strip_quotes($array) {
        foreach (array_keys($array) as $key) {
	    $array[$key] = preg_replace('/^\"(.*)\"$/', '\1', $array[$key]);
	}
	return($array);
    }


    /*
     * convert an array of strings to HTML-safe versions
     */

    function htmlspecialchars_array($array) {
        foreach ($array as $key => $string) {
	    if (is_string($string)) {
                $array[$key] = htmlspecialchars($string);
	    }
        }
	return($array);
    }
}
