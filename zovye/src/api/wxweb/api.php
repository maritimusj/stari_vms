<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wxweb;

use DateTime;
use zovye\api\wx\misc;
use zovye\App;
use zovye\Config;
use zovye\domain\Account;
use zovye\domain\Advertising;
use zovye\domain\Balance;
use zovye\domain\Delivery;
use zovye\domain\Device;
use zovye\domain\DeviceFeedback;
use zovye\domain\Goods;
use zovye\domain\Mall;
use zovye\domain\Order;
use zovye\domain\Questionnaire;
use zovye\domain\Task;
use zovye\domain\User;
use zovye\Job;
use zovye\JSON;
use zovye\model\balanceModelObj;
use zovye\model\userModelObj;
use zovye\Request;
use zovye\util\DeviceUtil;
use zovye\util\Helper;
use zovye\util\LocationUtil;
use zovye\util\PlaceHolder;
use function zovye\err;
use function zovye\is_error;
use function zovye\isEmptyArray;

class api
{
    public static function nearBy(): array
    {
        return DeviceUtil::getNearBy();
    }

    public static function deviceData(userModelObj $user): array
    {
        $device = Device::get(Request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if($device->isMaintenance()) {
            return err('设备正在维护中！');
        }

        $user->setLastActiveDevice($device);

        return $device->profile();
    }

    /**
     * 获取设备相关的广告
     */
    public static function ads(): array
    {
        $type = Request::int('typeId');
        $num = Request::int('num', 10);

        if (Request::has('deviceId')) {
            $device = Device::get(Request::str('deviceId'), true);
            if (empty($device)) {
                return err('找不到这个设备！');
            }
        }

        $list = DeviceUtil::getAds($device ?? Device::getDummyDevice(), $type, $num);

        if ($type == Advertising::SPONSOR) {
            $total = 0;
            foreach ($list as &$item) {
                $item['title'] = PlaceHolder::replace($item['title'], [
                    'num' => intval($item['data']['num']),
                ]);
                $total += intval($item['data']['num']);
            }

            return [
                'total' => $total,
                'list' => $list,
            ];
        }

        return $list;
    }

    public static function accounts(userModelObj $user): array
    {
        if (Request::has('deviceId')) {
            $device = Device::get(Request::str('deviceId'), true);
            if (empty($device)) {
                return err('找不到这个设备！');
            }
        } else {
            $device = Device::getDummyDevice();
        }

        $include = [];
        if (Request::bool('balance')) {
            $include[] = Account::BALANCE;
        }

        if (Request::bool('commission')) {
            $include[] = Account::COMMISSION;
        }

        if (empty($include)) {
            return [];
        }

        if (Request::is_array('type')) {
            $types = Request::array('type');
        } else {
            if (Request::str('type') == 'all') {
                $types = null;
            } elseif (Request::str('type') == 'normal') {
                $types = [Account::NORMAL, Account::AUTH];
            } else {
                $type = Request::int('type', Account::VIDEO);
                $types = [$type];
            }
        }

        if (Request::is_array('s_type')) {
            $s_types = Request::array('s_type');
        } else {
            if (Request::str('s_type') == 'all') {
                $s_types = null;
            } else {
                $s_types = [];
            }
        }

        $params = [
            'type' => $types,
            's_type' => $s_types,
            'include' => $include,
        ];

        if (Request::has('max')) {
            $params['max'] = Request::int('max');
        }

        return Account::getAvailableList($device, $user, $params);
    }

    public static function goods(userModelObj $user): array
    {
        $device = Device::get(Request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $type = Request::str('type');

        if ($type == 'balance' || $type == 'exchange') {
            $result = $device->getGoodsList($user, [Goods::AllowBalance]);
        } elseif ($type == 'free') {
            $result = $device->getGoodsList($user, [Goods::AllowFree]);
        } else {
            $result = $device->getGoodsAndPackages($user, [Goods::AllowPay]);
        }

        return $result;
    }

    public static function get(userModelObj $user): array
    {
        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            JSON::fail('无法锁定用户，请稍后再试！');
        }

        $device = Device::get(Request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isMcbOnline()) {
            return err('设备不在线！');
        }

        if (LocationUtil::mustValidate($user, $device)) {
            return err('设备位置不在允许的范围内！');
        }

        $goods_id = Request::int('goodsId');
        if (empty($goods_id)) {
            $goods = $device->getGoodsByLane(0);
            if ($goods && $goods['num'] < 1) {
                $goods = $device->getGoods($goods['id']);
            }
        } else {
            $goods = $device->getGoods($goods_id);
        }

        if (empty($goods)) {
            return err('找不到商品！');
        }

        if ($goods['num'] < 1) {
            return err('商品数量不足！');
        }

        if (Request::has('uid')) {
            $account = Account::findOneFromUID(Request::str('uid'));
            if (empty($account)) {
                return err('找不到指定任务！');
            }

            if ($account->getBonusType() != Account::COMMISSION) {
                return err('公众号奖励类型不正确！');
            }

            $orderUID = Order::makeUID($user, $device);

            if (Job::createAccountOrder([
                'device' => $device->getId(),
                'user' => $user->getId(),
                'account' => $account->getId(),
                'goods' => $goods['id'],
                'orderUID' => $orderUID,
                'ip' => CLIENT_IP,
            ])) {
                return ['orderUID' => $orderUID];
            }
        } else {
            $reward = Config::app('wxapp.advs.reward', []);
            if (empty($reward['allowFree']) || empty($reward['id'])) {
                return err('没有设置激励广告！');
            }

            $orderUID = Request::str('orderUID');
            $code = Request::str('code');

            if (empty($orderUID) || empty($code)) {
                return err('缺少必要的参数！');
            }

            if (Job::createRewardOrder([
                'order_no' => $orderUID,
                'user' => $user->getId(),
                'device' => $device->getId(),
                'goods' => $goods['id'],
                'num' => 1,
                'ip' => CLIENT_IP,
                'code' => $code,
            ])) {
                return ['orderUID' => $orderUID];
            }
        }

        return err('请求出货失败！');
    }

    public static function rewardOrderData(userModelObj $user): array
    {
        $reward = Config::app('wxapp.advs.reward', []);
        if (empty($reward['allowFree']) || empty($reward['id'])) {
            return err('没有设置激励广告！');
        }

        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            return err('无法锁定用户，请稍后再试！');
        }

        $limit = $reward['freeLimit'] ?? 0;
        if ($limit > 0) {
            $stats = $user->settings('extra.wxapp.reward.order', []);
            if (date('Ymd', $stats['time']) == date('Ymd', TIMESTAMP) && $stats['total'] >= $limit) {
                return err('今日免费额度已用完！');
            }
        }

        $device = Device::get(Request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $res = Helper::checkFreeOrderLimits($user, $device);
        if (is_error($res)) {
            return $res;
        }

        $order_no = Order::makeUID($user, $device, time());

        return [
            'orderUID' => $order_no,
            'code' => sha1($order_no.$reward['id'].$device->getShadowId().$user->getOpenid()),
        ];
    }

    public static function exchange(userModelObj $user): array
    {
        $device_uid = Request::str('deviceId');
        $goods_id = Request::int('goodsId');
        $num = Request::int('num');

        $res = Helper::exchange($user, $device_uid, $goods_id, $num);
        if (is_error($res)) {
            JSON::fail($res);
        }

        return ['orderUID' => $res];
    }

    public static function pay(userModelObj $user): array
    {
        if (!$user->acquireLocker(User::ORDER_LOCKER)) {
            return err('无法锁定用户，请稍后再试！');
        }

        $device = Device::get(Request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if (!$device->isMcbOnline()) {
            return err('设备不在线！');
        }

        if (!$device->lockAcquire(3)) {
            return err('设备正忙，请稍后再试！');
        }

        $is_package = false;
        if (Request::has('goodsId')) {
            $goods_or_package_id = Request::int('goodsId');
            if (empty($goods_or_package_id)) {
                return err('没有指定商品！');
            }
            $num = min(App::getOrderMaxGoodsNum(), max(Request::int('num'), 1));
            if ($num < 1) {
                return err('购买数量不能小于1！');
            }
        } else {
            $goods_or_package_id = Request::int('packageId');
            if (empty($goods_or_package_id)) {
                return err('没有指定套餐！');
            }
            $is_package = true;
        }

        return Helper::createWxAppOrder($user, $device, $goods_or_package_id, $num ?? 1, $is_package);
    }

    public static function orderStatus(): array
    {
        $order = Order::get(Request::str('uid'), true);
        if (empty($order)) {
            return [
                'msg' => '正在查询订单',
                'code' => 100,
            ];
        }

        $errno = $order->getExtraData('pull.result.errno', 'n/a');

        if ($errno == 0) {
            return [
                'code' => 200,
                'msg' => '出货完成!',
            ];
        } elseif ($errno == 12) {
            return [
                'code' => 100,
                'msg' => '订单正在处理中，请稍等！',
            ];
        } elseif ($errno == 'n/a') {
            return [
                'msg' => '订单正在处理中',
                'code' => 100,
            ];
        }

        return [
            'code' => 502,
            'msg' => '出货失败！',
        ];
    }

    public static function userInfo(userModelObj $user): array
    {
        $data = $user->profile();
        $data['banned'] = $user->isBanned();

        if (App::isBalanceEnabled()) {
            $data['signed'] = $user->isSigned();
            $data['balance'] = $user->getBalance()->total();
        }

        return $data;
    }

    public static function getJumpUserInfo(): array
    {
        $openid = Request::str('openid');

        $user = User::get($openid, true);
        if (empty($user)) {
            return err('找不到这个用户！');
        }

        $data = $user->profile();
        $data['banned'] = $user->isBanned();

        $account = $user->getLastActiveAccount();
        if (!empty($account)) {
            $data['account'] = $account->profile();
        }

        return $data;
    }


    public static function feedback(userModelObj $user): array
    {
        $imei = Request::str('deviceId');

        $text = Request::str('text');
        $pics = Request::array('pics');

        $device = Device::get($imei, true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $data = [
            'device_id' => $device->getId(),
            'user_id' => $user->getId(),
            'text' => $text,
            'pics' => serialize($pics),
            'createtime' => time(),
        ];

        if (DeviceFeedback::create($data)) {
            return ['msg' => '感谢您的反馈，我们会及时核实并处理！'];
        }

        return err('反馈失败，请稍后重试！');
    }

    public static function signIn(userModelObj $user): array
    {
        $res = Balance::dailySignIn($user);
        if (is_error($res)) {
            return $res;
        }

        return [
            'balance' => $user->getBalance()->total(),
            'bonus' => $res,
        ];
    }

    public static function bonus(userModelObj $user): array
    {
        $account = Account::findOneFromUID(Request::str('uid'));
        if (empty($account)) {
            return err('找不到这个公众号！');
        }

        $result = Balance::give($user, $account);
        if (is_error($result)) {
            return $result;
        }

        return [
            'balance' => $user->getBalance()->total(),
            'bonus' => $result instanceof balanceModelObj ? $result->getXVal() : 0,
        ];
    }

    protected static function getRewardBonus(userModelObj $user)
    {
        $bonusData = Config::app('wxapp.advs.reward.bonus', []);
        if (isEmptyArray($bonusData)) {
            return err('暂时没有奖励！');
        }

        //每日限额
        $limit = Config::app('wxapp.advs.reward.limit', 0);
        if ($limit > 0) {
            $today = new DateTime();
            $today->modify('00:00');

            $total = Balance::query([
                'openid' => $user->getOpenid(),
                'src' => Balance::REWARD_ADV,
                'createtime >=' => $today->getTimestamp(),
            ])->count();

            if ($total >= $limit) {
                return err('今天的广告奖励额度已到达上限，明天再来！');
            }
        }

        //最大限额
        $max = Config::app('wxapp.advs.reward.max', 0);
        if ($max > 0) {
            $total = Balance::query([
                'openid' => $user->getOpenid(),
                'src' => Balance::REWARD_ADV,
            ])->count();

            if ($total >= $max) {
                return err('您的广告奖励总额度已到达上限！');
            }
        }

        //获取用户奖励等级
        $condition = [
            'openid' => $user->getOpenid(),
            'src' => Balance::REWARD_ADV,
        ];

        $way = Config::app('wxapp.advs.reward.w', 'all');
        if ($way == 'day') {
            $today = new DateTime();
            $today->modify('00:00');
            $condition['createtime >='] = $today->getTimestamp();
        }

        $total = Balance::query($condition)->count();

        $bonus = 0;
        foreach ((array)$bonusData as $data) {
            if ($total > $data['max']) {
                $total -= $data['max'];
                continue;
            }
            $bonus = intval($data['v']);
            break;
        }

        if ($bonus < 1) {
            return err('暂时没有奖励！');
        }

        return $bonus;
    }

    public static function rewardQuota(userModelObj $user): array
    {
        if (!$user->acquireLocker(User::BALANCE_GIVE_LOCKER)) {
            return err('无法锁定用户！');
        }

        $res = self::getRewardBonus($user);
        if (is_error($res)) {
            return $res;
        }

        return ['msg' => 'Ok'];
    }

    public static function reward(userModelObj $user): array
    {
        if (!$user->acquireLocker(User::BALANCE_GIVE_LOCKER)) {
            return err('无法锁定用户！');
        }

        $res = self::getRewardBonus($user);
        if (is_error($res)) {
            return $res;
        }

        $result = $user->getBalance()->change($res, Balance::REWARD_ADV);
        if (empty($result)) {
            return err('获取奖励失败！');
        }

        return [
            'balance' => $user->getBalance()->total(),
            'bonus' => $result->getXVal(),
        ];
    }

    public static function balanceLog(userModelObj $user): array
    {
        $query = $user->getBalance()->log();

        $last_id = Request::int('lastId');
        if ($last_id > 0) {
            $query->where(['id <' => $last_id]);
        }

        $query->limit(Request::int('pagesize', DEFAULT_PAGE_SIZE));
        $query->orderBy('id DESC');

        $result = [];
        foreach ($query->findAll() as $entry) {
            $result[] = Balance::format($entry);
        }

        return $result;
    }

    public static function orderList(userModelObj $user): array
    {
        $way = Request::str('way');
        $page = Request::int('page');
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

        return Order::getList($user, $way, $page, $page_size);
    }

    public static function task(userModelObj $user): array
    {
        $max = Request::int('max', 10);

        return Task::getList($user, $max);
    }

    public static function detail(userModelObj $user): array
    {
        $uid = Request::str('uid');

        $account = Account::findOneFromUID($uid);
        if ($account && $account->isQuestionnaire()) {
            $data = $account->format();
            $data['questions'] = $account->getQuestions($user);

            return $data;
        }

        return Task::detail($account ?? $uid);
    }

    public static function submit(userModelObj $user): array
    {
        if (!$user->acquireLocker(User::TASK_LOCKER)) {
            return err('用户无法锁定，请重试！');
        }

        $uid = Request::str('uid');
        $data = Request::array('data');
        if (empty($data)) {
            return err('提交的数据为空！');
        }

        $account = Account::findOneFromUID($uid);
        if ($account && $account->isQuestionnaire()) {
            $res = Questionnaire::submitAnswer($uid, $data, $user);
        } else {
            $res = Task::submit($account ?? $uid, $data, $user);
        }

        if (is_error($res)) {
            return $res;
        }

        return ['msg' => '提交成功！'];
    }

    public static function getRecipient(userModelObj $user)
    {
        $recipient = $user->getRecipientData();
        if (empty($recipient)) {
            $recipient = [
                'name' => '',
                'phoneNum' => '',
                'address' => '',
            ];
        }

        return $recipient;
    }

    public static function updateRecipient(userModelObj $user): array
    {
        $name = Request::trim('name');
        $phone_num = Request::trim('phoneNum');
        $address = Request::trim('address');

        $result = $user->updateRecipientData($name, $phone_num, $address);

        if ($result) {
            return ['msg' => '已保存！'];
        }

        return err('保存失败！');
    }

    public static function getMallOrderList(userModelObj $user): array
    {
        $params = [
            'last_id' => Request::int('lastId'),
            'pagesize' => Request::int('pagesize'),
            'user_id' => $user->getId(),
        ];

        if (Request::isset('status')) {
            $params['status'] = Request::int('status');
        }

        return Delivery::getList($params);
    }

    public static function getMallGoodsList(): array
    {
        return Mall::getGoodsList([
            'page' => Request::int('page'),
            'pagesize' => Request::int('pagesize'),
        ]);
    }

    public static function createMallOrder(userModelObj $user)
    {
        return Mall::createOrder($user, [
            'goods_id' => Request::int('goods'),
            'num' => Request::int('num'),
        ]);
    }

    public static function validateLocation(userModelObj $user)
    {
        $device = Device::get(Request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        if ($device->needValidateLocation()) {
            $res = Helper::validateLocation($user, $device, Request::float('lat'), Request::float('lng'));
            if (is_error($res)) {
                return $res;
            }
        }

        return '成功！';
    }

    /**
     * 获取银行信息
     */
    public static function getUserBank(userModelObj $user): array
    {
        return misc::getUserBank($user);
    }

    /**
     * 设置提现银行信息
     */
    public static function setUserBank(userModelObj $user): array
    {
        return misc::setUserBank($user);
    }

    public static function getUserQRCode(userModelObj $user): array
    {
        return misc::getUserQRCode($user);
    }

    public static function updateUserQRCode(userModelObj $user): array
    {
        $type = Request::str('type');

        return misc::updateUserQRCode($user, $type);
    }
}
