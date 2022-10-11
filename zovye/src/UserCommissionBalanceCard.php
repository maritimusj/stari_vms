<?php

namespace zovye;

use zovye\Contract\ICard;
use zovye\model\userModelObj;

class UserCommissionBalanceCard implements ICard
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
        $total = $this->user->getCommissionBalance()->total();
        if ($total > 0) {
            return $total + $this->user->getCredit();
        }
        return $total;
    }

    function getOwner(): ?userModelObj
    {
        return $this->user;
    }

    function getTypename(): string
    {
        return 'commission_balance';
    }
}