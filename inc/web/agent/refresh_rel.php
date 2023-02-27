<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\model\agentModelObj;

if (!YZShop::isInstalled()) {
    JSON::fail('刷新失败！');
}

$page = max(1, request::int('page'));

$query = Agent::query();

$count = $query->count();

$query->page($page, DEFAULT_PAGE_SIZE);

/** @var agentModelObj $entry */
foreach ($query->findAll() as $entry) {
    $superior = YZShop::getSuperior($entry);
    if ($superior) {
        if ($entry->getSuperiorId() != $superior->getId()) {
            $entry->setSuperiorId($superior->getId());
            $entry->save();
        }
    } else {
        if ($entry->getSuperiorId()) {
            $entry->setSuperiorId(0);
            $entry->save();
        }
    }
}

JSON::success(['more' => $page * DEFAULT_PAGE_SIZE < $count ? 'y' : 'n']);