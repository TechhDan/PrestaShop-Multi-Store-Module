<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_.'multistoreapp/classes/MultiStoreAppClass.php';

class MultiStoreApp extends Module
{
    public function __construct()
    {
        $this->name = 'multistoreapp';
        $this->author = 'TechDesign';
        $this->version = '1.0.0';
        $this->tab = 'front_office_features';
        $this->need_instance = 0;

        $this->bootstrap = true;
        parent::__construct();

        // Add table to associated shop
        Shop::addTableAssociation('multistoreapp', array('type' => 'shop'));

        $this->displayName = $this->l('Multi Store App');
        $this->description = $this->l('Example of a multi-store compatible module.');
        $this->ps_versions_compliancy = array('min' => '1.7.4.0', 'max' => _PS_VERSION_);
    }

    public function install()
    {
        $db = true;

        $db &= Db::getInstance()->execute('
                CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'multistoreapp` (
                `id_multistoreapp` INT UNSIGNED NOT NULL AUTO_INCREMENT,
                PRIMARY KEY (`id_multistoreapp`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 ;'
        );

        $db &= Db::getInstance()->execute('
                CREATE TABLE IF NOT EXISTS `' . _DB_PREFIX_ . 'multistoreapp_shop` (
                `id_multistoreapp` INT(10) UNSIGNED NOT NULL,
                `id_shop` INT(10) UNSIGNED NOT NULL,
                PRIMARY KEY (`id_multistoreapp`, `id_shop`)
            ) ENGINE=' . _MYSQL_ENGINE_ . ' DEFAULT CHARSET=utf8 ;'
        );

        $db &= Db::getInstance()->execute('
                CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'multistoreapp_lang` (
                `id_multistoreapp` INT UNSIGNED NOT NULL,
                `id_shop` INT(10) UNSIGNED NOT NULL,
                `id_lang` INT(10) UNSIGNED NOT NULL ,
                `text` text NOT NULL,
                PRIMARY KEY (`id_multistoreapp`, `id_lang`, `id_shop`)
            ) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8 ;'
        );

        return $db &&
            parent::install() &&
            $this->registerHook('displayHome') &&
            $this->registerHook('actionShopDataDuplication') &&
            // Configuration works automatically with user
            Configuration::updateValue('MULTISTOREAPP_USER', null);
    }

