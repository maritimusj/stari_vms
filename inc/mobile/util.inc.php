<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
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
} else if ($op == 'data') {

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
    $lng = request::float('lng');
    $lat = request::float('lat');

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

    $data = [
        'validated' => false,
        'time' => time(),
        'lng' => $lng,
        'lat' => $lat,
    ];

    $user->updateSettings('last.location', $data);

    //用户扫描设备后的定位信息
    $location = $device->settings('extra.location.tencent', $device->settings('extra.location'));
    if ($location && $location['lng'] && $location['lat']) {

        $distance = App::userLocationValidateDistance(1);
        $agent = $device->getAgent();
        if ($agent) {
            if ($agent->settings('agentData.location.validate.enabled')) {
                $distance = $agent->settings('agentData.location.validate.distance', $distance);
            }
        }

        $res = Util::getDistance($location, ['lng' => $lng, 'lat' => $lat]);
        if (is_error($res)) {
            Util::logToFile('location', $res);
            JSON::fail('哎呀，出错了');
        }

        if ($res > $distance) {
            $user->updateSettings('last.deviceId', '');
            JSON::fail('哎呀，设备太远了');
        }
    }

    $user->updateSettings('last.location.validated', true);
    JSON::success('成功！');
} elseif ($op == 'adv_review') {

    $user = Util::getCurrentUser();
    if (empty($user) || $user->isBanned()) {
        Util::resultAlert('找不到这个用户或者用户已被禁用！', 'error');
    }

    $adv_id = request::int('id');
    if ($user->getId() != settings('notice.reviewAdminUserId') || request::str('sign') !== sha1(App::uid() . $user->getOpenid() . $adv_id)) {
        Util::resultAlert('无效的请求！', 'error');
    }

    $adv = Advertising::get($adv_id);
    if (empty($adv) || $adv->getState() == Advertising::DELETED) {
        Util::resultAlert('找不到这个广告！', 'error');
    }

    if ($adv->getReviewResult() == ReviewResult::PASSED) {
        request::is_ajax() ? JSON::success('已通过审核！') : Util::resultAlert('已通过审核！', 'success');
    }

    if ($adv->getReviewResult() == ReviewResult::REJECTED) {
        request::is_ajax() ? JSON::success('已拒绝！') : Util::resultAlert('已拒绝！', 'warning');
    }

    $fn = request::str('fn');
    if ($fn == 'pass') {
        if (Advertising::pass($adv_id, _W('username'))) {
            request::is_ajax() ? JSON::success('广告已经通过审核！') : Util::resultAlert('广告已经通过审核！', 'success');
        }
        request::is_ajax() ? JSON::fail('审核操作失败！') : Util::resultAlert('审核操作失败！', 'error');
    } elseif ($fn == 'reject') {
        if (Advertising::reject($adv_id)) {
            request::is_ajax() ? JSON::success('已拒绝！') : Util::resultAlert('已拒绝！', 'success');
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

} elseif ($op == 'auth') {

    try {
        $user = User::get(request::int('user'));
        if (empty($user)) {
            throw new RuntimeException('找不到这个用户！');
        }

        $device = Device::get(request::int('device'));
        if (empty($device)) {
            throw new RuntimeException('找不到这个设备！');
        }

        $account = Account::get(request::int('account'));
        if (empty($account)) {
            throw new RuntimeException('找不到这个公众号！');
        }

        $code = request::str('code');

        $res = WxPlatform::getUserInfo($account, $code);
        if (is_error($res)) {
            throw new RuntimeException($res['message']);
        }

        if (empty($res['openid'])) {
            throw new RuntimeException('缺少openid');
        }

        $appid = $account->settings('authorization_info.authorizer_appid');
        if (!ComponentUser::exists(['appid' => $appid, 'openid' => $res['openid']])) {
            if (!ComponentUser::create([
                'appid' => $appid,
                'openid' => $res['openid'],
                'extra' => $res,
            ])) {
                throw new RuntimeException('保存用户授权信息出错！');
            }
        }

        $data = $account->format();
        unset($data['service_type']);

        $user->setLastActiveData([
            'account' => $data,
            'time' => time(),
        ]);

        $url = Util::murl('entry', ['device' => $device->getImei()]);
        Util::redirect($url);

    } catch (Exception $e) {
        Util::logToFile('auth_user', [
            'error' => $e->getMessage(),
        ]);

        Util::resultAlert($e->getMessage(), 'error');
    }
} elseif ($op == 'profile') {
    $user = Util::getCurrentUser();
    if ($user) {
        if (request::has('sex')) {
            $user->updateSettings('fansData.sex', request::int('sex'));
        }
    }
}
