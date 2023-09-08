<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use DateTime;
use DateTimeImmutable;
use Exception;
use zovye\App;
use zovye\CommissionBalance;
use zovye\Config;
use zovye\DBUtil;
use zovye\Device;
use zovye\DeviceUtil;
use zovye\GDCVMachine;
use zovye\Inventory;
use zovye\Locker;
use zovye\Log;
use zovye\model\deviceModelObj;
use zovye\Goods;
use zovye\Group as ZovyeGroup;
use zovye\Order;
use zovye\Replenish;
use zovye\Request;
use zovye\JSON;
use zovye\model\keeperModelObj;
use zovye\LoginData;
use zovye\model\replenishModelObj;
use zovye\Stats;
use zovye\User;
use zovye\Util;
use zovye\We7;
use function zovye\err;
use function zovye\error;
use function zovye\request;
use function zovye\is_error;
use function zovye\m;
use function zovye\settings;

class keeper
{
    /**
     *
     * @return keeperModelObj
     */
    public static function getKeeper(): keeperModelObj
    {
        static $keeper = null;
        if ($keeper) {
            return $keeper;
        }

        $user = common::getUser();
        if (!\zovye\Keeper::exists($user)) {
            JSON::fail(['msg' => '不是运营人员！']);
        }

        return $user->getKeeper();
    }

    /**
     * 运营人员登录.
     *
     * @return array
     */
    public static function keeperLogin(): array
    {
        $res = common::getDecryptedWxUserData();
        if (is_error($res)) {
            Log::error('wxapi', $res);

            return err('登录失败，请稍后再试！[500]');
        }

        $openid = strval($res['openId']);
        if (empty($openid)) {
            return err('登录失败，无法获取用户openid！');
        }

        $user = User::get($openid, true);
        if ($user) {
            if (empty($user->getMobile())) {
                $mobile = strval($res['phoneNumber']);
                if (empty($mobile)) {
                    return error(1001, '用户没有绑定手机号码！');
                }
                $user->setMobile($mobile);
                $user->save();
            } else {
                $mobile = $user->getMobile();
            }
        } else {
            $mobile = strval($res['phoneNumber']);
            User::create([
                'app' => User::WxAPP,
                'openid' => $openid,
                'nickname' => '微信用户',
                'avatar' => '',
                'mobile' => $mobile,
                'createtime' => time(),
            ]);
        }

        if (empty($mobile)) {
            return err('获取手机号码失败，请稍后再试！');
        }

        $h5_user = User::findOne(['mobile' => $mobile, 'app' => User::WX]);
        if (empty($h5_user)) {
            $url = Util::murl('keeper', ['openid' => $user->getOpenid(), 'mobile' => $mobile]);
            JSON::fail(['msg' => "手机号码{$mobile}还没有绑定用户，请立即绑定！", 'url' => $url]);
        }

        $query = \zovye\Keeper::query(['mobile' => $mobile]);
        if (empty($query->count())) {
            return err("手机号码{$mobile}没有对应的运营人员信息！");
        }

        $keeper_data = [];

        /** @var keeperModelObj $keeper */
        foreach ($query->findAll() as $keeper) {
            //清除原来的登录信息
            foreach (LoginData::keeper(['user_id' => $keeper->getId()])->findAll() as $entry) {
                $entry->destroy();
            }
            $agent = $keeper->getAgent();
            if ($agent) {
                if ($res['config'] && !$agent->isWxAppAllowed($res['config']['key'])) {
                    continue;
                }
                $keeper_data[] = [
                    'id' => $keeper->getId(),
                    'user_id' => $h5_user->getId(),
                    'name' => $keeper->getName(),
                    'agent' => [
                        'id' => $agent->getId(),
                        'name' => $agent->getName(),
                        'avatar' => $agent->getAvatar(),
                    ],
                ];
            }
        }

        if (empty($keeper_data)) {
            return err('登录失败，请稍后再试！[501]');
        }

        $token = Util::getTokenValue();

        $data = [
            'src' => LoginData::KEEPER,
            'user_id' => 0,
            'session_key' => strval($res['session_key']),
            'openid_x' => $h5_user->getOpenid(),
            'token' => $token,
        ];

        if (count($keeper_data) == 1) {
            $keeper = current($keeper_data);
            $data['user_id'] = $keeper['id'];
        }

        if (LoginData::create($data)) {
            $result = [
                'token' => $token,
                'profile' => $keeper_data,
                'msg' => '登录成功!',
            ];
            $agreement = Config::agent('agreement.keeper', []);
            if ($agreement && $agreement['enabled']) {
                $result['agreement'] = $agreement['content'];
            }

            return $result;
        }

        return err('登录失败，请稍后再试！[502]');
    }

