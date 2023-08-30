<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\orderModelObj;
use zovye\model\pay_logsModelObj;

$op = Request::op('default');

if ($op === 'create') {

    //确定用户身份
    $user = Session::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户或者用户无法购买！');
    }

    $device_uid = Request::str('deviceUID');
    if (empty($device_uid)) {
        JSON::fail('参数错误，没有指定设备！');
    }

    $device = Device::find($device_uid, ['imei', 'shadow_id']);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    if (!$device->isMcbOnline()) {
        JSON::fail('对不起，设备已离线！');
    }

    if ($device->isLocked()) {
        JSON::fail('设备正忙，请稍后再试！');
    }

    $price = 0;
    $total = 0;
    $discount = 0;
    $goods = [];

    if (Request::has('goodsID')) {
        $goods_id = Request::int('goodsID');
        if (empty($goods_id)) {
            JSON::fail('参数错误，没有指定商品！');
        }

        $goods = $device->getGoods($goods_id);
        if (empty($goods) || empty($goods[Goods::AllowPay]) || $goods['price'] < 1) {
            JSON::fail('无法购买这个商品，请联系管理员！');
        }

        $total = min(App::getOrderMaxGoodsNum(), max(Request::int('total'), 1));

        if ($goods['num'] < $total) {
            JSON::fail('对不起，商品数量不足！');
        }

        //获取用户折扣
        $discount = User::getUserDiscount($user, $goods, $total);
        $price = $goods['price'] * $total - $discount;

    } elseif (Request::has('packageID')) {

        $package_id = Request::int('packageID');
        if (empty($package_id)) {
            JSON::fail('对不起，商品套餐不正确！');
        }

        $package = $device->getPackage($package_id);
        if (empty($package)) {
            JSON::fail('找不到这个商品套餐！');
        }

        if (empty($package['isOk'])) {
            JSON::fail('暂时无法购买这个商品套餐！');
        }

        $total = 1;
        $price = $package['price'];

        $goods = $package;

    } else {
        JSON::fail('对不起，请求参数不正确！');
    }

    if ($price < 1) {
        JSON::fail('支付金额不能为零！');
    }

    list($order_no, $data) = Pay::createJsPay($device, $user, $goods, [
        'level' => LOG_GOODS_PAY,
        'total' => $total,
        'price' => $price,
        'discount' => $discount,
    ]);

    if (is_error($data)) {
        JSON::fail('创建支付失败: '.$data['message']);
    }

    //加入一个支付结果检查
    $res = Job::orderPayResult($order_no);
    if (empty($res) || is_error($res)) {
        JSON::fail('创建支付任务失败！');
    }

    //加入一个支付超时任务
    $res = Job::orderTimeout($order_no);
    if (empty($res) || is_error($res)) {
        JSON::fail('创建支付任务失败！');
    }

    $data['orderNO'] = $order_no;

    JSON::success($data);

} elseif ($op == 'finished') {

    //完成付款操作
    $order_no = Request::str('orderNO');

    $pay_log = Pay::getPayLog($order_no);
    if ($pay_log) {
        $pay_log->setData('finished', ['time' => time()]);
        $pay_log->save();
    }

    JSON::success('已记录');

} elseif ($op == 'cancel') {

    //取消支付
    $order_no = Request::str('orderNO');
    if (Order::exists($order_no)) {
        JSON::fail('订单已生成，无法取消！');
    }

    $pay_log = Pay::getPayLog($order_no);
    if ($pay_log) {
        $pay_log->setData('close_order', Pay::close($order_no));
        $pay_log->setData('cancelled', ['createtime' => time()]);
        $pay_log->save();

        JSON::success('已取消！');
    }

} elseif ($op == 'result') {

    if (Request::has('openid')) {
        $user = User::get(Request::str('openid'), true);
        if (empty($user) || $user->isBanned()) {
            JSON::fail(['code' => 401, 'msg' => '找不到用户或者用户无法领取！']);
        }

    } else {
        //查询订单状态
        $user = Session::getCurrentUser();
        if (empty($user) || $user->isBanned()) {
            JSON::fail(['code' => 401, 'msg' => '找不到用户或者用户无法领取！']);
        }

        if (App::isIDCardVerifyEnabled() && !$user->isIDCardVerified()) {
            JSON::success(['code' => 101, 'msg' => '请先填写实名认主证信息！']);
        }
    }

    $order_no = Request::str('orderNO');

    $order = Order::get($order_no, true);
    if ($order) {
        $errno = $order->getExtraData('pull.result.errno', -1);
        if ($errno == -1) {
            $response = [
                'code' => 100,
                'msg' => '订单正在处理中...',
            ];
        } elseif ($errno == 0) {

            $response = [
                'code' => 200,
                'msg' => '出货完成!',
            ];
        } elseif ($errno == 12) {
            $response = [
                'code' => 100,
                'msg' => '订单正在处理中，请稍等！',
            ];
        } else {
            $response = ['code' => 502];
            if (Helper::NeedAutoRefund($order)) {
                $response['msg'] = '出货失败，已提交退款申请！';
            } else {
                $response['msg'] = '出货失败，请联系管理员！';
            }
        }

        $stats = $order->getExtraData('stats', []);
        if ($stats) {
            $response['stats'] = $stats;
        }

        if ($response['code'] != 200) {
            JSON::success($response);
        }

        $vouchers = $order->getExtraData('extra.voucher.recv', 0);
        if ($vouchers > 0) {
            $response['tips'] = [
                'type' => 'info',
                'msg' => "恭喜你获取{$vouchers}张提货券，详情请到个人中心查看！",
            ];
        }

        $goods = $order->getGoodsData();
        if ($goods['redirect_url']) {
            //闪蛋商品设置的转跳
            $response['redirect'] = $goods['redirect_url'];
            JSON::success($response);
        }

        $device = $order->getDevice();
        if ($device) {
            $url = $device->getRedirectUrl()['url'];
            if (!empty($url)) {
                $response['redirect'] = $url;
            }
            if ($device->isVDevice()) {
                $response['goods'] = Goods::data($order->getGoodsId(), ['useImageProxy' => true]);
                $response['order'] = [
                    'id' => $order->getOrderId(),
                    'num' => $order->getNum(),
                    'createtime_formatted' => date('Y-m-d H:i:s', $order->getCreatetime()),
                ];
                $response['user'] = $user->profile();
            }
        }
        JSON::success($response);
    }

    if (Request::bool('balance')) {
        JSON::success(['code' => 100, 'msg' => '正在查询订单，请稍等...']);
    }

    /** @var pay_logsModelObj $pay_log */
    $pay_log = Pay::getPayLog($order_no);
    if (empty($pay_log)) {
        JSON::fail(['code' => 400, 'msg' => "找不到支付信息！".$order_no]);
    }

    $url = '';
    $device_id = $pay_log->getDeviceId();
    if ($device_id) {
        $device = Device::get($device_id);
        if ($device) {
            $url = $device->getRedirectUrl('fail')['url'];
        }
    }

    //已取消
    if ($pay_log->getData('cancelled')) {
        JSON::fail(['code' => 500, 'msg' => '订单已取消！', 'redirect' => $url]);
    }

    //已超时
    if ($pay_log->getData('timeout')) {
        JSON::fail(['code' => 501, 'msg' => '订单已超时！', 'redirect' => $url]);
    }

    //已退款
    $refund = $pay_log->getData('refund');
    if ($refund) {
        JSON::fail(['code' => 502, 'msg' => '出货失败，订单已退款！', 'redirect' => $url]);
    }

    //支付成功
    if ($pay_log->getData('payResult')) {
        JSON::success(['code' => 100, 'msg' => '支付成功，请稍等...']);
    }

    JSON::success(['code' => 100, 'msg' => '正在查询订单，请稍等...']);

} elseif ($op == 'retry') {
    //确定用户身份
    $user = Session::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户或者用户已禁用！');
    }

    if (!$user->acquireLocker(User::ORDER_ACCOUNT_LOCKER)) {
        JSON::fail('用户锁定失败！');
    }

    /** @var orderModelObj $order */
    $order = Order::get(Request::str('uid'), true);
    if (empty($order)) {
        JSON::fail('找不到这个订单！');
    }

    if ($order->getResultCode() == 0) {
        JSON::fail('订单已完成！');
    }

    if ($order->getPrice() > 0 || $order->getBalance() > 0) {
        JSON::fail('只能是免费订单！');
    }

    $device = Device::findOne(['shadow_id' => Request::trim('device')]);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    if ($device->getId() != $order->getDeviceId()) {
        JSON::fail('设备不匹配！');
    }

    if ($user->getOpenid() != $order->getOpenid()) {
        JSON::fail('用户不匹配！');
    }

    $account = $order->getAccount(true);
    if (empty($account)) {
        JSON::fail('找不到这个公众号！');
    }

    $res = Job::createAccountOrder([
        'device' => $device->getId(),
        'user' => $user->getId(),
        'account' => $account->getId(),
        'orderUID' => $order->getOrderNO(),
    ]);

    if (!$res) {
        JSON::fail('创建出货任务失败！');
    }

    $response = [
        'message' => '正在重试出货，请稍等...',
    ];

    JSON::success($response);

} elseif ($op == 'list') {

    //手机  用户订单列表
    if (Request::has('user') && App::isCZTVEnabled()) {
        $user = User::get(Request::str('user'), true);
    } else {
        $user = Session::getCurrentUser();
    }

    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户！');
    }

    if ($user->isAgent()) {
        $role_title = '代理商';
    } elseif ($user->isPartner()) {
        $role_title = '合伙人';
    } elseif ($user->isKeeper()) {
        $role_title = '运营人员';
    } elseif ($user->isGSPor()) {
        $role_title = '佣金用户';
    } else {
        $role_title = '普通会员';
    }

    $way = Request::str('way');
    $page = Request::int('page');
    $page_size = Request::int('pagesize');

    $result = Order::getList($user, $way, $page, $page_size);

    JSON::success([
        'code' => 200,
        'orders' => $result['list'],
        'user' => $user->profile(),
        'role' => $role_title,
        'page' => $result['page'],
        'pagesize' => $result['page_size'],
        'total' => $result['total'],
        'way' => $way,
    ]);

} elseif ($op == 'detail') {

    //查询订单状态
    if (Request::has('user') && App::isCZTVEnabled()) {
        $user = User::get(Request::str('user'), true);
    } else {
        $user = Session::getCurrentUser();
    }

    if (empty($user) || $user->isBanned()) {
        JSON::fail(['code' => 401, 'msg' => '找不到用户或者用户无法领取！']);
    }

    $order_no = Request::str('orderNO');
    $order = Order::get($order_no, true);
    if (empty($order)) {
        JSON::fail('找不到这个订单！');
    }

    if ($order->getOpenid() != $user->getOpenid()) {
        JSON::fail('无法查看这个订单！');
    }

    $result = Order::format($order, true);

    $result['site'] = [];
    $agent = $order->getAgent();
    if ($agent) {
        $title = $agent->agentData('misc.siteTitle');
        $result['site']['title'] = $title;
    }
    if (empty($result['site']['title'])) {
        $result['site']['title'] = settings('misc.siteTitle');
    }

    JSON::success($result);

} elseif ($op == 'jump') {

    Response::orderPage([
        'user' => Request::str('user'),
    ]);

} elseif ($op == 'feedback') {

    Response::feedbackPage();
}
