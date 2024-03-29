<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\api\wx;

use zovye\api\common;
use zovye\model\agentModelObj;
use zovye\model\articleModelObj;
use zovye\model\filesModelObj;
use zovye\Request;
use zovye\We7;
use function zovye\err;
use function zovye\m;
use function zovye\settings;

class article
{
    /**
     * 读取文件详情
     */
    public static function detail(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_wd');

        $id = Request::int('id');
        /** @var articleModelObj $article */
        $article = \zovye\domain\Article::get($id);
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

        return err('找不到这篇文章！');
    }

    /**
     * 文件列表
     */
    public static function list(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_wd');

        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        $query = \zovye\domain\Article::query(['type' => 'article']);
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
            $query->orderBy('id DESC');
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
     * 获取附件列表
     */
    public static function archive(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_wd');

        $archive_types = settings('doc.types');
        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

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
            $query->orderBy('id DESC');

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
     * 常见问题
     */
    public static function faq(agentModelObj $agent): array
    {
        common::checkPrivileges($agent, 'F_wt');

        $page = max(1, Request::int('page'));
        $page_size = max(1, Request::int('pagesize', DEFAULT_PAGE_SIZE));

        $query = \zovye\domain\Article::query(['type' => 'faq']);
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
            $query->orderBy('id DESC');

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