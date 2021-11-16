<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use Exception;
use zovye\Device;
use zovye\request;
use zovye\LoginData;
use zovye\State;
use zovye\User;
use zovye\Util;
use zovye\We7;
use function zovye\error;
use function zovye\is_error;
use function zovye\m;

class fb
{
    public static function pic(): array
    {
        if (empty($token)) {
            $token = common::getToken();
        }
        if (empty($token)) {
            return error(State::ERROR, '请先登录后再请求数据！[101]');
        }

        We7::load()->func('file');
        $res = We7::file_upload($_FILES['pic'], 'image');

        if (!is_error($res)) {

            $filename = $res['path'];
            if ($res['success'] && $filename) {
                try {
                    We7::file_remote_upload($filename);
                } catch (Exception $e) {
                    Util::logToFile('doPageFeedBack', $e->getMessage());
                }

            }
            $url = $filename;
            return ['data' => $url];
        } else {
            return error(State::ERROR, '上传失败！');
        }
    }

    public static function feedback(): array
    {
        if (empty($token)) {
            $token = common::getToken();
        }
        if (empty($token)) {
            return error(State::ERROR, '请先登录后再请求数据！[101]');
        }
        $login_data = LoginData::get($token);
        if (empty($login_data)) {
            return error(State::ERROR, '请先登录后再请求数据！[102]');
        }
        $user = User::get($login_data->getUserId());
        if (empty($user)) {
            return error(State::ERROR, '请先登录后再请求数据！[103]');
        }

        $device_id = request::int('device');

        $text = request::str('text');
        $pics = request::array('pics');

        $device = Device::get($device_id, true);
        $data = [
            'device_id' => $device->getId(),
            'user_id' => $user->getId(),
            'text' => $text,
            'pics' => serialize($pics),
            'createtime' => time(),

        ];

        if (m('device_feedback')->create($data)) {
            return ['msg' => '反馈成功！'];
        } else {
            return error(State::ERROR, '反馈失败！');
        }

    }
}