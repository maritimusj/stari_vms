<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use DateTime;
use Exception;
use zovye\api\common;
use zovye\App;
use zovye\base\ModelObjFinder;
use zovye\business\GDCVMachine;
use zovye\business\TKPromoting;
use zovye\CtrlServ;
use zovye\domain\DeviceTypes;
use zovye\domain\Goods;
use zovye\domain\Group as ZovyeGroup;
use zovye\domain\Inventory;
use zovye\domain\Locker;
use zovye\domain\Order;
use zovye\model\agentModelObj;
use zovye\model\deviceModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\Stats;
use zovye\util\CacheUtil;
use zovye\util\DeviceUtil;
use zovye\util\Helper;
use zovye\util\Util;
use function zovye\err;
use function zovye\is_error;
use function zovye\isEmptyArray;

class device
{
    /**
     * 格式化设备信息
     */
    public static function formatDeviceInfo(
        userModelObj $user,
        deviceModelObj $device,
        bool $simple = false,
        int $keeper_id = 0,
        bool $online = false
    ): array {
        unset($user);

        $extra = $device->get('extra', []);

        $commission_val = $device->getCommissionValue($keeper_id);

        $location = isEmptyArray($extra['location']['tencent']) ? $extra['location'] : $extra['location']['tencent'];

        if ($simple) {
            $result = [
                'device' => [
                    'id' => $device->getImei(),
                    'name' => $device->getName(),
                    'model' => $device->getDeviceModel(),
                ],
                'keeper' => [
                    'keeper_id' => $keeper_id,
                    'id' => $device->hasKeeper($keeper_id) ? $keeper_id : 0,
                    'kind' => $device->getKeeperKind($keeper_id),
                    'commission' => $commission_val ? $commission_val->format() : null,
                ],
                'extra' => [
                    'is_down' => $device->isMaintenance() ? 1 : 0,
                ],
            ];
            if (!isEmptyArray($location)) {
                $result['extra']['location'] = $location;
            }

            return $result;
        }

        $agent = $device->getAgent();

        $result = [
            'device' => [
                'id' => $device->getImei(),
                'name' => $device->getName(),
                'model' => $device->getDeviceModel(),
                'rank' => $device->getRank(),
                'qrcode' => $device->getQrcode(),
                'createtime' => date('Y-m-d H:i:s', $device->getCreatetime()),
            ],
            'extra' => [
                'iccid' => $device->getICCID(),
                'volume' => intval($extra['volume']),
                'is_down' => $device->isMaintenance() ? 1 : 0,
            ],
            'status' => [
                'lastOnline' => date('Y-m-d H:i:s', $device->getlastOnline()),
                'lastPing' => date('Y-m-d H:i:s', $device->getLastPing()),
            ],
            'tags' => $device->getTagsAsText(false),
            'statistics' => [
                //暂未实现
            ],
            'keeper' => [
                'keeper_id' => $keeper_id,
                'id' => $device->hasKeeper($keeper_id) ? $keeper_id : 0,
                'kind' => $device->getKeeperKind($keeper_id),
                'commission' => $commission_val ? $commission_val->format() : null,
            ],
        ];

        $device_type = DeviceTypes::from($device);
        if ($device_type) {
            $result['type'] = [
                'id' => $device_type->getDeviceId() > 0 ? 0 : $device_type->getId(),
                'title' => $device_type->getTitle(),
            ];
        } else {
            $result['type'] = [
                'id' => 0,
                'title' => '',
            ];
        }

        //蓝牙设备
        if ($device->isBlueToothDevice() && App::isBluetoothDeviceSupported()) {
            $result['device']['buid'] = $device->getBUID();
            $result['device']['mac'] = $device->getMAC();
            $result['device']['protocol'] = $device->getBlueToothProtocolName();
        }

        //充电桩
        if ($device->isChargingDevice() && App::isChargingDeviceEnabled()) {
            $result['charger'] = [];
            $chargerNum = $device->getChargerNum();
            for ($i = 0; $i < $chargerNum; $i++) {
                $charging_data = $device->getChargerStatusData($i + 1);
                $result['charger'][] = [
                    'status' => $charging_data['status'],
                    'soc' => $charging_data['soc'],
                ];
            }
        }

        //尿素加注设备
        if ($device->isFuelingDevice() && App::isFuelingDeviceEnabled()) {
            $result['device']['expiration'] = [
                'date' => $device->getExpiration(),
                'is_expired' => $device->isExpired(),
            ];

            $result['device']['renewal'] = [
                'year' => [
                    'price' => $device->getYearRenewalPrice(),
                ],
            ];

            $result['device']['solo'] = $device->getSoloMode();
            $result['device']['timeout'] = $device->getTimeout();
            $result['device']['pulse'] = $device->getPulseValue();
        } else {
            if (App::isDeviceWithDoorEnabled()) {
                $result['doorNum'] = $device->getDoorNum();
            }
        }

        if (App::isFlashEggEnabled()) {
            $result['extra']['adDeviceUID'] = $device->getAdDeviceUID();
            $result['extra']['limit'] = $device->settings('extra.limit', []);
        }

        if (App::isDeviceScheduleTaskEnabled()) {
            $result['device']['schedule'] = \zovye\domain\Device::getScheduleTaskTotal($device);
        }

        if (App::isGoodsExpireAlertEnabled()) {
            $payload = Helper::getPayloadWithAlertData($device);
        } else {
            $payload = $device->getPayload(true);
        }

        if ($payload && is_array($payload['cargo_lanes'])) {
            $result['status']['cargo_lanes'] = array_map(function ($lane) {
                $lane['goods_price'] = intval($lane['goods_price']);
                $lane['goods_img'] = Util::toMedia($lane['goods_img']);

                return $lane;
            }, $payload['cargo_lanes']);
        } else {
            $result['status']['cargo_lanes'] = [];
        }

        if ($device->getAppId()) {
            $result['app'] = [
                'id' => $device->getAppId(),
                'lastonline' => date('Y-m-d H:i:s', $device->getAppLastOnline()),
                'version' => $device->getAppVersion() ?: '<无>',
            ];

            if ($online) {
                $result['app']['online'] = $device->isAppOnline();
            }
        }

        if ($online) {
            $result['status']['online'] = $device->isMcbOnline();
        }

        if (!isEmptyArray($location)) {
            $result['extra']['location'] = $location;
        }

        //app报告的位置数据
        $app_loc = $device->settings('location', []);
        if ($app_loc && is_array($app_loc)) {
            $result['app']['location'] = $app_loc;
        }

        //设备默认显示代理商的地区
        if (isEmptyArray($result['location']['area'])) {
            if ($agent) {
                $agent_data = $agent->getAgentData();
                if (!isEmptyArray($agent_data['area'])) {
                    $result['location']['area'] = array_values($agent_data['area']);
                }
            }
        } else {
            $result['location']['area'] = array_values($result['location']['area']);
        }

        if ($device->getGroupId()) {
            if ($device->isChargingDevice()) {
                $groupData = group::getDeviceGroup($device->getGroupId(), ZovyeGroup::CHARGING);
            } else {
                $groupData = group::getDeviceGroup($device->getGroupId());
            }
            if (empty($groupData['agent_id']) || $groupData['agent_id'] == $device->getAgentId()) {
                $result['group'] = $groupData;
            }
        }

        $result['status'][\zovye\domain\Device::V0_STATUS_VOLTAGE] = $device->getV0Status(
            \zovye\domain\Device::V0_STATUS_VOLTAGE
        );
        $result['status'][\zovye\domain\Device::V0_STATUS_COUNT] = (int)$device->getV0Status(
            \zovye\domain\Device::V0_STATUS_COUNT
        );
        $result['status'][\zovye\domain\Device::V0_STATUS_ERROR] = $device->getV0ErrorDescription();

        //信号强度
        $sig = $device->getSig();
        if ($sig != -1) {
            $result['status']['sig'] = $sig;
        }

        //电量
        $qoe = $device->getQoe();
        if (isset($qoe) && $qoe > 0) {
            $result['status']['qoe'] = intval($qoe);
        }

        return $result;
    }

