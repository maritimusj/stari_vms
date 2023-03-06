<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use Exception;
use zovye\App;
use zovye\Balance;
use zovye\Job;

use zovye\Locker;
use zovye\Package;
use zovye\PayloadLogs;
use zovye\PlaceHolder;
use zovye\SIM;
use zovye\Stats;
use zovye\We7;
use zovye\User;
use zovye\Util;
use zovye\Agent;
use zovye\Goods;
use zovye\Group;

use zovye\Topic;
use zovye\Device;
use zovye\Keeper;
use zovye\DeviceLocker;
use zovye\Account;
use zovye\CtrlServ;

use function zovye\err;
use function zovye\getArray;
use function zovye\m;
use function zovye\tb;
use zovye\Advertising;
use zovye\DeviceTypes;
use zovye\base\modelObj;
use function zovye\error;

use function zovye\is_error;
use function zovye\settings;
use zovye\BlueToothProtocol;

use zovye\base\modelObjFinder;
use function zovye\isEmptyArray;
use zovye\Contract\bluetooth\IBlueToothProtocol;
use zovye\Order;
use zovye\Pay;

/**
 * @method getGroupId()
 * @method setGroupId($groupId)
 * @method getDeviceType()
 * @method setDeviceType(int $device_type)
 * @method getName()
 * @method getImei()
 * @method getAppId()
 * @method setAppId($null)
 * @method getAgentId()
 * @method setAgentId(int $agent_id)
 * @method setRemain($remain)
 * @method setErrorCode(int $OK)
 * @method getErrorCode()
 * @method getRemain()
 * @method getLastPing()
 * @method setLastPing($TIMESTAMP)
 *
 * @method setName(string $name)
 * @method setTagsData(string $param)
 * @method setShadowId($shadow_id)
 * @method setQrcode(string $qrcode_file)
 * @method setMcbOnline(int $ONLINE)
 *
 * @method getCreatetime()
 * @method setRank($rank)
 * @method getRank()
 * @method getS1()
 * @method setS1(int $int)
 */
class deviceModelObj extends modelObj
{
    /** @var int */
    protected $id;

    protected $uniacid;

    /** @var int */
    protected $group_id;

    /** @var int */
    protected $device_type;

    /** @var string */
    protected $name;

    /** @var int */
    protected $capacity;

    /** @var int */
    protected $remain;

    /** @var int */
    protected $reset;

    /** @var string */
    protected $imei;

    /** @var string */
    protected $iccid;

    /** @var int */
    protected $sig;

    /** @var int */
    protected $qoe; //电量

    /** @var string */
    protected $qrcode;

    /** @var int */
    protected $last_online;

    /** @var int */
    protected $last_ping;

    protected $mcb_online;

    protected $app_id;

    /** @var int */
    protected $app_last_online;

    /** @var string */
    protected $app_version;

    /** @var int */
    protected $agent_id;

    protected $rank;

    protected $tags_data;

    /** @var string */
    protected $shadow_id;

    /** @var string */
    protected $locked_uid;

    /** @var int */
    protected $error_code;

    /** @var int */
    protected $s1; //位置是否变动

    /** @var int */
    protected $s2; //是否缺货

    protected $last_order;

    /** @var int */
    protected $createtime;

    private $agent = null;

    public static function getTableName($readOrWrite): string
    {
        return tb('device');
    }

    /**
     * 返回设备ＵＩＤ
     * return string
     */
    public function getUid()
    {
        return $this->getImei();
    }

    /**
     * 出货记录
     * @param int $level
     * @param array $data
     * @return bool
     */
    public function goodsLog(int $level, array $data = []): bool
    {
        return $this->log($level, $this->getImei(), $data);
    }

    /**
     * @param $keywords
     * @return modelObj | device_logsModelObj
     */
    public function getGoodsLog($keywords)
    {
        return m('device_logs')->findOne("LOCATE('$keywords', data) > 0");
    }

    public function getLastOnlineIp()
    {
        return $this->settings('extra.v1.ip', '');
    }

    public function setLastOnlineIp($ip): bool
    {
        return $this->updateSettings('extra.v1.ip', $ip);
    }

    public function setProtocolV1Code($code): bool
    {
        return $this->updateSettings('extra.v1.last_code', $code);
    }

    public function getLastOnline()
    {
        if ($this->isVDevice()) {
            return date('Y-m-d H:i:s');
        }

        return $this->settings('extra.v0.status.last_online', $this->last_online);
    }

    public function setLastOnline($last_online): bool
    {
        $this->last_online = $last_online;
        $this->setDirty('last_online');

        return $this->updateSettings('extra.v0.status.last_online', $last_online);
    }

    public function setEventLogEnabled($enable = true): bool
    {
        return $this->updateSettings('extra.event.log.enabled', $enable);
    }

    public function isEventLogEnabled()
    {
        return $this->settings('extra.event.log.enabled', false);
    }

    /**
     * 是不是普通设备?
     * @return bool
     */
    public function isNormalDevice(): bool
    {
        return $this->getDeviceModel() == Device::NORMAL_DEVICE;
    }

    /**
     * 是不是虚拟设备?
     * @return bool
     */
    public function isVDevice(): bool
    {
        return App::isVDeviceSupported() && ($this->settings('device.is_vd') || 
        $this->getDeviceModel() == Device::VIRTUAL_DEVICE);
    }

    /**
     * 是不是蓝牙设备?
     * @return bool
     */
    public function isBlueToothDevice(): bool
    {
        return $this->getDeviceModel() == Device::BLUETOOTH_DEVICE;
    }

    /**
     * 是不是充电桩?
     * @return bool
     */
    public function isChargingDevice(): bool
    {
        return $this->getDeviceModel() == Device::CHARGING_DEVICE;
    }

    /**
     * 是不是尿素加注机?
     * @return bool
     */
    public function isFuelingDevice(): bool
    {
        return $this->getDeviceModel() == Device::FUELING_DEVICE;
    }

    public function getBUID(): string
    {
        if ($this->isBlueToothDevice()) {
            $uid = $this->settings('extra.bluetooth.uid', '');
            $protocol = $this->getBlueToothProtocol();
            if ($protocol) {
                $uid = $protocol->transUID($uid);
            }

            return $uid;
        }

        return '';
    }

    /**
     * 蓝牙mac
     * @return mixed|string
     */
    public function getMAC(): string
    {
        if ($this->isBlueToothDevice()) {
            $mac = $this->settings('extra.bluetooth.mac', '');

            return ltrim($mac);
        }

        return '';
    }

    public function getMotor(): int
    {
        if ($this->isBlueToothDevice()) {
            return intval($this->settings('extra.bluetooth.motor', 0));
        }

        return 0;
    }

    public function setMotor($motor): bool
    {
        return $this->updateSettings('extra.bluetooth.motor', intval($motor));
    }

    public function setBluetoothStatus($status): bool
    {
        return $this->updateSettings('extra.bluetooth.status', $status);
    }

    public function getBluetoothStatus()
    {
        return $this->settings('extra.bluetooth.status', '');
    }

    public function isBluetoothReady(): bool
    {
        return $this->getBluetoothStatus() == Device::BLUETOOTH_READY;
    }

    /**
     * @return ?IBlueToothProtocol
     */
    public function getBlueToothProtocol(): ?IBlueToothProtocol
    {
        return BlueToothProtocol::get($this->getBlueToothProtocolName());
    }

    public function getBlueToothProtocolName()
    {
        return $this->settings('extra.bluetooth.protocol', '');
    }

    public function setBlueToothProtocol($protocol): bool
    {
        return $this->updateSettings('extra.bluetooth.protocol', $protocol);
    }

    public function setChargingData(array $data): bool
    {
        return $this->updateSettings('charging', $data);
    }

    public function getChargingData(): array
    {
        $data = $this->settings('charging', []);

        return [
            'cft' => $data['cft'] == 0 ? 'DC' : 'AC',
            'chargerNum' => $data['chargerNum'],
            'carrier' => $data['carrier'],
            'firmwareVersion' => $data['firmwareVersion'],
            'network' => $data['network'],
            'protocolVersion' => 'v'.$data['protocolVersion'] / 10,
        ];
    }

    public function setChargerData($chargerID, array $data): bool
    {
        $saved = $this->getChargerStatusData($chargerID, []);
        $data = array_merge($saved, $data); 
        
        return $this->updateSettings("charger_$chargerID", $data);
    }

    public function setChargerProperty($chargerID, $key, $val = null): bool
    {
        if (is_string($key)) {
            return $this->updateSettings("charger_$chargerID.$key", $val);
        }

        if (is_array($key)) {
            $data =  $this->settings("charger_$chargerID", []);
            $data = array_merge($data, $key);
            return $this->updateSettings("charger_$chargerID", $data);
        }

        return false;
    }

    public function getChargerProperty($chargerID, $key, $defaultVal = null)
    {
        return $this->settings("charger_$chargerID.$key", $defaultVal);
    }

    public function getChargerStatusData($chargerID): array
    {
        return $this->settings("charger_$chargerID", []);
    }

    public function setChargerBMSData($chargerID, array $data): bool
    {
        if ($data) {
            $saved = $this->getChargerBMSData($chargerID);
            $data = array_merge($saved, $data);            
        }
        
        return $this->updateSettings("chargerBMS.$chargerID", $data);
    }

    public function getChargerBMSData($chargerID): array
    {
        return $this->settings("chargerBMS.$chargerID", []);
    }

    public function setChargingNOWData(int $chargerID, $data): bool
    {
        return $this->updateSettings("chargingNOW.$chargerID", $data);
    }

    public function removeChargingNOWData(int $chargerID): bool
    {
        return $this->removeSettings('chargingNOW', $chargerID);
    }

