<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\articleModelObj;

$id = Request::int('id');
$type = Request::str('type');
$title = Request::str('title');
$content = Request::str('content');

if (empty($title)) {
    Util::itoast('文章标题不能为空！', We7::referer(), 'error');
}

if ($id) {
    /** @var articleModelObj $article */
    $article = m('article')->findOne(We7::uniacid(['id' => $id, 'type' => $type]));
    if ($article) {
        if ($article->getTitle() != $title) {
            $article->setTitle($title);
        }

        if ($article->getContent() != $content) {
            $article->setContent($content);
        }
    }
} else {
    $article = m('article')->create(
        We7::uniacid(
            [
                'type' => $type,
                'title' => $title,
                'content' => $content,
            ]
        )
    );
}

if ($article && $article->save()) {
    Util::itoast('保存成功！', We7::referer(), 'success');
}

Util::itoast('保存失败！', We7::referer(), 'error');