<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use DateTimeImmutable;
use Exception;
use zovye\model\userModelObj;
use zovye\base\modelObjFinder;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;
use zovye\model\keeperModelObj;
use zovye\Contract\bluetooth\ICmd;
use zovye\Contract\bluetooth\IResponse;
use zovye\Contract\bluetooth\IBlueToothProtocol;

class Device extends State
{
    const VIRTUAL_DEVICE = 'vd';
    const BLUETOOTH_DEVICE = 'bluetooth';
    const NORMAL_DEVICE = 'normal';
    const CHARGING_DEVICE = 'charging';
    const FUELING_DEVICE = 'fueling';

    const BLUETOOTH_CONNECTED = 1;
    const BLUETOOTH_READY = 2;

    const ERROR_LOW_BATTERY = -10;

    protected static $title = [
        self::OK => '成功',
        self::FAIL => '失败',
        self::ERROR => '错误',
        self::ERROR_LOW_BATTERY => '电量过低',
    ];

    const ONLINE = 1;
    const OFFLINE = 0;

    const DEFAULT_CARGO_LANE = 0;
    const CHANNEL_INVALID = 0;
    const CHANNEL_DEFAULT = 1;

    const STATUS_NORMAL = 0;
    const STATUS_MAINTENANCE = 1;

    const V0_STATUS_QOE = 'qoe';
    const V0_STATUS_SIG = 'sig';
    const V0_STATUS_VOLTAGE = 'voltage';
    const V0_STATUS_COUNT = 'count';
    const V0_STATUS_ERROR = 'error';

    const DUMMY_DEVICE_PREFIX = 'B#';

    const SENSOR_WATER_LEVEL = 'waterLevel';
    const SENSOR_INFRARED = 'infrared';

    private static $cache = [];

    public static function objClassname(): string
    {
        return m('device')->objClassname();
    }

    public static function getTableName(): string
    {
        return m('device')->getTableName();
    }

    /**
     * @param mixed $keeper
     * @param int $kind
     * @return modelObjFinder
     */
    public static function keeper($keeper, int $kind = -1): modelObjFinder
    {
        if ($keeper instanceof keeperModelObj) {
            $keeper_id = $keeper->getId();
        } else {
            $keeper_id = intval($keeper);
        }
        $query = m('device_keeper_vw')->where(We7::uniacid(['keeper_id' => $keeper_id]));
        if ($kind >= 0) {
            $query->where(['kind' => $kind]);
        }

        return $query;
    }

    /**
     * @param mixed $condition
     * @return modelObjFinder
     */
    public static function query($condition = []): modelObjFinder
    {
        if (isset($condition['keeper_id'])) {
            return m('device_keeper_vw')->where(We7::uniacid([]))->where($condition);
        }

        return m('device')->where(We7::uniacid([]))->where($condition);
    }

    /**
     * 通过http调用，获取指定设备的配置
     * @param deviceModelObj $device
     * @return array
     */
    public static function getAppConfigData(deviceModelObj $device): array
    {
        $app_id = strval($device->getAppId());
        if (empty($app_id)) {
            return err('设备没有绑定appId!');
        }

        $data = DeviceEventProcessor::onAppConfigMsg([
            'id' => $app_id,
        ], true);

        return ['status' => true, 'data' => $data];
    }

    public static function cleanAllErrorCode(): bool
    {
        $tb_name = We7::tablename(m('device')->getTableName());
        $res = We7::pdo_query(
            'update '.$tb_name.' SET error_code=0 WHERE uniacid=:uniacid',
            [':uniacid' => We7::uniacid()]
        );

        return !is_error($res) && $res !== false;
    }

    public static function removeDeviceType($type_id): bool
    {
        $tb_name = m('device')->getTableName();
        $res = We7::pdo_query(
            'UPDATE '.We7::tablename($tb_name).' SET `device_type`=:unknown WHERE `device_type`=:type',
            [
                ':type' => $type_id,
                ':unknown' => DeviceTypes::UNKNOWN_TYPE,
            ]
        );

        return !is_error($res) && $res !== false;
    }

    /**
     * 返回指定货道的电机通道id, 即channel id
     * @param deviceModelObj $device
     * @param int $lane 货道ID，0开始
     * @return int
     */
    public static function cargoLane2Channel(deviceModelObj $device, int $lane): int
    {
        unset($device);

        return $lane + 1;
    }

