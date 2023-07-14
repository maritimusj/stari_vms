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
    $tpl_data['id'] = $id;
    $tpl_data['archive'] = m('files')->findOne(We7::uniacid(['id' => $id]));
}

Response::showTemplate('web/doc/files_edit', $tpl_data);