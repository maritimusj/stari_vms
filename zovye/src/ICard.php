<?php

namespace zovye;

use zovye\model\userModelObj;

interface ICard
{
    function getOwner(): userModelObj;
    function getUID(): string;
    function getTypename(): string;
    function total(): int;
}