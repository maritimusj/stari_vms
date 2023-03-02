<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$id = Request::int('id');
if ($id) {
    
}
$name = Request::trim('name');
$memo = Request::trim('memo');
$image = Request::trim('image');

