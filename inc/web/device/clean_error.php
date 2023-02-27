<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
 
namespace zovye;

//清除所有设备的错误代码
Device::cleanAllErrorCode();

Util::itoast('清除成功！', $this->createWebUrl('device'), 'success');