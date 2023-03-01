<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

if (Migrate::detect()) {
    JSON::success(['redirect' => Util::url('migrate')]);
}