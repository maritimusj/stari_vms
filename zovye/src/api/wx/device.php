<?php


namespace zovye\api\wx;


use Exception;
use zovye\model\agentModelObj;
use zovye\App;
use zovye\base\modelObjFinder;
use zovye\CtrlServ;
use zovye\model\deviceModelObj;
use zovye\DeviceTypes;
use zovye\Goods;
use zovye\model\goods_stats_vwModelObj;
use zovye\request;
use zovye\State;
use zovye\Stats;
use zovye\model\userModelObj;
use zovye\Util;
use zovye\We7;
use function zovye\error;
use function zovye\request;
use function zovye\is_error;
use function zovye\isEmptyArray;
use function zovye\m;
use function zovye\settings;

class device
{

    /**
     * 格式化设备信息.
     *
     * @param userModelObj $user
     * @param deviceModelObj $device
     * @param bool $simple
     * @param int $keeper_id
     * @param bool $online
     *
     * @return array
     */
    public static function formatDeviceInfo(userModelObj $user, deviceModelObj $device, $simple = false, $keeper_id = 0, $online = false): array
    {
        unset($user);

        $extra = $device->get('extra', []);

        list($v, $way, $is_percent) = $device->getCommissionValue($keeper_id);

        if ($simple) {
            $result = [
                'device' => [
                    'id' => $device->getImei(),
                    'name' => $device->getName(),
                ],
                'keeper' => [
                    'keeper_id' => $keeper_id,
                    'id' => $device->hasKeeper($keeper_id) ? $keeper_id : 0,
                    'kind' => $device->getKeeperKind($keeper_id),
                    'way' => $way,
                ],
                'extra' => [
                    'location' => $extra['location'] ?: null,
                    'is_down' => isset($extra['isDown']) && $extra['isDown'] ? 1 : 0,
                ],
            ];
            if ($is_percent) {
                $result['keeper']['percent'] = $v;
            } else {
                $result['keeper']['fixed'] = $v;
            }

            return $result;
        }

        $agent = $device->getAgent();

        $result = [
            'device' => [
                'id' => $device->getImei(),
                'name' => $device->getName(),
                'rank' => $device->getRank(),
                'qrcode' => $device->getQrcode(),
                'createtime' => date('Y-m-d H:i:s', $device->getCreatetime()),
            ],
            'extra' => [
                'iccid' => $device->getIccid(),
                'location' => $extra['location'] ?: null,
                'volume' => intval($extra['volume']),
                'is_down' => isset($extra['isDown']) && $extra['isDown'] ? 1 : 0,
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
                'kind' => $device->getKeeperKind($keeper_id),
            ],
        ];

        $sig = intval($device->getSig());
        if ($sig != -1) {
            $result['status']['sig'] = $sig;
        }

        if ($is_percent) {
            $result['keeper']['percent'] = $v;
        } else {
            $result['keeper']['fixed'] = $v;
        }

        $device_type = DeviceTypes::from($device);
        if ($device_type) {
            $result['type'] = [
                'id' => $device_type->getDeviceId() > 0 ? 0 : $device_type->getId(),
                'title' => $device_type->getTitle(),
            ];
        }

        if (App::isBluetoothDeviceSupported() && $device->isBlueToothDevice()) {
            $result['device']['buid'] = $device->getBUID();
            $result['device']['mac'] = $device->getMAC();
        }

        $payload = $device->getPayload(true);
        if ($payload && is_array($payload['cargo_lanes'])) {
            $result['status']['cargo_lanes'] = array_map(function ($lane) {
                $lane['goods_price'] = number_format(intval($lane['goods_price']) / 100, 2);
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

        if ($device->getGroupId()) {
            $result['group'] = group::getDeviceGroup($device->getGroupId());
        }

        //电量
        $qoe = $device->getQoe();
        if (isset($qoe) && $qoe > 0) {
            $result['status']['qoe'] = intval($qoe);
        }

        //app报告的位置数据
        $app_loc = $device->get('location', null);
        if ($app_loc && is_array($app_loc)) {
            $result['app']['location'] = $app_loc;
        }

        //设备默认显示代理商的地区
        if (empty($result['extra']['location']['area'])) {
            if ($agent) {
                $agent_data = $agent->getAgentData();
                if ($agent_data['area']) {
                    $result['extra']['location']['area'] = array_values($agent_data['area']);
                }
            }
        } else {
            $result['extra']['location']['area'] = array_values($result['extra']['location']['area']);
        }

        return $result;
    }

    /**
     * @param $id
     * @param agentModelObj|null $owner
     *
     * @return mixed
     */
    public static function getDevice($id, agentModelObj $owner = null)
    {
        if (empty($id)) {
            return error(State::ERROR, '设备ID不正确！');
        }

        //findDevice可以查找到使用shadowId的设备
        $device = \zovye\Device::find($id, ['imei', 'shadow_id']);
        if (empty($device)) {
            $device = Util::activeDevice($id);
            if (is_error($device)) {
                return error(State::ERROR, '找不到这个设备，请重新扫描二维码！');
            }
        }

        if ($device->getAgentId() > 0) {
            if ($owner && !$owner->settings('agentData.misc.power')) {
                $agent = $owner->isPartner() ? $owner->getPartnerAgent() : $owner;
                if (!\zovye\Device::isOwner($device, $agent)) {
                    return error(State::ERROR, '没有权限管理这个设备！');
                }
            }
        }

        return $device;
    }

    public static function deviceReset(): array
    {
        $device = null;

        $user = common::getUser();

        if ($user->isAgent() || $user->isPartner()) {
            common::checkCurrentUserPrivileges('F_sb');
            $device = self::getDevice(request('id'), $user->isAgent() ? $user->getAgent() : $user->getPartnerAgent());
            if (is_error($device)) {
                return $device;
            }
            if (!$device->isOwnerOrSuperior($user)) {
                return error(State::ERROR, '没有权限执行这个操作！');
            }
        } elseif ($user->isKeeper()) {
            $device = \zovye\Device::find(request('id'), ['imei', 'shadow_id']);
            if (empty($device)) {
                return error(State::ERROR, '找不到这个设备！');
            }
            $keeper = $user->getKeeper();
            if (
                empty($keeper) ||
                $device->getAgentId() != $keeper->getAgentId() ||
                !$device->hasKeeper($keeper) ||
                $device->getKeeperKind($keeper) != \zovye\Keeper::OP
            ) {
                return error(State::ERROR, '没有权限执行这个操作！');
            }
        }

        if ($device) {
            if (!$device->lockAcquire()) {
                return error(State::ERROR, '无法锁定设备！');
            }

            $lane = request::int('lane');
            $laneData = $device->settings("extra.cargo_lanes.l{$lane}", []);
            if (empty($laneData)) {
                return error(State::ERROR, '货道不正确！');
            }

            $num = request::int('num');
            $device->updateSettings("extra.cargo_lanes.l{$lane}.num", $num);

            return ['msg' => '设置成功！'];
        }

        return error(State::ERROR, '找不到指定的设备！');
    }

    public static function deviceGoods(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_tj');

        $device = device::getDevice(request('id'), $user);
        if (is_error($device)) {
            return $device;
        }

        $agent = $user->isPartner() ? $user->getPartnerAgent() : $user;
        if (!\zovye\Device::isOwner($device, $agent)) {
            return error(State::FAIL, '没有权限执行这个操作！');
        }

        $date = request::trim('date');
        if (empty($date)) {
            $date = date('Y-m-d');
        }

        $query = m('goods_stats_vw')->where(
            [
                'agent_id' => $device->getAgentId(),
                'device_id' => $device->getId(),
                'date' => $date,
            ]
        );

        $result = [];

        /** @var goods_stats_vwModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $result[] = [
                'id' => intval($entry->getId()),
                'name' => strval($entry->getName()),
                'total' => intval($entry->getTotal()),
            ];
        }

        foreach ($result as &$entry) {
            $data = Goods::data($entry['id']);
            $entry['img'] = $data['img'];
            $entry['unit_title'] = $data['unit_title'];
        }

        return $result;
    }

    /**
     * 获取统计信息.
     *
     * @return array
     */
    public static function statistics(): array
    {
        $user = common::getAgent();

        /**
         * @param deviceModelObj $device
         *
         * @return array
         */
        $location = function (deviceModelObj $device) {
            $extra = $device->get('extra', []);
            //位置
            if ($extra['location']['area']) {
                return array_values($extra['location']['area']);
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
        };

        //是首页请求时，忽略F_tj权限
        if (request('date')) {
            common::checkCurrentUserPrivileges('F_tj');
        }

        $result = [
            'list' => [
                [
                    'id' => '',
                    'name' => '全部设备',
                ],
            ],
        ];

        if (request::has('date')) {
            //统计修复状态
            $v = $user->isAgent() ? $user : $user->getPartnerAgent();
            if ($v) {
                $repair = $v->settings('repair', []);
                if ($repair) {
                    $result['repair'] = [
                        'state' => $repair['status'],
                    ];
                }
            }

            $arr = explode('-', request::str('date'));
            if (count($arr) == 2) {
                //月份的每一天
                $m = 'days';
            } elseif (count($arr) == 3) {
                //天的每小时
                $m = 'hours';

                //具体哪天的时候需要设备出货列表
                $result['devices'] = [];
            } else {
                return error(State::ERROR, '日期不对！');
            }

            //指定了下级代理guid
            if (request::has('guid')) {
                $res = agent::getUserByGUID(request::str('guid'));
                if (empty($res)) {
                    return error(State::ERROR, '找不到这个用户！');
                } else {
                    $agent = $res->isAgent() ? $res : $res->getPartnerAgent();
                }
            } else {
                $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
            }

            //设备列表
            $devices_query = \zovye\Device::query();
            if (request::has('guid')) {
                $devices_query->where(['agent_id' => $agent->getAgentId()]);
            } else {
                $devices_query->where(['agent_id' => $user->getAgentId()]);
            }

            /** @var  deviceModelObj $item */
            foreach ($devices_query->findAll() as $item) {
                $x = [
                    'id' => $item->getId(),
                    'name' => $item->getName() ?: '<未登记>',
                ];

                $result['list'][] = $x;

                //具体到哪一天时，顺便加入设备详情
                if ($m == 'hours') {
                    $data = Stats::getDayTotal($item, request::str('date'));
                    if ($data['total'] > 0) {
                        $result['devices'][$x['id']] = [
                            'name' => $x['name'],
                            'all' => [
                                'free' => $data['free'] + $data['balance'],
                                'fee' => $data['fee'],
                            ],
                            'area' => $location($item),
                        ];
                    }
                }
            }

            $obj = null;
            if (request::has('guid')) {
                $obj = $agent;
            } else {
                $obj = $agent;
            }

            //指定了设备
            if (request::has('deviceid')) {
                $device = \zovye\Device::get(request::int('deviceid'));
                if (empty($device)) {
                    return error(State::ERROR, '找不到这个设备！');
                }

                if (!$device->isOwnerOrSuperior($agent)) {
                    return error(State::ERROR, '没有权限管理这个设备！');
                }

                $obj = $device;
            }

            $data = [];
            if ($m == 'days') {
                $data = Stats::daysOfMonth($obj, request::str('date'));
            } elseif ($m == 'hours') {
                $data = Stats::hoursOfDay($obj, request::str('date'));
            }

            $result[$m] = $data;
        } else {
            //首页
            $remainWarning = settings('device.remainWarning', 1);

            $low_query = \zovye\Device::query(['remain <' => $remainWarning]);
            $error_query = \zovye\Device::query(['error_code <>' => 0]);

            $low_query->where(['agent_id' => $user->getAgentId()]);
            $error_query->where(['agent_id' => $user->getAgentId()]);

            $result['msg'] = m('agent_msg')->findOne(We7::uniacid(['agent_id' => $user->getAgentId(), 'updatetime' => 0])) ? 1 : 0; //是否有未读消息
            $result['low'] = $low_query->count();
            $result['error'] = $error_query->count();

            //今日出货
            $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
            $data = Stats::getDayTotal($agent);
            $result['all'] = [
                'name' => $agent->getName(),
                'free' => $data['free'] + $data['balance'],
                'fee' => $data['fee'],
            ];
        }

        return $result;
    }

    public static function getDeviceOnline(): array
    {
        if (request::has('id')) {
            /** @var deviceModelObj|array $device */
            $device = $device = \zovye\Device::find(request::str('id'), ['imei', 'shadow_id']);
            if (is_error($device)) {
                return $device;
            }

            if ($device->isVDevice() || $device->isBlueToothDevice()) {
                return [
                    'mcb' => [ 'online' => true ],
                    'app' => [ 'online' => true ],
                ];
            }

            $result =  Util::cachedCall(30, function() use($device) {
                $result = [
                    'mcb' => [                        
                        'online' => $device->isMcbOnline(),
                    ],
                ];
                if ($device->getAppId()) {
                    $result['app'] =  [
                        'online' => $device->isAppOnline(),
                    ];
                }                
                return $result;
            }, $device->getId());

            Util::logToFile('debug', $result);
            return $result;
        }

        $ids = [];

        $mcbIDs = request::array('mcb');
        if ($mcbIDs) {
            $ids['mcb'] = $mcbIDs;
        }

        $app_ids = request::array('app');
        if ($app_ids) {
            $ids['app'] = $app_ids;
        }

        if (!isEmptyArray($ids)) {
            $res = CtrlServ::v2_query('online', [], json_encode($ids), 'application/json');
            if (!empty($res) && $res['status'] === true && is_array($res['data'])) {
                return $res['data'];
            }
        }

        return [];
    }

    /**
     * @param userModelObj $user
     * @param modelObjFinder $query
     * @param bool $onlineStatus
     * @return array
     * @throws Exception
     */
    public static function getDeviceList(userModelObj $user, modelObjFinder $query, bool $onlineStatus = null): array
    {
        if (request::has('keyword')) {
            $keyword = request::trim('keyword');
            $query->where("(name LIKE '%{$keyword}%' OR imei LIKE '%{$keyword}%')");
        }

        //简单信息
        $simple = request::bool('simple');
        $date = request::trim('date', '');
        $month = request::trim('month', '');
        $keeper_id = request::int('keeperid');

        if (!isset($online_status)) {
            $onlineStatus = request::bool('online');
        }

        $total = $query->count();

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGESIZE));

        $result = [
            'total' => $total,
            'page' => $page,
            'pagesize' => $page_size,
            'totalpage' => ceil($total / $page_size),
            'simple' => $simple,
            'list' => [],
        ];

        if ($total > 0) {
            if (request::has('orderby') && in_array(strtolower(request::str('orderby')), ['id', 'name', 'sig', 'createtime'])) {
                $order_by = strtolower(request::str('orderby'));
                if ($order_by == 'id') {
                    $order_by = 'imei';
                }
                $order = in_array(strtoupper(request::str('order')), ['ASC', 'DESC']) ? strtoupper(request::str('order')) : 'ASC';
                $query->orderBy("{$order_by} {$order}");
            } else {
                $query->orderBy('rank DESC');
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
                    $res = CtrlServ::v2_query('online', [], json_encode($ids), 'application/json');
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
                    } else if (App::isVDeviceSupported() && $device->isVDevice()) {
                        $data['status']['online'] = true;
                    } else {
                        $data['status']['online'] = $online_status[$device->getImei()] ? true : false;
                        if ($device->getAppId()) {
                            $data['app']['online'] = $online_status[$device->getAppId()] ? true : false;
                        }
                    }
                }
                if ($date) {
                    $data['stats']['day'] = Util::cachedCall(10, function() use($device, $date) {
                        return intval($device->getDTotal(['total'], $date));
                    }, $device->getId(), $date);
                }
                if ($month) {
                    $data['stats']['month'] = Util::cachedCall(10, function() use($device, $month) {
                        return intval($device->getMTotal(['total'], $month));
                    }, $device->getId(), $month);
                }
                $result['list'][] = $data;
            }
        }
        return $result;
    }

    public static function deviceTypes(): array
    {
        $user = common::getAgent();

        $params = [
            'page' => request::int('page'),
            'pagesize' => request::int('pagesize'),
            'keywords' => request::str('keywords'),
            'goods' => false,
        ];

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
        $params['agent_id'] = $agent->getId();
        $params['platform_types'] = true;

        return DeviceTypes::getList($params);
    }

    public static function deleteDeviceTypes(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_xh');

        $device_type = DeviceTypes::get(request::int('id'));
        if (empty($device_type)) {
            return error(State::ERROR, '找不到这个设备型号！');
        }

        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();
        if ($device_type->getAgentId() != $agent->getId()) {
            return error(State::ERROR, '没有权限管理');
        }

        if (!$device_type->destroy()) {
            return error(State::ERROR, '删除失败！');
        }

        return ['msg' => '删除成功！'];
    }

    public static function deviceTypeDetail(): array
    {
        $device_type = DeviceTypes::get(request::int('id'));
        if (empty($device_type)) {
            return error(State::ERROR, '找不到这个设备型号！');
        }

        return DeviceTypes::format($device_type);
    }

    public static function updateDeviceTypes(): array
    {
        $data = request::is_string('data') ? json_decode(urldecode(request::str('data')), true) : [];

        $user = common::getAgent();
        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        common::checkCurrentUserPrivileges('F_xh');

        $check_goods = function ($goods_id) use ($agent) {
            $goods = Goods::get($goods_id);

            return !empty($goods) && $goods->getAgentId() == $agent->getId();
        };

        $title = trim($data['title']);
        if (empty($title)) {
            return error(State::ERROR, '型号名称不能为空！');
        }

        if (empty($data['cargo_lanes'])) {
            return error(State::ERROR, '至少需要一个默认货道！');
        }

        if ($data['id']) {
            $device_type = DeviceTypes::get($data['id']);
            if (empty($device_type)) {
                return error(State::ERROR, '找不到这个设备型号！');
            }

            if ($device_type->getAgentId() != $agent->getId()) {
                return error(State::ERROR, '没有权限');
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
                return error(State::ERROR, '保存设备型号失败！');
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
                return error(State::ERROR, '创建设备型号失败！');
            }
        }

        return ['msg' => '保存设备型号成功！'];
    }

    public static function getDeviceInfo(): array
    {
        $imei = request::trim('imei');
        $res = \zovye\Device::get($imei, true);
        if ($res) {
            $data = [
                'id' => $res->getId(),
                'name' => $res->getName(),
                'mobile' => ''
            ];
            $agent = $res->getAgent();
            if ($agent) {
                $data['mobile'] = $agent->getMobile();
            }
            return ['data' => $data];
        } else {
            return error(State::ERROR, '没有数据！');
        }
    }

    public static function deviceSub(): array
    {
        $agent = common::getAgent();

        if ($agent->isPartner()) {
            $agent = $agent->getPartnerAgent();
        }

        //简单信息
        $simple = request::bool('simple');

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGESIZE));

