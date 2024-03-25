<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use Exception;
use zovye\App;
use zovye\base\ModelObj;
use zovye\base\ModelObjFinder;
use zovye\BlueToothProtocol;
use zovye\contract\bluetooth\IBlueToothProtocol;
use zovye\CtrlServ;
use zovye\domain\Account;
use zovye\domain\AdStats;
use zovye\domain\Advertising;
use zovye\domain\Agent;
use zovye\domain\Balance;
use zovye\domain\CommissionValue;
use zovye\domain\Device;
use zovye\domain\DeviceEvents;
use zovye\domain\DeviceLogs;
use zovye\domain\Goods;
use zovye\domain\Group;
use zovye\domain\Keeper;
use zovye\domain\Locker;
use zovye\domain\Maintenance;
use zovye\domain\Order;
use zovye\domain\Package;
use zovye\domain\PayloadLogs;
use zovye\domain\Tags;
use zovye\domain\User;
use zovye\domain\DeviceTypes;
use zovye\Job;
use zovye\Log;
use zovye\Pay;
use zovye\Stats;
use zovye\util\DeviceUtil;
use zovye\util\Helper;
use zovye\util\PlaceHolder;
use zovye\util\QRCodeUtil;
use zovye\util\SIMUtil;
use zovye\util\Util;
use zovye\We7;
use function zovye\err;
use function zovye\error;
use function zovye\getArray;
use function zovye\is_error;
use function zovye\isEmptyArray;
use function zovye\m;
use function zovye\settings;
use function zovye\tb;

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
 * @method setS2(int $param)
 * @method getS2()
 * @method setS3(bool $v)
 * @method getS3()
 * @method setLastOrder($ts)
 */
class deviceModelObj extends ModelObj
{
    /** @var int */
    protected $id;

    protected $uniacid;

    /** @var int */
    protected $agent_id;

    /** @var int */
    protected $group_id;

    /** @var int */
    protected $device_type;

    /** @var string */
    protected $name;

    /** @var int */
    protected $remain;

    /** @var string */
    protected $imei;

    protected $app_id;

    /** @var string */
    protected $iccid;

    /** @var string */
    protected $qrcode;

    /** @var int */
    protected $last_online;

    /** @var int */
    protected $last_ping;

    protected $mcb_online;

    /** @var int */
    protected $app_last_online;

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

    /** @var int */
    protected $s3; //是否维护中

    protected $last_order;

    /** @var int */
    protected $createtime;

    private $agent = null;

    public static function getTableName($read_or_write): string
    {
        return tb('device');
    }

    /**
     * 返回设备UID
     */
    public function getUid()
    {
        return $this->getImei();
    }

    public function support($key): bool
    {
        switch ($key) {
            case 'last_order':
                return boolval(settings('migration.device.last_order'));
            default:
                return false;
        }
    }

