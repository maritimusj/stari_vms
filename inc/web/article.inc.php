<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\model\articleModelObj;
use zovye\model\filesModelObj;

defined('IN_IA') or exit('Access Denied');

$op = request::op('default');

$archive_types = settings('doc.types');

$tpl_data = [
    'op' => $op,
    'art_types' => [
        'article' => [],
        'faq' => [],
    ],
    'archive_types' => $archive_types,
];

if ($op == 'art' || $op == 'default' || $op == 'article') {

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

    $query = m('article')->where(We7::uniacid(['type' => 'article']));

    $total = $query->count();
    $total_page = ceil($total / $page_size);

    if ($page > $total_page) {
        $page = 1;
    }

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);
    $query->page($page, $page_size);
    $query->orderBy('id desc');

    $articles = [];
    /** @var articleModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $articles[] = [
            'id' => $entry->getId(),
            'title' => $entry->getTitle(),
            'total' => intval($entry->getTotal()),
            'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];
    }

    $tpl_data['articles'] = $articles;

} elseif ($op == 'art_add' || $op == 'art_edit') {

    $id = request::int('id');

    $tpl_data['id'] = $id;
    $tpl_data['type'] = 'article';

    if ($id) {
        $art = m('article')->findOne(We7::uniacid(['id' => $id, 'type' => 'article']));
        if (empty($art)) {
            Util::itoast('找不到这篇文章！', $this->createWebUrl('article'), 'error');
        }
        $tpl_data['art'] = $art;
    }

    app()->showTemplate('web/doc/article-op', $tpl_data);

} elseif ($op == 'art_remove') {

    $id = request::int('id');
    if ($id) {
        $article = m('article')->findOne(We7::uniacid(['id' => $id, 'type' => 'article']));
        if (empty($article)) {
            Util::itoast('找不到这篇文章！', $this->createWebUrl('article'), 'error');
        }
        if ($article->destroy()) {
            Util::itoast('删除成功！', $this->createWebUrl('article'), 'sucess');
        }
    }

    Util::itoast('删除失败！', $this->createWebUrl('article'), 'sucess');

} elseif ($op == 'art_save') {

    $id = request::int('id');
    $type = request::str('type');
    $title = request::str('title');
    $content = request::str('content');

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
        Util::itoast('保存成功！', We7::referer(), 'sucess');
    }

    Util::itoast('保存失败！', We7::referer(), 'error');

} elseif ($op == 'files') {

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', DEFAULT_PAGESIZE);

    $query = m('files')->where(We7::uniacid([]));

    $total = $query->count();
    $total_page = ceil($total / $page_size);

    if ($page > $total_page) {
        $page = 1;
    }

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);

    $query->page($page, $page_size);
    $query->orderBy('id desc');

    $archives = [];

    /** @var filesModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $archives[] = [
            'id' => $entry->getId(),
            'title' => $entry->getTitle(),
            'type' => $entry->getType(),
            'url' => $entry->getUrl(),
            'total' => intval($entry->getTotal()),
            'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];
    }

    $tpl_data['archives'] = $archives;

} elseif ($op == 'files_add' || $op == 'files_edit') {

    $id = request::int('id');
    if ($id) {
        $tpl_data['archive'] = m('files')->findOne(We7::uniacid(['id' => $id]));
    }

    app()->showTemplate('web/doc/files-op', $tpl_data);

} elseif ($op == 'files_save') {

    $id = request::int('id');
    if ($id) {
        /** @var filesModelObj $archive */
        $archive = m('files')->findOne(We7::uniacid(['id' => $id]));
        if (empty($archive)) {
            Util::itoast('找不到这个附件！', $this->createWebUrl('article', ['op' => 'files']), 'error');
        }
    }

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

} elseif ($op == 'files_remove') {

    $id = request::int('id');
    if ($id) {
        $archive = m('files')->findOne(We7::uniacid(['id' => $id]));
        if ($archive && $archive->destroy()) {
            Util::itoast('删除成功！', $this->createWebUrl('article', ['op' => 'files']), 'sucess');
        }
    }

    Util::itoast('删除失败！', $this->createWebUrl('article', ['op' => 'files']), 'error');

} elseif ($op == 'faq') {

    $page = max(1, request::int('page'));
    $page_size = request::int('pagesize', 10);

    $query = m('article')->where(We7::uniacid(['type' => 'faq']));

    $total = $query->count();
    $total_page = ceil($total / $page_size);

    if ($page > $total_page) {
        $page = 1;
    }

    $tpl_data['pager'] = We7::pagination($total, $page, $page_size);
    $query->page($page, $page_size);
    $query->orderBy('id desc');

    $faq = [];

    /** @var articleModelObj $entry */
    foreach ($query->findAll() as $entry) {
        $faq[] = [
            'id' => $entry->getId(),
            'title' => $entry->getTitle(),
            'content' => $entry->getContent(),
            'total' => intval($entry->getTotal()),
            'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
        ];
    }

    $tpl_data['faq'] = $faq;

} elseif ($op == 'faq_add' || $op == 'faq_edit') {

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

    app()->showTemplate('web/doc/article-op', $tpl_data);

} elseif ($op == 'faq_remove') {

    $id = request::int('id');
    if ($id) {
        $faq = m('article')->findOne(We7::uniacid(['id' => $id, 'type' => 'faq']));
        if (empty($faq)) {
            Util::itoast('找不到这条FAQ！', $this->createWebUrl('article', ['op' => 'faq']), 'error');
        }
        if ($faq->destroy()) {
            Util::itoast('删除成功！', $this->createWebUrl('article', ['op' => 'faq']), 'sucess');
        }
    }

    Util::itoast('删除失败！', $this->createWebUrl('article', ['op' => 'faq']), 'error');
}

app()->showTemplate('web/doc/document', $tpl_data);
