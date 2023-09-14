<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\domain;

use zovye\base\ModelFactory;
use function zovye\m;

class PayLogs extends Base
{
    public static function model(): ModelFactory
    {
        return m('pay_logs');
    }
}