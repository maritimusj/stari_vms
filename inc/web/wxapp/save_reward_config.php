<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

Config::app('wxapp.advs.reward.bonus', [
    'level0' => [
        'max' => max(0, request::int('numLevel0')),
        'v' => max(0, request::int('bonusLevel0')),
    ],
    'level1' => [
        'max' => max(0, request::int('numLevel1')),
        'v' => max(0, request::int('bonusLevel1')),
    ],
    'level2' => [
        'max' => max(0, request::int('numLevel2')),
        'v' => max(0, request::int('bonusLevel2')),
    ],
], true);

Config::app('wxapp.advs.reward.w', request::str('way'), true);
Config::app('wxapp.advs.reward.limit', max(0, request::str('limit')), true);
Config::app('wxapp.advs.reward.max', max(0, request::str('max')), true);
Config::app('wxapp.advs.reward.allowFree', request::bool('allowFree') ? 1 : 0, true);
Config::app('wxapp.advs.reward.freeLimit', max(0, request::int('freeLimit')), true);
Config::app('wxapp.advs.reward.freeCommission', max(0, intval(round(request::float('freeCommission', 0, 2) * 100))), true);
