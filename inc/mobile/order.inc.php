<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\orderModelObj;
use zovye\model\pay_logsModelObj;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

if ($op === 'create') {

    //确定用户身份
    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户或者用户无法购买！');
    }

    $device_uid = request::str('deviceUID');
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

    if (request::has('goodsID')) {
        $goods_id = request::int('goodsID');
        if (empty($goods_id)) {
            JSON::fail('参数错误，没有指定商品！');
        }

        $goods = $device->getGoods($goods_id);
        if (empty($goods) || empty($goods['allowPay']) || $goods['price'] < 1) {
            JSON::fail('无法购买这个商品，请联系管理员！');
        }

        $total = min(App::orderMaxGoodsNum(), max(request::int('total'), 1));

        if ($goods['num'] < $total) {
            JSON::fail('对不起，商品数量不足！');
        }

        //获取用户折扣
        $discount = User::getUserDiscount($user, $goods, $total);
        $price = $goods['price'] * $total - $discount;

    } elseif (request::has('packageID')) {

        $package_id = request::int('packageID');
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
        JSON::fail('创建支付失败: ' . $data['message']);
    }

    //加入一个支付结果检查
    Job::orderPayResult($order_no);

    //加入一个支付超时任务
    $res = Job::orderTimeout($order_no);
    if (empty($res) || is_error($res)) {
        JSON::fail('创建支付任务失败！');
    }

    $data['orderNO'] = $order_no;

    JSON::success($data);

} elseif ($op == 'finished') {

    //完成付款操作
    $order_no = request::str('orderNO');

    $pay_log = Pay::getPayLog($order_no);
    if ($pay_log) {
        $pay_log->setData('finished', ['time' => time()]);
        $pay_log->save();
    }

    JSON::success('已记录');

} elseif ($op == 'cancel') {

    //取消支付
    $order_no = request::str('orderNO');
    if (Order::exists($order_no)) {
        JSON::fail('订单已生成，无法取消！');
    }

    $pay_log = Pay::getPayLog($order_no);
    if ($pay_log) {

        $pay_log->setData('cancelled', ['createtime' => time()]);
        $pay_log->save();

        JSON::success('已取消！');
    }

} elseif ($op == 'result') {

    //查询订单状态
    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail(['code' => 401, 'msg' => '找不到用户或者用户无法领取！']);
    }

    if (App::isIDCardVerifyEnabled() && !$user->isIDCardVerified()) {
        JSON::success(['code' => 101, 'msg' => '请先填写实名认主证信息！']);
    }

    $order_no = request::str('orderNO');

    $order = Order::get($order_no, true);
    if ($order) {
        $response = ['code' => 200];

        $errno = $order->getExtraData('pull.result.errno', 'n/a');
        if ($errno == 'n/a') {
            $response['msg'] = '订单正在处理中...';
        } elseif ($errno == 0) {
            $response['msg'] = '出货完成!';
        } elseif ($errno == 12) {
            $response['msg'] = '订单正在处理中，请稍等！';
        } else {
            if (Helper::NeedAutoRefund($order)) {
                $response['msg'] = '出货失败，已提交退款申请！';
            } else {
                $response['msg'] = '出货失败，请联系管理员！';
            }
        }

        $vouchers = $order->getExtraData('extra.voucher.recv', 0);
        if ($vouchers > 0) {
            $response['tips'] = [
                'type' => 'info',
                'msg' => "恭喜你获取{$vouchers}张提货券，详情请到个人中心查看！",
            ];
        }

        $url = settings('misc.redirect.success.url');
        if (!empty($url)) {
            $response['url'] = $url;
        }

        $device = $order->getDevice();
        if ($device && $device->isVDevice()) {
            $response['goods'] = Goods::data($order->getGoodsId(), ['useImageProxy' => true]);
            $response['order'] = [
                'id' => $order->getOrderId(),
                'num' => $order->getNum(),
                'createtime_formatted' => date('Y-m-d H:i:s', $order->getCreatetime()),
            ];
            $response['user'] = $user->profile();
        }
        JSON::success($response);
    }

    if (request::bool('balance')) {
        JSON::success(['code' => 100, 'msg' => '正在查询订单，请稍等...']);
    }

    /** @var pay_logsModelObj $pay_log */
    $pay_log = Pay::getPayLog($order_no);
    if (empty($pay_log)) {
        JSON::fail(['code' => 400, 'msg' => "找不到支付信息！" . $order_no]);
    }

    //已取消
    if ($pay_log->getData('cancelled')) {
        JSON::fail(['code' => 500, 'msg' => '订单已取消！']);
    }

    //已超时
    if ($pay_log->getData('timeout')) {
        JSON::fail(['code' => 501, 'msg' => '订单已超时！']);
    }

    //已退款
    $refund = $pay_log->getData('refund');
    if ($refund) {
        JSON::fail(['code' => 502, 'msg' => '出货失败，订单已退款！']);
    }

    //支付成功
    if ($pay_log->getData('payResult')) {
        JSON::success(['code' => 100, 'msg' => '支付成功，请稍等...']);
    }

    JSON::success(['code' => 100, 'msg' => '正在查询订单，请稍等...']);

} elseif ($op == 'retry') {
    //确定用户身份
    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户或者用户已禁用！');
    }

    if (!$user->acquireLocker(User::ORDER_ACCOUNT_LOCKER)) {
        JSON::fail('用户锁定失败！');
    }

    /** @var orderModelObj $order */
    $order = Order::get(request::str('uid'), true);
    if (empty($order)) {
        JSON::fail('找不到这个订单！');
    }

    if ($order->getResultCode() == 0) {
        JSON::fail('订单已完成！');
    }

    if ($order->getPrice() > 0 || $order->getBalance() > 0) {
        JSON::fail('只能是免费订单！');
    }

    $device = Device::findOne(['shadow_id' => request::trim('device')]);
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
    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户！');
    }

    $user_profile = User::query(['openid' => $user->getOpenid()])->findOne();

    $role_assoc = [
        'agent' => '代理商',
        'partner' => '合伙人',
        'keeper' => '运营人员',
        'gspor' => '佣金用户',
    ];

    $role = '';
    if (isset($user_profile->passport) && $user_profile->passport != '') {
        foreach ($role_assoc as $k => $v) {
            if (strpos($user_profile->passport, $k) !== false) {
                $role = $v;
                break;
            }
        }
    } else {
        $role = '普通会员';
    }

    $query = Order::query();
    $condition = [];

    //指定用户
    $condition['openid'] = $user->getOpenid();

    //
    $way = request::str('way');
    if ($way == 'free') {
        $condition['price'] = 0;
    } elseif ($way == 'fee') {
        $condition['price >'] = 0;
    } elseif (isset($device) || isset($user)) {
        $way = 'spec';
    }

    $query->where($condition);

    $page = max(1, request::int('page'));
    $page_size = max(1, request::int('pagesize', DEFAULT_PAGE_SIZE));

    $total = $query->count();
    if (ceil($total / $page_size) < $page) {
        $page = 1;
    }

    $balance_enabled = App::isBalanceEnabled();

    $orders = [];
    /** @var orderModelObj $entry */
    foreach ($query->page($page, $page_size)->orderBy('id DESC')->findAll() as $entry) {

        $data = [
            'id' => $entry->getId(),
            'num' => $entry->getNum(),
            'price' => number_format($entry->getPrice() / 100, 2),
            'ip' => $entry->getIp(),
            'account' => $entry->getAccount(),
            'orderId' => $entry->getOrderId(),
            'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
            'agentId' => $entry->getAgentId(),
            'type' => '',
            'status' => '',
        ];

        if ($balance_enabled && $entry->getBalance() > 0) {
            $data['balance'] = $entry->getBalance();
        }

        //商品
        $data['goods'] = $entry->getExtraData('goods', []);

        //设备信息
        $device_id = $entry->getDeviceId();
        $device_obj = Device::get($device_id);
        if ($device_obj) {
            $data['device'] = [
                'name' => $device_obj->getName(),
                'id' => $device_obj->getId(),
            ];
        }

        if ($data['price'] > 0) {
            $data['tips'] = ['text' => '支付', 'class' => 'wxpay'];
        } elseif ($data['balance'] > 0) {
            $data['tips'] = ['text' => '积分', 'class' => 'balance'];
        } else {
            $data['tips'] = ['text' => '免费', 'class' => 'free'];
        }

        if ($data['price'] > 0 && $entry->getExtraData('refund')) {
            $time = $entry->getExtraData('refund.createtime');
            $time_formatted = date('Y-m-d H:i:s', $time);
            $data['refund'] = "已退款，退款时间：{$time_formatted}";
            $data['clr'] = '#8bc34a';
        }

        $pay_result = $entry->getExtraData('payResult');
        if ($pay_result['result'] === 'success') {
            $data['uniontid'] = isset($pay_result['uniontid']) ? $pay_result['uniontid'] : $pay_result['transaction_id'];
        }

        //出货结果
        $data['result'] = $entry->getExtraData('pull.result', []);

        if ($entry->getPrice() > 0) {
            $data['type'] = '支付订单';
            if ($data['refund']) {
                $data['status'] = '已退款';
            } else {
                if (is_error($data['result'])) {
                    $data['status'] = '故障';
                } else {
                    $data['status'] = '成功';
                }
            }
        } else {
            $data['type'] = '免费订单';
        }

        if (User::isAliUser($entry->getOpenid())) {
            $pay_type = '支付宝';
        } elseif (User::isWxUser($entry->getOpenid())) {
            $pay_type = '微信';
        } elseif (User::isWXAppUser($entry->getOpenid())) {
            $pay_type = '微信小程序';
        } else {
            $pay_type = '未知';
        }

        $data['pay_type'] = $pay_type;
        $orders[] = $data;
    }

    $user_profile = ['name' => $user->getNickname(), 'avatar' => $user->getAvatar()];

    JSON::success([
        'code' => 200,
        'orders' => $orders,
        'user' => $user_profile,
        'role' => $role,
        'page' => $page,
        'pagesize' => $page_size,
        'total' => $total,
        'way' => $way,
    ]);

} elseif ($op == 'detail') {

    //查询订单状态
    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail(['code' => 401, 'msg' => '找不到用户或者用户无法领取！']);
    }

    $order_no = request::str('orderNO');
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

    $api_url = Util::murl('order');
    $jquery_url = JS_JQUERY_URL;

    $js_code = <<<CODE
