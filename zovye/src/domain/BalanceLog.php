<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;


use zovye\base\ModelObjFinder;
use zovye\model\balance_logsModelObj;
use function zovye\m;

class BalanceLog
{
    public static function create($data = []): ?balance_logsModelObj
    {
        if ($data['extra']) {
            $data['extra'] = balance_logsModelObj::serializeExtra($data['extra']);
        }

        return m('balance_logs')->create($data);
    }

    public static function query($condition = []): ModelObjFinder
    {
        return m('balance_logs')->query($condition);
    }

    public static function findOne($condition = []): ?balance_logsModelObj
    {
        return self::query($condition)->findOne();
    }
}