    /**
     * 保存运营人员信息.
     *
     * @return array
     */
    public static function setKeeper(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_yy');

        $id = Request::int('id');
        $name = Request::trim('name');
        $mobile = Request::trim('mobile');

        if (empty($name) || empty($mobile) || !preg_match(REGULAR_TEL, $mobile)) {
            return err('请输入正确的姓名和手机号码！');
        }

        if ($id) {
            if (\zovye\Keeper::findOne(['mobile' => $mobile, 'id <>' => $id])) {
                return err('手机号码已经被其它运营人员使用！');
            }
            /** @var keeperModelObj $keeper */
            $keeper = \zovye\Keeper::findOne(['id' => $id, 'agent_id' => $user->getAgentId()]);
            if ($keeper) {
                if ($name != $keeper->getName()) {
                    $keeper->setName($name);
                }
                if ($mobile != $keeper->getMobile()) {
                    $keeper->setMobile($mobile);
                }
            }
        } else {
            if (\zovye\Keeper::findOne(['mobile' => $mobile])) {
                return err('手机号码已经被其它运营人员使用！');
            }
            /** @var keeperModelObj $keeper */
            $keeper = \zovye\Keeper::create([
                'agent_id' => $user->getAgentId(),
                'name' => $name,
                'mobile' => $mobile,
            ]);
        }

        if ($keeper) {
            $keeper_user = $keeper->getUser();
            if ($keeper_user && !$keeper_user->isKeeper()) {
                $keeper_user->setKeeper();
                $keeper_user->save();
            }

            if ($keeper->save()) {
                return ['msg' => empty($id) ? '请联系运营人员登录并绑定手机号！' : '运营人员资料保存成功！'];
            }
        }

        return err('保存数据出错！');
    }

    /**
     * 删除运营人员.
     *
     * @return array
     */
    public static function deleteKeeper(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_yy');

        return DBUtil::transactionDo(function () use ($user) {
            $id = Request::int('id');

            /** @var keeperModelObj $keeper */
            $keeper = \zovye\Keeper::findOne(['id' => $id, 'agent_id' => $user->getAgentId()]);
            if (empty($keeper)) {
                return err('找不到这个运营人员！');
            }

            $query = Device::keeper($keeper)->where(['agent_id' => $user->getAgentId()]);

            /** @var deviceModelObj $entry */
            foreach ($query->findAll() as $entry) {
                if (!$entry->removeKeeper($keeper)) {
                    return err('删除运营人员设备失败！');
                }
            }

            $keeper_user = $keeper->getUser();
            if ($keeper_user) {
                $keeper_user->setKeeper(false);
            }

            $keeper->destroy();

            return ['msg' => '删除运营人员成功！'];
        });
    }

    /**
     * 获取运营人员列表.
     *
     * @return array
     */
    public static function keepers(): array
    {
        $agent = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_yy');

        if (Request::has('deviceId')) {
            $device = \zovye\api\wx\device::getDevice(Request::int('deviceId'));

            if (empty($device)) {
                return [];
            }

            if (!$device->getAgentId()) {
                return [];
            }

            $agent = $device->getAgent();
        }

        $keepers = [];
        $query = \zovye\Keeper::query(['agent_id' => $agent->getAgentId()]);

        if (Request::has('keyword')) {
            $keyword = Request::trim('keyword');
            if ($keyword) {
                $query->whereOr([
                    'name LIKE' => "%$keyword%",
                    'mobile LIKE' => "%$keyword%",
                ]);
            }
        }

        $query->orderBy('id desc');

        /** @var keeperModelObj $keeper */
        foreach ($query->findAll() as $keeper) {
            $data = [
                'id' => $keeper->getId(),
                'name' => $keeper->getName(),
                'mobile' => $keeper->getMobile(),
            ];
            $user = $keeper->getUser();
            if ($user) {
                $data['user_id'] = $user->getId();
            }
            $keepers[] = $data;
        }

        return $keepers;
    }

