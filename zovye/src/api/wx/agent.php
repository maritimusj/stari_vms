<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use ali\aop\AopClient;
use ali\aop\request\AlipaySystemOauthTokenRequest;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use zovye\Cache;
use zovye\Config;
use zovye\Inventory;
use zovye\Log;
use zovye\model\agent_msgModelObj;
use zovye\model\agentModelObj;
use zovye\App;
use zovye\CommissionBalance;
use zovye\Device;
use zovye\model\device_groupsModelObj;
use zovye\model\deviceModelObj;
use zovye\DeviceTypes;
use zovye\model\settings_userModelObj;
use zovye\request;
use zovye\Job;
use zovye\JSON;
use zovye\Keeper;
use zovye\model\keeperModelObj;
use zovye\model\login_dataModelObj;
use zovye\LoginData;
use zovye\Order;
use zovye\model\orderModelObj;
use zovye\State;
use zovye\User;
use zovye\model\userModelObj;
use zovye\Util;
use zovye\We7;
use function zovye\_W;
use function zovye\err;
use function zovye\error;
use function zovye\request;
use function zovye\is_error;
use function zovye\m;
use function zovye\setArray;
use function zovye\settings;

class agent
{
    /**
     * 获取当前登录的代理商身份，如果当前登录用户是合伙人，则返回合伙人对应的代理商身份
     * @return agentModelObj|null
     */
    public static function getAgent(): ?agentModelObj
    {
        $user = common::getAgent();
        if ($user->isAgent()) {
            return $user;
        }

        if ($user->isPartner()) {
            return $user->getPartnerAgent();
        }

        JSON::fail('操作失败，无法获取代理商身份！');
    }

    /**
     *  通过guid搜索用户
     *  GUID 通过绑定当前用户与下级ＩＤ生成一个只对当前用户有效的下级用户名ＩＤ.
     *
     * @param $guid
     *
     * @return agentModelObj|userModelObj
     */
    public static function getUserByGUID($guid)
    {
        $login_data = LoginData::get(common::getToken());
        if ($login_data) {
            $session_key = $login_data->getSessionKey() ?: _W('token');

            /** @var userModelObj $res */
            $res = User::findOne("SHA1(CONCAT('$session_key', id))='$guid'");
            if ($res) {
                if ($res->isAgent()) {
                    return $res->agent();
                }

                return $res;
            }
        }

        return null;
    }

    /**
     * @deprecated
     */
    public static function reg(): array
    {
        //邀请登记手机号码网址
        return ['url' => Util::murl('mobile')];
    }

    public static function doUserLogin($res): array
    {
        $mobile = $res['phoneNumber'];
        $session_key = $res['session_key'];

        if (empty($mobile)) {
            return error(State::ERROR, '获取用户手机号码失败，请稍后再试！');
        }

        $user = User::findOne(['mobile' => $mobile, 'app' => User::WX]);
        if ($user) {
            if ($res['config'] && !$user->isWxAppAllowed($res['config']['key'])) {
                return error(State::ERROR, '登录失败，无法使用这个小程序！');
            }

            if (!($user->isAgent() || $user->isPartner())) {
                return error(State::ERROR, '您还不是我们的代理商，立即注册?');
            }

            //清除原来的登录信息
            foreach (LoginData::agent(['user_id' => $user->getId()])->findAll() as $entry) {
                $entry->destroy();
            }

            $token = sha1(time()."$mobile$session_key");
            $data = [
                'src' => LoginData::AGENT,
                'user_id' => $user->getId(),
                'session_key' => $session_key,
                'openid_x' => $user->getOpenid(),
                'token' => $token,
            ];

            if (LoginData::create($data)) {
                $result = ['token' => $token];

                $agent_levels = settings('agent.levels');

                $agent = $user->agent();
                $agent_data = $agent->getAgentData();
                $FNs = is_array($agent_data['funcs']) ? $agent_data['funcs'] : [];
                $result['profile'] = [
                    'id' => $user->getId(),
                    'name' => $agent->getName(),
                    'company' => $agent_data['company'] ?: '<未登记>',
                    'level' => $agent_levels[$agent_data['level']],
                    'funcs' => array_merge(Util::getAgentFNs(false), $FNs),
                ];

                $referral = $agent->getReferral();
                if ($referral) {
                    $result['referral'] = [
                        'code' => $referral->getCode(),
                    ];
                    //兼容老版本小程序
                    $result['referal'] = [
                        'code' => $referral->getCode(),
                    ];
                }

                //F_cm = 佣金系统
                $commission_enabled = App::isCommissionEnabled() && $agent_data['commission']['enabled'];
                $result['profile']['funcs']['F_cm'] = $commission_enabled ? 1 : 0;

                if ($user->isAgent()) {
                    $result['profile']['passport'] = 'agent';
                } elseif ($user->isPartner()) {
                    $result['profile']['passport'] = 'partner';
                }

                $result['msg'] = '登录成功！';

                return $result;
            } else {
                return error(State::ERROR, '登录失败！[101]');
            }
        }

        return error(State::ERROR, '您还不是我们的代理商,立即注册?[102]');
    }

    public static function preLogin(): array
    {
        $result = [
            'secret' => sha1(App::uid(10)),
            //邀请登记手机号码网址
            'url' => Util::murl('mobile'),
            'debug' => 0,
            'plugin' => [
                'wxplatform' => App::isWxPlatformEnabled(),
                'douyin' => App::isDouyinEnabled(),
                'balance' => App::isBalanceEnabled(),
            ],
            'wxapp' => [
                'debug' => false,
                'config' => Config::app('wxapp.advs', []),
                'title' => settings('agentWxapp.title', ''),
                'name' => settings('agentWxapp.name', ''),
            ],
            'balance' => [
                'config' => [
                    'user' => Config::balance('user', []),
                    'sign' => Config::balance('sign.bonus', []),
                ],
            ],
            'goods' => [
                'max' => App::orderMaxGoodsNum(),
            ],
        ];

        //是否为微信审核模式
        $data = include ZOVYE_ROOT.DIRECTORY_SEPARATOR.'debug.php';
        if ($data) {
            $result['debug'] = intval($data['debug']);
        }

        return $result;
    }

    /**
     * @deprecated
     */
    public static function pluginsList(): array
    {
        return [
            'wxplatform' => App::isWxPlatformEnabled(),
            'douyin' => App::isDouyinEnabled(),
        ];
    }

    /**
     * 用户登录，小程序必须提交code,encryptedData和iv值
     *
     * @return array
     */
    public static function login(): array
    {
        $res = common::getDecryptedWxUserData();
        if (is_error($res)) {
            Log::error('wxapi', $res);
            return $res;
        }

        $result = agent::doUserLogin($res);
        if (is_error($result)) {
            return $result;
        }

        $agreement = Config::agent('agreement.agent', []);
        if ($agreement['enabled']) {
            $result['agreement'] = $agreement['content'];
        }

        return $result;
    }