    /**
     * 出货记录
     */
    public function goodsLog(int $level, array $data = []): bool
    {
        return $this->log($level, $this->getImei(), $data);
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
        if ($this->isVDevice() || $this->isDummyDevice()) {
            return TIMESTAMP;
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
        return $this->settings('extra.event.log.enabled', settings('device.eventLog.enabled'));
    }

    /**
     * 是不是普通设备?
     */
    public function isNormalDevice(): bool
    {
        return $this->getDeviceModel() == Device::NORMAL_DEVICE;
    }

    /**
     * 是不是虚拟设备?
     */
    public function isVDevice(): bool
    {
        return App::isVDeviceSupported() && ($this->settings('device.is_vd') ||
                $this->getDeviceModel() == Device::VIRTUAL_DEVICE);
    }

    /**
     * 是不是蓝牙设备?
     */
    public function isBlueToothDevice(): bool
    {
        return $this->getDeviceModel() == Device::BLUETOOTH_DEVICE;
    }

    /**
     * 是不是充电桩?
     */
    public function isChargingDevice(): bool
    {
        return $this->getDeviceModel() == Device::CHARGING_DEVICE;
    }

    /**
     * 是不是尿素加注机?
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

    public function getBluetoothTimeout(): int
    {
        return $this->settings('extra.bluetooth.timeout', 0);
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
        $saved = $this->getChargerStatusData($chargerID);

        // 当地政策要求显示小数点后三位
        if ($data['chargedKWH']) {
            if ($data['chargedKWH'] < $saved['chargedKWH']) {
                $data['chargedKWH'] = $saved['chargedKWH'];
            } else {
                $data['chargedKWH'] += rand(1, 10) / 1000.00;
            }
        }

        $data = array_merge($saved, $data);

        return $this->updateSettings("charger_$chargerID", $data);
    }

    public function setChargerProperty($chargerID, $key, $val = null): bool
    {
        if (is_string($key)) {
            return $this->updateSettings("charger_$chargerID.$key", $val);
        }

        if (is_array($key)) {
            $data = $this->settings("charger_$chargerID", []);
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

    /**
     * 设置当前加注状态
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

        return SIMUtil::get($this->getICCID());
    }

    /**
     * 获取设备信号强度
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
            } catch (Exception $e) {
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

    public function cleanLastError()
    {
        $this->setErrorCode(0);
    }

    public function setLastError(int $code, string $message = '')
    {
        $this->setErrorCode($code);
        if ($code !== 0) {
            $this->set(
                'lastErrorData',
                [
                    'createtime' => time(),
                    'code' => $code,
                    'message' => $message,
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
     */
    public function resetAllData(): bool
    {
        //删除订单
        We7::pdo_delete(m('order')->getTableName(), We7::uniacid(['device_id' => $this->getId()]));
        We7::pdo_delete(Maintenance::model()->getTableName(), We7::uniacid(['device_id' => $this->getId()]));
        We7::pdo_delete(AdStats::model()->getTableName(), We7::uniacid(['device_id' => $this->getId()]));
        We7::pdo_delete(DeviceEvents::model()->getTableName(), We7::uniacid(['device_uid' => $this->getUid()]));

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

        $this->setTagsFromText('');
        $this->updateQRCode(true);

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
     */
    public function resetPayload(array $data = [], string $reason = '', int $now = 0): array
    {
        static $cache = [];

        $now = empty($now) ? TIMESTAMP : $now;

        if ($cache[$now]) {
            $clr = $cache[$now];
        } else {
            $clr = Util::randColor();
            $cache[$now] = $clr;
        }

        $result = Device::resetPayload($this, $data);

        if (is_error($result)) {
            return $result;
        }

        foreach ($result as $entry) {
            $code = $this->getPayloadCode($now);
            if (!empty($entry['reason'])) {
                $reason = $reason."({$entry['reason']})";
            }
            if (!PayloadLogs::create([
                'device_id' => $this->id,
                'lane_id' => $entry['laneIndex'],
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

        return $result;
    }

    public function resetGoodsNum($goods_id, $delta, $reason = ''): array
    {
        $goods = $this->getGoods($goods_id, false);
        if ($goods) {
            return $this->resetPayload([$goods['cargo_lane'] => $delta], $reason);
        }

        return err('找不到指定的商品！');
    }

    /**
     * 设置设备标签，文本形式指定
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
                    $condition = ['title' => $text];
                    /** @var tagsModelObj $tag */
                    $tag = Tags::findOne($condition);
                    if (empty($tag)) {
                        $condition['count'] = 1;
                        $tag = Tags::create($condition);
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
            $tag = Tags::get($id);
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
     */
    public function updateQRCode(bool $force = false): bool
    {
        $need_notify = false;

        if ($this->isBlueToothDevice()) {
            if (empty($this->qrcode) || $force) {
                $need_notify = !is_error($this->createQRCodeFile());
            }
        } else {
            //无论什么情况都要更换shadowId!
            $this->resetShadowId();

            if ($force || $this->isActiveQRCodeEnabled() || empty($this->qrcode)) {
                $need_notify = !is_error($this->createQRCodeFile());
            }
        }

        return $need_notify && $this->updateAppQRCode();
    }

    /**
     * 重置设备shadowId
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
     */
    public function isActiveQRCodeEnabled(): bool
    {
        return $this->settings('extra.activeQrcode', 0);
    }

    public function createChargerQRCode()
    {
        $chargerNum = $this->getChargerNum();
        for ($i = 0; $i < $chargerNum; $i++) {

            $chargerID = $i + 1;

            $url = Util::murl('device', [
                'charging' => true,
                'device' => $this->getImei(),
                'charger' => $chargerID,
            ]);

            $res = QRCodeUtil::createFile(
                "device.$this->imei$chargerID",
                $url,
                function ($filename) use ($chargerID) {
                    QRCodeUtil::renderTxt($filename, sprintf("%s%02d", $this->imei, $chargerID));
                }
            );
            if (is_error($res)) {
                return $res;
            }
            $this->setChargerProperty($chargerID, 'qrcode', $res);
        }

        return true;
    }

    public function createQRCodeFileForAllLanes()
    {
        $device_type = DeviceTypes::from($this);
        if ($device_type) {
            $lanes = $device_type->getCargoLanes();
            for ($i = 0; $i < count($lanes); $i++) {
                $this->createQRCodeFileForLane($i);
            }
        }
    }

    public function createQRCodeFileForLane(int $lane_id)
    {
        $url = $this->getUrl($lane_id);

        $filename_index = $lane_id + 1;
        $res = QRCodeUtil::createFile("device.$this->imei$filename_index", $url, function ($filename) use ($filename_index) {
            QRCodeUtil::renderTxt($filename, sprintf("%s%02d", $this->imei, $filename_index));
        });

        if (is_error($res)) {
            return $res;
        }

        return $this->updateSettings("qrcode.$lane_id", $res);
    }

    /**
     * 创建二维码文件
     */
    public function createQRCodeFile()
    {
        if ($this->isChargingDevice()) {
            $res = $this->createChargerQRCode();
            if (is_error($res)) {
                return $res;
            }
        }

        $url = $this->getUrl();

        $qrcode_file = QRCodeUtil::createFile("device.$this->imei", $url, function ($filename) {
            QRCodeUtil::renderTxt($filename, $this->imei);
        });

        if (is_error($qrcode_file)) {
            return false;
        }

        $this->setQrcode($qrcode_file);

        return $this->save();
    }

    /**
     * 获取取货链接
     */
    public function getUrl(int $lane_id = null): string
    {
        if (App::isFlashEggEnabled()) {
            $adDeviceUID = $this->getAdDeviceUID();
            if ($adDeviceUID) {
                return Util::murl('sample', ['device' => $this->imei]);
            }
        }

        $params = [];

        //小程序识别码
        $adv = $this->getOneAd(Advertising::WX_APP_URL_CODE);
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
        $params['device'] = $this->isActiveQRCodeEnabled() ? $this->shadow_id : $this->imei;

        if (isset($lane_id)) {
            $params['lane'] = $lane_id;
        }

        return Util::murl('entry', $params);
    }

    public function getProtocolV1Code()
    {
        return $this->settings('extra.v1.last_code');
    }

    /**
     * 通知app更新二维码
     */
    public function updateAppQRCode(): bool
    {
        $data = [
            'qrcode' => $this->getAccountQRCode(),
        ];

        if (empty($data['qrcode'])) {
            $data['qrcode'] = $this->getQrcode();
            $data['qrcode_url'] = $this->getUrl();
        }

        return $this->appPublishConfig($data);
    }

    /**
     * 获取设备二维码
     */
    public function getQrcode(): string
    {
        $url = Util::toMedia($this->qrcode);
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

            return Group::get($this->group_id);
        }

        return null;
    }

    public function appRestart(): bool
    {
        return $this->appPublish('restart');
    }

    public function appUpdateNotify(): bool
    {
        return $this->appPublish('update');
    }

    public function appPublishConfig(array $data = []): bool
    {
        return $this->appPublish('config', $data);
    }

    /**
     * 给app发送通知
     */
    public function appPublish(string $op = 'config', array $data = []): bool
    {
        if ($this->app_id) {
            return CtrlServ::appPublish($this->app_id, $op, $data);
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

        return $this->appPublish('message', [
            'content' => $msg,
            'type' => $type,
            'style' => $style ?? $styles[$type],
        ]);
    }

    public function autoResetPayload()
    {
        $remainWarning = App::getRemainWarningNum($this->getAgent());

        // 处理自动补货
        $data = [];

        $payload = $this->getPayload();

        foreach ((array)$payload['cargo_lanes'] as $index => $lane) {
            if (empty($lane['auto'])) {
                continue;
            }
            if ($lane['num'] < $remainWarning) {
                $data[$index] = '@0';
            }
        }

        if ($data) {
            $this->resetPayload($data, '自动补货');
            $this->save();
        }
    }

    /**
     *  检查设备剩余，如果设置允许，会推送通知
     */
    public function checkRemain()
    {
        $remainWarning = App::getRemainWarningNum($this->getAgent());

        // 缺货标志位
        $set_s2_flag = false;

        if ($remainWarning > 0) {
            if ($this->remain < $remainWarning) {
                $set_s2_flag = true;
                Job::deviceEventNotify($this, Device::EVENT_LOW_REMAIN);
            }
        }

        $this->setS2($this->remain < 1 || $set_s2_flag ? 1 : 0);
        $this->save();
    }

    /**
     * 以文本形式获取设备标签
     */
    public function getTagsAsText(bool $str = true)
    {
        $titles = [];

        if ($this->tags_data) {
            $tags = trim($this->tags_data, '<>');
            if ($tags) {
                foreach (explode('><', $tags) as $id) {
                    /** @var tagsModelObj $tag */
                    $tag = Tags::get($id);
                    if ($tag) {
                        $titles[$tag->getId()] = $tag->getTitle();
                    }
                }
            }
        }

        return $str ? implode(',', $titles) : $titles;
    }

    public function getAccountQRCode($download_to_local = true): string
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
                    $url = $account['qrcode'];

                    if ($download_to_local) {
                        return Util::toMedia(QRCodeUtil::downloadQRCode($url));
                    }

                    return $url;
                }
                Log::error('device', $res);
            }
        }

        return '';
    }

    /**
     * 获取设备的App配置
     */
    public function getAppConfig(): array
    {
        //音量
        $vol = $this->settings('extra.volume', 0);

        //引导图
        $res = $this->getOneAd(Advertising::SCREEN_NAV);
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

        foreach ($this->getAllAds(Advertising::SCREEN) as $adv) {

            if ($adv['extra']['media'] == Advertising::MEDIA_SRT) {
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
                $data['url'] = Util::toMedia($adv['extra']['url']);
                if ($data['type'] == Advertising::MEDIA_IMAGE) {
                    $data['duration'] = intval($adv['extra']['duration'] ?: settings('advs.image.duration', 10)) * 1000;
                }
                if ($adv['extra']['area']) {
                    $data['area'] = intval($adv['extra']['area']);
                }
                if ($adv['extra']['scene']) {
                    $data['scene'] = strval($adv['extra']['scene']);
                }
                $ads[] = $data;
            }
        }

        //其它配置
        $cfg = [
            'banner' => Util::toMedia($banner),
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
        $cfg = array_merge($cfg, Device::getPayload($this, false, true));

        //字幕
        if ($srt['subs']) {
            $cfg['srt'] = $srt;
        }

        return $cfg;
    }

    /**
     * 获取一个指定类型的广告
     */
    public function getOneAd($type, bool $random = false, callable $filterFN = null): ?array
    {
        $ads = $this->getAllAds($type);
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
     */
    public function getAllAds($type, bool $ignore_cache = false): array
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
     */
    public function isMatched($assign_data): bool
    {
        return DeviceUtil::isAssigned($this, $assign_data);
    }

    /**
     * 获取设备标签ID
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

    public function getAppVersion(): string
    {
        return strval($this->settings('extra.v0.status.app.version'));
    }

    public function setAppVersion($ver): bool
    {
        return $this->updateSettings('extra.v0.status.app.version', $ver);
    }

    /**
     * 设备每天免费送货数量限制
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
     */
    public function setAgent(agentModelObj $agent = null)
    {
        $agent_id = is_object($agent) ? $agent->getAgentId() : intval($agent);
        $this->setAgentId($agent_id);
    }

    /**
     * 获取相关topics
     */
    public function getTopics(): array
    {
        $tags = [];

        //同一个平台的设备会订阅同一个主题
        $tags[] = ['name' => Util::encryptTopic()];

        if ($this->getId()) {

            $tags[] = ['name' => Util::encryptTopic('device'.$this->getId())];

            if ($this->agent_id) {
                $tags[] = ['name' => Util::encryptTopic('agent'.$this->getAgentId())];
            }

            if ($this->getGroupId()) {
                $tags[] = ['name' => Util::encryptTopic('group'.$this->getGroupId())];
            }
        }

        foreach ($this->getTagsAsId() as $id) {
            $tags[] = ['name' => Util::encryptTopic('tag'.$id)];
        }

        return $tags;
    }

    /**
     * 通知设备更新屏幕广告
     */
    public function updateScreenAdsData(): bool
    {
        if ($this->isAdsUpdated(Advertising::SCREEN)) {
            return $this->appUpdateNotify();
        }

        return false;
    }

    /**
     * 广告是否已经更新
     */
    public function isAdsUpdated($type): bool
    {
        if ($this->settings("adsData.type$type.version") != Advertising::version($type)) {
            return true;
        }

        $cachedData = $this->settings("adsData.type$type.data", []);
        $ads = $this->getAllAds($type, true);

        if (empty($cachedData) && empty($ads)) {
            return false;
        }

        return array_keys($cachedData) != array_keys($ads);
    }

    public function updateAccountData(): bool
    {
        return $this->accountsUpdated();
    }

    /**
     * 公众号是否已更新
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

        $res = CtrlServ::onlineV2($this->imei, $use_cache);

        if (is_error($res)) {
            return $res;
        }

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
     */
    public function isAppOnline(): bool
    {
        if ($this->app_id) {
            if ($this->imei) {
                $res = CtrlServ::appOnlineV2($this->imei, false);

                return $res['status'] === true && $res['data']['app'] === true;
            }
        }

        return false;
    }

    /**
     * 通知app更新音量
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

        $res = $this->appPublishConfig($data);

        return !is_error($res);
    }

    /**
     * 通知app更新剩余数量
     */
    public function updateAppRemain(): bool
    {
        $data = $this->getPayload(false, true);

        //目前如果没有srt，config通知会导致app隐藏字幕
        $srt = $this->getSrcConfig();
        if ($srt) {
            $data['srt'] = $srt;
        }

        $res = $this->appPublishConfig($data);

        return !is_error($res);
    }

    /**
     * 是否为自定义型号设备
     */
    public function isCustomizedType(): bool
    {
        return $this->device_type == 0;
    }

    public function getPayload(bool $detail = false, bool $available_restrict = false): array
    {
        return Device::getPayload($this, $detail, $available_restrict);
    }

    public function getCargoLanesNum(): int
    {
        $device_type = DeviceTypes::from($this);

        return $device_type ? $device_type->getCargoLanesNum() : 0;
    }

    public function getSrcConfig(): array
    {
        $subs = [];
        foreach ($this->getAllAds(Advertising::SCREEN) as $adv) {
            if ($adv['extra']['media'] == Advertising::MEDIA_SRT) {
                if (!empty($adv['extra']['text'])) {
                    $subs[] = strval($adv['extra']['text']);
                }
            }
        }

        if ($subs) {
            return [
                'speed' => intval(settings('ads.srt.speed', 1)),
                'subs' => $subs,
            ];
        }

        return [];
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
     */
    public function mcbPublish(string $op = 'params', string $code = '', array $data = []): bool
    {
        if ($this->imei) {
            if (empty($code)) {
                $code = $this->getProtocolV1Code();
            }

            return CtrlServ::mcbPublish($this->imei, $code, $op, $data);
        }

        return false;
    }

    /**
     * 启用/禁用动态二维码
     */
    public function enableActiveQrcode(bool $enable = true): bool
    {
        return $this->updateSettings('extra.activeQrcode', $enable ? 1 : 0);
    }

    public function isMcbStatusExpired(): bool
    {
        $update_time = $this->settings('extra.v1.status.updatetime', 0);

        return time() - $update_time > 60 * 60 * 60;
    }

    /**
     * 请求mcb报告状态
     */
    public function reportMcbStatus(string $code = '')
    {
        $this->mcbPublish('report', $code);
    }

    /**
     * 保存mcb报告的状态
     */
    public function updateMcbStatus(array $data = [])
    {
        $data['updatetime'] = time();
        $this->updateSettings('extra.v1.status', $data);
    }

    /**
     * 获取上次app更新推送信息
     */
    public function getLastApkUpgrade()
    {
        return $this->get('lastApkUpdate', []);
    }

    /**
     * 通知app更新APK
     */
    public function upgradeApk($title, $version, $url): bool
    {
        $data = [
            'version' => $version,
            'url' => $url,
        ];

        if ($this->appPublish('apk', $data)) {
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
     */
    public function getShadowId(): string
    {
        if (empty($this->shadow_id)) {
            $this->resetShadowId();
        }

        return $this->shadow_id;
    }

    /**
     * 尝试锁定设备
     */
    public function lockAcquire(int $retries = 0, int $delay_seconds = 1): ?lockerModelObj
    {
        return Locker::try("device:{$this->getImei()}", REQUEST_ID, $retries, $delay_seconds);
    }

    public function payloadLockAcquire(int $retries = 0, int $delay_seconds = 1): ?lockerModelObj
    {
        return Locker::try("payload:{$this->getImei()}", REQUEST_ID, $retries, $delay_seconds);
    }

    /**
     * 出货操作
     * 蓝牙设备出货操作可能会返回字符串，普通设备则返回成功或error()
     */
    public function pull(array $options = [])
    {
        //充电和加注设备不支持
        if ($this->isChargingDevice() || $this->isFuelingDevice()) {
            return err('设备不支持这个操作！');
        }

        //虚拟设备直接返回成功
        if ($this->isVDevice()) {
            return [
                'num' => max(1, $options['num']),
                'errno' => 0,
                'message' => '虚拟出货成功！',
            ];
        }

        //数量
        $num = max(1, $options['num']);

        //货道
        $mcb_channel = isset($options['channel']) ? intval($options['channel']) : Device::CHANNEL_DEFAULT;

        //zovye接口出货
        $timeout = isset($options['timeout']) ? intval($options['timeout']) : DEFAULT_DEVICE_WAIT_TIMEOUT;
        if ($timeout < 1) {
            $timeout = DEFAULT_DEVICE_WAIT_TIMEOUT;
        }

        //蓝牙设备出货处理
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
            if ($this->getBlueToothProtocolName() == 'hmb') {
                $bluetoothTimeout = $this->getBluetoothTimeout();
                $option['timeout'] = empty($bluetoothTimeout) ? $timeout : $bluetoothTimeout;
            } else {
                $option['timeout'] = $timeout;
            }

            $cmd = $protocol->open($this->getBUID(), $option);
            if ($cmd) {
                Device::createBluetoothCmdLog($this, $cmd);
                $result = $cmd->getEncoded(BlueToothProtocol::BASE64);

                if (Helper::isAutoRefundEnabled($this)) {
                    $order = Order::getLastOrderOfDevice($this);
                    if ($order) {
                        $delay = max(15, max(settings('order.rollback.delay', 0), $timeout));
                        //超时后检查订单是否成功，否则退款
                        Job::refund(
                            $order->getOrderNO(),
                            '设备响应超时',
                            0,
                            true,
                            $delay
                        );
                    }
                }

                return $result;
            }

            return null;
        }

        //普通设备出货请求处理

        if ($options['online'] && !$this->isMcbOnline()) {
            return err('设备已关机！');
        }

        //附加参数
        if (empty($options['ip'])) {
            $options['ip'] = CLIENT_IP;
        }

        if (empty($options['user-agent'])) {
            $options['user-agent'] = $_SERVER['HTTP_USER_AGENT'];
        }

        //打开设备，出货
        /** @var string|array $result */
        $result = $this->open($mcb_channel, $num, $timeout, $options);

        if (is_error($result)) {
            $this->setLastError($result['errno'], $result['message']);
            if (empty($options['test'])) {
                $this->scheduleErrorNotifyJob();
            }
        } elseif (is_error($result['data'])) {
            $this->setLastError($result['data']['errno'], $result['data']['message']);
            if (empty($options['test'])) {
                $this->scheduleErrorNotifyJob();
            }
            $result = $result['data'];
        }

        return $result;
    }

    public function scheduleErrorNotifyJob(): bool
    {
        //使用控制中心推送通知
        return Job::deviceEventNotify($this, Device::EVENT_ERROR);
    }

    /**
     * 设备关联的出货主板是否在线
     */
    public function isMcbOnline(bool $use_cache = true): bool
    {
        if ($this->isVDevice() || $this->isBlueToothDevice()) {
            return true;
        }

        if ($this->imei) {
            $res = CtrlServ::mcbOnlineV2($this->imei, $use_cache);

            return $res['status'] === true && $res['data']['mcb'] === true;
        }

        return false;
    }

    public function setMaintenance($v)
    {
        $this->setS3($v);
    }

    public function isMaintenance(): bool
    {
        if ($this->getS3() == Device::STATUS_MAINTENANCE) {
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
            $order_no = 'P'.We7::uniacid()."NO$no_str";
        }

        $data = [
            'deviceGUID' => $this->imei,
            'channel' => $channel,
            'timeout' => $timeout,
            'num' => $num,
        ];

        if (isset($extra['index'])) {
            $data['index'] = $extra['index'];
            $data['unit'] = $extra['unit'];
        }

        return CtrlServ::createOrder($order_no, $data);
    }

    /**
     * 设备上次故障通知是否已经超时
     */
    public function isNotificationTimeout(string $event): bool
    {
        $lastNotify = $this->getLastNotification($event);

        return empty($lastNotify) || time() - $lastNotify['time'] > 600;
    }

    /**
     * 设备上次故障通知
     */
    public function setLastNotification(string $event): bool
    {
        return $this->updateSettings("notification.$event.time", TIMESTAMP);
    }

    /**
     * 设备上次故障通知
     */
    public function getLastNotification(string $event): array
    {
        return (array)$this->settings("notification.$event", []);
    }

    /**
     * 从控制中心获取AppId并绑定
     */
    public function updateAppId(): bool
    {
        if (empty($this->getAppId())) {
            $imei = $this->getImei();
            if ($imei) {
                $res = CtrlServ::detail($imei);
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
     */
    public function getRedirectUrl(string $when = 'success'): array
    {
        $delay = 0;

        $ads = $this->getAllAds(Advertising::REDIRECT_URL);
        if ($ads) {
            foreach ($ads as $ad) {
                if ($ad['extra']['when'][$when]) {
                    $url = $ad['extra']['url'];
                    $delay = $ad['extra']['delay'];

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
        $location = $this->settings('extra.location.tencent', []);
        if (!isEmptyArray($location)) {
            return $location;
        }

        $location = $this->settings('extra.location.baidu', []);
        if (!isEmptyArray($location)) {
            return $location;
        }

        return [
            'address' => $this->settings('extra.address', ''),
        ];
    }

    /**
     * 设备需要定位吗
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
     */
    public function getGoods(int $goods_id, bool $available_restrict = true): array
    {
        return Device::getGoods($this, $goods_id, $available_restrict);
    }

    public function getGoodsByLane($lane, $params = [], $available_restrict = true): array
    {
        return Device::getGoodsByLane($this, $lane, $params, $available_restrict);
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

    public function getGoodsAndPackages($user, $params = [], $available_restrict = true): array
    {
        $result = [];

        $w = $this->goodsListViewStyle();

        if ($w == 'all' || $w == 'goods') {
            $result['goods'] = $this->getGoodsList($user, $params, $available_restrict);
        }

        if ($w == 'all' || $w == 'packages') {
            $result['packages'] = $this->getPackages();
        }

        return $result;
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

    public function removeKeeper($keeper): bool
    {
        if ($keeper instanceof keeperModelObj) {
            $keeper_id = $keeper->getId();
        } else {
            $keeper_id = intval($keeper);
        }

        $res = m('keeper_devices')->findOne([
            'keeper_id' => $keeper_id,
            'device_id' => $this->getId(),
        ]);
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

            if ($res->isFixedValue()) {
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

    public function getCommissionValue($keeper): ?CommissionValue
    {
        if ($keeper instanceof keeperModelObj) {
            $keeper_id = $keeper->getId();
        } else {
            $keeper_id = intval($keeper);
        }

        if (empty($keeper_id)) {
            return null;
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

        return null;
    }

    public function setKeeper($keeper, $data = []): bool
    {
        if (empty($keeper)) {
            return false;
        }

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
            if (App::isKeeperCommissionOrderDistinguishEnabled() && $data['way'] == Keeper::COMMISSION_ORDER) {
                if ($data['type'] == 'fixed') {
                    $res->setCommissionFixed(intval($data['pay_val']));
                    $res->setCommissionFreeFixed(intval($data['free_val']));
                } else {
                    $res->setCommissionPercent(intval($data['pay_val']));
                    $res->setCommissionFreePercent(intval($data['free_val']));
                }
            } else {
                if ($data['type'] == 'fixed') {
                    $res->setCommissionFixed(intval($data['val']));
                } else {
                    $res->setCommissionPercent(intval($data['val']));
                }
            }

            $res->setKind(intval($data['kind']));
            $res->setWay(intval($data['way']));

            if (App::isAppOnlineBonusEnabled()) {
                $res->setAppOnlineBonusPercent(intval($data['app_online_bonus_percent']));
            }
            
            if (App::isDeviceQoeBonusEnabled()) {
                $res->setDeviceQoeBonusPercent(intval($data['device_qoe_bonus_percent']));
            }
            
            return $res->save();
        }

        if (App::isKeeperCommissionOrderDistinguishEnabled() && $data['way'] == Keeper::COMMISSION_ORDER) {
            if ($data['type'] == 'fixed') {
                $cond['commission_fixed'] = intval($data['pay_val']);
                $cond['commission_free_fixed'] = intval($data['free_val']);
                $cond['commission_percent'] = -1;
                $cond['commission_free_percent'] = -1;
            } else {
                $cond['commission_percent'] = intval($data['pay_val']);
                $cond['commission_free_percent'] = intval($data['free_val']);
                $cond['commission_fixed'] = -1;
                $cond['commission_free_fixed'] = -1;
            }
        } else {
            if ($data['type'] == 'fixed') {
                $cond['commission_fixed'] = intval($data['val']);
                $cond['commission_percent'] = -1;
                $cond['commission_free_percent'] = -1;
            } else {
                $cond['commission_percent'] = intval($data['val']);
                $cond['commission_fixed'] = -1;
                $cond['commission_free_fixed'] = -1;
            }
        }

        $cond['kind'] = intval($data['kind']);
        $cond['way'] = intval($data['way']);

        $res = m('keeper_devices')->create($cond);

        return !empty($res);
    }

    public function hasKeeper($keeper, $op = null): bool
    {
        if (empty($keeper)) {
            return false;
        }

        if ($keeper instanceof keeperModelObj) {
            $keeper_id = $keeper->getId();
        } else {
            $keeper_id = intval($keeper);
        }

        $exists = m('keeper_devices')->exists([
            'keeper_id' => $keeper_id,
            'device_id' => $this->getId(),
        ]);

        if (!$exists) {
            return false;
        }

        if (isset($op)) {
            return $this->getKeeperKind($keeper) === $op;
        }

        return true;
    }

    /**
     * 因为device_view继承device，所以在这里要绑定settings一些参数
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
    public function updateFirstMsgStats(): bool
    {
        $statistic = $this->get('firstMsgStatistic', []);

        $month = date('Ym');
        $day = date('d');

        $statistic[$month][$day]['total'] = intval($statistic[$month][$day]['total']) + 1;

        $this->set('firstMsgStatistic', [$month => $statistic[$month]]);

        return $this->save();
    }

    public function payloadQuery($cond = []): ModelObjFinder
    {
        return PayloadLogs::query(['device_id' => $this->getId()])->where($cond);
    }

    public function logQuery($cond = []): ModelObjFinder
    {
        return DeviceLogs::query(['title' => $this->getImei()])->where($cond);
    }

    public function eventQuery($cond = []): ModelObjFinder
    {
        return DeviceEvents::query(['device_uid' => $this->getUid()])->where($cond);
    }

    public function isOwnerOrSuperior(userModelObj $user): bool
    {
        $agent = $user->isPartner() ? $user->getPartnerAgent() : $user;

        return Device::isOwner($this, $agent);
    }

    public function goodsListViewStyle()
    {
        if (App::isGoodsPackageEnabled()) {
            $w = $this->settings('extra.goodsList', '');
            if (in_array($w, ['all', 'goods', 'packages'])) {
                return $w;
            }
        }

        return 'goods';
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
            if (isEmptyArray($quota)) {
                return;
            }

            if ($goods->allowFree() || (($goods->allowBalance() || $goods->allowDelivery()) && Balance::isFreeOrder(
                    ))) {
                $day_limit = getArray($quota, 'free.day', 0);
                if ($day_limit > 0) {
                    $day_total = $user->getTodayFreeTotal($goods->getId());
                    if ($day_total >= $day_limit) {
                        self::disableFree($goodsData);
                    } elseif (!empty($params[Goods::AllowFree]) || in_array(Goods::AllowFree, $params, true)) {
                        $goodsData['num'] = min($goodsData['num'], $day_limit - $day_total);
                    }
                }

                $all_limit = getArray($quota, 'free.all', 0);
                if ($all_limit > 0) {
                    $all_total = $user->getFreeTotal($goods->getId());
                    if ($all_total >= $all_limit) {
                        self::disableFree($goodsData);
                    } elseif (!empty($params[Goods::AllowFree]) || in_array(Goods::AllowFree, $params, true)) {
                        $goodsData['num'] = min($goodsData['num'], $all_limit - $all_total);
                    }
                }
            }

            if ($goods->allowPay()) {
                $day_limit = getArray($quota, 'pay.day', 0);
                if ($day_limit > 0) {
                    $day_total = $user->getTodayPayTotal($goods->getId());
                    if ($day_total >= $day_limit) {
                        self::disablePay($goodsData);
                    } elseif (!empty($params[Goods::AllowPay]) || in_array(Goods::AllowPay, $params, true)) {
                        $goodsData['num'] = min($goodsData['num'], $day_limit - $day_total);
                    }
                }

                $all_limit = getArray($quota, 'pay.all', 0);
                if ($all_limit > 0) {
                    $all_total = $user->getPayTotal($goods->getId());
                    if ($all_total >= $all_limit) {
                        self::disablePay($goodsData);
                    } elseif (!empty($params[Goods::AllowPay]) || in_array(Goods::AllowPay, $params, true)) {
                        $goodsData['num'] = min($goodsData['num'], $all_limit - $all_total);
                    }
                }
            }
        }
    }

    public function getGoodsList(userModelObj $user = null, $params = [], $available_restrict = true): array
    {
        $result = [];

        $payload = $this->getPayload(false, $available_restrict);
        $checkFN = function ($data) use ($params) {
            if ($params) {
                if ((!empty($params[Goods::AllowPay]) || in_array(
                            Goods::AllowPay,
                            $params,
                            true
                        )) && empty($data[Goods::AllowPay])) {
                    return false;
                }
                if ((!empty($params[Goods::AllowFree]) || in_array(
                            Goods::AllowFree,
                            $params,
                            true
                        )) && empty($data[Goods::AllowFree])) {
                    return false;
                }
                if ((!empty($params[Goods::AllowBalance]) || in_array(Goods::AllowBalance, $params, true))) {
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
                        $result[$key]['price_formatted'] = number_format($goods_data['price'] / 100, 2).'元';
                    }
                } else {
                    $data = [
                        'id' => $goods_data['id'],
                        'name' => $goods_data['name'],
                        'img' => $goods_data['img'],
                        'detail_img' => $goods_data['detailImg'],
                        'price' => $goods_data['price'],
                        'price_formatted' => number_format($goods_data['price'] / 100, 2).'元',
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
                        $data['discount_formatted'] = number_format($discount / 100, 2).'元';
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
        /** @var  device_logsModelObj $entry */
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
        return $this->mcbPublish('run', '', [
            'ser' => Util::random(16, true),
            'sw' => $index,
        ]);
    }

    public function generateChargingSerial(int $chargerID): string
    {
        $locker = Locker::try("charging:serial:$this->imei");
        if ($locker) {
            $chargingData = $this->settings('extra.chargingData', []);
            if (date('Ymd', $chargingData['last']) != date('Ymd')) {
                $index = 0;
            } else {
                $index = intval($chargingData['index']);
            }

            $index++;

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
