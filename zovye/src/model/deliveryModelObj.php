<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use zovye\Delivery;
use zovye\traits\ExtraDataGettersAndSetters;
use zovye\User;

use function zovye\tb;

/**
 * @method int getUserId()
 */
class deliveryModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('delivery');
    }
    
     /** @var int */
	protected $id;

     /** @var int */
	protected $uniacid;     

     /** @var string */
	protected $order_no;

     /** @var int */
	protected $user_id;

     /** @var int */
	protected $goods_id;

     /** @var int */
	protected $num;

     /** @var string */
	protected $name;

     /** @var string */
	protected $phone_num;

     /** @var string */
	protected $address;

     /** @var int */
	protected $status;

	protected $extra;

     /** @var int */
	protected $createtime;

    use ExtraDataGettersAndSetters;

    public function getUser():? userModelObj 
    {
          return User::get($this->user_id);
    }

    public function getRawGoodsData(): array
    {
         return $this->getExtraData('goods', []);
    }

    public function getFormattedStatus()
    {
         return Delivery::formatStatus($this->status);
    }
}