    public function chargingNOWData(int $chargerID, string $key = '', $default = null)
    {
        $path = "chargingNOW.$chargerID";
        if ($key) {
            $path .= ".$key";
        }
        return $this->settings($path, $default);
    }

    /**
     * 设置当前加注状态
     * @param $chargerID
     * @param array $data
     * @return bool
     */
    public function setFuelingStatusData($chargerID, array $data): bool
    {
        if ($data) {
            $saved = $this->getFuelingStatusData($chargerID);
            $data = array_merge($saved, $data);
        }

        $data['time'] = time();

        return $this->updateSettings("fuelingStatus.$chargerID", $data);
    }

    /**
     * 获取当前加注状态
     * @param $chargerID
     * @return array
     */
    public function getFuelingStatusData($chargerID): array
    {
        return $this->settings("fuelingStatus.$chargerID", []);
    }

    public function setFuelingNOWData(int $chargerID, $data): bool
    {
        return $this->updateSettings("fuelingNOW.$chargerID", $data);
    }

    public function fuelingNOWData(int $chargerID, string $key = '', $default = null)
    {
        $path = "fuelingNOW.$chargerID";
        if ($key) {
            $path .= ".$key";
        }
        return $this->settings($path, $default);
    }

    public function removeFuelingNOWData(int $chargerID): bool
    {
        return $this->removeSettings('fuelingNOW', $chargerID);
    }

    public function setDeviceModel($model)
    {
        $this->updateSettings('device.model', $model);
    }

    public function getDeviceModel(): string
    {
        $model = $this->settings('device.model', Device::NORMAL_DEVICE);
        switch ($model) {
            case Device::VIRTUAL_DEVICE:
                if (App::isVDeviceSupported()) {
                    return Device::VIRTUAL_DEVICE;
                }
                break;
            case Device::BLUETOOTH_DEVICE:
                if (App::isBluetoothDeviceSupported()) {
                    return Device::BLUETOOTH_DEVICE;
                }
                break;
            case Device::CHARGING_DEVICE:
                if (App::isChargingDeviceEnabled()) {
                    return Device::CHARGING_DEVICE;
                }
                break;
            case Device::FUELING_DEVICE:
                if (App::isFuelingDeviceEnabled()) {
                    return Device::FUELING_DEVICE;
                }
                break;
        }

        return Device::NORMAL_DEVICE;
    }

    public function getAppLastOnline()
    {
        if ($this->isVDevice()) {
            return date('Y-m-d H:i:s');
        }

        return $this->settings('extra.v0.status.app_last_online', $this->app_last_online);
    }

    public function setAppLastOnline($last_online): bool
    {
        return $this->updateSettings('extra.v0.status.app_last_online', $last_online);
    }

    public function getICCID()
    {
        return $this->settings('extra.v0.status.iccid', $this->iccid);
    }

    public function setICCID($iccid): bool
    {
        return $this->updateSettings('extra.v0.status.iccid', $iccid);
    }

    public function getSIM()
    {
        $iccid = $this->getICCID();
        if (empty($iccid)) {
            return err('ICCID为空！');
        }
        return SIM::get($this->getICCID());
    }

    public function getCapacity(): int
    {
        $c = $this->settings('extra.v0.status.capacity');
        if (isset($c)) {
            return intval($c);
        }

        $this->setCapacity($this->capacity);

        return $this->capacity;
    }

    public function setCapacity($capacity): bool
    {
        return $this->updateSettings('extra.v0.status.capacity', $capacity);
    }

    /**
     * 获取设备信号强度
     * @param bool $raw
     * @return int
     */
    public function getSig(bool $raw = false): int
    {
        $sig = $this->settings('extra.v0.status.sig');
        if (!isset($sig)) {
            return -1;
        }

        if ($raw) {
            return $sig;
        }

        $val = time() - intval($this->getLastPing()) < 300 ? intval(intval($sig) / 31 * 100) : 0;

        return min(100, $val);
    }

    public function setSig($sig): bool
    {
        return $this->updateSettings('extra.v0.status.sig', $sig);
    }

    public function getQoe()
    {
        return $this->settings('extra.v0.status.qoe', -1);
    }

    public function setQoe($qoe): bool
    {
        return $this->updateSettings('extra.v0.status.qoe', $qoe);
    }

    public function getV0Status($name)
    {
        return $this->settings("extra.v0.status.$name");
    }

    public function setV0Status($name, $val): bool
    {
        return $this->updateSettings("extra.v0.status.$name", $val);
    }

    public function setSensorData($type, $data): bool
    {
        return $this->updateSettings("extra.sensor.$type", $data);
    }

    public function getSensorData($type, $default = null)
    {
        return $this->settings("extra.sensor.$type", $default);
    }

    public function getWaterLevel()
    {
        return $this->getSensorData(Device::SENSOR_WATER_LEVEL);
    }

    public function getV0ErrorDescription(): string
    {
        $error = $this->getV0Status(Device::V0_STATUS_ERROR);
        if ($error) {
            static $description = [
                '1' => '计数器故障',
                '2' => '卡膜',
                '3' => '电量低',
            ];

            return ($description[$error] ?? '未知')." [ $error ]";
        }

        return '';
    }

    public function getDoorNum(): int
    {
        return $this->settings('extra.door.num', 0);
    }

    public function getPulseValue(): int
    {
        return $this->settings('extra.pulse', 160);
    }

    public function getTimeout(): int
    {
        return $this->settings('extra.timeout', 120);
    }

    public function getSoloMode(): int
    {
        return $this->settings('extra.solo', 1);
    }

    public function getExpiration(): string
    {
        return $this->settings('extra.expiration', '');
    }

    public function setExpiration($expiration): bool
    {
        return $this->updateSettings('extra.expiration', $expiration);
    }

    public function isExpired(): bool
    {
        $expiration = $this->getExpiration();
        if ($expiration) {
            try {
                $expired_at = strtotime($expiration);
                return $expired_at < time();
            }catch (Exception $e) {
            }
        }
        return false;
    }

    public function getYearRenewalPrice(): int
    {
        return intval(settings('agent.device.fee.year', 0));
    }

    public function getFuelingConfig(): array
    {
        $config = [
            'price' => 0,
            'solo' => $this->getSoloMode(),
            'pulse' => $this->getPulseValue(),
            'timeout' => $this->getTimeout(),
            'ts' => time(),
        ];

        $goods = $this->getGoodsByLane(0);
        if ($goods) {
            $config['price'] = intval($goods['price']);
        }

        return $config;
    }

    /**
     * 电量是否过低
     */
    public function isLowBattery(): bool
    {
        /**
         * -1 表示不支持电量，0为了兼容早期没有电量的版本
         */
        $qoe = $this->getQoe();

        return $qoe != -1 && $qoe != 0 && $qoe < 10;
    }

    public function getReset(): int
    {
        $reset = $this->settings('extra.v0.status.reset');
        if (isset($reset)) {
            return intval($reset);
        }

        $this->setReset($this->reset);

        return $this->reset;
    }

    public function setReset($n): bool
    {
        return $this->updateSettings('extra.v0.status.reset', $n);
    }

    public function getArea($default = []): array
    {
        $address = $this->settings('extra.location.tencent.area', []);
        if (empty($address)) {
            $address = $this->settings('extra.location.baidu.area', []);
            if (empty($address)) {
                $address = $this->settings('extra.location.area', []);
            }
        }

        return empty($address) ? $default : $address;
    }

    public function getAddress($default = ''): string
    {
        $address = $this->settings('extra.location.tencent.address', '');
        if (empty($address)) {
            $address = $this->settings('extra.location.baidu.address', '');
            if (empty($address)) {
                $address = $this->settings('extra.location.address', '');
            }
        }

        return empty($address) ? $default : $address;
    }

    /**
     * 迁移货道数据
     */
    private function migrateLanesData(): string
    {
        $data = $this->settings('cargo_lanes');
        if (is_array($data)) {
            return '';
        }

        $data = $this->settings('extra.cargo_lanes', []);
        if ($this->set('cargo_lanes', $data)) {
            return '';
        }

        return 'extra.';
    }

    /**
     * 迁移货道数据
     * @param string $path
     * @param null $default
     * @return mixed
     */
    private function getMigratedLanesData(string $path = '', $default = null)
    {
        $data = $this->get('cargo_lanes');
        if (is_array($data)) {
            return getArray($data, $path, $default);
        }

        $data = $this->settings('extra.cargo_lanes', []);
        $this->set('cargo_lanes', $data);

        return getArray($data, $path, $default);
    }

    public function setLane($lane, $num = null, $price = null): bool
    {
        $prefix = $this->migrateLanesData();
        if (is_array($num)) {
            return $this->updateSettings("{$prefix}cargo_lanes.l$lane", $num);
        }

        if (is_numeric($num)) {
            if (!$this->updateSettings("{$prefix}cargo_lanes.l$lane.num", intval($num))) {
                return false;
            }
        }

        if (is_numeric($price)) {
            if (!$this->updateSettings("{$prefix}cargo_lanes.l$lane.price", intval($price))) {
                return false;
            }
        }

        return true;
    }

    public function getLane($lane): array
    {
        return (array)$this->getMigratedLanesData("l$lane", []);
    }

    public function getCargoLanes(): array
    {
        return (array)$this->getMigratedLanesData('', []);
    }

    public function setCargoLanes(array $lanes_data): bool
    {
        $prefix = $this->migrateLanesData();

        return $this->updateSettings("{$prefix}cargo_lanes", $lanes_data);
    }

    public function getChargerNum(): int
    {
        return count($this->getCargoLanes());
    }