    public static function removeDevicesFromKeeper(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_yy');

        return DBUtil::transactionDo(function () use ($user) {
            $keeper_id = Request::int('keeperid');

            $keeper = \zovye\Keeper::get($keeper_id);
            if (empty($keeper) || $keeper->getAgentId() != $user->getAgentId()) {
                return err('找不到这个运营人员！');
            }

            $device_ids = [];
            if (Request::is_array('devices')) {
                $device_ids = array_values(Request::array('devices'));
            }

            if (!empty($device_ids)) {
                $query = Device::query([
                    'keeper_id' => $keeper->getId(),
                    'agent_id' => $user->getAgentId(),
                    'imei' => $device_ids,
                ]);

                /** @var deviceModelObj $entry */
                foreach ($query->findAll() as $entry) {
                    $entry->removeKeeper($keeper);
                }
            }

            return ['msg' => '操作成功！'];
        });
    }

    /**
     * 分配设备到指定的运营人员.
     *
     * @return array
     */
    public static function assignDevicesToKeeper(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_yy');

        return DBUtil::transactionDo(function () use ($user) {
            $device_ids = [];

            if (Request::is_array('devices')) {
                $device_ids = array_values(Request::array('devices'));
            }

            if (Request::is_array('groups')) {
                $group_ids = array_values(Request::array('groups'));
                if ($group_ids) {
                    $query = Device::query(['group_id' => $group_ids, 'agent_id' => $user->getAgentId()]);

                    /** @var deviceModelObj $entry */
                    foreach ($query->findAll() as $entry) {
                        $device_ids[] = $entry->getImei();
                    }

                    $device_ids = array_unique($device_ids);
                }
            }

            if (Request::has('commission')) {
                $commission = Request::str('commission', '', true);

                //%结尾表示百分比，*表示固定金额
                if (substr($commission, -1) == '%') {
                    $commission = rtrim($commission, '%');
                    $percent = max(0, min(10000, intval($commission * 100)));
                    $set_commission = function (&$data) use ($percent) {
                        $data['percent'] = $percent;
                        unset($data['fixed']);

                        return $data;
                    };
                } else {
                    $commission = rtrim($commission, '*');
                    $fixed = max(0, intval($commission));
                    $set_commission = function (&$data) use ($fixed) {
                        $data['fixed'] = $fixed;
                        unset($data['percent']);

                        return $data;
                    };
                }
            } else {
                $set_commission = function ($data) {
                    return $data;
                };
            }

            //way 分佣时机：Keeper::COMMISSION_RELOAD，补货时 Keeper::COMMISSION_ORDER，订单生成时
            $way = Request::int('way', -1);
            $kind = Request::int('kind', -1);

            $keeper_id = Request::int('keeperid');
            $keeper = \zovye\Keeper::get($keeper_id);
            if (empty($keeper) || $keeper->getAgentId() != $user->getAgentId()) {
                return err('找不到这个运营人员！');
            }

            $query = Device::query([
                'agent_id' => $user->getAgentId(),
                'imei' => $device_ids,
            ]);

            /** @var deviceModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $keeper_data = $entry->getKeeperData($keeper);
                if ($way != -1) {
                    $keeper_data['way'] = $way;
                }
                if ($kind != -1) {
                    $keeper_data['kind'] = $kind;
                }
                $entry->setKeeper($keeper, $set_commission($keeper_data));
            }

            return ['msg' => '分配成功！'];
        });
    }

    /**
     * 提现.
     *
     * @return array
     */
    public static function keeperWithdraw(): array
    {
        $keeper = keeper::getKeeper();
        $user = $keeper->getUser();

        if (!$user) {
            return err('找不到运营人员关联的用户，请联系客服！');
        }

        if (!empty(settings('commission.withdraw.bank_card'))) {
            if (empty($user->settings('agentData.bank'))) {
                return err('请先绑定银行卡！');
            }
        }

        //如果用户是运营人员，则需要检查对应代理商账户是否异常
        $agent = $keeper->getAgent();
        if (empty($agent)) {
            return err('检查身份失败！');
        }

        //如果运营人员补货导致代理商余额小于零，则不允许运营人员提现
        if ($agent->getCommissionBalance()->total() < 0) {
            return err('代理商账户异常，请联系代理商！');
        }

        if ($agent->isPaymentConfigEnabled()) {
            return err('提现申请被拒绝，请联系代理商！');
        }

        $total = round(Request::float('amount', 0, 2) * 100);

        return balance::balanceWithdraw($user, $total);
    }

    /**
     * 获取银行信息.
     *
     * @return array
     */
    public static function getKeeperBank(): array
    {
        $result = [];

        $keeper = keeper::getKeeper();
        $user = $keeper->getUser();
        if ($user) {
            return common::getUserBank($user);
        }

        return $result;
    }

    /**
     * 设置提现银行信息.
     *
     * @return array
     */
    public static function setKeeperBank(): array
    {
        $keeper = keeper::getKeeper();
        $user = $keeper->getUser();
        if ($user) {
            return common::setUserBank($user);
        }

        return err('无法保存，请联系管理员！');
    }

    public static function getOrders(): array
    {
        $keeper = keeper::getKeeper();
        $condition = [
            'agent_id' => $keeper->getAgentId(),
        ];

        if (Request::has('deviceid')) {
            $device = \zovye\api\wx\device::getDevice(request('deviceid'));
            if (is_error($device)) {
                return $device;
            }
            $condition['device_id'] = $device->getId();
        } else {
            $condition['device_id'] = [];

            $query = Device::keeper($keeper)->where(['agent_id' => $keeper->getAgentId()]);
            foreach ($query->findAll() as $device) {
                $condition['device_id'][] = $device->getId();
            }
        }

        return agent::getAssociatedOrderList($condition);
    }

    /**
     * 运营人员统计信息.
     *
     * @return array
     */
    public static function brief(): array
    {
        $keeper = keeper::getKeeper();

        $result = [
            'devices' => [
                'low' => 0,
                'error' => 0,
            ],
            'stats' => [
                'today' => 0,
                'this_month' => 0,
                'all' => 0,
            ],
            'logs' => [],
        ];
        $user = $keeper->getUser();
        if ($user) {
            $result['balance_formatted'] = number_format($user->getCommissionBalance()->total() / 100, 2, '.', '');

            if (App::isPromoterEnabled()) {
                $referral = $user->getReferral();
                if ($referral) {
                    $result['referral'] = [
                        'code' => $referral->getCode(),
                        'url' => Util::murl('promoter', ['code' => $referral->getCode()]),
                    ];
                }
            }
        }

        if (Request::has('remain')) {
            $remainWarning = max(1, Request::int('remain'));
        } else {
            $remainWarning = settings('device.remainWarning', 0);
        }

        $lowQuery = Device::keeper($keeper)->where(['agent_id' => $keeper->getAgentId(), 'remain <' => $remainWarning]);
        if ($lowQuery->count() > 0) {
            foreach ($lowQuery->findAll() as $entry) {
                if ($entry->settings('extra.keepers') == $keeper->getId()) {
                    ++$result['devices']['low'];
                }
            }
        }

        $errorQuery = Device::keeper($keeper)->where(['agent_id' => $keeper->getAgentId(), 'error_code <>' => 0]);
        if ($errorQuery->count() > 0) {
            foreach ($errorQuery->findAll() as $entry) {
                if ($entry->settings('extra.keepers') == $keeper->getId()) {
                    ++$result['devices']['error'];
                }
            }
        }

        $result['stats']['today'] = (int)Replenish::model()->where(
            We7::uniacid(
                ['keeper_id' => $keeper->getId(), 'createtime >=' => (new DateTimeImmutable('00:00'))->getTimestamp()]
            )
        )->get('sum(num)');

        $result['stats']['this_month'] = (int)Replenish::model()->where(
            We7::uniacid(
                [
                    'keeper_id' => $keeper->getId(),
                    'createtime >=' => (new DateTimeImmutable('first day of this month 00:00'))->getTimestamp(),
                ]
            )
        )->get('sum(num)');

        $result['stats']['all'] = (int)Replenish::model()->where(We7::uniacid(['keeper_id' => $keeper->getId()]))->get(
            'sum(num)'
        );

        $lowQuery = Device::keeper($keeper, \zovye\Keeper::OP)->where(
            ['agent_id' => $keeper->getAgentId(), 'remain <' => $remainWarning]
        );
        $errorQuery = Device::keeper($keeper, \zovye\Keeper::OP)->where(
            ['agent_id' => $keeper->getAgentId(), 'error_code <>' => 0]
        );
        $result['devices']['low'] = $lowQuery->count();
        $result['devices']['error'] = $errorQuery->count();

        $lastQuery = Replenish::model()->where(We7::uniacid(['keeper_id' => $keeper->getId()]))->orderBy(
            'createtime DESC'
        )->limit(10);

        /** @var replenishModelObj $entry */
        foreach ($lastQuery->findAll() as $entry) {
            $device_name = $entry->getExtraData('device.name');
            if (empty($device_name)) {
                $device = Device::get($entry->getDeviceUid(), true);
                if ($device) {
                    $device_name = $device->getName();
                }
            }

            $result['logs'][] = [
                'id' => $entry->getDeviceUid(),
                'devicename' => $device_name,
                'goods' => Goods::data($entry->getGoodsId()),
                'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                'num' => (int)$entry->getNum(),
            ];
        }

        if (App::isFuelingDeviceEnabled()) {
            $cond = [
                'device_id' => [],
            ];

            $query = Device::keeper($keeper)->where(['agent_id' => $keeper->getAgentId()]);
            foreach ($query->findAll() as $device) {
                $cond['device_id'][] = $device->getId();
            }

            if (empty($cond['device_id'])) {
                return [];
            }

            if (Request::has('src')) {
                $cond['src'] = Request::int('src');
            }

            $w = Request::str('w');

            if (empty($w) || $w == 'today') {
                $result['today'] = agent::getUserTodayStats($user ? $user->getOpenid() : '', $cond);
            }

            if (empty($w) || $w == 'yesterday') {
                $result['yesterday'] = agent::getUserYesterdayStats($user ? $user->getOpenid() : '', $cond);
            }

            if (empty($w) || $w == 'month') {
                $result['month'] = agent::getUserMonthStats($user ? $user->getOpenid() : '', $cond);
            }

            if (empty($w) || $w == 'year') {
                $result['year'] = agent::getUserYearStats($user ? $user->getOpenid() : '', $cond);
            }
        }

        return $result;
    }

    /**
     * @return array
     * @throws Exception
     */
    public static function deviceList(): array
    {
        $keeper = keeper::getKeeper();
        $query = Device::keeper($keeper);

        return \zovye\api\wx\device::getDeviceList($keeper->getUser(), $query);
    }

    /**
     * 记录.
     *
     * @return array
     */
    public static function balanceLog(): array
    {
        $keeper = keeper::getKeeper();
        $user = $keeper->getUser();

        if (!$user) {
            return err('找不到运营人员关联的用户！');
        }

        $type = Request::str('type');
        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        return balance::getUserBalanceLog($user, $type, $page, $page_size);
    }

    /**
     * 运营人员缺货设备.
     *
     * @return array
     */
    public static function lowDevices(): array
    {
        $keeper = keeper::getKeeper();

        if (Request::has('remain')) {
            $remainWarning = max(1, Request::int('remain'));
        } else {
            $remainWarning = App::getRemainWarningNum($keeper->getAgent());
        }

        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        $query = Device::keeper($keeper, \zovye\Keeper::OP)->where(['remain <' => $remainWarning]);
        $query->where(['agent_id' => $keeper->getAgentId()]);

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
                $data = [
                    'id' => $entry->getImei(),
                    'name' => $entry->getName(),
                    'address' => $entry->getAddress('<地址未登记>'),
                ];

                $payload = $entry->getPayload();
                if ($payload && $payload['cargo_lanes']) {
                    $data['cargo_lanes'] = $payload['cargo_lanes'];
                } else {
                    $data['cargo_lanes'] = [];
                }

                $result['list'][] = $data;
            }
        }

        return $result;
    }

    /**
     * 运营人员故障设备.
     *
     * @return array
     */
    public static function errorDevices(): array
    {
        $keeper = keeper::getKeeper();

        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        $query = Device::keeper($keeper, \zovye\Keeper::OP)->where(['error_code <>' => 0]);
        $query->where(['agent_id' => $keeper->getAgentId()]);

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
                $last_error = $entry->getLastError();
                $result['list'][] = [
                    'id' => $entry->getImei(),
                    'name' => $entry->getName(),
                    'address' => $entry->getAddress('<地址未登记>'),
                    'errorCode' => intval($last_error['errno']),
                    'errorDesc' => strval($last_error['message']),
                    'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                ];
            }
        }

        return $result;
    }

