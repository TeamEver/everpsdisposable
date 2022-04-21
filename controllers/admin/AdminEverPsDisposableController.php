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

class AdminEverPsDisposableController extends ModuleAdminController
{
    private $html;
    private $postErrors = array();
    private $postSuccess = array();

    public function __construct()
    {
        $this->bootstrap = true;
        $this->lang = false;
        $this->table = 'everpsdisposable';
        $this->className = 'EverPsDisposableClass';
        $this->context = Context::getContext();
        $this->identifier = 'id_everpsdisposable';
        $this->isSeven = Tools::version_compare(_PS_VERSION_, '1.7', '>=') ? true : false;
        $this->context->smarty->assign(array(
            'everpsdisposable_dir' => _PS_BASE_URL_ . '/modules/everpsdisposable/'
        ));
        $this->bulk_actions = array(
            'delete' => array(
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected items ?')
            ),
        );
        $this->fields_list = array(
            'disposable_email' => array(
                'title' => $this->l('Email'),
                'align' => 'left',
                'width' => 'auto'
            ),
            'active' => array(
                'title' => $this->l('Banned'),
                'type' => 'bool',
                'active' => 'status',
                'orderby' => false,
                'class' => 'fixed-width-sm'
            ),
        );
        $this->colorOnBackground = true;
        parent::__construct();
    }

    protected function l($string, $class = null, $addslashes = false, $htmlentities = true)
    {
        if ($this->isSeven) {
            return Context::getContext()->getTranslator()->trans($string);
        }

        return parent::l($string, $class, $addslashes, $htmlentities);
    }

    public function renderList()
    {
        $this->html = '';

        $this->addRowAction('edit');
        $this->addRowAction('delete');
        $this->bulk_actions = array(
            'delete' => array(
                'text' => $this->l('Delete selected items'),
                'confirm' => $this->l('Delete selected items ?')
            ),
        );
        if (Tools::isSubmit('submitBulkdelete'.$this->table)) {
            $this->processBulkDelete();
        }

        $this->toolbar_title = $this->l('Disposable emails');

        $lists = parent::renderList();

        $this->html .= $this->context->smarty->fetch(
            _PS_MODULE_DIR_.'/everpsdisposable/views/templates/admin/header.tpl'
        );
        if (count($this->errors)) {
            foreach ($this->errors as $error) {
                $this->html .= Tools::displayError($error);
            }
        }
        $this->html .= $lists;
        $this->html .= $this->context->smarty->fetch(
            _PS_MODULE_DIR_.'/everpsdisposable/views/templates/admin/footer.tpl'
        );

        return $this->html;
    }

    public function renderForm()
    {
        if (Context::getContext()->shop->getContext() != Shop::CONTEXT_SHOP && Shop::isFeatureActive()) {
            $this->errors[] = $this->l('You have to select a shop before creating or editing new pro account.');
        }
        
        if (count($this->errors)) {
            return false;
        }

        $this->fields_form = array(
            'submit' => array(
                'name' => 'save',
                'title' => $this->l('Save'),
                'class' => 'button pull-right'
            ),
            'input' => array(
                array(
                    'type' => 'text',
                    'label' => $this->l('Customer email'),
                    'required' => true,
                    'name' => 'disposable_email'
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->l('Ban this domain name'),
                    'hint' => $this->l('Set no for allowing this domain name'),
                    'desc' => 'Please set domain as banned or not',
                    'name' => 'active',
                    'bool' => true,
                    'lang' => false,
                    'values' => array(
                        array(
                            'id'    => 'active_on',
                            'value' => 1,
                            'label' => $this->l('Enabled')
                        ),
                        array(
                            'id'    => 'active_off',
                            'value' => 0,
                            'label' => $this->l('Disabled')
                        )
                    )
                ),
            )
        );
        $lists = parent::renderForm();

        $this->html .= $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . '/everpsdisposable/views/templates/admin/header.tpl'
        );
        if (count($this->errors)) {
            foreach ($this->errors as $error) {
                $this->html .= Tools::displayError($error);
            }
        }
        $this->html .= $lists;
        $this->html .= $this->context->smarty->fetch(
            _PS_MODULE_DIR_ . '/everpsdisposable/views/templates/admin/footer.tpl'
        );

        return $this->html;
    }

    public function postProcess()
    {
        parent::postProcess();

        if (Tools::isSubmit('save')) {
            $customer = new Customer();
            if (!Tools::getValue('disposable_email')
                || !Validate::isEmail(Tools::getValue('disposable_email'))
            ) {
                 $this->errors[] = $this->l('Email is invalid');
            }
            if (Tools::getValue('active')
                && !Validate::isBool(Tools::getValue('active'))
            ) {
                 $this->errors[] = $this->l('Active is invalid');
            }
            if (!count($this->errors)) {
                $disposable = new EverPsDisposableClass(
                    (int)Tools::getValue('id_everpsdisposable')
                );
                $disposable->disposable_email = Tools::getValue('disposable_email');
                $disposable->active = Tools::getValue('active');

                if (!$disposable->save()) {
                    $this->errors[] = $this->l('An error has occurred: Can\'t save the current object');
                } else {
                    Tools::redirectAdmin(self::$currentIndex.'&conf=4&token='.$this->token);
                }
            }
        }
    }

    protected function processBulkDelete()
    {
        foreach (Tools::getValue($this->table.'Box') as $idEverObj) {
            $everpsobj = new EverPsDisposableClass((int)$idEverObj);

            if (!$everpsobj->delete()) {
                $this->errors[] = $this->l('An error has occurred: Can\'t delete the current object');
            }
        }
    }

    protected function displayError($message, $description = false)
    {
        /**
         * Set error message and description for the template.
         */
        array_push($this->errors, $this->module->l($message), $description);

        return $this->setTemplate('error.tpl');
    }
}
