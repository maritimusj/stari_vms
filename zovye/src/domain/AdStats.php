<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\domain;

use zovye\base\ModelFactory;
use function zovye\m;

class AdStats extends AbstractBase
{
    public static function model(): ModelFactory
    {
        return m('advs_stats');
    }
}