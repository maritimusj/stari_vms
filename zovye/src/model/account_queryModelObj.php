<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\User;
use zovye\Device;
use zovye\Account;
use function zovye\tb;
use zovye\base\modelObj;
use zovye\traits\ExtraDataGettersAndSetters;

/**
 * @method getRequestId()
 * @method getRequest()
 * @method getResult()
 */
class account_queryModelObj extends modelObj
{
    public static function getTableName($readOrWrite): string
    {
        return tb('account_query');
    }

    /** @var int */
    protected $id;

    /** @var string */
    protected $request_id;

    /** @var int */
    protected $account_id;

    /** @var int */
    protected $device_id;

    /** @var int */
    protected $user_id;

    /** @var json */
    protected $request;

    /** @var json */
    protected $result;

    protected $extra;

    /** @var int */
    protected $createtime;

    use ExtraDataGettersAndSetters;

    public function getAccount(): ?accountModelObj
    {
        return Account::get($this->account_id);
    }

    public function getUser(): ?userModelObj
    {
        return User::get($this->user_id);
    }

    public function getDevice(): ?deviceModelObj
    {
        return Device::get($this->device_id);
    }
}
