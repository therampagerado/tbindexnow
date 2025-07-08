<?php
/**
 * tbindexnow.php
 *
 * IndexNow Integration Module for Thirty Bees & PrestaShop 1.6
 *
 * LICENSE:
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER:
 * Do not edit or add to this file if you wish to upgrade Thirty Bees
 * or PrestaShop to newer versions in the future. If you wish to customize
 * this module for your needs, please refer to https://www.prestashop.com
 * for more information.
 *
 * @author    the.rampage.rado
 * @copyright 2025
 * @license   http://opensource.org/licenses/afl-3.0.php AFL 3.0
 */

if (!defined('_TB_VERSION_')) {
    exit;
}

class TbIndexNow extends Module
{
    protected $config_form = false;
    const QUEUE_TABLE   = 'tbindexnow_queue';
    const HISTORY_TABLE = 'tbindexnow_history';

    public function __construct()
    {
        $this->name                  = 'tbindexnow';
        $this->tab                   = 'seo';
        $this->version               = '1.4.6';
        $this->author                = 'the.rampage.rado';
        $this->need_instance         = 0;
        $this->bootstrap             = true;
        parent::__construct();
        $this->displayName           = $this->l('IndexNow Integration');
        $this->description           = $this->l('Queue URLs and submit in bulk via cron using IndexNow.');
        $this->ps_versions_compliancy= ['min' => '1.6', 'max' => '1.6.999'];
    }

