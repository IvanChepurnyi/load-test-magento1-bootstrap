<?php

$shellDirectory = realpath(dirname($_SERVER['SCRIPT_FILENAME']));

require_once $shellDirectory . DIRECTORY_SEPARATOR . 'abstract.php';
require_once dirname($shellDirectory) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'Mage.php';

$shell = new EcomDev_BenchmarkDataTool_Shell();
$shell->run();
