<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\articleModelObj;

$id = Request::int('id');

/** @var articleModelObj $archive */
$archive = m('files')->findOne(We7::uniacid(['id' => $id]));
if (empty($archive)) {
    exit('找不到这个文件，请联系管理员！');
}

$archive->setTotal($archive->getTotal() + 1);
$archive->save();

Response::redirect($archive->getUrl());
