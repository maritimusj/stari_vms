<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\domain;

use zovye\base\ModelFactory;
use function zovye\m;

class Tags extends AbstractBase
{
    public static function model(): ModelFactory
    {
        return m('tags');
    }
}