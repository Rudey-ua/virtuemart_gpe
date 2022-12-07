<?php

namespace Ginger\Lib;

require_once(JPATH_LIBRARIES . '/' .Bankconfig::BANK_PREFIX .'/vendor/autoload.php');

use Ginger\Redefiners\ClientBuilderRedefiner;
use Ginger\Redefiners\OrderBuilderRedefiner;
use GingerPluginSdk\Properties\Currency;
use JFactory;
use JRoute;
use JText;
use JUri;
use ShopFunctions;
use VirtueMartCart;
use vmJsApi;
use vmLanguage;
use VmModel;
use vmPSPlugin;
use vmText;
use vRequest;

/**
 *   ╲          ╱
 * ╭──────────────╮  COPYRIGHT (C) 2018 GINGER PAYMENTS B.V.
 * │╭──╮      ╭──╮│
 * ││//│      │//││  This software is released under the terms of the
 * │╰──╯      ╰──╯│  MIT License.
 * ╰──────────────╯
 *   ╭──────────╮    https://www.gingerpayments.com/
 *   │ () () () │
 *
 * @category    Ginger
 * @package     Ginger Virtuemart
 * @author      Ginger Payments B.V. (plugins@gingerpayments.com)
 * @version     v1.3.1
 * @copyright   COPYRIGHT (C) 2018 GINGER PAYMENTS B.V.
 * @license     The MIT License (MIT)
 * @since       v1.0.0
 **/
class GingerVmPaymentPlugin extends \vmPSPlugin
{
    private \GingerPluginSdk\Client $client;

