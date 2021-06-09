<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use zovye\Account;
use zovye\App;
use zovye\User;
use zovye\Util;
use zovye\Agent;
use zovye\Goods;
use zovye\Device;
use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

use function zovye\tb;
use function zovye\is_error;

/**
 * Class orderModelObj
 * @package zovye
 * @method getSrc()
 * @method getOpenid()
 * @method getNum()
 * @method getBalance()
 * @method getOrderId()
 * @method getAgentId()
 * @method getDeviceId()
 * @method getGoodsId()
 * @method getIp()
 * @method setIp($ip)
 * @method setBalance($balance_deduct_num)
 * @method setOrderId(string $string)
 * @method getDeviceType()
 * @method getUpdatetime()
 * @method getCreatetime()
 * @method getPrice()
 * @method setResultCode(int $int)
 * @method setRefund(bool $bool)
 * @method getResultCode()
 */
class orderModelObj extends modelObj
{
    /** @var int */
    protected $id;

    protected $uniacid;

    protected $src;

    /** @var string */
    protected $name;

    /** @var string */
    protected $openid;
    /** @var int */
    protected $num;
    /** @var int */
    protected $price;
    /** @var int */
    protected $balance;
    /** @var string */
    protected $account;
    /** @var string */
    protected $order_id;
    /** @var int */
    protected $agent_id;
    /** @var int */
    protected $device_id;
    /** @var int */
    protected $goods_id;
    /** @var string */
    protected $ip;
    protected $extra;
    /** @var int */
    protected $result_code;
    /** @var bool */
    protected $refund;
    /** @var int */
    protected $createtime;
    /** @var int */
    protected $updatetime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($readOrWrite): string
    {
        return tb('order');
    }

    public function getAccount($obj = false)
    {
        if ($obj) {
            return Account::findOne(['name' => $this->account]);
        }
        return $this->account;
    }

    /**
     * @return deviceModelObj
     */
    public function getDevice(): ?deviceModelObj
    {
        return Device::get($this->device_id);
    }

    public function getAgent(): ?agentModelObj
    {
        return Agent::get($this->agent_id);
    }

    public function getUser(): ?userModelObj
    {
        return User::get($this->openid, true);
    }

    public function getGoods(): ?goodsModelObj
    {
        return Goods::get($this->getGoodsId());
    }

    public function getGoodsData()
    {
        return $this->getExtraData('goods', []);
    }

    public function getOrderNO(): string
    {
        return strval($this->order_id);
    }

    public function getBluetoothDeviceBUID(): string
    {
        return strval($this->getExtraData('bluetooth.deviceBUID'));
    }

    public function setBluetoothDeviceBUID($buid)
    {
        return $this->setExtraData('bluetooth.deviceBUID', $buid);
    }

    public function setBluetoothResultOk()
    {
        $this->setExtraData('bluetooth.result', 1);
    }

    public function isBluetoothResultUnknown(): bool
    {
        return $this->getExtraData('bluetooth.result', -99) === -99;
    }

    public function setBluetoothResultFail()
    {
        $this->setExtraData('bluetooth.result', 2);
    }

    public function isBluetoothResultOk(): bool
    {
        return intval($this->getExtraData('bluetooth.result')) === 1;
    }

    public function isBluetoothResultFail(): bool
    {
        $code = $this->getExtraData('bluetooth.result');
        return $code === 0 && time() - $this->getCreatetime() > App::deviceWaitTimeout();
    }

    /**
     * 是否出货成功
     * @return bool
     */
    public function isPullOk(): bool
    {
        if ($this->getBluetoothDeviceBUID()) {
            $pull_result = $this->isBluetoothResultFail() ? ['errno' => 1] : [];
        } else {
            $pull_result = $this->getExtraData('pull.result');
        }
        return !empty($pull_result) && !is_error($pull_result);
    }

    public function getPullSerialNO()
    {
        return $this->getExtraData('pull.result.serialNO', '');
    }

    public function getDiscount(): int
    {
        return intval($this->getExtraData('discount.total', 0));
    }

    public function getCommissionPrice(): int
    {
        return $this->getPrice();
    }

    public function getGoodsPrice(): int
    {
        return intval($this->getExtraData('goods.price', 0));
    }

    public function getIpAddress(): array
    {
        $ip = $this->getIp();
        if (empty($ip)) {
            return [];
        }

        $info = $this->get('ip_info', []);
        if (empty($info)) {
            $info = Util::getIpInfo($ip);
            if ($info) {
                $this->set('ip_info', $info);
            }
        }

        return empty($info) ? [] : json_decode($info, true);
    }
}
