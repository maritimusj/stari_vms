<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

use Exception;
use zovye\base\modelObj;

class DBUtil
{
    /**
     * 返回一个表结构描述.
     *
     * @param string $tab_name
     *
     * @return array
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
     * 在事务中执行指定函数.
     *
     * @param callable $cb 要执行的函数, return error(..)或者抛出异常会回退事务
     *
     * @return mixed
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

        We7::cache_write($key, microtime(true));

        We7::pdo_begin();
        try {
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
     * 查找指定对象
     * @param $tb
     * @param $val
     * @param null $hint
     * @param null $must
     *
     * @return mixed
     * @deprecated
     */
    public static function findObject($tb, $val, $hint = null, $must = null)
    {
        if ($tb && $val) {
            $must_cond_fn = function ($cond = []) use ($must) {
                $cond = is_array($cond) ? $cond : [$cond];
                $cond['uniacid'] = We7::uniacid();
                if ($must) {
                    if (is_array($must)) {
                        $cond = array_merge($cond, $must);
                    } elseif (is_string($must)) {
                        $cond[] = $must;
                    }
                }
                foreach ($cond as $key => $val) {
                    if (empty($val)) {
                        unset($cond[$key]);
                    }
                }

                return $cond;
            };

            $query = m($tb)->query();
            if (empty($hint)) {
                if (is_scalar($val)) {
                    if (is_numeric($val)) {
                        $query->where($must_cond_fn(['id' => intval($val)]));
                    } elseif (is_string($val)) {
                        $query->where($must_cond_fn())->where($val);
                    }
                } elseif (is_array($val)) {
                    $query->where($must_cond_fn());
                    foreach ($val as $key => $entry) {
                        if ($entry) {
                            if (is_numeric($key)) {
                                $query->where($entry);
                            } elseif ($key) {
                                $query->where([$key => $entry]);
                            }
                        }
                    }
                }
            } elseif (is_scalar($hint)) {
                if (is_scalar($val)) {
                    $query->where($must_cond_fn([$hint => $val]));
                } elseif (is_array($val)) {
                    $query->where($must_cond_fn([]));
                    foreach ($val as $entry) {
                        $query->whereOr([$hint => $entry]);
                    }
                }
            } elseif (is_array($hint)) {
                if (is_scalar($val)) {
                    $query->where($must_cond_fn([]));
                    foreach ($hint as $key) {
                        if ($key) {
                            $query->whereOr([$key => $val]);
                        }
                    }
                }
            }

            return $query->limit(1)->findAll()->current();
        }

        return null;
    }

    /**
     * 通过写入唯一值，锁定数据库中某一行数据，成功返回锁对象，失败返回null.
     *
     * @param modelObj $obj 数据对象，必须是modelObj子类
     * @param array $cond 条件数组，用于判断是否可以锁定对象
     * @param bool $auto_unlock 是否自动解锁
     *
     * @return ?RowLocker
     */
    public static function lockObject(modelObj $obj, array $cond, bool $auto_unlock = false): ?RowLocker
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