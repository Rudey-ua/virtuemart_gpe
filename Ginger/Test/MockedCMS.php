<?php

define("JPATH_SITE", '123');

class VM_Order
{
    public static function OrderObject()
    {
        return [
            'details' => [
                'BT' => (object)[
                    'virtuemart_order_id' => 32,
                    'order_total' => 100,
                    'virtuemart_paymentmethod_id' => 12,
                    'first_name' => 'Max',
                    'last_name' => 'Kostenko',
                    'email' => 'kostenko@gmail.com',
                    'phone_1' => '0854232191',
                    'virtuemart_user_id' => '224321',
                    'zip' => '123'
                ]
            ],
        ];
    }

    public static function CartObject()
    {
        return (object)[
            'products' => [
                (object)[
                    'virtuemart_product_id' => '86349643',
                    'product_name' => 'TestProductName',
                    'quantity' => '404',
                    'prices' => [
                        'salesPrice' => '9999'
                    ]
                ]
            ]
        ];
    }

    public static function PaymentObject()
    {
        return 123;
    }


    public static function MethodObject()
    {
        return (object)[
            'payment_currency' => 47
        ];
    }
}

class shopFunctions
{
    public static function getCurrencyByID($id, $fld = 'currency_name')
    {
        if($id == 47 && $fld == 'currency_code_3'){
            return 'EUR';
        }
    }
}

class JFactory
{
    public static function getConfig()
    {
        return new Config();
    }

    public static function getSession()
    {
        return new Session();
    }

    public static function getXML()
    {
        return (object)[
          'plugin' => 'Joomla Virtuemart',
            'version' => '1.3.1'
        ];
    }

    public static function getApplication()
    {
        return (object)[
            'input' => (object)[
                'server' => new Remote()
            ]
        ];
    }

    public static function getLanguage()
    {
        return new Language();
    }
}

class Language
{
    public function getTag()
    {
        return 'en-en';
    }
}

class Config
{
    public function get($path)
    {
        if($path == 'sitename') {
            return 'Test';
        }
    }
}

class Remote
{
    public function get()
    {
        return 'TEST SERVER !@#';
    }
}

class Session
{
    public function get()
    {
        return 'BANKNL2Y';
    }
}

class JText
{
    public static function _()
    {
        return "Your order %s at %s";
    }
}

class JURI
{
    public static function base()
    {
        return 'http://localhost:8000/';
    }
}

class vmPSPlugin
{
    public static function getAmountInCurrency()
    {
        $return = array();
        $return['value'] = 10;
        $return['display'] = 'Cents';

        return $return;
    }    
}






