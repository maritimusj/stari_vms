<?php

namespace zovye;

class WxApp
{
    public static function query($condition = [])
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
            $wxapp = self::findOne($cond);
            if ($wxapp) {
                $cache[$wxapp->getId()] = $wxapp;
                $cache[$wxapp->getKey()] = $wxapp;
                return $wxapp;
            }            
        }
        return null;
    }

    public static function findOne($condition)
    {
        return self::query($condition)->findOne();
    }

    public static function create($data)
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