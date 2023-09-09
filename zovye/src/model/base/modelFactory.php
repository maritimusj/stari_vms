<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model\base;

use zovye\Util;
use zovye\We7;
use function zovye\is_error;

class modelFactory
{
    private $objClassname;
    private $shortName;

    /**
     * modelFactory constructor.
     * @param string $objClassname
     * @param string $shortName
     */
    public function __construct(string $objClassname, string $shortName)
    {
        $this->objClassname = $objClassname;
        $this->shortName = $shortName;
    }

    /**
     * @param null $mode
     * @return string
     */
    public function getTableName($mode = modelObj::OP_UNKNOWN): string
    {
        /** @var modelObj $objClassname */
        $objClassname = $this->objClassname;

        return $objClassname::getTableName($mode);
    }

    /**
     * @return string
     */
    public function objClassname(): string
    {
        return $this->objClassname;
    }

    /**
     * @return string
     */
    public function shortName(): string
    {
        return $this->shortName;
    }

    /**
     * @param array $data
     * @return modelObj|mixed
     */
    public function create(array $data = [])
    {
        /** @var modelObj $objClassname */
        $objClassname = $this->objClassname;
        if (call_user_func([$objClassname, 'hasCreatetime']) && empty($data['createtime'])) {
            $data['createtime'] = time();
        }
        $res = We7::pdo_insert($objClassname::getTableName(modelObj::OP_WRITE), $data);
        if ($res) {
            $id = intval(We7::pdo_insert_id());
            return $this->load($id);
        }

        return null;
    }

    /**
     * @param int $id
     * @return modelObj|mixed
     */
    public function load(int $id)
    {
        if ($id > 0) {
            $obj = new $this->objClassname($id, $this);
            $data = $this->__loadFromDb($obj);
            if ($data) {
                return $obj->__setData($data);
            }
        }

        return null;
    }

    /**
     * @param mixed $obj
     * @param array|mixed $seg_arr
     * @param bool $ignoreCache
     * @return array
     */
    public function __loadFromDb($obj, $seg_arr = [], bool $ignoreCache = false): array
    {
        $seg_arr = is_array($seg_arr) ? $seg_arr : [$seg_arr];

        /** @var modelObj $objClassname */
        $objClassname = $this->objClassname;

        //初始化返回值
        $res = [];

        $src = $objClassname::fromDbOrCache($obj, $seg_arr);
        $cache_missed = [];

        if ($src['cache']) {
            if ($ignoreCache) {
                $cache_missed = $src['cache'];
            } else {
                //处理对象缓存
                $cache_data = $this->getCacheData($obj);
                foreach ($src['cache'] as $seg) {
                    if (isset($cache_data[$seg])) {
                        $res[$seg] = $cache_data[$seg];
                    } else {
                        $cache_missed[] = $seg;
                    }
                }
            }
        }
        $seg_from_db = [];
        if ($src['db']) {
            $seg_from_db = $src['db'];
        }
        if ($cache_missed) {
            $seg_from_db = array_merge($seg_from_db, $cache_missed);
        }

        if ($seg_from_db) {
            $db_res = We7::pdo_get(
                $objClassname::getTableName(modelObj::OP_READ),
                ['id' => $obj->getId()],
                $seg_from_db
            ) ?: [];

            if ($db_res) {
                //处理对象缓存
                $cache_data = $cache_data ?? $this->getCacheData($obj);
                foreach ($cache_missed as $seg) {
                    $cache_data[$seg] = $db_res[$seg];
                }
                $this->writeCacheData($obj, $cache_data);
                $res = array_merge($res, $db_res);
            }
        }

        return $res;
    }

    /**
     * @param mixed $obj
     * @return mixed
     */
    protected function getCacheData($obj): array
    {
        $cache_data = We7::cache_read($this->getCacheKey($obj));
        if (is_error($cache_data) || empty($cache_data)) {
            $cache_data = [];
        }

        return $cache_data;
    }

    /**
     * @param mixed $obj
     * @return string
     */
    protected function getCacheKey($obj): string
    {
        $id = is_object($obj) ? $obj->getId() : $obj;

        return APP_NAME.":$this->shortName:$id";
    }

    /**
     * @param $obj
     * @param $data
     * @return mixed
     */
    protected function writeCacheData($obj, $data)
    {
        if ($data) {
            return We7::cache_write($this->getCacheKey($obj), $data);
        } else {
            return We7::cache_delete($this->getCacheKey($obj));
        }
    }

    /**
     * @param modelObj $obj
     * @return bool
     */
    public function remove(modelObj $obj): bool
    {
        /** @var modelObj $objClassname */
        $objClassname = $this->objClassname;
        if (false !== We7::pdo_delete($objClassname::getTableName(modelObj::OP_WRITE), ['id' => $obj->getId()])) {
            $src = $objClassname::fromDbOrCache($obj, $objClassname::getProps());
            if ($src['cache']) {
                $this->removeCacheData($obj);
            }

            return true;
        }

        return false;
    }

    /**
     * @param $id
     */
    protected function removeCacheData($id)
    {
        We7::cache_delete($this->getCacheKey($id));
    }

    /**
     * @param array|mixed $condition
     * @return int
     */
    public function count($condition = []): int
    {
        return (new modelObjFinder($this))->where($condition)->count();
    }

    public function delete($condition = []): bool
    {
        return (new modelObjFinder($this))->where($condition)->delete();
    }

    public function exists($condition = []): bool
    {
        $res = $this->where($condition);

        return !empty($res->get('id'));
    }

    /**
     * @param array|mixed $condition
     * @param bool $lazy
     * @return modelObjIterator|modelObjIteratorLazy
     */
    public function findAll($condition = [], bool $lazy = false)
    {
        return (new modelObjFinder($this))->findAll($condition, $lazy);
    }

    /**
     * @param array|mixed $condition
     * @param bool $lazy
     * @return mixed
     */
    public function findOne($condition = [], bool $lazy = false)
    {
        return (new modelObjFinder($this))->limit(1)->findAll($condition, $lazy)->current();
    }

    /**
     * @param array|mixed $condition
     * @return modelObjFinder
     */
    public function query($condition = []): modelObjFinder
    {
        return (new modelObjFinder($this))->where($condition);
    }

    /**
     * @param array|mixed $condition
     * @return modelObjFinder
     */
    public function where($condition = []): modelObjFinder
    {
        return (new modelObjFinder($this))->where($condition);
    }

    /**
     * @param modelObj $obj
     * @param null $seg_arr
     * @param array|mixed $condition
     * @return mixed
     */
    public function __saveToDb(modelObj $obj, $seg_arr = null, $condition = [])
    {
        $data = $obj->__getData($seg_arr);
        if (empty($data)) {
            return true;
        }

        /** @var modelObj $objClassname */
        $objClassname = $this->objClassname;

        $condition = is_array($condition) ? $condition : [$condition];
        $condition['id'] = $obj->getId();

        $res = We7::pdo_update($objClassname::getTableName(modelObj::OP_WRITE), $data, $condition);
        if ($res !== false) {
            $data_keys = array_keys($data);

            if (Util::traitUsed($obj, 'DirtyChecker')) {
                $obj->clearDirty($data_keys);
            }

            $src = $objClassname::fromDbOrCache($obj, $data_keys);
            //处理对象缓存
            if ($src['cache']) {
                $cache_data = $this->getCacheData($obj);
                foreach ($src['cache'] as $seg) {
                    $cache_data[$seg] = $data[$seg];
                }

                $this->writeCacheData($obj, $cache_data);
            }
        }

        return $res;
    }
}
