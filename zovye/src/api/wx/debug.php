<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\api\wx;

use zovye\domain\User;
use zovye\Request;
use zovye\util\Util;
use function zovye\err;

class debug
{
    public static function mode(): array
    {
        $data = include ZOVYE_ROOT.DIRECTORY_SEPARATOR.'debug.php';
        if ($data) {
            return [
                'debug' => intval($data['debug']),
            ];
        }

        return ['debug' => 0];
    }

    public static function login(): array
    {
        $data = include ZOVYE_ROOT.DIRECTORY_SEPARATOR.'debug.php';
        if (!empty($data) && !empty($data['debug']) && $data['password'] === Request::str('password')) {
            $mobile = $data['phoneNumber'];
            $user = User::findOne(['mobile' => $mobile]);
            if ($user) {
                return agent::doUserLogin(
                    [
                        'phoneNumber' => $mobile,
                        'openid' => $user->getOpenid(),
                        'session_key' => Util::random(16),
                    ]
                );
            } else {
                return err('找不到指定的用户！');
            }
        }

        return err('参数不正确！');
    }
}