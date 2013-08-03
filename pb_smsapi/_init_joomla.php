<?php

define('_JEXEC', 1);
define('DS', DIRECTORY_SEPARATOR);
define('JPATH_BASE', str_replace('/plugins/content/pb_smsapi', '', dirname(__FILE__)));

require_once JPATH_BASE .DS.'includes'.DS.'defines.php';
require_once JPATH_BASE .DS.'includes'.DS.'framework.php';

$site = JFactory::getApplication('site');
$site->initialise();