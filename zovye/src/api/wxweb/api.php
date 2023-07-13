<?php

/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wxweb;

use DateTime;
use zovye\Account;
use zovye\Advertising;
use zovye\Goods;
use zovye\Helper;
use zovye\Job;
use zovye\JSON;
use zovye\LocationUtil;
use zovye\model\balanceModelObj;
use zovye\model\userModelObj;
use zovye\Order;
use zovye\Task;
use zovye\User;
use zovye\Util;
use zovye\Device;
use zovye\Request;
use zovye\api\wxx\common;
use zovye\App;
use zovye\Balance;
use zovye\Config;
use zovye\Delivery;
use zovye\Log;
use zovye\Mall;
use zovye\PlaceHolder;
use zovye\Questionnaire;

use function zovye\err;
use function zovye\is_error;
use function zovye\isEmptyArray;
use function zovye\m;

class api
{
    /**
     * 用户登录，小程序必须提交code,encryptedData和iv值
     *
     * @return array
     */
    public static function login(): array
    {
        $res = \zovye\api\wx\common::getDecryptedWxUserData();
        if (is_error($res)) {
            Log::error('wxweb', $res);

            return err('用户登录失败，请稍后再试！[103]');
        }

        //如果小程序请求中携带了H5页面的openid，则使用该openid的H5用户登录小程序
        $h5_openid = '';
        if (Request::has('openId')) {
            $h5_openid = Request::str('openId');
        }

        return common::doUserLogin(
            $res,
            Request::array('userInfo'),
            $h5_openid,
            Request::str('device'),
            Request::str('from')
        );
    }

    public static function nearBy(): array
    {
        return Util::getNearByDevices();
    }

    public static function migrateUrl(): array
    {
        $url = Util::murl('util', ['op' => 'migrate', 'token' => \zovye\api\wx\common::getToken()]);

        return ['url' => $url];
    }