    public function profile($detail = false): array
    {
        $data = [
            'id' => $this->getId(),
            'imei' => $this->getImei(),
            'name' => $this->getName(),
        ];

        if ($this->isVDevice()) {
            $data['isVD'] = true;
        } elseif ($this->isBlueToothDevice()) {
            $data['isBluetooth'] = true;
            $data['buid'] = $this->getBUID();
        } elseif ($this->isChargingDevice()) {
            $data['isCharging'] = true;
        }

        if ($detail) {
            $data['location'] = $this->getLocation();
        }

        return $data;
    }

    public function cleanError()
    {
        $this->setErrorCode(0);
    }

    public function setError(int $code, string $desc = '')
    {
        $this->setErrorCode($code);
        if ($code !== 0) {
            $this->set(
                'lastErrorData',
                [
                    'createtime' => time(),
                    'code' => $code,
                    'message' => $desc,
                ]
            );
        } else {
            $this->remove('lastErrorData');
        }
    }

    public function getLastError(): array
    {
        if ($this->getErrorCode() != 0) {
            $msg = $this->settings('lastErrorData.message', Device::desc($this->getErrorCode()));
            $err = error($this->getErrorCode(), $msg);
            $err['createtime'] = $this->settings('lastErrorData.createtime');

            return $err;
        }

        return [];
    }

    /**
     * 重置设备相关的设置数据
     * @return bool
     */
    public function resetAllData(): bool
    {
        //删除订单
        We7::pdo_delete(m('order')->getTableName(), We7::uniacid(['device_id' => $this->getId()]));
        We7::pdo_delete(m('maintenance')->getTableName(), We7::uniacid(['device_id' => $this->getId()]));
        We7::pdo_delete(m('advs_stats')->getTableName(), We7::uniacid(['device_id' => $this->getId()]));
        We7::pdo_delete(m('device_events')->getTableName(), We7::uniacid(['device_uid' => $this->getUid()]));

        $this->remove('lastErrorNotify');
        $this->remove('lastRemainWarning');
        $this->remove('lastApkUpdate');
        $this->remove('lastErrorData');
        $this->remove('assigned');
        $this->remove('adsData');
        $this->remove('advs');
        $this->remove('ads');
        $this->remove('accountsData');
        $this->remove('firstMsgStatistic');
        $this->remove('location');
        $this->remove('refresh');
        $this->remove('statsData');

        $this->set('extra', []);

        $this->resetPayload([], '设备初始化');

        $this->setGroupId(0);

        Device::unbind($this);
        $this->setAgent();

        $this->resetLock();
        $this->setTagsFromText('');
        $this->updateQrcode(true);

        return $this->save();
    }

    public function getPayloadCode($now = 0): string
    {
        $last_code = $this->settings('last.code');
        if (empty($last_code)) {
            $last_code = App::uid();
        }

        return sha1($last_code.$now);
    }

    /**
     * 重置多货道商品数量，@开头表示增加指定数量，正值表示增加指定数量，负值表示减少指定数量，0值表示重置到最大数量
     * 空数组则重置所有货道商品数量到最大值
     * @param array $data
     * @param string $reason
     * @param int $now
     * @return array
     */
    public function resetPayload(array $data = [], string $reason = '', int $now = 0): array
    {
        static $cache = [];

        $now = empty($now) ? time() : $now;

        if ($cache[$now]) {
            $clr = $cache[$now];
        } else {
            $clr = Util::randColor();
            $cache[$now] = $clr;
        }

        $result = Device::resetPayload($this, $data);
        if ($result) {
            foreach ($result as $entry) {
                $code = $this->getPayloadCode($now);
                if (!empty($entry['reason'])) {
                    $reason = $reason."({$entry['reason']})";
                }
                if (!PayloadLogs::create([
                    'device_id' => $this->id,
                    'goods_id' => $entry['goodsId'],
                    'org' => $entry['org'],
                    'num' => $entry['num'],
                    'extra' => [
                        'reason' => $reason,
                        'code' => $code,
                        'clr' => $clr,
                    ],
                    'createtime' => $now,
                ])) {
                    return err('保存库存记录失败！');
                }
                if (!$this->updateSettings('last', [
                    'code' => $code,
                    'time' => $now,
                ])) {
                    return err('保存流水记录失败！');
                }
            }
        }

        return $result;
    }

    public function resetGoodsNum($goods_id, $delta, $reason = ''): array
    {
        $goods = $this->getGoods($goods_id);
        if ($goods) {
            return $this->resetPayload([$goods['cargo_lane'] => $delta], $reason);
        }

        return err('找不到指定的商品！');
    }

    /**
     * 重置设备锁
     */
    public function resetLock(): bool
    {
        if (We7::pdo_update(
            self::getTableName(modelObj::OP_WRITE),
            [OBJ_LOCKED_UID => UNLOCKED],
            ['id' => $this->getId()]
        )) {
            $this->locked_uid = UNLOCKED;

            return true;
        }

        return false;
    }

    /**
     * 设置设备标签，文本形式指定
     * @param $tags
     */
    public function setTagsFromText($tags)
    {
        $org_ids = explode('><', trim($this->tags_data, '<>'));

        $ids = [];
        if (is_string($tags)) {
            $arr = array_unique(explode(',', $tags));
            foreach ($arr as $text) {
                $text = trim($text);
                if ($text) {
                    $condition = We7::uniacid(['title' => $text]);
                    /** @var tagsModelObj $tag */
                    $tag = m('tags')->findOne($condition);
                    if (empty($tag)) {
                        $condition['count'] = 1;
                        $tag = m('tags')->create($condition);
                    } else {
                        $count = Device::query("tags_data REGEXP '<{$tag->getId()}>'")->where("id<>$this->id")->count();
                        $tag->setCount($count + 1);
                        $tag->save();
                    }

                    if ($tag) {
                        $ids[] = $tag->getId();
                    }
                }
            }
        }

        $diff_tags = array_diff($org_ids, $ids);
        foreach ($diff_tags as $id) {
            $tag = m('tags')->findOne(We7::uniacid(['id' => $id]));
            if ($tag) {
                $count = Device::query("tags_data REGEXP '<{$tag->getId()}>'")->count();
                $tag->setCount($count - 1);
                $tag->save();
            }
        }

        $data = implode('><', $ids);
        $this->setTagsData($data ? "<$data>" : '');
    }

    /**
     * 生成二维码并通知APP
     * @param bool $force
     * @return bool
     */
    public function updateQrcode(bool $force = false): bool
    {
        $need_notify = false;
        if ($this->isBlueToothDevice()) {
            if (empty($this->qrcode) || $force) {
                $need_notify = $this->createQrcodeFile();
            }
        } else {
            //无论什么情况都要更换shadowId!
            $this->resetShadowId();

            if ($force || $this->isActiveQrcodeEnabled() || empty($this->qrcode)) {
                $need_notify = $this->createQrcodeFile();
            }
        }

        return $need_notify && $this->updateAppQrcode();
    }

    /**
     * 重置设备shadowId
     * @return bool
     */
    public function resetShadowId(): bool
    {
        $i = 6;
        do {
            $shadow_id = Util::random($i++);
        } while (Device::find($shadow_id, ['id', 'imei', 'shadow_id', 'app_id']));

        $this->setShadowId($shadow_id);

        return $this->save();
    }

    /**
     * 是否启用了动态二维码
     * @return bool
     */
    public function isActiveQrcodeEnabled(): bool
    {
        return $this->settings('extra.activeQrcode', 0);
    }

    /**
     * 创建二维码文件
     * @return bool
     */
    public function createQrcodeFile(): bool
    {
        if ($this->isChargingDevice()) {
            $chargerNum = $this->getChargerNum();
            for($i = 0; $i < $chargerNum; $i++) {
                
                $chargerID = $i + 1;

                $url = Util::murl('device', [
                    'charging' => true,
                    'device' => $this->getImei(),
                    'charger' => $chargerID,
                ]);
                
                $qrcode_file = Util::createQrcodeFile("device.$this->imei$chargerID", $url, function ($filename) use ($chargerID) {
                    Util::renderTxt($filename, sprintf("%s%02d", $this->imei, $chargerID));
                });
                if (is_error($qrcode_file)) {
                    return false;
                }
                $this->setChargerProperty($chargerID, 'qrcode', $qrcode_file);
            }
        }

        $url = $this->getUrl();

        $qrcode_file = Util::createQrcodeFile("device.$this->imei", $url, function ($filename) {
            Util::renderTxt($filename, $this->imei);
        });

        if (is_error($qrcode_file)) {
            return false;
        }

        $this->setQrcode($qrcode_file);
        return $this->save();
    }

    /**
     * 获取领货链接
     * @return string
     */
    public function getUrl(): string
    {
        if (App::isFlashEggEnabled()) {
            $adDeviceUID = $this->getAdDeviceUID();
            if ($adDeviceUID) {
                return Util::murl('sample', ['device' => $this->imei]);
            }
        }

        $id = $this->isActiveQrcodeEnabled() ? $this->shadow_id : $this->imei;

        $params = [];

        //小程序识别码
        $adv = $this->getOneAdv(Advertising::WX_APP_URL_CODE);
        if ($adv && $adv['extra']['code']) {
            $params['app'] = strval($adv['extra']['code']);
        } else {
            $params['app'] = 'NULL';
        }

        if ($this->isBlueToothDevice()) {
            $params['wxapp'] = 'true';
        } elseif ($this->isChargingDevice()) {
            $params['charging'] = 'true';
        }

        $params['from'] = 'device';
        $params['device'] = $id;

        return Util::murl('entry', $params);
    }

    public function getProtocolV1Code()
    {
        return $this->settings('extra.v1.last_code');
    }

    /**
     * 通知app更新二维码
     * @return bool
     */
    public function updateAppQrcode(): bool
    {
        $data = [
            'qrcode' => $this->getAccountQRCode(),
        ];

        if (empty($data['qrcode'])) {
            $data['qrcode'] = $this->getQrcode();
            $data['qrcode_url'] = $this->getUrl();
        }

        return $this->appNotify('config', $data);
    }

