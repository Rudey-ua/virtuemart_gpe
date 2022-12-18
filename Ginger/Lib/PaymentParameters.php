<?php

namespace Ginger\Lib;

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
    private $statusNew;
    private $statusPending;
    private $statusProcessing;
    private $statusError;
    private $statusCompleted;
    private $statusCanceled;
    private $statusExpired;
    private $statusAccepted;
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

    public function afterpayAllowedCountries()
    {
        if (empty($this->afterpayAllowedCountries)) {
            return $this->afterpayAllowedCountries;
        } else {
            $expCountries = array_map("trim", explode(',', $this->afterpayAllowedCountries));
            return $expCountries;
        }
    }
}
