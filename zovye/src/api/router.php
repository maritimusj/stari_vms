<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api;

use Exception;
use ReflectionMethod;
use zovye\domain\LoginData;
use zovye\JSON;
use zovye\Log;
use zovye\model\agentModelObj;
use zovye\model\keeperModelObj;
use zovye\model\userModelObj;

class router
{
    public static function exec($op, $map)
    {
        $fn = $map[$op];
        if (!is_callable($fn)) {
            JSON::fail('不正确的调用:'.$op);
        }

        try {
            $args = [];
            $ref = new ReflectionMethod($fn[0], $fn[1]);
            foreach ($ref->getParameters() as $arg) {
                $type = $arg->getType();
                if ($type->getName() == userModelObj::class) {
                    if ($arg->getName() == 'wx_app_user') {
                        $args[] = common::getWXAppUser();
                    } elseif ($arg->getName() == 'ali_user') {
                        $args[] = common::getUser(LoginData::ALI_APP_USER);
                    } else {
                        $args[] = common::getUser();
                    }
                } elseif ($type->getName() == agentModelObj::class) {
                    $args[] = common::getAgent();
                } elseif ($type->getName() == keeperModelObj::class) {
                    $args[] = common::getKeeper();
                } else {
                    throw new Exception('无法解析参数类型！');
                }
            }

            $result = call_user_func_array($fn, $args);
            JSON::result($result);

        } catch (Exception $e) {

            Log::error('router', [
                'error' => $e->getMessage(),
            ]);

            JSON::fail($e);
        }
    }
}