    public function install()
    {
        $sqlQueue = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . self::QUEUE_TABLE . "` (
            `id_queue` INT AUTO_INCREMENT PRIMARY KEY,
            `url` VARCHAR(255) NOT NULL,
            `date_add` DATETIME NOT NULL,
            UNIQUE KEY `url_unique` (`url`)
        ) ENGINE=" . _MYSQL_ENGINE_;
        $sqlHistory = "CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . self::HISTORY_TABLE . "` (
            `id_history` INT AUTO_INCREMENT PRIMARY KEY,
            `url` VARCHAR(255) NOT NULL,
            `status_code` INT NOT NULL,
            `response` TEXT,
            `date_add` DATETIME NOT NULL
        ) ENGINE=" . _MYSQL_ENGINE_;

        return parent::install()
            && Db::getInstance()->execute($sqlQueue)
            && Db::getInstance()->execute($sqlHistory)
            && Configuration::updateValue('INDEXNOW_API_KEY', '')
            && $this->registerHook('actionObjectProductAddAfter')
            && $this->registerHook('actionObjectProductUpdateAfter')
            && $this->registerHook('actionObjectProductDeleteAfter')
            && $this->registerHook('displayBackOfficeHeader')
            && $this->registerHook('header');
    }

    public function uninstall()
    {
        $key = Configuration::get('INDEXNOW_API_KEY');
        if ($key) {
            @unlink(_PS_ROOT_DIR_ . '/' . $key . '.txt');
            Configuration::deleteByName('INDEXNOW_API_KEY');
        }
        Db::getInstance()->execute('DROP TABLE IF EXISTS `'. _DB_PREFIX_ . self::QUEUE_TABLE .'`');
        Db::getInstance()->execute('DROP TABLE IF EXISTS `'. _DB_PREFIX_ . self::HISTORY_TABLE .'`');
        return parent::uninstall();
    }

    private function queueUrl($url)
    {
        Db::getInstance()->insert(
            self::QUEUE_TABLE,
            ['url' => pSQL($url), 'date_add' => date('Y-m-d H:i:s')],
            false, true, Db::REPLACE
        );
    }

    /**
     * Get all shop IDs where a product is assigned
     *
     * @param int $idProduct
     * @return array of ['id_shop' => int]
     */

    public function hookActionObjectProductAddAfter($params)
    {
        // Skip on initial creation in all-shops context; wait for shop-specific save
        if (Shop::getContext() === Shop::CONTEXT_ALL) {
            return;
        }
        /** @var Product $product */
        $product = $params['object'];
        // Only get shops where this product is assigned
        $productShops = Product::getShopsByProduct($product->id);
        foreach ($productShops as $shopRow) {
            $idShop = (int)$shopRow['id_shop'];
            $languages = Language::getLanguages(true, $idShop);
            foreach ($languages as $lang) {
                $url = $this->context->link->getProductLink(
                    $product,
                    null,
                    null,
                    null,
                    (int)$lang['id_lang'],
                    $idShop
                );
                $this->queueUrl($url);
            }
        }
    }

    public function hookActionObjectProductUpdateAfter($params)
    {
        /** @var Product $product */
        $product = $params['object'];
        // Only get shops where this product is assigned
        $productShops = Product::getShopsByProduct($product->id);
        foreach ($productShops as $shopRow) {
            $idShop = (int)$shopRow['id_shop'];
            $languages = Language::getLanguages(true, $idShop);
            foreach ($languages as $lang) {
                $url = $this->context->link->getProductLink(
                    $product,
                    null,
                    null,
                    null,
                    (int)$lang['id_lang'],
                    $idShop
                );
                $this->queueUrl($url);
            }
        }
    }

    public function hookActionObjectProductDeleteAfter($params)
    {
        $this->hookActionObjectProductUpdateAfter($params);
    }

    public function getContent()
    {
        // Intro panel
        $this->context->smarty->assign('module', $this);
        $intro = $this->context->smarty->fetch(
            $this->local_path . 'views/templates/admin/intro.tpl'
        );

        // Bulk delete
        if (Tools::isSubmit('submitBulkdelete' . self::HISTORY_TABLE)) {
            $ids = Tools::getValue(self::HISTORY_TABLE . 'Box');
            if (is_array($ids)) {
                foreach ($ids as $id) {
                    $this->deleteHistory((int)$id);
                }
            }
            Tools::redirectAdmin(
                $this->context->link->getAdminLink('AdminModules', true)
                . '&configure=' . $this->name
            );
        }
        // Single delete
        if (Tools::isSubmit('delete' . self::HISTORY_TABLE)) {
            $id = (int)Tools::getValue('id_history');
            $this->deleteHistory($id);
            Tools::redirectAdmin(
                $this->context->link->getAdminLink('AdminModules', true)
                . '&configure=' . $this->name
            );
        }
        // Save settings
        if (Tools::isSubmit('submit' . $this->name)) {
            return $intro . $this->postProcess() . $this->renderForm() . $this->renderStats();
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        return $intro . $this->renderForm() . $this->renderStats();
    }

    protected function deleteHistory($id)
    {
        if ($id > 0) {
            Db::getInstance()->delete(
                _DB_PREFIX_ . self::HISTORY_TABLE,
                'id_history = ' . $id
            );
        }
    }

    protected function getConfigForm()
    {
        return ['form' => [
            'legend' => ['title' => $this->l('Settings'), 'icon' => 'icon-cogs'],
            'input'  => [[
                'type'     => 'text',
                'label'    => $this->l('IndexNow API Key'),
                'name'     => 'INDEXNOW_API_KEY',
                'size'     => 50,
                'required' => true,
                'hint'     => $this->l(
                    'Enter your API key (8–128 alphanumeric & dashes)'
                )
            ]],
            'submit' => ['title' => $this->l('Save')]
        ]];
    }

    protected function getConfigFormValues()
    {
        return ['INDEXNOW_API_KEY' => Configuration::get('INDEXNOW_API_KEY')];
    }

    protected function renderForm()
    {
        $helper = new HelperForm();
        $helper->show_toolbar            = false;
        $helper->table                   = 'configuration';
        $helper->module                  = $this;
        $helper->default_form_language   = $this->context->language->id;
        $helper->allow_employee_form_lang= Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        $helper->identifier              = $this->identifier;
        $helper->submit_action           = 'submit' . $this->name;
        $helper->currentIndex            =
            $this->context->link->getAdminLink('AdminModules', false)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helper->token                   = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars                = [
            'fields_value' => $this->getConfigFormValues(),
            'languages'    => $this->context->controller->getLanguages(),
            'id_language'  => $this->context->language->id,
        ];
        return $helper->generateForm([$this->getConfigForm()]);
    }

    protected function postProcess()
    {
        $values = $this->getConfigFormValues();
        foreach (array_keys($values) as $key) {
            $val = Tools::getValue($key);
            if ($key === 'INDEXNOW_API_KEY'
                && !preg_match('/^[A-Za-z0-9\-]{8,128}$/', $val)
            ) {
                return $this->displayError(
                    $this->l('Invalid API key format')
                );
            }
            Configuration::updateValue($key, $val);
            @file_put_contents(
                _PS_ROOT_DIR_ . '/' . $val . '.txt',
                $val
            );
        }
        return $this->displayConfirmation(
            $this->l('Settings updated')
        );
    }

    public function hookDisplayBackOfficeHeader()
    {
        if (Tools::getValue('configure') == $this->name) {
            $this->context->controller->addJS(
                $this->_path . 'views/js/back.js'
            );
            $this->context->controller->addCSS(
                $this->_path . 'views/css/back.css'
            );
        }
    }

    public function hookHeader()
    {
        $this->context->controller->addJS(
            $this->_path . 'views/js/front.js'
        );
        $this->context->controller->addCSS(
            $this->_path . 'views/css/front.css'
        );
    }

    protected function renderStats()
    {
        $db = Db::getInstance();
        $pendingTotal = (int)$db->getValue(
            'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . self::QUEUE_TABLE . '`'
        );

        // Fetch active shop IDs and details
        $shops = Shop::getShops(true, null, false);

        $cronHtml  = '<div class="panel"><h4>' . $this->l('Cron URLs per Shop') . '</h4><ul>';
        foreach ($shops as $shop) {
            $idShop   = (int)$shop['id_shop'];
            $shopName = htmlspecialchars($shop['name']);
            $cronLink = $this->context->link->getModuleLink(
                $this->name,
                'cron',
                ['key' => Configuration::get('INDEXNOW_API_KEY')],
                true,    // ssl
                null,    // id_lang
                $idShop  // id_shop
            );
            // every 6 hours
            $cronHtml .= '<li><code>0 */6 * * * curl ' 
                . htmlspecialchars($cronLink) 
                . '</code> (' 
                . $shopName 
                . ')</li>';
        }
        $cronHtml .= '</ul>'
            . '<div class="alert alert-warning">' . $this->l('Please set up a separate cron job for each domain using the URL(s) above. It is recommended to run the cronjobs every 6 to 12 hours depending on your crawl budget.') . '</div>'
            . '<h4>' . $this->l('Total Pending URLs') . ': ' . $pendingTotal . '</h4>'
            . '</div>';
            
        // Submission history panel remains unchanged
        $rows = $db->executeS(
            'SELECT id_history, url, status_code, date_add FROM `' . _DB_PREFIX_ . self::HISTORY_TABLE . '` ORDER BY date_add DESC LIMIT 50'
        );
        $helperList = new HelperList();
        $helperList->title       = $this->l('Submission History');
        $helperList->table       = self::HISTORY_TABLE;
        $helperList->identifier  = 'id_history';
        $helperList->token       = Tools::getAdminTokenLite('AdminModules');
        $helperList->actions     = ['delete'];
        $helperList->currentIndex=
            $this->context->link->getAdminLink('AdminModules', true)
            . '&configure=' . $this->name
            . '&tab_module=' . $this->tab
            . '&module_name=' . $this->name;
        $helperList->bulk_actions = [
            'delete' => [
                'text'    => $this->l('Delete selected'),
                'icon'    => 'icon-trash',
                'confirm' => $this->l('Delete selected items?'),
            ],
        ];
        $columns = [
            'date_add'    => ['title' => $this->l('Date'), 'type' => 'datetime'],
            'url'         => ['title' => $this->l('URL')],
            'status_code' => ['title' => $this->l('Status'), 'type' => 'int'],
        ];
        $tableHtml = '<div class="panel">' . $helperList->generateList($rows, $columns) . '</div>';

        return $cronHtml . $tableHtml;
    }
}
