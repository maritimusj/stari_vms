<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\api\wx;

use zovye\request;
use zovye\State;
use zovye\User;
use zovye\Util;
use function zovye\error;

class debug
{
    public static function mode(): array
    {
        $data = include ZOVYE_ROOT . DIRECTORY_SEPARATOR . 'debug.php';
        if ($data) {
            return [
                'debug' => intval($data['debug']),
            ];
        }

        return ['debug' => 0];
    }

    public static function login(): array
    {
        $data = include ZOVYE_ROOT . DIRECTORY_SEPARATOR . 'debug.php';
        if (!empty($data) && !empty($data['debug']) && $data['password'] === request::str('password')) {
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
                return error(State::ERROR, '找不到指定的用户！');
            }
        }

        return error(State::ERROR, '参数不正确！');
    }
}