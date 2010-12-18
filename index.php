<?php

/*
 * peerlist - an app that shows peer info for your IX
 * (C) 2006i-2009 Martijn Bakker <martijn@mactijn.eu>
 */

define('BASEDIR', getcwd() . DIRECTORY_SEPARATOR);

if (!is_file('config.inc.php')) {
  echo "no config file found! Please create config.inc.php\n";
  exit(1);
}

include_once('config.inc.php');
include_once('xtemplate.class.php');
include_once('toolbox.class.php');

$application = array(
  name => 'peerlist',
  version => '0.3-rc1'
);


$xtpl = new xtemplate("templates/peerlist.xtpl");
$toolbox = new toolbox();

$xtpl->assign('application', $toolbox->htmlspecialchars_array($application));
$xtpl->assign('getoptions', $toolbox->htmlspecialchars_array($_GET));

foreach ($config as $routerid => $routerconfig) {

    if (is_array($routerconfig) && $routerconfig['common_name'] != '') {
        $routerconfig['id'] = $routerid;
    
        if ($_GET['router'] == "$routerid" && $_GET['router'] != '') {
            $routerconfig['selected'] = 'selected';
        }

        if (is_file('routers/'. $routerconfig['router']['module']. '.class.php'))
            include_once('routers/'. $routerconfig['router']['module']. '.class.php');
        else
            die('router type not available');

        if (is_file('exchanges/'. $routerconfig['exchange']['module']. '.class.php'))
            include_once('exchanges/'. $routerconfig['exchange']['module']. '.class.php');
        else
            die('exchange module not available!');

        $exchangeobject = new $routerconfig['exchange']['module']($routerconfig['exchange']);

        $xtpl->assign('exchange', $exchangeobject->exchange_short);
        $xtpl->assign('router', $toolbox->htmlspecialchars_array($routerconfig));
        $xtpl->parse('page.header.routerselection.router');
    }
}

$xtpl->parse('page.header.routerselection');

if ($_GET['header'] != 'no') {
    $xtpl->parse('page.header');
}

if ($config[$_GET['router']]['common_name'] != '') {

    $config = $config[$_GET['router']];
    $xtpl->assign('selectedrouter', $toolbox->htmlspecialchars_array($config));

    if (is_file('routers/'. $config['router']['module']. '.class.php'))
        include_once('routers/'. $config['router']['module']. '.class.php');
    else
        die('router type not available');

    if (is_file('exchanges/'. $config['exchange']['module']. '.class.php'))
        include_once('exchanges/'. $config['exchange']['module']. '.class.php');
    else
        die('exchange module not available!');

    $routerobject = new $config['router']['module']($config['router']);
    $exchangeobject = new $config['exchange']['module']($config['exchange']);

    $xtpl->assign('exchange', $exchangeobject->exchange);

    if ($_GET['menubar'] != 'no') {
        $xtpl->parse('page.menubar');
    }

    $peer_columns = array();

    foreach ($exchangeobject->getcolumns() as $key => $val) {
      $peer_columns["ix_$val"] = $val;
    }
    
    foreach ($routerobject->getcolumns() as $key => $val) {
      $peer_columns["router_$val"] = $val;
    }

    foreach ($peer_columns as $column) {
        $xtpl->assign('column', array('name' => htmlspecialchars($column)));
        $xtpl->parse('page.peers.header.column');
    }
    $xtpl->parse('page.peers.header');

    foreach ($exchangeobject->getpeers() as $peer) {

        if ($exchangeobject->localasn != $peer[$exchangeobject->getascolumnname()]) {

	    $is_peer = array();

	    foreach ($peer as $key => $val) {
	        $is_peer["ix_$key"] = $val;
	    }

	    foreach ($routerobject->getpeerbyunique($peer[$exchangeobject->getuniquecolumnname()]) as $key => $val) {
	        $is_peer["router_$key"] = $val;
	    }

		$shortstatus = 'nopeer';
	    if ($is_peer['router__shortstatus'] != '') $shortstatus = $is_peer['router__shortstatus'];
        if ($shortstatus == 'down' && isset($is_peer['ix__shortstatus'])) $shortstatus = $is_peer['ix__shortstatus'];

		$is_peer['shortstatus'] = $shortstatus;

            if (($_GET['show'] == '' || $_GET['show'] == $is_peer['shortstatus'] ) &&
	        ($_GET['as'] == '' || $_GET['as'] == $is_peer["ix_".$exchangeobject->getascolumnname()])) {


                $xtpl->assign('peer', $toolbox->htmlspecialchars_array($is_peer));
	    
                foreach ($peer_columns as $columnname => $real_columnname) {
                    $xtpl->assign('table', array('column' => htmlspecialchars($is_peer[$columnname])));
                    $xtpl->parse('page.peers.peer.column');
                }
                $xtpl->parse('page.peers.peer');
	    }
        }
    }

    $xtpl->parse('page.peers');
    if ($_GET['footer'] != 'no') {
      $xtpl->parse('page.footer');
    }
}



$xtpl->parse('page');
echo $xtpl->text('page');
