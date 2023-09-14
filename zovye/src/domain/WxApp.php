<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use zovye\base;
use zovye\We7;
use function zovye\m;

class WxApp
{
    public static function query($condition = []): base\ModelObjFinder
    {
        return m('wx_app')->where(We7::uniacid([]))->where($condition);
    }

    public static function get($id, $is_appid = false)
    {
        static $cache = [];
        if ($id) {
            if ($cache[$id]) {
                return $cache[$id];
            }
            $cond = [];
            if ($is_appid) {
                $cond['key'] = strval($id);
            } else {
                $cond['id'] = intval($id);
            }
            $wx_app = self::findOne($cond);
            if ($wx_app) {
                $cache[$wx_app->getId()] = $wx_app;
                $cache[$wx_app->getKey()] = $wx_app;

                return $wx_app;
            }
        }

        return null;
    }

    public static function findOne($condition)
    {
        return self::query($condition)->findOne();
    }

    public static function create($data = [])
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        return m('wx_app')->create($data);
    }

    public static function exists($condition = []): bool
    {
        return m('wx_app')->where($condition)->exists();
    }

}