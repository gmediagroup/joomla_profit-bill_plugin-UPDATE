<?php

require_once '_init_joomla.php';
//ini_set('error_reporting', E_ALL);
require_once 'pb_models.php';


$smsId = isset($_GET['id']) ? $_GET['id'] : '';
$smsapiSecret = isset($_GET['key']) ? $_GET['key'] : '';

$smsModel = PBDBModel::factory('PBSMSModel', PBgetPluginParams());
$codesModel = PBDBModel::factory('PBCodesModel', PBgetPluginParams());

if ($smsModel->isValid($smsId, $smsapiSecret)) {
    $from = isset($_GET['from']) ? $_GET['from'] : '';
    $date = isset($_GET['date']) ? $_GET['date'] : '';
    $message = isset($_GET['message']) ? $_GET['message'] : '';
    $costRur = isset($_GET['cost_rur']) ? $_GET['cost_rur'] : '';
    $country = isset($_GET['country']) ? $_GET['country'] : '';
    $operator = isset($_GET['operator']) ? $_GET['operator'] : '';
    $shortNumber = isset($_GET['short_number']) ? $_GET['short_number'] : '';

    $smsModel->add($smsId, $from, $date, $message, $costRur, $country, $operator, $shortNumber);

    $code = $codesModel->add();
    
    header('Content-Type: text/html; charset=UTF-8');
    echo sprintf("reply\nВаш код: %s", $code);
} else {
    echo 'Request failed...';
}