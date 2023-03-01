<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

app()->showTemplate('web/device/new_group_edit', [
    'cr' => Util::randColor(),
]);