    /**
     * Constructor
     *
     * @param object $subject The object to observe
     * @param array $config An array that holds the plugin configuration
     */
    public function __construct(&$subject, $config)
    {
        parent::__construct($subject, $config);
        $this->_loggable = TRUE;
        $this->tableFields = array_keys(Helper::getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $this->setConfigParameterable($this->_configTableFieldName, parent::getVarsToPush());
        $this->client = (new ClientBuilderRedefiner($this->methodParametersFactory()))->createClient();
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * @since v1.0.0
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Standard Table');
    }

    /**
     * Check if the payment conditions are fulfilled for this payment method
     *
     * @param $cart_prices : cart prices
     * @param $payment
     * @return true: if the conditions are fulfilled, false otherwise
     *
     * @author: Valerie Isaksen
     *
     */
    protected function checkConditions($cart, $method, $cart_prices)
    {
        $this->convert_condition_amount($method);
        $amount = $this->getCartAmount($cart_prices);
        $address = (($cart->ST == 0) ? $cart->BT : $cart->ST);

        $amountCond = ($amount >= $method->min_amount and $amount <= $method->max_amount
            or ($method->min_amount <= $amount and ($method->max_amount == 0)));
        if (!$amountCond) {
            return FALSE;
        }
        $countries = array();
        if (!empty($method->countries)) {
            (!is_array($method->countries)) ? $countries[0] = $method->countries : $countries = $method->countries;
        }

        if (!is_array($address)) {
            $address = array();
            $address['virtuemart_country_id'] = 0;
        }

        if (!isset($address['virtuemart_country_id'])) {
            $address['virtuemart_country_id'] = 0;
        }
        if (count($countries) == 0 || in_array($address['virtuemart_country_id'], $countries) || count($countries) == 0) {
            return TRUE;
        }

        return FALSE;
    }

    /**
     * update vm order status
     *
     * @param string $gingerOrderStatus
     * @param int $virtuemart_order_id
     * @return boolean
     * @since v1.0.0
     */
    protected function updateOrder($gingerOrderStatus, $virtuemart_order_id)
    {
        switch ($gingerOrderStatus) {
            case 'completed':
                Helper::updateOrderStatus($this->methodParametersFactory()->statusCompleted(), $virtuemart_order_id);
                return true;
            case 'accepted':
                Helper::updateOrderStatus($this->methodParametersFactory()->statusAccepted(), $virtuemart_order_id);
                return false;
            case 'processing':
                Helper::updateOrderStatus($this->methodParametersFactory()->statusProcessing(), $virtuemart_order_id);
                return true;
            case 'pending':
                Helper::updateOrderStatus($this->methodParametersFactory()->statusPending(), $virtuemart_order_id);
                return false;
            case 'new':
                Helper::updateOrderStatus($this->methodParametersFactory()->statusNew(), $virtuemart_order_id);
                return true;
            case 'error':
                Helper::updateOrderStatus($this->methodParametersFactory()->statusError(), $virtuemart_order_id);
                return false;
            case 'cancelled':
                Helper::updateOrderStatus($this->methodParametersFactory()->statusCanceled(), $virtuemart_order_id);
                return false;
            case 'expired':
                Helper::updateOrderStatus($this->methodParametersFactory()->statusExpired(), $virtuemart_order_id);
                return false;
            default:
                die("Should not happen");
        }
    }

    /**
     * Create a PaymentParameter instance
     *
     * @return PaymentParameters
     * @since v1.0.0
     */
    protected function methodParametersFactory()
    {
        return PaymentParametersFactory::getConfig($this->_name);
    }

    /**
     * This method is fired when showing the order details in the frontend.
     * It displays the method-specific data.
     *
     * @param integer $order_id The order ID
     * @return mixed Null for methods that aren't active, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    protected function plgVmOnShowOrderFEPayment($virtuemart_order_id, $virtuemart_paymentmethod_id, &$payment_name)
    {
        $this->onShowOrderFE($virtuemart_order_id, $virtuemart_paymentmethod_id, $payment_name);
        return true;
    }

    /**
     * This method is fired when showing when priting an Order
     * It displays the the payment method-specific data.
     *
     * @param integer $_virtuemart_order_id The order ID
     * @param integer $method_id method used for this order
     * @return mixed Null when for payment methods that were not selected, text (HTML) otherwise
     * @author Valerie Isaksen
     */
    public function plgVmOnShowOrderPrintPayment($order_number, $method_id)
    {
        return parent::onShowOrderPrint($order_number, $method_id);
    }

    /**
     *
     * @param type $data
     * @return boolean
     */
    public function plgVmDeclarePluginParamsPaymentVM3(&$data)
    {
        return $this->declarePluginParams('payment', $data);
    }

    public function plgVmSetOnTablePluginParamsPayment($name, $id, &$table)
    {
        $afterPay = Bankconfig::BANK_PREFIX_UPPER . '_AFTERPAY_COUNTRIES_AVAILABLE';

        if ($name == Bankconfig::BANK_PREFIX . 'afterpay') {
            $table->$afterPay = "NL, BE";
        }
        return $this->setOnTablePluginParams($name, $id, $table);
    }

    /**
     * Create the table for this plugin if it does not yet exist.
     * This functions checks if the called plugin is active one.
     * When yes it is calling the standard method to create the tables
     *
     */
    public function plgVmOnStoreInstallPaymentPluginTable($jplugin_id)
    {
        return parent::onStoreInstallPluginTable($jplugin_id);
    }

    public function plgVmOnCheckAutomaticSelectedPayment(\VirtueMartCart $cart, array $cart_prices = array(), &$paymentCounter)
    {
        $return = $this->onCheckAutomaticSelected($cart, $cart_prices, $paymentCounter);

        return isset($return) ? 0 : NULL;
    }

    /**
     * @param $cart
     * @param $order
     * @return bool|null
     * @since v1.0.0
     */
    public function plgVmConfirmedOrder($cart, $order)
    {
        $method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id);

        if (!($method)) {
            return null;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }
        vmLanguage::loadJLang('com_virtuemart', true);
        vmLanguage::loadJLang('com_virtuemart_orders', true);

        Helper::getPaymentCurrency($method, $order['details']['BT']->payment_currency_id);
        $currencyCode = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
        $emailCurrency = $this->getEmailCurrency($method);
        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $method->payment_currency);

        if (!empty($method->payment_info)) {
            $lang = JFactory::getLanguage();
            if ($lang->hasKey($method->payment_info)) {
                $method->payment_info = vmText::_($method->payment_info);
            }
        }

        $buildOrder = (new OrderBuilderRedefiner($order, $method, $cart, $this->payment_method))->buildOrder();
        $client = (new ClientBuilderRedefiner($this->methodParametersFactory()))->createClient();
        $bankError = Bankconfig::BANK_PREFIX . '_LIB_ERROR_TRANSACTION';