    public function uninstall()
    {
        $db = true;
        $db &= Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'multistoreapp`');
        $db &= Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'multistoreapp_shop`');
        $db &= Db::getInstance()->execute('DROP TABLE IF EXISTS `' . _DB_PREFIX_ . 'multistoreapp_lang`');

        return $db &&
            parent::uninstall() &&
            Configuration::deleteByName('MULTISTOREAPP_SANDBOX');
    }

    public function hookDisplayHome($params)
    {
        $sql = 'SELECT * FROM `'._DB_PREFIX_.'multistoreapp_lang`
            WHERE `id_lang` = '.(int)$this->context->language->id.
            ' AND  `id_shop` = '.(int)$this->context->shop->id;

        $this->context->smarty->assign(array(
            'multistoreapp' => Db::getInstance()->getRow($sql),
        ));

        return $this->display(__FILE__, 'multistoreapp.tpl');
    }

    public function getContent()
    {
        $html = '';
        if (Tools::isSubmit('saveps_multistoreapp')) {
            Configuration::updateValue('MULTISTOREAPP_USER', Tools::getValue('user'));
            if (!Tools::getValue(
                    'text_'.(int) Configuration::get('PS_LANG_DEFAULT'),
                    false
                )
            ) {
                $html = $this->displayError($this->l('Please fill out all fields.'));
            } else {
                if ($this->processSaveCustomText()) {
                    $html = $this->displayConfirmation($this->l('Settings updated'));    
                } else {
                    $html = $this->displayError($this->l('An error occurred on saving.'));
                }
            }
        }
        return $html . $this->renderForm();
    }

    public function processSaveCustomText()
    {
        $shops = Tools::getValue(
            'checkBoxShopAsso_configuration',
            array($this->context->shop->id)
        );
        $text = array();
        $languages = Language::getLanguages(false);

        foreach ($languages as $lang) {
            $text[$lang['id_lang']] = Tools::getValue('text_'.$lang['id_lang']);
        }

        $saved = true;
        foreach ($shops as $shop) {
            Shop::setContext(Shop::CONTEXT_SHOP, $shop);
            $multistoreapp = new MultiStoreAppClass(Tools::getValue('id_multistoreapp', 1));
            $multistoreapp->text = $text;
            $saved &= $multistoreapp->save();
        }

        return $saved;
    }

    protected function renderForm()
    {
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');
        $fields_form = array(
            'tinymce' => true,
            'legend' => array(
                'title' => $this->l('Custom Title')
            ),
            'input' => array(
                'id_multistoreapp' => array(
                    'type' => 'hidden',
                    'name' => 'id_multistoreapp'
                ),
                'multistoreapp_user' => array(
                    'type' => 'text',
                    'prefix' => '<i class="icon icon-user"></i>',
                    'label' => $this->l('User'),
                    'name' => 'user',
                    'desc' => $this->l('A sample text.'),
                    'class' => 'col-lg-6',
                    'required' => true
                ),
                'content' => array(
                    'type' => 'textarea',
                    'label' => $this->l('Custom Label'),
                    'lang' => true,
                    'name' => 'text',
                    'cols' => 40,
                    'rows' => 10,
                    'class' => 'rte',
                    'autoload_rte' => true,
                ),
            ),
            'submit' => array(
                'title' => $this->l('Save')
            ),
            'buttons' => array(
                array(
                    'href' => AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'),
                    'title' => $this->l('Back to list'),
                    'icon' => 'process-icon-back'
                )
            )
        );

        if (Shop::isFeatureActive() && Tools::getValue('id_multistoreapp') == false) {
            $fields_form['input'][] = array(
                'type' => 'shop',
                'label' => $this->l('Shop association'),
                'name' => 'checkBoxShopAsso_theme'
            );
        }

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = 'ps_multistoreapp';
        $helper->identifier = $this->identifier;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        foreach (Language::getLanguages(false) as $lang) {
            $helper->languages[] = array(
                'id_lang' => $lang['id_lang'],
                'iso_code' => $lang['iso_code'],
                'name' => $lang['name'],
                'is_default' => ($default_lang == $lang['id_lang'] ? 1 : 0)
            );
        }

        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;
        $helper->toolbar_scroll = true;
        $helper->title = $this->displayName;
        $helper->submit_action = 'saveps_multistoreapp';

        $helper->fields_value = $this->getFormValues();
        $helper->fields_value['user'] = Configuration::get('MULTISTOREAPP_USER');

        return $helper->generateForm(array(array('form' => $fields_form)));
    }

    public function getFormValues()
    {
        $fields_value = array();
        $idShop = $this->context->shop->id;
        $idInfo = MultiStoreAppClass::getCustomTextIdByShop($idShop);

        Shop::setContext(Shop::CONTEXT_SHOP, $idShop);
        $info = new MultiStoreAppClass((int) $idInfo);

        $fields_value['text'] = $info->text;
        $fields_value['id_multistoreapp'] = $idInfo;

        return $fields_value;
    }

    /**
     * Add CustomText when adding a new Shop
     *
     * @param array $params
     */
    public function hookActionShopDataDuplication($params)
    {
        if ($infoId = MultiStoreAppClass::getCustomTextIdByShop($params['old_id_shop'])) {
            Shop::setContext(Shop::CONTEXT_SHOP, $params['old_id_shop']);
            $oldInfo = new MultiStoreAppClass($infoId);

            Shop::setContext(Shop::CONTEXT_SHOP, $params['new_id_shop']);
            $newInfo = new MultiStoreAppClass($infoId, null, $params['new_id_shop']);
            $newInfo->text = $oldInfo->text;

            $newInfo->save();
        }
    }
}
