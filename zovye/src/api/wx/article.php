<?php


namespace zovye\api\wx;

use zovye\model\articleModelObj;
use zovye\model\filesModelObj;
use zovye\request;
use zovye\State;
use zovye\We7;
use function zovye\error;
use function zovye\m;
use function zovye\settings;

class article
{
    /**
     * 读取文件详情.
     *
     * @return array
     */
    public static function detail(): array
    {
        common::checkCurrentUserPrivileges('F_wd');

        $id = request::int('id');
        /** @var articleModelObj $article */
        $article = m('article')->findOne(We7::uniacid(['id' => $id]));
        if ($article) {
            $article->setTotal(intval($article->getTotal()) + 1);
            $article->save();

            return [
                'id' => $article->getId(),
                'title' => $article->getTitle(),
                'content' => $article->getContent(),
                'createtime' => date('Y-m-d H:i:s', $article->getCreatetime()),
            ];
        }

        return error(State::ERROR, '找不到这篇文章！');
    }

    /**
     * 文件列表.
     *
     * @return array
     */
    public static function list(): array
    {
        common::checkCurrentUserPrivileges('F_wd');

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGESIZE));

        $query = m('article')->where(We7::uniacid(['type' => 'article']));
        $total = $query->count();

        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
            'totalpage' => ceil($total / $page_size),
            'list' => [],
        ];
        if ($total > 0) {
            $query->page($page, $page_size);
            $query->orderBy('id desc');
            /** @var articleModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $result['list'][] = [
                    'id' => $entry->getId(),
                    'title' => $entry->getTitle(),
                    'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                ];
            }
        }

        return $result;
    }

    /**
     * 获取附件列表.
     *
     * @return array
     */
    public static function archive(): array
    {
        common::checkCurrentUserPrivileges('F_wd');

        $archive_types = settings('doc.types');
        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGESIZE));

        $query = m('files')->where(We7::uniacid([]));
        $total = $query->count();

        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
            'totalpage' => ceil($total / $page_size),
            'list' => [],
        ];

        if ($total > 0) {
            $query->page($page, $page_size);
            $query->orderBy('id desc');

            /** @var filesModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $result['list'][] = [
                    'id' => $entry->getId(),
                    'title' => $entry->getTitle(),
                    'type' => $entry->getType(),
                    'icon' => $archive_types[$entry->getType()]['icon'] ?: $archive_types['unknown']['icon'],
                    'url' => $entry->getUrl(),
                    'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                ];
            }
        }

        return $result;
    }

    /**
     * 常见问题.
     *
     * @return array
     */
    public static function faq(): array
    {
        common::checkCurrentUserPrivileges('F_wt');

        $page = max(1, request::int('page'));
        $page_size = max(1, request::int('pagesize', DEFAULT_PAGESIZE));

        $query = m('article')->where(We7::uniacid(['type' => 'faq']));
        $total = $query->count();

        $result = [
            'page' => $page,
            'pagesize' => $page_size,
            'total' => $total,
            'totalpage' => ceil($total / $page_size),
            'list' => [],
        ];

        if ($total > 0) {
            $query->page($page, $page_size);
            $query->orderBy('id desc');

            /** @var articleModelObj $entry */
            foreach ($query->findAll() as $entry) {
                $result['list'][] = [
                    'id' => $entry->getId(),
                    'title' => $entry->getTitle(),
                    'content' => $entry->getContent(),
                    'createtime' => date('Y-m-d H:i:s', $entry->getCreatetime()),
                ];
            }
        }

        return $result;
    }
}