    /**
     * 重置多货道商品数量
     * @开头表示增加指定数量，正值表示增加指定数量，负值表示减少指定数量，0值表示重置到最大数量
     * 空数组则重置所有货道商品数量到最大值
     * 返回商品改变的数量
     * @param deviceModelObj $device
     * @param array $data
     * @return array
     */
    public static function resetPayload(deviceModelObj $device, array $data = []): array
    {
        $result = [];

        $device_type = DeviceTypes::from($device);
        if ($device_type) {
            $cargo_lanes = $device_type->getCargoLanes();
            if (empty($data)) {
                foreach ($cargo_lanes as $index => $lane) {
                    $data[$index] = 0;
                }
            } elseif (isset($data['*'])) {
                $v = $data['*'];
                $data = [];
                foreach ($cargo_lanes as $index => $lane) {
                    $data[$index] = $v;
                }
            }

            $lanes_data = $device->getCargoLanes();
            $lowest = null;

            foreach ($data as $index => $lane) {
                if (isset($cargo_lanes[$index])) {
                    $lane_id = "l$index";
                    $old = $lanes_data[$lane_id]['num'];

                    if (is_array($lane)) {
                        $num = $lane['num'];
                        if (isset($lane['price'])) {
                            $lanes_data[$lane_id]['price'] = $lane['price'];
                        }
                    } else {
                        $num = $lane;
                    }

                    $max_capacity = intval($cargo_lanes[$index]['capacity']);

                    if (We7::starts_with($num, '@')) {
                        $lanes_data[$lane_id]['num'] = max(0, intval(ltrim($num, '@')));
                    } else {
                        if ($num == 0) {
                            $lanes_data[$lane_id]['num'] = $max_capacity;
                        } else {
                            $lanes_data[$lane_id]['num'] = max(0, $old + intval($num));
                        }
                    }

                    //不能超过最大容量
                    $lanes_data[$lane_id]['num'] = min($max_capacity, $lanes_data[$lane_id]['num']);

                    if (is_null($lowest) || $lanes_data[$lane_id]['num'] < $lowest) {
                        $lowest = $lanes_data[$lane_id]['num'];
                    }

                    //统计商品补货数量
                    $goods = $cargo_lanes[$index]['goods'];
                    $changed = $lanes_data[$lane_id]['num'] - $old;
                    if ($changed != 0) {
                        $result[$goods] = [
                            'goodsId' => $goods,
                            'org' => intval($result[$goods]['org']) + $old,
                            'num' => intval($result[$goods]['num']) + $changed,
                        ];
                    }
                }
            }

            //把remain设备为货道商品最少的数量
            $device->setRemain(intval($lowest));
            $device->setCargoLanes($lanes_data);
        } else {
            Log::error("resetPayload", [
                'error' => '货道数据错误！',
                'data' => $data,
            ]);
        }

        return array_values($result);
    }

    /**
     * 获取指定货道上的商品数据，同时包括商品所在货道
     * @param deviceModelObj $device
     * @param int $goods_id
     * @return array
     */
    public static function getGoods(deviceModelObj $device, int $goods_id): array
    {
        $result = Goods::data($goods_id);
        if (empty($result)) {
            return [];
        }

        $result['num'] = 0;
        if (App::shipmentBalance($device)) {
            $match_fn = function ($lane) use ($goods_id, &$result) {
                return $lane['goods'] == $goods_id && $lane['num'] > 0 && $lane['num'] > $result['num'];
            };
        } else {
            $match_fn = function ($lane) use ($goods_id, &$result) {
                if ($lane['goods'] == $goods_id && $lane['num'] > 0) {
                    if (empty($result['num'])) {
                        return true;
                    }

                    return $lane['num'] < $result['num'];
                }

                return false;
            };
        }

        $payload = self::getPayload($device);

        $total = 0;
        foreach ($payload['cargo_lanes'] as $index => $lane) {
            //统计商品总库存
            if ($lane['goods'] == $goods_id) {
                $total += $lane['num'];
            }

            //根据出货策略匹配货道
            if ($match_fn($lane)) {
                $result['num'] = $lane['num'];
                $result['cargo_lane'] = $index;
                if ($device->isCustomizedType() && isset($lane['goods_price'])) {
                    $result['price'] = $lane['goods_price'];
                    $result['price_formatted'] = '¥'.number_format($result['price'] / 100, 2).'元';
                }
            }
        }

        $result['num'] = $total;

        return $result;
    }

