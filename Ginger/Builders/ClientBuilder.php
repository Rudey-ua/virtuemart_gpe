<?php

namespace Ginger\Builders;

use GingerPluginSdk\Client;
use GingerPluginSdk\Properties\ClientOptions;
use Ginger\Lib\Bankconfig;

class ClientBuilder
{
    public function __construct($params)
    {
        $this->params = $params;
    }

    public function createClient()
    {
        if(!empty($this->params->apiKey()))
        {
            return new Client(
                new ClientOptions(
                    endpoint: Bankconfig::BANK_ENDPOINT,
                    useBundle: $this->params->bundleCaCert(),
                    apiKey: $this->params->apiKey()
                )
            );
        }
    }
}