    /**
     * 运营人员设备详情.
     *
     * @return array
     */
    public static function deviceDetail(): array
    {
        $keeper = keeper::getKeeper();

        $device = Device::find(request('id'), ['imei', 'shadow_id']);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if ($device->getAgentId() != $keeper->getAgentId() || !$device->hasKeeper($keeper)) {
            return err('没有权限执行这个操作！');
        }

        $result = [
            'id' => $device->getImei(),
            'name' => $device->getName(),
            'address' => $device->getAddress('<地址未登记>'),
            'status' => [],
            'device' => [
                'model' => $device->getDeviceModel(),
            ],
            'keeper' => [
                'kind' => $device->getKeeperKind($keeper),
            ],
        ];

        //电量
        $qoe = $device->getQoe();
        if (isset($qoe) && $qoe > 0) {
            $result['status']['qoe'] = intval($qoe);
        }

        //信号强度
        $sig = $device->getSig();
        if ($sig != -1) {
            $result['status']['sig'] = $sig;
        }

        if ($device->isBlueToothDevice()) {
            $result['device']['buid'] = $device->getBUID();
            if ($device->getMAC()) {
                $result['device']['mac'] = $device->getMAC();
            }
            $result['device']['protocol'] = $device->getBlueToothProtocolName();
        } elseif (App::isChargingDeviceEnabled() && $device->isChargingDevice()) {
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

        $payload = $device->getPayload(true);
        if ($payload && is_array($payload['cargo_lanes'])) {
            $result['cargo_lanes'] = $payload['cargo_lanes'];
        } else {
            $result['cargo_lanes'] = [];
        }

        if (App::isDeviceWithDoorEnabled()) {
            $result['doorNum'] = $device->getDoorNum();
        }

        return $result;
    }

    /**
     * 运营人员补货.
     *
     * @return array
     * @throws Exception
     */
    public static function deviceReset(): array
    {
        $keeper = keeper::getKeeper();

        if (!Locker::try("keeper:{$keeper->getId()}")) {
            return err('无法锁定用户，请稍后再试！');
        }

        $device = Device::find(request('id'), ['imei', 'shadow_id']);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if ($device->getAgentId() != $keeper->getAgentId() ||
            !$device->hasKeeper($keeper) ||
            $device->getKeeperKind($keeper) != \zovye\Keeper::OP) {
            return err('没有权限执行这个操作！');
        }

        $agent = $device->getAgent();
        if (empty($agent)) {
            return err('找不到设备代理商！');
        }

        if (!$device->payloadLockAcquire()) {
            return err('设备正忙，请稍后再试！');
        }

        //补货佣金计算函数
        $commission_price_calc = function () {
            return 0;
        };

        list($v, $way, $is_percent) = $keeper->getCommissionValue($device);
        if ($way == \zovye\Keeper::COMMISSION_RELOAD) {
            if ($is_percent) {
                $commission_price_calc = function ($num, $goods_id) use ($v) {
                    $goods = Goods::get($goods_id);
                    $price = $goods ? $goods->getPrice() : 0;

                    return intval(round($num * $price * $v / 100));
                };
            } else {
                $commission_price_calc = function ($num) use ($v) {
                    return intval($v * $num);
                };
            }
        }

        //创建佣金记录
        $create_commission_fn = function ($total) use ($agent, $device, $keeper) {
            if (!$agent->acquireLocker(User::COMMISSION_BALANCE_LOCKER)) {
                return err('无法锁定代理商！');
            }

            if ($agent->getCommissionBalance()->total() < $total) {
                return err('代理商余额不足！');
            }

            if ($agent->getCommissionBalance()->total() >= $total) {
                $keeperUser = $keeper->getUser();

                $r1 = $agent->commission_change(0 - $total, CommissionBalance::RELOAD_OUT, [
                    'device' => $device->getId(),
                    'keeper' => $keeper->getId(),
                    'user' => $keeperUser ? $keeperUser->getId() : -1,
                ]);

                if ($r1 && $r1->update([], true)) {
                    if (!empty($keeperUser)) {
                        $r2 = $keeperUser->commission_change(
                            $total,
                            CommissionBalance::RELOAD_IN,
                            [
                                'device' => $device->getId(),
                                'r1' => $r1->getId(),
                            ]
                        );
                        if ($r2 && $r2->update([], true)) {
                            return true;
                        }
                    }
                }
                throw new Exception('创建佣金失败！');
            }

            return true;
        };

        if (Request::isset('lane')) {
            $lane = Request::int('lane');
            $num = Request::int('num');

            if ($num != 0 && !$agent->allowReduceGoodsNum()) {
                $laneData = $device->getLane($lane);
                if ($laneData && $num < $laneData['num']) {
                    return err('不允许减少商品库存！');
                }
            }

            $data = [
                $lane => $num != 0 ? '@'.$num : 0,
            ];
        } else {
            $data = [];
        }

        return DBUtil::transactionDo(
            function () use ($device, $data, $keeper, $commission_price_calc, $create_commission_fn) {

                //改变库存
                $result = $device->resetPayload($data, "运营人员补货：{$keeper->getMobile()}");
                if (is_error($result)) {
                    return err('保存库存失败！');
                }

                if (App::isInventoryEnabled()) {
                    $user = $keeper->getUser();
                    $v = Inventory::syncDevicePayloadLog($user, $device, $result, '运营人员补货');
                    if (is_error($v)) {
                        return $v;
                    }
                }

                $total = 0;
                foreach ($result as $entry) {
                    //创建补货记录
                    \zovye\Keeper::createReplenish(
                        $keeper,
                        $device,
                        $entry['goodsId'],
                        $entry['org'],
                        $entry['num'],
                        [
                            'device' => [
                                'id' => $device->getId(),
                                'name' => $device->getName(),
                            ],
                        ]
                    );

                    //累计佣金
                    $total += $commission_price_calc($entry['num'], $entry['goodsId']);
                }

                //保存佣金
                if ($total > 0) {
                    $err = $create_commission_fn($total);
                    if (is_error($err)) {
                        Log::error('keeper', [
                            'error' => '创建运营人员补货佣金失败:'.$err['message'],
                            'total' => $total,
                        ]);

                        return $err;
                    }
                }

                $device->updateAppRemain();
                if (!$device->save()) {
                    return err('保存设备数据失败！');
                }

                if (App::isGDCVMachineEnabled()) {
                    GDCVMachine::scheduleUploadDeviceJob($device);
                }

                return ['msg' => '设置成功！'];
            }
        );
    }

    /**
     * 运营人员测试设备.
     *
     * @return array
     */
    public static function deviceTest(): array
    {
        $keeper = keeper::getKeeper();

        $device = Device::find(request('id'), ['imei', 'shadow_id']);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (
            $device->getAgentId() != $keeper->getAgentId() ||
            !$device->hasKeeper($keeper) ||
            $device->getKeeperKind($keeper) != \zovye\Keeper::OP
        ) {
            return err('没有权限！');
        }

        $lane = Request::int('lane');

        //设置params['keeper']后，库存不会减少
        $res = DeviceUtil::test(
            $device,
            $keeper->getUser(),
            $lane,
            [
                'from' => 'wxapp.keeper',
                'keeper' => [
                    'name' => $keeper->getName(),
                ],
            ]
        );

        if (is_error($res)) {
            return $res;
        }

        $resp = ['id' => $device->getImei()];
        if ($device->isBlueToothDevice()) {
            $data = $res['data'];
            if (!empty($data)) {
                $resp['bluetooth'] = [
                    'data' => $data,
                    'hex' => bin2hex(base64_decode($data)),
                ];
            }
            $resp['msg'] = '已发送！';
        } else {
            $resp['msg'] = '出货成功！';
        }

        return $resp;
    }

    /**
     * 运营人员统计
     *
     * @param $keeper keeperModelObj|null
     *
     * @return array
     *
     * @throws
     */
    public static function stats(keeperModelObj $keeper = null): array
    {
        if (is_null($keeper)) {
            $keeper = keeper::getKeeper();
        }

        list($y, $m, $d) = explode('-', request('date'), 3);
        if (!checkdate($m, $d ?: 1, $y)) {
            return err('时间不正确！');
        }

        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'list' => [],
        ];

        $query = Replenish::model()->where(
            We7::uniacid([
                'keeper_id' => $keeper->getId(),
                'agent_id' => $keeper->getAgentId(),
            ])
        );

        if ($d) {
            //请求某天的出货记录
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', "$y-$m-$d 00:00:00");
            $begin = $datetime->getTimestamp();
            $datetime->modify('+1 day');
            $end = $datetime->getTimestamp();

            $query->where(['createtime >=' => $begin, 'createtime <' => $end]);

            $result['total'] = $query->count();
            $result['totalpage'] = ceil($result['total'] / $page_size);

            if ($result['total'] > 0) {
                $query->page($page, $page_size);

                /** @var replenishModelObj $entry */
                foreach ($query->findAll() as $entry) {
                    $device_name = $entry->getExtraData('device.name');
                    if (empty($device_name)) {
                        $device = Device::get($entry->getDeviceUid(), true);
                        if ($device) {
                            $device_name = $device->getName();
                        }
                    }

                    $result['list'][] = [
                        'id' => $entry->getDeviceUid(),
                        'devicename' => $device_name,
                        'createtime_formatted' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                        'num' => (int)$entry->getNum(),
                    ];
                }
            }
        } else {
            //请求一月中每天的出货统计
            $datetime = DateTime::createFromFormat('Y-m-d H:i:s', "$y-$m-01 00:00:00");
            $day = DateTime::createFromFormat('Y-m-d H:i:s', "$y-$m-01 00:00:00");
            $day->modify('+1 month');
            if ($day->getTimestamp() > time()) {
                $day = new DateTime('tomorrow');
            }

            while ($day > $datetime) {
                $ts_end = $day->getTimestamp();

                $day->modify('-1 day');
                $ts_start = $day->getTimestamp();

                $title = $day->format('m-d');

                $cond = We7::uniacid([
                    'keeper_id' => $keeper->getId(),
                    'agent_id' => $keeper->getAgentId(),
                    'createtime >=' => $ts_start,
                    'createtime <' => $ts_end,
                ]);

                if (Replenish::model()->where($cond)->count() > 0) {
                    $total = (int)Replenish::model()->where($cond)->get('sum(num)');
                    $result['list'][$title] = $total;
                }
            }

            $result['totalpage'] = 1;
            $result['total'] = count($result['list']);
        }

        return $result;
    }

