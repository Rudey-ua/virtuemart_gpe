<?php

namespace Ginger\Lib;

use DateTime;
use JFactory;

class Helper
{
    const PHYSICAL = 'physical';
    const TERMS_CONDITION_URL_NL = 'https://www.afterpay.nl/nl/algemeen/betalen-met-afterpay/betalingsvoorwaarden';
    const TERMS_CONDITION_URL_BE = 'https://www.afterpay.be/be/footer/betalen-met-afterpay/betalingsvoorwaarden';
    const BE_ISO_CODE = 'BE';
    const PLATFORM_NAME = 'VirtueMart';
    const PLATFORM_VERSION = '?';

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

    public static function convertDateToAcceptedFormat($stringDate)
    {
        if (preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', $stringDate, $matches)) {
            $date =  DateTime::createFromFormat('d-m-Y', $stringDate);
            return $date->format('Y-m-d');
        }
        return null;
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

    public static function applePayDetection()
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

    public static function gettermsAndConditionsUrlByCountry($country)
    {
        if (strtoupper($country) === Helper::BE_ISO_CODE)
        {
            return Helper::TERMS_CONDITION_URL_BE;
        }
        return Helper::TERMS_CONDITION_URL_NL;
    }

    /**
     *
     * @param string $newStatus
     * @param int $virtuemart_order_id
     * @since v1.0.0
     */
    public static function updateOrderStatus($newStatus, $virtuemart_order_id)
    {
        $modelOrder = \VmModel::getModel('orders');
        $order['order_status'] = $newStatus;
        $order['customer_notified'] = 1;
        $order['comments'] = '';
        $modelOrder->updateStatusForOneOrder($virtuemart_order_id, $order, true);
    }

    /**
     * fetch vm order id for the payment table
     *
     * @param type $gingerOrderId
     * @return int
     * @since v1.0.0
     */
    public static function getOrderIdByGingerOrder($gingerOrderId, $table)
    {
        $query = "SELECT `virtuemart_order_id` FROM " . $table . "  WHERE `ginger_order_id` = '" . $gingerOrderId . "'";
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
    public static function getOrderNumberByGingerOrder($gingerOrderId, $table)
    {
        $query = "SELECT `order_number` FROM " . $table . "  WHERE `ginger_order_id` = '" . $gingerOrderId . "'";
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
    public static function processFalseOrderStatusResponse($html)
    {
        $mainframe = \JFactory::getApplication();
        $mainframe->enqueueMessage($html, 'error');
        $mainframe->redirect(\JRoute::_('index.php?option=com_virtuemart&view=cart', FALSE));
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

    public static function getGingerPaymentIban($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_iban'];
    }

    public static function getGingerPaymentBic($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_bic'];
    }

    public static function getGingerPaymentHolderName($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_account_holder_name'];
    }

    public static function getGingerPaymentHolderCity($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_account_holder_city'];
    }

    public static function getGingerPaymentHolderCountry($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['creditor_account_holder_country'];
    }

    public static function getGingerPaymentReference($gingerOrder)
    {
        return current($gingerOrder->toArray()['transactions'])['payment_method_details']['reference'];
    }
}
