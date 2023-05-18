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
 * @method getBalance()
 * @method getOrderId()
 * @method getAgentId()
 * @method getDeviceId()
 * @method getGoodsId()
 * @method getIp()
 * @method setIp($ip)
 * @method setOrderId(string $string)
 * @method getDeviceType()
 * @method setUpdatetime($ts)
 * @method getUpdatetime()
 * @method getCreatetime()
 * @method getPrice()
 * @method setResultCode(int $int)
 * @method setRefund(bool $bool)
 * @method getResultCode()
 * @method isRefund()
 * @method setPrice(float|int $param)
 * @method setSrc(int $CHARGING)
 * @method setNum(int $amount)
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

    public function getNum(): int
    {
        $num = parent::getNum();
        return $this->isFuelingOrder() ? $num / 100 : $num;
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

    public function getDeviceChannelId()
    {

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

        return $code === 2 || ($code === 0 && time() - $this->getCreatetime() > App::getDeviceWaitTimeout());
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

    public function isChargingResultOk(): bool
    {
        return $this->getExtraData('charging.result.re', 0) == 3;
    }

    public function isChargingResultFailed(): bool
    {
        $result = $this->getExtraData('charging.result', []);
        if ($result && $result['re'] != 3) {
            return true;
        }

        if (empty($result) && $this->getExtraData('timeout')) {
            return true;
        }

        return false;
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

    public function getChargingRecord($sub = '', $default = null)
    {
        if (empty($sub)) {
            return $this->getExtraData('charging.record', is_null($default) ? [] : $default);
        }

        return $this->getExtraData("charging.record.$sub", $default);
    }

    public function isChargingOrder(): bool
    {
        return $this->src == Order::CHARGING || $this->src == Order::CHARGING_UNPAID;
    }

    public function isChargingFinished(): bool
    {
        if ($this->getSrc() == Order::CHARGING) {
            return true;
        }

        if ($this->isChargingResultFailed()) {
            return true;
        }

        $record = $this->getChargingRecord();
        if ($record && isset($record['totalPrice'])) {
            return true;
        }

        return false;
    }

    public function getChargerID()
    {
        return $this->getExtraData('chargerID', 0);
    }

    public function getPullSerialNO()
    {
        return $this->getExtraData('pull.result.serialNO', '');
    }

    public function getDiscount(): int
    {
        return intval($this->getExtraData('discount.total', 0));
    }

    public function getChargingSF(): int
    {
        if ($this->isChargingOrder()) {
            $sf = 0.0;
            $device = $this->getDevice();
            if ($device) {
                $group = $device->getGroup();
                if ($group) {
                    $fee = $group->getFee();
                    $sf = floatval($fee['l0']['sf']);
                }
            }
            $record = $this->getChargingRecord();
            if ($record) {
                return intval(round((floatval($record['total']) * $sf) * 100));
            }
        }

        return 0;
    }

    public function getChargingEF(): int
    {
        return max(0, $this->getPrice() - $this->getChargingSF());
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

    public function isFuelingOrder(): bool
    {
        return $this->src == Order::FUELING || $this->src == Order::FUELING_UNPAID || $this->src == Order::FUELING_SOLO;
    }

    /**
     * @param $result
     * @return mixed
     */
    public function setFuelingResult($result)
    {
        return $this->setExtraData('fueling.result', $result);
    }

    public function getFuelingResult()
    {
        return $this->getExtraData('fueling.result', []);
    }

    /**
     * 设置订单收费信息
     * @param $record
     * @return mixed|true
     */
    public function setFuelingRecord($record)
    {
        $saved = $this->getFuelingRecord();
        if ($saved) {
            if (sha1(json_encode($saved)) == sha1(json_encode($record))) {
                return true;
            }
            $ts = time();
            return $this->setExtraData("fueling.record.$ts", $record);
        }

        return $this->setExtraData('fueling.record', $record);
    }

    /**
     * 获取订单计费数据
     * @param string $sub
     * @param $default
     * @return mixed|null
     */
    public function getFuelingRecord(string $sub = '', $default = null)
    {
        if (empty($sub)) {
            return $this->getExtraData('fueling.record', is_null($default) ? [] : $default);
        }

        return $this->getExtraData("fueling.record.$sub", $default);
    }

    public function isFuelingResultOk(): bool
    {
        return $this->getExtraData('fueling.result.re', 0) == 3;
    }

    public function isFuelingResultFailed(): bool
    {
        $result = $this->getExtraData('fueling.result', []);
        if ($result && $result['re'] != 3) {
            return true;
        }

        if (empty($result) && $this->getExtraData('timeout')) {
            return true;
        }

        return false;
    }

    public function isFuelingFinished(): bool
    {
        if ($this->getSrc() == Order::FUELING) {
            return true;
        }

        if ($this->isFuelingResultFailed()) {
            return true;
        }

        $record = $this->getFuelingRecord();
        if ($record && isset($record['price_total'])) {
            return true;
        }

        return false;
    }
}
