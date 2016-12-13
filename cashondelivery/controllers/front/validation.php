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

/**
 * @since 1.5.0
 */
class CashondeliveryValidationModuleFrontController extends ModuleFrontController
{
	public $ssl = true;
	public $display_column_left = false;

	public function postProcess()
	{
		if ($this->context->cart->id_customer == 0 || $this->context->cart->id_address_delivery == 0 || $this->context->cart->id_address_invoice == 0 || !$this->module->active)
			Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');

		// Check that this payment option is still available in case the customer changed his address just before the end of the checkout process
		$authorized = false;
		foreach (Module::getPaymentModules() as $module)
			if ($module['name'] == 'cashondelivery')
			{
				$authorized = true;
				break;
			}
		if (!$authorized)
			die(Tools::displayError('This payment method is not available.'));

		$customer = new Customer($this->context->cart->id_customer);
		if (!Validate::isLoadedObject($customer))
			Tools::redirectLink(__PS_BASE_URI__.'order.php?step=1');

		if (Tools::getValue('confirm'))
		{
			$customer = new Customer((int)$this->context->cart->id_customer);
			// by cpm - se ha confirmado el pago y pasamos el carrito a pedido incluyendo el producto del recargo
			$fee = floatval($this->calcFee());
			$idproduct_fee = (int)Configuration::get('COD_IDPRODUCT_FEE');
			$total = $fee + floatval($this->context->cart->getOrderTotal(true, Cart::BOTH)); orig: $total = $this->context->cart->getOrderTotal(true, Cart::BOTH);
			$p=new Product($idproduct_fee);
			$iva=1+($p->getTaxesRate()/100);
			$p->price=number_format($fee/$iva,2,'.','');
			$p->updateWs();
			$cart->updateQty(1,$idproduct_fee);
			$cart->getPackageList(true);
			// end by cpm
			$this->module->validateOrder((int)$this->context->cart->id, Configuration::get('PS_OS_PREPARATION'), $total, $this->module->displayName, null, array(), null, false, $customer->secure_key);
			Tools::redirectLink(__PS_BASE_URI__.'order-confirmation.php?key='.$customer->secure_key.'&id_cart='.(int)$this->context->cart->id.'&id_module='.(int)$this->module->id.'&id_order='.(int)$this->module->currentOrder);
		}
	}

	/**
	 * @see FrontController::initContent()
	 */
	public function initContent()
	{
		parent::initContent();

		$total = floatval($this->calcFee()) + floatval($this->context->cart->getOrderTotal(true, Cart::BOTH)); // by cpm
		$this->context->smarty->assign(array(
			'total' => $total,
			'this_path' => $this->module->getPathUri(),//keep for retro compat
			'this_path_cod' => $this->module->getPathUri(),
			'this_path_ssl' => Tools::getShopDomainSsl(true, true).__PS_BASE_URI__.'modules/'.$this->module->name.'/'
		));

		$this->setTemplate('validation.tpl');
	}

	/*
	 *  by cpm
	 */
    public function calcFee()
    {
		$fee = 0;
		if ((int)Configuration::get('COD_IDPRODUCT_FEE') > 0)
		{
            $cart = $this->context->cart;
            $cart_details = $cart->getSummaryDetails(null, true);
            $idproduct_fee = (int)Configuration::get('COD_IDPRODUCT_FEE');
            $porcent_fee = Configuration::get('COD_PORCENT_FEE');
            $fixed_fee = Configuration::get('COD_FIXED_FEE');

            $p = new Product($idproduct_fee);
            $vat = ($p->getTaxesRate()/100);
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
                $fee = $fixed_fee;
            }
            $fee=number_format($fee/(1+$vat),2,'.','');
            $fee=number_format($fee*(1+$vat),2,'.','');
            // $cod_content['fee'] = $fee;
            // $cod_content['porcent_fee'] = $porcent_fee;
            // $cod_content['fixed_fee'] = $fixed_fee;
        }
        return $fee;
	}

}
