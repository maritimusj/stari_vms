<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\modelObj;
use zovye\Device;
use zovye\traits\ExtraDataGettersAndSetters;
use zovye\User;
use function zovye\tb;

/**
 * Class balanceModelObj
 * @package zovye
 * @method getOpenid()
 * @method getXVal()
 * @method getSrc()
 * @method getExtra()
 * @method getCreatetime()
 */
class balanceModelObj extends modelObj
{
    /** @var int */
    protected $id;
    /** @var int */
    protected $uniacid;
    /** @var string */
    protected $openid;
    /** @var int */
    protected $x_val;
    /** @var int */
    protected $src;

    protected $extra;
    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($readOrWrite): string
    {
        return tb('balance');
    }

    public function getUser(): ?userModelObj
    {
        $user_id = $this->getExtraData('user.id');
        return User::get($user_id);
    }

    public function getDevice(): ?deviceModelObj
    {
        $device_id = $this->getExtraData('device.id');
        return Device::get($device_id);
    }

    public function getNum(): int
    {
        return $this->getExtraData('num', 0);
    }

    public function getGoodsId(): int
    {
        return $this->getExtraData('goods.id', 0);
    }

    public function getGoodsBalance(): int
    {
        return $this->getExtraData('goods.balance', 0);
    }
}