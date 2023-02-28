<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$promotion = request::array('promotion');

$enabled = $promotion['enabled'] == 'true';

Config::fueling('vip.recharge.promotion', [
    'enabled' => $enabled,
    'list' => $enabled ? (array)($promotion['list']) : [],
], true);

JSON::success('Ok');