<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

use function zovye\tb;

class deliveryModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('delivery');
    }
    
     /** @var int */
	protected $id;

     /** @var int */
	protected $user_id;

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

}