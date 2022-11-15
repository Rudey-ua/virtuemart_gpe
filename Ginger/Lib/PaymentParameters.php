<?php

namespace Ginger\Lib;

/**
 * Paymentparameters
 *
 * @author GingerPayments
 */
class PaymentParameters
{
    public static $mapping = [
        Bankconfig::BANK_PREFIX_UPPER . '_API_KEY' => 'apiKey',
        Bankconfig::BANK_PREFIX_UPPER .'_LIB_BUNDLE_CA_CERT' => 'bundleCaCert',
        Bankconfig::BANK_PREFIX_UPPER .'_ALLOW_NOTIFICATIONS_FROM_X' => 'allowNotification',
        Bankconfig::BANK_PREFIX_UPPER .'_STATUS_NEW' => 'statusNew',
        Bankconfig::BANK_PREFIX_UPPER .'_STATUS_PENDING' => 'statusPending',
        Bankconfig::BANK_PREFIX_UPPER .'_STATUS_PROCESSING' => 'statusProcessing',
        Bankconfig::BANK_PREFIX_UPPER .'_STATUS_ERROR' => 'statusError',
        Bankconfig::BANK_PREFIX_UPPER .'_STATUS_COMPLETED' => 'statusCompleted',
        Bankconfig::BANK_PREFIX_UPPER .'_STATUS_CANCELED' => 'statusCanceled',
        Bankconfig::BANK_PREFIX_UPPER .'_STATUS_EXPIRED' => 'statusExpired',
        Bankconfig::BANK_PREFIX_UPPER .'_STATUS_ACCEPTED' => 'statusAccepted',
        Bankconfig::BANK_PREFIX_UPPER .'_STATUS_CAPTURED' => 'statusCaptured',
        Bankconfig::BANK_PREFIX_UPPER .'_ALLOWED_IP_ADDRESSES' => 'allowedIpAddresses',
        Bankconfig::BANK_PREFIX_UPPER .'_TEST_API_KEY' => 'testApiKey',
        Bankconfig::BANK_PREFIX_UPPER .'_AFTERPAY_TEST_APIKEY' => 'afterpayTestApiKey',
        Bankconfig::BANK_PREFIX_UPPER .'_AFTERPAY_ALLOWED_IP_ADDRESSES' => 'afterpayAllowedIpAddresses',
        Bankconfig::BANK_PREFIX_UPPER .'_AFTERPAY_COUNTRIES_AVAILABLE' => 'afterpayAllowedCountries'
    ];
    private $apiKey;
    private $bundleCaCert;
    private $allowNotification;
    private $statusNew;
    private $statusPending;
    private $statusProcessing;
    private $statusError;
    private $statusCompleted;
    private $statusCanceled;
    private $statusExpired;
    private $statusAccepted;
    private $statusCaptured;
    private $allowedIpAddresses;
    private $testApiKey;
    private $afterpayTestApiKey;
    private $afterpayAllowedIpAddresses;
    private $afterpayAllowedCountries;

    public function apiKey()
    {
        return $this->apiKey;
    }

    public function bundleCaCert()
    {
        return boolval($this->bundleCaCert);
    }

    public function allowNotification()
    {
        return $this->allowNotification;
    }

    public function statusNew()
    {
        return $this->statusNew;
    }

    public function statusPending()
    {
        return $this->statusPending;
    }

    public function statusProcessing()
    {
        return $this->statusProcessing;
    }

    public function statusError()
    {
        return $this->statusError;
    }

    public function statusCompleted()
    {
        return $this->statusCompleted;
    }

    public function statusCanceled()
    {
        return $this->statusCanceled;
    }

    public function statusExpired()
    {
        return $this->statusExpired;
    }

    public function statusAccepted()
    {
        return $this->statusAccepted;
    }

    public function statusCaptured()
    {
        return $this->statusCaptured;
    }

    public function getAfterpayTestApiKey() {
        return $this->afterpayTestApiKey;
    }

    public function isApiKeyValid()
    {
        return $this->apiKey !== null && strlen($this->apiKey) > 0;
    }

    /**
     * id addresses for klarna
     *
     * @return null|array
     */
    public function allowedIpAddresses()
    {
        if (empty($this->allowedIpAddresses)) {
            return null;
        }
        $addresses = explode(',', $this->allowedIpAddresses);
        array_walk($addresses,
                function(&$val) {
                    return trim($val);
                });
        return $addresses;
    }

    /**
     * id addresses for afterpay
     *
     * @return null|array
     */
    public function afterpayAllowedIpAddresses()
    {
        if (empty($this->afterpayAllowedIpAddresses)) {
            return null;
        }
        $addresses = explode(',', $this->afterpayAllowedIpAddresses);
        array_walk($addresses,
                function(&$val) {
                    return trim($val);
                });
        return $addresses;
    }

    /**
     * countries available for afterpay
     *
     * @return null|array
     */
    public function afterpayAllowedCountries()
    {
        if (empty($this->afterpayAllowedCountries)) {
            return $this->afterpayAllowedCountries;
        } else {
            $expCountries = array_map("trim", explode(',', $this->afterpayAllowedCountries));
            return $expCountries;
        }
    }

    public function testApiKey()
    {
        return $this->testApiKey;
    }

    public function getKlarnaPayLaterApiKey()
    {
        if (!empty($this->testApiKey)) {
            return $this->testApiKey;
        }
        return $this->apiKey;
    }

     public function getAfterpayApiKey()
    {
        if (!empty($this->afterpayTestApiKey)) {
            return $this->afterpayTestApiKey;
        }
        return $this->apiKey;
    }

}
