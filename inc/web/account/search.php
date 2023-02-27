<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

use zovye\model\accountModelObj;

$result = [];

$query = Account::query();

if (request::isset('type')) {
    $query->where(['type' => request::int('type')]);
}

$keyword = request::trim('keyword', '', true);
if ($keyword) {
    $query->whereOr([
        'name LIKE' => "%$keyword%",
        'title LIKE' => "%$keyword%",
    ]);
}

$query->limit(request::int('limit', 100));

/** @var accountModelObj $entry */
foreach ($query->findAll() as $entry) {
    $result[] = $entry->profile();
}

JSON::success($result);