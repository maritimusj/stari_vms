<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\agentModelObj;
use zovye\model\device_groupsModelObj;
use zovye\model\deviceModelObj;
use zovye\model\goodsModelObj;
use zovye\model\keeperModelObj;
use zovye\model\orderModelObj;
use zovye\model\userModelObj;

/**
 * @method limit(int $int)
 * @method whereOr(string[] $array)
 * @method count()
 * @method page(int|mixed $page, int $page_size)
 * @method orderBy(string $string)
 * @method groupBy($group_by)
 * @method findAll($cond = [], $lazy = false)
 * @method findOne($lazy = false)
 * @method exists($condition = [])
 * @method makeSQL($fields, $delete = false)
 * @method delete($condition = [])
 * @method get($m)
 * @method resetAll()
 * @method getAll(string[] $array)
 */
class ModelObjFinderProxy
{
    private $finder;

    /**
     * modelObjFinder constructor.
     * @param base\modelObjFinder $finder
     */
    public function __construct(base\modelObjFinder $finder)
    {
        $this->finder = $finder;
    }

    public function __call($name, $arguments)
    {
        return $this->finder->$name(...$arguments);
    }

    public function where($condition = []): ModelObjFinderProxy
    {
        if (is_array($condition)) {

            $this->finder->where($condition);

        } elseif ($condition instanceof agentModelObj) {

            if ($this->finder->isPropertyExists('agent_id')) {
                $this->finder->where(['agent_id' => $condition->getId()]);
            }

        } elseif ($condition instanceof keeperModelObj) {

            if ($this->finder->isPropertyExists('keeper_id')) {
                $this->finder->where(['keeper_id' => $condition->getId()]);
            }

        } elseif ($condition instanceof userModelObj) {

            if ($this->finder->isPropertyExists('user_id')) {
                $this->finder->where(['user_id' => $condition->getId()]);
            } else if ($this->finder->isPropertyExists('openid')) {
                $this->finder->where(['openid' => $condition->getOpenid()]);
            } else {
                trigger_error('property not exists', E_USER_ERROR);
            }            

        } elseif ($condition instanceof deviceModelObj) {

            if ($this->finder->isPropertyExists('device_id')) {
                $this->finder->where(['device_id' => $condition->getId()]);
            } elseif ($this->finder->isPropertyExists('device_uid')) {
                $this->finder->where(['device_uid' => $condition->getUid()]);
            } else {
                trigger_error('property not exists', E_USER_ERROR);
            }            

        } elseif ($condition instanceof orderModelObj) {

            if ($this->finder->isPropertyExists('order_id')) {
                $this->finder->where(['order_id' => $condition->getId()]);
            } else {
                trigger_error('property not exists', E_USER_ERROR);
            }            

        } elseif ($condition instanceof device_groupsModelObj) {

            if ($this->finder->isPropertyExists('group_id')) {
                $this->finder->where(['group_id' => $condition->getId()]);
            } else {
                trigger_error('property not exists', E_USER_ERROR);
            }            

        } elseif ($condition instanceof goodsModelObj) {

            if ($this->finder->isPropertyExists('goods_id')) {
                $this->finder->where(['goods_id' => $condition->getId()]);
            } else {
                trigger_error('property not exists', E_USER_ERROR);
            }            

        } else {
            $this->finder->where($condition);
        }

        return $this;
    }

}