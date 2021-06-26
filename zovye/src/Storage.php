<?php


namespace zovye;


use zovye\base\modelObjFinder;
use zovye\model\storageModelObj;
use zovye\traits\ExtraDataGettersAndSetters;

class Storage
{
    public function create($data = []): ?storageModelObj
    {
        if (empty($data['uniacid'])) {
            $data['uniacid'] = We7::uniacid();
        }

        /** @var ExtraDataGettersAndSetters $classname */
        $classname = m('storage')->objClassname();
        $data['extra'] = $classname::serializeExtra($data['extra']);

        return m('storage')->create($data);
    }

    /**
     * @param array $condition
     * @return modelObjFinder
     */
    public static function query(array $condition = []): modelObjFinder
    {
        return m('storage')->where(We7::uniacid([]))->where($condition);
    }

    /**
     * @param $cond
     * @return storageModelObj|null
     */
    public static function findOne($cond): ?storageModelObj
    {
        return self::query($cond)->findOne();
    }

    public static function exists($cond): bool
    {
        $cond = is_array($cond) ? $cond : ['uid' => strval($cond)];
        return self::query()->exists($cond);
    }
}