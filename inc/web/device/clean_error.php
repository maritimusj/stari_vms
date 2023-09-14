<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

use zovye\domain\Device;
use zovye\util\Util;

defined('IN_IA') or exit('Access Denied');

//清除所有设备的错误代码
Device::cleanAllErrorCode();

Response::toast('清除成功！',Util::url('device'), 'success');