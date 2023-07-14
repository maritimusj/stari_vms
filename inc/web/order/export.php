<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

use DateTime;

$headers = Order::getExportHeaders();
unset($headers['ID']);

$tpl_data['headers'] = $headers;
$tpl_data['s_date'] = (new DateTime('first day of this month'))->format('Y-m-d');
$tpl_data['e_date'] = (new DateTime())->format('Y-m-d');

Response::showTemplate('web/order/export', $tpl_data);