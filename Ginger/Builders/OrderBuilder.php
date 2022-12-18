<?php

namespace Ginger\Builders;

use Ginger\Lib\GingerVmPaymentPlugin;
use Ginger\Lib\Helper;
use GingerPluginSdk\Collections\AdditionalAddresses;
use GingerPluginSdk\Collections\OrderLines;
use GingerPluginSdk\Collections\PhoneNumbers;
use GingerPluginSdk\Collections\Transactions;
use GingerPluginSdk\Entities\Address;
use GingerPluginSdk\Entities\Customer;
use GingerPluginSdk\Entities\Extra;
use GingerPluginSdk\Entities\Line;
use GingerPluginSdk\Entities\PaymentMethodDetails;
use GingerPluginSdk\Properties\Amount;
use GingerPluginSdk\Properties\Birthdate;
use GingerPluginSdk\Properties\Country;
use GingerPluginSdk\Properties\Currency;
use GingerPluginSdk\Properties\EmailAddress;
use GingerPluginSdk\Properties\Locale;
use GingerPluginSdk\Properties\VatPercentage;
use JFactory;
use ShopFunctions;
use vmPSPlugin;
use GingerPluginSdk\Entities\Order;
use Ginger\Lib\Bankconfig;
use GingerPluginSdk\Entities\Transaction;

class OrderBuilder
{
    public function __construct($order, $method, $cart, $payment_method)
    {
        $this->order = $order;
        $this->method = $method;
        $this->cart = $cart;
        $this->payment_method = $payment_method;
    }

    public function getTotalInCents()
    {
        return Helper::getAmountInCents(vmPSPlugin::getAmountInCurrency($this->order['details']['BT']->order_total, $this->method->payment_currency)['value']);
    }

    public function getCurrency()
    {
        return shopFunctions::getCurrencyByID($this->method->payment_currency, 'currency_code_3');
    }

    public function getIssuer()
    {
        return JFactory::getSession()->get(Bankconfig::BANK_PREFIX . 'ideal_issuer', null, 'vm');
    }

    public function getOrderId()
    {
        return $this->order['details']['BT']->virtuemart_order_id;
    }

    public function getDescription()
    {
        return Helper::getOrderDescription($this->getOrderId());
    }

    public function getPlugin()
    {
        return ['plugin' => Helper::getPluginVersion()];
    }

    public function getWeebhook()
    {
        return sprintf('%s?option=com_virtuemart&view=pluginresponse&task=pluginnotification&pm=%d', \JURI::base(), intval($this->order['details']['BT']->virtuemart_paymentmethod_id));
    }

    public function getReturnUrl()
    {
        return sprintf('%s?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=%d', \JURI::base(), intval($this->order['details']['BT']->virtuemart_paymentmethod_id));
    }

    public function preparePaymentMethodDetails($verifiedTerms = null): PaymentMethodDetails
    {
        return new PaymentMethodDetails(
            [
                'issuer_id' => $this->getIssuer(),
                'verified_terms_of_service' => $this->getTermsAndConditions(),
                'cutomer' => 'cutomer'
            ]);
    }

    public function getTransactions(): Transactions
    {
        return new Transactions(
            new Transaction(
                paymentMethod: $this->payment_method,
                paymentMethodDetails: $this->preparePaymentMethodDetails()
            )
        );
    }

    public function getAddress($billing_info, $addressType): Address
    {
        return new Address(
            addressType: $addressType,
            postalCode: $billing_info->zip,
            country: new Country(shopFunctions::getCountryByID($this->order['details']['BT']->virtuemart_country_id, 'country_2_code'))
        );
    }

    public function getAdditionalAddress(): AdditionalAddresses
    {
        return new AdditionalAddresses(
            $this->getAddress($this->order['details']['BT'], 'customer'),
            $this->getAddress($this->order['details']['BT'], 'billing'),
        );
    }

    private function getBirthdate()
    {
        return new Birthdate(\JFactory::getSession()->get(Bankconfig::BANK_PREFIX.'afterpay_dob', null, 'vm'));
    }

    private function getGender()
    {
        return \JFactory::getSession()->get(Bankconfig::BANK_PREFIX.'afterpay_gender', null, 'vm');
    }

    private function getTermsAndConditions()
    {
        return JFactory::getSession()->get(Bankconfig::BANK_PREFIX .'afterpay_terms_and_confditions', null, 'vm') == 'on';
    }

    public function getCustomer(): Customer
    {
        return new Customer(
            additionalAddresses: $this->getAdditionalAddress(),
            firstName: $this->order['details']['BT']->first_name,
            lastName: $this->order['details']['BT']->last_name,
            emailAddress: new EmailAddress($this->order['details']['BT']->email),
            gender: $this->getGender(),
            phoneNumbers: new PhoneNumbers($this->order['details']['BT']->phone_1),
            birthdate: $this->getBirthdate() ? new Birthdate($this->getBirthdate()) : null,
            ipAddress: filter_var(\JFactory::getApplication()->input->server->get('REMOTE_ADDR'), FILTER_VALIDATE_IP),
            locale: new Locale(Helper::getLocale()),
            merchantCustomerId: $this->order['details']['BT']->virtuemart_user_id
        );
    }

    public function getExtra() : Extra
    {
        return new Extra([
            'fields' => [
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'platform_name' => Helper::PLATFORM_NAME,
                'platform_version' => Helper::PLATFORM_VERSION,
                'plugin_name' => Bankconfig::PLUGIN_NAME,
                'plugin_version' => Helper::getPluginVersion(),
            ]
        ]);
    }

    public function getOrderLines(): OrderLines
    {
        $orderLines = new OrderLines();
        $orderLines->addLine(new Line(
            type: Helper::PHYSICAL,
            merchantOrderLineId: current($this->cart->products)->virtuemart_product_id,
            name: current($this->cart->products)->product_name,
            quantity: current($this->cart->products)->quantity,
            amount: new Amount($this->getTotalInCents()),
            vatPercentage: new VatPercentage(current($this->cart->products)->prices['salesPrice']),
            currency: new Currency($this->getCurrency()),
        ));
        return $orderLines;
    }

        public function buildOrder(): Order
    {
        return new Order(
            currency: new Currency($this->getCurrency()),
            amount: new Amount($this->getTotalInCents()),
            transactions: $this->getTransactions(),
            customer: $this->getCustomer(),
            orderLines: $this->getOrderLines(),
            extra: $this->getExtra(),
            webhook_url: $this->getWeebhook(),
            return_url: $this->getReturnUrl(),
            merchantOrderId: $this->getOrderId(),
            description: $this->getDescription()
        );
    }
}
