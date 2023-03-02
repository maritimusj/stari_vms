<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = request::int('id');

app()->showTemplate(
    'web/account/gift_edit',
    [
        'id' => $id,
    ]
);