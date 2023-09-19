<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use zovye\api\common;
use zovye\domain\Device;
use zovye\domain\DeviceFeedback;
use zovye\domain\LoginData;
use zovye\domain\User;
use zovye\Request;
use function zovye\err;

class fb
{
    public static function feedback(): array
    {
        if (empty($token)) {
            $token = common::getToken();
        }

        if (empty($token)) {
            return err('请先登录后再请求数据！[101]');
        }

        $login_data = LoginData::get($token);
        if (empty($login_data)) {
            return err('请先登录后再请求数据！[102]');
        }

        $user = User::get($login_data->getUserId());
        if (empty($user)) {
            return err('请先登录后再请求数据！[103]');
        }

        $device_id = Request::str('device');

        $text = Request::str('text');
        $pics = Request::array('pics');

        $device = Device::get($device_id, true);
        if (empty($device)) {
            return err('设备不存在！');
        }

        $data = [
            'device_id' => $device->getId(),
            'user_id' => $user->getId(),
            'text' => $text,
            'pics' => serialize($pics),
            'createtime' => time(),

        ];

        if (DeviceFeedback::create($data)) {
            return ['msg' => '反馈成功！'];
        } else {
            return err('反馈失败！');
        }

    }
}