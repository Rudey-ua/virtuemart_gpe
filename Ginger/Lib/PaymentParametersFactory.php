<?php

namespace Ginger\Lib;

/**
 * PaymentParameters Factory
 *
 * @author bojan
 */
class PaymentParametersFactory {

    /**
     * resolve payment method params
     *
     * @param string $methodName
     * @return \Ginger\Lib\PaymentParameters
     */
    public static function getConfig($methodName) {
        try {
            $q = "SELECT payment_params FROM #__virtuemart_paymentmethods WHERE payment_element='" . $methodName . "'";
            $db = \JFactory::getDBO();
            $db->setQuery($q);
            $params = $db->loadResult();

            $payment_params = explode("|", $params);

            $paymentParams = new PaymentParameters;
            $refObject = new \ReflectionObject($paymentParams);

            foreach ($payment_params as $payment_param) {
                if (empty($payment_param)) {
                    continue;
                }
                $param = explode('=', $payment_param);
                $payment_params[$param[0]] = substr($param[1], 1, -1);

                if(isset(PaymentParameters::$mapping[$param[0]]))
                {
                    if ($refObject->hasProperty(PaymentParameters::$mapping[$param[0]])) {
                        $refProperty = $refObject->getProperty(PaymentParameters::$mapping[$param[0]]);
                        $refProperty->setAccessible(true);
                        $refProperty->setValue($paymentParams, trim(substr($param[1], 1, -1)));
                    }
                }
            }

            return $paymentParams;
        } catch (\Exception $e) {
            throw new \RuntimeException();
        }
    }

}