        try {
            $gingerOrder = $client->sendOrder($buildOrder);
        } catch (\Exception $exception) {
            $html = "<p>" . JText::_($bankError) . "</p><p>Error: " . $exception->getMessage() . "</p>";
            Helper::processFalseOrderStatusResponse($html);
        }
        if ($gingerOrder->getStatus()->get() == 'error') {
            $html = "<p>" . JText::_($bankError) . "</p><p>Error: ".$gingerOrder->toArray()['transactions'][0]['customer_message']."</p>";
            Helper::processFalseOrderStatusResponse($html);
        }
        if (array_key_exists('customer_message', $gingerOrder->toArray()['transactions'][0])) {
            $html = "<p>" . JText::_($bankError) . "</p><p>Error: " . $gingerOrder->toArray()['transactions'][0]['customer_message'] . "</p>";
            Helper::processFalseOrderStatusResponse($html);
        }
        if (!$gingerOrder->getCurrentTransaction()->getId()->get()) {
            $html = "<p>" . JText::_($bankError) . "</p><p>Error: Response did not include id!</p>";
            Helper::processFalseOrderStatusResponse($html);
        }
        if ($this->payment_method != 'bank-transfer') {
            if (!$gingerOrder->getPaymentUrl()) {
                $html = "<p>" . JText::_($bankError) . "</p><p>Error: Response did not include payment url!</p>";
                Helper::processFalseOrderStatusResponse($html);
            }
        }

        JFactory::getSession()->clear(Bankconfig::BANK_PREFIX . 'ideal_issuer', 'vm'); //clear session values
        $dbValues['payment_name'] = $this->renderPluginName($method) . '<br />' . $method->payment_info;
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
        $dbValues['cost_min_transaction'] = $method->cost_min_transaction;
        $dbValues['cost_percent_total'] = $method->cost_percent_total;
        $dbValues['payment_currency'] = $currencyCode;
        $dbValues['email_currency'] = $emailCurrency;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
        $dbValues['tax_id'] = $method->tax_id;
        $dbValues['ginger_order_id'] = $gingerOrder->getId()->get();
        $this->storePSPluginInternalData($dbValues);
        $virtuemartOrderId = $gingerOrder->toArray()['merchant_order_id'];
        $virtuemartOrderNumber = Helper::getOrderNumberByGingerOrder(vRequest::get('order_id'), $this->_tablename);
        $statusSucceeded = $this->updateOrder($gingerOrder->getStatus()->get(), $virtuemartOrderId);