    /**
     * 代理商申请提交.
     *
     * @return array
     */
    public static function application(): array
    {
        $name = request::trim('name');
        $mobile = request::trim('mobile');

        if (empty($name) || empty($mobile)) {
            return error(State::ERROR, '对不起，请填写姓名和手机号码！');
        }

        $data = We7::uniacid(
            [
                'name' => $name,
                'mobile' => $mobile,
                'address' => htmlspecialchars(request::trim('address')),
                'referee' => request::trim('referee'),
                'state' => 0,
            ]
        );

        $app = m('agent_app')->create($data);
        if ($app) {
            Job::agentApplyNotice($app->getId());

            return ['msg' => '提交成功，请耐心等待管理员审核！'];
        }

        return error(State::ERROR, '提交失败，请稍后重试！');
    }

    /**
     * 设置代理商提现银行信息.
     *
     * @return array
     */
    public static function setAgentBank(): array
    {
        $user = common::getAgent();
        if ($user->isAgent() || $user->isPartner()) {
            $agent = $user->isPartner() ? $user->getPartnerAgent() : $user;
            if ($agent) {
                return common::setUserBank($agent);
            }
        }

        return error(State::ERROR, '无法保存，请联系管理员！');
    }

    /**
     * 获取代理商的银行信息.
     *
     * @return array
     */
    public static function getAgentBank(): array
    {
        $user = common::getAgent();

        $result = [];

        if ($user->isAgent() || $user->isPartner()) {
            $agent = $user->isPartner() ? $user->getPartnerAgent() : $user;
            if ($agent) {
                return $agent->settings(
                    'agentData.bank',
                    [
                        'realname' => '',
                        'bank' => '',
                        'branch' => '',
                        'account' => '',
                        'address' => [
                            'province' => '',
                            'city' => '',
                        ],
                    ]
                );
            }
        }

        return $result;
    }

    /**
     * 代理商消息列表.
     *
     * @return array
     */
    public static function agentMsg(): array
    {
        $user = common::getAgent();

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        $query = m('agent_msg')->where(We7::uniacid(['agent_id' => $user->getAgentId()]));

        $total = $query->count();

        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
            'totalpage' => ceil($total / $page_size),
            'list' => [],
        ];

        if ($total > 0) {
            $query->page($page, $page_size);
            $query->orderBy('id desc');

            /** @var agent_msgModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $result['list'][] = [
                    'id' => $entry->getId(),
                    'title' => $entry->getTitle(),
                    'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                    'isReaded' => $entry->getUpdatetime() ? 1 : 0,
                ];
            }
        }

        return $result;
    }

    /**
     * 读取消息详细内容.
     *
     * @return array
     */
    public static function msgDetail(): array
    {
        $user = common::getAgent();

        $id = request::int('id');
        if ($id) {
            /** @var agent_msgModelObj $msg */
            $msg = m('agent_msg')->findOne(We7::uniacid(['agent_id' => $user->getAgentId(), 'id' => $id]));
            if ($msg) {
                $msg->setUpdatetime(time());
                $msg->save();

                return ['id' => $msg->getId(), 'title' => $msg->getTitle(), 'content' => $msg->getContent()];
            }
        }

        return error(State::ERROR, '出错了，读取消息失败！');
    }

    /**
     * 删除消息.
     *
     * @return array
     */
    public static function msgRemove(): array
    {
        $user = common::getAgent();

        $id = request::int('id');
        if ($id) {
            $msg = m('agent_msg')->findOne(We7::uniacid(['agent_id' => $user->getAgentId(), 'id' => $id]));
            if ($msg) {
                $msg->destroy();

                return ['id' => $id, 'msg' => '删除成功！'];
            }
        }

        return error(State::ERROR, '出错了，删除消息出错！');
    }

    /**
     * 获取设备列表.
     *
     * @return array
     * @throws Exception
     */
    public static function deviceList(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sb');

        $query = Device::query();
        $group_id = request::int('group_id');
        if (!empty($group_id)) {
            $query->where(['group_id' => $group_id]);
        }
        $agent_id = $user->getAgentId();
        if (request::has('agent')) {
            $agent = agent::getUserByGUID(request::str('agent'));
            if ($agent) {
                $agent_id = $agent->getId();
            }
        }
        $query->where(['agent_id' => $agent_id]);

        return \zovye\api\wx\device::getDeviceList($user, $query);
    }

    public static function keeperDeviceList(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sb');

        $keeperId = request::int('keeperid');
        $keeper = Keeper::get($keeperId);
        if (empty($keeper) || $user->getAgentId() != $keeper->getAgentId()) {
            return error(State::ERROR, '找不到这个营运人员！');
        }

        $query = Device::keeper($keeper);

        if (request::has('keyword')) {
            $keyword = request::trim('keyword');
            if ($keyword) {
                $query->whereOr([
                    'name LIKE' => "%$keyword%",
                    'imei LIKE' => "%$keyword%",
                ]);
            }
        }

        $page = max(1, request::int('page'));
        $page_size = request::int('pagesize', DEFAULT_PAGE_SIZE);

        $total = $query->count();

        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
            'totalpage' => ceil($total / $page_size),
            'list' => [],
        ];

        /** @var deviceModelObj $entry */
        foreach ($query->findAll() as $entry) {
            $data = \zovye\api\wx\device::formatDeviceInfo($user, $entry, true, $keeperId);
            $result['list'][] = $data;
        }

        return $result;
    }

