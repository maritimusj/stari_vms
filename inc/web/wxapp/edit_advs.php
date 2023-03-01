<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [];

$content = app()->fetchTemplate('web/wxapp/advs', $tpl_data);

JSON::success([
    'title' => '广告位',
    'content' => $content,
]);