<?php

/**
 * Защита от прямого доступа к файлу
 */

defined('_JEXEC') or die('Restricted access');

/**
 * Параметры плагина нужно получать не только в самом плагине. так что вот такой вот хак...
 *
 * @return JRegistry
 */
function PBgetPluginParams()
{
    $database = JFactory::getDBO();

    static $registry = null;

    if (is_null($registry)) {
        $plugin = &JPluginHelper::getPlugin('content', 'pb_smsapi');

        $registry = new JRegistry;
        $registry->loadJSON($plugin->params);
    }

    return $registry;
}

/**
 * Обертка для сесии
 */
abstract class PBAccessModel
{

    const sessionNamespace = 'PBAccess';

    static public function isAllowed($id)
    {
        $access = false;

        if (JFactory::getSession()->has($id, self::sessionNamespace)) {
            $access = JFactory::getSession()->get($id, false, self::sessionNamespace);
            // только одно использование
            JFactory::getSession()->clear($id, self::sessionNamespace);
        }

        return $access;
    }

    static public function allow($id)
    {
        JFactory::getSession()->set($id, true, self::sessionNamespace);
    }

}

abstract class PBDBModel
{

    static protected $_instances = array();

    /**
     *
     * @var JDatabase
     */
    protected $_database;

    /**
     *  Параметры плагина
     *
     * @var JRegistry
     */
    protected $_pluginParams;

    public function __construct(JDatabase $database, JRegistry $pluginParams)
    {
        $this->_pluginParams = $pluginParams;
        $this->_database = $database;

        $this->init();
    }

    abstract public function init();

    /**
     * Это gateway к таблице, и он будет singleton
     * 
     * @return PBModel
     */

    /**
     * Фабрика создает экземпляры шлюзов (gateway) к таблицам
     * 
     * @param string $model
     * @param JRegistry $pluginParams
     * @return type
     */
    static public function factory($model, JRegistry $pluginParams)
    {
        $model = (string)$model;

        if (!array_key_exists($model, self::$_instances)) {
            self::$_instances[$model] = new $model(JFactory::getDBO(), $pluginParams);
        }

        return self::$_instances[$model];
    }
    
}

class PBCodesModel extends PBDBModel
{

    protected $_codeLength = 8;

    public function init()
    {
        $query = <<<SCHEMA
CREATE TABLE IF NOT EXISTS `pb_codes` (
`code` CHAR( $this->_codeLength ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`created_at` DATETIME NOT NULL ,
`use_num` SMALLINT UNSIGNED NOT NULL DEFAULT '0',
PRIMARY KEY ( `code` )
) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci;
SCHEMA;

        $this->_database->setQuery($query);
        $this->_database->query();
    }

    public function add()
    {
        do {
            $code = substr(md5(uniqid(rand())), 0, $this->_codeLength);

            if (!$this->doesExist($code)) {
                $query = sprintf('
                    INSERT INTO `pb_codes`
                    SET `code` = %s, `created_at` = %s, `use_num` = %d',
                    $this->_database->quote($code), 
                    $this->_database->quote(date('Y-m-d H:i:s')),
                    (int)$this->_pluginParams->get('use_num'));
                $this->_database->setQuery($query);
                $this->_database->query();
                return $code;
            }
        } while (true);
    }

    public function doesExist($code)
    {
        $query = 'SELECT COUNT(*) FROM `pb_codes` WHERE `code` = ' . $this->_database->quote($code);
        $this->_database->setQuery($query);
        return (bool)$this->_database->loadResult();
    }

    public function isValid($code)
    {
        $isValid = true;


        $query = 'SELECT `created_at`, `use_num` FROM `pb_codes` WHERE `code` = '
               . $this->_database->quote($code);
        $this->_database->setQuery($query);
        $result = $this->_database->loadAssoc();

        if (is_null($result)) {
            $isValid = false;
        } else {
            // $createdAt - дата создания кода
            // $leftUseNum - кол-во оставшихся просмотров
            list($createdAt, $leftUseNum) = array_values($result);

            // истек ли срок жизни?
            $lifetime = (int)$this->_pluginParams->get('lifetime'); // в минутах
            // 0 - значит временем не ограничены
            if (($lifetime > 0) && (time() > strtotime($createdAt) + $lifetime * 60)) {
                $isValid = false;
            }

            // остались ли просмотры?
            $useNum = (int)$this->_pluginParams->get('use_num'); // в разах
            if ($useNum > 0 && 0 == $leftUseNum) {
                $isValid = false;
            }
        }

        return $isValid;
    }

    public function decrementUseNum($code)
    {
        $query = 'UPDATE `pb_codes` SET `use_num` = IF(`use_num`, `use_num` - 1, 0) WHERE `code` = '
               . $this->_database->quote($code);
        $this->_database->setQuery($query);
        $this->_database->query();
    }

}

class PBSMSModel extends PBDBModel
{

    const TARIFFS_URL = 'http://profit-bill.com/api/get_api_tariffs?smsapi_id=%d&secret=%s&format=json';

    public function init()
    {
        $query = <<<SCHEMA
CREATE TABLE IF NOT EXISTS `pb_sms` (
`id` INT( 11 ) NOT NULL AUTO_INCREMENT ,
`sms_id` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`from` VARCHAR( 30 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`date` DATETIME NOT NULL ,
`message` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`cost_rur` DOUBLE( 10,2 ) UNSIGNED NOT NULL ,
`country` CHAR( 2 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`operator` VARCHAR( 255 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
`short_number` VARCHAR( 40 ) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL ,
PRIMARY KEY ( `id` )
) ENGINE = MYISAM CHARACTER SET utf8 COLLATE utf8_general_ci;
SCHEMA;

        $this->_database->setQuery($query);
        $this->_database->query();
    }

    public function add($smsId, $from, $date, $message, $costRur, $country, $operator, $shortNumber)
    {
        $query = 'INSERT INTO `pb_sms` SET
            `sms_id` = ' . $this->_database->quote($smsId) . ',
            `from` = ' . $this->_database->quote($from) . ',
            `date` = ' . $this->_database->quote($date) . ',
            `message` = ' . $this->_database->quote($message) . ',
            `cost_rur` = ' . $this->_database->quote($costRur) . ',
            `country` = ' . $this->_database->quote($country) . ',
            `operator` = ' . $this->_database->quote($operator) . ',
            `short_number` = ' . $this->_database->quote($shortNumber) . '
            ';
        
        $this->_database->setQuery($query);
        $this->_database->query();
    }

    public function isValid($smsId, $smsapiSecret)
    {
        return $smsapiSecret == md5($this->_pluginParams->get('smsapi_secret') . $smsId);
    }

}


class PBContentModel extends PBDBModel
{

    public function init()
    { }

    public function hide($content, $id)
    {
        $tag = $this->_pluginParams->get('tag');
        $text = $this->_pluginParams->get('text');
		
		if(strpos($content, '[/'. $tag . ']') === false) return $content;

        $content = preg_replace("#\[" . preg_quote($tag) . "\].*?\[\/" .preg_quote($tag) . "\]#isu", '<a data-id="' . $id . '" class="pb_smsapi_hidden_content_trigger" href="#">' . $text . '</a>', $content);

        return $content;
    }

    public function show($content)
    {
        $tag = $this->_pluginParams->get('tag');
        $content = str_ireplace('[' . $tag . ']', '', $content);
		$content = str_ireplace('[/' . $tag . ']', '', $content);
        return $content;
    }

}