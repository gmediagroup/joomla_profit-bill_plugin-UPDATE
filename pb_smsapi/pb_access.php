<?php

require_once '_init_joomla.php';
//ini_set('error_reporting', E_ALL);
require_once 'pb_models.php';


$access = false;

if (!empty($_POST)) {
    $code = isset($_POST['code']) ? $_POST['code'] : '';
    $articleId = isset($_POST['article_id']) ? (int)$_POST['article_id'] : 0;


    $codesModel = PBDBModel::factory('PBCodesModel', PBgetPluginParams());
    if ($access = $codesModel->isValid($code)) {
        // открываем доступ
        PBAccessModel::allow($articleId);

        // уменьшаем кол-во просмотров
        $codesModel->decrementUseNum($code);
    }

    
    if ('XMLHttpRequest' == @$_SERVER['HTTP_X_REQUESTED_WITH']) {
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(array('access' => $access));
        exit;
    } else {
        // на случай если запрос придет не ajax'ом
        if ($access) {
            header('Location: ' . @$_SERVER['HTTP_REFERER']);
            exit;
        } else {
            header('Content-Type: text/html; charset=UTF-8');
            echo 'Вы ввели неверный код. Вернитесь <a href="' . @$_SERVER['HTTP_REFERER'] . '">обратно</a>
                и попробуйте ввести другой.';
        }
    }
}


