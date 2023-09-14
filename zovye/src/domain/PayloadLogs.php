<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use zovye\base\ModelObjFinder;
use zovye\model\payload_logsModelObj;
use zovye\We7;
use function zovye\m;

class PayloadLogs
{
    public static function create(array $data = []): ?payload_logsModelObj
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        /** @var payload_logsModelObj $classname */
        $classname = m('goods')->objClassname();
        $data['extra'] = $classname::serializeExtra($data['extra']);

        return m('payload_logs')->create($data);
    }


    public static function get($id): ?payload_logsModelObj
    {
        /** @var payload_logsModelObj $cache */
        static $cache = [];

        $id = intval($id);
        if ($id) {
            if ($cache[$id]) {
                return $cache[$id];
            }
            $log = self::query()->findOne(['id' => $id]);
            if ($log) {
                $cache[$log->getId()] = $log;

                return $log;
            }
        }

        return null;
    }

    public static function query($condition = []): ModelObjFinder
    {
        return m('payload_logs')->where(We7::uniacid([]))->where($condition);
    }

}