    public static function deviceNearBy(agentModelObj $agent): array
    {
        return DeviceUtil::getNearBy($agent);
    }

    public static function getDevice($id, agentModelObj $owner = null)
    {
        if (empty($id)) {
            return err('设备ID不正确！');
        }

        //findDevice可以查找到使用shadowId的设备
        $device = \zovye\domain\Device::find($id, ['imei', 'shadow_id']);
        if (empty($device)) {
            $params = [];

            $defaultType = DeviceTypes::getDefault();
            if ($defaultType) {
                $params['device_type'] = $defaultType->getId();
            }

            /** @var deviceModelObj $device */
            $device = \zovye\domain\Device::activate($id, $params);
            if (is_error($device)) {
                return err('找不到这个设备，请重新扫描二维码！');
            }

            if (App::isFuelingDeviceEnabled()) {
                $device->setDeviceModel(\zovye\domain\Device::FUELING_DEVICE);
                $device->save();
            }

            if (App::isTKPromotingEnabled()) {
                TKPromoting::deviceReg($device);
            }
        }

        if ($device->getAgentId() > 0) {
            if ($owner && !$owner->hasFactoryPermission()) {
                $agent = $owner->isPartner() ? $owner->getPartnerAgent() : $owner;
                if (!\zovye\domain\Device::isOwner($device, $agent)) {
                    return err('没有权限管理这个设备！');
                }
            }
        }

        return $device;
    }

