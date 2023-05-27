<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

//确定用户身份
$user = Util::getCurrentUser();
if (empty($user) || $user->isBanned()) {
    JSON::fail('找不到用户或者用户无法购买！');
}

$op = Request::op('default');

//验证
if ($op == 'default') {

    $order_no = Request::str('orderNO');
    $device_id = '';
    if (!empty($order_no)) {
        $pay_log = $user->getPayLog($order_no);
        $data = $pay_log->getData();
        $device_id = $data['orderData']['deviceId'];
    }

    app()->idCardPage(
        [
            'deviceId' => $device_id,
            'orderNO' => $order_no,
        ]
    );

} elseif ($op == 'verify') {

    if ($user->isIDCardVerified()) {
        JSON::fail('用户已经通过实名验证！');
    }

    $name = Request::trim('name');
    $num = Request::trim('num');

    if (empty($name) || strlen($num) != 18) {
        JSON::fail('请输入有效的姓名和身份证号码！');
    }

    $order_no = Request::str('orderNO');

    $log = $user->settings('idcard.log', []);
    if ($log) {
        $count = intval($log['count']);
        if ($log['date'] == date('Ymd')) {
            if ($count > settings('user.verify.maxtimes', 0)) {
                JSON::fail('今日尝试次数太多了，请明天再试！');
            }
        } else {
            $count = 0;
        }
    } else {
        $count = 0;
    }

    $user->updateSettings(
        'idcard.log',
        [
            'date' => date('Ymd'),
            'count' => $count + 1,
        ]
    );

    $str = base64_encode("$name|$num");
    $res = CtrlServ::getV2("idcard/check/$str");
    if (empty($res) || empty($res['status'])) {
        if ($res['data']['message'] == 'invalid idcard') {
            JSON::fail('身份证号码填写有误，请检查后再试！');
        }
        if ($res['data']['message'] == '余额不足！') {
            JSON::fail('认证失败，服务不可用！');
        }

        JSON::fail('认证失败!');
    }

    $matched = boolval($res['data']['result']);
    if (!$matched) {
        JSON::fail('姓名和证件号码不匹配，请检查后再试！');
    }

    $age = intval($res['data']['age']);
    if ($age < 18) {
        $message = '未满18周岁，无法购买本产品，稍后自动退款！';
        Job::refund($order_no, $message);
        JSON::fail(['code' => 201, 'msg' => $message]);
    }

    $user->setIDCardVerified(sha1("$name|$num"));

    Job::getResult($order_no, $user->getOpenid());

    JSON::success('实名认证完成！');

} elseif ($op == 'refund') {

    $order_no = Request::str('orderNO');
    Job::refund($order_no, '用户拒绝实名认证！');
    JSON::success(['code' => 201, 'msg' => '已发起退款请求，稍后自动退款！']);

} elseif ($op == 'verify_18') {
    $user = Util::getCurrentUser();
    if ($user->isIDCardVerified()) {
        JSON::success('用户已通过实名认证！');
    }

    $name = Request::trim('name');
    if ($name == '') {
        JSON::fail('请输入姓名！');
    }

    $num = Request::trim('num');
    if ($num == '') {
        JSON::fail('请输入身份证号码！');
    }

    $birth = '';
    if (strlen($num) == 15) {
        $input_year = substr($num, 6, 2);
        if ($input_year < 10) {
            $input_year = '20'.$input_year;
        } else {
            $input_year = '19'.$input_year;
        }
        $birth = $input_year.substr($num, 8, 4);
    }

    if (strlen($num) == 18) {
        $birth = substr($num, 6, 8);
    }

    if (strlen($birth) != 8) {
        JSON::fail('身份证信息有误！'.$birth);
    }

    $the_current_year = date('Y');
    $the_current_month = date('m');
    $the_current_day = date('d');

    $input_year = substr($birth, 0, 4);
    $input_month = substr($birth, 4, 2);
    $input_day = substr($birth, 6, 2);

    $isOver18 = false;
    if ($the_current_year - $input_year > 18) {
        $isOver18 = true;
    } else {
        if ($the_current_year - $input_year == 18) {
            if ($the_current_month - $input_month > 0) {
                $isOver18 = true;
            } else {
                if ($the_current_month - $input_month == 0) {
                    if ($the_current_day - $input_day >= 0) {
                        $isOver18 = true;
                    }
                }
            }
        }
    }

    if (!$isOver18) {
        JSON::fail('年龄未超过18岁，不能购买！');
    }

    $hash = sha1("$name|$num");

    $s_query = m('settings_user');
    $s_query = $s_query->query(We7::uniacid([]));
    $s_count = $s_query->where(['data LIKE' => "%$hash%"])->limit(1)->count();
    if ($s_count > 0) {
        JSON::fail('该身份证已用于认证！');
    }

    $user->setIDCardVerified($hash);

    JSON::success('认证成功！');
}
