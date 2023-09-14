<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\contract;

use zovye\model\userModelObj;

interface ICard
{
    function getOwner(): ?userModelObj;

    function getUID(): string;

    static function getTypename(): string;

    function total(): int;

    function isUsable(): bool;
}