    /**
     * 更新终端设置.
     *
     * @return array
     */
    public static function deviceUpdate(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sb');

        /** @var deviceModelObj|array $device */
        $device = \zovye\api\wx\device::getDevice(request('id'));
        if (is_error($device)) {
            return $device;
        }

        if (!$device->payloadLockAcquire(3)) {
            return error(State::ERROR, '设备正忙，请稍后再试！');
        }

        if (empty($device->getAgentId())) {
            return error(State::ERROR, '这个设备没有绑定代理商！');
        }

        //管理员登记不能修改设备参数
        if (!$device->isOwnerOrSuperior($user)) {
            return error(State::ERROR, '没有权限管理这个设备！');
        }

        //修改设备名称
        $name = request::trim('name');
        if ($name && $name != $device->getName()) {
            $device->setName($name);
        }

        //修改排序值
        $rank = request::int('rank');
        $device->setRank($rank);

        //指定group id
        if (request::isset('group')) {
            $group = request::int('group');
            $device->setGroupId($group);
        }

        $extra = $device->get('extra', []);

        $now = time();
        $payload = [];

        if (request::isset('device_type')) {
            $type_id = request::int('device_type');

            if ($type_id != $device->getDeviceType()) {
                $payload[] = $device->resetPayload(['*' => '@0'], '代理商改变型号', $now);
                $device->setDeviceType($type_id);
            }

            $device_type = DeviceTypes::from($device);
            if (empty($device_type)) {
                return error(State::ERROR, '设备类型不正确！');
            }

            if ($device->isCustomType()) {
                $old = $device_type->getExtraData('cargo_lanes', []);

                $cargo_lanes = [];
                $capacities = request::array('capacities');
                foreach (request::array('goods') as $index => $goods_id) {
                    $cargo_lanes[] = [
                        'goods' => intval($goods_id),
                        'capacity' => intval($capacities[$index]),
                    ];
                    if ($old[$index] && $old[$index]['goods'] != intval($goods_id)) {
                        $payload[] = $device->resetPayload([$index => '@0'], '代理商更改货道商品', $now);
                    }
                    unset($old[$index]);
                }

                foreach ($old as $index => $lane) {
                    $payload[] = $device->resetPayload([$index => '@0'], '代理商删除货道', $now);
                }

                $device_type->setExtraData('cargo_lanes', $cargo_lanes);
                $device_type->save();
            }
        }

        if (empty($device_type)) {
            return error(State::ERROR, '获取型号失败！');
        }

        if (request::isset('price') || request::isset('num')) {
            //货道商品数量和价格
            $prices = request::array('price');
            $num = request::array('num');

            $type_data = DeviceTypes::format($device_type);
            $cargo_lanes = [];
            foreach ($type_data['cargo_lanes'] as $index => $lane) {
                $cargo_lanes[$index] = [
                    'num' => '@'.max(0, intval($num[$index])),
                ];
                if ($device_type->getDeviceId() == $device->getId()) {
                    $cargo_lanes[$index]['price'] = intval($prices[$index]);
                }
            }
            $res = $device->resetPayload($cargo_lanes, '代理商编辑设备', $now);
            if (is_error($res)) {
                return error(State::ERROR, '保存设备库存数据失败！');
            }
            $payload[] = $res;
        }

        if (App::isInventoryEnabled()) {
            $user = $user->isPartner() ? $user->getPartnerAgent() : $user;
            foreach ($payload as $result) {
                $v = Inventory::syncDevicePayloadLog($user, $device, $result, '代理商编辑设备');
                if (is_error($v)) {
                    return $v;
                }
            }
        }

        if (App::isDeviceWithDoorEnabled()) {
            setArray($extra, 'door.num', request::int('doorNum', 1));
        }

        //修改位置信息
        $location = request::is_array('location') ? request::array('location') :
            json_decode(html_entity_decode(request::str('location')), true);
        if ($location) {
            $location = array_intersect_key($location, ['lat' => 0, 'lng' => 0, 'address' => '', 'area' => '']);
        } else {
            $location = [];
        }

        if (!empty($location['lat']) && !empty($location['lng'])) {
            setArray($extra, 'location.tencent', $location);
        }

        //音量
        $volume = max(0, min(100, request::int('volume')));
        if ($volume !== $extra['volume']) {
            setArray($extra, 'volume', $volume);
            $device->updateAppVolume($volume);
        }

        //修改运营人员
        $keeper_id = request::int('keeper');
        if ($keeper_id) {
            $keeper = Keeper::findOne(['id' => $keeper_id]);
            if ($keeper) {
                $extra['keepers'] = $keeper->getId();
            }
        } else {
            unset($extra['keepers']);
        }

        $extra['isDown'] = request::int('is_down');

        if ($device->set('extra', $extra) && $device->save()) {
            return ['msg' => '保存成功！'];
        }

        return error(State::ERROR, '保存失败！');
    }

    /**
     * 请求设备信息.
     *
     * @return array
     */
    public static function deviceInfo(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sb');

        /** @var deviceModelObj|array $device */
        $device = \zovye\api\wx\device::getDevice(request::str('id'));
        if (is_error($device)) {
            return $device;
        }

        /** @var device_groupsModelObj $group */
        $group = $device->getGroup();

        if ($device->getAgentId()) {
            //已绑定设备
            if ($device->isOwnerOrSuperior($user)) {
                $result = \zovye\api\wx\device::formatDeviceInfo($user, $device);

                if ($group) {
                    $result['group']['id'] = $group->getId();
                    $result['group']['title'] = $group->getTitle();
                    $result['group']['clr'] = $group->getClr();
                }

                if (request::bool('online', true)) {
                    $detail = $device->getOnlineDetail();
                    if ($detail) {
                        $device->setSig(intval($detail['mcb']['RSSI']));
                        $device->save();

                        $result['status']['sig'] = $device->getSig();
                        $result['status']['online'] = boolval($detail['mcb']);
                        if (isset($detail['app'])) {
                            $result['app']['online'] = boolval($detail['app']);
                        }
                    }
                }
            } else {
                return error(State::ERROR, '没有权限管理这个设备！');
            }
        } else {
            //未绑定设备
            $result = [
                'info' => [
                    'id' => $device->getImei(),
                    'name' => $device->getName(),
                    'sig' => $device->getSig(),
                    'qrcode' => Util::toMedia($device->getQrcode()),
                    'capacity' => $device->getCapacity(),
                ],
            ];
        }

        if ($result) {
            return $result;
        }

        return error(State::ERROR, '请求无法完成！');
    }

    /**
     * 绑定和解绑设备.
     *
     * @return array
     */
    public static function deviceBind(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sb');

        /** @var deviceModelObj|array $device */
        $device = \zovye\api\wx\device::getDevice(request::trim('id'), $user);
        if (is_error($device)) {
            return $device;
        }

        if (!$device->lockAcquire(3)) {
            return error(State::ERROR, '锁定设备失败，请稍后再试！');
        }

        $agent = $user->getPartnerAgent() ?: $user;

        $agent_id = $device->getAgentId();
        if (empty($agent_id)) {
            //绑定
            if ($agent->isAgent()) {
                if (Device::bind($device, $agent)) {
                    return ['op' => 'bind', 'result' => true];
                }
            } else {
                return error(State::ERROR, '只能绑定到代理商帐号！');
            }
        } else {
            if (!$user->settings('agentData.misc.power')) {
                if ($device->getAgentId() != $user->getAgentId()) {
                    return error(State::ERROR, '没有权限管理这个设备！');
                }
            }

            if (Device::unbind($device)) {
                return ['op' => 'unbind', 'result' => true];
            }
        }

        return error(State::ERROR, '操作失败，请稍后再试！');
    }

    /**
     * 出货测试.
     *
     * @return array
     */
    public static function deviceTest()
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sb');

        /** @var deviceModelObj|array $device */
        $device = \zovye\api\wx\device::getDevice(request('id'), $user);
        if (is_error($device)) {
            return $device;
        }

        if (!$device->isOwnerOrSuperior($user)) {
            return error(State::FAIL, '没有权限执行这个操作！');
        }

        $lane = request::int('lane');
        $res = Util::deviceTest($user, $device, $lane);

        if (is_error($res)) {
            return error(State::FAIL, $res['message']);
        }

