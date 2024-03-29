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
    $faq = Article::findOne(['id' => $id, 'type' => 'faq']);
    if (empty($faq)) {
        Response::toast('找不到这条FAQ！', Util::url('article', ['op' => 'faq']), 'error');
    }

    $tpl_data['id'] = $id;
    $tpl_data['faq'] = $faq;
}

Response::showTemplate('web/doc/faq_edit', $tpl_data);