    /**
     * 获取设备二维码
     * @return string
     */
    public function getQrcode(): string
    {
        $url = Util::toMedia(parent::getQrcode());
        $f = stripos($url, '?') !== false ? '&' : '?';
        $ts = microtime(true);

        return $url."{$f}v=$ts";
    }

    public function getGroup(): ?device_groupsModelObj
    {
        if ($this->group_id > 0) {
            if ($this->isChargingDevice()) {
                return Group::get($this->group_id, Group::CHARGING);
            }

            return Group::get($this->group_id, Group::NORMAL);
        }

        return null;
    }

    /**
     * 给app发送通知
     * @param string $op
     * @param array $data
     * @return bool
     */
    public function appNotify(string $op = 'config', array $data = []): bool
    {
        if ($this->app_id) {
            return CtrlServ::appNotify($this->app_id, $op, $data);
        }

        return false;
    }

    public function appShowMessage($msg, $type = 'success', $style = null): bool
    {
        static $styles = [
            'success' => [
                'background' => '#4CAF50',
                'text' => '#FFFFFF',
            ],
            'warn' => [
                'background' => '#E6A23C',
                'text' => '#FFFFFF',
            ],
            'error' => [
                'background' => '#F56C6C',
                'text' => '#FFFFFF',
            ],
            'info' => [
                'background' => '#409EFF',
                'text' => '#FFFFFF',
            ],
        ];

        return $this->appNotify('message', [
            'content' => $msg,
            'type' => $type,
            'style' => $style ?? $styles[$type],
        ]);
    }

    /**
     * 设备是否已经锁定
     * @return bool
     */
    public function isLocked(): bool
    {
        $this->checkLockerExpired();

        return $this->locked_uid != UNLOCKED;
    }

    /**
     * 尝试锁定设备
     * @param null $uid
     * @return string
     */
    public function lock($uid = null): string
    {
        $uid = strval($uid) ?: Util::random(6);
        $uid = "$uid:".time();
        $res = We7::pdo_update(
            self::getTableName(modelObj::OP_WRITE),
            [OBJ_LOCKED_UID => $uid],
            ['id' => $this->getId(), OBJ_LOCKED_UID => UNLOCKED]
        );
        if ($res) {
            $this->locked_uid = $uid;

            return $uid;
        }

        return '';
    }

    /**
     * 解除设备锁定
     * @param $uid
     * @return bool
     */
    public function unlock($uid): bool
    {
        if ($uid) {
            return We7::pdo_update(
                self::getTableName(modelObj::OP_WRITE),
                [OBJ_LOCKED_UID => UNLOCKED],
                ['id' => $this->getId(), OBJ_LOCKED_UID => $uid]
            );
        }

        return false;
    }

    /**
     * 设备剩余不足通知是否已超时
     * @return bool
     */
    public function isLastOnlineNotifyTimeout(): bool
    {
        $lastNotify = $this->get('lastOnlineNotify');
        if (empty($lastNotify) || time() - $lastNotify['createtime'] > settings(
                'notice.delay.deviceOnlineDelay',
                1
            ) * 60) {
            return true;
        }

        return false;
    }

    /**
     * 更新设备上下线通知时间
     * @param null $time
     * @return bool
     */
    public function updateLastDeviceOnlineNotify($time = null): bool
    {
        $now = $time ?: time();

        return $this->set('lastOnlineNotify', ['createtime' => $now]);
    }

    /**
     * 更新设备最后故障通知时间
     * @param null $time
     * @return bool
     */
    public function updateLastDeviceNotify($time = null): bool
    {
        $now = $time ?: time();

        return $this->set('lastErrorNotify', ['createtime' => $now]);
    }

    /**
     * 更新设备剩余通知时间
     * @param null $time
     * @return bool
     */
    public function updateLastRemainWarning($time = null): bool
    {
        $now = $time ?: time();

        return $this->set('lastRemainWarning', ['createtime' => $now]);
    }

    /**
     *  检查设备剩余，如果设置允许，会推送通知
     */
    public function checkRemain()
    {
        $remainWarning = App::getRemainWarningNum($this->getAgent());

        $set_s2_flag = false;

        if ($remainWarning > 0 && $this->remain < $remainWarning) {
            $tpl_id = settings('notice.reload_tplid');
            if ($tpl_id) {
                if ($this->isLastRemainWarningTimeout()) {
                    //使用控制中心推送通知
                    Job::devicePayloadWarning($this->getId());
                }
            }
            $set_s2_flag = true;
        }

        $this->setS2($this->remain < 1 || $set_s2_flag ? 1 : 0);
        $this->save();
    }

    /**
     * 设备剩余不足通知是否已超时
     * @return bool
     */
    public function isLastRemainWarningTimeout(): bool
    {
        $lastNotify = $this->getLastRemainWarning();
        if (empty($lastNotify) || time() - $lastNotify['createtime'] > settings(
                'notice.delay.remainWarning',
                1
            ) * 3600) {
            return true;
        }

        return false;
    }

    /**
     * 上次设备剩余不足通知
     * @return array
     */
    public function getLastRemainWarning(): array
    {
        return (array)$this->get('lastRemainWarning', []);
    }

    /**
     * 以文本形式获取设备标签
     * @param bool $str
     * @return string|array
     */
    public function getTagsAsText(bool $str = true)
    {
        $titles = [];

        if ($this->tags_data) {
            $tags = trim($this->tags_data, '<>');
            if ($tags) {
                foreach (explode('><', $tags) as $id) {
                    $condition = We7::uniacid(['id' => $id]);
                    /** @var tagsModelObj $tag */
                    $tag = m('tags')->findOne($condition);
                    if ($tag) {
                        $titles[$tag->getId()] = $tag->getTitle();
                    }
                }
            }
        }

        return $str ? implode(',', $titles) : $titles;
    }

    public function getAccountQRCode(): string
    {
        //是否设置了屏幕二维码的公众号
        if (App::isUseAccountQRCode()) {
            $accounts = $this->getAccounts(Account::AUTH);
            foreach ($accounts as $account) {
                $obj = Account::get($account['id']);
                if (empty($obj) || !$obj->useAccountQRCode()) {
                    continue;
                }

                $res = Account::updateAuthAccountQRCode($account, [App::uid(6), 'device', $this->getId()], false);
                if (!is_error($res)) {
                    return $account['qrcode'];
                }
            }
        }

        return '';
    }

    /**
     * 获取设备的App配置
     * @return array
     */
    public function getAppConfig(): array
    {
        //音量
        $vol = $this->settings('extra.volume', 0);

        //引导图
        $res = $this->getOneAdv(Advertising::SCREEN_NAV);
        if ($res) {
            $banner = $res['extra']['url'];
        }

        if (empty($banner)) {
            $banner = settings('misc.banner');
        }

        //广告列表
        $ads = [];
        $srt = [
            'speed' => intval(settings('advs.srt.speed', 1)),
            'subs' => [],
        ];

        foreach ($this->getAds(Advertising::SCREEN) as $adv) {

            if ($adv['extra']['media'] == 'srt') {
                if (!empty($adv['extra']['text'])) {
                    $srt['subs'][] = strval($adv['extra']['text']);
                    if (!empty($adv['extra']['speed']) && $srt['speed'] != $adv['extra']['speed']) {
                        $srt['speed'] = intval($adv['extra']['speed']);
                    }
                    if (!empty($adv['extra']['clr']) && $srt['color'] != $adv['extra']['clr']) {
                        $srt['color'] = strval($adv['extra']['clr']);
                    }
                    if (!empty($adv['extra']['background-clr']) && $srt['background-color'] != $adv['extra']['background-clr']) {
                        $srt['background-color'] = strval($adv['extra']['background-clr']);
                    }
                    if (!empty($adv['extra']['size']) && $srt['size'] < $adv['extra']['size']) {
                        $srt['size'] = intval($adv['extra']['size']);
                    }
                }
            } else {
                $data = [
                    'id' => intval($adv['id']),
                    'type' => strval($adv['extra']['media']),
                ];
                $data['url'] = strval(Util::toMedia($adv['extra']['url']));
                if ($data['type'] == 'image') {
                    $data['duration'] = intval($adv['extra']['duration'] ?: settings('advs.image.duration', 10)) * 1000;
                }
                if ($adv['extra']['area']) {
                    $data['area'] = intval($adv['extra']['area']);
                }
                $ads[] = $data;
            }
        }

        //其它配置
        $cfg = [
            'banner' => strval(Util::toMedia($banner)),
            'volume' => intval($vol),
            'advs' => $ads,
        ];

        //自动开关机
        $config = $this->settings('extra.schedule.screen', []);
        if ($config['enabled']) {
            $on = $config['on'] ?? '';
            $off = $config['off'] ?? '';
            $cfg['schedule'] = [
                'enabled' => true,
                'on' => $on,
                'off' => $off,
            ];
        } else {
            $cfg['schedule'] = [
                'enabled' => false,
            ];
        }

        $qrcode = $this->getAccountQRCode();

        if ($qrcode) {
            $cfg['qrcode'] = $qrcode;
        } else {
            $cfg['qrcode'] = $this->getQrcode();
            $cfg['qrcode_url'] = $this->getUrl();
        }

        //商品库存
        $cfg = array_merge($cfg, Device::getPayload($this));

        //字幕
        if ($srt['subs']) {
            $cfg['srt'] = $srt;
        }

        return $cfg;
    }

