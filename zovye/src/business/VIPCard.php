<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\business;

class VIPCard extends UserCommissionBalanceCard
{
    public static function getTypename(): string
    {
        return 'vip';
    }

    public function total(): int
    {
        return 1000000;
    }
}