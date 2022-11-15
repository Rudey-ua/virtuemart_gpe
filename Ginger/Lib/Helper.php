<?php

namespace Ginger\Lib;

use JFactory;

class Helper
{
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
     * Get CA certificate path
     *
     * @return bool|string
     */
    public static function getCaCertPath(){
        return realpath(JPATH_LIBRARIES . '/' . Bankconfig::BANK_PREFIX . '/assets/cacert.pem');
    }

    /**
     * Returns a new array with all elements which have a null value removed.
     *
     * @param array $array
     * @return array
     */
    public static function withoutNullValues(array $array)
    {
        static $fn = __FUNCTION__;

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::$fn($array[$key]);
            }

            if (empty($array[$key]) && $array[$key] !== '0' && $array[$key] !== 0) {
                unset($array[$key]);
            }
        }

        return $array;
    }

    /**
     * @return string
     */
    public static function getPaymentCurrency()
    {
        return 'EUR';
    }
}
