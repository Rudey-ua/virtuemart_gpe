<?php

require_once __DIR__ . '/MockedCMS.php';
require_once __DIR__ . '/../Builders/OrderBuilder.php';
require_once __DIR__ . '/../Lib/Bankconfig.php';
require_once __DIR__ . '/../Lib/Helper.php';


use Ginger\Lib\Helper;
use PHPUnit\Framework\TestCase;
use Ginger\Builders\OrderBuilder;


class CreateOrderTest extends TestCase
{
    public $orderBuilder;

    public function __construct(?string $name = null, array $data = [], $dataName = '')
    {
        parent::__construct($name, $data, $dataName);

        $this->orderBuilder = new OrderBuilder(
            VM_Order::OrderObject(),
            VM_Order::MethodObject(),
            VM_Order::CartObject(),
            VM_Order::PaymentObject()
        );
    }

    function testGingerGetTotalInCents()
    {
        $expectedTotalInCents = 1000;
        $realTotalInCents = $this->orderBuilder->getTotalInCents();
        $this->assertSame($expectedTotalInCents, $realTotalInCents);
    }

    function testGingerGetCurrency()
    {
        $expectedCurrency = "EUR";
        $realCurrency = $this->orderBuilder->getCurrency();
        $this->assertSame($expectedCurrency, $realCurrency);
    }

    function testGingerGetIssuer()
    {
        $expectedIssuer = "BANKNL2Y";
        $realIssuer = $this->orderBuilder->getIssuer();
        $this->assertSame($expectedIssuer, $realIssuer);
    }

    function testGingerGetOrderId()
    {
        $expectedOrderId = 32;
        $realOrderId = $this->orderBuilder->getOrderId();
        $this->assertSame($expectedOrderId, $realOrderId);
    }

    function testGingerGetDescription()
    {
        $expectedDescription = "Your order 32 at Test";
        $realDescription = $this->orderBuilder->getDescription();
        $this->assertSame($expectedDescription, $realDescription);
    }

    function testGingerGetPlugin()
    {
        $expectedPlugin = [
            'plugin' => 'Joomla Virtuemart v1.3.1'
        ];

        $realPlugin = $this->orderBuilder->getPlugin();
        $this->assertSame($expectedPlugin, $realPlugin);
    }

    function testGingerGetWebhook()
    {
        $expectedWebhook = "http://localhost:8000/?option=com_virtuemart&view=pluginresponse&task=pluginnotification&pm=12";
        $realWebhook = $this->orderBuilder->getWeebhook();
        $this->assertSame($expectedWebhook, $realWebhook);
    }

    function testGingerGetReturnUrl()
    {
        $expectedWebhook = "http://localhost:8000/?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=12";
        $realWebhook = $this->orderBuilder->getReturnUrl();
        $this->assertSame($expectedWebhook, $realWebhook);
    }

    function testPreparePaymentMethodDetails()
    {
        $expectedPaymentMethodDetails = [
            'issuer_id' => 'BANKNL2Y',
            'cutomer' => 'cutomer'
        ];
        $realPaymentMethodDetails = $this->orderBuilder->preparePaymentMethodDetails()->toArray();
           $this->assertSame($expectedPaymentMethodDetails, $realPaymentMethodDetails);
    }

    function testPrepareGetTransactions()
    {
        $expectedPaymentMethodDetails = [
            [
                'payment_method' => '123',
                'payment_method_details' =>
                [
                    'issuer_id' => 'BANKNL2Y',
                    'cutomer' => 'cutomer'
                ]
            ]
        ];
        $realPaymentMethodDetails = $this->orderBuilder->getTransactions()->toArray();
        $this->assertSame($expectedPaymentMethodDetails, $realPaymentMethodDetails);
    }

    function testGingerGetAdditionalAddress()
    {
        $expectedPaymentMethodDetails = [
            [
                'address_type' => 'customer',
                'postal_code' => '123',
                'country' => 'NL',
                'address' => '123'
            ],
            [
                'address_type' => 'billing',
                'postal_code' => '123',
                'country' => 'NL',
                'address' => '123'
            ]
        ];
        $realPaymentMethodDetails = $this->orderBuilder->getAdditionalAddress()->toArray();
        $this->assertSame($expectedPaymentMethodDetails, $realPaymentMethodDetails);
    }

