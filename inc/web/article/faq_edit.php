<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [
    'art_types' => [
        'article' => [],
        'faq' => [],
    ],
    'archive_types' => settings('doc.types'),
];

$id = Request::int('id');
if ($id) {
    $faq = m('article')->findOne(We7::uniacid(['id' => $id, 'type' => 'faq']));
    if (empty($faq)) {
        Response::itoast('找不到这条FAQ！', $this->createWebUrl('article', ['op' => 'faq']), 'error');
    }

    $tpl_data['id'] = $id;
    $tpl_data['faq'] = $faq;
}

app()->showTemplate('web/doc/faq_edit', $tpl_data);