        $result = [
            'total' => 0,
            'page' => $page,
            'pagesize' => $page_size,
            'totalpage' => 0,
            'simple' => $simple,
            'list' => [],
        ];

        $agent_ids = \zovye\Agent::getAllSubordinates($agent);
        if (empty($agent_ids)) {
            return $result;
        }

        $query = \zovye\Device::query(['agent_id' => $agent_ids]);

        if (request::has('keyword')) {
            $keyword = request::trim('keyword');
            $query->where("(name LIKE '%{$keyword}%' OR imei LIKE '%{$keyword}%')");
        }

        $total = $query->count();

        if ($total > 0) {
            $result['total'] = $total;
            $result['totalpage'] = ceil($total / $page_size);

            if (request::has('orderby') && in_array(strtolower(request::str('orderby')), ['id', 'name', 'sig', 'createtime'])) {
                $order_by = strtolower(request::str('orderby'));
                if ($order_by == 'id') {
                    $order_by = 'imei';
                }
                $order = in_array(strtoupper(request::str('order')), ['ASC', 'DESC']) ? strtoupper(request::str('order')) : 'ASC';
                $query->orderBy("{$order_by} {$order}");
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

                $res = CtrlServ::v2_query('online', [], json_encode($ids), 'application/json');
                if (!empty($res) && $res['status'] === true && is_array($res['data'])) {
                    $online_status = $res['data'];
                }
            }
            /** @var deviceModelObj $device */
            foreach ($devices as $device) {
                $data = device::formatDeviceInfo($device->getAgent(), $device, $simple, 0);
                if (App::isBluetoothDeviceSupported() && $device->isBlueToothDevice()) {
                    $data['status']['online'] = true;
                } else if (App::isVDeviceSupported() && $device->isVDevice()) {
                    $data['status']['online'] = true;
                } else {
                    if (!$simple) {
                        $data['status']['online'] = $online_status[$device->getImei()] ? true : false;
                        if ($device->getAppId()) {
                            $data['app']['online'] = $online_status[$device->getAppId()] ? true : false;
                        }
                    }
                }
                $result['list'][] = $data;
            }
        }
        return $result;
    }

    /**
     * 发送app重启消息.
     *
     * @return array
     */
    public static function appRestart(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sb');
        $app_id = request::trim('id');

        if ($app_id) {
            $device = \zovye\Device::getFromAppId($app_id);
            if (empty($device)) {
                return error(State::ERROR, '找不到设备！');
            }

            if (!$device->isOwnerOrSuperior($user)) {
                return error(State::ERROR, '没有权限管理这个设备！');
            }

            $device->appNotify('restart');

            return ['msg' => 'APP重启消息已发送！'];
        }

        return error(State::ERROR, '操作失败，请联系管理员！');
    }
}