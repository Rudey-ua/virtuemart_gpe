<?php

namespace Ginger\Lib;

require_once(JPATH_LIBRARIES . '/' .Bankconfig::BANK_PREFIX .'/vendor/autoload.php');

use DateTime;
use Ginger\Builders\OrderBuilder;
use Ginger\Redefiners\ClientBuilderRedefiner;
use Ginger\Redefiners\OrderBuilderRedefiner;
use Ginger\Ginger;
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
        $this->tableFields = array_keys($this->getTableSQLFields());
        $this->_tablepkey = 'id';
        $this->_tableId = 'id';
        $this->setConfigParameterable($this->_configTableFieldName, parent::getVarsToPush());
    }

    const TERMS_CONDITION_URL_NL = 'https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden';
    const TERMS_CONDITION_URL_BE = 'https://www.afterpay.be/be/footer/betalen-met-afterpay/betalingsvoorwaarden';
    const BE_ISO_CODE = 'BE';

    /**
     * Create the table for this plugin if it does not yet exist.
     * @since v1.0.0
     */
    public function getVmPluginCreateTableSQL()
    {
        return $this->createTableSQL('Payment Standard Table');
    }

    /**
     * Fields to create the payment table
     *
     * @return array SQL Fileds
     * @since v1.0.0
     */
    function getTableSQLFields()
    {
        $SQLfields = array(
            'id' => 'int(1) UNSIGNED NOT NULL AUTO_INCREMENT',
            'virtuemart_order_id' => 'int(1) UNSIGNED',
            'ginger_order_id' => 'varchar(64)',
            'order_number' => 'char(64)',
            'virtuemart_paymentmethod_id' => 'mediumint(1) UNSIGNED',
            'payment_name' => 'varchar(5000)',
            'payment_order_total' => 'decimal(15,5) NOT NULL DEFAULT \'0.00000\'',
            'payment_currency' => 'char(3)',
            'email_currency' => 'char(3)',
            'cost_per_transaction' => 'decimal(10,2)',
            'cost_min_transaction' => 'decimal(10,2)',
            'cost_percent_total' => 'decimal(10,2)',
            'tax_id' => 'smallint(1)'
        );

        return $SQLfields;
    }

    /**
     *
     * @param type $method
     * @param type $selectedUserCurrency
     * @return type
     * @since v1.0.0
     */
    static function getPaymentCurrency(&$method, $selectedUserCurrency = false)
    {
        if (empty($method->payment_currency)) {
            $vendor_model = \VmModel::getModel('vendor');
            $vendor = $vendor_model->getVendor($method->virtuemart_vendor_id);
            $method->payment_currency = $vendor->vendor_currency;
            return $method->payment_currency;
        } else {

            $vendor_model = \VmModel::getModel('vendor');
            $vendor_currencies = $vendor_model->getVendorAndAcceptedCurrencies($method->virtuemart_vendor_id);

            if (!$selectedUserCurrency) {
                if ($method->payment_currency == -1) {
                    $mainframe = \JFactory::getApplication();
                    $selectedUserCurrency = $mainframe->getUserStateFromRequest("virtuemart_currency_id", 'virtuemart_currency_id', vRequest::getInt('virtuemart_currency_id', $vendor_currencies['vendor_currency']));
                } else {
                    $selectedUserCurrency = $method->payment_currency;
                }
            }

            $vendor_currencies['all_currencies'] = explode(',', $vendor_currencies['all_currencies']);
            if (in_array($selectedUserCurrency, $vendor_currencies['all_currencies'])) {
                $method->payment_currency = $selectedUserCurrency;
            } else {
                $method->payment_currency = $vendor_currencies['vendor_currency'];
            }

            return $method->payment_currency;
        }
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

        $amount_cond = ($amount >= $method->min_amount and $amount <= $method->max_amount
            or ($method->min_amount <= $amount and ($method->max_amount == 0)));
        if (!$amount_cond) {
            return FALSE;
        }
        $countries = array();
        if (!empty($method->countries)) {
            if (!is_array($method->countries)) {
                $countries[0] = $method->countries;
            } else {
                $countries = $method->countries;
            }
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
                $this->updateOrderStatus($this->methodParametersFactory()->statusCompleted(), $virtuemart_order_id);
                return true;
            case 'accepted':
                $this->updateOrderStatus($this->methodParametersFactory()->statusAccepted(), $virtuemart_order_id);
                return false;
            case 'processing':
                $this->updateOrderStatus($this->methodParametersFactory()->statusProcessing(), $virtuemart_order_id);
                return true;
            case 'pending':
                $this->updateOrderStatus($this->methodParametersFactory()->statusPending(), $virtuemart_order_id);
                return false;
            case 'new':
                $this->updateOrderStatus($this->methodParametersFactory()->statusNew(), $virtuemart_order_id);
                return true;
            case 'error':
                $this->updateOrderStatus($this->methodParametersFactory()->statusError(), $virtuemart_order_id);
                return false;
            case 'cancelled':
                $this->updateOrderStatus($this->methodParametersFactory()->statusCanceled(), $virtuemart_order_id);
                return false;
            case 'expired':
                $this->updateOrderStatus($this->methodParametersFactory()->statusExpired(), $virtuemart_order_id);
                return false;
            default:
                die("Should not happen");
        }
    }

    /**
     *
     * @param string $newStatus
     * @param int $virtuemart_order_id
     * @since v1.0.0
     */
    protected function updateOrderStatus($newStatus, $virtuemart_order_id)
    {
        $modelOrder = \VmModel::getModel('orders');
        $order['order_status'] = $newStatus;
        $order['customer_notified'] = 1;
        $order['comments'] = '';
        $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, TRUE);
    }

    /**
     * fetch vm order id for the payment table
     *
     * @param type $gingerOrderId
     * @return int
     * @since v1.0.0
     */
    protected function getOrderIdByGingerOrder($gingerOrderId)
    {
        $query = "SELECT `virtuemart_order_id` FROM " . $this->_tablename . "  WHERE `ginger_order_id` = '" . $gingerOrderId . "'";
        $db = \JFactory::getDBO();
        $db->setQuery($query);
        $r = $db->loadObject();
        if (is_object($r)) {
            return (int)$r->virtuemart_order_id;
        }
        return 0;
    }

    /**
     * fetch vm order number for the payment table
     *
     * @param type $gingerOrderId
     * @return string
     * @since v1.0.0
     */
    protected function getOrderNumberByGingerOrder($gingerOrderId)
    {
        $query = "SELECT `order_number` FROM " . $this->_tablename . "  WHERE `ginger_order_id` = '" . $gingerOrderId . "'";
        $db = \JFactory::getDBO();
        $db->setQuery($query);
        $r = $db->loadObject();
        if (is_object($r)) {
            return (string)$r->order_number;
        }
        return 0;
    }

    /**
     *
     * @param string $html
     * @since v1.0.0
     */
    protected function processFalseOrderStatusResponse($html)
    {
        $mainframe = \JFactory::getApplication();
        $mainframe->enqueueMessage($html, 'error');
        $mainframe->redirect(\JRoute::_('index.php?option=com_virtuemart&view=cart', FALSE));
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
        $test = Bankconfig::BANK_PREFIX_UPPER . '_AFTERPAY_COUNTRIES_AVAILABLE';

        if ($name == Bankconfig::BANK_PREFIX . 'afterpay') {
            $table->$test = "NL, BE";
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

        if (isset($return)) {
            return 0;
        } else {
            return NULL;
        }
    }

    /**
     * @param $cart
     * @param $order
     * @return bool|null
     * @since v1.0.0
     */
    public function plgVmConfirmedOrder($cart, $order)
    {
        if (!($method = $this->getVmPluginMethod($order['details']['BT']->virtuemart_paymentmethod_id))) {
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

        $this->getPaymentCurrency($method, $order['details']['BT']->payment_currency_id);
        $currency_code_3 = shopFunctions::getCurrencyByID($method->payment_currency, 'currency_code_3');
        $email_currency = $this->getEmailCurrency($method);
        $totalInPaymentCurrency = vmPSPlugin::getAmountInCurrency($order['details']['BT']->order_total, $method->payment_currency);

        if (!empty($method->payment_info)) {
            $lang = JFactory::getLanguage();
            if ($lang->hasKey($method->payment_info)) {
                $method->payment_info = vmText::_($method->payment_info);
            }
        }

        $ginger_order = (new OrderBuilderRedefiner($order, $method, $cart, $this->payment_method))->buildOrder();
        $client = (new ClientBuilderRedefiner($this->methodParametersFactory()))->createClient();
        $bank_error = Bankconfig::BANK_PREFIX . '_LIB_ERROR_TRANSACTION';

        try {
            $response = $client->sendOrder($ginger_order);
        } catch (\Exception $exception) {
            $html = "<p>" . JText::_($bank_error) . "</p><p>Error: " . $exception->getMessage() . "</p>";
            $this->processFalseOrderStatusResponse($html);
        }
        if ($response->getStatus()->get() == 'error') {
            $html = "<p>" . JText::_($bank_error) . "</p><p>Error: " . current($response->toArray()['transactions']['customer_message']) . "</p>";
            $this->processFalseOrderStatusResponse($html);
        }
        if (array_key_exists('reason', current($response->toArray()['transactions']))) {
            $html = "<p>" . JText::_($bank_error) . "</p><p>Error: " . current($response->toArray()['transactions']['customer_message']) . "</p>";
            $this->processFalseOrderStatusResponse($html);
        }
        if (!$response->getCurrentTransaction()->getId()->get()) {
            $html = "<p>" . JText::_($bank_error) . "</p><p>Error: Response did not include id!</p>";
            $this->processFalseOrderStatusResponse($html);
        }
        if ($this->payment_method != 'bank-transfer') {
            if (!$response->getPaymentUrl()) {
                $html = "<p>" . JText::_($bank_error) . "</p><p>Error: Response did not include payment url!</p>";
                $this->processFalseOrderStatusResponse($html);
            }
        }
        JFactory::getSession()->clear(Bankconfig::BANK_PREFIX . 'ideal_issuer', 'vm'); //clear session values
        $dbValues['payment_name'] = $this->renderPluginName($method) . '<br />' . $method->payment_info;
        $dbValues['order_number'] = $order['details']['BT']->order_number;
        $dbValues['virtuemart_paymentmethod_id'] = $order['details']['BT']->virtuemart_paymentmethod_id;
        $dbValues['cost_per_transaction'] = $method->cost_per_transaction;
        $dbValues['cost_min_transaction'] = $method->cost_min_transaction;
        $dbValues['cost_percent_total'] = $method->cost_percent_total;
        $dbValues['payment_currency'] = $currency_code_3;
        $dbValues['email_currency'] = $email_currency;
        $dbValues['payment_order_total'] = $totalInPaymentCurrency['value'];
        $dbValues['tax_id'] = $method->tax_id;
        $dbValues['ginger_order_id'] = $response->getId()->get();
        $this->storePSPluginInternalData($dbValues);
        $virtuemart_order_id = $this->getOrderIdByGingerOrder($response->getId()->get());
        $virtuemart_order_number = $this->getOrderNumberByGingerOrder(vRequest::get('order_id'));
        $statusSucceeded = $this->updateOrder($response->getStatus()->get(), $virtuemart_order_id);

        if ($this->payment_method == 'afterpay') {
            $html = "<p>" . Helper::getOrderDescription($virtuemart_order_number) . "</p>";
            if ($statusSucceeded) {
                $this->clearSessionData();
                $this->emptyCart(null, $virtuemart_order_id);
                $html .= "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_LIB_THANK_YOU_FOR_YOUR_ORDER') . "</p>";
                vRequest::setVar('html', $html);
                return true;
            }

            $html .= "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_LIB_ERROR_STATUS') . "</p>";
            if ($response->getStatus()->get()) {
                $html .= "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_AFTERPAY_CANCELLED_STATUS_MSG') . "</p>";
            }
            $this->processFalseOrderStatusResponse($html);
        }

        if ($this->payment_method == 'bank-transfer') {
            if ($statusSucceeded) {
                $html = $this->renderByLayout('post_payment', array(
                    'total_to_pay' => $totalInPaymentCurrency['display'],
                    'reference' => $this->getGingerPaymentReference($response),
                    'description' => "<p>" . Helper::getOrderDescription($virtuemart_order_id) . "</p>",
                    'bank_information' => "IBAN: " . $this->getGingerPaymentIban($response) .
                        "<br/>BIC: " . $this->getGingerPaymentBic($response) .
                        "<br/>Account holder: " . $this->getGingerPaymentHolderName($response) .
                        "<br/>City: " . $this->getGingerPaymentHolderCity($response) .
                        "<br/>Country: " . $this->getGingerPaymentHolderCountry($response)
                ));
                $this->emptyCart(null, $virtuemart_order_id);
                vRequest::setVar('html', $html);
                return true;
            }
            $html = "<p>" . Helper::getOrderDescription($virtuemart_order_number) . "</p>" .
                "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_LIB_ERROR_STATUS') . "</p>";

            if ($this->payment_method == 'afterpay') {
                if ($response['status']) {
                    $html .= "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_AFTERPAY_CANCELLED_STATUS_MSG') . "</p>";
                }
            }
            $this->processFalseOrderStatusResponse($html);
        }
        JFactory::getApplication()->redirect($response->getPaymentUrl());
    }

    /**
     * Handle payment response
     *
     * @param string $payment_reponse
     * @param string $html
     * @return bool|null|string
     * @since v1.0.0
     */
    public function plgVmOnPaymentResponseReceived(&$html, &$payment_reponse)
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

        $params = $this->methodParametersFactory();

        $client = Ginger::createClient(
            Bankconfig::BANK_ENDPOINT,
            $params->apiKey(),
            ($params->bundleCaCert() == true) ?
                [
                    CURLOPT_CAINFO => Helper::getCaCertPath()
                ] : []
        );

        $gingerOrder = $client->getOrder(vRequest::get('order_id'));

        $virtuemart_order_id = $this->getOrderIdByGingerOrder(vRequest::get('order_id'));
        $virtuemart_order_number = $this->getOrderNumberByGingerOrder(vRequest::get('order_id'));
        $statusSucceeded = $this->updateOrder($gingerOrder['status'], $virtuemart_order_id);
        $html = "<p>" . Helper::getOrderDescription($virtuemart_order_number) . "</p>";

        if ($this->payment_method == 'ideal') {
            if ($this->isProcessingOrderNotConfirmedRedirect()) {
                $this->emptyCart(null, $virtuemart_order_id);
                $html .= "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_LIB_NO_BANK_RESPONSE') . "</p>";
                $this->processFalseOrderStatusResponse($html);
            }

            if ($gingerOrder['status'] === 'processing' && !$this->isProcessingOrderNotConfirmedRedirect()) {
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
                    'description' => "<p>" . Helper::getOrderDescription($virtuemart_order_id) . "</p>",
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
                $this->processFalseOrderStatusResponse($html);
            }

            $this->emptyCart(null, $virtuemart_order_id);
            $payment_reponse .= "<br>" . "Paid with " . $this->getDataByOrderId($virtuemart_order_id)->payment_name;
            vRequest::setVar('html', $html);
            vRequest::setVar('display_title', false);
            return true;
        }

        if ($statusSucceeded) {
            $payment_reponse .= "<br>" . "Paid with " . $this->getDataByOrderId($virtuemart_order_id)->payment_name;
            vRequest::setVar('html', $html);
            $this->emptyCart(null, $virtuemart_order_id);
            vRequest::setVar('html', $html);
            return true;
        }
        $html .= "<p>" . JText::_(Bankconfig::BANK_PREFIX . '_LIB_ERROR_STATUS') . "</p>";
        $this->processFalseOrderStatusResponse($html);
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

        $virtuemart_order_id = $this->getOrderIdByGingerOrder($input['order_id']);

        $this->updateOrder($gingerOrder['status'], $virtuemart_order_id);

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
        $this->getPaymentCurrency($method);

        $paymentCurrencyId = $method->payment_currency;
        return;
    }

    public function applePayDetection()
    {
        echo "<script>
            if(!window.ApplePaySession){
                document.cookie = 'ginger_apple_pay_status = false'
            } else {
                document.cookie = 'ginger_apple_pay_status = true'
            }
        </script>";

        return $_COOKIE['ginger_apple_pay_status'] === 'true';
    }

    /**
     *
     * @param \VirtueMartCart $cart
     * @param type $selected
     * @param type $htmlIn
     * @return boolean
     * @since v1.0.0
     */
    public function plgVmDisplayListFEPayment(\VirtueMartCart $cart, $selected = 0, &$htmlIn)
    {
        $currency_model = VmModel::getModel('currency');
        $displayCurrency = $currency_model->getCurrency( $this->product->product_currency );
        $currency = $displayCurrency->currency_code_3;

        if($this->payment_method == 'apple-pay' && !$this->applePayDetection()) return false;

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
    public function plgVmonSelectedCalculatePricePayment(\VirtueMartCart $cart, array &$cart_prices, &$cart_prices_name)
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
            $payment_name = $this->renderPluginName($this->_currentMethod);

            $this->setCartPrices($cart, $cart_prices, $this->_currentMethod);

            return true;
        }

        return $this->onSelectedCalculatePrice($cart, $cart_prices, $cart_prices_name);
    }

    /**
     * before order is created
     *
     * @param type $orderDetails
     */
    public function plgVmOnUserOrder(&$orderDetails)
    {
        return true;
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

            if ($this->isValidDate($dob) === false || $dob === null) {
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
     * Check should page after no response from the bank should be redirected
     *
     * @return bool
     * @since v1.0.0
     */
    protected function isProcessingOrderNotConfirmedRedirect()
    {
        return (bool)(vRequest::get('no_confirmation_redirect') !== null && vRequest::get('no_confirmation_redirect') == '1');
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

            if (!($currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
                return false;
            }
            return true;
        }

        if ($this->payment_method == 'afterpay') {
            if (!$this->selectedThisByMethodId($cart->virtuemart_paymentmethod_id)) {
                return null; // Another method was selected, do nothing
            }

            if (!($currentMethod = $this->getVmPluginMethod($cart->virtuemart_paymentmethod_id))) {
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
            $html .= '<a href="' . $this->gettermsAndConditionsUrlByCountry($country) . '" target="blank">' . JText::_($terms) . '</a>';
            return $html;
        }

        $client = (new ClientBuilderRedefiner($this->methodParametersFactory()))->createClient();

        if ($this->payment_method == 'ideal') {
            $issuers = $client->getIdealIssuers()->toArray();
            $html = '<select name="issuer" id="issuer" class="' . $this->_name . '">';
            foreach ($issuers as $issuer) {
                $html .= '<option value="' . $issuer['id'] . '">' . $issuer['name'] . "</option>";
            }
            $html .= "</select>";
            return $html;
        }
    }
    protected function gettermsAndConditionsUrlByCountry($country)
    {
        if (strtoupper($country) === self::BE_ISO_CODE) {
            return self::TERMS_CONDITION_URL_BE;
        }
        return self::TERMS_CONDITION_URL_NL;
    }

    /*--------------------------------------------------------------------*/
    //BankTransfer

    protected function getGingerPaymentIban($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_iban'];
    }

    protected function getGingerPaymentBic($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_bic'];
    }

    protected function getGingerPaymentHolderName($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_account_holder_name'];
    }

    protected function getGingerPaymentHolderCity($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_account_holder_city'];
    }

    protected function getGingerPaymentHolderCountry($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_account_holder_country'];
    }

    protected function getGingerPaymentReference($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['reference'];
    }

    /*--------------------------------------------------------------------*/
    //Klarnapaylater

    /**
     * on order status shipped
     *
     * @param array $_formData
     * @return boolean
     * @since v1.0.0
     */
    /*public function plgVmOnUpdateOrderPayment($_formData)
    {
        if (!($method = $this->getVmPluginMethod($_formData->virtuemart_paymentmethod_id))) {
            return null; // Another method was selected, do nothing
        }

        if (!$this->selectedThisElement($method->payment_element)) {
            return false;
        }
        try {
            if ($_formData->order_status === $this->methodParametersFactory()->statusCaptured() && $gingerOrderId = $this->getGingerOrderIdByOrderId($_formData->virtuemart_order_id)) {
                $ginger = $this->getGingerClient();
                $ginger_order = $ginger->getOrder($gingerOrderId);
                $transaction_id = !empty(current($ginger_order['transactions'])) ? current($ginger_order['transactions'])['id'] : null;
                $ginger->captureOrderTransaction($ginger_order['id'],$transaction_id);
            }
        } catch (\Exception $ex) {
            JFactory::getApplication()->enqueueMessage($ex->getMessage(), 'error');
            return false;
        }
        return true;
    }

    /**
     * Get Gigner order id from the plugin table
     *
     * @param int $orderId
     * @return string
     * @since v1.0.0
     */
    protected function getGingerOrderIdByOrderId($orderId)
    {
        $query = "SELECT `ginger_order_id` FROM " . $this->_tablename . "  WHERE `virtuemart_order_id` = '" . $orderId . "'";
        $db = \JFactory::getDBO();
        $db->setQuery($query);
        $r = $db->loadObject();
        if (is_object($r)) {
            return $r->ginger_order_id;
        }
        return null;
    }

    /**
     *
     * clear user seesion data
     * @since v1.1.0
     */
    protected function clearSessionData()
    {
        $session = JFactory::getSession();
        $session->set(Bankconfig::BANK_PREFIX . 'afterpay_gender', null, 'vm');
        $session->set(Bankconfig::BANK_PREFIX . 'afterpay_dob', null, 'vm');
        $session->set(Bankconfig::BANK_PREFIX . 'afterpay_terms_and_confditions', null, 'vm');
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

    /**
     * validate date of birth
     *
     * @param type $string
     * @return boolean
     * @since v1.1.0
     */
    protected function isValidDate($string)
    {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $string, $matches)) {
            return DateTime::createFromFormat('d-m-Y', $string) instanceof \DateTime;
        }
        return false;
    }
}
