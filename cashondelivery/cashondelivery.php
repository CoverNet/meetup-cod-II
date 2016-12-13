<?php
/*
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2015 PrestaShop SA
*  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

if (!defined('_PS_VERSION_'))
	exit;

class CashOnDelivery extends PaymentModule
{

	public function __construct()
	{
		$this->name = 'cashondelivery';
		$this->tab = 'payments_gateways';
		$this->version = '0.7.5';
		$this->author = 'PrestaShop';
		$this->need_instance = 1;// 1 -> Modules    
		$this->controllers = array('validation');
		$this->is_eu_compatible = 1;
                
//                by Jesús
                $this->bootstrap = true;

		$this->currencies = false;

		parent::__construct();

		$this->displayName = $this->l('Cash on delivery (COD)');
		$this->description = $this->l('Accept cash on delivery payments');
                
//                by Jesús
                if ((!isset($this->description) || !isset($this->displayName) || empty($this->description) || empty($this->displayName))){
			$this->warning = $this->l('warning module cashondelivery.');
                }
//                end Jesús
                
		/* For 1.4.3 and less compatibility */
		$updateConfig = array('PS_OS_CHEQUE', 'PS_OS_PAYMENT', 'PS_OS_PREPARATION', 'PS_OS_SHIPPING', 'PS_OS_CANCELED', 'PS_OS_REFUND', 'PS_OS_ERROR', 'PS_OS_OUTOFSTOCK', 'PS_OS_BANKWIRE', 'PS_OS_PAYPAL', 'PS_OS_WS_PAYMENT');
		if (!Configuration::get('PS_OS_PAYMENT'))
			foreach ($updateConfig as $u)
				if (!Configuration::get($u) && defined('_'.$u.'_'))
					Configuration::updateValue($u, constant('_'.$u.'_'));
	}

	public function install()
	{
		// by cpm
		Configuration::updateValue('COD_PORCENT_FEE', 0);
		Configuration::updateValue('COD_FIXED_FEE', 0);
		Configuration::updateValue('COD_IDPRODUCT_FEE', 0);
		// Configuration::updateValue('COD_MAX_ORDER_AMOUNT', 0);
		// end by cpm

		if (!parent::install() OR !$this->registerHook('payment') OR 
                        !$this->registerHook('displayPaymentEU') OR 
                        !$this->registerHook('paymentReturn')
//                                by Jesüs
                        OR !$this->registerHook('DisplayHeader')
                        )
			return false;
		return true;
	}

	/* by cpm
	 * 
	 */
    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitCODModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

//		$output = '';
        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
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
        $helper->submit_action = 'submitCODModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    
        
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-cogs"></i>',
                        'label' => $this->l('Fee Porcent'),
                        'name' => 'COD_PORCENT_FEE',
                        'desc' => $this->l('Assign a porcent value to calculate the fee'),
                    ),
                    array(
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-cogs"></i>',
                        'label' => $this->l('Fee Fixed Amount'),
                        'name' => 'COD_FIXED_FEE',
                        'desc' => $this->l('Assign a fixed value to calculate the fee'),
                    ),
                    array(
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-cogs"></i>',
                        'label' => $this->l('ID Product Fee'),
                        'name' => 'COD_IDPRODUCT_FEE',
                        'desc' => $this->l('Assign a product to the fee'),
                    ),
                    
                    
