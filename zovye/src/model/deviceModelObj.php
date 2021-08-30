<?php

/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye\model;

use Exception;
use zovye\App;
use zovye\Job;

use zovye\Locker;
use zovye\Package;
use zovye\PayloadLogs;
use zovye\PlaceHolder;
use zovye\We7;
use zovye\User;
use zovye\Util;
use zovye\Agent;
use zovye\Goods;
use zovye\Group;

use zovye\Order;
use zovye\State;
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
use DateTime;
use DateTimeImmutable;

use function zovye\is_error;
use function zovye\settings;
use zovye\BlueToothProtocol;

use zovye\base\modelObjFinder;
use function zovye\isEmptyArray;
use zovye\Contract\bluetooth\IBlueToothProtocol;

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
    protected $s1;

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
     * @param $level
     * @param array $data
     * @return bool
     */
    public function goodsLog($level, array $data = []): bool
    {
        return $this->log($level, $this->getImei(), $data);
    }

    /**
     * @param $keywords
     * @return modelObj | device_logsModelObj
     */
    public function getGoodsLog($keywords)
    {
        return m('device_logs')->findOne("LOCATE('{$keywords}', data) > 0");
    }

    public function setProtocolV1Code($code): bool
    {
        return $this->updateSettings('extra.v1.lastcode', $code);
    }

    public function getLastOnline()
    {
        if ($this->isVDevice()) {
            return date('Y-m-d H:i:s');
        }

        return $this->settings('extra.v0.status.lastonline', $this->last_online);
    }

    public function setLastOnline($last_online): bool
    {
        return $this->updateSettings('extra.v0.status.lastonline', $last_online);
    }

    /**
     * 是不是虚拟设备
     * @return bool
     */
    public function isVDevice(): bool
    {
        return App::isVDeviceSupported() && ($this->settings('device.is_vd') || $this->getDeviceModel() == Device::VIRTUAL_DEVICE);
    }

    /**
     * 是不是蓝牙设备
     * @return bool
     */
    public function isBlueToothDevice(): bool
    {
        return $this->getDeviceModel() == Device::BLUETOOTH_DEVICE;
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
        }
        return Device::NORMAL_DEVICE;
    }

    public function getAppLastOnline()
    {
        if ($this->isVDevice()) {
            return date('Y-m-d H:i:s');
        }

        return $this->settings('extra.v0.status.applastonline', $this->app_last_online);
    }

    public function setAppLastOnline($last_online): bool
    {
        return $this->updateSettings('extra.v0.status.applastonline', $last_online);
    }

    public function getIccid()
    {
        return $this->settings('extra.v0.status.iccid', $this->iccid);
    }

    public function setIccid($iccid): bool
    {
        return $this->updateSettings('extra.v0.status.iccid', $iccid);
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

    /**
     * 主板是否带有液晶屏
     */
    public function hasMcbDisp()
    {
        return $this->settings('extra.v1.status.disp', false);
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

    public function profile(): array
    {
        $data = [
            'id' => $this->getId(),
            'imei' => $this->getImei(),
            'name' => $this->getName(),
        ];
        if ($this->isVDevice()) {
            $data['vDevice'] = true;
        } elseif ($this->isBlueToothDevice()) {
            $data['bluetooth'] = true;
            $data['buid'] = $this->getBUID();
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
        $this->remove('advsData');
        $this->remove('advs');
        $this->remove('accountsData');
        $this->remove('firstMsgStatistic');
        $this->remove('location');
        $this->remove('refresh');
        $this->remove('statsData');

        $this->set('extra', []);

        $this->resetPayload([], '设备初始化');

        $this->setGroupId(0);
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
        return sha1($last_code . $now);
    }

    /**
     * 重置多货道商品数量，负值表示减少指定数量，正值表示设置为指定数量，0值表示重围到最大数量
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
                    $reason = $reason . "({$entry['reason']})";
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

        return error(State::ERROR, '找不到指定的商品！');
    }

    /**
     * 重置设备锁
     */
    public function resetLock(): bool
    {
        if (We7::pdo_update(self::getTableName(modelObj::OP_WRITE), [OBJ_LOCKED_UID => UNLOCKED], ['id' => $this->getId()])) {
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

    public function renderTxt($file, $text)
    {
        $file_size = getimagesize($file);
        $ext = $file_size['mime'];
        if (strpos(strtolower($ext), 'jpeg') !== false || strpos(strtolower($ext), 'jpg') !== false) {
            $im = imagecreatefromjpeg($file);
        } elseif (strpos(strtolower($ext), 'png') !== false) {
            $im = imagecreatefrompng($file);
        } else {
            return;
        }

        $i_w = imagesx($im);
        $i_h = imagesy($im);

        $x_offset = ($i_w + 44 - 18 * strlen($text)) / 2;
        $n_w = $i_w + 44;
        $n_h = $i_h + 44;

        $im2 = imagecreatetruecolor($n_w, $n_h);
        $background = imagecolorallocate($im2, 255, 255, 255);
        imagefill($im2, 0, 0, $background);

        imagecopyresized($im2, $im, 22, 0, 0, 0, floor($i_w), floor($i_h), floor($i_w), floor($i_h));
        $black = imagecolorallocate($im2, 0, 0, 0);
        imagefttext($im2, 24, 0, $x_offset, floor($i_h) + 24, $black, realpath(realpath(ZOVYE_CORE_ROOT . '../static/fonts/arial.ttf')), $text);

        if (strpos(strtolower($ext), 'jpeg') !== false || strpos(strtolower($ext), 'jpg') !== false) {
            imagejpeg($im2, $file);
        } elseif (strpos(strtolower($ext), 'png') !== false) {
            imagepng($im2, $file);
        }

        imagedestroy($im);
        imagedestroy($im2);
    }

    /**
     * 创建二维码文件
     * @return mixed
     */
    public function createQrcodeFile(): bool
    {
        $url = $this->getUrl();

        $qrcode_file = Util::createQrcodeFile("device.$this->imei", $url, function ($filename) {
            $this->renderTxt($filename, $this->imei);
        });

        if (is_error($qrcode_file)) {
            return $qrcode_file;
        }

        $this->setQrcode($qrcode_file);

        if (!$this->save()) {
            return error(State::ERROR, '创建二维码文件失败！');
        }
        return true;
    }

    /**
     * 获取领货链接
     * @return string
     */
    public function getUrl(): string
    {
        $id = $this->isActiveQrcodeEnabled() ? $this->shadow_id : $this->imei;

        $params = [];
        $adv = $this->getOneAdv(Advertising::WX_APP_URL_CODE);
        if ($adv && $adv['extra']['code']) {
            $params['app'] = strval($adv['extra']['code']);
        } else {
            $params['app'] = 'NULL';
        }

        if ($this->isBlueToothDevice()) {
            $params['wxapp'] = 'true';
        }

        $params['from'] = 'device';
        $params['device'] = $id;
        return Util::murl('entry', $params, true);
    }

    public function getProtocolV1Code()
    {
        return $this->settings('extra.v1.lastcode');
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

        return $url . "{$f}v=$ts";
    }

    public function getGroup(): ?device_groupsModelObj
    {
        return Group::get($this->getGroupId());
    }

    /**
     * 给app发送通知
     * @param string $op
     * @param array $data
     * @return bool
     */
    public function appNotify(string $op = 'update', array $data = []): bool
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
        $uid = "$uid:" . time();
        $res = We7::pdo_update(self::getTableName(modelObj::OP_WRITE), [OBJ_LOCKED_UID => $uid], ['id' => $this->getId(), OBJ_LOCKED_UID => UNLOCKED]);
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
            return We7::pdo_update(self::getTableName(modelObj::OP_WRITE), [OBJ_LOCKED_UID => UNLOCKED], ['id' => $this->getId(), OBJ_LOCKED_UID => $uid]);
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
        $remainWarning = App::remainWarningNum($this->getAgent());

        if ($remainWarning > 0 && $this->remain < $remainWarning) {
            $tpl_id = settings('notice.reload_tplid');
            if ($tpl_id) {
                if ($this->isLastRemainWarningTimeout()) {
                    //使用控制中心推送通知
                    Job::devicePayloadWarning($this->getId());
                }
            }
        }
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

    //公众号推广二维码
    public function getAccountAppQRCode(): string
    {
        if (App::useAccountAppQRCode()) {
            $accounts = $this->getAccounts(Account::AUTH);
            foreach ($accounts as $account) {
                $obj = Account::get($account['id']);
                if (empty($obj) || !$obj->settings('config.appQRCode')) {
                    continue;
                }

                $res = Account::updateAuthAccountQRCode($account, [App::uid(6), 'app', $this->getId()], false);
                if (!is_error($res)) {
                    return $account['qrcode'];
                }
            }
        }

        return '';
    }

    public function getAccountQRCode(): string
    {
        //是否分配了屏幕推广的公众号
        $qrcode = $this->getAccountAppQRCode();
        if ($qrcode) {
            return $qrcode;
        }

        //是否设置了屏幕二维码的公众号
        if (App::useAccountQRCode()) {
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
        $advs = [];
        $srt = [
            'speed' => intval(settings('advs.srt.speed', 1)),
            'subs' => [],
        ];

        foreach ($this->getAdvs(Advertising::SCREEN) as $adv) {

            if ($adv['extra']['media'] == 'srt') {
                if (!empty($adv['extra']['text'])) {
                    $srt['subs'][] = strval($adv['extra']['text']);
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
                $advs[] = $data;
            }
        }

        //其它配置
        $cfg = [
            'banner' => strval(Util::toMedia($banner)),
            'volume' => intval($vol),
            'advs' => $advs,
        ];

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
     * @return array|null
     */
    public function getOneAdv($type, bool $random = false): ?array
    {
        $advs = $this->getAdvs($type);
        if (!isEmptyArray($advs)) {
            if ($random) {
                shuffle($advs);
            }
            return current($advs);
        }

        return null;
    }

    /**
     * 获取相关广告
     * @param $type
     * @param bool $ignore_cache
     * @return array
     */
    public function getAdvs($type, bool $ignore_cache = false): array
    {
        $advs = null;

        if ($ignore_cache == false) {
            if ($this->settings("advsData.type$type.version") == Advertising::version($type)) {
                $advs = $this->settings("advsData.type$type.data");
            }
        }

        if (is_null($advs)) {
            $query = Advertising::query(['type' => $type]);

            $query->orderBy('createtime DESC');

            $advs = [];

            /** @var advertisingModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $passed = !App::isAdvsReviewEnabled() || $entry->isReviewPassed();
                if ($entry->getState() == Advertising::NORMAL && $passed) {
                    $assign_data = $entry->settings('assigned');
                    if ($this->isMatched($assign_data)) {
                        $advs["U{$entry->getId()}"] = Advertising::format($entry);
                        continue;
                    }
                }

                unset($advs["U{$entry->getId()}"]);
            }

            $this->updateSettings(
                "advsData.type$type",
                [
                    'version' => Advertising::version($type),
                    'data' => $advs,
                ]
            );
        }

        return $advs;
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

            return $day_limits > 0 && $this->getDTotal(['free']) >= $day_limits;
        }

        $day_limits = settings('device.limits.day', 0);

        return $day_limits > 0 && $this->getDTotal(['free']) >= $day_limits;
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
     * 获取设备今日出货数据
     * @param array $way
     * @param string $day
     * @return int|array
     */
    public function getDTotal(array $way = [], string $day = 'today')
    {
        try {
            $v = new DateTime($day);
            $begin = $v->format('Y-m-d 00:00:00');

            $v->modify('+1 day');
            $end = $v->format('Y-m-d 00:00:00');

            $total = $this->getTotal($way, $begin, $end);

            if (empty($way) || count($way) > 1) {
                return $total;
            } else {
                return $total[$way[0]];
            }
        } catch (Exception $e) {
        }

        return 0;
    }

    public function getTotal($way, $begin, $end): array
    {
        $total = [];

        if (!is_numeric($begin)) {
            $begin = strtotime($begin);
        }

        if (!is_numeric($end)) {
            $end = strtotime($end);
        }

        if (empty($way) || in_array('free', $way)) {

            $query = Order::query(['device_id' => $this->id]);

            $query->where(
                [
                    'createtime >=' => $begin,
                    'createtime <' => $end,
                ]
            );

            $query->where(['price' => 0]);

            if (settings('user.balance.type') != 'free') {
                $query->where(['balance' => 0]);
            }

            $total['free'] = (int)$query->get('sum(num)');
        }

        if (empty($way) || in_array('pay', $way)) {

            $query = Order::query(['device_id' => $this->id]);

            $query->where(
                [
                    'createtime >=' => $begin,
                    'createtime <' => $end,
                ]
            );

            if (settings('user.balance.type') != 'free') {
                $query->where('(price > 0 OR balance > 0)');
            } else {
                $query->where(['price >' => 0]);
            }

            $total['pay'] = (int)$query->get('sum(num)');
        }

        if (empty($way) || in_array('balance', $way)) {

            $query = Order::query(['device_id' => $this->id]);

            $query->where(
                [
                    'createtime >=' => $begin,
                    'createtime <' => $end,
                ]
            );

            $query->where(['balance >' => 0]);

            $total['balance'] = (int)$query->get('sum(num)');
        }

        if (empty($way) || in_array('total', $way)) {

            $query = Order::query(['device_id' => $this->id]);
            $query->where(
                [
                    'createtime >=' => $begin,
                    'createtime <' => $end,
                ]
            );

            $total['total'] = (int)$query->get('sum(num)');
        }

        return $total;
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

            $tags[] = ['name' => Topic::encrypt('device' . $this->getId())];

            if ($this->agent_id) {
                $tags[] = ['name' => Topic::encrypt('agent' . $this->getAgentId())];
            }

            if ($this->getGroupId()) {
                $tags[] = ['name' => Topic::encrypt('group' . $this->getGroupId())];
            }
        }

        foreach ($this->getTagsAsId() as $id) {
            $tags[] = ['name' => Topic::encrypt('tag' . $id)];
        }

        return $tags;
    }

    /**
     * 通知设备更新屏幕广告
     * @return bool
     */
    public function updateScreenAdvsData(): bool
    {
        if ($this->isAdvsUpdated(Advertising::SCREEN)) {
            return $this->appNotify();
        }

        return false;
    }

    /**
     * 广告是否已经更新
     * @param $type
     * @return bool
     */
    public function isAdvsUpdated($type): bool
    {
        if ($this->settings("advsData.type$type.version") != Advertising::version($type)) {
            return true;
        }

        $cachedData = $this->settings("advsData.type$type.data", []);
        $advs = $this->getAdvs($type, true);

        if (empty($cachedData) && empty($advs)) {
            return false;
        }

        return array_keys($cachedData) != array_keys($advs);
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

        return $accounts_cached_data['lastupdate'] != $accounts['lastupdate'];
    }

    public function getAccounts($state_filter = [Account::NORMAL, Account::VIDEO, Account::AUTH]): array
    {
        $result = [];

        $state_filter = is_array($state_filter) ? $state_filter : [$state_filter];

        $accounts = $this->getAssignedAccounts();
        foreach ($accounts as $index => $account) {
            if (in_array($account['state'], $state_filter)) {
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

        $last_update = settings('accounts.lastupdate');
        if ($ignore_cache == false) {
            $accounts_data = $this->get('accountsData', []);
            if ($accounts_data && $accounts_data['lastupdate'] == $last_update) {
                return $accounts_data['data'] ?: [];
            }
        }

        $query = Account::query(['state <>' => Account::BANNED]);

        /** @var accountModelObj $entry */
        foreach ($query->findAll() as $entry) {
            if ($entry->isBanned()) {
                continue;
            }
            $assign_data = $entry->settings('assigned');
            if ($this->isMatched($assign_data)) {
                $accounts[$entry->getUid()] = $entry->format();
            }
        }

        $this->set(
            'accountsData',
            [
                'lastupdate' => $last_update,
                'data' => $accounts,
            ]
        );

        return $accounts;
    }

    public function getOnlineDetail($use_cache = true): array
    {
        if ($this->isVDevice() || $this->isBlueToothDevice()) {
            return [
                'mcb' => true,
                'app' => true,
            ];
        }
        $res = CtrlServ::v2_query("device/$this->imei/online", ['nocache' => $use_cache ? 'false' : 'true']);
        if ($res['status'] === true) {
            return $res['data'];
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
                $res = CtrlServ::v2_query("device/$this->imei/app/online", ['nocache' => false]);
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
     * @return void
     */
    public function updateRemain()
    {
        $this->updateAppRemain();

        if ($this->hasMcbDisp()) {
            $code = $this->getProtocolV1Code();
            if ($code) {
                $this->updateMcbParams($code);
            }
        }
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
    public function isCustomType(): bool
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
        foreach ($this->getAdvs(Advertising::SCREEN) as $adv) {
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

    /**
     * 获取设备本月统计数据
     * @param array $way
     * @param string $month
     * @return int|array
     */
    public function getMTotal(array $way = [], string $month = 'this month')
    {
        $ts = strtotime($month);
        if ($ts <= time()) {

            $m_label = date('Ym', $ts);
            $cache = $this->get('M_total', []);

            if ($cache && $cache[$m_label]) {
                $result = $cache[$m_label];
            } else {
                $begin = new DateTimeImmutable("first day of $month");
                $end = $begin->modify('+1 month');

                if ($m_label != date('Ym')) {
                    $result = $this->getTotal([], $begin->format('Y-m-d 00:00:00'), $end->format('Y-m-d 00:00:00'));

                    $M_total[$m_label] = $result;
                    $this->set('M_total', $M_total);
                } else {
                    $result = $this->getTotal($way, $begin->format('Y-m-d 00:00:00'), $end->format('Y-m-d 00:00:00'));
                }
            }

            if (empty($way)) {
                return $result;
            } elseif (count($way) > 1) {
                return array_filter(
                    $result,
                    function ($key) use ($way) {
                        return in_array($key, $way);
                    },
                    ARRAY_FILTER_USE_KEY
                );
            } else {
                return $result[$way[0]];
            }
        }

        return 0;
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
        return Locker::try("payload:{$this->getImei()}", $retries, $delay_seconds);
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
            return error(State::FAIL, '设备已关机！');
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

        //蓝牙设备
        if ($this->isBlueToothDevice()) {
            $protocol = $this->getBlueToothProtocol();
            if (empty($protocol)) {
                return error(State::ERROR, '未知的蓝牙协议！');
            }
            $motorNum = $this->getMotor();
            $option = $mcb_channel <= $motorNum ? ['motor' => $mcb_channel] : ['locker' => $mcb_channel - $motorNum];

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
        }

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
            $res = CtrlServ::v2_query(
                "device/$this->imei/mcb/online",
                [
                    'nocache' => $use_cache == false ? 'true' : 'false',
                ]
            );

            return $res['status'] === true && $res['data']['mcb'] === true;
        }

        return false;
    }

    public function isDown(): bool
    {
        if ($this->settings('extra.isDown')) {
            return true;
        }

        if (settings('device.errorDown')) {
            if ($this->getErrorCode()) {
                return true;
            }
        }

        return false;
    }

    /**
     * 出货操作
     * @param array $extra
     * @param int $channel
     * @param int $num
     * @param int $timeout
     * @return array
     */
    public function open(int $channel = Device::CHANNEL_DEFAULT, int $num = 1, int $timeout = DEFAULT_DEVICE_WAIT_TIMEOUT, array $extra = []): array
    {
        $no_str = Util::random(16, true);
        $order_no = 'P' . We7::uniacid() . "NO$no_str";

        $content = http_build_query(
            [
                'deviceGUID' => $this->imei,
                'src' => json_encode($extra),
                'channel' => $channel,
                'timeout' => $timeout,
                'num' => $num,
            ]
        );

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

        $advs = $this->getAdvs(Advertising::REDIRECT_URL);
        if ($advs && is_array($advs)) {
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

        return ['url' => PlaceHolder::url($url, [$this]), 'delay' => intval($delay)];
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
    protected function getSettingsKeyNew($key, $classname = ''): string
    {
        return parent::getSettingsKeyNew($key, deviceModelObj::class);
    }

    protected function getSettingsKey($key): string
    {
        $classname = str_replace('zovye\model', 'lltjs', deviceModelObj::class);
        return "$classname:{$this->getId()}:$key";
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
        //$statistic[$month][$day]['rec'][] = TIMESTAMP;

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

    public function getGoodsList(userModelObj $user = null, $params = []): array
    {
        $result = [];

        $payload = $this->getPayload();

        if ($payload && $payload['cargo_lanes']) {
            foreach ($payload['cargo_lanes'] as $entry) {
                $goods_data = Goods::data($entry['goods'], ['useImageProxy' => true]);
                if (empty($goods_data)) {
                    continue;
                }

                if ($params) {
                    if ((!empty($params['allowPay']) || in_array('allowPay', $params)) && empty($goods_data['allowPay'])) {
                        continue;
                    }
                    if ((!empty($params['allowFree']) || in_array('allowFree', $params)) && empty($goods_data['allowFree'])) {
                        continue;
                    }
                }

                $goods_data['num'] = $entry['num'];
                if ($this->isCustomType() && isset($entry['goods_price'])) {
                    $goods_data['price'] = $entry['goods_price'];
                }

                $key = "goods{$goods_data['id']}";
                if ($result['goods'][$key]) {
                    $result['goods'][$key]['num'] += intval($goods_data['num']);
                    //如果相同商品设置了不同价格，则使用更高的价格
                    if ($result['goods'][$key]['price'] < $goods_data['price']) {
                        $result['goods'][$key]['price'] = $goods_data['price'];
                        $result['goods'][$key]['price_formatted'] = '￥' . number_format($goods_data['price'] / 100, 2) . '元';
                    }
                } else {
                    $result['goods'][$key] = [
                        'id' => $goods_data['id'],
                        'name' => $goods_data['name'],
                        'img' => $goods_data['img'],
                        'detail_img' => $goods_data['detailImg'],
                        'price' => $goods_data['price'],
                        'price_formatted' => '￥' . number_format($goods_data['price'] / 100, 2) . '元',
                        'num' => intval($goods_data['num']),
                        'allowFree' => $goods_data['allowFree'],
                        'allowPay' => $goods_data['allowPay'],
                    ];

                    if (!empty($user)) {
                        $discount = User::getUserDiscount($user, $goods_data);
                        $result['goods'][$key]['discount'] = $discount;
                        $result['goods'][$key]['discount_formatted'] = '￥' . number_format($discount / 100, 2) . '元';
                    }
                }
            }

            $result = array_values((array)$result['goods']);
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
            $result = $entry->getData('result');
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
}
