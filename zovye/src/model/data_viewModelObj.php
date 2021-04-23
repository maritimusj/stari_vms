<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use zovye\base\modelObj;

use function zovye\tb;

class data_viewModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('data_view');
    }

    /** @var int */
    protected $id;

    protected $k;

    protected $v;

    protected $createtime;

}