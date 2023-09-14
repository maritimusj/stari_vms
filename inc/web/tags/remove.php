<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Tags;
use zovye\model\tagsModelObj;

$id = Request::int('id');
if ($id) {
    /** @var tagsModelObj $tag */
    $tag = Tags::findOne(['id' => Request::int('id')]);
    if (empty($tag)) {
        JSON::fail('找不到这个标签！');
    }
    if ($tag->getCount() > 0) {
        JSON::fail('不能删除这个标签！');
    }

    if ($tag->destroy()) {
        JSON::success('删除成功！');
    }
}

JSON::fail('删除失败！');