    public static function deviceReset(userModelObj $user): array
    {
        $device = null;

        if (!Locker::try("user:{$user->getId()}")) {
            return err('无法锁定用户，请稍后再试！');
        }

        $reason = '??';
        if ($user->isAgent() || $user->isPartner()) {
            $agent = $user->isAgent() ? $user->getAgent() : $user->getPartnerAgent();
            $device = self::getDevice(Request::trim('id'), $agent);
            if (is_error($device)) {
                return $device;
            }
            if (!$device->isOwnerOrSuperior($user)) {
                return err('没有权限执行这个操作！');
            }
            $reason = '代理商补货';
        } elseif ($user->isKeeper()) {
            $device = \zovye\domain\Device::find(Request::trim('id'), ['imei', 'shadow_id']);
            if (empty($device)) {
                return err('找不到这个设备！');
            }
            $keeper = $user->getKeeper();
            if (empty($keeper) ||
                $device->getAgentId() != $keeper->getAgentId() ||
                !$device->hasKeeper($keeper, \zovye\domain\Keeper::OP)) {
                return err('没有权限执行这个操作！');
            }
            $reason = '运营人员补货';
        }

        if ($device) {
            if (!$device->payloadLockAcquire()) {
                return err('设备正忙，请稍后再试！');
            }

            $lane = Request::int('lane');
            $laneData = $device->getLane($lane);
            if (empty($laneData)) {
                return err('货道不正确！');
            }

            $num = Request::int('num');

            if ($user->isKeeper()) {
                $agent = $device->getAgent();
                if ($agent && !$agent->allowReduceGoodsNum()) {
                    if ($num < $laneData['num']) {
                        return err('不允许减少商品库存！');
                    }
                }
            }

            $res = $device->resetPayload([$lane => '@'.$num], $reason);
            if (is_error($res)) {
                return err('保存库存失败！');
            }

            if (App::isInventoryEnabled()) {
                $user = $user->isPartner() ? $user->getPartnerAgent() : $user;
                $v = Inventory::syncDevicePayloadLog($user, $device, $res, $reason);
                if (is_error($v)) {
                    return $v;
                }
            }

            if (App::isGDCVMachineEnabled()) {
                GDCVMachine::scheduleUploadDeviceJob($device);
            }

            return ['msg' => '设置成功！'];
        }

        return err('找不到指定的设备！');
    }