//                    by Jesús
                    
                    
                    array(
                        'type' => 'radio',
                        'label' => $this->l('Radio Button'),
                        'name' => 'Radio Button',
                        'default_value' => 0,
                        'values' => array(
                            array(
                                'id' => 'radio1',
                                'value' => 0,
                                'label' => $this->l('radio1')),
                            array(
                                'id' => 'radio2',
                                'value' => 1,
                                'label' => $this->l('radio2')),
                            array(
                                'id' => 'radio3',
                                'value' => 2,
                                'label' => $this->l('radio3')),
                        ),
                        'validation' => 'isUnsignedInt',
                    ),
                    
                    $arrCheck = array(
                        array(
                          'id_option' => 1,                 
                          'name' => 'Method 1'              
                        ),
                        array(
                          'id_option' => 2,
                          'name' => 'Method 2'
                        ),
                    ),
                    
                    
                    array(
                        'type'    => 'checkbox',                         
                        'label'   => $this->l('Options'),                
                        'desc'    => $this->l('Choose options.'),        
                        'name'    => 'checkbox',  
                        'values'  => array(
                          'query' => $arrCheck,                          
                          'id'    => 'id_option',                        
                          'name'  => 'name'                              
                        ),
                    ),
                    
                    array(
                        'type'   =>   'file',
                        'label'  =>   $this->l('Select a file:'),
                        'desc'  =>  $this->l('file: jpeg, png, jpg, gif.'),
                        'name'   =>   'testfile',
                        'required' =>   true,
                        'lang'  =>  false,
                        'display_image' => true,
                    ),
                    
                    array(
                        'type' => 'textarea',
                        'label' => $this->l('textarea:'),
                        'name' => 'description',
                        'autoload_rte' => true,
                        'desc' => $this->l('textarea')
                    ),
                                        
                    array(
                        'type' => 'color',
                        'label' => $this->l('Color:'),
                        'name' => 'caption_color',
                        'size' => 33,
                        'validation' => 'isColor',
                    ),
                    
                    array(
                        'type' => 'select',
                        'label' => $this->l('Select:'),
                        'name' => 'select',
                        'required' => true,
                        'options' => array(
                              'query' => $arrCheck,
                              'id' => 'id_feature',
                              'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('New switc'),
                        'name' => 'Switch Input',
                        'class' => 'fixed-width-xs',
                        'desc' => $this->l('InputSwitch.'),
                        'values' => array(
                            array(
                                    'id' => 'active_on',
                                    'value' => 1,
                                    'label' => $this->l('Yes')
                            ),
                            array(
                                    'id' => 'active_off',
                                    'value' => 0,
                                    'label' => $this->l('No')
                            )
                        ),
                    ),
                    
                    
//                    end Jesús
                    
                    
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
                    
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'COD_PORCENT_FEE' => Configuration::get('COD_PORCENT_FEE', 0),
            'COD_FIXED_FEE' => Configuration::get('COD_FIXED_FEE', 0),
            'COD_IDPRODUCT_FEE' => Configuration::get('COD_IDPRODUCT_FEE', 0),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }
	/* end by cpm
	 * 
	 */

	public function hasProductDownload($cart)
	{
		foreach ($cart->getProducts() AS $product)
		{
			$pd = ProductDownload::getIdFromIdProduct((int)($product['id_product']));
			if ($pd AND Validate::isUnsignedInt($pd))
				return true;
		}
		return false;
	}

	public function hookPayment($params)
	{
//            $this->context->controller->addCSS($this->_path.'views/css/testfront.css', 'all');
//            $this->context->controller->addJS($this->_path.'views/js/testfront.js');
		if (!$this->active)
			return ;

		global $smarty;

		// Check if cart has product download
		if ($this->hasProductDownload($params['cart']))
			return false;

		// by cpm
		$cod_content = array('fee' => 0, 'porcent_fee' => 0, 'fixed_fee' => 0);
        $fee = 0;
		if ((int)Configuration::get('COD_IDPRODUCT_FEE') > 0)
		{
            $cart = $this->context->cart;
            $cart_details = $cart->getSummaryDetails(null, true);
            $idproduct_fee = (int)Configuration::get('COD_IDPRODUCT_FEE');
            $porcent_fee = (float)Configuration::get('COD_PORCENT_FEE');
            $fixed_fee = (float)Configuration::get('COD_FIXED_FEE');

            $p = new Product($idproduct_fee);
            $vat = ($p->getTaxesRate()/100);
            $fee = 0;
            if ($porcent_fee > 0)
            {
                $fee = number_format( ( $cart_details['total_price'] * $porcent_fee ) / 100, 2, '.', '' );
                if ($fixed_fee > 0)
                {
                    $fee += $fixed_fee;
                }
            }
            else if ($fixed_fee > 0)
            {
                $fee = $fixedfee;
            }
            $fee=number_format($fee/(1+$vat),2,'.','');
            $fee=number_format($fee*(1+$vat),2,'.','');
            $cod_content['fee'] = $fee;
            $cod_content['porcent_fee'] = $porcent_fee;
            $cod_content['fixed_fee'] = $fixed_fee;
        }
        // end by cpm

		$smarty->assign(array(
			'cod_content' => $cod_content, // by cpm
			'this_path' => $this->_path, //keep for retro compat
			'this_path_cod' => $this->_path,
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->name.'/'
		));
		return $this->display(__FILE__, 'payment.tpl');
	}

	public function hookDisplayPaymentEU($params)
	{
		if (!$this->active)
			return ;

		// Check if cart has product download
		if ($this->hasProductDownload($params['cart']))
			return false;

		return array(
			'cta_text' => $this->l('Pay with cash on delivery (COD)'),
			'logo' => Media::getMediaPath(dirname(__FILE__).'/cashondelivery.png'),
			'action' => $this->context->link->getModuleLink($this->name, 'validation', array('confirm' => true), true)
		);
	}

	public function hookPaymentReturn($params)
	{
		if (!$this->active)
			return ;

		return $this->display(__FILE__, 'confirmation.tpl');
	}
        
	public function hookDisplayHeader($params)
	{
		$this->hookHeader($params);
	}

	public function hookHeader($params)
	{
//            $this->context->controller->addJS(_THEME_JS_DIR_.'tools/treeManagement.js');
//            $this->context->controller->addJS(_PS_JS_DIR_.'admin/dnd.js');
            $this->context->controller->addCSS($this->_path.'views/css/testfront.css');
            $this->context->controller->addJS($this->_path.'views/js/testfront.js');
	}
        public function hookDisplayHome($param) {
//            $this->context->controller->addCSS($this->_path.'views/css/testfront.css', 'all');
//            $this->context->controller->addJS($this->_path.'views/js/testfront.js');
        }
}
