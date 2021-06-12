<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
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
use zovye\Contract\bluetooth\IResult;
use zovye\Contract\bluetooth\IBlueToothProtocol;

class Device extends State
{
    const WX_APP_ENTRY_PAGE = 'pages/bigcms/customer/index/index';

    const VIRTUAL_DEVICE = 'vd';
    const BLUETOOTH_DEVICE = 'bluetooth';
    const NORMAL_DEVICE = 'normal';

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
    public static function keeper($keeper, $kind = -1): modelObjFinder
    {
        if ($keeper instanceof keeperModelObj) {
            $keeper_id = $keeper->getId();
        } else {
            $keeper_id = intval($keeper);
        }
        $query = m('device_keeper_vw')->where(We7::uniacid([]))->where(['keeper_id' => intval($keeper_id)]);
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

    public static function query_view($condition = []): modelObjFinder
    {
        return m('device_view')->where(We7::uniacid([]))->where($condition);
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
            return error(State::ERROR, '设备没有绑定appId!');
        }

        $data = DeviceEventProcessor::onAppConfigMsg([
            'id' => $app_id,
        ], true);

        return ['status' => true, 'data' => $data];
    }

    public static function cleanAllErrorCode(): bool
    {
        $tb_name = We7::tablename(m('device')->getTableName());
        $res = We7::pdo_query('update ' . $tb_name . ' SET error_code=0 WHERE uniacid=:uniacid', [':uniacid' => We7::uniacid()]);
        return !is_error($res) && $res !== false;
    }

