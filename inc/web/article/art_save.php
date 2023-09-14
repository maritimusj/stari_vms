<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

use zovye\domain\Article;
use zovye\model\articleModelObj;

$id = Request::int('id');
$type = Request::str('type');
$title = Request::str('title');
$content = Request::str('content');

if (empty($title)) {
    Response::toast('文章标题不能为空！', We7::referer(), 'error');
}

if ($id) {
    /** @var articleModelObj $article */
    $article = Article::findOne(['id' => $id, 'type' => $type]);
    if ($article) {
        if ($article->getTitle() != $title) {
            $article->setTitle($title);
        }

        if ($article->getContent() != $content) {
            $article->setContent($content);
        }
    }
} else {
    $article = Article::create([
        'type' => $type,
        'title' => $title,
        'content' => $content,
    ]);
}

if ($article && $article->save()) {
    Response::toast('保存成功！', We7::referer(), 'success');
}

Response::toast('保存失败！', We7::referer(), 'error');