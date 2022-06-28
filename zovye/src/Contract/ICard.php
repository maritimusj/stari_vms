<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\Contract;

use zovye\model\userModelObj;

interface ICard
{
    function getOwner(): ?userModelObj;

    function getUID(): string;

    function getTypename(): string;

    function total(): int;
}