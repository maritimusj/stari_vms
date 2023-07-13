<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

defined('IN_IA') or exit('Access Denied');

//清除所有设备的错误代码
Device::cleanAllErrorCode();

Response::itoast('清除成功！', $this->createWebUrl('device'), 'success');