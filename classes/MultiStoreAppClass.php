<?php

class MultiStoreAppClass extends ObjectModel
{
    public $id_multistoreapp;
    public $text;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'multistoreapp',
        'primary' => 'id_multistoreapp',
        'multilang' => true,
        'multilang_shop' => true,
        'fields' => array(
            'id_multistoreapp' => array(
                'type' => self::TYPE_NOTHING,
                'validate' => 'isUnsignedId'
            ),
            'text' => array(
                'type' => self::TYPE_HTML,
                'lang' => true,
                'validate' => 'isCleanHtml',
                'required' => true
            ),
        )
    );

    /**
     * Return the CustomText ID By shop ID
     * 
     * @param int $shopId
     * @return bool|int
     */
    public static function getCustomTextIdByShop($shopId)
    {
        $sql = 'SELECT m.`id_multistoreapp` FROM `' . _DB_PREFIX_ . 'multistoreapp` m
        LEFT JOIN `' . _DB_PREFIX_ . 'multistoreapp_shop` msh ON msh.`id_multistoreapp` = m.`id_multistoreapp`
        WHERE msh.`id_shop` = ' . (int) $shopId;
        
        if ($result = Db::getInstance()->executeS($sql)) {
            return (int) reset($result)['id_multistoreapp'];
        }

        return false;
    }
}
