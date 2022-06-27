<?php

namespace zovye;

use zovye\model\userModelObj;

class UserCommissionBalanceICard implements ICard
{
    /** @var userModelObj */
    private $user;

    /**
     * @param $user
     */
    public function __construct($user)
    {
        $this->user = $user;
    }

    function getUID(): string
    {
        return $this->user->getPhysicalCardNO();
    }

    function total(): int
    {
        return $this->user->getCommissionBalance()->total();
    }

    function getOwner(): userModelObj
    {
        return $this->user;
    }

    function getTypename(): string
    {
        return 'commission_balance';
    }
}