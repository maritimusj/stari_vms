<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$data = Config::fueling('vip.recharge', []);
JSON::success($data);