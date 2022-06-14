<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;

use Exception;
use RuntimeException;
use ZipArchive;
use zovye\model\device_eventsModelObj;
use zovye\model\device_feedbackModelObj;
use zovye\model\device_groupsModelObj;
use zovye\model\device_logsModelObj;
use zovye\model\device_recordModelObj;
use zovye\model\deviceModelObj;
use zovye\model\packageModelObj;
use zovye\model\payload_logsModelObj;
use zovye\model\userModelObj;
use zovye\model\versionModelObj;

$op = request::op('default');

$tpl_data = [
    'op' => $op,
];

if ($op == 'list') {

    JSON::result(Device::search());

} else {
    if ($op == 'default') {

        if (request::is_ajax()) {
            JSON::result(Device::search());
        }

        $tpl_data = [
            'lost_offset_day' => intval(settings('device.lost', 1)),
            'issuing_offset_day' => intval(settings('device.issuing', 1)),
        ];
        //指定代理商
        $agent_id = request::int('agentId');
        if ($agent_id) {
            $agent = Agent::get($agent_id);
            if (empty($agent)) {
                Util::itoast('找不到这个代理商！', $this->createWebUrl('device'), 'error');
            }
            $tpl_data['s_agent'] = $agent->profile();
        }

        $tags_id = request::int('tag_id');
        if ($tags_id) {
            $tag = m('tags')->findOne(['id' => $tags_id]);
            if (empty($tag)) {
                Util::itoast('找不到这个标签！', $this->createWebUrl('device'), 'error');
            }
            $tpl_data['s_tags'] = [
                [
                    'id' => intval($tag->getId()),
                    'title' => strval($tag->getTitle()),
                    'count' => intval($tag->getCount()),
                ],
            ];
        }

        if (request::has('types')) {
            $type_id = request::int('types');
            $type = DeviceTypes::get($type_id);
            if (empty($type)) {
                Util::itoast('找不到这个型号！', $this->createWebUrl('device'), 'error');
            }
            $tpl_data['s_device_type'] = [
                [
                    'id' => $type->getId(),
                    'title' => $type->getTitle(),
                    'lanes_total' => count($type->getCargoLanes()),
                ],
            ];
        }

        $tpl_data['page'] = request::int('page', 1);
        $tpl_data['upload'] = (bool)settings('device.upload.url', '');
        $tpl_data['gate'] = CtrlServ::status();


        app()->showTemplate('web/device/default_new', $tpl_data);
    } elseif ($op == 'search') {

        $result = [];

        $query = Device::query();

        $openid = request::trim('openid', '', true);
        if ($openid) {
            $agent = Agent::get($openid, true);
            if ($agent) {
                $query->where(['agent_id' => $agent->getId()]);
            }
        }

        $keyword = request::trim('keyword', '', true);
        if ($keyword) {
            $query->whereOr([
                'imei LIKE' => "%$keyword%",
                'name LIKE' => "%$keyword%",
            ]);
        }

        if (request::has('page')) {
            $page = max(1, request::int('page'));
            $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

            $query->page($page, $page_size);
        } else {
            $query->limit(20);
        }

        /** @var deviceModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'imei' => $entry->getImei(),
                'name' => $entry->getName(),
            ];

            $res = $entry->getAgent();
            if ($res) {
                $data['agent'] = $res->getName();
            }

            $result[] = $data;
        }

        JSON::success($result);
    } elseif ($op == 'online_stats') {

        $ids = request::has('id') ? [request::int('id')] : request('ids');

        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        $result = [];

        if (is_array($ids)) {
            foreach ($ids as &$id) {
                $id = intval($id);
            }

            $devices = Device::query(['id' => $ids])->findAll();

            $online_ids = [];
            /** @var deviceModelObj $entry */
            foreach ($devices as $entry) {
                if ($entry->isBlueToothDevice() || $entry->isVDevice()) {
                    continue;
                }
                $online_ids['uid'][] = $entry->getImei();
            }

            $ids_str = json_encode($online_ids);
            $devices_status = Util::cachedCall(10, function () use ($ids_str) {
                $res = CtrlServ::v2_query('detail', [], $ids_str, 'application/json');
                if (!empty($res) && $res['status'] === true && is_array($res['data'])) {
                    return $res['data'];
                }

                return [];
            }, $ids_str);

            /** @var deviceModelObj $entry */
            foreach ($devices as $entry) {
                $data = [
                    'id' => $entry->getId(),
                    'status' => [
                        'mcb' => false,
                        'app' => empty($entry->getAppId()) ? null : false,
                    ],
                ];

                if ($entry->isVDevice() || $entry->isBlueToothDevice()) {
                    $data['status']['mcb'] = true;
                } else {
                    $status = $devices_status[$entry->getImei()];
                    if (isset($status['mcb']['online'])) {
                        $data['status']['mcb'] = boolval($status['mcb']['online']);
                    }

                    if (isset($status['app']['online'])) {
                        $data['status']['app'] = boolval($status['app']['online']);
                        if (!empty($status['app']['uid'])) {
                            $data['status']['appId'] = $status['app']['uid'];
                            $entry->setAppId($status['app']['uid']);
                        }
                    }

                    if (isset($status['mcb']['RSSI'])) {
                        $entry->setSig($status['mcb']['RSSI']);
                        $data['sig'] = $entry->getSig();
                    }

                    $entry->save();
                }

                $result[] = $data;
            }
        }

        JSON::success($result);
    } elseif ($op == 'device_stats') {

        $id = request::int('id');
        $device = Device::get($id);
        if (empty($device)) {
            JSON::fail([]);
        }

        $result = Util::cachedCall(60, function () use ($device) {
            if (Util::isSysLoadAverageOk()) {
                return $device->getPullStats();
            }
            throw new RuntimeException('系统繁忙！');
        }, $device->getId());

        JSON::success($result);

    } elseif ($op == 'device_data') {

        $ids = request::has('id') ? [request::int('id')] : request('ids');

        if (is_string($ids)) {
            $ids = explode(',', $ids);
        }

        $result = [];

        if (is_array($ids)) {
            foreach ($ids as &$id) {
                $id = intval($id);
            }

            $devices = Device::query(['id' => $ids])->findAll();

            /** @var deviceModelObj $entry */
            foreach ($devices as $entry) {
                $data = [
                    'id' => $entry->getId(),
                    'name' => $entry->getName(),
                    'IMEI' => $entry->getImei(),
                    'ICCID' => $entry->getIccid(),
                    'qrcode' => $entry->getQrcode(),
                    'model' => $entry->getDeviceModel(),
                    'activeQrcode' => $entry->isActiveQrcodeEnabled(),
                    'getUrl' => $entry->getUrl(),
                    'v0_status' => [
                        Device::V0_STATUS_SIG => $entry->getSig(),
                        Device::V0_STATUS_QOE => $entry->getQoe(),
                        Device::V0_STATUS_VOLTAGE => $entry->getV0Status(Device::V0_STATUS_VOLTAGE),
                        Device::V0_STATUS_COUNT => (int)$entry->getV0Status(Device::V0_STATUS_COUNT),
                        Device::V0_STATUS_ERROR => $entry->getV0ErrorDescription(),
                    ],
                    'capacity' => intval($entry->getCapacity()),
                    'remain' => intval($entry->getRemainNum()),
                    'reset' => $entry->getReset(),
                    'lastError' => $entry->getLastError(),
                    'lastOnline' => $entry->getLastOnline() ? date('Y-m-d H:i:s', $entry->getLastOnline()) : '',
                    'lastPing' => $entry->getLastPing() ? date('Y-m-d H:i:s', $entry->getLastPing()) : '',
                    'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                    'lockedTime' => $entry->isLocked() ? date('Y-m-d H:i:s', $entry->getLockedTime()) : '',
                    'appId' => $entry->getAppId(),
                    'appVersion' => $entry->getAppVersion(),
                    'total' => 'n/a',
                    'gettype' => [
                        'location' => $entry->needValidateLocation(),
                    ],
                    'address' => [
                        'web' => $entry->settings('extra.location.baidu.address', ''),
                        'agent' => $entry->settings('extra.location.tencent.address', ''),
                    ],
                    'isDown' => $entry->settings('extra.isDown', Device::STATUS_NORMAL),
                ];

                if (App::isDeviceWithDoorEnabled()) {
                    $data['doorNum'] = $entry->getDoorNum();
                }

                if (Util::isSysLoadAverageOk()) {
                    $data['total'] = [
                        'month' => intval(Stats::getMonthTotal($entry)['total']),
                        'today' => intval(Stats::getDayTotal($entry)['total']),
                    ];

                    $data['gettype']['freeLimitsReached'] = $entry->isFreeLimitsReached();

                    $accounts = $entry->getAssignedAccounts();
                    if ($accounts) {
                        $data['gettype']['free'] = true;
                    }

                    $payload = $entry->getPayload(true);
                    $data = array_merge($data, $payload);

                    $low_price = 0;
                    $high_price = 0;

                    foreach ((array)$payload['cargo_lanes'] as $lane) {
                        $goods_data = Goods::data($lane['goods'], ['useImageProxy' => true]);
                        if ($goods_data && $goods_data[Goods::AllowPay]) {
                            if ($low_price === 0 || $low_price > $goods_data['price']) {
                                $low_price = $goods_data['price'];
                            }
                            if ($high_price == 0 || $high_price < $goods_data['price']) {
                                $high_price = $goods_data['price'];
                            }
                        }
                    }

                    if ($low_price == $high_price) {
                        $data['gettype']['price'] = number_format($low_price / 100, 2);
                    } else {
                        $data['gettype']['price'] = number_format($low_price / 100, 2).'-'.number_format(
                                $high_price / 100,
                                2
                            );
                    }
                }

                $group = $entry->getGroup();
                if ($group) {
                    $data['group'] = $group->format();
                }

                $tags = $entry->getTagsAsText(false);
                foreach ($tags as $i => $title) {
                    $data['tags'][] = [
                        'id' => $i,
                        'title' => $title,
                    ];
                }

                if (App::isVDeviceSupported()) {
                    $data['isVD'] = $entry->isVDevice();
                    unset($data['lastOnline'], $data['lastPing'], $data['lastError']);
                }

                if (App::isBluetoothDeviceSupported()) {
                    if ($entry->isBlueToothDevice()) {
                        $data['isBluetooth'] = true;
                        $data['BUID'] = $entry->getBUID();
                    }
                }

                if (settings('device.lac.enabled')) {
                    $data['s1'] = $entry->getS1();
                }

                $data['device_type'] = $entry->getDeviceType();

                $statistic = $entry->get('firstMsgStatistic', []);
                if ($statistic) {
                    $data['firstMsgTotal'] = intval($statistic[date('Ym')][date('d')]['total']);
                }

                $result[] = $data;
            }
        }

        JSON::success($result);
    } elseif ($op == 'add' || $op == 'add_vd' || $op == 'add_bluetooth_device' || $op == 'edit') {

        //替换原先的groups
        $group_res = Group::query(Group::NORMAL)->findAll();

        $group_arr = [];

        /** @var device_groupsModelObj $val */
        foreach ($group_res as $val) {
            $group_arr[$val->getId()] = ['title' => $val->getTitle()];
        }

        $tpl_data['groups'] = $group_arr;

        $id = request::int('id');

        $device_types = [];

        if ($id) {
            $device = Device::get($id);
            if (empty($device)) {
                Util::itoast('设备不存在！', We7::referer(), 'error');
            }

            $x = DeviceTypes::from($device);
            if ($x) {
                $device_types[] = DeviceTypes::format($x);
            }

            $tpl_data['app'] = $device->getAppId();

            $extra = $device->get('extra');

            $loc = empty($extra['location']['baidu']) ? [] : $extra['location']['baidu'];
            $tpl_data['loc'] = $loc;

            $tpl_data['device'] = $device;
            $tpl_data['extra'] = $extra;

            if ($device->isBlueToothDevice()) {
                $tpl_data['bluetooth'] = $device->settings('extra.bluetooth', []);
            }

            $agent = $device->getAgent();

            $tpl_data['agent'] = $agent;
        } else {
            if ($op == 'add_vd') {
                $tpl_data['vd_imei'] = 'V'.Util::random(15, true);
            }
        }

        if ($op == 'add_vd' || (isset($device) && $device->isVDevice())) {
            $tpl_data['device_model'] = Device::VIRTUAL_DEVICE;
        } elseif ($op == 'add_bluetooth_device' || (isset($device) && $device->isBlueToothDevice())) {
            $tpl_data['device_model'] = Device::BLUETOOTH_DEVICE;
        } else {
            $tpl_data['device_model'] = Device::NORMAL_DEVICE;
        }

        $tpl_data['bluetooth']['protocols'] = BlueToothProtocol::all();
        $tpl_data['device_types'] = $device_types;

        if (isset($device) && App::isMoscaleEnabled() && MoscaleAccount::isAssigned($device)) {
            $tpl_data['moscale'] = [
                'MachineKey' => isset($extra) && is_array($extra) ? strval($extra['moscale']['key']) : '',
                'LabelList' => MoscaleAccount::getLabelList(),
                'AreaListSaved' => isset($extra) && is_array($extra) ? $extra['moscale']['label'] : [],
                'RegionData' => MoscaleAccount::getRegionData(),
                'RegionSaved' => isset($extra) && is_array($extra) ? $extra['moscale']['region'] : [],
            ];
        }

        if (isset($device) && App::isZJBaoEnabled() && ZhiJinBaoAccount::isAssigned($device)) {
            $tpl_data['zjbao'] = [
                'scene' => $device->settings('zjbao.scene', ''),
            ];
        }

        $module_url = MODULE_URL;
        if ($op == 'add_vd' || ($device && $device->isVDevice())) {
            $icon_html = <<<HTML
        <img src="{$module_url}static/img/vdevice.svg" class="icon" title="虚拟设备">
HTML;
        } elseif ($op == 'add_bluetooth_device' || ($device && $device->isBlueToothDevice())) {
            $icon_html = <<<HTML
        <img src="{$module_url}static/img/bluetooth.svg" class="icon" title="蓝牙设备">
HTML;
        } else {
            $icon_html = <<<HTML
        <img src="{$module_url}static/img/machine.svg" class="icon">
HTML;
        }

        $tpl_data['icon'] = $icon_html;
        $tpl_data['from'] = request::str('from', 'base');
        $tpl_data['is_bluetooth_device'] = $op == 'add_bluetooth_device' || (isset($device) && $device->isBlueToothDevice(
                ));
        $tpl_data['themes'] = Theme::all();

        app()->showTemplate('web/device/edit_new', $tpl_data);

    } elseif ($op == 'deviceTestAll') {

        $device = Device::get(request('id'));
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }

        $data = [
            'is_vd' => $device->isVDevice(),
            'device_id' => $device->getId(),
            'params' => $device->getPayload(true),
        ];

        $content = app()->fetchTemplate(
            'web/device/cargo_lanes_test',
            $data
        );

        JSON::success([
            'title' => "设备货道 [ {$device->getName()} ]",
            'content' => $content,
        ]);
    } elseif ($op == 'deviceTestLaneN') {

        $device = Device::get(request('id'));
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }

        $lane = max(0, request::int('lane'));
        $res = Util::deviceTest(null, $device, $lane);

        Util::resultJSON(!is_error($res), ['msg' => $res['message']]);
    } elseif ($op == 'reset') {

        $id = request::int('id');
        $device = Device::get($id);
        if (empty($device)) {
            Util::itoast('找不到这个设备！', $this->createWebUrl('device'), 'error');
        }

        if (!$device->payloadLockAcquire(3)) {
            Util::itoast('设备正忙，请稍后再试！', $this->createWebUrl('device'), 'error');
        }

        $result = Util::transactionDo(function () use ($device) {
            if (!request::isset('lane') || request::str('lane') == 'all') {
                $data = [];
            } else {
                $data = [
                    request::int('lane') => 0,
                ];
            }
            $res = $device->resetPayload($data, '管理员重置商品数量');
            if (is_error($res)) {
                throw new RuntimeException('保存库存失败！');
            }
            if (!$device->save()) {
                throw new RuntimeException('保存数据失败！');
            }

            return true;
        });

        if (is_error($result)) {
            Util::itoast($result['message'], $this->createWebUrl('device'), 'error');
        }

        $device->updateAppRemain();
        Util::itoast('商品数量重置成功！', $this->createWebUrl('device'), 'success');
    } elseif ($op == 'unreg') {

        $id = request::int('id');
        if ($id) {
            $device = Device::get($id);
            if ($device) {
                $app_id = $device->getAppId();
                if ($device->setAppId(null) && $device->setAppVersion(null) && $device->save()) {

                    //删除广告缓存
                    $device->remove('advsData');

                    //通知app更新配置
                    if ($app_id) {
                        CtrlServ::appNotify($app_id, 'update');
                    }

                    Util::itoast('清除AppId成功！', $this->createWebUrl('device'), 'success');
                }
            }
        }

        Util::itoast('清除AppId失败！', $this->createWebUrl('device'), 'error');
    } elseif ($op == 'remove') {

        $device = Device::get(request::int('id'));
        if (empty($device)) {
            Util::itoast('删除失败！', $this->createWebUrl('device'), 'error');
        }

        We7::load()->func('file');
        We7::file_remote_delete($device->getQrcode());

        $device->remove('assigned');
        $device->remove('advsData');
        $device->remove('accountsData');
        $device->remove('lastErrorNotify');
        $device->remove('lastRemainWarning');
        $device->remove('fakeQrcodeData');
        $device->remove('lastApkUpdate');
        $device->remove('firstMsgStatistic');
        $device->remove('location');
        $device->remove('statsData');
        $device->remove('lastErrorData');
        $device->remove('extra');

        //删除相关套餐
        foreach (Package::query(['device_id' => $device->getId()])->findAll() as $entry) {
            $entry->destroy();
        }

        //通知实体设备
        $device->appNotify('update');

        $device->destroy();

        Util::itoast('删除成功！', $this->createWebUrl('device'), 'success');
    } elseif ($op == 'get_lane_detail') {

        $priceFN = function ($data) {
            if ($data['cargo_lanes']) {
                foreach ((array)$data['cargo_lanes'] as $index => $lane) {
                    $data['cargo_lanes'][$index]['goods_price'] = number_format($lane['goods_price'] / 100, 2);
                    if (!isset($lane['num'])) {
                        $data['cargo_lanes'][$index]['num'] = 0;
                    }
                }
            }

            return $data;
        };

        $device_id = request::int('deviceid');
        $type_id = request::int('typeid');

        if ($device_id) {
            $device = Device::get($device_id);
        }

        if ($type_id) {
            $device_type = DeviceTypes::get($type_id);
            if (empty($device_type)) {
                JSON::fail('找不到这个型号！');
            }

            $data = DeviceTypes::format($device_type, true);
            $data = $priceFN($data);
            if (isset($device)) {
                $payload = $device->getPayload(false);
                foreach ((array)$payload['cargo_lanes'] as $index => $lane) {
                    $data['cargo_lanes'][$index]['num'] = intval($lane['num']);
                }
            }
            JSON::success($data);
        }


        if ($device_id) {
            $device = Device::get($device_id);
            if (empty($device)) {
                JSON::fail('找不到这个设备！');
            }

            $data = $device->getPayload(true);
            JSON::success($priceFN($data));
        }

        JSON::success([
            'cargo_lanes' => [],
        ]);
    } elseif ($op == 'save') {
        $id = request::int('id');

        $device = null;
        $result = Util::transactionDo(function () use ($id, &$device) {
            $data = [
                'agent_id' => 0,
                'name' => request::trim('name'),
                'imei' => request::trim('IMEI'),
                'group_id' => request::int('group'),
                'capacity' => max(0, request::int('capacity')),
                'remain' => max(0, request::int('remain')),
            ];

            $tags = request::trim('tags');
            $extra = [
                'pushAccountMsg' => request::trim('pushAccountMsg'),
                'isDown' => request::bool('isDown') ? Device::STATUS_MAINTENANCE : Device::STATUS_NORMAL,
                'activeQrcode' => request::bool('activeQrcode') ? 1 : 0,
                'address' => request::trim('address'),
                'grantloc' => [
                    'lng' => floatval(request('location')['lng']),
                    'lat' => floatval(request('location')['lat']),
                ],
                'txt' => [request::trim('first_txt'), request::trim('second_txt'), request::trim('third_txt')],
                'theme' => request::str('theme'),
            ];

            if (App::isDeviceWithDoorEnabled()) {
                $extra['door'] = [
                    'num' => request::int('doorNum', 1),
                ];
            }

            if (App::isMustFollowAccountEnabled()) {
                $extra['mfa'] = [
                    'enable' => request::int('mustFollow'),
                ];
            }

            if (App::isMoscaleEnabled()) {
                $extra['moscale'] = [
                    'key' => request::trim('moscaleMachineKey'),
                    'label' => array_map(function ($e) {
                        return intval($e);
                    }, explode(',', request::trim('moscaleLabel'))),
                    'region' => [
                        'province' => request::int('province_code'),
                        'city' => request::int('city_code'),
                        'area' => request::int('area_code'),
                    ],
                ];
            }

            if (App::isZeroBonusEnabled()) {
                setArray($extra, 'custom.bonus.zero.v', min(100, request::float('zeroBonus', -1, 2)));
            }

            if (empty($data['name']) || empty($data['imei'])) {
                throw new RuntimeException('设备名称或IMEI不能为空！');
            }

            $type_id = request::int('deviceType');
            if ($type_id) {
                $device_type = DeviceTypes::get($type_id);
                if (empty($device_type)) {
                    throw new RuntimeException('设备类型不正确！');
                }
            }

            $data['device_type'] = $type_id;

            if (App::isBluetoothDeviceSupported() && request::str('device_model') == Device::BLUETOOTH_DEVICE) {
                $extra['bluetooth'] = [
                    'protocol' => request::str('blueToothProtocol'),
                    'uid' => request::trim('BUID'),
                    'mac' => request::trim('MAC'),
                    'motor' => request::int('Motor'),
                    'screen' => request::int('blueToothScreen') ? 1 : 0,
                    'power' => request::int('blueToothPowerSupply') ? 1 : 0,
                    'disinfectant' => request::int('blueToothDisinfectant') ? 1 : 0,
                ];
            }

            $agent_id = request::int('agent_id');
            if ($agent_id) {
                $agent = Agent::get($agent_id);
                if (empty($agent)) {
                    throw new RuntimeException('找不到这个代理商！');
                }

                $data['agent_id'] = $agent->getId();
            }

            $now = time();

            if ($id) {
                $device = Device::get($id);
                if (empty($device)) {
                    throw new RuntimeException('设备不存在！');
                }

                if (!$device->payloadLockAcquire(3)) {
                    throw new RuntimeException('设备正忙，请稍后再试！');
                }

                if ($data['shadow_id']) {
                    $device->setShadowId($data['shadow_id']);
                }

                if ($data['agent_id'] != $device->getAgentId()) {
                    $device->setAgentId($data['agent_id']);
                }

                if ($data['name'] != $device->getName()) {
                    $device->setName($data['name']);
                }

                if ($data['device_type'] != $device->getDeviceType()) {
                    $res = $device->resetPayload(['*' => '@0'], '管理员改变型号', $now);
                    if (is_error($res)) {
                        throw new RuntimeException('保存库存失败！');
                    }
                    $device->setDeviceType($data['device_type']);
                }

                if ($data['group_id'] != $device->getGroupId()) {
                    $device->setGroupId($data['group_id']);
                }

                if (request::isset('volume')) {
                    $vol = max(0, min(100, request::int('volume')));
                    if ($vol != $device->settings('extra.volume')) {
                        $extra['volume'] = $vol;
                    }
                }
            } else {
                $device = Device::create($data);
                if (empty($device)) {
                    throw new RuntimeException('创建失败！');
                }

                $model = request('device_model');

                $device->setDeviceModel($model);

                if ($model == Device::NORMAL_DEVICE) {
                    $activeRes = Util::activeDevice($device->getImei());
                }

                //绑定套餐
                if (!$device->isBlueToothDevice()) {
                    /** @var packageModelObj $entry */
                    foreach (Package::query(['device_id' => 0])->findAll() as $entry) {
                        $entry->setDeviceId($device->getId());
                        $entry->save();
                    }
                }

                //绑定appId
                $device->updateAppId();
            }

            //处理自定义型号
            if (empty($type_id)) {
                $device->setDeviceType(0);

                $device_type = DeviceTypes::from($device);
                if (empty($device_type)) {
                    throw new RuntimeException('设备类型不正确！');
                }

                $old = $device_type->getExtraData('cargo_lanes', []);

                $cargo_lanes = [];
                $capacities = request::array('capacities');
                foreach (request::array('goods') as $index => $goods_id) {
                    $cargo_lanes[] = [
                        'goods' => intval($goods_id),
                        'capacity' => intval($capacities[$index]),
                    ];
                    if ($old[$index] && $old[$index]['goods'] != intval($goods_id)) {
                        $device->resetPayload([$index => '@0'], '管理员更改货道商品', $now);
                    }
                    unset($old[$index]);
                }

                foreach ($old as $index => $lane) {
                    $device->resetPayload([$index => '@0'], '管理员删除货道', $now);
                }

                $device_type->setExtraData('cargo_lanes', $cargo_lanes);
                $device_type->save();
            }

            if (empty($device_type)) {
                throw new RuntimeException('获取型号失败！');
            }

            //货道商品数量和价格
            $type_data = DeviceTypes::format($device_type);
            $cargo_lanes = [];
            foreach ($type_data['cargo_lanes'] as $index => $lane) {
                $cargo_lanes[$index] = [
                    'num' => '@'.max(0, request::int("lane{$index}_num")),
                ];
                if ($device_type->getDeviceId() == $device->getId()) {
                    $cargo_lanes[$index]['price'] = request::float("price$index", 0, 2) * 100;
                }
            }

            $res = $device->resetPayload($cargo_lanes, '管理员编辑设备', $now);
            if (is_error($res)) {
                throw new RuntimeException('保存设备库存数据失败！');
            }

            $location = request::array('location');
            $extra['location']['baidu']['lat'] = $location['lat'];
            $extra['location']['baidu']['lng'] = $location['lng'];

            $saved_baidu_loc = $device->settings('extra.location.baidu', []);
            if (
                strval($saved_baidu_loc['lng']) != strval($location['lng'])
                || strval($saved_baidu_loc['lat']) != strval($location['lat'])
            ) {
                $address = Util::getLocation($location['lng'], $location['lat']);
                if ($address) {
                    $extra['location']['baidu']['area'] = [
                        $address['province'],
                        $address['city'],
                        $address['district'],
                    ];
                    $extra['location']['baidu']['address'] = $address['address'];
                } else {
                    $extra['location']['area'] = [];
                    $extra['location']['address'] = [];
                }
            } else {
                $extra['location']['baidu'] = $device->settings('extra.location.baidu');
            }

            $extra['location']['tencent'] = $device->settings('extra.location.tencent', []);
            $extra['goodsList'] = request::trim('goodsList');

            $extra['schedule'] = [
                'screen' => [
                    'enabled' => request::bool('screenSchedule') ? 1 : 0,
                    'on' => request::str('start'),
                    'off' => request::str('end'),
                ],
            ];

            $original_extra = $device->get('extra', []);
            if ($original_extra['schedule']['screen'] !== $extra['schedule']['screen']) {
                $device->appNotify('config', [
                    'schedule' => $extra['schedule']['screen'],
                ]);
            }

            //合并extra
            $extra = array_merge($original_extra, $extra);

            if (!$device->set('extra', $extra)) {
                throw new RuntimeException('保存扩展数据失败！');
            }

            $device->setTagsFromText($tags);
            $device->setDeviceModel(request('device_model'));
            if (!$device->save()) {
                throw new RuntimeException('保存数据失败！');
            }

            if (App::isZJBaoEnabled()) {
                $device->updateSettings('zjbao.scene', request::trim('ZJBao_Scene'));
            }

            //更新公众号缓存
            $device->updateAccountData();

            $msg = '保存成功';
            $error = false;

            $device->updateScreenAdvsData();

            $device->updateAppVolume();

            $device->updateAppRemain();

            $res = $device->updateQrcode(true);

            if (is_error($res)) {
                $msg .= ', 发生错误：'.$res['message'];
                $error = true;
            }

            if (isset($activeRes) && is_error($activeRes)) {
                $msg .= '，发生错误：无法激活设备';
                $error = true;
            }

            $msg .= '!';

            return ['error' => $error, 'message' => $msg];
        });

        if (is_error($result)) {
            Util::itoast($result['message'], $id ? We7::referer() : $this->createWebUrl('device'), 'error');
        }

        Util::itoast(
            $result['message'],
            $this->createWebUrl(
                'device',
                ['op' => 'edit', 'id' => $device ? $device->getId() : $id, 'from' => request::str('from')]
            ),
            $result['error'] ? 'warning' : 'success'
        );
    } elseif ($op == 'online') {

        $device = Device::get(request::int('id'));
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }

        $res = $device->getOnlineDetail(false);
        if (empty($res)) {
            JSON::fail('请求出错，请稍后再试！');
        }
        JSON::success($res);
    } elseif ($op == 'deviceTest') {

        $id = request::int('id');
        if ($id) {
            /** @var deviceModelObj $device */
            $device = Device::get($id);
            if ($device) {
                $res = Util::deviceTest(null, $device);
                if (is_error($res)) {
                    JSON::fail($res);
                }

                $device->cleanError();
                $device->save();

                JSON::success('出货成功！');
            }
        }

        JSON::fail('找不到设备！');
    } elseif ($op == 'upgrade') {

        $version_id = request::int('version');

        $device = Device::find(request::trim('id'), ['id', 'imei']);
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }

        /** @var versionModelObj $version */
        $version = m('version')->findOne(We7::uniacid(['id' => $version_id]));
        if (empty($version) || empty($version->getUrl())) {
            JSON::fail('版本信息不正确！');
        }

        $res = $device->upgradeApk($version->getTitle(), $version->getVersion(), $version->getUrl());
        if ($res) {
            JSON::success("已通知设备下载更新！\r\n版本：{$version->getVersion()}\r\n网址：{$version->getUrl()}");
        } else {
            JSON::fail('通知更新失败！');
        }
    } elseif ($op == 'detail') {

        $device = Device::get(request::int('id'));
        if (empty($device)) {
            Util::itoast('找不到这个设备！', $this->createWebUrl('device'), 'error');
        }

        $tpl_data['navs'] = [
            'detail' => $device->getName(),
            'payload' => '库存',
            'log' => '事件',
            //'poll_event' => '最新',
            'event' => '消息',
        ];

        $tpl_data['media'] = [
            'image' => ['title' => '图片'],
            'video' => ['title' => '视频'],
            'audio' => ['title' => '音频'],
            'srt' => ['title' => '字幕'],
        ];

        $res = Device::getAppConfigData($device);
        if (is_error($res)) {
            $tpl_data['config'] = false;
        } else {
            if ($res['data']['srt']['subs']) {
                $srt = [];
                $ads = $device->getAdvs(Advertising::SCREEN);
                foreach ($ads as $ad) {
                    if ($ad['extra']['media'] == 'srt') {
                        $srt[] = [
                            'id' => $ad['id'],
                            'text' => strval($ad['extra']['text']),
                        ];
                    }
                }
                $tpl_data['srt'] = $srt;
            }
            $tpl_data['config'] = $res;
        }

        $tpl_data['is_device_notify_timeout'] = $device->isDeviceNotifyTimeout();
        $tpl_data['last_error_notify'] = $device->settings('lastErrorNotify');
        $tpl_data['last_error_data'] = $device->settings('lastErrorData');

        $tpl_data['is_last_remain_warning_timeout'] = $device->isLastRemainWarningTimeout();
        $tpl_data['last_remain_warning'] = $device->settings('lastRemainWarning');

        $tpl_data['last_apk_update'] = $device->settings('lastApkUpdate');
        $tpl_data['first_msg_statistic'] = $device->settings('firstMsgStatistic');
        $tpl_data['first_total'] = intval($tpl_data['firstMsgStatistic'][date('Ym')][date('d')]['total']);

        $accounts = $device->getAssignedAccounts();
        if ($accounts) {
            foreach ($accounts as &$entry) {
                $entry['edit_url'] = $this->createWebUrl('account', ['op' => 'edit', 'id' => $entry['id']]);
                if (empty($entry['qrcode'])) {
                    $entry['qrcode'] = MODULE_URL.'static/img/qrcode_blank.svg';
                }
            }
        }
        $tpl_data['accounts'] = $accounts;

        $tpl_data['day_stats'] = app()->fetchTemplate(
            'web/device/stats',
            [
                'chartid' => Util::random(10),
                'chart' => Util::cachedCall(30, function () use ($device) {
                    return Stats::chartDataOfDay($device, new DateTime());
                }, $device->getId()),
            ]
        );

        $tpl_data['month_stats'] = app()->fetchTemplate(
            'web/device/stats',
            [
                'chartid' => Util::random(10),
                'chart' => Util::cachedCall(30, function () use ($device) {
                    return Stats::chartDataOfMonth($device, new DateTime());
                }, $device->getId()),
            ]
        );

        $tpl_data['device'] = $device;
        $tpl_data['payload'] = $device->getPayload(true);

        $packages = [];
        $query = Package::query(['device_id' => $device->getId()]);
        /** @var packageModelObj $i */
        foreach ($query->findAll() as $i) {
            $packages[] = $i->format(true);
        }

        $tpl_data['packages'] = $packages;

        $tpl_data['mcb_online'] = $device->isMcbOnline();
        $tpl_data['app_online'] = $device->isAppOnline();

        app()->showTemplate('web/device/detail', $tpl_data);
    } elseif ($op == 'payload') {

        $device = Device::get(request('id'));
        if (empty($device)) {
            Util::itoast('找不到这个设备！', $this->createWebUrl('device'), 'error');
        }

        $tpl_data['navs'] = [
            'detail' => $device->getName(),
            'payload' => '库存',
            'log' => '事件',
            //'poll_event' => '最新',
            'event' => '消息',
        ];

        $query = $device->payloadQuery();

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        $total = $query->count();
        if (ceil($total / $page_size) < $page) {
            $page = 1;
        }

        $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

        $query->page($page, $page_size);
        $query->orderBy('id desc');

        $logs = [];

        /** @var payload_logsModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'org' => $entry->getOrg(),
                'num' => $entry->getNum(),
                'new' => $entry->getOrg() + $entry->getNum(),
                'reason' => strval($entry->getExtraData('reason', '')),
                'code' => strval($entry->getExtraData('code', '')),
                'clr' => strval($entry->getExtraData('clr', '#9e9e9e')),
                'createtime' => $entry->getCreatetime(),
                'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            ];
            $goods = Goods::get($entry->getGoodsId());
            if ($goods) {
                $data['goods'] = Goods::format($goods, false, true);
            }
            $logs[] = $data;
        }

        $verified = [];
        foreach ($logs as $index => $log) {
            $code = $log['code'];
            if (isset($verified[$code])) {
                continue;
            }
            $createtime = $log['createtime'];
            if (isset($logs[$index + 1])) {
                $verified[$code] = sha1($logs[$index + 1]['code'].$createtime) == $code;
            } else {
                $l = $device->payloadQuery(['id <' => $log['id']])->orderBy('id desc')->findOne();
                if ($l) {
                    $verified[$code] = sha1($l->getExtraData('code').$createtime) == $code;
                } else {
                    $verified[$code] = sha1(App::uid().$createtime) == $code;
                }
            }
        }

        $tpl_data['logs'] = $logs;
        $tpl_data['verified'] = $verified;
        $tpl_data['device'] = $device;

        app()->showTemplate('web/device/payload', $tpl_data);
    } elseif ($op == 'log') {

        $device = Device::get(request('id'));
        if (empty($device)) {
            Util::itoast('找不到这个设备！', $this->createWebUrl('device'), 'error');
        }

        $tpl_data['navs'] = [
            'detail' => $device->getName(),
            'payload' => '库存',
            'log' => '事件',
            //'poll_event' => '最新',
            'event' => '消息',
        ];

        $query = $device->logQuery();
        if (request::isset('way')) {
            $query->where(['level' => request::int('way')]);
        }

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        $total = $query->count();
        if (ceil($total / $page_size) < $page) {
            $page = 1;
        }

        $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

        $query->page($page, $page_size);
        $query->orderBy('id DESC');

        $logs = [];

        /** @var device_logsModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                'imei' => $entry->getTitle(),
                'title' => Device::formatPullTitle($entry->getLevel()),
                'goods' => $entry->getData('goods'),
                'user' => $entry->getData('user'),
            ];

            $data['goods']['img'] = Util::toMedia($data['goods']['img'], true);

            $result = $entry->getData('result');
            if (is_array($result)) {
                $result_data = $result['data'] ?? $result;
                if (isset($result_data['errno'])) {
                    $data['result'] = [
                        'errno' => intval($result_data['errno']),
                        'message' => $result_data['message'],
                    ];
                } else {
                    $data['result'] = [
                        'errno' => -1,
                        'message' => $result_data['message'] ?: '<未知>',
                    ];
                }
                $data['result']['orderUID'] = strval($result_data['orderUID']);
                $data['result']['serialNO'] = strval($result_data['serialNO']);
                $data['result']['timeUsed'] = intval($result_data['timeUsed']);
            } else {
                $data['result'] = [
                    'errno' => empty($result),
                    'message' => empty($result) ? '失败' : '成功',
                ];
            }

            $confirm = $entry->getData('confirm', []);
            if ($confirm) {
                $data['confirm'] = [
                    'errno' => $confirm['result'],
                    'message' => Order::desc($confirm['result']),
                ];
            }

            $order_tid = $entry->getData('order.tid');
            if ($order_tid) {
                $data['memo'] = '订单编号:'.$order_tid;
            }

            $acc = $entry->getData('account');
            if ($acc) {
                $data['memo'] = '公众号:'.$acc['name'];
            }

            $order_id = $entry->getData('order');
            if ($order_id) {
                $order = Order::get($order_id);
                if ($order) {
                    $data['order'] = [
                        'uid' => $order->getOrderNO(),
                    ];
                }
            }

            $logs[] = $data;
        }

        $tpl_data['logs'] = $logs;
        $tpl_data['device'] = $device;

        app()->showTemplate('web/device/log', $tpl_data);
    } elseif ($op == 'event') {

        $device = Device::get(request('id'));
        if (empty($device)) {
            Util::itoast('找不到这个设备！', $this->createWebUrl('device'), 'error');
        }

        $query = $device->eventQuery();

        if (request::isset('event')) {
            $query->where(['event' => request('event')]);
        }

        $detail = request::bool('detail');

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        $total = $query->count();
        if (ceil($total / $page_size) < $page) {
            $page = 1;
        }

        $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

        $query->page($page, $page_size);
        $query->orderBy('id DESC');

        $events = [];
        /** @var device_eventsModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                'event' => $entry->getEvent(),
            ];
            if ($device->isBlueToothDevice()) {
                $data['title'] = $entry->getExtraData('message');
            } else {
                $data['title'] = DeviceEventProcessor::logEventTitle($entry->getEvent());
            }
            if ($detail) {
                $data['extra'] = $entry->getExtra();
            }
            $events[] = $data;
        }

        $tpl_data['navs'] = [
            'detail' => $device->getName(),
            'payload' => '库存',
            'log' => '事件',
            //'poll_event' => '最新',
            'event' => '消息',
        ];

        $tpl_data['events'] = $events;
        $tpl_data['device'] = $device;

        app()->showTemplate('web/device/event', $tpl_data);
    } elseif ($op == 'daystats') {

        $device = Device::get(request('id'));
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }

        $title = date('n月d日');
        $content = app()->fetchTemplate(
            'web/device/stats',
            [
                'chartid' => Util::random(10),
                'title' => $title,
                'chart' => Util::cachedCall(30, function () use ($device, $title) {
                    return Stats::chartDataOfDay($device, new DateTime(), "设备：{$device->getName()}($title)");
                }, $device->getId()),
            ]
        );

        JSON::success(['z' => date('z'), 'content' => $content]);
    } elseif ($op == 'monthstats') {

        $device = Device::get(request::int('id'));
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }

        $month_str = request::str('month');
        try {
            $month = new DateTime($month_str);
        } catch (Exception $e) {
            JSON::fail('时间不正确！');
        }

        $title = $month->format('Y年n月');

        $content = app()->fetchTemplate(
            'web/device/stats',
            [
                'chartid' => Util::random(10),
                'title' => $title,
                'chart' => Util::cachedCall(30, function () use ($device, $month, $title) {
                    return Stats::chartDataOfMonth($device, $month, "设备：{$device->getName()}($title)");
                }, $device->getId(), $month),
            ]
        );

        JSON::success(['title' => '', 'content' => $content]);
    } elseif ($op == 'allstats') {

        //全部出货统计
        $device = Device::get(request::int('id'));
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }

        list($m, $total) = Util::cachedCall(30, function () use ($device) {
            //开始 结束
            $first_order = Order::getFirstOrderOfDevice($device);
            $last_order = Order::getLastOrderOfDevice($device);
            if ($first_order) {
                $first_order_datetime = intval($first_order['createtime']);
            } else {
                $first_order_datetime = time();
            }
            if ($last_order) {
                $last_order_datetime = intval($last_order->getCreatetime());
            } else {
                $last_order_datetime = time();
            }

            $total_num = 0;
            $months = [];
            try {
                $begin = new DateTime(date('Y-m-d H:i:s', $first_order_datetime));
                $end = new DateTime(date('Y-m-d H:i:s', $last_order_datetime));

                $end = $end->modify('last day of this month 00:00');

                $counter = new OrderCounter();

                while ($begin < $end) {
                    $result = $counter->getMonthAll([$device, 'goods'], $begin);
                    $result['month'] = $begin->format('Y-m');
                    $total_num += $result['total'];
                    $months[$begin->format('Y年m月')] = $result;
                    $begin->modify('first day of next month 00:00');
                }

                return [$months, $total_num];

            } catch (Exception $e) {
            }

            return [];
        }, $device->getId());

        $content = app()->fetchTemplate(
            'web/device/all_stats',
            [
                'device' => $device,
                'm_all' => $m,
                'total' => $total,
                'device_id' => $device->getId(),
            ]
        );

        JSON::success(['title' => "<b>{$device->getName()}</b>的出货统计", 'content' => $content]);
    } elseif ($op == 'refresh_all') {

        if (Advertising::notifyAll(['all' => 1])) {
            JSON::success('已通知有设备更新！');
        }

        JSON::fail('通知失败！');
    } elseif ($op == 'clean_error') {

        //清除所有设备的错误代码
        Device::cleanAllErrorCode();

        Util::itoast('清除成功！', $this->createWebUrl('device'), 'success');
    } elseif ($op == 'chartData') {

        $device = Device::get(request('id'));
        $firstMsg = $device->get('firstMsgStatistic', []);

        JSON::success(['first' => $firstMsg]);
    } elseif ($op == 'preRevert') {

        $device = Device::get(request('id'));
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }

        $content = app()->fetchTemplate(
            'web/device/confirm',
            [
                'device_id' => $device->getId(),
                'confirm_code' => $device->getShadowId(),
                'device_name' => $device->getName(),
            ]
        );

        JSON::success(['title' => '注意：重置会删除该设备的所有设置及订单记录，并且无法恢复！', 'content' => $content]);
    } elseif ($op == 'revert') {

        $confirm_code = request::trim('code');
        $device = Device::get(request('id'));
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }

        if ($device->getShadowId() != $confirm_code) {
            JSON::fail('操作失败，确认码不正确！(注意大小写)');
        }

        $res = Util::transactionDo(
            function () use ($device) {
                return $device->resetAllData() ? true : error(State::FAIL, '清除失败！');
            }
        );

        Util::resultJSON(!is_error($res), ['msg' => is_error($res) ? $res['message'] : '重置成功！']);
    } elseif ($op == 'unlock') {

        $id = request::int('id');
        if ($id) {
            $device = Device::get($id);
            if ($device && $device->resetLock()) {
                JSON::success('锁定已解除！');
            }
        }

        JSON::success();
    } elseif ($op == 'confirmLAC') {

        $id = request::int('id');
        if ($id) {
            $device = Device::get($id);
            if ($device && $device->confirmLAC()) {
                JSON::success('已确认！');
            }
        }

        JSON::success();
    } elseif ($op == 'setNormal') {

        $id = request::int('id');
        if ($id) {
            $device = Device::get($id);
            if ($device && $device->updateSettings('extra.isDown', Device::STATUS_NORMAL) && $device->save()) {
                JSON::success('维护状态已取消！');
            }
        }

        JSON::success();
    } elseif ($op == 'refresh') {

        $device = Device::get(request::int('id'));
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }

        if (Device::refresh($device)) {
            JSON::success('设备状态已刷新！');
        }

        JSON::fail('设备状态刷新失败！');
    } elseif ($op == 'report') {

        $device = Device::get(request::int('id'));
        if ($device) {
            $code = $device->getProtocolV1Code();
            if ($code) {
                $device->reportMcbStatus($code);
            }
        }
    } elseif ($op == 'sig') {

        $device = Device::get(request::int('id'));
        if ($device) {
            JSON::success([
                'sig' => "{$device->getSig()}%",
            ]);
        }

        JSON::fail('找不到这个设备');
    } elseif ($op == 'report_list') {

        //设备故障 提交列表

        $tpl_data = [];

        $condition = We7::uniacid([]);
        $query = m('maintenance')->where($condition);

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        $total = $query->count();

        //列表数据
        $data = [];

        $condition = We7::uniacid([]);

        $query = We7::load()->object('query');

        $join = $query
            ->from(m('maintenance')->getTableName(), 'm')
            ->leftjoin(Device::getTableName(), 'd')
            ->on('m.device_id', 'd.imei');

        //搜索关键字
        $keywords = request::trim('keywords');
        if ($keywords) {
            $join->whereor("d.name LIKE", "%$keywords%")
                ->whereor("d.imei LIKE", "%$keywords%")
                ->whereor("d.app_id LIKE", "%$keywords%")
                ->whereor("d.iccid LIKE", "%$keywords%");
        }

        $res = $join
            ->where($condition)
            ->page($page, $page_size)
            ->select('m.*', 'm.name as mname', 'd.name as dname', 'd.imei')
            ->orderby("m.createtime desc")
            ->getAll();

        foreach ($res as $entry) {
            $data[] = [
                'id' => $entry['id'],
                'mobile' => $entry['mobile'],
                'mname' => $entry['mname'],
                'result' => $entry['result'],
                'createtime_formatted' => date('Y-m-d H:i', $entry['createtime']),
                'dname' => is_null($entry['dname']) ? '' : $entry['dname'],
                'imei' => $entry['imei'],
            ];
        }

        $filter = [
            'page' => $page,
            'pagesize' => $page_size,
        ];

        if ($keywords) {
            $filter['keywords'] = $keywords;
        }

        $tpl_data['data'] = $data;
        $tpl_data['filter'] = $filter;
        $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

        app()->showTemplate('web/device/report_list', $tpl_data);
    } elseif ($op == 'group') {
        $query = Group::query(Group::NORMAL);

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        $total = $query->count();
        $total_page = ceil($total / $page_size);

        $query->page($page, $page_size);

        $keywords = request::trim('keywords');
        if ($keywords) {
            $query->where(['title REGEXP' => $keywords]);
        }

        //分配assign.js通过ids获取对应分组数据
        $ids = Util::parseIdsFromGPC();
        if (!empty($ids)) {
            $query->where(['id' => $ids]);
        }

        $result = [
            'page' => $page,
            'total' => $total,
            'totalpage' => $total_page,
            'list' => [],
        ];

        /** @var device_groupsModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $result['list'][] = [
                'id' => $entry->getId(),
                'title' => $entry->getTitle(),
                'clr' => $entry->getClr(),
                'total' => Device::query(['group_id' => $entry->getId()])->count(),
            ];
        }

        $result['serial'] = request::trim('serial') ?: microtime(true).'';

        Util::resultJSON(true, $result);
    } elseif ($op == 'group_search') {

        $query = Group::query(Group::NORMAL);

        $keyword = request::trim('keywords');
        if ($keyword) {
            $query->where(['title REGEXP' => $keyword]);
        }

        $agent_id = request::int('agent');

        if ($agent_id) {
            $agent = Agent::get($agent_id);
            if (empty($agent)) {
                JSON::fail('找不到这个代理商！');
            }
            $query->where(['agent_id' => $agent_id]);
        }

        $result = [];
        /** @var device_groupsModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'title' => $entry->getTitle(),
                'clr' => $entry->getClr(),
                'createtime' => date('Y-m-d H:i', $entry->getCreatetime()),
            ];
            $agent = $entry->getAgent();
            if ($agent) {
                $data['agent'] = $agent->profile();
            }
            $result[] = $data;
        }

        JSON::success($result);
    } elseif ($op == 'new_group') {

        //新分组管理
        $tpl_data = ['op' => $op];

        $navs[$op] = '分组';

        //分组表
        $query = Group::query(Group::NORMAL);

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        if (request::isset('agent_id')) {
            $agent_id = request::int('agent_idd');
            if ($agent_id > 0) {
                $agent = Agent::get($agent_id);
                if (empty($agent)) {
                    Util::itoast('找不到这个代理商！', '', 'error');
                }
                $query->where(['agent_id' => $agent_id]);
            } else {
                $query->where(['agent_id' => 0]);
            }
        }

        $total = $query->count();

        //列表数据
        $query->page($page, $page_size);

        $list = [];
        /** @var device_groupsModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = [
                'id' => $entry->getId(),
                'title' => $entry->getTitle(),
                'clr' => $entry->getClr(),
                'total' => Device::query(['group_id' => $entry->getId()])->count(),
                'createtime_formatted' => date('Y-m-d H:i', $entry->getCreatetime()),
            ];
            $agent = $entry->getAgent();
            if ($agent) {
                $data['agent'] = $agent->profile();
            }
            $list[] = $data;
        }

        $filter = [
            'page' => $page,
            'pagesize' => $page_size,
        ];

        $tpl_data['groups'] = $list;
        $tpl_data['filter'] = $filter;
        $tpl_data['pager'] = We7::pagination($total, $page, $page_size);
        $tpl_data['agentId'] = $agent_id ?? null;
        $tpl_data['navs'] = $navs;

        app()->showTemplate('web/device/new_group', $tpl_data);
    } elseif ($op == 'new_group_add') {

        $tpl_data['op'] = $op;
        $tpl_data['clr'] = Util::randColor();

        app()->showTemplate('web/device/new_group', $tpl_data);

    } elseif ($op == 'new_group_edit') {

        $id = request::int('id');
        $tpl_data['id'] = $id;

        /** @var device_groupsModelObj $one */
        $one = Group::get($id);
        if (empty($one)) {
            Util::itoast('分组不存在！', $this->createWebUrl('device', ['op' => 'new_group']), 'error');
        }

        $tpl_data['group'] = [
            'title' => $one->getTitle(),
            'clr' => $one->getClr(),
        ];

        $agent = $one->getAgent();
        if (!empty($agent)) {
            $tpl_data['agent'] = [
                'id' => $agent->getId(),
                'name' => $agent->getName(),
                'mobile' => $agent->getMobile(),
            ];
        }

        app()->showTemplate('web/device/new_group', $tpl_data);
    } elseif ($op == 'new_group_save') {

        $title = request::trim('title');
        $clr = request('clr');
        $agent_id = request::int('agentId');

        $id = request::int('id') ?: time();

        /** @var device_groupsModelObj $one */
        $one = Group::get($id);
        if ($one) {
            $one->setTitle($title);
            $one->setClr($clr);
            $one->setAgentId($agent_id);
        } else {
            $one = Group::create([
                'type_id' => Group::NORMAL,
                'agent_id' => $agent_id,
                'title' => $title,
                'clr' => $clr,
                'createtime' => time(),
            ]);
        }

        if ($one->save()) {
            Util::itoast('保存成功！', $this->createWebUrl('device', ['op' => 'new_group']), 'success');
        }

        Util::itoast('保存失败！', $this->createWebUrl('device', ['op' => 'new_group']), 'error');
    } elseif ($op == 'new_group_remove') {

        $id = request::int('id');
        $group = Group::get($id);

        if ($group && $group->destroy()) {
            $result = Device::query(['group_id' => $id])->findAll();

            /** @var deviceModelObj $entry */
            foreach ($result as $entry) {
                $entry->setGroupId(0);
                //更新广告
                $entry->updateScreenAdvsData();
                //更新公众号
                $entry->updateAccountData();
            }

            Util::itoast('删除成功！', $this->createWebUrl('device', ['op' => 'new_group']), 'success');
        }

        Util::itoast('删除失败！', $this->createWebUrl('device', ['op' => 'new_group']), 'error');
    } elseif ($op == 'maintain_record') {

        $is_export = request('is_export');

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        $device_id = request::int('device_id');

        $date_limit = request::array('datelimit');
        if ($date_limit['start']) {
            $s_date = new DateTime($date_limit['start'].'00:00');
        } else {
            $s_date = new DateTime('first day of this month 00:00');
        }

        if ($date_limit['end']) {
            $e_date = new DateTime($date_limit['end'].' 00:00');
        } else {
            $e_date = new DateTime('first day of next month 00:00');
        }

        $agent_openid = request::str('agent_openid');
        $nickname = request::trim('nickname');

        $user_ids = [];
        if ($nickname != '') {
            $user_res = User::query()->whereOr([
                'nickname LIKE' => "%$nickname%",
                'mobile LIKE' => "%$nickname%",
            ])->findAll();
            foreach ($user_res as $item) {
                $user_ids[] = $item->getId();
            }
        }

        $device_ids = [];
        if (!empty($agent_openid)) {
            $agent = Agent::get($agent_openid, true);
            if ($agent) {
                $device_res = Device::query(['agent_id' => $agent->getId()])->findAll();
                foreach ($device_res as $item) {
                    $device_ids[] = $item->getId();
                }
            }
        }

        $condition = [
            'createtime >=' => $s_date->getTimestamp(),
            'createtime <' => $e_date->getTimestamp(),
        ];

        if (!empty($device_id)) {
            $condition['device_id'] = $device_id;
        }

        $cate = request::int('cate');
        if (!empty($cate)) {
            $condition['cate'] = $cate;
        }

        $query = m('device_record')->query($condition);
        if ($nickname != '') {
            if (empty($user_ids)) {
                $query->where('id = -1');
            } else {
                $user_ids = array_unique($user_ids);
                $query->where(['user_id' => $user_ids]);
            }
        }

        if (!empty($agent_openid)) {
            if (empty($device_ids)) {
                $query->where('id = -1');
            } else {
                $device_ids = array_unique($device_ids);
                $query->where(['device_id' => $device_ids]);
            }
        }

        if ($is_export) {
            $query->orderBy('id DESC');
            $res = $query->findAll();
        } else {
            $total = $query->count();

            $query->orderBy('id DESC');
            $res = $query->page($page, $page_size)->findAll();
        }

        $data = [];
        $user_ids = [];
        $device_ids = [];

        /** @var  device_recordModelObj $item */
        foreach ($res as $item) {
            $arr = [
                'id' => $item->getId(),
                'deviceId' => $item->getDeviceId(),
                'userId' => $item->getUserId(),
                'cate' => $item->getCate(),
                'createtime' => date('Y-m-d H:i:s', $item->getCreatetime()),
            ];

            $data[] = $arr;
            $user_ids[] = $item->getUserId();
            $device_ids[] = $item->getDeviceId();
        }

        $user_assoc = [];
        if (!empty($user_ids)) {
            $user_ids = array_unique($user_ids);
            $user_res = User::query()->where(['id' => $user_ids])->findAll();
            /** @var userModelObj $item */
            foreach ($user_res as $item) {
                $user_assoc[$item->getId()] = $item->getNickname();
            }
        }

        $device_assoc = [];
        $device_agent_assoc = [];
        $agent_ids = [];
        if (!empty($device_ids)) {
            $device_ids = array_unique($device_ids);
            $device_res = Device::query()->where(['id' => $device_ids])->findAll();
            /** @var deviceModelObj $item */
            foreach ($device_res as $item) {
                $device_assoc[$item->getId()] = $item->getName().', '.$item->getImei();
                $device_agent_assoc[$item->getId()] = $item->getAgentId();
                $agent_ids[] = $item->getAgentId();
            }
        }

        $agent_assoc = [];
        $agent_assoc[0] = '平台';
        if (!empty($agent_ids)) {
            $agent_ids = array_unique($agent_ids);
            $agent_res = m('agent_vw')->where('id IN('.implode(',', $agent_ids).')')->findAll();
            foreach ($agent_res as $item) {
                $agent_assoc[$item->getId()] = $item->getNickname();
            }
        }

        $rec_type = [
            '1' => '开门记录',
            '2' => '消毒记录',
            '3' => '换电池记录',
        ];

        if ($is_export) {
            $title = [
                '设备名称',
                '代理商',
                '操作人员',
                '类型',
                '日期',
            ];
            $e_data = [];
            foreach ($data as $item) {
                $e_data[] = [
                    $device_assoc[$item['deviceId']],
                    $agent_assoc[$device_agent_assoc[$item['deviceId']]],
                    $user_assoc[$item['userId']],
                    $rec_type[$item['cate']],
                    $item['createtime'],
                ];
            }

            Util::exportExcel('维护记录', $title, $e_data);
            exit();
        } else {

            $tpl_data['s_date'] = $s_date;
            $tpl_data['e_date'] = $e_date;
            $tpl_data['nickname'] = $nickname;
            $tpl_data['device_id'] = $device_id;
            $tpl_data['open_id'] = $agent_openid;
            $tpl_data['cate'] = $cate;

            $tpl_data['data'] = $data;
            $tpl_data['user_assoc'] = $user_assoc;
            $tpl_data['device_assoc'] = $device_assoc;
            $tpl_data['device_agent_assoc'] = $device_agent_assoc;
            $tpl_data['agent_assoc'] = $agent_assoc;

            $tpl_data['rec_type'] = $rec_type;

            $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

            app()->showTemplate('web/device/record', $tpl_data);
        }
    } else {
        if ($op == 'feed_back') {

            $date_limit = request::array('datelimit');
            if ($date_limit['start']) {
                $s_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['start'].' 00:00:00');
            } else {
                $s_date = new DateTime('first day of this month 00:00:00');
            }

            if ($date_limit['end']) {
                $e_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['end'].' 00:00:00');
                $e_date->modify('next day');
            } else {
                $e_date = new DateTime('first day of next month 00:00:00');
            }

            $condition = [
                'createtime >=' => $s_date->getTimestamp(),
                'createtime <' => $e_date->getTimestamp(),
            ];

            $device_id = request::int('device_id');

            if (!empty($device_id)) {
                $condition['device_id'] = $device_id;
            }

            $query = m('device_feedback')->query($condition);

            $page = max(1, request::int('page'));
            $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

            $total = $query->count();

            $query->orderBy('id DESC');
            $query->page($page, $page_size);

            $data = [];
            /** @var device_feedbackModelObj $item */
            foreach ($query->findAll() as $item) {
                $pics = unserialize($item->getPics());
                if ($pics === false) {
                    $pics = [];
                } else {
                    foreach ($pics as $index => $pic) {
                        $pics[$index] = Util::toMedia($pic);
                    }
                }

                $arr = [
                    'id' => $item->getId(),
                    'text' => $item->getText(),
                    'pics' => $pics,
                    'remark' => $item->getRemark(),
                    'createtime' => date('Y-m-d H:i:s', $item->getCreatetime()),
                ];

                $user = User::get($item->getUserId());
                if ($user) {
                    $arr['user'] = $user->profile();
                }

                $device = Device::get($item->getDeviceId());
                if ($device) {
                    $arr['device'] = [
                        'imei' => $device->getImei(),
                        'name' => $device->getName(),
                    ];
                    $agent = $device->getAgent();
                    if ($agent) {
                        $arr['agent'] = $agent->profile();
                    }
                }

                $data[] = $arr;
            }

            $tpl_data['s_date'] = $s_date->format('Y-m-d');
            $tpl_data['e_date'] = $e_date->modify('-1 day')->format('Y-m-d');
            $tpl_data['device_id'] = $device_id;
            $tpl_data['data'] = $data;
            $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

            app()->showTemplate('web/device/feedback', $tpl_data);
        } else {
            if ($op == 'deal_fb') {

                //处理反馈
                $id = request::int('id');
                $remark = request::trim('remark');
                if (empty($remark)) {
                    JSON::fail('请输入处理内容！');
                }

                /** @var device_feedbackModelObj $res */
                $res = m('device_feedback')->findOne(['id' => $id]);

                if ($res) {
                    $res->setRemark($remark);
                    $res->save();

                    JSON::success(['id' => $id, 'remark' => $remark]);
                } else {
                    JSON::fail('error');
                }
            } else {
                if ($op == 'add_fb') {

                    $id = request::int('id');

                    /** @var device_feedbackModelObj $res */
                    $res = m('device_feedback')->findOne(['id' => $id]);
                    if ($res) {
                        if ($res->getRemark() != '') {
                            JSON::fail('已处理该反馈！');
                        }
                    } else {
                        JSON::fail('找不到该记录！');
                    }

                    $content = app()->fetchTemplate(
                        'web/device/deal_fb',
                        [
                            'chartid' => Util::random(10),
                            'id' => $res->getId(),
                            'text' => $res->getText(),
                        ]
                    );

                    JSON::success(['content' => $content]);
                } else {
                    if ($op == 'import_bluetooth_device_upload') {

                        $tpl_data = [];
                        app()->showTemplate('web/device/bluetooth_upload', $tpl_data);
                    } else {
                        if ($op == 'create_bluetooth_device') {

                            $data = [
                                'agent_id' => request('agent'),
                                'name' => request('name'),
                                'imei' => request('imei'),
                                'group_id' => request('groupId'),
                                'capacity' => 0,
                                'remain' => 0,
                            ];

                            if (empty($data['name']) || empty($data['imei'])) {
                                JSON::fail('设备名称或IMEI不能为空！');
                            }

                            if (Device::get($data['imei'], true)) {
                                JSON::fail('IMEI已经存在！');
                            }

                            $extra = [
                                'pushAccountMsg' => '',
                                'activeQrcode' => 0,
                                'grantloc' => [
                                    'lng' => 0,
                                    'lat' => 0,
                                ],
                                'location' => [],
                            ];

                            $protocol = strtolower(request('protocol'));
                            if (!in_array($protocol, ['wx', 'grid'])) {
                                $protocol = 'wx';
                            }

                            $blue_tooth_screen = empty(request('screen')) ? 0 : 1;
                            $power = empty(request('power')) ? 0 : 1;
                            $blue_tooth_disinfectant = empty(request('disinfect')) ? 0 : 1;

                            $extra['bluetooth'] = [
                                'protocol' => $protocol,
                                'uid' => strval(request('buid')),
                                'mac' => strval(request('mac')),
                                'screen' => $blue_tooth_screen,
                                'power' => $power,
                                'disinfectant' => $blue_tooth_disinfectant,
                            ];

                            if ($data['agent_id']) {
                                $agent = Agent::get($data['agentId']);
                                if (empty($agent)) {
                                    JSON::fail('找不到这个代理商!');
                                }
                            }

                            $type_id = request('typeid');
                            $device_type = DeviceTypes::get($type_id);
                            if (empty($device_type)) {
                                JSON::fail('设备类型不正确!');
                            }

                            $type_data = DeviceTypes::format($device_type);

                            $data['device_type'] = $type_data['id'];
                            $extra['cargo_lanes'] = $type_data['cargo_lanes'];

                            $device = Device::create($data);

                            if (empty($device)) {
                                JSON::fail('创建失败！');
                            }

                            $device->setDeviceModel(Device::BLUETOOTH_DEVICE);
                            $device->updateQrcode(true);

                            if ($device->set('extra', $extra) && $device->save()) {
                                JSON::success(['message' => '成功']);
                            }

                            JSON::fail('无法保存数据！');
                        } elseif ($op == 'poll_event') {

                            $device = Device::get(request('id'));
                            if (empty($device)) {
                                Util::itoast('找不到这个设备！', $this->createWebUrl('device'), 'error');
                            }

                            $query = $device->eventQuery();

                            $query->where(['event' => [14, 20]]);
                            $query->orderBy('id DESC');
                            $query->limit(10);

                            $events = [];
                            $the_first_id = 0;

                            /** @var device_eventsModelObj $item */
                            foreach ($query->findAll() as $item) {
                                if (!$the_first_id) {
                                    $the_first_id = $item->getId();
                                }

                                $extra = json_decode($item->getExtra(), true);
                                $extra = $extra['extra'];

                                $arr = [];
                                $arr['id'] = $item->getId();
                                $arr['time'] = date('H:i:s', $item->getCreatetime());
                                if ($item->getEvent() == 14) {
                                    $rssi = $extra['RSSI'] ?: 0;
                                    $per = floor($rssi * 100 / 31);
                                    $iccid = $extra['ICCID'] ?: '';

                                    $arr['type'] = 14;
                                    $arr['per'] = $per;
                                    $arr['iccid'] = $iccid;
                                }

                                if ($item->getEvent() == 20) {
                                    $sw = $extra['sw'] ?: [];
                                    $f_sw = [];
                                    foreach ($sw as $val) {
                                        if ($val == 1) {
                                            $f_sw[] = '工作';
                                        } else {
                                            $f_sw[] = '待机';
                                        }
                                    }

                                    $door = $extra['door'] ?: [];
                                    $f_door = [];
                                    foreach ($door as $val) {
                                        if ($val == 1) {
                                            $f_door[] = '关';
                                        } else {
                                            $f_door[] = '开';
                                        }
                                    }

                                    $arr['type'] = 20;
                                    $arr['sw'] = $f_sw;
                                    $arr['door'] = $f_door;
                                    $arr['temperature'] = $extra['temperature'];
                                    $arr['weights'] = $extra['weights'];
                                }

                                $events[] = $arr;
                            }

                            $tpl_data['navs'] = [
                                'detail' => $device->getName(),
                                'log' => '日志',
                                //'poll_event' => '最新',
                                'event' => '事件',
                            ];

                            $tpl_data['device'] = $device;
                            $tpl_data['events'] = $events;
                            $tpl_data['the_first_id'] = $the_first_id;

                            app()->showTemplate('web/device/poll_event', $tpl_data);
                        } elseif ($op == 'new_event') {

                            $device = Device::get(request('id'));
                            if (empty($device)) {
                                Util::itoast('找不到这个设备！', $this->createWebUrl('device'), 'error');
                            }

                            $query = $device->eventQuery();

                            $the_first_id = request('the_first_id') ?: 0;

                            $query->where(['event' => [14, 20]]);
                            $query->where(['id >' => $the_first_id]);

                            $res = $query->findAll();
                            if (count($res) == 0) {
                                echo json_encode([]);
                            } else {
                                $events = [];
                                /** @var device_eventsModelObj $item */
                                foreach ($res as $item) {
                                    $extra = json_decode($item->getExtra(), true);
                                    $extra = $extra['extra'];

                                    $arr = [];
                                    $arr['id'] = $item->getId();
                                    $arr['time'] = date('H:i:s', $item->getCreatetime());

                                    if ($item->getEvent() == 14) {
                                        $rssi = $extra['RSSI'] ?: 0;
                                        $per = floor($rssi * 100 / 31);
                                        $iccid = $extra['ICCID'] ?: '';

                                        $arr['type'] = 14;
                                        $arr['per'] = $per;
                                        $arr['iccid'] = $iccid;
                                    }
                                    if ($item->getEvent() == 20) {
                                        $sw = $extra['sw'] ?: [];
                                        $f_sw = [];
                                        foreach ($sw as $val) {
                                            if ($val == 1) {
                                                $f_sw[] = '工作';
                                            } else {
                                                $f_sw[] = '待机';
                                            }
                                        }
                                        $door = $extra['door'] ?: [];
                                        $f_door = [];
                                        foreach ($door as $val) {
                                            if ($val == 1) {
                                                $f_door[] = '关';
                                            } else {
                                                $f_door[] = '开';
                                            }
                                        }
                                        $arr['type'] = 20;
                                        $arr['sw'] = $f_sw;
                                        $arr['door'] = $f_door;
                                        $arr['temperature'] = $extra['temperature'];
                                        $arr['weights'] = $extra['weights'];
                                    }
                                    $events[] = $arr;
                                }
                                echo json_encode($events);
                            }
                        } elseif ($op == 'qrcode_download') {

                            //简单的二维码导出功能
                            $url_prefix = We7::attachment_set_attach_url();
                            $attach_prefix = ATTACHMENT_ROOT;

                            $zip = new ZipArchive();
                            $file_name = time().'_'.rand().'.zip';
                            $file_path = $attach_prefix.$file_name;
                            $zip->open($file_path, ZipArchive::CREATE);   //打开压缩包

                            $ids = request::array('ids', []);
                            $query = Device::query(['id' => $ids]);

                            /** @var deviceModelObj $device */
                            foreach ($query->findAll() as $device) {
                                $file_real = str_replace($url_prefix, $attach_prefix, $device->getQrcode());
                                $file_real = preg_replace('/\?.*/', '', $file_real);
                                if (file_exists($file_real)) {
                                    $zip->addFile($file_real, basename($file_real));
                                }
                            }

                            $zip->close();

                            JSON::success(['url' => Util::toMedia($file_name)]);

                        } elseif ($op == 'card_status') {

                            $iccid = request::str('iccid');
                            if (empty($iccid)) {
                                JSON::fail('错误：iccid 为空！');
                            }

                            $result = CtrlServ::v2_query("iccid/$iccid");
                            if (is_error($result)) {
                                JSON::fail($result);
                            }

                            if (!$result['status']) {
                                JSON::fail($result['data']['message'] ?? '查询失败！');
                            }

                            $card = $result['data'] ?? [];
                            if (empty($card)) {
                                JSON::fail('查询失败，请稍后再试！');
                            }

                            $status_title = [
                                "00" => '正常使用',
                                "10" => '测试期',
                                "02" => '停机',
                                "03" => '预销号',
                                "04" => '销号',
                                "11" => '沉默期',
                                "12" => '停机保号',
                                "99" => '未知',
                            ];

                            $card['status'] = $status_title[$card['account_status']] ?? '未知';

                            $content = app()->fetchTemplate(
                                'web/device/card_status',
                                [
                                    'card' => $card,
                                ]
                            );
                            JSON::success([
                                'title' => "流量卡状态",
                                'content' => $content,
                            ]);

                        } elseif ($op == 'openDoor') {

                            if (!App::isDeviceWithDoorEnabled()) {
                                JSON::fail('没有启用这个功能！');
                            }

                            $id = request::int('id');
                            $device = Device::get($id);
                            if (empty($device)) {
                                JSON::fail('找不到这个设备！');
                            }

                            $index = request::int('index', 1);

                            $result = $device->openDoor($index);
                            if (is_error($result)) {
                                JSON::fail($result);
                            }

                            JSON::success('开锁指令已发送！');

                        } elseif ($op == 'upload_info') {

                            $config = settings('device.upload', []);
                            if (empty($config['url'])) {
                                JSON::fail('没有配置第三方平台！');
                            }

                            if (Job::uploadDevieInfo()) {
                                JSON::success('已启动设备上传任务！');
                            }

                            JSON::fail('设备信息上传任务启动失败！');

                        }
                    }
                }
            }
        }
    }
}
