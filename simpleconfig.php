<?php

/*
 * peerlist - an app that shows peer info for your IX
 * (C) 2006 Martijn Bakker <martijn@insecure.nl>
 * simpleconfig.php - simple config generation based on template
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
  version => '0.3-rc1',
);


$xtpl = new xtemplate("templates/peerlist.xtpl");
$toolbox = new toolbox();

$routerid = $_GET['router'];

if (!isset($config[$routerid]))
	die('router not found');

$remotepeer = $_GET['remotepeer'];

if (!preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $remotepeer) && !preg_match('/^((([0-9A-Fa-f]{1,4}:){7}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}:[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){5}:([0-9A-Fa-f]{1,4}:)?[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){4}:([0-9A-Fa-f]{1,4}:){0,2}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){3}:([0-9A-Fa-f]{1,4}:){0,3}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){2}:([0-9A-Fa-f]{1,4}:){0,4}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){6}((\b((25[0-5])|(1\d{2})|(2[0 4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(([0-9A-Fa-f]{1,4}:){0,5}:((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2
}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|(::([0-9A-Fa-f]{1,4}:){0,5}((\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b)\.){3}(\b((25[0-5])|(1\d{2})|(2[0-4]\d)|(\d{1,2}))\b))|([0-9A-Fa-f]{1,4}::([0-9A-Fa-f]{1,4}:){0,5}[0-9A-Fa-f]{1,4})|(::([0-9A-Fa-f]{1,4}:){0,6}[0-9A-Fa-f]{1,4})|(([0-9A-Fa-f]{1,4}:){1,7}:))$/', $remotepeer))
	die('invalid peer');

$config = $config[$routerid];

if (is_file('routers/'. $config['router']['module']. '.class.php'))
            include_once('routers/'. $config['router']['module']. '.class.php');
        else
            die('router type not available');

        if (is_file('exchanges/'. $config['exchange']['module']. '.class.php'))
            include_once('exchanges/'. $config['exchange']['module']. '.class.php');
        else
            die('exchange module not available!');

$exchange = new $config['exchange']['module']($config['exchange']);
$router = new $config['router']['module']($config['router']);


$xtpl = new xtemplate("templates/simpleconfig_". $routerid. ".xtpl");
$peer = $exchange->getpeerbyip($remotepeer);

$xtpl->assign('config', $config);
$xtpl->assign('peer', $peer);

$xtpl->parse('page');
echo $xtpl->text('page');
