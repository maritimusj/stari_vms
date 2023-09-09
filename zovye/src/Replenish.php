<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class Replenish extends Base
{
    public static function model(): base\modelFactory
    {
        return m('replenish');
    }
}