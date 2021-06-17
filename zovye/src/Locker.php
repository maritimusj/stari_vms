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
    public static function create($data = []): ?lockerModelObj
    {
        return m('locker')->create($data);
    }

    public static function query($condition = []): modelObjFinder
    {
        return m('locker')->query($condition);
    }

    public static function findOne($condition = []): ?lockerModelObj
    {
        return self::query($condition)->findOne();
    }

    public static function exists($condition = []): bool
    {
        return self::query($condition)->exists();
    }

    public static function get($id, bool $is_uid = false): ?lockerModelObj
    {
        if ($is_uid) {
            return self::findOne(['uid' => strval($id)]);
        }
        return self::findOne(['id' => intval($id)]);
    }

    protected static function registerLockerDestroy(lockerModelObj $locker)
    {
        if ($locker->getAvailable() <= 0) {
            register_shutdown_function(function () use ($locker) {
                $locker->destroy();
            });
        }
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
            'request_id' => $available > 0 ? REQUEST_ID : Util::generateUID(),
            'available' => max(0, $available - 1),
            'expired_at' => $expire_seconds > 0 ? time() + $expire_seconds : 0,
        ]);

        if ($locker) {
            self::registerLockerDestroy($locker);
        }

        return $locker;
    }

    public static function try(string $uid = '', int $tries = 0, $delay = 1, int $available = 0, int $expired_at = 60): ?lockerModelObj
    {
        $i = 0;
        do {
            $locker = self::load($uid, $available, $expired_at);
            if ($locker) {
                return $locker;
            }
            if ($i++ < $tries) {
                sleep($delay);
            }
        } while ($i < $tries);

        return null;
    }
}