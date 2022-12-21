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

    public function getUID(): string
    {
        return $this->user->getPhysicalCardNO();
    }

    public function total(): int
    {
        $total = $this->user->getCommissionBalance()->total();
        if ($total > 0) {
            return $total + $this->user->getCredit();
        }
        return $total;
    }

    public function getOwner(): ?userModelObj
    {
        return $this->user;
    }

    public static function getTypename(): string
    {
        return 'commission_balance';
    }

    public function isUsable(): bool
    {
        $owner = $this->getOwner();
        if (empty($owner) || $owner->isBanned()) {
            return false;
        }

        if (App::isChargingDeviceEnabled()) {
            $user_charging_data = $owner->chargingNOWData();
            if ($user_charging_data) {
                return false;
            }
        }

        if (App::isFuelingDeviceEnabled()) {
            $user_fueling_data = $owner->fuelingNOWData();
            if ($user_fueling_data) {
                return false;
            }
        }
        return true;
    }
}