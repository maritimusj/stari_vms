<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

Config::app('wxapp.advs.reward.bonus', [
    'level0' => [
        'max' => max(0, Request::int('numLevel0')),
        'v' => max(0, Request::int('bonusLevel0')),
    ],
    'level1' => [
        'max' => max(0, Request::int('numLevel1')),
        'v' => max(0, Request::int('bonusLevel1')),
    ],
    'level2' => [
        'max' => max(0, Request::int('numLevel2')),
        'v' => max(0, Request::int('bonusLevel2')),
    ],
], true);

Config::app('wxapp.advs.reward.w', Request::str('way'), true);
Config::app('wxapp.advs.reward.limit', max(0, Request::str('limit')), true);
Config::app('wxapp.advs.reward.max', max(0, Request::str('max')), true);
Config::app('wxapp.advs.reward.allowFree', Request::bool('allowFree') ? 1 : 0, true);
Config::app('wxapp.advs.reward.freeLimit', max(0, Request::int('freeLimit')), true);
Config::app('wxapp.advs.reward.freeCommission', max(0, intval(round(Request::float('freeCommission', 0, 2) * 100))), true);