    /**
     * 获取设备的当前商品的库存信息
     * @param deviceModelObj $device
     * @param bool $detail
     * @return array
     */
    public static function getPayload(deviceModelObj $device, bool $detail = false): array
    {
        $data = [];

        $device_type = DeviceTypes::from($device);
        if (empty($device_type)) {
            return $data;
        }

        $res = DeviceTypes::format($device_type, $detail);
        if ($res && is_array($res['cargo_lanes'])) {
            $data['cargo_lanes'] = $res['cargo_lanes'];

            $lanes_data = $device->getCargoLanes();

            foreach ($data['cargo_lanes'] as $index => &$lane) {
                $laneId = "l$index";
                if (!empty($lanes_data[$laneId])) {
                    $lane['num'] = intval($lanes_data[$laneId]['num']);
                    if ($device->isCustomizedType() && isset($lanes_data[$laneId]['price'])) {
                        $lane['goods_price'] = intval(round($lanes_data[$laneId]['price']));
                        $lane['goods_price_formatted'] = '¥'.number_format($lane['goods_price'] / 100, 2).'元';
                    }
                }
                if ($device->isBlueToothDevice()) {
                    $lane['is_motor'] = $device->getMotor() > $index;
                }
            }
        }

        return $data;
    }

    public static function getGoodsByLane(deviceModelObj $device, $lane_id): array
    {
        $result = [];

        $payload = self::getPayload($device);
        $lane = $payload['cargo_lanes'][$lane_id];

        if (!empty($lane)) {
            $goods_id = $lane['goods'];
            $result = Goods::data($goods_id);
            if ($result) {
                $result['num'] = $lane['num'];
                if ($lane['goods_price']) {
                    $result['price'] = $lane['goods_price'];
                    $result['price_formatted'] = $lane['goods_price_formatted'];
                }
            }
        }

        return $result;
    }

    /**
     * 创建新设备.
     *
     * @param array $params
     *
     * @return deviceModelObj|null
     */
    public static function createNewDevice(array $params = []): ?deviceModelObj
    {
        if (App::isDeviceAutoJoin()) {
            if (isset($params['IMEI'])) {
                $imei = $params['IMEI'];
            } elseif (isset($params['uid'])) {
                $imei = $params['uid'];
            }

            if (!empty($imei)) {
                $data = [
                    'name' => $imei,
                    'imei' => $imei,
                    'remain' => DEFAULT_DEVICE_CAPACITY,
                ];

                $defaultType = App::getDefaultDeviceType();
                if ($defaultType) {
                    $data['device_type'] = $defaultType->getId();
                }

                $device = Device::create($data);
                if ($device) {
                    $device->setCapacity(DEFAULT_DEVICE_CAPACITY);
                    $device->updateQrcode(true);

                    $extra = [];

                    if (App::isDeviceWithDoorEnabled()) {
                        $extra['door'] = [
                            'num' => 1,
                        ];
                    }

                    $device->set('extra', $extra);

                    $data['params'] = $params;
                    $data['result'] = '设备已自动加入！';

                    Log::info('device', $data);

                    return $device;
                }
            }
        }

        return null;
    }

    /**
     * @param deviceModelObj $device
     */
    public static function cache(deviceModelObj $device)
    {
        self::$cache[$device->getImei()] = $device;
        self::$cache[$device->getId()] = $device;

        $app_id = $device->getAppId();
        if (!empty($app_id)) {
            self::$cache[$app_id] = $device;
        }
    }

    public static function getFromCache($id)
    {
        return self::$cache[$id];
    }

    public static function cacheExists($id): bool
    {
        return isset(self::$cache[$id]);
    }

    public static function isDummyDeviceIMEI($imei): bool
    {
        return boolval(preg_match('/^'.Device::DUMMY_DEVICE_PREFIX.'/', $imei));
    }

    /**
     * @param mixed $id
     * @param bool $is_imei
     *
     * @return deviceModelObj|null
     */
    public static function get($id, bool $is_imei = false): ?deviceModelObj
    {
        if ($id) {
            if (self::cacheExists($id)) {
                return self::getFromCache($id);
            }
            if ($is_imei) {
                $imei = strval($id);
                if (self::isDummyDeviceIMEI($imei)) {
                    return self::getDummyDevice($imei);
                }
                $device = self::findOne(['imei' => $imei]);
            } else {
                $device = self::findOne(['id' => intval($id)]);
            }
            if ($device) {
                self::cache($device);

                return $device;
            }
        }

        return null;
    }

