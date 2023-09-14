<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\model;

use zovye\base\ModelObj;
use zovye\domain\Account;
use zovye\domain\User;
use zovye\traits\ExtraDataGettersAndSetters;
use function zovye\tb;

/**
 * @method getAccountId()
 * @method getUserId()
 * @method getS1()
 * @method getState()
 * @method setS1(int $INIT)
 */
class task_vwModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('task_vw');
    }

    /** @var int */
    protected $id;

    /** @var int */
    protected $user_id;

    /** @var int */
    protected $account_id;

    /** @var int */
    protected $s1;

    /** @var string */
    protected $s2;

    /** @var int */
    protected $state;

    protected $extra;

    /** @var int */
    protected $createtime;

    private $account_obj = null;

    use ExtraDataGettersAndSetters;

    public function getAccount(): ?accountModelObj
    {
        if (!isset($this->account_obj)) {
            $this->account_obj = Account::get($this->account_id);
        }

        return $this->account_obj;
    }

    public function getUser(): ?userModelObj
    {
        return User::get($this->user_id);
    }

    public function getUid(): string
    {
        return $this->s2;
    }

    public function format(): array
    {
        $acc = $this->getAccount();
        $result = $acc ? $acc->format() : [];
        if ($result) {
            $result['status'] = $this->s1;
            $result['createtime'] = $this->createtime;
        }

        return $result;
    }
}