        $resp = ['id' => $device->getImei(), 'msg' => '出货成功！'];
        if ($device->isBlueToothDevice()) {
            $data = $res['data'];
            if (!empty($data)) {
                $resp['bluetooth'] = [
                    'data' => $data,
                    'hex' => bin2hex(base64_decode($data)),
                ];
            }
        }

        return $resp;
    }

    /**
     * 重置货量.
     *
     * @return array
     */
    public static function deviceReset(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sb');

        $device = \zovye\api\wx\device::getDevice(request('id'), $user);
        if (is_error($device)) {
            return $device;
        }

        if (!$device->isOwnerOrSuperior($user)) {
            return error(State::ERROR, '没有权限执行这个操作！');
        }

        $locker = $device->payloadLockAcquire(3);
        if (empty($locker)) {
            return error(State::ERROR, '设备正忙，请稍后再试！');
        }

        if (request::isset('lane')) {
            $num = request::int('num');
            $data = [
                request::int('lane') => $num > 0 ? '@'.$num : 0,
            ];
        } else {
            $data = [];
        }

        $res = $device->resetPayload($data, '代理商补货');
        if (is_error($res)) {
            return error(State::ERROR, '保存库存失败！');
        }

        if (App::isInventoryEnabled()) {
            $user = $user->isPartner() ? $user->getPartnerAgent() : $user;
            $v = Inventory::syncDevicePayloadLog($user, $device, $res, '代理商补货');
            if (is_error($v)) {
                return $v;
            }
        }

        $locker->unlock();

        $device->updateAppRemain();
        $device->save();

        return $device->getPayload(true);
    }

    /**
     * 转移设备给自己的下级代理商.
     *
     * @return array
     */
    public static function deviceAssign(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sb');

        $target = agent::getUserByGUID(request('guid'));
        if (empty($target)) {
            return error(State::ERROR, '用户不存在！');
        }

        $device_ids = [];

        $device_id = request::trim('deviceid');
        if (!empty($device_id)) {
            $device_ids[] = $device_id;
        }

        $group_id = request::int('group');
        if ($group_id > 0) {
            $query = Device::query([
                'agent_id' => $user->getAgentId(),
                'group_id' => $group_id,
            ]);
            /** @var deviceModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $device_ids[] = $entry->getImei();
            }
        }

        foreach ($device_ids as $device_id) {
            $device = \zovye\api\wx\device::getDevice($device_id, $user);
            if (is_error($device)) {
                return $device;
            }

            if (Device::bind($device, $target) && $device->save()) {
                continue;
            } else {
                return error(State::ERROR, '转移设备失败！');
            }
        }

        return ['msg' => '转移设备成功！'];
    }

    /**
     * 缺货设备列表.
     *
     * @return array
     */
    public static function deviceLowRemain(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_qz');

        if (request::has('remain')) {
            $remain_warning = max(1, request::int('remain'));
        } else {
            $remain_warning = settings('device.remainWarning', 0);
        }

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGE_SIZE));

        $query = Device::query([
            'remain <' => $remain_warning,
            'agent_id' => $user->getAgentId(),
        ]);

        $total = $query->count();
        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'totalpage' => ceil($total / $page_size),
            'total' => $total,
            'list' => [],
        ];

        if ($total > 0) {
            /** @var deviceModelObj $entry */
            foreach ($query->page($page, $page_size)->findAll() as $entry) {
                $address = $entry->settings(
                    'extra.location.tencent.address',
                    $entry->settings('extra.location.address')
                ) ?: '<地址未登记>';
                $result['list'][] = [
                    'id' => $entry->getImei(),
                    'name' => $entry->getName(),
                    'address' => $address,
                    'remain' => intval($entry->getRemainNum()),
                    'capacity' => intval($entry->getCapacity()),
                ];
            }
        }

        return $result;
    }

    /**
     * 获取故障设备.
     *
     * @return array
     */
    public static function deviceError(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_gz');

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGE_SIZE));

        $query = Device::query(['agent_id' => $user->getAgentId()]);

        $error_code = request::int('error');
        if ($error_code > 0) {
            $query->where(['error_code' => $error_code]);
        } else {
            $query->where(['error_code <>' => $error_code]);
        }

        $total = $query->count();
        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'totalpage' => ceil($total / $page_size),
            'total' => $total,
            'list' => [],
        ];

        if ($total > 0) {
            /** @var deviceModelObj $entry */
            foreach ($query->page($page, $page_size)->findAll() as $entry) {
                $address = $entry->settings(
                    'extra.location.tencent.address',
                    $entry->settings('extra.location.address')
                ) ?: '<地址未登记>';
                $last_error = $entry->getLastError();
                $result['list'][] = [
                    'id' => $entry->getImei(),
                    'name' => $entry->getName(),
                    'address' => $address,
                    'errorCode' => intval($last_error['errno']),
                    'errorDesc' => strval($last_error['message']),
                    'createtime' => date('Y-m-d H:i:s', $last_error['createtime']),
                ];
            }
        }

        return $result;
    }

    public static function orderRefund(): array
    {
        $user = common::getAgent();
        $agent = $user->isPartner() ? $user->getPartnerAgent() : $user;

        if (!settings('agent.order.refund')) {
            return error(State::ERROR, '不允许退款，请联系管理员！');
        }

        $order = Order::get(request('orderid'));
        if (empty($order) || $order->getAgentId() != $agent->getId()) {
            return error(State::ERROR, '找不到这个订单！');
        }

        if ($agent->getCommissionBalance()->total() < $order->getPrice()) {
            return error(State::ERROR, '代理商余额不足，无法退款！');
        }

        $num = request::int('num');

        $res = Order::refund($order->getOrderNO(), $num, ['message' => '代理商：'.$agent->getName()]);
        if (is_error($res)) {
            return error(State::ERROR, $res['message']);
        }

        return ['msg' => '退款成功！'];
    }

    /**
     * 订单列表.
     *
     * @return array
     */
    public static function orders(): array
    {
        common::checkCurrentUserPrivileges('F_sb');

        $query = Order::query();
        $condition = [];

        if (request::has('deviceid')) {
            $device = \zovye\api\wx\device::getDevice(request('deviceid'));
            if (is_error($device)) {
                return $device;
            }

            $condition['device_id'] = $device->getId();
            $condition['agent_id'] = $device->getAgentId();
        }

        $user_id = request::int('userid');
        if ($user_id) {
            $condition['user_id'] = $user_id;
        }

        $query->where($condition);

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGE_SIZE));

        $total = $query->count();
        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
            'totalpage' => ceil($total / $page_size),
            'list' => [],
        ];

        $query->page($page, $page_size);
        $query->orderBy('id desc');

        /** @var orderModelObj $order */
        foreach ($query->findAll() as $order) {
            $data = [
                'id' => $order->getId(),
                'orderId' => $order->getOrderId(),
                'num' => intval($order->getNum()),
                'price' => number_format($order->getPrice() / 100, 2),
                'refund' => !empty($order->getExtraData('refund')),
                'account' => $order->getAccount(),
                'goods' => $order->getGoodsData(),
                'createtime' => date('Y-m-d H:i:s', $order->getCreatetime()),
            ];

            $pay_result = $order->getExtraData('payResult');
            $data['transaction_id'] = $pay_result['transaction_id'] ?? ($pay_result['uniontid'] ?? '');

            $data['goods']['img'] = Util::toMedia($data['goods']['img'], true);

            $x = User::get($order->getOpenid(), true);
            if ($x) {
                $data['userid'] = $x->getId();
                $data['name'] = $x->getNickname();
                $data['avatar'] = $x->getAvatar();
            }


            $pull_result = $order->getExtraData('pull.result', []);
            if (is_error($pull_result)) {
                $data['status'] = [
                    'title' => $pull_result['message'],
                    'clr' => '#F56C6C',
                ];
            } else {
                $data['status'] = [
                    'title' => '出货成功',
                    'clr' => '#67C23A',
                ];
            }

            if ($data['refund']) {
                $data['status']['title'] .= '（已退款）';
            }

            $result['list'][] = $data;
        }

        return $result;
    }

    /**
     * 清除设备故障代码
     *
     * @return array
     */
    public static function deviceSetErrorCode(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_sb');

        $device = \zovye\api\wx\device::getDevice(request('id'));
        if (is_error($device)) {
            return $device;
        }

        if (!$device->isOwnerOrSuperior($user)) {
            return error(State::ERROR, '没有权限执行这个操作！');
        }

        $resultDesc = request::trim('desc');
        $resultCode = request::int('code');

        if ($resultCode !== 0) {
            $device->setError($resultCode, $resultDesc);
        } else {
            $device->cleanError();
        }

        $data = We7::uniacid(
            [
                'device_id' => $device->getImei(),
                'error_code' => $device->getErrorCode(),
                'result_code' => $resultCode,
                'result' => $resultDesc,
                'mobile' => $user->getMobile(),
                'name' => $user->getName(),
            ]
        );

        if (m('maintenance')->create($data) && $device->save()) {
            $device->remove('lastErrorData');

            return [
                'msg' => '提交成功！',
            ];
        }

        return error(State::ERROR, '提交失败！');
    }

    /**
     * 搜索用户名的下级代理商.
     *
     * @return array
     */
    public static function agentSearch(): array
    {
        $user = common::getAgent();

        common::checkCurrentUserPrivileges('F_xj');

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGE_SIZE));

        $query = User::query("LOCATE('agent',passport)>0");

        $keyword = request::trim('keyword');
        if ($keyword) {
            $query->whereOr([
                'name LIKE' => "%$keyword%",
                'mobile LIKE' => "%$keyword%",
            ]);
        }

        $superior_guid = '';

        $guid = request::trim('guid');
        if (empty($guid)) {
            $query->where(['superior_id' => $user->getAgentId()]);
        } else {
            $res = agent::getUserByGUID($guid);
            if (empty($res)) {
                return error(State::ERROR, '用户不存在！');
            } else {
                $query->where(['superior_id' => $res->getAgentId()]);
            }

            if ($res->getId() != $user->getId()) {
                $superior = $res->getSuperior();
                if ($superior && $superior->getId() != $user->getAgentId()) {
                    $superior_guid = common::getGUID($superior);
                }
            }
        }

        $total = $query->count();
        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'totalpage' => ceil($total / $page_size),
            'total' => $total,
            'sup_guid' => "$superior_guid",
            'list' => [],
            'remove' => (bool)$user->settings('agentData.misc.power'),
        ];

        if ($total > 0) {
            $agent_levels = settings('agent.levels');
            $query->page($page, $page_size);

            /** @var  userModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $agent = $entry->agent();

                $agent_data = $agent->getAgentData();

                $data = [
                    'guid' => common::getGUID($agent),
                    'name' => $agent->getName(),
                    'avatar' => $agent->getAvatar(),
                    'mobile' => substr_replace($agent->getMobile(), '****', 3, 4),
                    'address' => is_array($agent_data['area']) ? implode('-', array_values($agent_data['area'])) : '',
                    'level' => $agent_levels[$agent_data['level']],
                    'device_count' => Device::query(['agent_id' => $agent->getAgentId()])->count(),
                    'hasB' => User::findOne(['superior_id' => $agent->getAgentId()]) ? 1 : 0,
                ];

                $gsp = $agent->settings('agentData.gsp', []);
                if ($gsp['enabled'] && $gsp['mode'] == 'rel') {
                    foreach ((array)$gsp['rel'] as $level => $val) {
                        $gsp['rel'][$level] = number_format($val / 100, 2);
                    }
                    $data['gsp_rel'] = $gsp['rel'];
                    $data['gsp_rel_mode_type'] = $gsp['mode_type'] ?? 'percent';
                }

                $result['list'][] = $data;
            }
        }

        return $result;
    }

    /**
     * 修改下级代理商名称
     */
    public static function agentUpdate(): array
    {
        common::getAgent();

        $guid = request::trim('guid');

        $res = agent::getUserByGUID($guid);
        if (empty($res)) {
            return error(State::ERROR, '用户不存在！');
        }

        $name = request::trim('name');
        if ($name) {
            $res->updateSettings('agentData.name', $name);
        }

        return ['msg' => '修改成功！'];
    }

    public static function getAgentKeepers(): array
    {
        $user = common::getAgent();
        $agent = $user->isAgent() ? $user : $user->getPartnerAgent();

        $keep_res = Keeper::query(['agent_id' => $agent->getId()])->findAll();

        $data = [];
        /** @var keeperModelObj $item */
        foreach ($keep_res as $item) {
            $user = $item->getUser();
            if ($user) {
                $data[] = [
                    'id' => $user->getId(),
                    'name' => $item->getName(),
                ];
            }
        }

        $s_query = m('settings_user');
        $s_arr = [];
        $s_query = $s_query->query(We7::uniacid([]))->where(['name LIKE' => '%partnerData']);
        $s_res = $s_query->findAll();
        $_reg = '/.+:(.+):.+/';
        /** @var settings_userModelObj $val */
        foreach ($s_res as $val) {
            $s_data = unserialize($val->getData());
            $s_agent = $s_data['agent'] ?? '';
            if ($s_agent == $agent->getId()) {
                $str = $val->getName();
                preg_match($_reg, $str, $mat);
                if (isset($mat[1])) {
                    $s_arr[] = $mat[1];
                }
            }
        }
        $user_res = User::query()->where('id IN ('.implode(',', $s_arr).')')->findAll();
        /** @var userModelObj $item */
        foreach ($user_res as $item) {
            $data[] = [
                'id' => $item->getId(),
                'name' => $item->getNickname(),
            ];
        }

        $data[] = [
            'id' => $agent->getId(),
            'name' => $agent->getName(),
        ];

        return ['data' => $data];
    }

    public static function agentStat(): array
    {
        $agent = common::getAgent();

        if ($agent->isAgent() || $agent->isPartner()) {
            if ($agent->isPartner()) {
                $agent = $agent->getPartnerAgent();
            }

            $dt = new DateTime('today');
            $today_st = $dt->getTimestamp();

            $dt->modify('yesterday');
            $yesterday_st = $dt->getTimestamp();

            $dt->setTimestamp($today_st);
            $dt->modify('tomorrow');
            $tomorrow_st = $dt->getTimestamp();

            $dt->setTimestamp($today_st);
            $dt->modify('first day of this month');
            $this_month_first_st = $dt->getTimestamp();
            $dt->modify('first day of next month');
            $next_month_first_st = $dt->getTimestamp();

            $result = [];

            $w = request::str('w');
            if (empty($w) || $w == 'today') {
                $result[empty($w) ? 'today' : 'w'] = self::getAgentStat($agent, $today_st, $tomorrow_st);
            }

            if (empty($w) || $w == 'yesterday') {
                $result[empty($w) ? 'yesterday' : 'w'] = self::getAgentStat($agent, $yesterday_st, $today_st);
            }

            if (empty($w) || $w == 'month') {
                $result[empty($w) ? 'month' : 'w'] = self::getAgentStat(
                    $agent,
                    $this_month_first_st,
                    $next_month_first_st
                );
            }

            return $result;
        }

        return error(State::ERROR, '没有权限！');
    }

    public static function removeAgent(): array
    {
        $op_user = common::getAgent();

        if ($op_user->isAgent() || $op_user->isPartner()) {
            if ($op_user->settings('agentData.misc.power')) {
                $user_id = request('agent');

                if ($user_id) {
                    $res = Util::transactionDo(
                        function () use ($user_id) {
                            $user = agent::getUserByGUID($user_id);
                            if ($user) {
                                return \zovye\Agent::remove($user);
                            }

                            return err('找不到个代理商！');
                        }
                    );

                    if (!is_error($res)) {
                        return ['message' => '已取消用户代理身份！'];
                    }
                }

                return error(State::ERROR, empty($res['message']) ? '操作失败！' : $res['message']);
            }

            return error(State::ERROR, '没有操作权限！');
        }

        return error(State::ERROR, '只有代理商才能保存运营人员信息！');
    }

    public static function agentSub(): array
    {
        $agent = common::getAgent();
        if ($agent->isAgent() || $agent->isPartner()) {
            if ($agent->isPartner()) {
                $agent = $agent->getPartnerAgent();
            }

            $agent_ids = \zovye\Agent::getAllSubordinates($agent);

            $result = [];
            if (!empty($agent_ids)) {
                $query = \zovye\Agent::query();
                $keyword = request::trim('keyword');

                $query->where('id IN('.implode(',', $agent_ids).')');

                $total = $query->count();
                $result = [
                    'total' => $total,
                    'list' => [],
                    'remove' => (bool)$agent->settings('agentData.misc.power'),
                ];

                if ($total > 0) {
                    $agent_levels = settings('agent.levels');
                    /** @var  userModelObj $entry */
                    foreach ($query->findAll() as $entry) {
                        $agent = $entry->agent();
                        $agent_data = $agent->getAgentData();
                        if ($keyword) {
                            $h_key = false;
                            if (strpos($entry->getNickname(), $keyword) !== false) {
                                $h_key = true;
                            }
                            if (strpos($entry->getMobile(), $keyword) !== false) {
                                $h_key = true;
                            }
                            $a_name = $agent_data['name'] ?: $agent->getNickname();
                            if (strpos($a_name, $keyword) !== false) {
                                $h_key = true;
                            }
                        } else {
                            $h_key = true;
                        }
                        if ($h_key) {
                            $data = [
                                'guid' => common::getGUID($agent),
                                'name' => $agent->getName(),
                                'avatar' => $agent->getAvatar(),
                                'mobile' => substr_replace($agent->getMobile(), '****', 3, 4),
                                'address' => is_array($agent_data['area']) ? implode(
                                    '-',
                                    array_values($agent_data['area'])
                                ) : '',
                                'level' => $agent_levels[$agent_data['level']],
                                'device_count' => Device::query(['agent_id' => $agent->getAgentId()])->count(),
                                'hasB' => User::findOne(['superior_id' => $agent->getAgentId()]) ? 1 : 0,
                            ];

                            $gsp = $agent->settings('agentData.gsp', []);
                            if ($gsp['enabled'] && $gsp['mode'] == 'rel') {
                                foreach ((array)$gsp['rel'] as $level => $val) {
                                    $gsp['rel'][$level] = number_format($val / 100, 2);
                                }
                                $data['gsp_rel_mode_type'] = $gsp['mode_type'] ?? 'percent';
                            }

                            $result['list'][] = $data;
                        }
                    }
                }
            }

            return $result;
        }

        return error(State::ERROR, '获取列表失败！');
    }

    public static function setAgentProfile(): array
    {
        $user = common::getAgent();
        $agent = $user->isAgent() ? $user->Agent() : $user->getPartnerAgent();

        if ($agent) {
            $agent->updateSettings('agentData.misc.siteTitle', request::trim('siteTitle'));
            $agent->updateSettings('agentData.misc.auto_ref', request::trim('auto_ref'));
            $agent->updateSettings('agentData.device.remainWarning', request::trim('remainWarning'));
        }

        if ($agent->save()) {
            return ['status' => true, 'msg' => '操作成功！'];
        } else {
            return error(State::ERROR, '操作失败！');
        }
    }

    public static function getAgentProfile(): array
    {
        $user = common::getAgent();
        $agent = $user->isAgent() ? $user->Agent() : $user->getPartnerAgent();

        $result = [];

        if ($agent) {
            $result['siteTitle'] = strval($agent->settings('agentData.misc.siteTitle'));
            $result['auto_ref'] = intval($agent->settings('agentData.misc.auto_ref'));
            $result['remainWarning'] = intval($agent->settings('agentData.device.remainWarning'));
        }

        return $result;
    }

    public static function getAgentStat($agent, $s_ts, $e_ts): array
    {
        return Util::cachedCall(30, function () use ($agent, $s_ts, $e_ts) {
            $query = Order::query([
                'agent_id' => $agent->getId(),
                'createtime >=' => $s_ts,
                'createtime <' => $e_ts,
            ]);

            list($priceTotal, $orderTotal) = $query->get(['sum(price)', 'count(*)']);

            $commissionTotal = CommissionBalance::query([
                'openid' => $agent->getOpenid(),
                'src' => [
                    CommissionBalance::ORDER_FREE,
                    CommissionBalance::ORDER_BALANCE,
                    CommissionBalance::ORDER_WX_PAY,
                    CommissionBalance::ORDER_REFUND,
                    CommissionBalance::GSP,
                    CommissionBalance::BONUS,
                ],
                'createtime >=' => $s_ts,
                'createtime <' => $e_ts,
            ])->get('sum(x_val)');

            return [
                'price_all' => intval($priceTotal),
                'order' => intval($orderTotal),
                'comm' => intval($commissionTotal),
            ];
        }, $agent->getId(), $s_ts, $e_ts);
    }

    public static function loginQR(): array
    {
        $url = Util::murl('agent', [
            'op' => 'login_scan',
            'uniq' => Util::random(32),
        ]);

        return [
            'data' => $url,
        ];
    }

    public static function loginScan(): array
    {
        $uniq = request::str('uniq');
        if (empty($uniq)) {
            return error(State::ERROR, '参数错误！');
        }

        $res = common::getDecryptedWxUserData();
        if (is_error($res)) {
            return error(State::ERROR, '系统错误！');
        }

        $mobile = $res['purePhoneNumber'] ?? $res['phoneNumber'];
        $session_key = $res['session_key'];

        if (empty($mobile)) {
            return error(State::ERROR, '获取用户手机号码失败，请稍后再试！');
        }

        $user = User::findONe(['mobile' => $mobile]);
        if (empty($user)) {
            return error(State::ERROR, '用户不存在！');
        }

        if ($res['config'] && !$user->isWxAppAllowed($res['config']['key'])) {
            return error(State::ERROR, '登录失败，无法使用这个小程序！');
        }

        if ($user->isBanned()) {
            return error(State::ERROR, '用户暂时无法登录！');
        }

        if (!($user->isAgent() || $user->isPartner())) {
            return error(State::ERROR, '您还不是我们的代理商??！');
        }

        //清除原来的登录信息
        $query = LoginData::agentWeb(['user_id' => $user->getId()]);
        foreach ($query->findAll() as $entry) {
            $entry->destroy();
        }

        $token = sha1(time()."$mobile$session_key");

        $data = [
            'src' => LoginData::AGENT_WEB,
            'user_id' => $user->getId(),
            'session_key' => $session_key,
            'openid_x' => $uniq,
            'token' => $token,
        ];

        if (LoginData::create($data)) {
            return (['msg' => '登录成功！']);
        }

        return error(State::ERROR, '登录失败，无法创建登录数据！');
    }

    public static function loginPoll(): array
    {
        $uniq = request('uniq');
        if (empty($uniq)) {
            return error(State::ERROR, '参数错误！');
        }

        /** @var login_dataModelObj $res */
        $res = LoginData::findOne(['src' => LoginData::AGENT_WEB, 'openid_x' => $uniq]);
        if (empty($res)) {
            return error(State::ERROR, '请先扫描网页二维码！');
        }

        $user = User::get($res->getUserId());
        if (empty($user) || $user->isBanned()) {
            return error(State::ERROR, '暂时无法登录！');
        }

        $res->setOpenidX($user->getOpenid());
        $res->save();

        return [
            'token' => $res->getToken(),
            'id' => $user->getId(),
            'nickname' => $user->getNickname(),
            'avatar' => $user->getAvatar(),
        ];
    }

    public static function userIncome(): array
    {
        //one month
        $user = agent::getUserByGUID(request('guid'));
        if ($user) {
            $condition['agent_id'] = $user->getId();
            $condition['createtime >='] = (new DateTimeImmutable('first day of this month 00:00'))->getTimestamp();

            $data = [];
            $total = [
                'income' => 0,
            ];

            $query = Order::query($condition);

            /** @var orderModelObj $item */
            foreach ($query->findAll() as $item) {
                $amount = $item->getPrice();
                $create_date = date('Y-m-d', $item->getCreatetime());
                if (!isset($data[$create_date])) {
                    $data[$create_date]['income'] = 0;
                }
                $data[$create_date]['income'] += $amount;
                $total['income'] += $amount;
            }

            $list = [];
            foreach ($data as $date => $val) {
                $list[] = [
                    'date' => $date,
                    'income' => $val['income'],
                ];
            }

            usort($list, function ($a, $b) {
                if ($a == $b) {
                    return 0;
                }

                return ($a < $b) ? -1 : 1;
            });

            return [
                'data' => $data,
                'list' => $list,
                'total' => $total['income'],
            ];
        }

        return error(State::ERROR, '获取列表失败！');
    }

    public static function getUserQRCode(): array
    {
        $user = common::getAgentOrKeeper();
        if ($user instanceof keeperModelObj) {
            return common::getUserQRCode($user->getUser());
        }
        return common::getUserQRCode($user);
    }

    public static function updateUserQRCode(): array
    {
        $user = common::getAgentOrKeeper();
        $type = request::str('type');
        if ($user instanceof keeperModelObj) {
            return common::updateUserQRCode($user->getUser(), $type);
        }
        return common::updateUserQRCode($user, $type);
    }

    public static function aliAuthCode()
    {
        $auth_code = request::str('authcode');

        $aop = new AopClient();
        $aop->appId = settings('ali.appid');
        $aop->rsaPrivateKey = settings('ali.prikey');
        $aop->alipayrsaPublicKey = settings('ali.pubkey');

        $request = new AlipaySystemOauthTokenRequest();
        $request->setGrantType('authorization_code');
        $request->setCode($auth_code);

        try {
            $result = $aop->execute($request);

            return json_encode($result);
        } catch (Exception $e) {
            return ['msg' => $e->getMessage()];
        }
    }

    public static function homepageOrderStat(): array
    {
        $user = common::getAgent();
        $agent_id = $user->getAgentId();
        if (request::has('start')) {
            $s_date = DateTime::createFromFormat('Y-m-d H:i:s', request::str('start').' 00:00:00');
        } else {
            $s_date = new DateTime('first day of this month 00:00:00');
        }

        if (request::has('end')) {
            $e_date = DateTime::createFromFormat('Y-m-d H:i:s', request::str('end').' 00:00:00');
            $e_date->modify('next day');
        } else {
            $e_date = new DateTime('first day of next month 00:00:00');
        }

        $device_id = request::int('deviceid');
        if ($device_id > 0) {
            $device = Device::get($device_id);
            if (empty($device) || $device->getAgentId() != $agent_id) {
                return err('找不到这个设备！');
            }
        }

        return Util::cachedCall(30, function () use ($agent_id, $s_date, $e_date, $device_id) {

            $condition = [
                'agent_id' => $agent_id,
                'src' => Order::PAY,
                'createtime >=' => $s_date->getTimestamp(),
                'createtime <' => $e_date->getTimestamp(),
            ];

            if ($device_id > 0) {
                $condition['device_id'] = $device_id;
            }

            $data = [];
            $total = [
                'income' => 0,
                'refund' => 0,
                'receipt' => 0,
                'wx_income' => 0,
                'wx_refund' => 0,
                'wx_receipt' => 0,
                'ali_income' => 0,
                'ali_refund' => 0,
                'ali_receipt' => 0,
            ];

            $res = Order::query($condition)->findAll();

            /** @var orderModelObj $item */
            foreach ($res as $item) {
                $amount = $item->getCommissionPrice();

                $create_at = date('Y-m-d', $item->getCreatetime());
                if (!isset($data[$create_at])) {
                    $data[$create_at]['income'] = 0;
                    $data[$create_at]['refund'] = 0;
                    $data[$create_at]['receipt'] = 0;
                    $data[$create_at]['wx_income'] = 0;
                    $data[$create_at]['wx_refund'] = 0;
                    $data[$create_at]['wx_receipt'] = 0;
                    $data[$create_at]['ali_income'] = 0;
                    $data[$create_at]['ali_refund'] = 0;
                    $data[$create_at]['ali_receipt'] = 0;
                }

                $is_alipay = User::isAliUser($item->getOpenid());

                $data[$create_at]['income'] += $amount;
                $total['income'] += $amount;
                if ($is_alipay) {
                    $data[$create_at]['ali_income'] += $amount;
                    $total['ali_income'] += $amount;
                } else {
                    $data[$create_at]['wx_income'] += $amount;
                    $total['wx_income'] += $amount;
                }
                if ($item->getExtraData('refund')) {
                    //如果是退款
                    $data[$create_at]['refund'] += $amount;
                    $total['refund'] += $amount;
                    if ($is_alipay) {
                        $data[$create_at]['ali_refund'] += $amount;
                        $total['ali_refund'] += $amount;
                    } else {
                        $data[$create_at]['wx_refund'] += $amount;
                        $total['wx_refund'] += $amount;
                    }
                } else {
                    $data[$create_at]['receipt'] += $amount;
                    $total['receipt'] += $amount;
                    if ($is_alipay) {
                        $data[$create_at]['ali_receipt'] += $amount;
                        $total['ali_receipt'] += $amount;
                    } else {
                        $data[$create_at]['wx_receipt'] += $amount;
                        $total['wx_receipt'] += $amount;
                    }
                }
            }

            krsort($data);

            $format_data = [];
            foreach ($data as $k => $v) {
                $v['date'] = $k;
                $format_data[] = $v;
            }

            $devices = Util::cachedCall(300, function () use ($agent_id) {
                $devices = [];
                $query = Device::query(['agent_id' => $agent_id]);
                /** @var deviceModelObj $device */
                foreach ($query->findAll() as $device) {
                    $devices[] = [
                        'id' => $device->getId(),
                        'name' => $device->getName(),
                        'imei' => $device->getImei(),
                    ];
                }

                return $devices;
            });

            return [
                'data' => $format_data,
                'total' => $total,
                'devices' => $devices,
                's_date' => $s_date->format('Y-m-d'),
                'e_date' => $e_date->format('Y-m-d'),
                'deviceid' => $device_id,
            ];
        }, $agent_id, $s_date->getTimestamp(), $e_date->getTimestamp(), $device_id);
    }

    public static function homepageDefault(): array
    {
        $user = common::getAgent();

        return Util::cachedCall(30, function () use ($user) {

            $condition = [];
            $condition['agent_id'] = $user->getAgentId();

            $device_stat = [];

            $time_less_15 = new DateTime('-15 min');
            $power_time = $time_less_15->getTimestamp();
            $device_stat['all'] = Device::query($condition)->count();
            $device_stat['on'] = Device::query($condition)->where(
                'last_ping IS NOT NULL AND last_ping > '.$power_time
            )->count();
            $device_stat['off'] = $device_stat['all'] - $device_stat['on'];

            $data['all']['n'] = 0;

            $uid_data = [
                'api' => 'homepage',
                'name' => 'order_stats',
                'agent' => $user->getAgentId(),
            ];

            $countFN = function (DateTimeInterface $begin = null, DateTimeInterface $end = null) use ($user) {
                $cond = [
                    'agent_id' => $user->getAgentId(),
                ];
                if ($begin) {
                    $cond['createtime >='] = $begin->getTimestamp();
                }
                if ($end) {
                    $cond['createtime <'] = $end->getTimestamp();
                }

                return Order::query($cond)->count();
            };

            $today = new DateTime('today');
            $uid_data['day'] = $today->format('Y-m-d');
            $data['today']['n'] = Cache::fetch($uid_data, function () use ($countFN, $today) {
                return $countFN($today);
            }, Cache::ResultExpiredAfter(10));

            $yesterday = new DateTime('yesterday');
            $uid_data['day'] = $yesterday->format('Y-m-d');
            $data['yesterday']['n'] = Cache::fetch($uid_data, function () use ($countFN, $today, $yesterday) {
                return $countFN($yesterday, $today);
            });

            //统计本月订单数量
            $data['month']['n'] = $data['today']['n'];

            $begin = new DateTimeImmutable('today');
            $current_month_label = $today->format('Y-m-d');

            for ($i = 0; $i < 31; $i++) {

                $end = $begin->modify('-1 day');

                $label = $end->format('Y-m-d');
                if ($label != $current_month_label) {
                    break;
                }

                $uid_data['day'] = $label;

                $res = Cache::fetch($uid_data, function () use ($countFN, $begin, $end) {
                    return $countFN($end, $begin);
                });
                if (is_error($res)) {
                    return $res;
                }

                $data['month']['n'] += $res;
            }

            //全部统计
            $uid_data['day'] = 'all';
            $data['all']['n'] = $data['today']['n'];

            $res = Cache::fetch($uid_data, function () use ($countFN, $today) {
                return $countFN(null, $today);
            }, Cache::ResultExpiredAt('tomorrow'));

            if (is_error($res)) {
                return $res;
            }
            $data['all']['n'] += $res;

            return ['device_stat' => $device_stat, 'data' => $data];
        }, $user->getId());
    }

    public static function repair()
    {
        $user = common::getAgent();
        $agent = $user->isPartner() ? $user->getPartnerAgent() : $user;

        $repairData = $agent->settings('repair', []);

        $cleanFN = function () use ($agent) {
            $agent->updateSettings('repair', []);
            $agent->save();
        };

        if (is_error($repairData['error'])) {
            $cleanFN();

            return $repairData['error'];
        }

        if ($repairData['status'] == 'finished') {
            $cleanFN();

            return ['state' => '', 'msg' => '刷新已完成！'];
        }

        if ($repairData['status'] == 'busy') {
            return [
                'state' => 'busy',
                'msg' => '正在刷新缓存中，请稍等！',
            ];
        }

        if (Job::repairAgentMonthStats($agent->getId(), request::str('month'))) {
            $agent->updateSettings('repair', [
                'status' => 'busy',
            ]);
            $agent->save();

            return ['state' => 'busy', 'msg' => '已启动后台刷新任务，请耐心等待完成！'];
        }

        return error(State::ERROR, '无法启动刷新任务，请联系管理员！');
    }
}
