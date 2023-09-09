<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\Agent;
use zovye\base\modelObj;
use zovye\User;
use function zovye\tb;

/**
 * Class referralModelObj
 * @package zovye
 * @method getUserId()
 * @method getCode()
 * @method getCreatetime()
 */
class referralModelObj extends modelObj
{
    /** @var int */
    protected $id;

    /** @var int */
    protected $user_id;

    /** @var string */
    protected $code;

    /** @var int */
    protected $createtime;

    public static function getTableName($read_or_write): string
    {
        return tb('referral');
    }

    public function getAgent(): ?agentModelObj
    {
        return Agent::get($this->user_id);
    }

    public function getUser(): ?userModelObj
    {
        return User::get($this->user_id);
    }
    
}