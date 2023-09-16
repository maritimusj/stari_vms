<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\domain;

use Exception;
use zovye\base\ModelObjFinder;
use zovye\Log;
use zovye\model\lockerModelObj;
use zovye\util\Util;
use zovye\We7;
use function zovye\m;

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
     * @return ModelObjFinder
     */
    public static function query($condition = []): ModelObjFinder
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

    public static function flock($uid, callable $callback)
    {
        $hash_val = sha1($uid);

        $first = substr($hash_val, 0, 4);
        $second = substr($hash_val, 4);

        $dir = RUNTIME_DIR.'locker'.DIRECTORY_SEPARATOR.$first.DIRECTORY_SEPARATOR;

        We7::make_dirs($dir);

        $fp = fopen($dir.$second.'.lock', 'w+');
        if ($fp) {
            if (flock($fp, LOCK_EX)) {
                if (DEBUG) {
                    fwrite($fp, REQUEST_ID."\r\n");
                    fwrite($fp, date('Y-m-d H:i:s')."\r\n");
                    fwrite($fp, $uid."\r\n");
                }
                if ($callback) {
                    $result = $callback();
                }
                flock($fp, LOCK_UN);
            }
            fclose($fp);
        }

        return $result ?? null;
    }

    /**
     * @param string $uid 锁的全局唯一UID
     * @param string $requestID
     * @param int $available 可用次数，即可重入的次数
     * @param int $expired_at
     * @param bool $auto_release
     * @return lockerModelObj|null
     */
    public static function load(
        string $uid = '',
        string $requestID = REQUEST_ID,
        int $available = 0,
        int $expired_at = 0,
        bool $auto_release = true
    ): ?lockerModelObj {
        if (empty($uid)) {
            $uid = Util::generateUID();
        }

        return self::flock($uid, function () use ($uid, $requestID, $auto_release, $available, $expired_at) {
            $locker = self::get($uid, true);
            if ($locker) {
                if ($locker->isExpired()) {
                    $locker->destroy();
                } else {
                    if ($locker->reenter($requestID)) {
                        if ($auto_release) {
                            self::registerLockerDestroy($locker);
                        }

                        return $locker;
                    }

                    return null;
                }
            }

            try {
                $locker = self::create([
                    'uid' => $uid,
                    'request_id' => $requestID,
                    'available' => max(1, $available),
                    'used' => 1,
                    'expired_at' => max($expired_at, 0),
                ]);
                if ($locker && $auto_release) {
                    self::registerLockerDestroy($locker);
                }

                return $locker;
            } catch (Exception $e) {
                Log::error('locker', $e->getMessage());
            }

            return null;
        });
    }

    public static function try(
        string $uid = '',
        $requestID = REQUEST_ID,
        int $retries = 0,
        $retry_delay_seconds = 1,
        int $available = 0,
        int $expired_after_seconds = 60,
        bool $auto_release = true
    ): ?lockerModelObj {
        $i = 0;
        $expired_at = time() + $expired_after_seconds;
        do {
            $locker = self::load($uid, $requestID, $available, $expired_at, $auto_release);
            if ($locker) {
                return $locker;
            }
            if (++$i > $retries) {
                break;
            }
            sleep($retry_delay_seconds);
        } while (time() < $expired_at);

        return null;
    }

    public static function enter(string $requestID, $auto_release = true): ?lockerModelObj
    {
        $locker = self::findOne([
            'request_id' => $requestID,
        ]);
        if ($locker && $locker->reenter($requestID)) {
            if ($auto_release) {
                self::registerLockerDestroy($locker);
            }

            return $locker;
        }

        return null;
    }
}