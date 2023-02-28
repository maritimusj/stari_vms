<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$content = app()->fetchTemplate('web/user/export');

JSON::success(['title' => '用户导出', 'content' => $content]);