    /**
     * 查看运营人员统计
     *
     * @return array
     */
    public static function viewKeeperStats(): array
    {
        $user = common::getAgentOrPartner();

        common::checkCurrentUserPrivileges('F_yy');

        $id = Request::int('id');
        if ($id) {
            /** @var keeperModelObj $keeper */
            $keeper = \zovye\Keeper::get($id);
            if (empty($keeper)) {
                return err('找不到这个运营人员！');
            }

            if ($keeper->getAgentId() != $user->getAgentId()) {
                return err('代理商不匹配！');
            }

            return keeper::stats($keeper);
        }

        return err('请求出错！');
    }

    public static function orderRefund(): array
    {
        if (!settings('agent.order.refund')) {
            return err('不允许退款，请联系管理员！');
        }

        $keeper = keeper::getKeeper();

        $order_id = Request::int('orderid');

        $agent = $keeper->getAgent();
        $order = Order::get($order_id);
        if (empty($order) || $order->getAgentId() != $agent->getId()) {
            return err('找不到这个订单！');
        }

        $device = $order->getDevice();
        if (empty($device) || !$device->hasKeeper($keeper) || $device->getKeeperKind($keeper) != \zovye\Keeper::OP) {
            return err('没有权限管理这个订单！');
        }

        if ($agent->getCommissionBalance()->total() < $order->getPrice()) {
            return err('代理商余额不足，无法退款！');
        }

        $num = Request::int('num');

        $res = Order::refund($order->getOrderNO(), $num, ['message' => '运营人员：'.$keeper->getName()]);
        if (is_error($res)) {
            return err($res['message']);
        }

        return ['msg' => '退款成功！'];
    }

