<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

$type = request::int('type', Account::NORMAL);

app()->showTemplate('web/account/edit_'.$type, [
    'clr' => Util::randColor(),
    'op' => 'add',
    'type' => $type,
    'media_type' => 'video',
    'from' => 'base',
]);