<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\domain;

use Exception;
use zovye\base;
use zovye\We7;
use function zovye\m;

class Cron
{
    public static function query($condition = []): base\ModelObjFinder
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
            if (file_exists(MODULE_ROOT.'vendor/autoload.php')) {
                require MODULE_ROOT.'vendor/autoload.php';
                return (new \Panlatent\CronExpressionDescriptor\ExpressionDescriptor($expression, 'zh-Hans', true))->getDescription();   
            }
        } catch(Exception $e) {
        }
        return "";
    }
}