<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\Contract\bluetooth\IBlueToothProtocol;

class BlueToothProtocol
{
    const BATTERY = 'battery';

    public static function all(): array
    {
        $protocols = [];

        foreach (glob(MODULE_ROOT.'/lib/bluetooth/*', GLOB_ONLYDIR) as $name) {
            $protoName = basename($name);
            $proto = self::get($protoName);
            if ($proto) {
                $protocols[] = [
                    'name' => $protoName,
                    'title' => $proto->getTitle(),
                ];
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