    protected static function getStatisticsData($user, $params = []): array
    {
        $locationFN = function (deviceModelObj $device) {
            return CacheUtil::cachedCall(300, function () use ($device) {
                $extra = $device->get('extra', []);
                //位置
                if ($extra['location']['tencent']['area']) {
                    return array_values($extra['location']['tencent']['area']);
                } else {
                    $agent = $device->getAgent();
                    if ($agent) {
                        $agent_data = $agent->getAgentData();
                        if ($agent_data['area']) {
                            return array_values($agent_data['area']);
                        }
                    }
                    //else 获取定位地址？
                }

                return [];
            }, $device->getId());
        };

        $result = [
            'list' => [
                [
                    'id' => '',
                    'name' => '全部设备',
                ],
            ],
            'goods_expired_alert' => alert::count($user),
        ];

        if ($params['date']) {
            $date_str = $params['date'];
            $arr = explode('-', $date_str);
            if (count($arr) == 2) {
                //月份的每一天
                $m = 'days';
            } elseif (count($arr) == 3) {
                //天的每小时
                $m = 'hours';

                //具体哪天的时候需要设备出货列表
                $result['devices'] = [];
            } else {
                return err('日期不正确！');
            }

            //指定了下级代理guid
            if ($params['guid']) {
                $res = agent::getUserByGUID($params['guid']);
                if (empty($res)) {
                    return err('找不到这个用户！');
                } else {
                    $agent = $res->isAgent() ? $res : $res->getPartnerAgent();
                }
            } else {
                $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
            }

            $first_order = Order::getFirstOrderOfAgent($agent);
            if ($first_order) {
                try {
                    $date_obj = new DateTime($date_str);
                    $order_date_obj = new DateTime(date('Y-m', $first_order['createtime']));
                    if ($date_obj < $order_date_obj) {
                        return $result;
                    }
                } catch (Exception $e) {
                }
                $result['date_limit'] = date('Y-m-d', $first_order['createtime']);
            } else {
                return $result;
            }

            //设备列表
            $devices_query = \zovye\domain\Device::query();
            $devices_query->where(['agent_id' => $agent->getId()]);

            /** @var  deviceModelObj $item */
            foreach ($devices_query->findAll() as $item) {
                $id = $item->getId();
                $name = $item->getName();
                if (empty($params['deviceid']) || $params['deviceid'] == $id) {
                    //具体到哪一天时，顺便加入设备详情
                    if ($m == 'hours') {
                        $data = Stats::getDayTotal($item, $date_str, $params['w']);
                        if ($data['total'] > 0) {
                            $result['devices'][$id] = [
                                'name' => $name,
                                'all' => [
                                    'free' => $data['free'],
                                    'fee' => $data['pay'],
                                ],
                                'area' => $locationFN($item),
                            ];
                        }
                    }
                }

                $result['list'][] = [
                    'id' => $item->getId(),
                    'name' => $item->getName() ?: '<未登记>',
                ];
            }

            $obj = $agent;

            //指定了设备
            if ($params['deviceid']) {
                $device = \zovye\domain\Device::get($params['deviceid']);
                if (empty($device)) {
                    return err('找不到这个设备！');
                }

                if (!$device->isOwnerOrSuperior($agent)) {
                    return err('没有权限管理这个设备！');
                }

                $obj = $device;
            }

            if ($m == 'days') {
                $data = Stats::daysOfMonth($obj, $date_str, $params['w']);
            } elseif ($m == 'hours') {
                $data = Stats::hoursOfDay($obj, $date_str, $params['w']);
            } else {
                $data = [];
            }

            $result[$m] = $data;
        } else {
            $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

            //首页
            $low_query = \zovye\domain\Device::query([
                'remain <' => App::getRemainWarningNum($agent),
                'agent_id' => $agent->getId(),
            ]);
            $error_query = \zovye\domain\Device::query([
                'error_code <>' => 0,
                'agent_id' => $agent->getId(),
            ]);

            $result['low'] = $low_query->count();
            $result['error'] = $error_query->count();

            //今日出货
            $data = Stats::getDayTotal($agent, null, $params['w']);
            $result['all'] = [
                'name' => $agent->getName(),
                'free' => $data['free'],
                'fee' => $data['pay'],
            ];
        }

        return $result;
    }

    /**
     * 获取统计信息
     */
    public static function statistics(agentModelObj $agent): array
    {
        $params = [
            'date' => Request::str('date'),
            'guid' => Request::str('guid'),
            'deviceid' => Request::str('deviceid'),
            'w' => Request::str('w', 'goods'),
        ];

        return CacheUtil::cachedCall(6, function () use ($agent, $params) {
            return self::getStatisticsData($agent, $params);
        }, $agent->getId(), http_build_query($params));
    }

    public static function getDeviceOnline(): array
    {
        if (Request::has('id')) {
            /** @var deviceModelObj|array $device */
            $device = \zovye\domain\Device::find(Request::str('id'), ['imei', 'shadow_id']);
            if (is_error($device)) {
                return $device;
            }

            if ($device->isVDevice() || $device->isBlueToothDevice()) {
                return [
                    'mcb' => ['online' => true],
                    'app' => ['online' => true],
                ];
            }

            $result = [
                'mcb' => [
                    'online' => $device->isMcbOnline(),
                ],
            ];
            if ($device->getAppId()) {
                $result['app'] = [
                    'online' => $device->isAppOnline(),
                ];
            }

            return $result;
        }

        $ids = [];

        $mcbIDs = Request::array('mcb');
        if ($mcbIDs) {
            $ids['mcb'] = $mcbIDs;
        }

        $app_ids = Request::array('app');
        if ($app_ids) {
            $ids['app'] = $app_ids;
        }

        if (!isEmptyArray($ids)) {
            $res = CtrlServ::onlineV2($ids);
            if (!empty($res) && $res['status'] === true && is_array($res['data'])) {
                return $res['data'];
            }
        }

        return [];
    }