    /**
     * 获取一个指定类型的广告
     * @param $type
     * @param bool $random
     * @param callable|null $filterFN
     * @return array|null
     */
    public function getOneAdv($type, bool $random = false, callable $filterFN = null): ?array
    {
        $ads = $this->getAds($type);
        if (!isEmptyArray($ads)) {
            if ($random) {
                shuffle($ads);
            }
            if ($filterFN) {
                foreach ($ads as $ad) {
                    $adv = Advertising::get($ad['id']);
                    if ($adv && $filterFN($adv)) {
                        return Advertising::format($adv);
                    }
                }
                reset($ads);
            }
            return current($ads);
        }
        return null;
    }

    /**
     * 获取相关广告
     * @param $type
     * @param bool $ignore_cache
     * @return array
     */
    public function getAds($type, bool $ignore_cache = false): array
    {
        $ads = null;

        if (!$ignore_cache) {
            if ($this->settings("adsData.type$type.version") == Advertising::version($type)) {
                $ads = $this->settings("adsData.type$type.data");
            }
        }

        if (is_null($ads)) {
            $query = Advertising::query(['type' => $type]);

            $query->orderBy('createtime DESC');

            $ads = [];

            /** @var advertisingModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $passed = !App::isAdsReviewEnabled() || $entry->isReviewPassed();
                if ($entry->getState() == Advertising::NORMAL && $passed) {
                    $assign_data = $entry->settings('assigned');
                    if ($this->isMatched($assign_data)) {
                        $ads["U{$entry->getId()}"] = Advertising::format($entry);
                        continue;
                    }
                }

                unset($ads["U{$entry->getId()}"]);
            }

            $this->updateSettings(
                "adsData.type$type",
                [
                    'version' => Advertising::version($type),
                    'data' => $ads,
                ]
            );
        }

        return $ads;
    }

    /**
     * 判断assigned数据是否包括当前设备
     * @param $assign_data
     * @return bool
     */
    public function isMatched($assign_data): bool
    {
        return Util::isAssigned($assign_data, $this);
    }

    /**
     * 获取设备标签ID
     * @return array
     */
    public function getTagsAsId(): array
    {
        $ids = [];

        if ($this->tags_data) {
            $tags = trim($this->tags_data, '<>');
            if ($tags) {
                foreach (explode('><', $tags) as $id) {
                    if ($id) {
                        $ids[] = $id;
                    }
                }
            }
        }

        return $ids;
    }

    public function getAppVersion()
    {
        $ver = $this->settings('extra.v0.status.appversion');
        if (isset($ver)) {
            return $ver;
        }

        $this->setAppVersion($this->app_version);

        return $this->app_version;
    }

    public function setAppVersion($ver): bool
    {
        return $this->updateSettings('extra.v0.status.appversion', $ver);
    }

    /**
     * 设备每天免费送货数量限制
     * @return bool
     */
    public function isFreeLimitsReached(): bool
    {
        $agent = $this->getAgent();
        if ($agent) {
            $day_limits = $agent->settings('agentData.misc.limits.day', 0);
        } else {
            $day_limits = settings('device.limits.day', 0);
        }

        if ($day_limits > 0) {
            $data = Stats::getDayTotal($this);

            return $data['free'] >= $day_limits;
        }

        return false;
    }

    /**
     * 获取设备所属的代理商
     * @return agentModelObj|null
     */
    public function getAgent(): ?agentModelObj
    {
        if ($this->agent_id && is_null($this->agent)) {
            $this->agent = Agent::get($this->agent_id);
        }

        return $this->agent;
    }

    /**
     * 设置设备代理商
     * @param agentModelObj|null $agent
     */
    public function setAgent(agentModelObj $agent = null)
    {
        $agent_id = is_object($agent) ? $agent->getAgentId() : intval($agent);
        $this->setAgentId($agent_id);
    }

    /**
     * 获取相关topics
     * @return array
     */
    public function getTopics(): array
    {
        $tags = [];

        //同一个平台的设备会订阅同一个主题
        $tags[] = ['name' => Topic::encrypt()];

        if ($this->getId()) {

            $tags[] = ['name' => Topic::encrypt('device'.$this->getId())];

            if ($this->agent_id) {
                $tags[] = ['name' => Topic::encrypt('agent'.$this->getAgentId())];
            }

            if ($this->getGroupId()) {
                $tags[] = ['name' => Topic::encrypt('group'.$this->getGroupId())];
            }
        }

        foreach ($this->getTagsAsId() as $id) {
            $tags[] = ['name' => Topic::encrypt('tag'.$id)];
        }

        return $tags;
    }

    /**
     * 通知设备更新屏幕广告
     * @return bool
     */
    public function updateScreenAdvsData(): bool
    {
        if ($this->isAdsUpdated(Advertising::SCREEN)) {
            return $this->appNotify('update');
        }

        return false;
    }

    /**
     * 广告是否已经更新
     * @param $type
     * @return bool
     */
    public function isAdsUpdated($type): bool
    {
        if ($this->settings("adsData.type$type.version") != Advertising::version($type)) {
            return true;
        }

        $cachedData = $this->settings("adsData.type$type.data", []);
        $ads = $this->getAds($type, true);

        if (empty($cachedData) && empty($ads)) {
            return false;
        }

        return array_keys($cachedData) != array_keys($ads);
    }

    /**
     * @return bool
     */
    public function updateAccountData(): bool
    {
        return $this->accountsUpdated();
    }

    /**
     * 公众号是否已更新
     * @return bool
     */
    public function accountsUpdated(): bool
    {
        $accounts = $this->getAssignedAccounts(true);
        $accounts_cached_data = $this->get('accountsData', []);

        if (empty($accounts_cached_data) && empty($accounts)) {
            return false;
        }

        return $accounts_cached_data['last_update'] != $accounts['last_update'];
    }

    public function getAccounts($state_filter = [Account::NORMAL, Account::VIDEO, Account::AUTH]): array
    {
        $result = [];

        $state_filter = is_array($state_filter) ? $state_filter : [$state_filter];

        $accounts = $this->getAssignedAccounts();
        foreach ($accounts as $index => $account) {
            if (in_array($account['type'], $state_filter)) {
                $result[$index] = $account;
            }
        }

        return $result;
    }

    /**
     * 获取已经分配到这个设备的相关公众号
     * @param bool $ignore_cache
     * @return array
     */
    public function getAssignedAccounts(bool $ignore_cache = false): array
    {
        $accounts = [];

        $last_update = settings('accounts.last_update');
        if (!$ignore_cache) {
            $accounts_data = $this->get('accountsData', []);
            if ($accounts_data && $accounts_data['last_update'] == $last_update) {
                return $accounts_data['data'] ?: [];
            }
        }

        $query = Account::query(['state <>' => Account::BANNED]);
        $query->orderBy('order_no DESC');

        $balance_enabled = App::isBalanceEnabled();

        /** @var accountModelObj $entry */
        foreach ($query->findAll() as $entry) {
            if ($entry->isBanned()) {
                continue;
            }

            if ($balance_enabled && $entry->getBonusType() == Account::BALANCE) {
                $accounts[$entry->getUid()] = $entry->format();
                continue;
            }

            $assign_data = $entry->settings('assigned');
            if ($this->isMatched($assign_data)) {
                $accounts[$entry->getUid()] = $entry->format();
            }
        }

        if (!$this->isDummyDevice()) {
            $this->set(
                'accountsData',
                [
                    'last_update' => $last_update,
                    'data' => $accounts,
                ]
            );
        }

        return $accounts;
    }

    public function isDummyDevice(): bool
    {
       return Device::isDummyDeviceIMEI($this->imei);
    }

    public function getOnlineDetail($use_cache = true): array
    {
        if ($this->isVDevice() || $this->isBlueToothDevice()) {
            return [
                'mcb' => true,
            ];
        }
        $res = CtrlServ::getV2("device/$this->imei/online", ['nocache' => $use_cache ? 'false' : 'true']);
        if ($res['status'] === true) {
            return $res['data'];
        }

        if ($res['data']['message']) {
            return err($res['data']['message']);
        }

        return [];
    }

    /**
     * 设备关联的app是否在线
     * @return bool
     */
    public function isAppOnline(): bool
    {
        if ($this->app_id) {
            if ($this->imei) {
                $res = CtrlServ::getV2("device/$this->imei/app/online", ['nocache' => false]);

                return $res['status'] === true && $res['data']['app'] === true;
            }
        }

        return false;
    }

    /**
     * 通知app更新音量
     * @param null $vol
     * @return bool
     */
    public function updateAppVolume($vol = null): bool
    {
        if (is_null($vol)) {
            $extra = $this->get('extra', []);
            if (isset($extra['volume'])) {
                $vol = intval($extra['volume']);
            } else {
                $vol = 100;
            }
        } else {
            $vol = intval($vol);
        }

        $data = ['volume' => $vol];

        $res = $this->appNotify('config', $data);

        return !is_error($res);
    }

    /**
     * 通知app更新剩余数量
     * @return bool
     */
    public function updateAppRemain(): bool
    {
        $data = $this->getPayload();

        //目前如果没有srt，config通知会导致app隐藏字幕
        $srt = $this->getSrcConfig();
        if ($srt) {
            $data['srt'] = $srt;
        }

        $res = $this->appNotify('config', $data);

        return !is_error($res);
    }

    /**
     * 是否为自定义型号设备
     * @return bool
     */
    public function isCustomizedType(): bool
    {
        return $this->device_type == 0;
    }

    /**
     * @param bool $detail
     * @return array
     */
    public function getPayload(bool $detail = false): array
    {
        return Device::getPayload($this, $detail);
    }

    public function getCargoLanesNum(): int
    {
        $device_type = DeviceTypes::from($this);

        return $device_type ? $device_type->getCargoLanesNum() : 0;
    }

    /**
     * @return array
     */
    public function getSrcConfig(): array
    {
        //app版本需要大于等于3.1才能处理字幕
        if ($this->getAppVersion() < 3.1) {
            return [];
        }

        $subs = [];
        foreach ($this->getAds(Advertising::SCREEN) as $adv) {
            if ($adv['extra']['media'] == 'srt') {
                if (!empty($adv['extra']['text'])) {
                    $subs[] = strval($adv['extra']['text']);
                }
            }
        }

        if ($subs) {
            return [
                'speed' => intval(settings('advs.srt.speed', 1)),
                'subs' => $subs,
            ];
        }

        return [];
    }

    /**
     * 通知mcb更新参数
     * @param $code
     * @return bool
     */
    public function updateMcbParams($code): bool
    {
        $this->resetShadowId();
        $data = [
            'qr' => str_replace('{imei}', $this->getImei(), DEVICE_FORWARDER_URL),
            'num' => $this->getRemainNum(),
        ];

        $txt = $this->settings('extra.txt', []);
        if (!isEmptyArray($txt)) {
            $data['txt'] = [
                '123', //固定数据，主板要求
                strval($txt[0]),
                strval($txt[1]),
                strval($txt[2]),
            ];
        }

        return $this->mcbNotify('params', $code, $data);
    }

    public function getRemainNum(): int
    {
        $data = Device::getPayload($this);
        if (isset($data['payload']['remain'])) {
            return $data['payload']['remain'];
        }

        if (is_array($data['cargo_lanes'])) {
            return array_reduce(
                $data['cargo_lanes'],
                function ($carry, $item) {
                    return $carry + $item['num'];
                },
                0
            );
        }

        return 0;
    }

    /**
     * 给mcb发送通知
     * @param string $op
     * @param string $code
     * @param array $data
     * @return bool
     */
    public function mcbNotify(string $op = 'params', string $code = '', array $data = []): bool
    {
        if ($this->imei) {
            if (empty($code)) {
                $code = $this->getProtocolV1Code();
            }

            return CtrlServ::mcbNotify($this->imei, $code, $op, $data);
        }

        return false;
    }

    /**
     * 启用/禁用动态二维码
     * @param bool $enable
     * @return bool
     */
    public function enableActiveQrcode(bool $enable = true): bool
    {
        return $this->updateSettings('extra.activeQrcode', $enable ? 1 : 0);
    }

    /**
     * 通知mcb更新配置
     * @param $code
     * @param array $data
     * @return void
     */
    public function updateMcbConfig($code, array $data = [])
    {
        $this->mcbNotify('config', $code, $data);
    }

    public function isMcbStatusExpired(): bool
    {
        $update_time = $this->settings('extra.v1.status.updatetime', 0);

        return time() - $update_time > 60 * 60 * 60;
    }

    /**
     * 请求mcb报告状态
     * @param $code
     * @return void
     */
    public function reportMcbStatus($code)
    {
        $this->mcbNotify('report', $code, []);
    }

    /**
     * 保存mcb报告的状态
     * @param array $data
     * @return void
     */
    public function updateMcbStatus(array $data = [])
    {
        $data['updatetime'] = time();
        $this->updateSettings('extra.v1.status', $data);
    }

    /**
     * 获取上次app更新推送信息
     * @return mixed
     */
    public function getLastApkUpgrade()
    {
        return $this->get('lastApkUpdate', []);
    }

    /**
     * 通知app更新APK
     * @param $title
     * @param $version
     * @param $url
     * @return bool
     */
    public function upgradeApk($title, $version, $url): bool
    {
        $data = [
            'version' => $version,
            'url' => $url,
        ];

        if ($this->appNotify('apk', $data)) {
            //记录
            $this->set(
                'lastApkUpdate',
                [
                    'time' => time(),
                    'title' => $title,
                    'version' => $version,
                    'url' => $url,
                ]
            );

            return true;
        }

        return false;
    }

    /**
     * 重置设备shadowId
     * @return string
     */
    public function getShadowId(): string
    {
        if (empty($this->shadow_id)) {
            $this->resetShadowId();
        }

        return $this->shadow_id;
    }

    protected function checkLockerExpired(): bool
    {
        $wait_timeout = intval(settings('device.waitTimeout'));
        $lock_timeout = intval(settings('device.lockTimeout'));

        if ($lock_timeout > 0) {
            $locked_time = $this->getLockedTime();
            if ($locked_time > 0 && time() - $this->getLockedTime() > $wait_timeout + $lock_timeout) {
                return $this->resetLock();
            }
        }

        return false;
    }

    /**
     * 尝试锁定设备，超过系统设置的超时时长后，自动解锁
     * @param int $retries
     * @param int $delay_seconds
     * @return bool
     */
    public function lockAcquire(int $retries = 0, int $delay_seconds = 1): bool
    {
        $this->checkLockerExpired();

        for (; ;) {
            if ((new DeviceLocker($this))->isLocked()) {
                return true;
            }

            $retries--;

            if ($retries <= 0) {
                return false;
            }

            sleep($delay_seconds);
        }
    }

    public function payloadLockAcquire(int $retries = 0, int $delay_seconds = 1): ?lockerModelObj
    {
        return Locker::try("payload:{$this->getImei()}", REQUEST_ID, $retries, $delay_seconds);
    }

    /**
     * 获取设备当前锁定时间戳, 返回 0 则表示设备未锁定
     * @return int
     */
    public function getLockedTime(): int
    {
        list(, $timestamp) = explode(':', $this->locked_uid);
        if (is_numeric($timestamp)) {
            return intval($timestamp);
        }

        return 0;
    }

    /**
     * 出货操作
     * 蓝牙设备出货操作可能会返回字符串，普通设备则返回成功或error()
     * @param array $options
     * @return array|string|null
     */
    public function pull(array $options = [])
    {
        if ($options['online'] && !$this->isMcbOnline()) {
            return err('设备已关机！');
        }

        $num = max(1, $options['num']);

        //虚拟设备直接返回成功
        if ($this->isVDevice()) {
            return [
                'num' => max(1, $options['num']),
                'errno' => 0,
                'message' => '虚拟出货成功！',
            ];
        }

        $mcb_channel = isset($options['channel']) ? intval($options['channel']) : Device::CHANNEL_DEFAULT;

        $result = null;

        if ($this->isChargingDevice()) {
            return err('设备不支持这个操作！');
        }
        //蓝牙设备
        if ($this->isBlueToothDevice()) {
            $protocol = $this->getBlueToothProtocol();
            if (empty($protocol)) {
                return err('未知的蓝牙协议！');
            }

            $motorNum = $this->getMotor();
            if ($motorNum > 0) {
                if ($motorNum >= $mcb_channel) {
                    $option = ['motor' => $mcb_channel];
                } else {
                    $option = ['locker' => $mcb_channel - $motorNum];
                }
            } else {
                $option = ['locker' => $mcb_channel];
            }

            $msg = $protocol->Open($this->getBUID(), $option);
            if ($msg) {
                Device::createBluetoothCmdLog($this, $msg);
                $result = $msg->getEncoded(IBlueToothProtocol::BASE64);
            }
        } else {
            //zovye接口出货
            $timeout = isset($options['timeout']) ? intval($options['timeout']) : DEFAULT_DEVICE_WAIT_TIMEOUT;
            if ($timeout <= 0) {
                $timeout = DEFAULT_DEVICE_WAIT_TIMEOUT;
            }

            $extra = $options;

            //附加参数
            if (empty($extra['ip'])) {
                $extra['ip'] = CLIENT_IP;
            }

            if (empty($extra['user-agent'])) {
                $extra['user-agent'] = $_SERVER['HTTP_USER_AGENT'];
            }

            //打开设备，出货
            /** @var string|array $result */
            $result = $this->open($mcb_channel, $num, $timeout, $extra);

            if (is_error($result)) {
                $this->setError($result['errno'], $result['message']);
                if (empty($options['test'])) {
                    $this->scheduleErrorNotifyJob($result['errno'], $result['message']);
                }
            } elseif (is_error($result['data'])) {
                $this->setError($result['data']['errno'], $result['data']['message']);
                if (empty($options['test'])) {
                    $this->scheduleErrorNotifyJob($result['data']['errno'], $result['data']['message']);
                }
                $result = $result['data'];
            }
        }

        $this->save();

        return $result;
    }

    public function scheduleErrorNotifyJob($errno, $err_msg)
    {
        //使用控制中心推送通知
        if ($this->isDeviceNotifyTimeout()) {
            Job::deviceErrorNotice($this->getId(), $errno, $err_msg);
        }
    }

    /**
     * 设备关联的出货主板是否在线
     * @param bool $use_cache
     * @return bool
     */
    public function isMcbOnline(bool $use_cache = true): bool
    {
        if ($this->isVDevice() || $this->isBlueToothDevice()) {
            return true;
        }

        if ($this->imei) {
            $res = CtrlServ::getV2(
                "device/$this->imei/mcb/online",
                [
                    'nocache' => !$use_cache ? 'true' : 'false',
                ]
            );

            return $res['status'] === true && $res['data']['mcb'] === true;
        }

        return false;
    }

    public function isDown(): bool
    {
        if ($this->settings('extra.isDown') == Device::STATUS_MAINTENANCE) {
            return true;
        }

        if (settings('device.errorDown')) {
            if ($this->getErrorCode()) {
                return true;
            }
        }

        return false;
    }

    public function setReady($scene = 'online', $is_ready = true): bool
    {
        return $this->updateSettings("last.$scene", $is_ready ? time() : 0);
    }

    public function isReadyTimeout($scene = 'online'): bool
    {
        return TIMESTAMP - $this->settings("last.$scene", 0) > 60;
    }

    /**
     * 出货操作
     * @param array $extra
     * @param int $channel
     * @param int $num
     * @param int $timeout
     * @return array
     */
    public function open(
        int $channel = Device::CHANNEL_DEFAULT,
        int $num = 1,
        int $timeout = DEFAULT_DEVICE_WAIT_TIMEOUT,
        array $extra = []
    ): array {
        if (!empty($extra['order_no'])) {
            $order_no = strval($extra['order_no']);
        }

        if (empty($order_no)) {
            $no_str = Util::random(16, true);
            $order_no =  'P'.We7::uniacid()."NO$no_str";
        }

        $params = [
            'deviceGUID' => $this->imei,
            'src' => json_encode($extra),
            'channel' => $channel,
            'timeout' => $timeout,
            'num' => $num,
        ];

        if ($extra['index']) {
            $params['index'] = $extra['index'];
            $params['unit'] = $extra['unit'];
        }

        $content = http_build_query($params);

        return CtrlServ::query("order/$order_no", ["nostr" => microtime(true)], $content);
    }

    /**
     * 设备上次故障通知是否已经超时
     * @return bool
     */
    public function isDeviceNotifyTimeout(): bool
    {
        $lastNotify = $this->getLastDeviceErrorNotify();
        if (empty($lastNotify) || time() - $lastNotify['createtime'] > settings('notice.delay.device_err', 1) * 3600) {
            return true;
        }

        return false;
    }

    /**
     * 设备上次故障通知
     * @return array
     */
    public function getLastDeviceErrorNotify(): array
    {
        return $this->get('lastErrorNotify', []);
    }

    /**
     * 从控制中心获取AppId并绑定
     * @return bool
     */
    public function updateAppId(): bool
    {
        if (empty($this->getAppId())) {
            $imei = $this->getImei();
            if ($imei) {
                $res = CtrlServ::query("device/$imei", []);
                if (!is_error($res) && $res['appUID']) {
                    $this->setAppId($res['appUID']);

                    return $this->save();
                }
            }
        }

        return false;
    }

    /**
     * 获取出货成功或者失败的转跳设置
     * @param string $when
     * @return array
     */
    public function getRedirectUrl(string $when = 'success'): array
    {
        $delay = 0;

        $advs = $this->getAds(Advertising::REDIRECT_URL);
        if ($advs) {
            foreach ($advs as $adv) {

                if ($adv['extra']['when'][$when]) {
                    $url = $adv['extra']['url'];
                    $delay = $adv['extra']['delay'];

                    if ($url) {
                        break;
                    }
                }
            }
        }

        if (empty($url)) {
            $url = settings("misc.redirect.$when.url");
        }

        return ['url' => PlaceHolder::replace($url, [$this]), 'delay' => intval($delay)];
    }

    public function getLocation()
    {
        return $this->settings('extra.location.tencent', $this->settings('extra.location.baidu', []));
    }

    /**
     * 设备需要定位吗？
     * @return bool
     */
    public function needValidateLocation(): bool
    {
        if (App::isLocationValidateEnabled()) {
            if (empty(settings('user.location.validate.force'))) {
                $location = $this->settings('extra.location.tencent', $this->settings('extra.location', []));
                if (empty($location) || empty($location['lng']) || empty($location['lat'])) {
                    return false;
                }
            }

            $agent = $this->getAgent();
            if ($agent) {
                return Agent::isLocationValidateEnabled($agent);
            }

            return true;
        }

        return false;
    }

    /**
     * 获取指定的商品
     * @param int $goods_id
     * @return array
     */
    public function getGoods(int $goods_id): array
    {
        return Device::getGoods($this, $goods_id);
    }

    /**
     * @return keeperModelObj[]
     */
    public function getKeepers(): array
    {
        $result = [];
        $query = m('keeper_devices')->where(['device_id' => $this->getId()]);
        /** @var keeper_devicesModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $res = Keeper::get($entry->getKeeperId());
            if ($res) {
                $result[] = $res;
            }
        }

        return $result;
    }

    /**
     * @param bool $detail
     * @return array
     */
    public function getTypeData(bool $detail = false): array
    {
        $type = DeviceTypes::from($this);
        if ($type) {
            return DeviceTypes::format($type, $detail);
        }

        return [];
    }

    /**
     * 未知类型设备
     * @return bool
     */
    public function isUnknownType(): bool
    {
        return $this->device_type < 1;
    }

    public function removeKeeper($keeper): bool
    {
        if ($keeper instanceof keeperModelObj) {
            $keeper_id = $keeper->getId();
        } else {
            $keeper_id = intval($keeper);
        }

        $res = m('keeper_devices')->findOne(['keeper_id' => $keeper_id, 'device_id' => $this->getId()]);
        if ($res) {
            return $res->destroy();
        }

        return false;
    }

    public function getKeeperData($keeper): array
    {
        if ($keeper instanceof keeperModelObj) {
            $keeper_id = $keeper->getId();
        } else {
            $keeper_id = intval($keeper);
        }

        if (empty($keeper_id)) {
            return [];
        }

        $device_id = $this->getId();
        /** @var keeper_devicesModelObj $res */
        $res = m('keeper_devices')->findOne(
            [
                'device_id' => $device_id,
                'keeper_id' => $keeper_id,
            ]
        );

        if ($res) {
            $data = [
                'kind' => $res->getKind(),
                'way' => $res->getWay(),
            ];

            if ($res->getCommissionFixed() != -1) {
                $data['fixed'] = $res->getCommissionFixed();
            } else {
                $data['percent'] = $res->getCommissionPercent();
            }

            return $data;
        }

        //返回代理商默认设置
        $agent = $this->getAgent();
        if ($agent) {
            return $agent->settings('agentData.keeper.data', []);
        }

        return [];
    }

    public function getKeeperKind($keeper): int
    {
        if ($keeper instanceof keeperModelObj) {
            $keeper_id = $keeper->getId();
        } else {
            $keeper_id = intval($keeper);
        }

        if (empty($keeper_id)) {
            return 0;
        }

        $device_id = $this->getId();
        /** @var keeper_devicesModelObj $res */
        $res = m('keeper_devices')->findOne(
            [
                'device_id' => $device_id,
                'keeper_id' => $keeper_id,
            ]
        );

        if ($res) {
            return intval($res->getKind());
        }

        return 0;
    }

    public function getCommissionValue($keeper): array
    {
        if ($keeper instanceof keeperModelObj) {
            $keeper_id = $keeper->getId();
        } else {
            $keeper_id = intval($keeper);
        }

        if (empty($keeper_id)) {
            return Keeper::DEFAULT_COMMISSION_VAL;
        }

        $device_id = $this->getId();
        /** @var keeper_devicesModelObj $res */
        $res = m('keeper_devices')->findOne(
            [
                'device_id' => $device_id,
                'keeper_id' => $keeper_id,
            ]
        );

        if ($res) {
            return $res->getCommissionValue();
        }

        return Keeper::DEFAULT_COMMISSION_VAL;
    }

    public function setKeeper($keeper, $data = []): bool
    {
        if (!empty($keeper)) {
            if ($keeper instanceof keeperModelObj) {
                $keeper_id = $keeper->getId();
            } else {
                $keeper_id = intval($keeper);
            }
            $cond = [
                'keeper_id' => $keeper_id,
                'device_id' => $this->getId(),
            ];

            /** @var keeper_devicesModelObj $res */
            $res = m('keeper_devices')->findOne($cond);
            if (!empty($res)) {
                if ($data['fixed']) {
                    $res->setCommissionFixed(intval($data['fixed']));
                } else {
                    $res->setCommissionPercent(intval($data['percent']));
                }

                $res->setKind(intval($data['kind']));
                $res->setWay(intval($data['way']));

                return $res->save();
            } else {
                if (isset($data['percent'])) {
                    $cond['commission_percent'] = intval($data['percent']);
                } else {
                    $cond['commission_fixed'] = intval($data['fixed']);
                }

                $cond['kind'] = intval($data['kind']);
                $cond['way'] = intval($data['way']);

                $res = m('keeper_devices')->create($cond);

                return !empty($res);
            }
        }

        return false;
    }

    public function hasKeeper($keeper): bool
    {
        if (!empty($keeper)) {
            if ($keeper instanceof keeperModelObj) {
                $keeper_id = $keeper->getId();
            } else {
                $keeper_id = intval($keeper);
            }

            $res = m('keeper_devices')->findOne(['keeper_id' => $keeper_id, 'device_id' => $this->getId()]);

            return !empty($res);
        }

        return false;
    }

    /**
     * 因为device_view继承device，所以在这里要绑定settings一些参数
     * @param $key
     * @param string $classname
     * @return string
     */
    protected function getSettingsKey($key, string $classname = ''): string
    {
        return parent::getSettingsKey($key, deviceModelObj::class);
    }

    protected function getSettingsBindClassName(): string
    {
        return 'device';
    }

    /**
     * 统计设备上线次数
     */
    public function firstMsgStatistic(): bool
    {
        $statistic = $this->get('firstMsgStatistic', []);

        $month = date('Ym');
        $day = date('d');

        $statistic[$month][$day]['total'] = intval($statistic[$month][$day]['total']) + 1;

        $this->set('firstMsgStatistic', [$month => $statistic[$month]]);

        return $this->save();
    }

    public function payloadQuery($cond = []): modelObjFinder
    {
        return PayloadLogs::query(['device_id' => $this->getId()])->where($cond);
    }

    public function logQuery($cond = []): modelObjFinder
    {
        return m('device_logs')->where(We7::uniacid(['title' => $this->getImei()]))->where($cond);
    }

    public function eventQuery($cond = []): modelObjFinder
    {
        return m('device_events')->where(We7::uniacid(['device_uid' => $this->getUid()]))->where($cond);
    }

    public function isOwnerOrSuperior(userModelObj $user): bool
    {
        $agent = $user->isPartner() ? $user->getPartnerAgent() : $user;

        return Device::isOwner($this, $agent);
    }

    public function getGoodsByLane($lane): array
    {
        return Device::getGoodsByLane($this, $lane);
    }

    public function getGoodsTotal($goods_id): int
    {
        $total = 0;
        $payload = $this->getPayload();
        if ($payload && $payload['cargo_lanes']) {
            foreach ($payload['cargo_lanes'] as $entry) {
                if ($entry['goods'] == $goods_id) {
                    $total += intval($entry['num']);
                }
            }
        }

        return $total;
    }

    public function getGoodsAndPackages($user, $params = []): array
    {
        $result = [];
        $w = $this->settings('extra.goodsList');
        if (empty($w) || $w == 'all' || $w == 'goods') {
            $result['goods'] = $this->getGoodsList($user, $params);
        }
        if ($w == 'all' || $w == 'packages') {
            $result['packages'] = $this->getPackages();
        }

        return $result;
    }

    public static function disableFree(array &$goodsData)
    {
        $goodsData[Goods::AllowFree] = false;

        if (Balance::isFreeOrder()) {
            $goodsData[Goods::AllowBalance] = false;
            $goodsData[Goods::AllowDelivery] = false;
        }
    }

    public static function disablePay(array &$goodsData)
    {
        $goodsData[Goods::AllowPay] = false;

        if (Balance::isPayOrder()) {
            $goodsData[Goods::AllowBalance] = false;
            $goodsData[Goods::AllowDelivery] = false;
        }
    }

    public static function checkGoodsQuota(userModelObj $user, array &$goodsData, $params)
    {
        $goods = Goods::get($goodsData['id']);
        if ($goods) {
            $quota = $goods->getQuota();

            if (!isEmptyArray($quota)) {
                if ($goods->allowFree() || (($goods->allowBalance() || $goods->allowDelivery(
                            )) && Balance::isFreeOrder())) {
                    $day_limit = $quota['free']['day'];
                    if ($day_limit > 0) {
                        $day_total = $user->getTodayFreeTotal($goods->getId());
                        if ($day_total >= $day_limit) {
                            self::disableFree($goodsData);
                        } elseif (!empty($params[Goods::AllowFree]) || in_array(Goods::AllowFree, $params)) {
                            $goodsData['num'] = min($goodsData['num'], $day_limit - $day_total);
                        }
                    }

                    $all_limit = $quota['free']['all'];
                    if ($all_limit > 0) {
                        $all_total = $user->getFreeTotal($goods->getId());
                        if ($all_total >= $all_limit) {
                            self::disableFree($goodsData);
                        } elseif (!empty($params[Goods::AllowFree]) || in_array(Goods::AllowFree, $params)) {
                            $goodsData['num'] = min($goodsData['num'], $all_limit - $all_total);
                        }
                    }
                }

                if ($goods->allowPay()) {
                    $day_limit = $quota['pay']['day'];
                    if ($day_limit > 0) {
                        $day_total = $user->getTodayPayTotal($goods->getId());
                        if ($day_total >= $day_limit) {
                            self::disablePay($goodsData);
                        } elseif (!empty($params[Goods::AllowPay]) || in_array(Goods::AllowPay, $params)) {
                            $goodsData['num'] = min($goodsData['num'], $day_limit - $day_total);
                        }
                    }

                    $all_limit = $quota['pay']['all'];
                    if ($all_limit > 0) {
                        $all_total = $user->getPayTotal($goods->getId());
                        if ($all_total >= $all_limit) {
                            self::disablePay($goodsData);
                        } elseif (!empty($params[Goods::AllowPay]) || in_array(Goods::AllowPay, $params)) {
                            $goodsData['num'] = min($goodsData['num'], $all_limit - $all_total);
                        }
                    }
                }
            }
        }
    }

    public function getGoodsList(userModelObj $user = null, $params = []): array
    {
        $result = [];

        $payload = $this->getPayload();
        $checkFN = function ($data) use ($params) {
            if ($params) {
                if ((!empty($params[Goods::AllowPay]) || in_array(
                            Goods::AllowPay,
                            $params
                        )) && empty($data[Goods::AllowPay])) {
                    return false;
                }
                if ((!empty($params[Goods::AllowFree]) || in_array(
                            Goods::AllowFree,
                            $params
                        )) && empty($data[Goods::AllowFree])) {
                    return false;
                }
                if ((!empty($params[Goods::AllowBalance]) || in_array(Goods::AllowBalance, $params))) {
                    if (empty($data[Goods::AllowBalance]) || empty($data['balance'])) {
                        return false;
                    }
                }
            }

            return true;
        };

        if ($payload && $payload['cargo_lanes']) {
            foreach ($payload['cargo_lanes'] as $entry) {
                $goods_data = Goods::data($entry['goods'], ['useImageProxy' => true]);
                if (empty($goods_data)) {
                    continue;
                }

                if (!$checkFN($goods_data)) {
                    continue;
                }

                $goods_data['num'] = $entry['num'];
                if ($this->isCustomizedType() && isset($entry['goods_price'])) {
                    $goods_data['price'] = $entry['goods_price'];
                }

                $key = "goods{$goods_data['id']}";
                if ($result[$key]) {
                    $result[$key]['num'] += intval($goods_data['num']);
                    //如果相同商品设置了不同价格，则使用更高的价格
                    if ($result[$key]['price'] < $goods_data['price']) {
                        $result[$key]['price'] = $goods_data['price'];
                        $result[$key]['price_formatted'] = '￥'.number_format($goods_data['price'] / 100, 2).'元';
                    }
                } else {
                    $data = [
                        'id' => $goods_data['id'],
                        'name' => $goods_data['name'],
                        'img' => $goods_data['img'],
                        'detail_img' => $goods_data['detailImg'],
                        'price' => $goods_data['price'],
                        'price_formatted' => '￥'.number_format($goods_data['price'] / 100, 2).'元',
                        'num' => intval($goods_data['num']),
                        Goods::AllowFree => $goods_data[Goods::AllowFree],
                        Goods::AllowPay => $goods_data[Goods::AllowPay],
                        Goods::AllowBalance => $goods_data[Goods::AllowBalance],
                    ];

                    if (isset($goods_data['balance'])) {
                        $data['balance'] = (int)$goods_data['balance'];
                    }

                    if (!empty($user)) {
                        self::checkGoodsQuota($user, $data, $params);
                        if (!$checkFN($data)) {
                            continue;
                        }

                        $discount = User::getUserDiscount($user, $goods_data);
                        $data['discount'] = $discount;
                        $data['discount_formatted'] = '￥'.number_format($discount / 100, 2).'元';
                    }

                    $result[$key] = $data;
                }
            }

            $result = array_values($result);
        }

        return $result;
    }

    protected function isPackageOk(array $data): bool
    {
        $goods_list = [];
        foreach ($data['list'] as $item) {
            $goods_list[$item['goods_id']] += $item['num'];
        }

        foreach ($goods_list as $id => $num) {
            $payload = $this->getGoods($id);
            if (empty($payload) || empty($num) || $payload['num'] < $num) {
                return false;
            }
        }

        return true;
    }

    public function getPackage($id): array
    {
        $result = [];
        $package = Package::findOne(['device_id' => $this->getId(), 'id' => $id]);
        if ($package) {
            $result = $package->format(true, false);
            $result['isOk'] = $this->isPackageOk($result);
        }

        return $result;
    }

    public function getPackages(): array
    {
        $result = [];

        $query = Package::query(['device_id' => $this->getId()]);
        $query->orderBy('id ASC');

        /** @var packageModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = $entry->format(true);
            $data['isOk'] = $this->isPackageOk($data);
            $result[] = $data;
        }

        return $result;
    }

    public function getPullStats(): array
    {
        $query = $this->logQuery(['data REGEXP' => 's:8:"timeUsed"']);
        $query->limit(10);
        $query->orderBy('id DESC');

        $stats = [];

        $all = $query->findAll([], true);

        $last = $all->current();
        if ($last) {
            $c = $this->get('pull_stats');
            if ($c && $c['id'] == $last->getId()) {
                return $c['data'];
            }
        }

        foreach ($all as $entry) {
            $result = $entry->getData();
            if ($result) {
                $result_data = $result['data'] ?? $result;
                $time_used = intval($result_data['timeUsed']);
                if ($time_used > 0) {
                    $stats[] = round($time_used / 1000, 2);
                }
            }
        }

        if ($last) {
            $this->set('pull_stats', [
                'id' => $last->getId(),
                'data' => $stats,
            ]);
        }

        return $stats;
    }

    public function confirmLAC(): bool
    {
        $this->setS1(0);

        return $this->save();
    }

    public function openDoor($index): bool
    {
        return $this->mcbNotify('run', '', [
            'ser' => Util::random(16, true),
            'sw' => $index,
        ]);
    }

    public function generateChargingSerial(int $chargerID): string
    {
        $locker = Locker::try("charging:serial:{$this->imei}");
        if ($locker) {
            $chargingData = $this->settings('extra.chargingData', []);
            if (date('Ymd', $chargingData['last']) != date('Ymd')) {
                $index = 0;
            } else {
                $index = intval($chargingData['index']);
            }

            $index ++;

            $this->updateSettings('extra.chargingData', [
                'last' => time(),
                'index' => $index,
            ]);

            $locker->release();
        } else {
            $index = rand(8000, 9999);
        }
        
        $serial = sprintf('%s%02d%s%04d', $this->imei, $chargerID, date('Ymd'), $index);
        if (Order::exists($serial)) {
            return self::generateChargingSerial($chargerID);
        }
        if (Pay::getPayLog($serial)) {
            return self::generateChargingSerial($chargerID); 
        }

        return $serial;
    }

    public function getAdDeviceUID(): string
    {
        return $this->settings('extra.ad.device.uid', '');
    }
}
