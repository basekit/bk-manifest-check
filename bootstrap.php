<?php
// Define path to application directory
defined('APPLICATION_PATH')
    || define('APPLICATION_PATH', realpath(dirname(__FILE__)));
    
// Ensure library/ is on include_path
set_include_path(implode(PATH_SEPARATOR, array(
    realpath(APPLICATION_PATH . '/vendor/symfony/symfony/src'),
    get_include_path(),
)));
require_once './vendor/symfony/symfony/src/Symfony/Component/ClassLoader/UniversalClassLoader.php';
use Symfony\Component\ClassLoader\UniversalClassLoader;
// Register autoloader
$loader = new UniversalClassLoader();
$loader->registerNamespaces(array(
	'Symfony' => realpath(APPLICATION_PATH . '/vendor/symfony/symfony/src'),
	'BaseKit' => realpath(APPLICATION_PATH . '/')
));
$loader->register();