    function testGingerGetCustomer()
    {
        $expectedCustomer = [
            'last_name' => 'Kostenko',
            'first_name' => 'Max',
            'country' => 'NL',
            'phone_numbers' => [
                '0854232191'
            ],
            'merchant_customer_id' => '224321',
            'ip_address' => '',
            'additional_addresses' => [
                [
                    'address_type' => 'customer',
                    'postal_code' => '123',
                    'country' => 'NL',
                    'address' => '123',
                ],
                [
                    'address_type' => 'billing',
                    'postal_code' => '123',
                    'country' => 'NL',
                    'address' => '123'
                ]
            ],
            'email_address' => 'kostenko@gmail.com',
            'locale' => 'en_en'
        ];
        $realCustomer = $this->orderBuilder->getCustomer()->toArray();
        $this->assertSame($expectedCustomer, $realCustomer);
    }

    public function testGingerGetExtra()
    {
        $expectedPaymentMethodDetails = ['fields' => [
            'user_agent' => null,
            'platform_name' => Helper::PLATFORM_NAME,
            'platform_version' => '4.0.6',
            'plugin_name' => \Ginger\Lib\Bankconfig::PLUGIN_NAME,
            'plugin_version' => 'Joomla Virtuemart v1.3.1',
        ]];
        $realPaymentMethodDetails = $this->orderBuilder->getExtra()->toArray();
        $this->assertSame($expectedPaymentMethodDetails, $realPaymentMethodDetails);
    }

    public function testGingerGetOrderLines()
    {
        $expectedOrderLines = [
            [
                'type' => 'physical',
                'merchant_order_line_id' => '86349643',
                'name' => 'TestProductName',
                'quantity' => 404,
                'amount' => 1000,
                'vat_percentage' => 9999,
                'currency' => 'EUR'
            ]
        ];
        $realOrderLines = $this->orderBuilder->getOrderLines()->toArray();
        $this->assertSame($expectedOrderLines, $realOrderLines);
    }

    public function testGingerBuildOrder()
    {
        $expectedOrder = [
            'webhook_url' => 'http://localhost:8000/?option=com_virtuemart&view=pluginresponse&task=pluginnotification&pm=12',
            'return_url' => 'http://localhost:8000/?option=com_virtuemart&view=pluginresponse&task=pluginresponsereceived&pm=12',
            'merchant_order_id' => '32',
            'currency' => 'EUR',
            'amount' => 1000,
            'transactions' => [
                [
                    'payment_method' => '123',
                    'payment_method_details' => [
                        'issuer_id' => 'BANKNL2Y',
                        'cutomer' => 'cutomer'
                    ]
                ]
            ],
            'customer' => [
                'last_name' => 'Kostenko',
                'first_name' => 'Max',
                'country' => 'NL',
                'phone_numbers' => ['0854232191'],
                'merchant_customer_id' => '224321',
                'ip_address' => '',
                'additional_addresses' => [
                    [
                        'address_type' => 'customer',
                        'postal_code' => '123',
                        'country' => 'NL',
                        'address' => '123'
                    ],
                    [
                        'address_type' => 'billing',
                        'postal_code' => '123',
                        'country' => 'NL',
                        'address' => '123'
                    ]
                ],
                'email_address' => 'kostenko@gmail.com',
                'locale' => 'en_en'
            ],
            'order_lines' => [
                [
                    'type' => 'physical',
                    'merchant_order_line_id' => '86349643',
                    'name' => 'TestProductName',
                    'quantity' => 404,
                    'amount' => 1000,
                    'vat_percentage' => 9999,
                    'currency' => 'EUR'
                ]
            ],
            'extra' => [
                'fields' => [
                    'user_agent' => null,
                    'platform_name' => 'VirtueMart',
                    'platform_version' => '4.0.6',
                    'plugin_name' => 'ems-online-virtuemart',
                    'plugin_version' => 'Joomla Virtuemart v1.3.1'
                ]
            ],
            'description' => 'Your order 32 at Test'
        ];
        $realOrder = $this->orderBuilder->buildOrder()->toArray();
        $this->assertSame($expectedOrder, $realOrder);
    }

}