<?php


namespace zovye;


use zovye\base\modelObjFinder;
use zovye\model\lockerModelObj;

class Locker
{
    /**
     * @param array $data
     * @return ?lockerModelObj
     */
    public static function create(array $data = []): ?lockerModelObj
    {
        return m('locker')->create($data);
    }

    /**
     * @param mixed $condition
     * @return modelObjFinder
     */
    public static function query($condition = []): modelObjFinder
    {
        return m('locker')->query($condition);
    }

    /**
     * @param mixed $condition
     * @return lockerModelObj|null
     */
    public static function findOne($condition = []): ?lockerModelObj
    {
        return self::query($condition)->findOne();
    }

    /**
     * @param mixed $condition
     * @return bool
     */
    public static function exists($condition = []): bool
    {
        return self::query($condition)->exists();
    }

    /**
     * @param $id
     * @param bool $is_uid
     * @return lockerModelObj|null
     */
    public static function get($id, bool $is_uid = false): ?lockerModelObj
    {
        if ($is_uid) {
            return self::findOne(['uid' => strval($id)]);
        }
        return self::findOne(['id' => intval($id)]);
    }

    protected static function registerLockerDestroy(lockerModelObj $locker)
    {
        $id = $locker->getId();
        register_shutdown_function(function () use ($id) {
            $locker = self::get($id);
            if ($locker) {
                $locker->release();
            }
        });
    }

    /**
     * @param string $uid 锁的全局唯一UID
     * @param int $available 可用次数，即可重入的次数
     * @param int $expire_seconds 几秒后过期，0为默认：脚本运行完过期
     * @return lockerModelObj|null
     */
    public static function load(string $uid = '', int $available = 0, int $expire_seconds = 0): ?lockerModelObj
    {
        if (empty($uid)) {
            $uid = Util::generateUID();
        }

        $locker = self::get($uid, true);
        if ($locker) {            
            if ($locker->isExpired()) {
                $locker->destroy();
            } else {               
                if ($locker->reenter()) {                
                    self::registerLockerDestroy($locker);
                    return $locker;
                }
                return null;
            }
        }

        $locker = self::create([
            'uid' => $uid,
            'request_id' => REQUEST_ID,
            'available' => max(1, $available),
            'used' => 1,
            'expired_at' => $expire_seconds > 0 ? time() + $expire_seconds : 0,
        ]);
        if ($locker) {
            self::registerLockerDestroy($locker);
        }

        return $locker;
    }

    public static function try(string $uid = '', int $retries = 0, $retry_delay_seconds = 1, int $available = 0, int $expired_after_seconds = 60): ?lockerModelObj
    {
        $i = 0;
        do {
            $locker = self::load($uid, $available, $expired_after_seconds);
            if ($locker) {
                return $locker;
            }
            if ($i++ < $retries) {
                sleep($retry_delay_seconds);
            }
        } while ($i < $retries);

        return null;
    }
}