    public static function userStats(): array
    {
        $keeper = keeper::getKeeper();

        $user = $keeper->getUser();
        if (empty($user)) {
            return [];
        }

        try {
            $res = explode('-', Request::str('date'), 3);
            if (empty($res)) {
                return err('请求的时间不正确！');
            } elseif (count($res) == 1) {
                $begin = new DateTimeImmutable(sprintf("%d-01-01 00:00", $res[0]));
                $end = $begin->modify("first day of jan next year");
            } elseif (count($res) == 2) {
                $begin = new DateTimeImmutable(sprintf("%d-%02d-01", $res[0], $res[1]));
                $end = $begin->modify('first day of next month');
            } else {
                $begin = new DateTimeImmutable(sprintf("%d-%02d-%02d", $res[0], $res[1], $res[2]));
                $end = $begin->modify('next day');
            }
        } catch (Exception $e) {
            return err('时间格式不正确！');
        }

        $now = new DateTime();
        if ($end > $now) {
            $end = $now;
        }

        $cond = [
            'device_id' => [],
        ];

        $query = Device::keeper($keeper)->where(['agent_id' => $keeper->getAgentId()]);
        foreach ($query->findAll() as $device) {
            $cond['device_id'][] = $device->getId();
        }

        if (empty($cond['device_id'])) {
            return [];
        }

        if (Request::has('src')) {
            $cond['src'] = Request::int('src');
        }

        return agent::getUserStats($user->getOpenid(), $begin->getTimestamp(), $end->getTimestamp(), $cond);
    }

    public static function commissionStats(): array
    {
        $keeper = keeper::getKeeper();

        $user = $keeper->getUser();
        if (empty($user)) {
            return [];
        }

        if (Request::has('month')) {
            //获取指定月份每天的收入统计
            return Stats::getDayOfMonthCommissionStatsData($user, Request::str('month'));
        }

        $year = Request::str('year', (new DateTime())->format('Y'));

        //获取指定年份每月收入统计
        list($years, $data) = Stats::getUserMonthCommissionStatsOfYear($user, $year);

        return [
            'data' => $data,
            'years' => $years && count($years) > 1 ? $years : [],
            'current' => $year,
        ];
    }
}
