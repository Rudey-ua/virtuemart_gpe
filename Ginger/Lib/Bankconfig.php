<?php

namespace Ginger\Lib;

class Bankconfig
{
    const PLUGIN_NAME = 'ems-online-virtuemart';

    const PLATFORM_NAME = 'VirtueMart';

    const BANK_PREFIX = 'emspay';

    const BANK_PREFIX_UPPER = 'EMSPAY';

    const BANK_ENDPOINT = 'https://api.online.emspay.eu';

    public function getPlatformVersion()
    {
        return '4.0.6';
    }
}
