<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Article;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    $faq = Article::findOne(['id' => $id, 'type' => 'faq']);
    if (empty($faq)) {
        Response::toast('找不到这条FAQ！', Util::url('article', ['op' => 'faq']), 'error');
    }
    if ($faq->destroy()) {
        Response::toast('删除成功！', Util::url('article', ['op' => 'faq']), 'sucess');
    }
}

Response::toast('删除失败！', Util::url('article', ['op' => 'faq']), 'error');