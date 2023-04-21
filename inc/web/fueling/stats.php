<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye;

defined('IN_IA') or exit('Access Denied');

$tpl_data = [];

app()->showTemplate('web/fueling/stats', $tpl_data);