<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use Exception;
use RuntimeException;
use zovye\model\deviceModelObj;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');
if ($op == 'default') {

    $js_sdk = Util::fetchJSSDK();
    app()->showTemplate('map', ['jssdk' => $js_sdk]);

} elseif ($op == 'data') {

    //请求附近设备数据
    $query = Device::query();

    $result = [];

    /** @var deviceModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $location = $entry->settings('extra.location.tencent', $entry->settings('extra.location'));
        if ($location && $location['lat'] && $location['lng']) {
            unset($location['area'], $location['address']);
            $result[] = [
                'name' => $entry->getName(),
                'location' => $location,
            ];
        }
    }

    JSON::success($result);
} elseif ($op == 'location') {
    //请求定位

    $id = request::trim('id');
    $lat = request::float('lat');
    $lng = request::float('lng');

    if (empty($id) || empty($lng) || empty($lat)) {
        JSON::fail('无效的参数！');
    }

    $user = Util::getCurrentUser();
    if (empty($user)) {
        JSON::fail('只能从微信中打开！');
    }

    $device = Device::findOne(['shadow_id' => $id]);
    if (empty($device)) {
        JSON::fail('找不到这个设备！');
    }

    $result = Helper::validateLocation($user, $device, $lat, $lng);

    if (is_error($result)) {
        JSON::fail($result['message']);
    }

    JSON::success("成功！");

} elseif ($op == 'adv_review') {

    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        Util::resultAlert('找不到这个用户或者用户已被禁用！', 'error');
    }

    $adv_id = request::int('id');
    if ($user->getId() != settings('notice.reviewAdminUserId') || request::str('sign') !== sha1(
            App::uid().$user->getOpenid().$adv_id
        )) {
        Util::resultAlert('无效的请求！', 'error');
    }

    $adv = Advertising::get($adv_id);
    if (empty($adv) || $adv->getState() == Advertising::DELETED) {
        Util::resultAlert('找不到这个广告！', 'error');
    }

    if ($adv->getReviewResult() == ReviewResult::PASSED) {
        request::is_ajax() ? JSON::success('已通过审核！') : Util::resultAlert('已通过审核！');
    }

    if ($adv->getReviewResult() == ReviewResult::REJECTED) {
        request::is_ajax() ? JSON::success('已拒绝！') : Util::resultAlert('已拒绝！', 'warning');
    }

    $fn = request::str('fn');
    if ($fn == 'pass') {
        if (Advertising::pass($adv_id, _W('username'))) {
            request::is_ajax() ? JSON::success('广告已经通过审核！') : Util::resultAlert('广告已经通过审核！');
        }
        request::is_ajax() ? JSON::fail('审核操作失败！') : Util::resultAlert('审核操作失败！', 'error');
    } elseif ($fn == 'reject') {
        if (Advertising::reject($adv_id)) {
            request::is_ajax() ? JSON::success('已拒绝！') : Util::resultAlert('已拒绝！');
        }
        request::is_ajax() ? JSON::fail('审核操作失败！') : Util::resultAlert('审核操作失败！', 'error');
    }

    $tpl_data = [
        'id' => $adv->getId(),
        'sign' => request::str('sign'),
        'title' => $adv->getTitle(),
        'type' => Advertising::desc($adv->getType()),
    ];

    $agent_id = $adv->getAgentId();
    if ($agent_id) {
        $agent = Agent::get($agent_id);
        if (empty($agent)) {
            request::is_ajax() ? JSON::fail('找不到上传广告的代理商！') : Util::resultAlert('找不到上传广告的代理商！', 'error');
        }
        $tpl_data['agent'] = $agent->profile();
    }


    switch ($adv->getType()) {
        case Advertising::SCREEN:
            $media = $adv->getExtraData('media');
            if ($media == 'srt') {
                $tpl_data['content'] = $adv->getExtraData('text');
            } elseif ($media == 'image') {
                $tpl_data['images'] = [$adv->getExtraData('url')];
            } elseif ($media == 'video') {
                $tpl_data['videos'] = [$adv->getExtraData('url')];
            } elseif ($media == 'audio') {
                $tpl_data['audios'] = [$adv->getExtraData('url')];
            }
            break;
        case Advertising::SCREEN_NAV:
            $tpl_data['images'] = [$adv->getExtraData('url')];
            break;
        case Advertising::WELCOME_PAGE:
        case Advertising::GET_PAGE:
            $tpl_data['images'] = $adv->getExtraData('images');
            break;
        case Advertising::REDIRECT_URL:
            $tpl_data['content'] = $adv->getExtraData('url', '');
            break;
        case Advertising::PUSH_MSG:
            $tpl_data['content'] = $adv->getExtraData('msg');
            break;
        case Advertising::GOODS:
            $tpl_data['images'] = [$adv->getExtraData('image')];
            $tpl_data['content'] = $adv->getExtraData('url');
            break;
        case Advertising::QRCODE:
            $tpl_data['content'] = $adv->getExtraData('text');
            $tpl_data['images'] = [$adv->getExtraData('image')];
            break;
        case Advertising::LINK:
            $tpl_data['content'] = $adv->getExtraData('url');
            $tpl_data['images'] = [$adv->getExtraData('image')];
    }

    if ($tpl_data['audios']) {
        foreach ($tpl_data['audios'] as $index => $url) {
            $tpl_data['audios'][$index] = Util::toMedia($url);
        }
    }
    if ($tpl_data['videos']) {
        foreach ($tpl_data['videos'] as $index => $url) {
            $tpl_data['videos'][$index] = Util::toMedia($url);
        }
    }
    if ($tpl_data['images']) {
        foreach ($tpl_data['images'] as $index => $url) {
            $tpl_data['images'][$index] = Util::toMedia($url);
        }
    }

    app()->showTemplate('review', $tpl_data);

} elseif ($op == 'profile') {
    $user = Util::getCurrentUser();
    if ($user) {
        if (request::has('sex')) {
            $user->updateSettings('customData.sex', request::int('sex'));
        }
    }
} elseif ($op == 'upload_pic') {

    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户！');
    }

    We7::load()->func('file');
    $res = We7::file_upload($_FILES['pic']);

    if (!is_error($res)) {
        $filename = $res['path'];
        if ($res['success'] && $filename) {
            try {
                We7::file_remote_upload($filename);
            } catch (Exception $e) {
                Log::error('mobile_device_fb', $e->getMessage());
            }
        }
        $url = $filename;
        JSON::success(['data' => $url]);
    } else {
        JSON::fail(['msg' => '上传失败！']);
    }

} elseif ($op == 'migrate') {
    //代理商小程序更改后，代理商数据迁移

    $user = Util::getCurrentUser([
        'create' => true,
        'update' => true,
    ]);
    
    if (empty($user) || $user->isBanned()) {
        JSON::fail('找不到用户！');
    }

    if ($user->isAgent()) {
        JSON::fail('用户已经是代理商！');
    }

    if ($user->isPartner()) {
        JSON::fail('用户已经是合伙人！');
    }

    if ($user->isKeeper()) {
        JSON::fail('用户已经是营运人员！');
    }

    $token = request::str('token');

    $agent = api\wx\common::getUser($token);
    if (empty($agent)) {
        JSON::fail('找不到这个代理商！');
    }

    if (!$agent->isAgent()) {
        JSON::fail('用户不是我们的代理商！');
    }

    $agent = $agent->agent();

    if ($user->getId() == $agent->getId()) {
        JSON::fail('代理商已完成迁移！');
    }

    if (!$agent->acquireLocker(User::COMMISSION_BALANCE_LOCKER)) {
        JSON::fail('无法锁定用户！');
    }

    if (!$user->acquireLocker(User::COMMISSION_BALANCE_LOCKER)) {
        JSON::fail('无法锁定用户！');
    }

    $result = Util::transactionDo(function () use ($user, $agent) {
        $total = $agent->getCommissionBalance()->total();
        $balance_total = $agent->getBalance()->total();

        $data =  [
            'admin' => _W('username'),
            'ip' => CLIENT_IP,
            'user-agent' => $_SERVER['HTTP_USER_AGENT'],
            'memo' => '系统公众号迁移',
        ];

        if (!$agent->commission_change(0 - $total, CommissionBalance::ADJUST, $data)) {
            return err('余额变动失败！');
        }

        if (!$user->commission_change($total, CommissionBalance::ADJUST, $data)) {
            return err('余额变动失败！');
        }

        if (!$agent->getBalance()->change(0 - $balance_total, Balance::ADJUST, $data)) {
            return err('积分变动失败！');
        }

        if (!$user->getBalance()->change($balance_total, Balance::ADJUST, $data)) {
            return err('积分变动失败！');
        }

        $user_openid = $user->getOpenid();
        $user->setOpenid(Util::random(16, true));
        if (!$user->save()) {
            return err('无法保存用户信息！');
        }

        $agent_openid = $agent->getOpenid();
        $agent->setOpenid($user_openid);
        $agent->settings('agentData.openid', $agent_openid);
        if (!$agent->save()) {
            return err('无法保存用户信息！');
        }

        $user->setOpenid($agent_openid);
        if (!$user->save()) {
            return err('无法保存用户信息！');
        }

        if (!$agent->remove('commission_balance')) {
            return err('无法清除余额缓存！');
        }

        if (!$user->remove('commission_balance')) {
            return err('无法清除余额缓存！');
        }
        
        if (!$agent->remove('balance:cache')) {
            return err('无法清除余额缓存！');
        }

        if (!$user->remove('balance:cache')) {
            return err('无法清除余额缓存！');
        }

        return true;
    });

    if (is_error($result)) {
        return $result;
    }

    //清除原来的登录信息
    foreach (LoginData::query(['user_id' => [$user->getId(), $agent->getId()]])->findAll() as $entry) {
        $entry->destroy();
    }

    return ['msg' => '完成！'];
}
