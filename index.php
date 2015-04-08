<?php
/**
 * Created by PhpStorm.
 * User: ydenyshchenk
 * Date: 27.03.15
 * Time: 16:42
 */

header('Content-Type: text/html; charset=utf-8');
ini_set('display_errors', 1);

define('DS', DIRECTORY_SEPARATOR);
define('PS', PATH_SEPARATOR);
define('BP', dirname(__FILE__));

$configPath = BP . DS . 'etc' . DS . 'config.php';
if (file_exists($configPath)) {
    require_once $configPath;
}

define('BU', $config['base_url']);

$paths = array();
$paths[] = BP . DS . 'app' . DS . 'code' . DS . 'local';
$paths[] = BP . DS . 'app' . DS . 'code' . DS . 'community';
$paths[] = BP . DS . 'app' . DS . 'code' . DS . 'core';
$paths[] = BP . DS . 'lib';

$appPath = implode(PS, $paths);
$dir = $appPath . PS;
set_include_path($appPath);
include_once "Varien/Autoload.php";
Varien_Autoload::register();


if (!file_exists($configPath)) {
    $install = new Install();
    $install->run();
    exit();
}

$_request = explode('/', trim($_SERVER['REQUEST_URI'], '/'));
switch(current($_request)) {
    case 'db': {
        /** @var Diff_Db $diffDb */
        $diffDb = new Diff_Db();
        $diffDb->run();
        break;
    }
    case 'file': {
        /** @var Diff_File $diffFile */
        $diffFile = new Diff_File();
        $diffFile->run();
        break;
    }
    case 'triggers': {
        /** @var Diff_Triggers $diffFile */
        $diffTriggers = new Diff_Triggers();
        $diffTriggers->run();
        break;
    }
    case 'logs': {
        /** @var Diff_Logs $diffLogs */
        $diffLogs = new Diff_Logs();
        $diffLogs->run();
        break;
    }
    default: {
        /** @var Diff $diff */
        $diff = new Diff();
        $diff->run();
    }
}