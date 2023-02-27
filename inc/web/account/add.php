<?php

namespace zovye;

$type = request::int('type', Account::NORMAL);

app()->showTemplate('web/account/edit_'.$type, [
    'clr' => Util::randColor(),
    'op' => $op,
    'type' => $type,
    'media_type' => 'video',
    'from' => 'base',
]);