    /**
     * 获取设备相关的广告
     * @return array
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

        $list = Util::getDeviceAds($device ?? Device::getDummyDevice(), $type, $num);

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

    public static function accounts(): array
    {
        $user = \zovye\api\wx\common::getUser();

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

    public static function goods(): array
    {
        $user = \zovye\api\wx\common::getUser();

        $device = Device::get(Request::str('deviceId'), true);
        if (empty($device)) {
            return err('找不到这个设备！');
        }

        $type = Request::str('type'); //free or pay or balance

        if ($type == 'balance' || $type == 'exchange') {
            $result = $device->getGoodsList($user, [Goods::AllowBalance]);
        } elseif ($type == 'free') {
            $result = $device->getGoodsList($user, [Goods::AllowFree]);
        } else {
            $result = $device->getGoodsAndPackages($user, [Goods::AllowPay]);
        }

        return $result;
    }

    public static function get(): array
    {
        $user = \zovye\api\wx\common::getUser();

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
                'ip' => LocationUtil::getClientIp(),
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
                'ip' => LocationUtil::getClientIp(),
                'code' => $code,
            ])) {
                return ['orderUID' => $orderUID];
            }
        }

        return err('请求出货失败！');
    }

    public static function rewardOrderData(): array
    {
        $user = \zovye\api\wx\common::getUser();

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

        $res = Util::checkFreeOrderLimits($user, $device);
        if (is_error($res)) {
            return $res;
        }

        $order_no = Order::makeUID($user, $device, time());

        return [
            'orderUID' => $order_no,
            'code' => sha1($order_no.$reward['id'].$device->getShadowId().$user->getOpenid()),
        ];
    }

    public static function exchange(): array
    {
        $user = \zovye\api\wx\common::getUser();

        $device_uid = Request::str('deviceId');
        $goods_id = Request::int('goodsId');
        $num = Request::int('num');

        $res = Helper::exchange($user, $device_uid, $goods_id, $num);
        if (is_error($res)) {
            JSON::fail($res);
        }

        return ['orderUID' => $res];
    }

    public static function pay(): array
    {
        $user = \zovye\api\wx\common::getWXAppUser();

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

        if ($device->isLocked()) {
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

    public static function userInfo(): array
    {
        $user = \zovye\api\wx\common::getUser();

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


    public static function feedback(): array
    {
        $user = \zovye\api\wx\common::getUser();

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

        if (m('device_feedback')->create($data)) {
            return ['msg' => '感谢您的反馈，我们会及时核实并处理！'];
        }

        return err('反馈失败，请稍后重试！');
    }

    public static function signIn(): array
    {
        $user = \zovye\api\wx\common::getUser();

        $res = Balance::dailySignIn($user);
        if (is_error($res)) {
            return $res;
        }

        return [
            'balance' => $user->getBalance()->total(),
            'bonus' => $res,
        ];
    }

    public static function bonus(): array
    {
        $user = \zovye\api\wx\common::getUser();

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

    public static function rewardQuota(): array
    {
        $user = \zovye\api\wx\common::getUser();

        if (!$user->acquireLocker(User::BALANCE_GIVE_LOCKER)) {
            return err('无法锁定用户！');
        }

        $res = self::getRewardBonus($user);
        if (is_error($res)) {
            return $res;
        }

        return ['msg' => 'Ok'];
    }

    public static function reward(): array
    {
        $user = \zovye\api\wx\common::getUser();

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

    public static function balanceLog(): array
    {
        $user = \zovye\api\wx\common::getUser();

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

    public static function orderList(): array
    {
        $user = \zovye\api\wx\common::getUser();

        $way = Request::str('way');
        $page = Request::int('page');
        $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

        return Order::getList($user, $way, $page, $page_size);
    }

    public static function task(): array
    {
        $user = \zovye\api\wx\common::getUser();

        $max = Request::int('max', 10);

        return Task::getList($user, $max);
    }

    public static function detail(): array
    {
        $uid = Request::str('uid');

        $account = Account::findOneFromUID($uid);
        if ($account && $account->isQuestionnaire()) {
            $user = \zovye\api\wx\common::getUser();
            $data = $account->format();
            $data['questions'] = $account->getQuestions($user);

            return $data;
        }

        return Task::detail($account ?? $uid);
    }

    public static function submit(): array
    {
        $user = \zovye\api\wx\common::getUser();
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

    public static function getRecipient()
    {
        $user = \zovye\api\wx\common::getUser();

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

    public static function updateRecipient(): array
    {
        $user = \zovye\api\wx\common::getUser();

        $name = Request::trim('name');
        $phone_num = Request::trim('phoneNum');
        $address = Request::trim('address');

        $result = $user->updateRecipientData($name, $phone_num, $address);

        if ($result) {
            return ['msg' => '已保存！'];
        }

        return err('保存失败！');
    }

    public static function getMallOrderList(): array
    {
        $user = \zovye\api\wx\common::getUser();

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

    public static function createMallOrder()
    {
        $user = \zovye\api\wx\common::getUser();

        return Mall::createOrder($user, [
            'goods_id' => Request::int('goods'),
            'num' => Request::int('num'),
        ]);
    }

    public static function validateLocation()
    {
        $user = \zovye\api\wx\common::getUser();
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
     * 获取银行信息.
     *
     * @return array
     */
    public static function getUserBank(): array
    {
        $user = \zovye\api\wx\common::getUser();

        return \zovye\api\wx\common::getUserBank($user);
    }

    /**
     * 设置提现银行信息.
     *
     * @return array
     */
    public static function setUserBank(): array
    {
        $user = \zovye\api\wx\common::getUser();

        return \zovye\api\wx\common::setUserBank($user);
    }

    public static function getUserQRCode(): array
    {
        $user = \zovye\api\wx\common::getUser();

        return \zovye\api\wx\common::getUserQRCode($user);
    }

    public static function updateUserQRCode(): array
    {
        $user = \zovye\api\wx\common::getUser();
        $type = Request::str('type');

        return \zovye\api\wx\common::updateUserQRCode($user, $type);
    }
}
