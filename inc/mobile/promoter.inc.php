<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$user = Util::getCurrentUser();
if (empty($user)) {
    Util::resultAlert('请在微信中打开，谢谢！', 'error');
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
JSCODE;
    if ($user->isPromoter()) {
        $tpl_data['js']['code'] .= <<<JSCODE
        zovye_fn.getList = function(page, size) {
            return $.getJSON('$api_url', {op: 'log', 'page': page, 'pagesize': size});
        };
JSCODE;
    } else {
        $tpl_data['js']['code'] .= <<<JSCODE
        zovye_fn.reg = function(code) {
            return $.getJSON('$api_url', {op: 'reg', 'code': code});
        };
JSCODE;
    }
    $tpl_data['js']['code'] .= <<<JSCODE
\r\n</script>
JSCODE;
    app()->showTemplate($user->isPromoter() ? 'promoter/log' : 'promoter/reg', ['tpl' => $tpl_data]);

} elseif ($op == 'reg') {

    $code = Request::trim('code');
    if (empty($code)) {
        JSON::fail('输入的推荐码无效，请重新输入！');
    }

    $ref = Referral::from($code);
    if (empty($ref)) {
        JSON::fail('输入的推荐码无效，请重新输入！');
    }

    $keeper_user = $ref->getUser();
    if (empty($keeper_user) || $keeper_user->isBanned()) {
        JSON::fail('找不到推荐码对应的运营人员！');
    }

    $keeper = $keeper_user->getKeeper();
    if (empty($keeper)) {
        JSON::fail('找不到推荐码对应的运营人员！');
    }

    if ($user->setPrincipal(Principal::Promoter, [
        'keeper' => $keeper->getId(),
    ])) {
        JSON::success('恭喜您成为推广员！');
    }

    JSON::fail('加入失败，请联系管理员！');

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
}