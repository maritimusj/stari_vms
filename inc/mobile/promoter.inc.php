<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use RuntimeException;
use zovye\api\wx\balance;
use zovye\api\wx\common;

if (!App::isPromoterEnabled()) {
    Response::alert('这个功能没有启用，谢谢！', 'error');
}

//用户参数
$params = [
    'create' => true,
    'update' => true,
    'from' => [
        'src' => 'promoter',
        'ip' => CLIENT_IP,
        'user-agent' => $_SERVER['HTTP_USER_AGENT'],
    ],
];

$user = Util::getCurrentUser($params);
if (empty($user)) {
    Response::alert('请在微信中打开，谢谢！', 'error');
}

if ($user->isBanned()) {
    Response::alert('对不起，暂时无法使用这个功能！', 'error');
}

if ($user->isAgent()) {
    Response::alert('对不起，代理商无法使用这个功能！', 'error');
}

if ($user->isPartner()) {
    Response::alert('对不起，合伙人无法使用这个功能！', 'error');
}

if ($user->isKeeper()) {
    Response::alert('对不起，运营人员无法使用这个功能！', 'error');
}

$op = Request::op('default');
if ($op == 'default') {
    $tpl_data = [];

    $api_url = Util::murl('promoter');
    $jquery_url = JS_JQUERY_URL;
    $vueJs_url = JS_VUE_URL;
    $js_sdk = Util::fetchJSSDK();
    $pre_withdraw_url = Util::murl('promoter', ['op' => 'pre_withdraw']);

    $tpl_data['js']['code'] = <<<JSCODE
    <script src="$jquery_url"></script>
    <script src="$vueJs_url"></script>
    $js_sdk
    <script>
        wx.ready(function(){
            wx.hideAllNonBaseMenuItem();
            wx.showMenuItems({
                menuList: [
                    "menuItem:share:appMessage",
                    "menuItem:share:timeline",
                    "menuItem:favorite",
                    "menuItem:copyUrl",
                ] // 要显示的菜单项，所有menu项见附录3
            });
        })
        const zovye_fn = {
            api_url: "$api_url",
        }
        zovye_fn.brief = function() {
            return $.getJSON(this.api_url, {op: "brief"});
        }
        \r\n
JSCODE;
    if ($user->isPromoter()) {
        $tpl_data['js']['code'] .= <<<JSCODE
        zovye_fn.getList = function(page, size) {
            return $.getJSON(this.api_url, {op: "log", "page": page, "pagesize": size});
        }
        zovye_fn.redirectToWithdrawPage = function() {
            window.location.href = "$pre_withdraw_url";
        }
        \r\n
JSCODE;
    } else {
        $tpl_data['js']['code'] .= <<<JSCODE
        zovye_fn.reg = function(code) {
            return $.getJSON(this.api_url, {op: "reg", "code": code});
        };
        \r\n
JSCODE;
    }
    $tpl_data['js']['code'] .= <<<JSCODE
</script>
JSCODE;
    if (User::isSnapshot()) {
        $tpl_data['js']['code'] .= app()->snapshotJs(['entry' => 'promoter']);
    }
    app()->showTemplate($user->isPromoter() ? 'promoter/log' : 'promoter/reg', ['tpl' => $tpl_data]);

} elseif ($op == 'reg') {

    $result = DBUtil::transactionDo(function () use ($user) {
        $code = Request::trim('code');
        if (empty($code)) {
            throw new RuntimeException('输入的邀请码无效，请重新输入！');
        }

        $ref = Referral::from($code);
        if (empty($ref)) {
            throw new RuntimeException('输入的邀请码无效，请重新输入！');
        }

        $keeper_user = $ref->getUser();
        if (empty($keeper_user) || $keeper_user->isBanned()) {
            throw new RuntimeException('找不到邀请码对应的运营人员！');
        }

        if ($keeper_user->getId() == $user->getId()) {
            throw new RuntimeException('不能邀请自己，谢谢！');
        }

        $keeper = $keeper_user->getKeeper();
        if (empty($keeper)) {
            throw new RuntimeException('找不到推荐码对应的运营人员！');
        }

        $user->setSuperiorId($keeper->getId());

        if ($user->save() && $user->setPrincipal(Principal::Promoter, [
                'keeper' => $keeper->getId(),
                'time' => time(),
            ])) {
            return true;
        }

        return err('加入失败，请联系管理员！');
    });

    if (is_error($result)) {
        JSON::fail($result);
    }

    JSON::success('恭喜您成为推广员！');

} elseif ($op == 'setData') {

    $fn = Request::trim('fn');
    if ($fn == 'bank') {
        JSON::result(common::setUserBank($user));
    } elseif ($fn == 'qrcode') {
        $type = Request::str('type');
        JSON::result(common::updateUserQRCode($user, $type));
    }

    JSON::fail('请求不正确！');

} elseif ($op == 'getData') {

    JSON::success([
        'bank' => common::getUserBank($user),
        'qrcode' => common::getUserQRCode($user),
    ]);

} elseif ($op == 'brief') {

    $data = $user->profile(false);
    $data['balance'] = $user->getCommissionBalance()->total();
    $data['promoter'] = $user->isPromoter();

    JSON::success($data);

} elseif ($op == 'log') {

    $result = Helper::getUserCommissionLogs($user);
    JSON::success($result);

} elseif ($op == 'pre_withdraw') {
    $tpl_data = [];

    $api_url = Util::murl('promoter');
    $jquery_url = JS_JQUERY_URL;
    $vueJs_url = JS_VUE_URL;
    $js_sdk = Util::fetchJSSDK();

    $tpl_data['js']['code'] = <<<JSCODE
    <script src="$jquery_url"></script>
    <script src="$vueJs_url"></script>
    $js_sdk
    <script>
        wx.ready(function(){
            wx.hideAllNonBaseMenuItem();
            wx.showMenuItems({
                menuList: [
                    "menuItem:share:appMessage",
                    "menuItem:share:timeline",
                    "menuItem:favorite",
                    "menuItem:copyUrl",
                ] // 要显示的菜单项，所有menu项见附录3
            });
        });
        const zovye_fn = {
            api_url: "$api_url",
        }
        zovye_fn.brief = function() {
            return $.getJSON(this.api_url, {op: "brief"});
        }
        zovye_fn.withdraw = function(amount) {
            return $.getJSON(this.api_url, {op: "withdraw", amount});
        }
        zovye_fn.getData = function() {
            return $.getJSON(this.api_url, {op: "getData"});
        }
        zovye_fn.setBank = function(data) {
            return $.post(this.api_url, {op: "setData", "fn": "bank", ...data});
        }
        zovye_fn.setQRCode = function(type, file) {
            const form = new FormData();
            form.append("op", "setData");
            form.append("fn", "qrcode");
            form.append("type", type);
            form.append("pic", file);
            return $.ajax({
                url: this.api_url,
                data: form,
                processData: false,
                contentType: false,
                type: "POST",
            });
        }
</script>
JSCODE;

    app()->showTemplate('promoter/withdraw', ['tpl' => $tpl_data]);

} elseif ($op == 'withdraw') {
    JSON::result(balance::balanceWithdraw($user, Request::float('amount', 0, 2) * 100));
}