<script src="$jquery_url"></script>
<script>
const zovye_fn = {};

zovye_fn.get_list = function(way, page, pagesize) {
  return new Promise((resolve) => {
     $.getJSON("{$api_url}", {op: 'list', way, page, pagesize}).then(function(res) {
        resolve(res);
     });
  });
}

zovye_fn.get_free_list = function(page, pagesize) {
    return zovye_fn.get_list('free', page, pagesize);
}

zovye_fn.get_fee_list = function(page, pagesize) {
    return zovye_fn.get_list('fee', page, pagesize);
}

zovye_fn.get_order_detail =function(orderNO) {
    return new Promise((resolve, reject) => {
        $.getJSON("{$api_url}", {op: 'detail', orderNO}).then(function(res) {
            if (res && res.status) {
                resolve(res);
            } else {
                reject(res && res.data.msg ? res.data.msg : '请求失败！');
            }
        });
    });
}
</script>
CODE;

    $tpl_data['js']['code'] = $js_code;
    app()->showTemplate(Theme::file('order'), ['tpl' => $tpl_data]);

} elseif ($op == 'feedback') {

    $api_url1 = Util::murl('device', ['op' => 'fb_pic']);
    $api_url2 = Util::murl('device', ['op' => 'feed_back']);

    $axios_url = JS_AXIOS_URL;
    $js_code = <<<CODE
<script src="$axios_url"></script>
<script>
const zovye_fn = {};
zovye_fn.upload = function(filename) {
    const param = new FormData();
    param.append('pic', filename);
    
    const config = {
        headers: {
            'Content-Type': 'multipart/form-data'
        }
    }
    return new Promise((resolve, reject) => {
         axios.post('$api_url1',param, config).then((res) => {
            return res.data;
         }).then((res) => {
             if (res.status && res.data) {
                 resolve(res.data.data);
             } else {
                reject(res.msg || '上传失败！');
             }
         }).catch(() => {
           reject("上传失败！");
         });
    })
}

zovye_fn.feedback = function(device, text, pics) {
    const data = new FormData();
    data.append('device', device);
    data.append('text', text);
    
    for (let i = 0; i < (pics || []).length; i++) {
        data.append(('pics[' + i + ']'), pics[i]);
    }
    
    return new Promise((resolve, reject) => {
        axios.post("$api_url2", data).then((res) => {
            return res.data;
        }).then((res) => {
            if (res.status) {
                resolve(res.data.msg || '反馈成功！');
            } else {
                reject(res.data.msg || '上传失败！');
            }
        }).catch(() =>{
            reject("上传失败！");
        });        
    })
}

</script>
CODE;

    $tpl_data['js']['code'] = $js_code;
    app()->showTemplate(Theme::file('feedback'), ['tpl' => $tpl_data]);
}
