<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

app()->showTemplate('web/device/new_group_edit', [
    'cr' => Util::randColor(),
]);