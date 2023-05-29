<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use zovye\Order;

use function zovye\tb;

class cv_upload_orderModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('cv_upload_order');
    }
    
	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var int */
	protected $order_id;

	/** @var int */
	protected $createtime;


	public function getOrder():?orderModelObj
	{
		return Order::get($this->order_id);
	}

}