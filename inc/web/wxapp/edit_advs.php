<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$tpl_data = [];

$content = app()->fetchTemplate('web/wxapp/advs', $tpl_data);

JSON::success([
    'title' => '广告位',
    'content' => $content,
]);