    public static function removeDeviceType($type_id): bool
    {
        $tb_name = m('device')->getTableName();
        $res = We7::pdo_query(
            'UPDATE ' . We7::tablename($tb_name) . ' SET `device_type`=:unknown WHERE `device_type`=:type',
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

        return intval($lane) + 1;
    }

    /**
     * 重置多货道商品数量
     * -号开头的负值表示减少指定数量，+开头表示增加指定数量，正值表示设置为指定数量，0值表示重置到最大数量
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
            }

            $lanes_data = $device->getCargoLanes();
            $lowest = null;

            foreach ($data as $lane => $entry) {
                if (isset($cargo_lanes[$lane])) {
                    $lane_id = "l{$lane}";
                    $old = $lanes_data[$lane_id]['num'];

                    if (is_array($entry)) {
                        $num = $entry['num'];
                        if (isset($entry['price'])) {
                            $lanes_data[$lane_id]['price'] = $entry['price'];
                        }
                    } else {
                        $num = $entry;
                    }

                    if (We7::starts_with($num, '+')) {
                        $lanes_data[$lane_id]['num'] = max(0, $old + intval($num));
                    } elseif (We7::starts_with($num, '@')) {
                        $lanes_data[$lane_id]['num'] = max(0, intval(ltrim($num, '@')));
                    } else {
                        if ($num > 0) {
                            $lanes_data[$lane_id]['num'] = intval($num);
                        } elseif ($num == 0) {
                            $lanes_data[$lane_id]['num'] = intval($cargo_lanes[$lane]['capacity']);
                        } elseif ($num < 0) {
                            $lanes_data[$lane_id]['num'] = max(0, $old + $num);
                        }
                    }

                    if (is_null($lowest) || $lanes_data[$lane_id]['num'] < $lowest) {
                        $lowest = $lanes_data[$lane_id]['num'];
                    }

                    //统计商品补货数量
                    $goods = $cargo_lanes[$lane]['goods'];
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
            Util::logToFile("resetPayload", [
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
                if ($device->getDeviceType() == 0 && isset($lane['goods_price'])) {
                    $result['price'] = $lane['goods_price'];
                    $result['price_formatted'] = '¥' . number_format($result['price'] / 100, 2) . '元';
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
    public static function getPayload(deviceModelObj $device, $detail = false): array
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
                $laneId = "l{$index}";
                if (!empty($lanes_data[$laneId])) {
                    $lane['num'] = intval($lanes_data[$laneId]['num']);
                    if (isset($lanes_data[$laneId]['price'])) {
                        $lane['goods_price'] =  $lanes_data[$laneId]['price'];
                        $lane['goods_price_formatted'] = '¥' . number_format($lane['goods_price'] / 100, 2) . '元';
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

        if (!empty($payload['cargo_lanes'][$lane_id])) {
            $goods_id = $payload['cargo_lanes'][$lane_id]['goods'];
            $result = Goods::data($goods_id);
            $result['num'] = $payload['cargo_lanes'][$lane_id]['num'];
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
    public static function createNewDevice($params = []): ?deviceModelObj
    {
        if (App::deviceAutoJoin()) {
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

                    $data['params'] = $params;
                    $data['result'] = '设备已自动加入！';

                    Util::logToFile('device', $data);

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

    /**
     * @param mixed $id
     * @param bool $is_imei
     *
     * @return deviceModelObj|null
     */
    public static function get($id, $is_imei = false): ?deviceModelObj
    {
        if ($id) {
            if (self::cacheExists($id)) {
                return self::getFromCache($id);
            }
            if ($is_imei) {
                $device = self::findOne(['imei' => strval($id)]);
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
     */
    public static function find($cond, $hints = null): ?deviceModelObj
    {
        return Util::findObject('device', $cond, $hints);
    }

    public static function findOne($cond): ?deviceModelObj
    {
        return self::query()->findOne($cond);
    }

    public static function createBluetoothCmdLog(deviceModelObj $device, ICmd $cmd)
    {
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
                Util::logToFile('events', [
                    'error' => 'create device log failed',
                    'data' => $data,
                ]);
            }
        }
    }

    public static function createBluetoothEventLog(deviceModelObj $device, IResult $result)
    {
        if ($result->getRawData()) {
            $data = We7::uniacid([
                'event' => $result->getCode(),
                'device_uid' => $device->getUid(),
                'extra' => json_encode([
                    'raw' => base64_encode($result->getRawData()),
                    'code' => $result->getCode(),
                    'message' => $result->getMessage(),
                    'serial' => $result->getSerial(),
                ]),
            ]);

            if (!m('device_events')->create($data)) {
                Util::logToFile('events', [
                    'error' => 'create device log failed',
                    'data' => $data,
                ]);
            }
        }
    }

    /**
     * 刷新设备状态
     * @param deviceModelObj $device
     * @param bool $notify
     * @return bool
     */
    public static function refresh(deviceModelObj $device, bool $notify = true): bool
    {
        $device->remove('fakeQrcodeData');
        $device->remove('advsData');
        $device->remove('accountsData');
        $device->remove('lastErrorData');
        $device->remove('lastErrorNotify');
        $device->remove('lastRemainWarning');
        $device->remove('fakeQrcodeData');
        $device->remove('assigned');
        $device->remove('advsData');
        $device->remove('advs');
        $device->remove('statsData');

        //绑定appId
        $device->updateAppId();

        $device->resetLock();
        $device->resetShadowId();

        $device->setGroupId(0);
        $device->setTagsFromText('');

        $device->set('refresh', time());
        if ($notify) {
            $device->appNotify('init');
        } else {
            $device->updateQrcode(true);
        }

        $code = $device->getProtocolV1Code();
        if ($code) {
            $device->reportMcbStatus($code);
        }

        return $device->save();
    }

    /**
     * 恢复设备设置到默认状态
     * @param deviceModelObj $device
     * @return bool
     */
    public static function reset(deviceModelObj $device): bool
    {
        //清空营运人员
        $extra = $device->get('extra', []);
        unset($extra['keepers']);
        $device->set('extra', $extra);

        $device->resetPayload([], '设备重置');

        //设备分组
        $device->setGroupId(0);

        //设备类型
        $device->setDeviceType(DeviceTypes::UNKNOWN_TYPE);

        //删除关联的营运人员
        foreach ($device->getKeepers() as $keeper) {
            $device->removeKeeper($keeper);
        }

        return self::refresh($device);
    }

    /**
     * 解除设备与当前代理商的绑定关系
     * @param deviceModelObj $device
     * @return array|bool
     */
    public static function unbind(deviceModelObj $device)
    {
        return self::bind($device);
    }

    /**
     * 绑定、解绑设备
     * @param deviceModelObj $device
     * @param agentModelObj|null $agent
     * @return array|bool
     */
    public static function bind(deviceModelObj $device, agentModelObj $agent = null)
    {
        if ($agent) {
            $device->setAgent($agent);
        } else {
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
        }

        return self::reset($device);
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
            LOG_GOODS_GETX => '领取',
            LOG_GOODS_VOUCHER => '取货',
            LOG_GOODS_ADVS => '广告',
            LOG_GOODS_RETRY => '重试',
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

    public static function search() {
        try {
            $query = Device::query();

            //指定代理商
            if (request::isset('agent_id')) {
                $agent_id = request::int('agent_id');
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
            if (request::isset('group_id')) {
                $group_id = request::int('group_id');
                if ($group_id == 0) {
                    $query->where(['group_id' => $group_id]);
                } else {
                    $group = Group::get($group_id);
                    if (empty($group)) {
                        throw new Exception('找不到这个分组！');
                        
                    }
                    $query->where(['group_id' => $group_id]);
                }
            }
        
            //型号
            if (request::isset('device_type')) {
                $device_type_id = request::int('device_type');
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
            if (request::has('tag_ids')) {
                $tag_ids = request::array('tag_ids');
            }
            if (request::has('tag_id')) {
                $tag_ids[] = request::int('tag_id');
            }
        
            $tag_ids = array_unique($tag_ids);
            if ($tag_ids) {
                $tags_query = m('tags')->where(['id' => $tag_ids]);
                foreach ($tags_query->findAll() as $tag) {
                    $query->where("tags_data REGEXP '<{$tag->getId()}>'");
                }
            }
        
            //关键字
            $keywords = request::trim('keywords');
            if (!empty($keywords)) {
                $query->whereOr([
                    'name LIKE' => "%{$keywords}%",
                    'imei LIKE' => "%{$keywords}%",
                    'app_id LIKE' => "%{$keywords}%",
                    'iccid LIKE' => "%{$keywords}%",
                ]);
            }
        
            //只显示有问题设备
            if (request::bool('error')) {
                $query->where(['error_code <>' => 0]);
            }
        
            //缺货设备
            if (request::bool('low')) {
                $remain_warning = intval(settings('device.remainWarning', 1));
                $query->where(['remain <' => $remain_warning]);
            }
        
            //位置已变化        
            if (request::bool('lac')) {
                $query->where(['s1' => 1]);
            }
        
            $now = new DateTimeImmutable();
        
            if (request::isset('online')) {
                $online_time = $now->modify('-15 min');
                //在线状态
                if (request::bool('online')) {
                    $query->where(['last_ping >' => $online_time->getTimestamp()]);
                } elseif (request::bool('offline')) {
                    $query->where(['last_ping <' => $online_time->getTimestamp()]);
                }
            }
        
            //长时间不在线
            if (request::bool('lost')) {
                $offset = intval(settings('device.lost', 1));
                $offset_time = $now->modify("-{$offset} days");
                $query->where(['last_online <' => $offset_time->getTimestamp()]);
            }
        
            //长时间不出货
            if (request::bool('no_order')) {
                $offset = intval(settings('device.issuing', 1));
                $offset_time = $now->modify("-{$offset} days");
                $query->where(['last_order <' => $offset_time->getTimestamp()]);
            }
        
            //维护状态
            if (request::bool('maintenance')) {
                $query->where(['status' => Device::STATUS_MAINTENANCE]);
            }
        
            //App未绑定
            if (request::bool('unbind')) {
                $query->where("(app_id IS NULL OR app_id='')");
            }

            //指定设备id获取设备列表
            if (request::has('ids')) {
                $ids = request::array('ids', []);
                $query->where(['id' => $ids]);
            }
            
            $page = max(1, request::int('page'));
            $page_size = request::int('pagesize', DEFAULT_PAGESIZE);
        
            $total = $query->count();
            $total_page = ceil($total / $page_size);
            if ($page > $total_page) {
                $page = 1;
            }
        
            $devices = [
                'total' => $total,
                'page' => $page,
                'totalpage' => $total_page,
                'list' => [],
            ];
        
            $query->page($page, $page_size);
            $query->orderBy('id desc');
        
            /** @var deviceModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $data = [
                    'id' => intval($entry->getId()),
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
                $devices['list'][] = $data;
            }
        
            return $devices;
        } catch(Exception $e) {
            return err($e->getMessage());
        }
    }
}
