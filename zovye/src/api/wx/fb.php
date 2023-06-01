<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use Exception;
use zovye\Device;
use zovye\Log;
use zovye\Request;
use zovye\LoginData;
use zovye\User;
use zovye\We7;
use function zovye\err;
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
            return err('请先登录后再请求数据！[101]');
        }

        We7::load()->func('file');
        $res = We7::file_upload($_FILES['pic']);

        if (!is_error($res)) {

            $filename = $res['path'];
            if ($res['success'] && $filename) {
                try {
                    We7::file_remote_upload($filename);
                } catch (Exception $e) {
                    Log::error('doPageFeedBack', $e->getMessage());
                }

            }
            $url = $filename;

            return ['data' => $url];
        } else {
            return err('上传失败！');
        }
    }

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

        if (m('device_feedback')->create($data)) {
            return ['msg' => '反馈成功！'];
        } else {
            return err('反馈失败！');
        }

    }
}