<?php

/**
 * Защита от прямого доступа к файлу
 */

defined('_JEXEC') or die('Restricted access');

/**
 * Подключаем главный плагин Joomla
 */
jimport('joomla.plugin.plugin');

require_once 'pb_models.php';

class plgContentPB_smsapi extends JPlugin
{

    /**
     * Флаг, указывающий, нужен ли попап доступа
     *
     * @var boolean
     */
    protected $_initDialog = false;


    /**
     * @param object $subject Объект наблюдения
     * @param object $config  Объект, который содержит параметры
     */
    public function __construct(&$subject, $config = array())
    {
        parent::__construct($subject, $config);
        $this->loadLanguage();

        ini_set('error_reporting', E_ALL);
    }

    /**
     * @param object $context Объект, наблюдения
     * @param object $article Объект, который содержит параметры материала
     * @param object $params Объект, который содержит параметры
     * @param int $page номер страницы
     */
    public function onContentBeforeDisplay($context, &$article, &$params, $page=0)
    {
        if (@$article->text) {
            $content = $article->text;
            $contentModel = PBDBModel::factory('PBContentModel', $this->params);

            if (!PBAccessModel::isAllowed($article->id)) {
                $this->_initDialog = true;
                $this->_initDialogScripts();

                $article->text = $contentModel->hide($content, $article->id);
            } else {
                $article->text = $contentModel->show($content);
            }
        }
    }

    public function onAfterRender()
    {
        if ($this->_initDialog) {
            $this->_initDialogHtml();
        }
    }

    protected function _initDialogScripts()
    {
        static $inited = false;

        if (!$inited) {
            $document = &JFactory::getDocument();
            $document->addStyleSheet('plugins/content/pb_smsapi/css/jquery-ui-1.8.14.custom.css');
            $document->addStyleSheet('plugins/content/pb_smsapi/css/pb_smsapi.css');

            $document->addScript('plugins/content/pb_smsapi/js/jquery-1.5.1.min.js');
            $document->addScript('plugins/content/pb_smsapi/js/jquery-ui-1.8.14.custom.min.js');
            $document->addScript('plugins/content/pb_smsapi/js/pb_smsapi.js');

            $tarifs = trim(@file_get_contents('http://profit-bill.com/api/get_api_tariffs?format=json&smsapi_id='.$this->params->get('smsapi_id').'&secret='.$this->params->get('smsapi_secret')));

			if($tarifs != ''){
				$document->addScriptDeclaration("var JSON_TARIFS = '{$tarifs}'");
			}else{
				//$document->addScriptDeclaration(sprintf('', ''));
			}

            
            $inited = true;
        }
    }

    protected function _initDialogHtml()
    {
        $body = JResponse::getBody();
        $dialog = file_get_contents('plugins/content/pb_smsapi/dialog.html');
        $content = str_replace('</body>', $dialog . '</body>', $body);

        JResponse::setBody($content);
    }

}