    /**
     * 根据AppId查找设备.
     *
     * @param mixed $id
     *
     * @return ?deviceModelObj
     */
    public static function getFromAppId($id): ?deviceModelObj
    {
        if ($id) {
            if (self::cacheExists($id)) {
                return self::getFromCache($id);
            }
            $device = self::findOne(['app_id' => strval($id)]);
            if ($device) {
                self::cache($device);

                return $device;
            }
        }

        return null;
    }

    /**
     * @param array $data
     * @return deviceModelObj|null
     */
    public static function create(array $data = []): ?deviceModelObj
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        return m('device')->create($data);
    }

    /**
     * 根据指定条件查找设备，可以传入id,imei或者影子ID
     * @param $cond
     * @param null $hints
     * @return deviceModelObj|null
     * @deprecated
     */
    public static function find($cond, $hints = null): ?deviceModelObj
    {
        return Util::findObject('device', $cond, $hints);
    }

    public static function findOne($cond = []): ?deviceModelObj
    {
        return self::query($cond)->findOne();
    }

    public static function getDummyDevice($imei = ''): deviceModelObj
    {
        $deviceClassname = m('device')->objClassname();

        $device = new $deviceClassname(0, m('device'));
        if (empty($imei)) {
            $imei = self::DUMMY_DEVICE_PREFIX.Util::random(16, true);
        }
        $device->setName('');
        $device->setImei($imei);
        return $device;
    }

    public static function createBluetoothCmdLog(deviceModelObj $device, ICmd $cmd)
    {
        if ($device->isEventLogEnabled()) {
            $data = $cmd->getData();
            if ($data) {
                $str = is_string($data) ? $data : json_encode($data);
                $data = We7::uniacid([
                    'event' => $cmd->getID(),
                    'device_uid' => $device->getUid(),
                    'extra' => json_encode([
                        'id' => $cmd->getId(),
                        'data' => base64_encode($str),
                        'message' => $cmd->getMessage(),
                        'raw' => $cmd->getEncoded(IBlueToothProtocol::HEX),
                    ]),
                ]);

                if (!m('device_events')->create($data)) {
                    Log::error('events', [
                        'error' => 'create device log failed',
                        'data' => $data,
                    ]);
                }
            }
        }
    }

    public static function createBluetoothEventLog(deviceModelObj $device, IResponse $result)
    {
        if ($device->isEventLogEnabled()) {
            $result_data = $result->getEncodeData();
            if ($result_data) {
                $data = We7::uniacid([
                    'event' => $result->getID(),
                    'device_uid' => $device->getUid(),
                    'extra' => [
                        'raw' => $result_data,
                        'code' => $result->getErrorCode(),
                        'message' => $result->getMessage(),
                    ],
                ]);

                $serial = $result->getSerial();
                if ($serial) {
                    $data['extra']['serial'] = $serial;
                }

                $data['extra'] = json_encode($data['extra']);

                if (!m('device_events')->create($data)) {
                    Log::error('events', [
                        'error' => 'create device log failed',
                        'data' => $data,
                    ]);
                }
            }
        }
    }

    /**
     * 刷新设备状态
     * @param deviceModelObj $device
     * @return bool
     */
    public static function refresh(deviceModelObj $device): bool
    {
        $device->remove('fakeQrcodeData');
        $device->remove('accountsData');
        $device->remove('lastErrorData');
        $device->remove('lastErrorNotify');
        $device->remove('lastRemainWarning');
        $device->remove('fakeQrcodeData');
        $device->remove('adsData');

        //绑定appId
        $device->updateAppId();

        $device->resetLock();
        $device->resetShadowId();

        $device->set('refresh', time());
        $device->appNotify('update');

        $code = $device->getProtocolV1Code();
        if ($code) {
            $device->reportMcbStatus($code);
        }

        return $device->save();
    }

    /**
     * 恢复设备设置到默认状态
     * @param deviceModelObj $device
     * @param string $reason
     * @return bool
     */
    public static function reset(deviceModelObj $device, string $reason = '设备重置'): bool
    {
        //清空运营人员
        $extra = $device->get('extra', []);
        unset($extra['keepers']);
        $device->set('extra', $extra);

        $locker = $device->payloadLockAcquire();
        if (empty($locker)) {
            return false;
        }

        $res = $device->resetPayload(['*' => '@0'], $reason);
        if (is_error($res)) {
            return false;
        }

        $locker->unlock();

        //设备分组
        if (!$device->isChargingDevice()) {
            $device->setGroupId(0);
        }

        //设备标签
        $device->setTagsFromText('');

        $device->remove('statsData');
        $device->remove('assigned');
        $device->remove('weight');
        $device->remove('last');
        $device->remove('zjbao');
        $device->remove('wx9se');

        //设备类型
        $defaultDeviceType = App::getDefaultDeviceType();
        if ($defaultDeviceType) {
            $device->setDeviceType($defaultDeviceType->getId());
        } else {
            $device->setDeviceType(DeviceTypes::UNKNOWN_TYPE);
        }

        //删除关联的运营人员
        foreach ($device->getKeepers() as $keeper) {
            if (!$device->removeKeeper($keeper)) {
                return false;
            }
        }

        return self::refresh($device);
    }

    /**
     * 解除设备与当前代理商的绑定关系
     * @param deviceModelObj $device
     * @return bool
     */
    public static function unbind(deviceModelObj $device): bool
    {
        return self::bind($device);
    }

    /**
     * 绑定、解绑设备
     * @param deviceModelObj $device
     * @param agentModelObj|null $agent
     * @return bool
     */
    public static function bind(deviceModelObj $device, agentModelObj $agent = null): bool
    {
        if ($agent) {
            $device->setAgent($agent);
        } else {
            //解绑设备，根据系统设置决定设备归属
            if (empty(settings('agent.device.unbind'))) {
                $original = $device->getAgent();
                if ($original) {
                    //如果用户上级也是代理商，则设备代理商设置为上级代理商，否则设置为平台（即代理商为null)
                    $superior = $original->getSuperior();
                    if ($superior && $superior->isAgent()) {
                        $device->setAgent($superior);
                    } else {
                        $device->setAgent();
                    }
                }
            } else {
                $device->setAgent();
            }
        }

        if (self::reset($device, $agent ? '绑定设备' : '解绑设备')) {
            $device->appNotify('update');

            return true;
        }

        return false;
    }

    /**
     * 判断设备是不是属于该用或者用户的下级
     * @param userModelObj $user
     * @param deviceModelObj $device
     * @return bool
     */
    public static function isOwner(deviceModelObj $device, userModelObj $user): bool
    {
        if (empty($user) || empty($device)) {
            return false;
        }

        $owner = $device->getAgent();
        $superior_ids = [];

        while (!empty($owner)) {
            if ($user->getId() == $owner->getId()) {
                return true;
            }

            $superior_ids[$owner->getId()] = true;
            $owner = $owner->getSuperior();

            //循环关系检测
            if ($owner && $superior_ids[$owner->getId()]) {
                return false;
            }
        }

        return false;
    }

    public static function formatPullTitle($type): string
    {
        static $titles = [
            LOG_GOODS_TEST => '测试',
            LOG_GOODS_PAY => '支付',
            LOG_GOODS_CB => '回调',
            LOG_GOODS_FREE => '免费',
            LOG_GOODS_VOUCHER => '取货',
            LOG_GOODS_ADV => '广告',
            LOG_GOODS_RETRY => '重试',
            LOG_GOODS_BALANCE => '积分',
        ];
        if (isset($titles[$type])) {
            return $titles[$type];
        }

        return '未知';
    }

    public static function findByMoscaleKey($key): ?deviceModelObj
    {
        return self::findOne(['shadow_id' => $key]);
    }

    public static function search(): array
    {
        try {
            $query = Device::query();

            //指定代理商
            if (Request::isset('agent_id')) {
                $agent_id = Request::int('agent_id');
                if ($agent_id == 0) {
                    $query->where(['agent_id' => 0]);
                } else {
                    $agent = Agent::get($agent_id);
                    if (empty($agent)) {
                        throw new Exception('找不到这个代理商！');
                    }
                    $query->where(['agent_id' => $agent->getId()]);
                }
            }

            //分组
            if (Request::isset('group_id')) {
                $group_id = Request::int('group_id');
                $query->where(['group_id' => $group_id]);
            }

            //型号
            if (Request::isset('device_type')) {
                $device_type_id = Request::int('device_type');
                if ($device_type_id == 0) {
                    $query->where(['device_type' => 0]);
                } else {
                    $device_type = DeviceTypes::get($device_type_id);
                    if (empty($device_type)) {
                        throw new Exception('找不到这个型号！');

                    }
                    $query->where(['device_type' => $device_type->getId()]);
                }
            }

            //标签
            $tag_ids = [];
            if (Request::has('tag_ids')) {
                $tag_ids = Request::array('tag_ids');
            }
            if (Request::has('tag_id')) {
                $tag_ids[] = Request::int('tag_id');
            }

            $tag_ids = array_unique($tag_ids);
            if ($tag_ids) {
                $tags_query = m('tags')->where(['id' => $tag_ids]);
                foreach ($tags_query->findAll() as $tag) {
                    $query->where("tags_data REGEXP '<{$tag->getId()}>'");
                }
            }

            //关键字
            $keywords = Request::trim('keywords');
            if (!empty($keywords)) {
                $query->whereOr([
                    'name LIKE' => "%$keywords%",
                    'imei LIKE' => "%$keywords%",
                    'app_id LIKE' => "%$keywords%",
                    'iccid LIKE' => "%$keywords%",
                ]);
            }

            //只显示有问题设备
            if (Request::bool('error')) {
                $query->where(['error_code <>' => 0]);
            }

            //缺货设备
            if (Request::bool('low')) {
                $remain_warning = intval(settings('device.remainWarning', 1));
                $query->where(['remain <' => $remain_warning]);
            }

            //位置已变化
            if (Request::bool('lac')) {
                $query->where(['s1' => 1]);
            }

            $now = new DateTimeImmutable();

            if (Request::isset('online')) {
                //在线状态
                if (Request::bool('online')) {
                    $query->where(['mcb_online' => true]);
                } else {
                    $query->where(['mcb_online' => false]);
                }
            }

            //长时间不在线
            if (Request::bool('lost')) {
                $offset = intval(settings('device.lost', 1));
                $offset_time = $now->modify("-$offset days");
                $query->where(['last_online <' => $offset_time->getTimestamp()]);
            }

            //长时间不出货
            if (Request::bool('no_order')) {
                $offset = intval(settings('device.issuing', 1));
                $offset_time = $now->modify("-$offset days");
                $query->where(['last_order <' => $offset_time->getTimestamp()]);
            }

            //维护状态
            if (Request::bool('maintenance')) {
                $query->where(['status' => Device::STATUS_MAINTENANCE]);
            }

            //App未绑定
            if (Request::bool('unbind')) {
                $query->where("(app_id IS NULL OR app_id='')");
            }

            //指定设备id获取设备列表
            if (Request::has('ids')) {
                $ids = Request::array('ids', []);
                $query->where(['id' => $ids]);
            }

            $page = max(1, Request::int('page'));
            $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

            $total = $query->count();
            $total_page = ceil($total / $page_size);

            $devices = [
                'total' => $total,
                'page' => $page,
                'totalpage' => $total_page,
                'list' => [],
            ];

            $query->page($page, $page_size);

            $sort_by = Request::str('by', 'id');
            $sort_dir = Request::str('dir', 'desc');
            if ($sort_by && $sort_dir) {
                $query->orderBy("$sort_by $sort_dir");
            }

            /** @var deviceModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $data = [
                    'id' => $entry->getId(),
                    'name' => strval($entry->getName()),
                    'IMEI' => strval($entry->getImei()),
                    'appId' => strval($entry->getAppId()),
                    'qrcode' => $entry->getQrcode(),
                    'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                ];
                $res = $entry->getAgent();
                if ($res) {
                    $data['agent'] = $res->profile();
                }

                if (App::isVDeviceSupported()) {
                    $data['isVD'] = $entry->isVDevice();
                }

                if (App::isBluetoothDeviceSupported()) {
                    if ($entry->isBlueToothDevice()) {
                        $data['isBluetooth'] = true;
                    }
                }
                if (App::isChargingDeviceEnabled()) {
                    if ($entry->isChargingDevice()) {
                        $data['isCharging'] = true;
                    }
                }
                $devices['list'][] = $data;
            }

            return $devices;
        } catch (Exception $e) {
            return err($e->getMessage());
        }
    }
}
