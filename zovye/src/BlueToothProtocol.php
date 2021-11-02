<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\Contract\bluetooth\IBlueToothProtocol;

class BlueToothProtocol
{
    public static function all(): array
    {
        static $protocols = [];
        if (empty($protocols)) {
            foreach (glob(MODULE_ROOT . '/lib/bluetooth/*', GLOB_ONLYDIR) as $name) {
                $protoName = basename($name);
                $proto = self::get($protoName);
                if ($proto) {
                    $protocols[] = [
                        'name' => $protoName,
                        'title' => $proto->getTitle(),
                    ];
                }

            }
        }

        return $protocols;
    }

    public static function get($protocol): ?IBlueToothProtocol
    {
        $classname = "\bluetooth\\$protocol\\protocol";
        if (class_exists($classname)) {
            return new $classname();
        }
        return null;
    }
}