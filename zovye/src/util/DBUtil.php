<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\util;

use Exception;
use zovye\base\ModelObj;
use zovye\RowLocker;
use zovye\We7;
use function zovye\err;
use function zovye\is_error;

class DBUtil
{
    /**
     * 返回一个表结构描述
     */
    public static function tableSchema(string $tab_name): array
    {
        $ret = [];
        $db = We7::pdo();
        if ($db->tableexists($tab_name)) {
            $result = $db->fetchall('SHOW FULL COLUMNS FROM '.$db->tablename($tab_name));
            foreach ($result as $value) {
                $temp = [];
                $type = explode(' ', $value['Type'], 2);
                $temp['name'] = $value['Field'];
                $pieces = explode('(', $type[0], 2);
                if ($temp) {
                    $temp['type'] = $pieces[0];
                    $temp['length'] = rtrim($pieces[1], ')');
                }
                $temp['null'] = $value['Null'] != 'NO';
                //暂时去掉默认值的对比
                //if(isset($value['Default'])) {
                //    $temp['default'] = $value['Default'];
                //}
                $temp['signed'] = empty($type[1]);
                $temp['increment'] = $value['Extra'] == 'auto_increment';
                $ret['fields'][$value['Field']] = $temp;
            }
        }

        return $ret;
    }

    /**
     * 在事务中执行指定函数
     * @param callable $cb 要执行的函数, return error(..)或者抛出异常会回退事务
     */
    public static function transactionDo(callable $cb)
    {
        $key = 'transaction:'.REQUEST_ID;

        if (We7::cache_read($key)) {
            try {
                return $cb();
            } catch (Exception $e) {
                return err($e->getMessage());
            }
        }

        try {
            We7::cache_write($key, microtime(true));

            We7::pdo_begin();

            $ret = $cb();

            if (is_error($ret)) {
                We7::pdo_rollback();
            } else {
                We7::pdo_commit();
            }

            return $ret;

        } catch (Exception $e) {
            We7::pdo_rollback();

            return err($e->getMessage());
        } finally {
            We7::cache_delete($key);
        }
    }

    /**
     * 通过写入唯一值，锁定数据库中某一行数据，成功返回锁对象，失败返回null
     *
     * @param ModelObj $obj 数据对象，必须是modelObj子类
     * @param array $cond 条件数组，用于判断是否可以锁定对象
     * @param bool $auto_unlock 是否自动解锁
     *
     */
    public static function lockObject(ModelObj $obj, array $cond, bool $auto_unlock = false): ?RowLocker
    {
        $seg = key($cond);
        if (is_string($seg)) {
            $val = $cond[$seg];
            $condition = [
                'id' => $obj->getId(),
                $seg => $val,
            ];

            $locker = new RowLocker($obj->getTableName($obj::OP_WRITE), $condition, $seg, $auto_unlock);
            if ($locker->isLocked()) {
                return $locker;
            }
        }

        return null;
    }
}