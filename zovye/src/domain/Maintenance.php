<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\domain;

use zovye\base\ModelFactory;
use function zovye\m;

class Maintenance extends Base
{
    public static function model(): ModelFactory
    {
        return m('maintenance');
    }
}