<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\Agent;

use function zovye\tb;
use zovye\base\modelObj;

/**
 * Class referralModelObj
 * @package zovye
 * @method getAgent_id()
 * @method getCode()
 * @method getCreatetime()
 */
class referralModelObj extends modelObj
{
    /** @var int */
    protected $id;

    protected $uniacid;

    /** @var int */
    protected $agent_id;

    /** @var string */
    protected $code;

    /** @var int */
    protected $createtime;

    public static function getTableName($readOrWrite): string
    {
        return tb('referral');
    }

    public function getAgent(): ?agentModelObj
    {
        return Agent::get($this->agent_id);
    }
}