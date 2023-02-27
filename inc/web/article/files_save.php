<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\filesModelObj;

$id = request::int('id');
if ($id) {
    /** @var filesModelObj $archive */
    $archive = m('files')->findOne(We7::uniacid(['id' => $id]));
    if (empty($archive)) {
        Util::itoast('找不到这个附件！', $this->createWebUrl('article', ['op' => 'files']), 'error');
    }
}

$archive_types = settings('doc.types');

$type = request::str('type');
$data = We7::uniacid(
    [
        'title' => request::str('title'),
        'url' => request::str('url'),
        'type' => array_key_exists($type, $archive_types) ? $type : 'unknown',
    ]
);

if (empty($data['title']) || empty($data['url'])) {
    Util::itoast('请填写标题和网址！', $this->createWebUrl('article', ['op' => 'files']), 'error');
}

if (isset($archive)) {
    if ($data['title'] != $archive->getTitle()) {
        $archive->setTitle($data['title']);
    }

    if ($data['url'] != $archive->getUrl()) {
        $archive->setUrl($data['url']);
    }

    if ($data['type'] != $archive->getType()) {
        $archive->setType($data['type']);
    }
} else {
    $archive = m('files')->create($data);
}

if ($archive && $archive->save()) {
    Util::itoast('保存成功！', $this->createWebUrl('article', ['op' => 'files']), 'sucess');
}

Util::itoast('保存失败！', $this->createWebUrl('article', ['op' => 'files']), 'error');