<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\ModelObj;
use zovye\domain\Device;
use function zovye\tb;

class cv_upload_deviceModelObj extends ModelObj
{
    public static function getTableName($read_or_write): string
    {
        return tb('cv_upload_device');
    }

	/** @var int */
	protected $id;

	/** @var int */
	protected $uniacid;

	/** @var int */
	protected $device_id;

	/** @var int */
	protected $createtime;


	public function getDevice():? deviceModelObj
	{
		return Device::get($this->device_id);
	}
}