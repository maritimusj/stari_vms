<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api;

use Exception;
use ReflectionMethod;
use zovye\api\wx\common;
use zovye\domain\Agent;
use zovye\domain\Keeper;
use zovye\domain\User;
use zovye\JSON;

class router
{
    public static function exec($op, $map)
    {
        $fn = $map[$op];

        if (is_callable($fn)) {
            try {
                $args = [];

                $ref = new ReflectionMethod($fn);
                foreach ($ref->getParameters() as $arg) {
                    if ($arg instanceof User) {
                        $args[] = common::getUser();
                    } elseif ($arg instanceof Agent) {
                        $args[] = common::getAgent();
                    } elseif ($arg instanceof Keeper) {
                        $args[] = common::getKeeper();
                    } else {
                        trigger_error("can't resolve args of method", E_USER_ERROR);
                    }
                }

                $result = call_user_func_array($fn, $args);
                JSON::result($result);

            } catch (Exception $e) {
                JSON::fail($e);
            }
        }

        JSON::fail('不正确的调用:'.$op);
    }
}