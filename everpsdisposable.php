<?php
/**
 * Project : everpsdisposable
 * @author Team Ever
 * @copyright Team Ever
 * @license   Tous droits réservés / Le droit d'auteur s'applique (All rights reserved / French copyright law applies)
 * @link https://www.team-ever.com
 */

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once _PS_MODULE_DIR_.'everpsdisposable/models/EverPsDisposableClass.php';

class EverPsDisposable extends Module
{
    private $html;
    private $postErrors = array();
    private $postSuccess = array();

    public function __construct()
    {
        $this->name = 'everpsdisposable';
        $this->tab = 'administration';
        $this->version = '1.1.4';
        $this->author = 'Team Ever';
        $this->need_instance = 0;
        $this->bootstrap = true;
        parent::__construct();
        $this->displayName = $this->l('Ever PS Disposable emails');
        $this->description = $this->l('Ban disposable emails from your shop');
        $this->confirmUninstall = $this->l('');
        $this->isSeven = Tools::version_compare(_PS_VERSION_, '1.7', '>=') ? true : false;
        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Don't forget to create update methods if needed:
     * http://doc.prestashop.com/display/PS16/Enabling+the+Auto-Update
     */
    public function install()
    {
        Configuration::updateValue('DELETE_DISPOSABLE', false);
        Configuration::updateValue('ONLY_SPAM', false);
        include(dirname(__FILE__).'/sql/install.php');
        return parent::install()
            && $this->registerHook('actionCustomerAccountAdd')
            && $this->registerHook('actionCustomerAccountUpdate')
            && $this->installModuleTab(
                'AdminEverPsDisposable',
                'AdminParentCustomer',
                $this->l('Disposable emails')
            );
    }

    public function uninstall()
    {
        Configuration::deleteByName('DELETE_DISPOSABLE');
        Configuration::deleteByName('ONLY_SPAM');
        include(dirname(__FILE__).'/sql/uninstall.php');
        return parent::uninstall()
            && $this->uninstallModuleTab('AdminEverPsDisposable');
    }

    private function installModuleTab($tabClass, $parent, $tabName)
    {
        $tab = new Tab();
        $tab->active = 1;
        $tab->class_name = $tabClass;
        $tab->id_parent = (int)Tab::getIdFromClassName($parent);
        $tab->position = Tab::getNewLastPosition($tab->id_parent);
        $tab->module = $this->name;
        foreach (Language::getLanguages(false) as $lang) {
            $tab->name[(int)$lang['id_lang']] = $tabName;
        }

        return $tab->add();
    }

    private function uninstallModuleTab($tabClass)
    {
        $tab = new Tab((int)Tab::getIdFromClassName($tabClass));

        return $tab->delete();
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        if (((bool)Tools::isSubmit('submitEverpsdisposableconfirmModule')) == true) {
            $this->postValidation();

            if (!count($this->postErrors)) {
                $this->postProcess();
            }
        }
        $this->context->smarty->assign(array(
            'everpsdisposable_dir' => $this->_path,
        ));

        $this->html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/header.tpl');
        $this->html .= $this->renderForm();
        $this->html .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/footer.tpl');

        return $this->html;
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitEverpsdisposableconfirmModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm($this->getConfigForm());
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        $cms = CMS::getCMSPages(
            (int)$this->context->language->id,
            null,
            true,
            (int)$this->context->shop->id
        );
        $customerGroups = Group::getGroups(
            (int)$this->context->language->id,
            (int)$this->context->shop->id
        );

        $form_fields = array();
        $form_fields[] = array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-smile',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Delete all disposable emails'),
                        'name' => 'DELETE_DISPOSABLE',
                        'is_bool' => true,
                        'desc' => $this->l('Will delete all disposable emails subscriptions'),
                        'hint' => $this->l('Else accounts will be disabled'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('No')
                            )
                        ),
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Delete/disable only spams account'),
                        'name' => 'ONLY_SPAM',
                        'is_bool' => true,
                        'desc' => $this->l('Will only delete/disable spams accounts'),
                        'hint' => $this->l('Else disposable emails will be deleted or disabled'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('No')
                            )
                        ),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
        return $form_fields;
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'DELETE_DISPOSABLE' => Configuration::get('DELETE_DISPOSABLE'),
            'ONLY_SPAM' => Configuration::get('ONLY_SPAM'),
        );
    }

    public function postValidation()
    {
        if (((bool)Tools::isSubmit('submitEverpsdisposableconfirmModule')) == true) {
            if (Tools::getValue('DELETE_DISPOSABLE')
                && !Validate::isBool(Tools::getValue('DELETE_DISPOSABLE'))
            ) {
                $this->postErrors[] = $this->l(
                    'Error : The field "DELETE_DISPOSABLE" is not valid'
                );
            }
            if (Tools::getValue('ONLY_SPAM')
                && !Validate::isBool(Tools::getValue('ONLY_SPAM'))
            ) {
                $this->postErrors[] = $this->l(
                    'Error : The field "ONLY_SPAM" is not valid'
                );
            }
        }
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();
        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue(
                $key,
                Tools::getValue($key)
            );
        }
        $this->postSuccess[] = $this->l('All settings have been saved');
    }

    public function hookActionCustomerAccountUpdate($params)
    {
        // Hook for tests only
    }

    public function hookActionCustomerAccountAdd($params)
    {
        $customer = new Customer((int)$params['newCustomer']->id);
        if (Configuration::get('ONLY_SPAM')) {
            if ($this->getDisposableEmail($customer->email)) {
                if (Configuration::get('DELETE_DISPOSABLE')) {
                    $customer->logout();
                    $this->context->cookie->logout();
                    $customer->delete();
                } else {
                    $customer->active = 0;
                    $customer->save();
                    $customer->logout();
                }
                return false;
            }
        }
        if (filter_var($customer->firstname, FILTER_VALIDATE_EMAIL)
            || $this->isInvalidCutomerName($customer->firstname)
        ) {
            if (Configuration::get('DELETE_DISPOSABLE')) {
                $customer->logout();
                $this->context->cookie->logout();
                $customer->delete();
            } else {
                $customer->active = 0;
                $customer->logout();
                $this->context->cookie->logout();
                $customer->save();
            }
            return false;
        }
        if (filter_var($customer->lastname, FILTER_VALIDATE_EMAIL)
            || $this->isInvalidCutomerName($customer->lastname)
        ) {
            if (Configuration::get('DELETE_DISPOSABLE')) {
                $customer->logout();
                $this->context->cookie->logout();
                $customer->delete();
            } else {
                $customer->active = 0;
                $customer->logout();
                $this->context->cookie->logout();
                $customer->save();
            }
            return false;
        }
    }

    private function getDisposableEmail($email)
    {
        $domain = Tools::substr($email, Tools::strpos($email, '@') + 1);
        die(var_dump($domain));
        $sql = new DbQuery();
        $sql->select('id_everpsdisposable');
        $sql->from('everpsdisposable');
        $sql->where('disposable_email = "'.pSQL($domain).'"');
        return Db::getInstance()->getValue($sql);
    }

    private function isInvalidCutomerName($domain_name)
    {
        $validityPattern = Tools::cleanNonUnicodeSupport(
            '/^(?:[^0-9!<>,;?=+()\/\\@#"°*`{}_^$%:¤\[\]|\.。]|[\.。](?:\s|$))*$/u'
        );

        return !preg_match($validityPattern, $name);
    }
}
