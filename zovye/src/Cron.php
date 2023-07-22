<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use Exception;

class Cron
{
    public static function query($condition = []): base\modelObjFinder
    {
        return m('cron')->query(We7::uniacid([]))->where($condition);
    }

    public static function create($uid, $url, $spec, $extra = null)
    {
        $data = We7::uniacid([
            'uid' => $uid,
            'url' => $url,
            'spec' => $spec,
        ]);

        if ($extra) {
            $data['extra'] = json_encode($extra);
        }

        return m('cron')->create($data);
    }

    public static function getList($uid)
    {
        return self::query(['uid' => $uid])->findAll();
    }

    public static function describe($expression): string
    {
        try {
            require MODULE_ROOT.'vendor/autoload.php';
            return (new \Panlatent\CronExpressionDescriptor\ExpressionDescriptor($expression, 'zh-Hans'))->getDescription();            
        } catch(Exception $e) {
            return "";
        }
    }
}