    public static function getDeviceList(userModelObj $user, ModelObjFinder $query, bool $onlineStatus = null): array
    {
        if (Request::has('keyword')) {
            $keyword = Request::trim('keyword');
            if ($keyword) {
                $query->whereOr([
                    'name LIKE' => "%$keyword%",
                    'imei LIKE' => "%$keyword%",
                ]);
            }
        }

        //简单信息
        $simple = Request::bool('simple');
        $date = Request::trim('date');
        $month = Request::trim('month');
        $keeper_id = Request::int('keeperid');

        if (!isset($online_status)) {
            $onlineStatus = Request::bool('online');
        }

        $total = $query->count();

        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        $result = [
            'total' => $total,
            'page' => $page,
            'pagesize' => $page_size,
            'totalpage' => ceil($total / $page_size),
            'simple' => $simple,
            'list' => [],
        ];

        if ($total > 0) {
            if (Request::has('orderby') && in_array(
                    strtolower(Request::str('orderby')),
                    ['id', 'name', 'sig', 'createtime']
                )) {
                $order_by = strtolower(Request::str('orderby'));
                if ($order_by == 'id') {
                    $order_by = 'imei';
                }
                $order = in_array(strtoupper(Request::str('order')), ['ASC', 'DESC']) ? strtoupper(
                    Request::str('order')
                ) : 'ASC';
                $query->orderBy("$order_by $order");
            } else {
                $query->orderBy(['rank DESC', 'id DESC']);
            }

            $query->page($page, $page_size);

            $ids = [];
            $online_status = [];
            $devices = $query->findAll();

            if (!$simple) {
                /** @var deviceModelObj $device */
                foreach ($devices as $device) {
                    if (App::isVDeviceSupported() && $device->isVDevice()) {
                        continue;
                    }
                    if (App::isBluetoothDeviceSupported() && $device->isBlueToothDevice()) {
                        continue;
                    }
                    $ids['mcb'][] = $device->getImei();
                    if ($device->getAppId()) {
                        $ids['app'][] = $device->getAppId();
                    }
                }

                if ($onlineStatus) {
                    $res = CtrlServ::onlineV2($ids);
                    if (!empty($res) && $res['status'] === true && is_array($res['data'])) {
                        $online_status = $res['data'];
                    }
                }
            }
            /** @var deviceModelObj $device */
            foreach ($devices as $device) {
                $data = device::formatDeviceInfo($user, $device, $simple, $keeper_id);
                if ($online_status) {
                    if (App::isBluetoothDeviceSupported() && $device->isBlueToothDevice()) {
                        $data['status']['online'] = true;
                    } else {
                        if (App::isVDeviceSupported() && $device->isVDevice()) {
                            $data['status']['online'] = true;
                        } else {
                            $data['status']['online'] = (bool)$online_status[$device->getImei()];
                            if ($device->getAppId()) {
                                $data['app']['online'] = (bool)$online_status[$device->getAppId()];
                            }
                        }
                    }
                }

                if ($date) {
                    $data['stats']['day'] = intval(Stats::getDayTotal($device, $date)['total']);
                }

                if ($month) {
                    $data['stats']['month'] = intval(Stats::getMonthTotal($device, $month)['total']);
                }

                $result['list'][] = $data;
            }
        }

        return $result;
    }

    public static function deviceTypes(agentModelObj $agent): array
    {
        $params = [
            'page' => Request::int('page'),
            'pagesize' => Request::int('pagesize'),
            'keywords' => Request::str('keywords'),
            'goods' => false,
        ];

        $params['agent_id'] = $agent->getId();
        $params['platform_types'] = true;

        return DeviceTypes::getList($params);
    }

