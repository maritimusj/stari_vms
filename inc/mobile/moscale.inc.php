<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye;

defined('IN_IA') or exit('Access Denied');

$data = request::raw();
Util::logToFile('moscale', $data);

MoscaleAccount::cb(json_decode($data, true));


