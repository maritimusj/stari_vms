<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\base;

use we7\SqlParser;
use zovye\We7;
use function zovye\is_error;

class ModelObjFinder extends SqlParser
{
    private $factory;
    private $condition = [];
    private $conditionOr = [];
    private $limit = [];
    private $orderBy = [];
    private $groupBy = [];
    private $params = [];

    public function __construct(ModelFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * 重置所有条件
     * @return $this
     */
    public function resetAll(): ModelObjFinder
    {
        $this->condition = [];
        $this->conditionOr = [];
        $this->limit = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->params = [];

        return $this;
    }

    /**
     * @param mixed $condition
     * @return $this
     */
    public function where($condition = []): ModelObjFinder
    {
        if ($condition) {
            $this->parseCondition($condition);
        }

        return $this;
    }

    /**
     * @param $condition
     * @param bool $or
     * @return void
     */
    private function parseCondition($condition, bool $or = false): void
    {
        $res = parent::parseParameter($condition, $or ? 'OR' : 'AND');
        if (!is_error($res)) {
            if ($or) {
                $this->conditionOr[] = "{$res['fields']}";
            } else {
                $this->condition[] = "{$res['fields']}";
            }
            $this->params = array_merge($this->params, $res['params']);
        } elseif (DEBUG) {
            trigger_error('sqlParser has occurred an error!', E_USER_ERROR);
        }

    }

    /**
     * @param mixed $condition
     * @return $this
     */
    public function whereOr($condition = []): ModelObjFinder
    {
        if ($condition) {
            $this->parseCondition($condition, true);
        }

        return $this;
    }

    /**
     * @param int $page
     * @param int $page_size
     * @return $this
     */
    public function page(int $page, int $page_size): ModelObjFinder
    {
        $this->limit = [$page, $page_size];

        return $this;
    }

    /**
     * @param $order_by
     * @return $this
     */
    public function orderBy($order_by): ModelObjFinder
    {
        $this->orderBy = array_merge(is_array($order_by) ? $order_by : [$order_by], $this->orderBy);

        return $this;
    }

    /**
     * @param $group_by
     * @return $this
     */
    public function groupBy($group_by): ModelObjFinder
    {
        $this->groupBy = array_merge(is_array($group_by) ? $group_by : [$group_by], $this->groupBy);

        return $this;
    }

    /**
     * @param mixed $field
     * @return int
     */
    public function count($field = '*'): int
    {
        $res = $this->get("COUNT($field)");

        return intval($res);
    }

    /**
     * @param mixed $field
     * @return numeric
     */
    public function sum($field)
    {
        return $this->get("SUM($field)") ?? 0;
    }

    /**
     * @param $m
     * @return mixed
     */
    public function get($m)
    {
        if ($m) {
            $res = We7::pdo_fetch($this->makeSQL($m), $this->params);
            if ($res) {
                return count($res) > 1 ? array_values($res) : current($res);
            }
        }

        return null;
    }

    public function getAll($m)
    {
        if ($m) {
            $res = We7::pdo_fetchAll($this->makeSQL($m), $this->params);
            if ($res) {
                return $res;
            }
        }

        return null;
    }

    public function delete($condition = []): bool
    {
        $this->where($condition);

        return We7::pdo_query($this->makeSQL('', true), $this->params);
    }

    /**
     * @param $fields
     * @param bool $delete
     * @return string
     */
    private function makeSQL($fields, bool $delete = false): string
    {
        /** @var ModelObj $objClassname */
        $objClassname = $this->factory->objClassname();

        if ($delete) {
            $sql = 'DELETE FROM '.We7::tb($objClassname::getTableName(ModelObj::OP_WRITE));
        } else {
            $select = parent::parseSelect($fields);
            $count_select = We7::str_exists($select, "COUNT(");
            if ($count_select && $this->limit) {
                $sql = "$select FROM (SELECT * FROM ".We7::tb($objClassname::getTableName(ModelObj::OP_READ));
            } else {
                $sql = "$select FROM ".We7::tb($objClassname::getTableName(ModelObj::OP_READ));
            }
        }

        if ($this->condition || $this->conditionOr) {
            if ($this->condition) {
                $sql .= ' WHERE '.implode(' AND ', $this->condition);
            }

            if ($this->conditionOr) {
                if ($this->condition) {
                    $sql .= ' AND ('.implode(' OR ', $this->conditionOr).')';
                } else {
                    $sql .= ' WHERE '.implode(' OR ', $this->conditionOr);
                }
            }
        } else {
            $sql .= ' WHERE 1';
        }

        if ($this->groupBy) {
            $sql .= parent::parseGroupby($this->groupBy);
        }

        if ($this->orderBy) {
            $sql .= parent::parseOrderby($this->orderBy);
        }

        if ($this->limit) {
            $sql .= parent::parseLimit($this->limit);
            if ($count_select ?? false) {
                $sql .= ') AS a';
            }
        }

        //echo $sql;
        return $sql;
    }

    public function exists($condition = []): bool
    {
        if ($condition) {
            $this->where($condition);
        }

        return !empty($this->get('id'));
    }

    /**
     * @param mixed $condition
     * @param bool $lazy
     * @return mixed
     */
    public function findOne($condition = [], bool $lazy = false)
    {
        $res = $this->limit(1)->findAll($condition, $lazy);
        if ($res) {
            return $res->current();
        }

        return null;
    }

    /**
     * @param mixed $condition
     * @param bool $lazy
     * @return ModelObjIterator|ModelObjIteratorLazy
     */
    public function findAll($condition = [], bool $lazy = false)
    {
        $this->where($condition);

        if ($lazy) {
            $res = We7::pdo_fetchAll($this->makeSQL('id'), $this->params);

            return new ModelObjIteratorLazy($this->factory, $res);
        } else {
            $res = We7::pdo_fetchAll($this->makeSQL('*'), $this->params);

            return new ModelObjIterator($this->factory, $res);
        }
    }

    /**
     * @param $limit
     * @return $this
     */
    public function limit($limit): ModelObjFinder
    {
        $this->limit = $limit;

        return $this;
    }

    /**
     * @param string $select
     * @return string
     */
    public function getSQL(string $select = ''): string
    {
        return $this->makeSQL($select);
    }
}
