<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

class AdStats extends Base
{
    public static function model(): base\modelFactory
    {
        return m('advs_stats');
    }
}