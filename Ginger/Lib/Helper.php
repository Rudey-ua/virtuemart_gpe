<?php

namespace Ginger\Lib;

use DateTime;
use Ginger\Redefiners\ClientBuilderRedefiner;
use JFactory;
use JText;
use vRequest;

class Helper
{
    const TERMS_CONDITION_URL_NL = 'https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden';
    const TERMS_CONDITION_URL_BE = 'https://www.afterpay.be/be/footer/betalen-met-afterpay/betalingsvoorwaarden';
    const BE_ISO_CODE = 'BE';
    const PLATFORM_NAME = 'VirtueMart';
    const PLATFORM_VERSION = '4.0.6';

    /**
     * GINGER_ENDPOINT used for create Ginger client
     */
    const PHYSICAL = 'physical';

    /**
     * @param string $amount
     * @return int
     * @since v1.0.0
     */
    public static function getAmountInCents($amount)
    {
        return (int) round($amount * 100);
    }

    /**
     * @return mixed
     * @since v1.0.0
     */
    public static function getLocale()
    {
        $lang = JFactory::getLanguage();
        return str_replace('-', '_', $lang->getTag());
    }

    /**
     * Method obtains plugin information from the manifest file
     *
     * @param string $name
     * @return string
     * @since v1.0.0
     */
    public static function getPluginVersion()
    {
        $xml = JFactory::getXML(JPATH_SITE."/administrator/manifests/libraries/". Bankconfig::BANK_PREFIX . ".xml");

        return sprintf('Joomla Virtuemart v%s', (string) $xml->version);
    }

    /**
     * @param string $orderId
     * @return string
     * @since v1.0.0
     */
    public static function getOrderDescription($orderId)
    {
        return sprintf(\JText::_(Bankconfig::BANK_PREFIX . "_LIB_ORDER_DESCRIPTION"), $orderId, JFactory::getConfig()->get('sitename'));
    }

    /**
     * @param string $orderId
     * @return string
     * @since v1.0.0
     */
    public static function getReturnUrl($orderId)
    {
        return sprintf('%s?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=%d', \JURI::base(), intval($orderId));
    }

    /**
     * validate date of birth
     *
     * @param type $string
     * @return boolean
     * @since v1.1.0
     */
    static function isValidDate($string)
    {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $string, $matches)) {
            return DateTime::createFromFormat('d-m-Y', $string) instanceof \DateTime;
        }
        return false;
    }

    /**
     *
     * clear user seesion data
     * @since v1.1.0
     */
    static function clearSessionData()
    {
        $session = JFactory::getSession();
        $session->set(Bankconfig::BANK_PREFIX . 'afterpay_gender', null, 'vm');
        $session->set(Bankconfig::BANK_PREFIX . 'afterpay_dob', null, 'vm');
        $session->set(Bankconfig::BANK_PREFIX . 'afterpay_terms_and_confditions', null, 'vm');
    }

    /*--------------------------------------------------------------------*/
    //BankTransfer

    static function getGingerPaymentIban($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_iban'];
    }

    static function getGingerPaymentBic($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_bic'];
    }

    static function getGingerPaymentHolderName($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_account_holder_name'];
    }

    static function getGingerPaymentHolderCity($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_account_holder_city'];
    }

    static function getGingerPaymentHolderCountry($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_account_holder_country'];
    }

    static function getGingerPaymentReference($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['reference'];
    }

    static function gettermsAndConditionsUrlByCountry($country)
    {
        if (strtoupper($country) === Helper::BE_ISO_CODE) {
            return Helper::TERMS_CONDITION_URL_BE;
        }
        return Helper::TERMS_CONDITION_URL_NL;
    }

    static function applePayDetection()
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
     * Fields to create the payment table
     *
     * @return array SQL Fileds
     * @since v1.0.0
     */
    static function getTableSQLFields()
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
     * Check should page after no response from the bank should be redirected
     *
     * @return bool
     * @since v1.0.0
     */
    static function isProcessingOrderNotConfirmedRedirect()
    {
        return (bool)(vRequest::get('no_confirmation_redirect') !== null && vRequest::get('no_confirmation_redirect') == '1');
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
            $vendorModel = \VmModel::getModel('vendor');
            $vendor = $vendorModel->getVendor($method->virtuemart_vendor_id);
            $method->payment_currency = $vendor->vendor_currency;
            return $method->payment_currency;
        } else {

            $vendorModel = \VmModel::getModel('vendor');
            $vendorCurrencies = $vendorModel->getVendorAndAcceptedCurrencies($method->virtuemart_vendor_id);

            if (!$selectedUserCurrency) {
                if ($method->payment_currency == -1) {
                    $mainframe = \JFactory::getApplication();
                    $selectedUserCurrency = $mainframe->getUserStateFromRequest("virtuemart_currency_id", 'virtuemart_currency_id', vRequest::getInt('virtuemart_currency_id', $vendorCurrencies['vendor_currency']));
                } else {
                    $selectedUserCurrency = $method->payment_currency;
                }
            }
            $vendorCurrencies['all_currencies'] = explode(',', $vendorCurrencies['all_currencies']);
            (in_array($selectedUserCurrency, $vendorCurrencies['all_currencies'])) ? $method->payment_currency = $selectedUserCurrency : $method->payment_currency = $vendorCurrencies['vendor_currency'];
            return $method->payment_currency;
        }
    }

    /**
     *
     * @param string $html
     * @since v1.0.0
     */
    static function processFalseOrderStatusResponse($html)
    {
        $mainframe = \JFactory::getApplication();
        $mainframe->enqueueMessage($html, 'error');
        $mainframe->redirect(\JRoute::_('index.php?option=com_virtuemart&view=cart', FALSE));
    }

    /**
     *
     * @param string $newStatus
     * @param int $virtuemart_order_id
     * @since v1.0.0
     */
    static function updateOrderStatus($newStatus, $virtuemart_order_id)
    {
        $modelOrder = \VmModel::getModel('orders');
        $order['order_status'] = $newStatus;
        $order['customer_notified'] = 1;
        $order['comments'] = '';
        $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, TRUE);
    }


    static function getOrderIdByGingerOrder($gingerOrderId, $tableName)
    {
        $query = "SELECT `virtuemart_order_id` FROM " . $tableName . "  WHERE `ginger_order_id` = '" . $gingerOrderId . "'";
        $db = \JFactory::getDBO();
        $db->setQuery($query);
        $response = $db->loadObject();
        if (is_object($response)) {
            return (int)$response->virtuemart_order_id;
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
    static function getOrderNumberByGingerOrder($gingerOrderId, $tableName)
    {
        $query = "SELECT `order_number` FROM " . $tableName . "  WHERE `ginger_order_id` = '" . $gingerOrderId . "'";
        $db = \JFactory::getDBO();
        $db->setQuery($query);
        $response = $db->loadObject();
        if (is_object($response)) {
            return (string)$response->order_number;
        }
        return 0;
    }
}
