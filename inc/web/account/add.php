<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$type = Request::int('type', Account::NORMAL);

Response::showTemplate('web/account/edit_'.$type, [
    'clr' => Util::randColor(),
    'op' => 'add',
    'type' => $type,
    'media_type' => 'video',
    'from' => 'base',
]);