    public static function deleteDeviceTypes(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_xh');

        $device_type = DeviceTypes::get(Request::int('id'));
        if (empty($device_type)) {
            return err('找不到这个设备型号！');
        }

        if ($device_type->getAgentId() != $agent->getId()) {
            return err('没有权限管理');
        }

        if (!$device_type->destroy()) {
            return err('删除失败！');
        }

        return ['msg' => '删除成功！'];
    }

    public static function deviceTypeDetail(): array
    {
        $device_type = DeviceTypes::get(Request::int('id'));
        if (empty($device_type)) {
            return err('找不到这个设备型号！');
        }

        return DeviceTypes::format($device_type);
    }

    public static function updateDeviceTypes(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_xh');

        $data = Request::is_string('data') ? json_decode(urldecode(Request::str('data')), true) : [];

        $check_goods = function ($goods_id) use ($agent) {
            $goods = Goods::get($goods_id);

            return !empty($goods) && $goods->getAgentId() == $agent->getId();
        };

        $title = trim($data['title']);
        if (empty($title)) {
            return err('型号名称不能为空！');
        }

        if (empty($data['cargo_lanes'])) {
            return err('至少需要一个默认货道！');
        }

        if ($data['id']) {
            $device_type = DeviceTypes::get($data['id']);
            if (empty($device_type)) {
                return err('找不到这个设备型号！');
            }

            if ($device_type->getAgentId() != $agent->getId()) {
                return err('没有权限');
            }

            if ($title != $device_type->getTitle()) {
                $device_type->setTitle($title);
            }

            $cargo_lanes = [];
            foreach ((array)$data['cargo_lanes'] as $cargo) {
                if ($check_goods($cargo['goods'])) {
                    $cargo_lanes[] = [
                        'goods' => intval($cargo['goods']),
                        'capacity' => intval($cargo['capacity']),
                    ];
                }
            }

            $device_type->setExtraData('cargo_lanes', $cargo_lanes);

            if (!$device_type->save()) {
                return err('保存设备型号失败！');
            }
        } else {
            $types_data = [
                'agent_id' => $agent->getId(),
                'title' => $title,
                'extra' => [
                    'cargo_lanes' => [],
                ],
            ];
            foreach ((array)$data['cargo_lanes'] as $cargo) {
                if ($check_goods($cargo['goods'])) {
                    $types_data['extra']['cargo_lanes'][] = [
                        'goods' => intval($cargo['goods']),
                        'capacity' => intval($cargo['capacity']),
                    ];
                }
            }

            $device_type = DeviceTypes::create($types_data);
            if (empty($device_type)) {
                return err('创建设备型号失败！');
            }
        }

        if (App::isGDCVMachineEnabled()) {
            GDCVMachine::scheduleUploadDeviceJobForDeviceType($device_type->getId());
        }

        return ['msg' => '保存设备型号成功！'];
    }

    public static function getDeviceInfo(): array
    {
        $imei = Request::trim('imei');
        $res = \zovye\domain\Device::get($imei, true);
        if ($res) {
            $data = [
                'id' => $res->getId(),
                'name' => $res->getName(),
                'mobile' => '',
            ];
            $agent = $res->getAgent();
            if ($agent) {
                $data['mobile'] = $agent->getMobile();
            }

            return ['data' => $data];
        } else {
            return err('没有数据！');
        }
    }

