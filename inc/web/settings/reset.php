<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

Migrate::reset();
if (Migrate::detect()) {
    JSON::success(['redirect' => Util::url('migrate')]);
}

JSON::success('已重置！');