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
    $tpl_data['id'] = $id;
    $tpl_data['archive'] = m('files')->findOne(We7::uniacid(['id' => $id]));
}

app()->showTemplate('web/doc/files_edit', $tpl_data);