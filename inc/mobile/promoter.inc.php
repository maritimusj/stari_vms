<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use RuntimeException;
use zovye\api\wx\balance;

if (!App::isPromoterEnabled()) {
    Util::resultAlert('这个功能没有启用，谢谢！', 'error');
}

$user = Util::getCurrentUser();
if (empty($user)) {
    Util::resultAlert('请在微信中打开，谢谢！', 'error');
}

if ($user->isBanned()) {
    Util::resultAlert('对不起，暂时无法使用这个功能！', 'error');
}

if ($user->isAgent()) {
    Util::resultAlert('对不起，代理商无法使用这个功能！', 'error');
}

if ($user->isPartner()) {
    Util::resultAlert('对不起，合伙人无法使用这个功能！', 'error');
}

if ($user->isKeeper()) {
    Util::resultAlert('对不起，运营人员无法使用这个功能！', 'error');
}

$op = Request::op('default');
if ($op == 'default') {
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
            return $.getJSON('$api_url', {op: 'brief'});
        };
        \r\n
JSCODE;
    if ($user->isPromoter()) {
        $tpl_data['js']['code'] .= <<<JSCODE
        zovye_fn.getList = function(page, size) {
            return $.getJSON('$api_url', {op: 'log', 'page': page, 'pagesize': size});
        };
        \r\n
JSCODE;
    } else {
        $tpl_data['js']['code'] .= <<<JSCODE
        zovye_fn.reg = function(code) {
            return $.getJSON('$api_url', {op: 'reg', 'code': code});
        };
        \r\n
JSCODE;
    }
    $tpl_data['js']['code'] .= <<<JSCODE
</script>
JSCODE;
    app()->showTemplate($user->isPromoter() ? 'promoter/log' : 'promoter/reg', ['tpl' => $tpl_data]);

} elseif ($op == 'reg') {

    $result = Util::transactionDo(function () {
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
        ])) {
            return true;
        }

        throw new RuntimeException('加入失败，请联系管理员！');
    });

    if (is_error($result)) {
        JSON::fail($result);
    }
    
    JSON::success('恭喜您成为推广员！');

} elseif ($op == 'brief') {

   $data = $user->profile(false);
   $data['balance'] = $user->getCommissionBalance()->total();
   $data['promoter'] = $user->isPromoter();

   JSON::success($data);

} elseif ($op == 'log') {

    $page = max(1, Request::int('page'));
    $page_size = Request::int('pagesize', DEFAULT_PAGE_SIZE);

    $query = $user->getCommissionBalance()->log();
    $query->page($page, $page_size);

    $query->orderBy('createtime DESC');

    $result = [];
    foreach ($query->findAll() as $log) {
        $result[] = CommissionBalance::format($log);
    }

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
            return $.getJSON('$api_url', {op: 'brief'});
        };
        zovye_fn.withdraw = function(amount) {
            return $.getJSON('$api_url', {op: 'withdraw', amount});
        };
</script>
JSCODE;

    app()->showTemplate('promoter/withdraw', ['tpl' => $tpl_data]);

} elseif ($op == 'withdraw') {
    return balance::balanceWithdraw($user, Request::float('amount', 0, 2) * 100);
}