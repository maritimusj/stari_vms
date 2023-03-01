<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$promotion = Request::array('promotion');

$enabled = $promotion['enabled'] == 'true';

Config::fueling('vip.recharge.promotion', [
    'enabled' => $enabled,
    'list' => $enabled ? (array)($promotion['list']) : [],
], true);

JSON::success('Ok');