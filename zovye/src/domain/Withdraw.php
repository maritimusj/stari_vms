<?php
namespace zovye\domain;

use zovye\base\ModelObjFinder;
use function zovye\m;


class Withdraw {
    public static function query($condition = []): ModelObjFinder
    {
        return m('withdraw_vw')->query($condition);
    }
}