        if ($this->payment_method == 'bank-transfer') {
            if ($statusSucceeded) {
                $html = $this->renderByLayout('post_payment', array(
                    'total_to_pay' => $totalInPaymentCurrency['display'],
                    'reference' => Helper::getGingerPaymentReference($gingerOrder),
                    'description' => "<p>" . Helper::getOrderDescription($virtuemartOrderId) . "</p>",
                    'bank_information' => "IBAN: " . Helper::getGingerPaymentIban($gingerOrder) .
                        "<br/>BIC: " . Helper::getGingerPaymentBic($gingerOrder) .
                        "<br/>Account holder: " . Helper::getGingerPaymentHolderName($gingerOrder) .
                        "<br/>City: " . Helper::getGingerPaymentHolderCity($gingerOrder) .
                        "<br/>Country: " . Helper::getGingerPaymentHolderCountry($gingerOrder)
                ));
                $this->emptyCart(null, $virtuemartOrderId);
                vRequest::setVar('html', $html);
                return true;
            }
            $html = "<p>" . Helper::getOrderDescription($virtuemartOrderNumber) . "</p>" .
                "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_LIB_ERROR_STATUS') . "</p>";

            if ($this->payment_method == 'afterpay') {
                if ($gingerOrder['status']) {
                    $html .= "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_AFTERPAY_CANCELLED_STATUS_MSG') . "</p>";
                }
            }
            Helper::processFalseOrderStatusResponse($html);
        }
        JFactory::getApplication()->redirect($gingerOrder->getPaymentUrl());
    }

    /**
     * Handle payment response
     *
     * @param string $paymentResponse
     * @param string $html
     * @return bool|null|string
     * @throws \Exception
     * @since v1.0.0
     */
    public function plgVmOnPaymentResponseReceived(&$html, &$paymentResponse)
    {
        if (!($method = $this->getVmPluginMethod(vRequest::getInt('pm')))) {
            return null;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        if (!class_exists('VirtueMartModelOrders')) {
            require(VMPATH_ADMIN . DS . 'models' . DS . 'orders.php');
        }
        vmLanguage::loadJLang('com_virtuemart', true);
        vmLanguage::loadJLang('com_virtuemart_orders', true);

        $gingerOrder = ($this->client->getOrder(vRequest::get('order_id')))->toArray();
        $virtuemartOrderId = Helper::getOrderIdByGingerOrder(vRequest::get('order_id'), $this->_tablename);
        $virtuemartOrderNumber = Helper::getOrderNumberByGingerOrder(vRequest::get('order_id'), $this->_tablename);
        $statusSucceeded = $this->updateOrder($gingerOrder['status'], $virtuemartOrderId);
        $html = "<p>" . Helper::getOrderDescription($virtuemartOrderNumber) . "</p>";

        if ($this->payment_method == 'ideal') {
            if (Helper::isProcessingOrderNotConfirmedRedirect()) {
                $this->emptyCart(null, $virtuemartOrderId);
                $html .= "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_LIB_NO_BANK_RESPONSE') . "</p>";
                Helper::processFalseOrderStatusResponse($html);
            }

            if ($gingerOrder['status'] === 'processing' && !Helper::isProcessingOrderNotConfirmedRedirect()) {
                $box = '
                jQuery(document).ready(function($) {
                    var fallback_url = \'' . JURI::root() . '?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=' . vRequest::getInt('pm') . '&project_id=' . vRequest::getInt('project_id') . '&order_id=' . vRequest::get('order_id') . '&no_confirmation_redirect=1\';
                    var ajaxResponseUrl = \'/?option=com_virtuemart&view=plugin&type=vmpayment&name=' . $this->_name . "&call=selffe&order_id=" . vRequest::get('order_id') . '\' ;
                    var counter = 0;
                    var loop = setInterval(
                            function refresh_pending() {
                                counter++;
                                $.ajax({
                                    type: "GET",
                                    url: ajaxResponseUrl,
                                    success: function (data) {
                                        if (data.redirect == true) {
                                            location.reload();
                                        }
                                    }
                                });
                                if (counter >= 6) {
                                    clearInterval(loop);
                                    location.href = fallback_url;
                                }
                            },
                        10000
                    );
                });
                ';
                $html = $this->renderByLayout('payment_processing', array(
                    'description' => "<p>" . Helper::getOrderDescription($virtuemartOrderId) . "</p>",
                    'logo' => sprintf('%s/assets/images/ajax-loader.gif', (JURI::root() . $this->getOwnUrl()))
                ));
                vmJsApi::addJScript('box', $box);
                vRequest::setVar('html', $html);
                vRequest::setVar('display_title', false);
                return false;
            }
            if ($statusSucceeded === false) {
                switch ($gingerOrder['status']) {
                    case 'expired':
                        $html .= "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_LIB_ERROR_STATUS_EXPIRED') . "</p>";
                        break;
                    case 'cancelled':
                        $html .= "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_LIB_ERROR_STATUS_CANCELED') . "</p>";
                        break;
                    default:
                        $html .= "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_LIB_ERROR_STATUS') . "</p>";
                        break;
                }
                Helper::processFalseOrderStatusResponse($html);
            }

            $this->emptyCart(null, $virtuemartOrderId);
            $paymentResponse .= "<br>" . "Paid with " . $this->getDataByOrderId($virtuemartOrderId)->payment_name;
            vRequest::setVar('html', $html);
            vRequest::setVar('display_title', false);
            return true;
        }

        if ($statusSucceeded) {
            $paymentResponse .= "<br>" . "Paid with " . $this->getDataByOrderId($virtuemartOrderId)->payment_name;
            vRequest::setVar('html', $html);
            $this->emptyCart(null, $virtuemartOrderId);
            vRequest::setVar('html', $html);
            return true;
        }
        $html .= "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_LIB_ERROR_STATUS') . "</p>";
        Helper::processFalseOrderStatusResponse($html);
    }

    /**
     * Webhook action
     *
     * @return boolean
     * @since v1.0.0
     */
    public function plgVmOnPaymentNotification()
    {
        if (!($method = $this->getVmPluginMethod(vRequest::getInt('pm')))) {
            return null;
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }

        $input = json_decode(file_get_contents("php://input"), true);

        if (empty($input['order_id']) || $input['event'] !== 'status_changed') {
            exit('Invalid input');
        }

        $gingerOrder = $this->client->getOrder($input['order_id'])->toArray();
        $virtuemartOrderId = Helper::getOrderIdByGingerOrder($input['order_id'], $this->_tablename);
        $this->updateOrder($gingerOrder['status'], $virtuemartOrderId);

        exit();
    }

    /**
     *
     * @param type $virtuemart_paymentmethod_id
     * @param type $paymentCurrencyId
     * @return boolean
     * @since v1.0.0
     */
    public function plgVmgetPaymentCurrency($virtuemart_paymentmethod_id, &$paymentCurrencyId)
    {
        if (!($method = $this->getVmPluginMethod($virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }
        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        Helper::getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
        return;
    }

    /**
     *
     * @param \VirtueMartCart $cart
     * @param type $selected
     * @param type $htmlIn
     * @return boolean
     * @since v1.0.0
     */
    public function plgVmDisplayListFEPayment(\VirtueMartCart $cart, $selected = 0, &$htmlIn) // main
    {
        $currency_model = VmModel::getModel('currency');
        $displayCurrency = $currency_model->getCurrency( $this->product->product_currency );
        $currency = $displayCurrency->currency_code_3;

        if($this->payment_method == 'apple-pay' && !Helper::applePayDetection()) return false;

        $client = (new ClientBuilderRedefiner($this->methodParametersFactory()))->createClient();

        if(isset($this->methods)) {
            $result = $client->checkAvailabilityForPaymentMethodUsingCurrency($this->payment_method, new Currency($currency));
            if(!$result) return false;
        }

        if ($this->payment_method == 'afterpay' && isset($cart->BT['virtuemart_country_id'])) {

            $country = shopFunctions::getCountryByID($cart->BT['virtuemart_country_id'], 'country_2_code');

            if (!$this->userIsFromAllowedCountries($country)) {
                return false;
            }
            if ($this->isSetShowForIpFilter() && !$this->addressIsAllowed()) {
                return false;
            }
        }

        if ($this->payment_method == 'ideal' || $this->payment_method == 'afterpay' || $this->payment_method == 'klarna-pay-later') {
            if ($this->getPluginMethods($cart->vendorId) === 0) {
                if (empty($this->_name)) {
                    $app = JFactory::getApplication();
                    $app->enqueueMessage(vmText::_('COM_VIRTUEMART_CART_NO_' . strtoupper($this->_psType)));
                    return false;
                } else {
                    return false;
                }
            }
            $method_name = $this->_psType . '_name';
            vmLanguage::loadJLang('com_virtuemart', true);
            $htmla = array();
            $html = '';
            foreach ($this->methods as $currentMethod) {
                if ($this->checkConditions($cart, $currentMethod, $cart->cartPrices)) {
                    $cartPrices = $cart->cartPrices;
                    $methodSalesPrice = $this->setCartPrices($cart, $cartPrices, $currentMethod);
                    $currentMethod->$method_name = $this->renderPluginName($currentMethod);
                    $html = $this->getPluginHtml($currentMethod, $selected, $methodSalesPrice);

                    if ($this->payment_method == 'klarna-pay-later') {
                        $htmlIn[] = [$html];
                        return $this->isPaymentSelected($selected);
                    } else {
                        $htmla[] = $html . '<br />' . $this->customInfoHTML();
                        $htmlIn[] = $htmla;
                        return $this->isPaymentSelected($selected);
                    }
                }
            }
        }
        return $this->displayListFE($cart, $selected, $htmlIn);
    }

    /**
     * check if current method is selected
     *
     * @param int $selected
     * @return boolean
     * @since v1.1.0
     */
    public function isPaymentSelected($selected)
    {
        $method = array_shift($this->methods);
        if (is_object($method)) {
            return $method->virtuemart_paymentmethod_id === $selected;
        }
        return false;
    }

    /**
     *
     * @param \VirtueMartCart $cart
     * @param array $cart_prices
     * @param type $cart_prices_name
     * @return boolean
     * @since v1.0.0
     */
    public function plgVmonSelectedCalculatePricePayment(\VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name) // question
    {
        if ($this->payment_method == 'ideal' || $this->payment_method == 'klarna-pay-later' || $this->payment_method == 'afterpay') {
            if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
                return null; // Another method was selected, do nothing
            }
            if (!$this->selectedThisElement($this->_currentMethod->payment_element)) {
                return false;
            }

            if (!$this->checkConditions($cart, $this->_currentMethod, $cart_prices)) {
                return false;
            }
            $this->renderPluginName($this->_currentMethod);

            $this->setCartPrices($cart, $cart_prices, $this->_currentMethod);

            return true;
        }

        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * This is for checking the input data of the payment method within the checkout
     *
     * @author Valerie Cartan Isaksen
     */
    public function plgVmOnCheckoutCheckDataPayment(\VirtueMartCart $cart)
    {
        if ($this->payment_method == 'afterpay') {
            if ($cart->cartData['paymentName'] == '<span class="vmpayment_name">AfterPay</span>') {
                if (!$this->userIsFromAllowedCountries($cart->BTaddress['fields']['virtuemart_country_id']['country_2_code'])) {
                    return false;
                }
            } else {
                return null;
            }
            if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
                return null; // Another method was selected, do nothing
            }
            if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
                return false;
            }

            $app = JFactory::getApplication();
            $dob = $app->getSession()->get(Bankconfig::BANK_PREFIX . 'afterpay_dob', null, 'vm');

            if (Helper::isValidDate($dob) === false || $dob === null) {
                $app->enqueueMessage(JText::_('PLG_VMPAYMENT_'. Bankconfig::BANK_PREFIX .'AFTERPAY_MESSAGE_INVALID_DATE_ERROR'), 'error');
                $app->getSession()->clear(Bankconfig::BANK_PREFIX . 'afterpay_dob', 'vm');
                $app->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&task=editpayment', false));
                return false;
            }
            $tc = $app->getSession()->get(Bankconfig::BANK_PREFIX . 'afterpay_terms_and_confditions', null, 'vm');
            if ($tc != 'on' || $tc == null) {
                $app->enqueueMessage(JText::_('PLG_VMPAYMENT_' . Bankconfig::BANK_PREFIX .'AFTERPAY_MESSAGE_PLEASE_ACCEPT_TC'), 'error');
                $app->getSession()->clear(Bankconfig::BANK_PREFIX . 'afterpay_terms_and_confditions', 'vm');
                $app->redirect(JRoute::_('index.php?option=com_virtuemart&view=cart&task=editpayment', false));
                return false;
            }
            return true;
        }

        if ($this->payment_method == 'klarna-pay-later') {
            if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
                return null; // Another method was selected, do nothing
            }
            if (!($this->_currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
                return false;
            }
            return true;
        }
        return true;
    }

    /**
     * store the choosen isseur into session
     *
     * @param VirtueMartCart $cart
     * @param type $msg
     * @return boolean
     * @since v1.0.0
     */
    public function plgVmOnSelectCheckPayment(VirtueMartCart $cart, &$msg)
    {
        if ($this->payment_method == 'klarna-pay-later') {
            if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
                return null; // Another method was selected, do nothing
            }

            if (!($this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
                return false;
            }
            return true;
        }

        if ($this->payment_method == 'afterpay') {
            if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
                return null; // Another method was selected, do nothing
            }

            if (!($this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
                return false;
            }
            JFactory::getSession()->set(Bankconfig::BANK_PREFIX . 'afterpay_gender', vRequest::getVar('gender'), 'vm');
            JFactory::getSession()->set(Bankconfig::BANK_PREFIX . 'afterpay_dob', vRequest::getVar(Bankconfig::BANK_PREFIX . 'afterpay_dob'), 'vm');
            JFactory::getSession()->set(Bankconfig::BANK_PREFIX . 'afterpay_terms_and_confditions', vRequest::getVar('terms_and_confditions'), 'vm');

            return true;
        }

        JFactory::getSession()->set(Bankconfig::BANK_PREFIX . 'ideal_issuer', vRequest::getVar('issuer'), 'vm');
        return $this->OnSelectCheck($cart);
    }

    /**
     * @return string
     * @since v1.0.0
     */
    public function customInfoHTML($country = null)
    {
        $select_message = 'PLG_VMPAYMENT_' . Bankconfig::BANK_PREFIX . 'AFTERPAY_MESSAGE_SELECT_GENDER';
        $male = 'PLG_VMPAYMENT_' . Bankconfig::BANK_PREFIX . 'AFTERPAY_MESSAGE_SELECT_GENDER_MALE';
        $female = 'PLG_VMPAYMENT_' . Bankconfig::BANK_PREFIX . 'AFTERPAY_MESSAGE_SELECT_GENDER_FEMALE';
        $dob = 'PLG_VMPAYMENT_' . Bankconfig::BANK_PREFIX . 'AFTERPAY_MESSAGE_ENTER_DOB';
        $data_format = 'PLG_VMPAYMENT_' . Bankconfig::BANK_PREFIX . 'AFTERPAY_MESSAGE_DATE_FORMAT';
        $terms = 'PLG_VMPAYMENT_' . Bankconfig::BANK_PREFIX . 'AFTERPAY_TERMS_AND_CONDITIONS';
        $input = '<input type="text" name="' . Bankconfig::BANK_PREFIX .'afterpay_dob" value="';

        if ($this->payment_method == 'afterpay') {
            $html = JText::_($select_message) . ' <br/>';
            $html .= '<select name="gender" id="' . $this->name . '" class="' . $this->name . '">';
            $html .= '<option value="male" '
                . (JFactory::getSession()->get(Bankconfig::BANK_PREFIX . 'afterpay_gender') == 'male' ? " selected" : "") . '>'
                . JText::_($male) . '</option>';
            $html .= '<option value="female" '
                . (JFactory::getSession()->get(Bankconfig::BANK_PREFIX . 'afterpay_gender') == 'male' ? " selected" : "") . '>'
                . JText::_($female) . '</option>';
            $html .= "</select><br/>";
            $html .= JText::_($dob) . '<br>';
            $html .= $input . JFactory::getSession()->get(Bankconfig::BANK_PREFIX . 'afterpay_dob', null, 'vm') . '"/>';
            $html .= '<i>(' . JText::_($data_format) . ')</i></br>';
            $html .= '<input type="checkbox" name="terms_and_confditions" ' . (JFactory::getSession()->get(Bankconfig::BANK_PREFIX . 'afterpay_terms_and_confditions', null, 'vm') == 'on' ? 'checked="checked"' : null) . ' />';
            $html .= '<a href="' . Helper::gettermsAndConditionsUrlByCountry($country) . '" target="blank">' . JText::_($terms) . '</a>';
            return $html;
        }
        if ($this->payment_method == 'ideal') {
            $issuers = $this->client->getIdealIssuers()->toArray();
            $html = '<select name="issuer" id="issuer" class="' . $this->_name . '">';
            foreach ($issuers as $issuer) {
                $html .= '<option value="' . $issuer['id'] . '">' . $issuer['name'] . "</option>";
            }
            $html .= "</select>";
            return $html;
        }
    }

    /**
     * check if filltering is set on,
     * if so, only display if user is from that IP
     *
     * @return boolean
     * @since v1.1.0
     */
    protected function isSetShowForIpFilter()
    {
        return (bool)is_array($this->methodParametersFactory()->afterpayAllowedIpAddresses());
    }

    /**
     * if filltering is set on,
     * only display if user is from that IP
     *
     * @return boolean
     * @since v1.1.0
     */
    protected function addressIsAllowed()
    {
        return (bool)in_array(filter_var($_SERVER['REMOTE_ADDR'], FILTER_VALIDATE_IP), $this->methodParametersFactory()->afterpayAllowedIpAddresses());
    }

    /**
     * checks is user form allowed countries
     *
     * @param string $country
     * @return boolean
     * @since v1.1.0
     */
    protected function userIsFromAllowedCountries($country)
    {
        if (empty($this->methodParametersFactory()->afterpayAllowedCountries())) {
            return true;
        } else {
            return in_array(strtoupper($country), $this->methodParametersFactory()->afterpayAllowedCountries());
        }
    }
}
