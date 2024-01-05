<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\domain;

use zovye\App;
use zovye\model\keeper_devicesModelObj;

class CommissionValue
{
    private $way;
    private $is_fixed;
    private $fixed;
    private $percent;
    private $free_fixed;
    private $free_percent;

    public static function from(keeper_devicesModelObj $entry): CommissionValue
    {
        $v = new self();

        $v->way = $entry->getWay();

        if ($entry->isFixedValue()) {
            $v->is_fixed = true;
            $v->fixed = $entry->getCommissionFixed();
            if (App::isKeeperCommissionOrderDistinguishEnabled() && $v->way == Keeper::COMMISSION_ORDER) {
                $v->free_fixed = $entry->getCommissionFreeFixed();
            } else {
                $v->free_fixed = 0;
            }
        } else {
            $v->is_fixed = false;
            $v->percent = $entry->getCommissionPercent();
            if (App::isKeeperCommissionOrderDistinguishEnabled() && $v->way == Keeper::COMMISSION_ORDER) {
                $v->free_percent = $entry->getCommissionFreePercent();
            } else {
                $v->free_percent = 0;
            }
        }

        return $v;
    }


    public function getWay()
    {
        return $this->way;
    }

    public function isFixed(): bool
    {
        return $this->is_fixed;
    }

    public function getValue()
    {
        return $this->isFixed() ? $this->fixed : $this->percent;
    }

    public function getPayValue()
    {
        return $this->isFixed() ? $this->fixed : $this->percent;
    }

    public function getFreeValue()
    {
        if (App::isKeeperCommissionOrderDistinguishEnabled()) {
            return $this->isFixed() ? $this->free_fixed : $this->free_percent;
        }

        return $this->getPayValue();
    }

    public function format(): array
    {
        if (App::isKeeperCommissionOrderDistinguishEnabled() && $this->way == Keeper::COMMISSION_ORDER) {
            return [
                'way' => $this->way,
                'pay_val' => $this->getPayValue() / 100.00,
                'free_val' => $this->getFreeValue() / 100.00,
                'type' => $this->isFixed() ? 'fixed' : 'percent',
            ];
        } else {
            return [
                'way' => $this->way,
                'val' => $this->getPayValue() / 100.00,
                'type' => $this->isFixed() ? 'fixed' : 'percent',
            ];
        }
    }
}