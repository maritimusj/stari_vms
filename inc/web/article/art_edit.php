<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Article;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [
    'art_types' => [
        'article' => [],
        'faq' => [],
    ],
    'archive_types' => settings('doc.types'),
];

$id = Request::int('id');
if ($id > 0) {
    $art = Article::findOne(['id' => $id, 'type' => 'article']);
    if (empty($art)) {
        Response::toast('找不到这篇文章！', Util::url('article'), 'error');
    }
    $tpl_data['id'] = $id;
    $tpl_data['art'] = $art;
}

Response::showTemplate('web/doc/article_edit', $tpl_data);