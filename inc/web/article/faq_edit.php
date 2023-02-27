<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

$tpl_data = [
    'art_types' => [
        'article' => [],
        'faq' => [],
    ],
    'archive_types' => settings('doc.types'),
];

$id = request::int('id');
if ($id) {
    $art = m('article')->findOne(We7::uniacid(['id' => $id, 'type' => 'faq']));
    if (empty($art)) {
        Util::itoast('找不到这条FAQ！', $this->createWebUrl('article', ['op' => 'faq']), 'error');
    }

    $tpl_data['art'] = $art;
}

$tpl_data['id'] = $id;
$tpl_data['type'] = 'faq';

app()->showTemplate('web/doc/article_op', $tpl_data);