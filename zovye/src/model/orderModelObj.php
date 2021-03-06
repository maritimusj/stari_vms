<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\Account;
use zovye\App;
use zovye\Balance;
use zovye\Order;
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
 * @method setOrderId(string $string)
 * @method getDeviceType()
 * @method getUpdatetime()
 * @method getCreatetime()
 * @method getPrice()
 * @method setResultCode(int $int)
 * @method setRefund(bool $bool)
 * @method getResultCode()
 * @method isRefund()
 * @method setPrice(float|int $param)
 * @method setSrc(int $CHARGING)
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
            return Account::findOneFromName($this->account);
        }

        return $this->account;
    }

    public function isFree(): bool
    {
        if ($this->getSrc() == Order::ACCOUNT || $this->getSrc() == Order::FREE) {
            return true;
        }
        if ($this->getSrc() == Order::BALANCE) {
            return App::isBalanceEnabled() && Balance::isFreeOrder();
        }

        return false;
    }

    public function isPay(): bool
    {
        if ($this->getSrc() == Order::PAY) {
            return true;
        }
        if ($this->getSrc() == Order::BALANCE) {
            return App::isBalanceEnabled() && Balance::isPayOrder();
        }

        return false;
    }

    /**
     * @return deviceModelObj
     */
    public function getDevice(): ?deviceModelObj
    {
        if ($this->device_id) {
            return Device::get($this->device_id);
        }
        $device_id = $this->getExtraData('custom.device', 0);
        if ($device_id) {
            return Device::get($device_id);
        }

        return null;
    }

    public function getAgent(): ?agentModelObj
    {
        if ($this->agent_id) {
            return Agent::get($this->agent_id);
        }
        $agent_id = $this->getExtraData('custom.agent', 0);
        if ($agent_id) {
            return Agent::get($agent_id);
        }

        return null;
    }

    public function getUser(): ?userModelObj
    {
        return User::get($this->openid, true);
    }

    public function isPackage(): bool
    {
        return $this->getPackageId() > 0;
    }

    public function getPackageId(): int
    {
        return intval($this->getExtraData('package.id'));
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
        return $this->order_id;
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

    public function setBluetoothResultFail($message = '')
    {
        $this->setExtraData('bluetooth.result', 2);
        $this->setExtraData('bluetooth.error.msg', $message);
    }

    public function isBluetoothResultOk(): bool
    {
        return intval($this->getExtraData('bluetooth.result')) === 1;
    }

    public function isBluetoothResultFail(): bool
    {
        $code = $this->getExtraData('bluetooth.result', 0);

        return $code === 2 || ($code === 0 && time() - $this->getCreatetime() > App::deviceWaitTimeout());
    }

    /**
     * ??????????????????
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

    public function isChargingResultOk(): bool
    {
        return $this->getExtraData('charging.result.re', 0) == 3;
    }

    public function setChargingResult($result)
    {
        return $this->setExtraData('charging.result', $result);
    }

    public function getChargingResult()
    {
        return $this->getExtraData('charging.result', []);
    }

    public function setChargingRecord($record)
    {
        $saved = $this->getChargingRecord();
        if ($saved) {
            if (sha1(json_encode($saved)) == sha1(json_encode($record))) {
                return true;
            }
            $ts = time();
            return $this->setExtraData("charging.record.$ts", $record);
        }

        return $this->setExtraData('charging.record', $record);
    }

    public function getChargingRecord()
    {
        return $this->getExtraData('charging.record', []);
    }

    public function getChargerID()
    {
        return $this->getExtraData('chargingID', 0);
    }

    public function isChargingFinished(): bool
    {
        if ($this->getSrc() != Order::CHARGING_UNPAID) {
            return true;
        }

        if ($this->getExtraData('timeout')) {
            return true;
        }

        $result = $this->getChargingResult();
        if ($result && $result['re'] != 3) {
            return true;
        }

        $record = $this->getChargingRecord();
        if ($record && isset($record['totalPrice'])) {
            return true;
        }

        return false;
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

    public function isZeroBonus()
    {
        return $this->getExtraData('custom.zero_bonus', false);
    }

    public function profile(): array
    {
        return [
            'id' => $this->getId(),
            'orderNO' => $this->getOrderNO(),
            'createtime' => $this->getCreatetime(),
        ];
    }

}
