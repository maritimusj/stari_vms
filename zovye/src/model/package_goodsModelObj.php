<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use function zovye\tb;

class package_goodsModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('package_goods');
    }
    
    /** @var int */
	protected $id;

    /** @var int */
	protected $package_id;

    /** @var int */
	protected $goods_id;

    /** @var int */
	protected $price;

    /** @var int */
	protected $num;

    /** @var int */
	protected $createtime;

}