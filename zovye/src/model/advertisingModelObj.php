<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\domain\Advertising;
use zovye\domain\Agent;
use zovye\traits\ExtraDataGettersAndSetters;
use zovye\We7;
use function zovye\getArray;
use function zovye\setArray;
use function zovye\tb;

/**
 * Class advertisingModelObj
 * @method getState()
 * @method setState(int $state)
 * @method getAgentId()
 * @method getType()
 * @method setType($type)
 * @method getTitle()
 * @method setTitle(string $title)
 * @method setUpdatetime($time)
 * @method getCreatetime()
 * @method getUpdatetime()
 */
class advertisingModelObj extends ModelObj
{
    /** @var int */
    protected $id;

    protected $uniacid;

    /** @var int */
    protected $state;

    /** @var int */
    protected $agent_id;

    /** @var int */
    protected $type;

    /** @var string */
    protected $title;

    protected $extra;

    /** @var int */
    protected $createtime;

    /** @var int */
    protected $updatetime;

    use ExtraDataGettersAndSetters;

    public static function getTableName($read_or_write): string
    {
        return tb('advertising');
    }


    public function destroy(): bool
    {
        $this->remove('reviewData');
        $this->remove('assigned');

        $this->setState(Advertising::DELETED);

        return $this->save();
    }

    /**
     * @return bool
     */
    public function save(): bool
    {
        if (!We7::is_serialized($this->extra)) {
            $this->setExtra(serialize($this->extra));
        }

        return parent::save();
    }

    /**
     * 获取广告的所有者，代理商
     * @return agentModelObj|null
     */
    public function getOwner(): ?agentModelObj
    {
        if ($this->agent_id) {
            return Agent::get($this->agent_id);
        }

        return null;
    }

    /**
     * 审核是否已通过
     * @return bool
     */
    public function isReviewPassed(): bool
    {
        if (empty($this->agent_id)) {
            return true;
        }

        return $this->getReviewResult() === Advertising::REVIEW_PASSED;
    }

    /**
     * 获取广告的审核结果
     * @return int
     */
    public function getReviewResult(): int
    {
        if ($this->agent_id > 0) {
            $current = $this->settings('reviewData.current');
            if ($current) {
                return $this->settings("reviewData.$current.result");
            }
        }

        return Advertising::REVIEW_WAIT;
    }

    public function getExtra()
    {
        $this->deserializeExtra();

        return $this->extra;
    }

    public function deserializeExtra()
    {
        if (We7::is_serialized($this->extra)) {
            $res = unserialize($this->extra);
            $this->extra = $res === false ? [] : $res;
        }
    }

    /**
     * 获取广告的扩展设置数据
     * @param string $key
     * @param null|mixed $default
     * @return mixed|null
     */
    public function getExtraData(string $key, $default = null)
    {
        $this->deserializeExtra();

        return getArray($this->extra, $key, $default);
    }

    /**
     * 设置广告的扩展设置数据
     * @param string $key
     * @param mixed $val
     */
    public function setExtraData(string $key, $val)
    {
        $this->deserializeExtra();

        setArray($this->extra, $key, $val);
    }

    /**
     * 更新广告版本
     * @return bool
     */
    public function update(): bool
    {
        $this->setUpdatetime(time());

        return $this->save();
    }

    public function getAgent(): ?agentModelObj
    {
        return Agent::get($this->agent_id);
    }

}
