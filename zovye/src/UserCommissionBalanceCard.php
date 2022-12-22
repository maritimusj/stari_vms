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

        $isOrderFinished = function ($order_no) {
            $order = Order::get($order_no, true);
            if ($order) {
                if ($order->isChargingOrder()) {
                    return $order->isChargingFinished();
                }
                if ($order->isFuelingOrder()) {
                    return $order->isFuelingFinished();
                }
            }
            return true;
        };

        if (App::isChargingDeviceEnabled()) {
            $data = $owner->chargingNOWData();
            if ($data && !$isOrderFinished($data['serial'])) {
                return false;
            }
        }

        if (App::isFuelingDeviceEnabled()) {
            $data = $owner->fuelingNOWData();
            if ($data && !$isOrderFinished($data['serial'])) {
                return false;
            }
        }

        return true;
    }
}