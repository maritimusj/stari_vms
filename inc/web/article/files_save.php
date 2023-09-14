<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\model\filesModelObj;
use zovye\util\Util;

$id = Request::int('id');
if ($id) {
    /** @var filesModelObj $archive */
    $archive = m('files')->findOne(We7::uniacid(['id' => $id]));
    if (empty($archive)) {
        Response::toast('找不到这个附件！', Util::url('article', ['op' => 'files']), 'error');
    }
}

$archive_types = settings('doc.types');

$type = Request::str('type');
$data = We7::uniacid(
    [
        'title' => Request::str('title'),
        'url' => Request::str('url'),
        'type' => array_key_exists($type, $archive_types) ? $type : 'unknown',
    ]
);

if (empty($data['title']) || empty($data['url'])) {
    Response::toast('请填写标题和网址！', Util::url('article', ['op' => 'files']), 'error');
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
    Response::toast('保存成功！', Util::url('article', ['op' => 'files']), 'sucess');
}

Response::toast('保存失败！', Util::url('article', ['op' => 'files']), 'error');