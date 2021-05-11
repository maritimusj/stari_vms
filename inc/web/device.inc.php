<?php

/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;
use DateTimeImmutable;
use Exception;
use zovye\model\agent_vwModelObj;
use zovye\model\device_eventsModelObj;
use zovye\model\device_feedbackModelObj;
use zovye\model\device_groupsModelObj;
use zovye\model\device_logsModelObj;
use zovye\model\device_recordModelObj;
use zovye\model\deviceModelObj;
use zovye\model\tagsModelObj;
use zovye\model\userModelObj;
use zovye\model\versionModelObj;

$op = request::op('default');

$tpl_data = [
    'op' => $op,
];

if ($op == 'list') {
    $query = Device::query();

    //指定代理商
    if (request::isset('agent_id')) {
        $agent_id = request::int('agent_id');
        if ($agent_id == 0) {
            $query->where(['agent_id' => 0]);
        } else {
            $agent = Agent::get($agent_id);
            if (empty($agent)) {
                JSON::fail('找不到这个代理商！');
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
                JSON::fail('找不到这个分组！');
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
                JSON::fail('找不到这个型号！');
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
        $query->where("(appId IS NULL OR appId='')");
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

    JSON::success($devices);
} else if ($op == 'default') {

    if (!request::is_ajax()) {
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

        $this->showTemplate('web/device/default_new', $tpl_data);
    }

    //分配设备控件查询设备详情
    if (request::has('id')) {
        $device = Device::get(request::int('id'));
        if ($device) {
            $data = [
                'id' => intval($device->getId()),
                'name' => strval($device->getName()),
                'IMEI' => strval($device->getImei()),
                'appId' => strval($device->getAppId()),
            ];
            $agent = $device->getAgent();
            if ($agent) {
                $data['agent'] = $agent->getName();
            }
            JSON::success($data);
        }

        JSON::fail('没有找到这个设备');
    }

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

    $order_by = request::str('orderby');
    if ($order_by && in_array($order_by, ['id', 'createtime', 'imei', 'agentId', 'groupId', 'd_total', 'm_total'])) {
        $order = in_array(strtolower(request('order')), ['desc', 'asc']) ? request('order') : 'desc';
    } else {
        $order_by = 'createtime';
        $order = 'DESC';
    }

    $query = Device::query();

    //搜索指定ID
    if (request::has('ids')) {
        if (is_string(request('ids'))) {
            $ids = explode(',', request::str('ids'));
        } elseif (is_array((request('ids')))) {
            $ids = request::array('ids');
        } else {
            $ids = [request::int('ids')];
        }
        foreach ($ids as $index => $id) {
            $id = intval($id);
            if ($id > 0) {
                $ids[$index] = $id;
            }
        }
        if ($ids) {
            $query->where(['id' => $ids]);
        }
    }

    //指定分组
    if (request::isset('groupId')) {
        $group_id = request::int('groupId');
        if ($group_id > 0) {
            $group = Group::get($group_id);
            if (empty($group)) {
                Util::itoast('找不到这个分组！', $this->createWebUrl('device'), 'error');
            }
            $query->where(['group_id' => $group_id]);
        } else {
            $query->where(['group_id' => 0]);
        }
    }

    //指定代理商
    $agent_id = request::int('agentId');
    if ($agent_id) {
        $agent = Agent::get($agent_id);
        if ($agent) {
            $query->where(['agent_id' => $agent_id]);
        }
    }

    //搜索关键字
    $keywords = request::trim('keywords');
    if ($keywords) {
        $query->whereOr([
            'name LIKE' => "%{$keywords}%",
            'imei LIKE' => "%{$keywords}%",
            'app_id LIKE' => "%{$keywords}%",
            'iccid LIKE' => "%{$keywords}%",
        ]);
    }

    //指定tags
    if (request::has('tagids')) {
        $tag_ids = request::is_array('tagids') ? request::array('tagids') : [request::int('tagids')];
        /** @var tagsModelObj $tag */
        foreach (m('tags')->where(We7::uniacid(['id' => $tag_ids]))->findAll() as $tag) {
            $query->where("tags_data REGEXP '<{$tag->getId()}>'");
        }
    }

    //只显示问题设备
    if (request::bool('errorDevice')) {
        $query->where(['error_code <>' => 0]);
    }

    //只显示缺货设备
    if (request::bool('lowRemain')) {
        $remainWarning = intval(settings('device.remainWarning', 1));
        $query->where(['remain <' => $remainWarning]);
    }

    //只显绑定有APP设备
    if (request::isset('unreg')) {
        if (request::bool('unreg')) {
            $query->where("(app_id IS NULL OR app_id='')");
            $filter['unreg'] = 1;
        } else {
            $query->where("(app_id IS NOT NULL AND app_id<>'')");
        }
    }

    if (request::isset('types')) {
        $query->where(['device_type' => request::int('types')]);
    }

    $query->page($page, $page_size);
    $query->orderBy("{$order_by} {$order}");

    $devices = [];

    /** @var deviceModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $data = [
            'id' => intval($entry->getId()),
            'name' => strval($entry->getName()),
            'IMEI' => strval($entry->getImei()),
            'appId' => strval($entry->getAppId()),
        ];
        $res = $entry->getAgent();
        if ($res) {
            $data['agent'] = $res->getName();
        }
        $devices['list'][] = $data;
    }

    $devices['serial'] = request::str('serial') ?: strval(microtime(true));
    JSON::success($devices);
    
} elseif ($op == 'search') {

    $result = [];

    $query = Device::query();

    $openid = trim(urldecode(request('openid')));
    if ($openid) {
        $agent = Agent::get($openid, true);
        if ($agent) {
            $query->where(['agent_id' => $agent->getId()]);
        }
    }

    $keyword = trim(urldecode(request('keyword')));
    if ($keyword) {
        $query->whereOr([
            'imei LIKE' => "%{$keyword}%",
            'name LIKE' => "%{$keyword}%",
        ]);
    }

    if (request::has('page')) {
        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

        $total = $query->count();
        $total_page = ceil($total / $page_size);
        if ($page > $total_page) {
            $page = 1;
        }
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
        $devices_status = Util::cachedCall(10, function() use($ids_str) {            
            $res = CtrlServ::v2_query('detail', [], $ids_str, 'application/json');
            if (!empty($res) && $res['status'] === true && is_array($res['data'])) {
                return $res['data'];
            }
            return [];
        }, $ids_str);

        /** @var deviceModelObj $entry */
        foreach ($devices as $entry) {
            $data = [
                'id' => intval($entry->getId()),
                'status' => [
                    'mcb' => false,
                    'app' => empty($entry->getAppId()) ? null : false,
                ],
            ];

            if ($entry->isVDevice() || $entry->isBlueToothDevice()) {
                $data['status']['mcb'] = true;
                $data['status']['app'] = true;
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

    $result = Util::cachedCall(60, function() use($device) {
        return $device->getPullStats();
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
                'id' => intval($entry->getId()),
                'name' => $entry->getName(),
                'IMEI' => $entry->getImei(),
                'ICCID' => $entry->getIccid(),
                'qrcode' => $entry->getQrcode(),
                'model' => $entry->getDeviceModel(),
                'activeQrcode' => $entry->isActiveQrcodeEnabled(),
                'getUrl' => $entry->getUrl(),
                'sig' => $entry->getSig(),
                'qoe' => $entry->getQoe(),
                'capacity' => intval($entry->getCapacity()),
                'remain' => intval($entry->getRemainNum()),
                'reset' => $entry->getReset(),
                'lastError' => $entry->getLastError(),
                'lastOnline' => $entry->getLastOnline() ? date('Y-m-d H:i:s', $entry->getLastOnline()) : '',
                'lastPing' => $entry->getLastPing() ? date('Y-m-d H:i:s', $entry->getLastPing()) : '',
                'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                'lockedtime' => $entry->isLocked() ? date('Y-m-d H:i:s', $entry->getLockedTime()) : '',
                'appId' => $entry->getAppId(),
                'appVersion' => $entry->getAppVersion(),
                'total' => [
                    'month' => intval($entry->getMTotal(['total'])),
                    'today' => intval($entry->getDTotal(['total'])),
                ],
                'gettype' => [
                    'freeLimitsReached' => $entry->isFreeLimitsReached(),
                    'location' => $entry->needValidateLocation(),
                ],
                'address' => $entry->getAddress(),
                'isDown' => $entry->settings('extra.isDown', 0),
            ];

            $groupId = $entry->getGroupId();
            if ($groupId > 0) {
                $group = Group::get($groupId);
                if ($group) {
                    $data['group'] = $group->format();
                }
            }

            $accounts = $entry->getAccounts();
            if ($accounts) {
                $data['gettype']['free'] = true;
            }

            $payload = $entry->getPayload();
            $low_price = 0;
            $high_price = 0;

            foreach ((array)$payload['cargo_lanes'] as $lane) {
                $goods_data = Goods::data($lane['goods'], ['useImageProxy' => true]);
                if ($goods_data && $goods_data['allowPay']) {
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
                $data['gettype']['price'] = number_format($low_price / 100, 2) . '-' . number_format($high_price / 100, 2);
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
            $data = array_merge($data, $entry->getPayload(true));

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
    $group_res = Group::query()->findAll();
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

        $tpl_data['disp'] = $device->hasMcbDisp();

        $extra = $device->get('extra');

        $loc = empty($extra['location']) ? [] : $extra['location'];
        $tpl_data['loc'] = $loc;

        if ($loc['lng'] && $loc['lat']) {
            $baidu_loc = Util::convert2Baidu($loc['lng'], $loc['lat']);
            if ($baidu_loc) {
                $tpl_data['baidu_loc'] = $baidu_loc;
                $device->updateSettings('extra.location.baidu', $baidu_loc);
            }
        }

        $tpl_data['device'] = $device;
        $tpl_data['extra'] = $extra;

        if ($device->isBlueToothDevice()) {
            $tpl_data['bluetooth'] = $device->settings('extra.bluetooth', []);
        }

        $agent = $device->getAgent();

        $tpl_data['agent'] = $agent;
        $tpl_data['allowWxPay'] = settings('purchase.enabled') && (empty($agent) || $agent->settings('agentData.purchase.enabled'));
    } else {
        if ($op == 'add_vd') {
            $tpl_data['vd_imei'] = 'V' . Util::random(15, true);
        }
        $tpl_data['allowWxPay'] = settings('purchase.enabled');
    }

    if ($op == 'add_vd' || (isset($device) && $device->isVDevice())) {
        $tpl_data['device_model'] = Device::VIRTUAL_DEVICE;
    } elseif ($op == 'add_bluetooth_device' || (isset($device) && $device->isBlueToothDevice())) {
        $tpl_data['device_model'] = Device::BLUETOOTH_DEVICE;
    } else {
        $tpl_data['device_model'] = Device::NORMAL_DEVICE;
    }

    $tpl_data['bluetooth']['protocols'] = BlueToothProtocol::all();

    $tpl_data['id'] = $id;
    $tpl_data['device_types'] = $device_types;

    if (App::isMoscaleEnabled()) {
        $tpl_data['moscaleMachineKey'] = is_array($extra) ? strval($extra['moscale']['key']) : '';
        $tpl_data['moscaleLabelList'] = MoscaleAccount::getLabelList();
        $tpl_data['moscaleAreaListSaved'] = is_array($extra) ? $extra['moscale']['label'] : [];
        $tpl_data['moscaleRegionData'] = MoscaleAccount::getRegionData();
        $tpl_data['moscaleRegionSaved'] = is_array($extra) ? $extra['moscale']['region'] : [];
    }

    $this->showTemplate('web/device/edit_new', $tpl_data);
} elseif ($op == 'deviceTestAll') {

    $device = Device::get(request('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $content = $this->fetchTemplate(
        'web/device/cargo_lanes_test',
        [
            'is_vd' => $device->isVDevice(),
            'device_id' => $device->getId(),
            'params' => $device->getPayload(true),
        ]
    );

    JSON::success([
        'title' => "设备测试 [ {$device->getName()} ]",
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

    if (!request::isset('lane') || request::str('lane') == 'all') {
        $data = [];
    } else {
        $data = [
            request::int('lane') => request::int('num'),
        ];
    }

    $device->resetPayload($data);
    $device->updateRemain();
    $device->save();

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
                    CtrlServ::appNotify($app_id);
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

    //通知实体设备
    $device->appNotify();

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
        'isDown' => request::bool('isDown') ? 1 : 0,
        'activeQrcode' => request::bool('activeQrcode') ? 1 : 0,
        'address' => request::trim('address'),
        'grantloc' => [
            'lng' => floatval(request('location')['lng']),
            'lat' => floatval(request('location')['lat']),
        ],
        'txt' => [request::trim('first_txt'), request::trim('second_txt'), request::trim('third_txt')],
    ];

    if (App::isMustFollowAccountEnabled()) {
        $extra['mfa'] = [
            'enable' => request::int('mustFollow'),
        ];
    }

    if (App::isMoscaleEnabled()) {
        $extra['moscale'] = [
            'key' => request::trim('moscaleMachineKey'),
            'label' => array_map(function($e) { return intval($e);}, explode(',', request::trim('moscaleLabel'))),
            'region' => [
                'province' => request::int('province_code'),
                'city' => request::int('city_code'),
                'area' => request::int('area_code'),
            ],
        ];
    }

    $location = request::array('location');
    $lng = $location['lng'];
    $lat = $location['lat'];

    if ($lng && $lat) {
        $res = Util::convert2Tencent($lng, $lat);
        if ($res) {
            $extra['location']['lng'] = floatval($res['lng']);
            $extra['location']['lat'] = floatval($res['lat']);
            $addr = Util::getLocation($extra['location']['lng'], $extra['location']['lat']);
            if ($addr) {
                $extra['location']['area'] = [
                    $addr['province'],
                    $addr['city'],
                    $addr['district'],
                ];
                $extra['location']['address'] = $addr['address'];
            }
        }
    } else {
        //清除位置信息
        $extra['location'] = [];
    }

    if (empty($data['name']) || empty($data['imei'])) {
        Util::itoast('设备名称或IMEI不能为空！', We7::referer(), 'error');
    }

    $type_id = request::int('deviceType');
    if ($type_id) {
        $device_type = DeviceTypes::get($type_id);
        if (empty($device_type)) {
            Util::itoast('设备类型不正确！', We7::referer(), 'error');
        }
    }

    $data['device_type'] = $type_id;

    if (App::isBluetoothDeviceSupported() && request('device_model') == Device::BLUETOOTH_DEVICE) {
        $extra['bluetooth'] = [
            'protocol' => request('blueToothProtocol'),
            'uid' => request('BUID'),
            'mac' => request('MAC'),
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
            Util::itoast('找不到这个代理商！', We7::referer(), 'error');
        }

        $data['agent_id'] = $agent->getId();
    }

    if ($id) {
        $device = Device::get($id);
        if (empty($device)) {
            Util::itoast('设备不存在！', $this->createWebUrl('device'), 'error');
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
            Util::itoast('创建失败！', We7::referer(), 'error');
        }

        $model = request('device_model');

        $device->setDeviceModel($model);

        if ($model == Device::NORMAL_DEVICE) {
            $activeRes = Util::activeDevice($device->getImei());
        }

        //绑定appId
        $device->updateAppId();
    }

    if (empty($type_id)) {
        $device->setDeviceType(0);
        $device_type = DeviceTypes::from($device);

        $cargo_lanes = [];
        $capacities = request::array('capacities');
        foreach (request::array('goods') as $index => $goods_id) {
            $cargo_lanes[] = [
                'goods' => intval($goods_id),
                'capacity' => intval($capacities[$index]),
            ];
        }

        $device_type->setExtraData('cargo_lanes', $cargo_lanes);
        $device_type->save();
    }

    if (empty($device_type)) {
        Util::itoast('获取型号失败！', We7::referer(), 'error');
    }

    $type_data = DeviceTypes::format($device_type);
    $cargo_lanes = [];
    foreach ($type_data['cargo_lanes'] as $index => $lane) {
        $cargo_lanes["l{$index}"] = [
            'num' => max(0, request::int("lane{$index}_num")),
        ];
        if ($device_type->getDeviceId() == $device->getId()) {
            $cargo_lanes["l{$index}"]['price'] = request::float("price{$index}", 0, 2) * 100;
        }
    }
    
    if (!$device->setCargoLanes($cargo_lanes)) {
        Util::itoast('保存型号数据失败！', We7::referer(), 'error');
    }

    //合并extra
    $extra = array_merge($device->get('extra', []), $extra);

    if (!$device->set('extra', $extra)) {
        Util::itoast('保存扩展数据失败！', We7::referer(), 'error');
    }

    $device->setTagsFromText($tags);
    $device->setDeviceModel(request('device_model'));

    if ($device->save()) {
        //更新公众号缓存
        $device->updateAccountData();
        $device->updateScreenAdvsData();

        $device->updateAppVolume();
        $device->updateRemain();

        $msg = '保存成功';

        $res = $device->updateQrcode(true);
        if (is_error($res)) {
            $msg .= ', 发生错误：' . $res['message'];
            $error = true;
        }

        if (isset($activeRes) && is_error($activeRes)) {
            $msg .= '，发生错误：无法激活设备';
            $error = true;
        }

        $msg .= '!';

        Util::itoast($msg, $id ? We7::referer() : $this->createWebUrl('device'), isset($error) ? 'warning' : 'success');
    }

    Util::itoast('保存失败！', We7::referer(), 'error');
} elseif ($op == 'online') {

    $client_id = request::trim('id');
    if ($client_id) {
        $res = CtrlServ::v2_query("device/{$client_id}/online");
        exit($res['status'] === true && $res['data']['mcb'] === true ? 1 : 0);
    }

    exit(-1);
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

    $accounts = $device->getAccounts();
    if ($accounts) {
        foreach ($accounts as &$entry) {
            $entry['edit_url'] = $this->createWebUrl('account', ['op' => 'edit', 'id' => $entry['id']]);
        }
    }
    $tpl_data['accounts'] = $accounts;

    $tpl_data['day_stats'] = $this->fetchTemplate(
        'web/device/stats',
        [
            'chartid' => Util::random(10),
            'chart' => Util::cachedCall(30, function() use($device) {
                return Stats::chartDataOfDay($device, time());
            }, $device->getId()),
        ]
    );

    $tpl_data['month_stats'] = $this->fetchTemplate(
        'web/device/stats',
        [
            'chartid' => Util::random(10),
            'chart' => Util::cachedCall(30, function() use($device) {
                return Stats::chartDataOfMonth($device, time());
            }, $device->getId()),
        ]
    );

    $tpl_data['device'] = $device;
    $tpl_data['payload'] = $device->getPayload(true);

    $tpl_data['mcb_online'] = $device->isMcbOnline();
    $tpl_data['app_online'] = $device->isAppOnline();

    $this->showTemplate('web/device/detail', $tpl_data);
} elseif ($op == 'log') {

    $device = Device::get(request('id'));
    if (empty($device)) {
        Util::itoast('找不到这个设备！', $this->createWebUrl('device'), 'error');
    }

    $tpl_data['navs'] = [
        'detail' => $device->getName(),
        'log' => '事件',
        //'poll_event' => '最新',
        'event' => '消息',
    ];

    $query = $device->logQuery();
    if (request::isset('way')) {
        $query->where(['level' => request::int('way')]);
    }

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

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
            'createtime_foramtted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            'imei' => $entry->getTitle(),
            'title' => Device::formatPullTitle($entry->getLevel()),
            'goods' => $entry->getData('goods'),
            'user' => $entry->getData('user'),
        ];

        $data['goods']['img'] = Util::toMedia($data['goods']['img'], true);

        $result = $entry->getData('result');
        if (is_array($result)) {
            $result_data = isset($result['data']) ? $result['data'] : $result;
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
            $data['memo'] = '订单编号:' . $order_tid;
        }

        $acc = $entry->getData('account');
        if ($acc) {
            $data['memo'] = '公众号:' . $acc['name'];
        }

        $logs[] = $data;
    }

    $tpl_data['logs'] = $logs;
    $tpl_data['device'] = $device;

    $this->showTemplate('web/device/log', $tpl_data);
} elseif ($op == 'event') {

    $device = Device::get(request('id'));
    if (empty($device)) {
        Util::itoast('找不到这个设备！', $this->createWebUrl('device'), 'error');
    }

    $query = $device->eventQuery();

    if (request::isset('event')) {
        $query->where(['event' => request('event')]);
    }

    $detail = request('detail') ? true : false;

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

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
            'createtime_foramtted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
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
        'log' => '事件',
        //'poll_event' => '最新',
        'event' => '消息',
    ];

    $tpl_data['events'] = $events;
    $tpl_data['device'] = $device;

    $this->showTemplate('web/device/event', $tpl_data);
} elseif ($op == 'daystats') {

    $device = Device::get(request('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $title = date('n月d日');
    $content = $this->fetchTemplate(
        'web/device/stats',
        [
            'chartid' => Util::random(10),
            'title' => $title,
            'chart' => Util::cachedCall(30, function() use($device, $title) {
                return Stats::chartDataOfDay($device, time(), "设备：{$device->getName()}({$title})");
            }, $device->getId()),
        ]
    );

    JSON::success(['z' => date('z'), 'content' => $content]);
} elseif ($op == 'monthstats') {

    $device = Device::get(request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $month = strtotime(request::str('month'));
    if (empty($month)) {
        $month = time();
    }

    $title = date('Y年n月', $month);

    $content = $this->fetchTemplate(
        'web/device/stats',
        [
            'chartid' => Util::random(10),
            'title' => $title,
            'chart' => Util::cachedCall(30, function() use($device, $month, $title) {
                return Stats::chartDataOfMonth($device, $month, "设备：{$device->getName()}({$title})");
            }, $device->getId(), $month),
        ]
    );

    JSON::success(['title' => '', 'content' => $content]);

} elseif ($op == 'repairMonthStats') {
    $device = Device::get(request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $month = strtotime(request::str('month'));
    if (empty($month)) {
        $month = time();
    }

    if (Stats::repairMonthData($device, $month)) {
        JSON::success('修复完成！');
    }

    JSON::success('修复失败！');
    
} elseif ($op == 'allstats') {

    //全部出货统计
    $device = Device::get(request::int('id'));
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    list($m, $total) = Util::cachedCall(30, function() use($device) {
        //开始 结束
        $first_order = Order::query(['device_id' => $device->getId()])->limit(1)->orderBy('id ASC')->findAll()->current();
        $last_order = Order::query(['device_id' => $device->getId()])->limit(1)->orderBy('id DESC')->findAll()->current();
        if ($first_order) {
            $first_order_datetime = intval($first_order->getCreatetime());
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
            $begin = new DateTime('@' . $first_order_datetime);
            $end = new DateTime('@' . $last_order_datetime);

            $end = $end->modify('last day of this month');

            while ($begin < $end) {
                $result = [
                    'total' => 0,
                    'free' => 0,
                    'fee' => 0,
                    'balance' => 0,
                ];

                $begin->modify('first day of this month');
                $begin->modify('00:00:00');

                $t_begin = $begin->getTimestamp();

                $month = $begin->format('Y-m-d');
                $title = $begin->format('Y年m月');

                $begin->modify('first day of next month');
                $t_end = $begin->getTimestamp();

                $free = Order::query()->where([
                    'device_id' => $device->getId(),
                    'price' => 0,
                    'balance' => 0,
                    'createtime >=' => $t_begin,
                    'createtime <' => $t_end
                ])->get('sum(num)');

                $result['free'] = intval($free);

                $fee = Order::query()->where([
                    'device_id' => $device->getId(),
                    'price >' => 0,
                    'createtime >=' => $t_begin,
                    'createtime <' => $t_end,
                ])->get('sum(num)');

                $result['fee'] = intval($fee);

                $balance = Order::query()->where([
                    'device_id' => $device->getId(),
                    'balance >' => 0,
                    'createtime >=' => $t_begin,
                    'createtime <' => $t_end,
                ])->get('sum(num)');

                $result['balance'] = intval($balance);

                $result['total'] = $result['fee'] + $result['free'] + $result['balance'];
                $total_num += $result['total'];

                $result['month'] = $month;
                $months[$title] = $result;
            }
            return [$months, $total_num];
        } catch (Exception $e) {            
        }
        
        return [];
    });
    $content = $this->fetchTemplate(
        'web/device/all-stats',
        [
            'device' => $device,
            'm_all' => $m,
            'total' => $total,
            'device_id' => $device->getId(),
        ]
    );

    JSON::success(['title' => "<b>{$device->getName()}</b>的出货统计", 'content' => $content]);
} elseif ($op == 'refresh_all') {

    if (CtrlServ::advsNotifyAll(['all' => 1])) {
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

    $content = $this->fetchTemplate(
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

    Util::resultJSON(is_error($res) ? false : true, ['msg' => is_error($res) ? $res['message'] : '重置成功！']);
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

}  elseif ($op == 'setNormal') {

    $id = request::int('id');
    if ($id) {
        $device = Device::get($id);
        if ($device && $device->updateSettings('extra.isDown', 0) && $device->save()) {
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
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

    $total = $query->count();
    $total_page = ceil($total / $page_size);
    if ($page > $total_page) {
        $page = 1;
    }

    //列表数据
    $data = [];

    $condition = We7::uniacid([]);

    $query = We7::load()->object('query');

    $join = $query
        ->from(m('maintenance')->getTableName(), 'm')
        ->leftjoin(Device::getTableName(), 'd')
        ->on('m.device_id', 'd.id');

    //搜索关键字
    $keywords = request::trim('keywords');
    if ($keywords) {
        $join->whereor("d.name LIKE", "%" . $keywords . "%")
            ->whereor("d.imei LIKE", "%" . $keywords . "%")
            ->whereor("d.app_id LIKE", "%" . $keywords . "%")
            ->whereor("d.iccid LIKE", "%" . $keywords . "%");
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
            'create' => date('Y-m-d H:i', $entry['createtime']),
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

    $this->showTemplate('web/device/report_list', $tpl_data);

} elseif ($op == 'group') {
    $query = Group::query();

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

    $total = $query->count();
    $total_page = ceil($total / $page_size);
    if ($page > $total_page) {
        $page = 1;
    }

    $query->page($page, $page_size);

    $keywords = request::trim('keywords');
    if ($keywords) {
        $query->where(['title REGEXP' => $keywords]);
    }

    $result     = [
        'page'      => $page,
        'total'     => $total,
        'totalpage' => $total_page,
        'list'      => [],
    ];

    foreach ($query->findAll() as $entry) {
        $result['list'][] = [
            'id' => $entry->getId(),
            'title' => $entry->getTitle(),
            'clr' => $entry->getClr(),
            'total' => (int) Device::query(['group_id' => $entry->getId()])->count(),
        ];
    }

    $result['serial'] = request::trim('serial') ?: microtime(true) . '';

    Util::resultJSON(true, $result);
    
} elseif ($op == 'group_search') {

    $query = Group::query();

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
    foreach ($query->findAll() as $entry) {
        $data = [
            'id' => intval($entry->getId()),
            'title' => $entry->getTitle(),
            'clr' => $entry->getClr(),
            'create' => date('Y-m-d H:i', $entry->getCreatetime()),
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
    $query = Group::query();

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

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
    $total_page = ceil($total / $page_size);
    if ($page > $total_page) {
        $page = 1;
    }

    //列表数据
    $query->page($page, $page_size);

    $list = [];
    /** @var device_groupsModelObj */
    foreach ($query->findAll() as $entry) {
        $data = [
            'id' => $entry->getId(),
            'title' => $entry->getTitle(),
            'clr' => $entry->getClr(),
            'create' => date('Y-m-d H:i', $entry->getCreatetime()),
            'total' => Device::query(['group_id' => $entry->getId()])->count()
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
    $tpl_data['agentId'] = isset($agent_id) ? $agent_id : null;
    $tpl_data['navs'] = $navs;

    $this->showTemplate('web/device/new_group', $tpl_data);
} elseif ($op == 'new_group_add') {

    $tpl_data['op'] = $op;
    $tpl_data['clr'] = Util::randColor();

    $this->showTemplate('web/device/new_group', $tpl_data);
} elseif ($op == 'new_group_edit') {

    $id = request::int('id');
    $tpl_data['id'] = $id;

    /** @var device_groupsModelObj $one */
    $one = Group::get($id);;
    if (empty($one)) {
        Util::itoast('分组不存在！', $this->createWebUrl('device', ['op' => 'new_group']), 'error');
    }

    $tpl_data['group'] = [
        'title' => $one->getTitle(),
        'clr' => $one->getClr(),
    ];

    if ($one) {
        $agent = $one->getAgent();
        if (empty($agent)) {
            Util::itoast('找不到这个代理商！', $this->createWebUrl('device', ['op' => 'new_group']), 'error');
        }
        $tpl_data['agent'] = [
            'id' => $agent->getId(),
            'name' => $agent->getName(),
            'mobile' => $agent->getMobile(),
        ];
    }

    $this->showTemplate('web/device/new_group', $tpl_data);
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
            'agent_id' => $agent_id,
            'title' => $title,
            'clr' => $clr,
            'createtime' => time()
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
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

    $device_id = request::int('device_id');

    $date_limit = request::array('datelimit');
    if ($date_limit['start']) {
        $s_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['start'] . ' 00:00:00');
    } else {
        $s_date = new DateTime('first day of this month 00:00:00');
    }

    if ($date_limit['end']) {
        $e_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['end'] . ' 00:00:00');
    } else {
        $e_date = new DateTime('first day of next month 00:00:00');
    }

    $agent_openid = request::str('agent_openid');
    $nickname = request::trim('nickname');

    $user_ids = [];
    if ($nickname != '') {
        $user_res = User::query()->whereOr([
            'nickname LIKE' => "%{$nickname}%",
            'mobile LIKE' => "%{$nickname}%",
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
        if ($page > ceil($total / $page_size)) {
            $page = 1;
        }
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
            $device_assoc[$item->getId()] = $item->getName() . ', ' . $item->getImei();
            $device_agent_assoc[$item->getId()] = $item->getAgentId();
            $agent_ids[] = $item->getAgentId();
        }
    }

    $agent_assoc = [];
    $agent_assoc[0] = '平台';
    if (!empty($agent_ids)) {
        $agent_ids = array_unique($agent_ids);
        $agent_res = m('agent_vw')->where('id IN(' . implode(',', $agent_ids) . ')')->findAll();
        foreach ($agent_res as $item) {
            $agent_assoc[$item->getId()] = $item->getNickname();
        }
    }

    $rec_type = [
        '1' => '开门记录',
        '2' => '消毒记录',
        '3' => '换电池记录'
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
                $item['createtime']
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

        $this->showTemplate('web/device/record', $tpl_data);
    }
} else if ($op == 'feed_back') {

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

    $device_id = request::int('device_id');

    $date_time = new DateTime();
    $date_time->modify('first day of this month');

    $date_limit = request::array('datelimit');
    if ($date_limit['start']) {
        $s_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['start'] . ' 00:00:00');
    } else {
        $s_date = new DateTime('first day of this month 00:00:00');
    }

    if ($date_limit['end']) {
        $e_date = DateTime::createFromFormat('Y-m-d H:i:s', $date_limit['end'] . ' 00:00:00');
        $e_date->modify('next day');
    } else {
        $e_date = new DateTime('first day of next month 00:00:00');
    }

    $condition = [
        'createtime >=' => $s_date->getTimestamp(),
        'createtime <' => $e_date->getTimestamp(),
    ];

    $agent_openid = request::str('agent_openid');
    $nickname = request::trim('nickname');

    $user_ids = [];
    if ($nickname != '') {
        $user_res = User::query()->whereOr([
            'nickname LIKE' => "%{$nickname}%",
            'mobile LIKE' => "%{$nickname}%",
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

    if (!empty($device_id)) {
        $condition['device_id'] = $device_id;
    }

    $query = m('device_feedback')->query($condition);

    if (empty($user_ids)) {
        $query->where('id = -1');
    } else {
        $user_ids = array_unique($user_ids);
        $query->where(['user_id' => $user_ids]);
    }

    if (empty($device_ids)) {
        $query->where('id = -1');
    } else {
        $device_ids = array_unique($device_ids);
        $query->where(['device_id' => $device_ids]);
    }

    $total = $query->count();
    if ($page > ceil($total / $page_size)) {
        $page = 1;
    }

    $query->orderBy('id DESC');
    $res = $query->page($page, $page_size)->findAll();

    $data = [];
    $user_ids = [];
    $device_ids = [];

    /** @var device_feedbackModelObj $item */
    foreach ($res as $item) {
        $pics = unserialize($item->getPics());
        if ($pics === false) {
            $pics = [];
        }
        $arr = [
            'id' => $item->getId(),
            'deviceId' => $item->getDeviceId(),
            'userId' => $item->getUserId(),

            'text' => $item->getText(),
            'pics' => $pics,
            'remark' => $item->getRemark(),

            'createtime' => date('Y-m-d H:i:s', $item->getCreatetime()),
        ];

        $data[] = $arr;
        $user_ids[] = $item->getUserId();
        $device_ids[] = $item->getDeviceId();
    }

    $user_assoc = [];
    if (!empty($user_ids)) {

        $user_ids = array_unique($user_ids);
        $user_res = User::query('id IN (' . implode(',', $user_ids) . ')')->findAll();

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
        $device_res = Device::query('id IN (' . implode(',', $device_ids) . ')')->findAll();

        /** @var deviceModelObj $item */
        foreach ($device_res as $item) {
            $device_assoc[$item->getId()] = $item->getName() . ', ' . $item->getImei();
            $device_agent_assoc[$item->getId()] = $item->getAgentId();
            $agent_ids[] = $item->getAgentId();
        }
    }

    $agent_assoc = [];
    $agent_assoc[0] = '平台';

    if (!empty($agent_ids)) {
        $agent_ids = array_unique($agent_ids);
        $agent_res = m('agent_vw')->where('id IN(' . implode(',', $agent_ids) . ')')->findAll();

        /** @var agent_vwModelObj $item */
        foreach ($agent_res as $item) {
            $agent_assoc[$item->getId()] = $item->getNickname();
        }
    }

    $tpl_data['s_date'] = $s_date;
    $tpl_data['e_date'] = $e_date;
    $tpl_data['nickname'] = $nickname;
    $tpl_data['device_id'] = $device_id;
    $tpl_data['open_id'] = $agent_openid;

    $tpl_data['data'] = $data;
    $tpl_data['user_assoc'] = $user_assoc;
    $tpl_data['device_assoc'] = $device_assoc;
    $tpl_data['device_agent_assoc'] = $device_agent_assoc;
    $tpl_data['agent_assoc'] = $agent_assoc;

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);
    $tpl_data['attach_url'] = We7::attachment_set_attach_url();

    $this->showTemplate('web/device/feedback', $tpl_data);
} else if ($op == 'deal_fb') {

    //处理反馈
    $id = request('id');
    $remark = request('remark');

    /** @var device_feedbackModelObj $res */
    $res = m('device_feedback')->findOne(['id' => $id]);

    if ($res) {
        $res->setRemark($remark);
        $res->save();

        JSON::success(['id' => $id, 'remark' => $remark]);
    } else {
        JSON::fail('error');
    }
} else if ($op == 'add_fb') {

    $id = request('id');

    /** @var device_feedbackModelObj $res */
    $res = m('device_feedback')->findOne(['id' => $id]);
    if ($res) {
        if ($res->getRemark() != '') {
            JSON::fail('已处理该数据！');
        }
    } else {
        JSON::fail('找不到该记录！');
    }

    $pics = unserialize($res->getPics());
    if ($pics === false) {
        $pics = [];
    }

    $content = $this->fetchTemplate(
        'web/device/deal_fb',
        [
            'chartid' => Util::random(10),
            'id' => $res->getId(),
            'text' => $res->getText(),
            'pics' => $pics,
            'attach_url' => We7::attachment_set_attach_url(),
        ]
    );

    JSON::success(['content' => $content]);
} else if ($op == 'import_bluetooth_device_upload') {

    $tpl_data = [];
    $this->showTemplate('web/device/bluetooth_upload', $tpl_data);
} else if ($op == 'create_bluetooth_device') {

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

    $this->showTemplate('web/device/poll_event', $tpl_data);
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

} elseif ($op == 'aliTicket') {

    $result = [];

    $id = request::int('id');
    if ($id > 0) {
        $device = Device::get($id);
        if (empty($device)) {
            JSON::fail('找不到这个设备！');
        }
    }

    $fn = request::trim('fn');
    if ($fn == 'detail') {
        $result['device_types'] = AliTicket::getDeviceTypes();
        $result['scenes'] = AliTicket::getSceneList();
        if ($device) {
            $result['status'] = AliTicket::getDeviceJoinStatus($device);
        }
    }

    JSON::success($result);    
}