    public static function deviceSub(agentModelObj $agent): array
    {
        //简单信息
        $simple = Request::bool('simple');

        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        $result = [
            'total' => 0,
            'page' => $page,
            'pagesize' => $page_size,
            'totalpage' => 0,
            'simple' => $simple,
            'list' => [],
        ];

        $agent_ids = \zovye\domain\Agent::getAllSubordinates($agent);
        if (empty($agent_ids)) {
            return $result;
        }

        $query = \zovye\domain\Device::query(['agent_id' => $agent_ids]);

        if (Request::has('keyword')) {
            $keyword = Request::trim('keyword');
            $query->where([
                'name LIKE' => "%$keyword%",
                'imei LIKE' => "%$keyword%",
            ]);
        }

        $total = $query->count();

        if ($total > 0) {
            $result['total'] = $total;
            $result['totalpage'] = ceil($total / $page_size);

            if (Request::has('orderby') && in_array(
                    strtolower(Request::str('orderby')),
                    ['id', 'name', 'sig', 'createtime']
                )) {
                $order_by = strtolower(Request::str('orderby'));
                if ($order_by == 'id') {
                    $order_by = 'imei';
                }
                $order = in_array(strtoupper(Request::str('order')), ['ASC', 'DESC']) ? strtoupper(
                    Request::str('order')
                ) : 'ASC';
                $query->orderBy("$order_by $order");
            } else {
                $query->orderBy('name ASC');
            }

            $query->page($page, $page_size);

            $ids = [];
            $online_status = [];
            $devices = $query->findAll();
            if (!$simple) {
                /** @var deviceModelObj $device */
                foreach ($devices as $device) {
                    if (App::isVDeviceSupported() && $device->isVDevice()) {
                        continue;
                    }
                    if (App::isBluetoothDeviceSupported() && $device->isBlueToothDevice()) {
                        continue;
                    }
                    $ids['mcb'][] = $device->getImei();
                    if ($device->getAppId()) {
                        $ids['app'][] = $device->getAppId();
                    }
                }

                $res = CtrlServ::onlineV2($ids);
                if (!empty($res) && $res['status'] === true && is_array($res['data'])) {
                    $online_status = $res['data'];
                }
            }
            /** @var deviceModelObj $device */
            foreach ($devices as $device) {
                $data = device::formatDeviceInfo($device->getAgent(), $device, $simple);
                if (App::isBluetoothDeviceSupported() && $device->isBlueToothDevice()) {
                    $data['status']['online'] = true;
                } else {
                    if (App::isVDeviceSupported() && $device->isVDevice()) {
                        $data['status']['online'] = true;
                    } else {
                        if (!$simple) {
                            $data['status']['online'] = (bool)$online_status[$device->getImei()];
                            if ($device->getAppId()) {
                                $data['app']['online'] = (bool)$online_status[$device->getAppId()];
                            }
                        }
                    }
                }
                $result['list'][] = $data;
            }
        }

        return $result;
    }

    /**
     * 发送app重启消息
     */
    public static function appRestart(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_sb');

        $app_id = Request::trim('id');
        if ($app_id) {
            $device = \zovye\domain\Device::getFromAppId($app_id);
            if (empty($device)) {
                return err('找不到设备！');
            }

            if (!$device->isOwnerOrSuperior($agent)) {
                return err('没有权限管理这个设备！');
            }

            if ($device->appRestart()) {
                return ['msg' => 'APP重启消息已发送！'];
            }

            return ['msg' => 'APP重启消息发送失败！'];
        }

        return err('操作失败，请联系管理员！');
    }

    public static function openDoor(userModelObj $user): array
    {
        if ($user->isAgent() || $user->isPartner()) {
            $agent = $user->isAgent() ? $user->getAgent() : $user->getPartnerAgent();
            common::checkPrivileges($agent, 'F_sb');
            $device = device::getDevice(Request::str('id'), $agent);
            if (is_error($device)) {
                return $device;
            }
            if (!\zovye\domain\Device::isOwner($device, $agent)) {
                return err('没有权限执行这个操作！');
            }
        } elseif ($user->isKeeper()) {
            $device = \zovye\domain\Device::find(Request::str('id'), ['imei', 'shadow_id']);
            if (!$device) {
                return err('找不到这个设备！');
            }

            $keeper = $user->getKeeper();
            if (empty($keeper) || !$device->hasKeeper($keeper, \zovye\domain\Keeper::OP)) {
                return err('没有权限管理这个设备！');
            }
        } else {
            return err('没有权限管理这个设备！');
        }

        $index = Request::int('index', 1);

        $msg = $device->openDoor($index) ? '开锁指令已发送！' : '开锁指令发送失败！';

        return ['msg' => $msg];
    }

    public static function deviceKeepers(agentModelObj $agent): array
    {
        $device = device::getDevice(Request::str('id'), $agent);
        if (is_error($device)) {
            return $device;
        }

        $result = [];
        $keepers = $device->getKeepers();

        foreach ($keepers as $keeper) {
            $commission_val = $keeper->getCommissionValue($device);
            $data = [
                'name' => $keeper->getName(),
                'mobile' => $keeper->getMobile(),
                'kind' => $keeper->getKind($device),
                'commission' => $commission_val ? $commission_val->format() : null,
            ];

            $user = $keeper->getUser();
            if ($user) {
                $data['user'] = $user->profile();
            }

            $result[] = $data;
        }

        return $result;
    }
}