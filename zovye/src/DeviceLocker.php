<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace zovye;

use zovye\model\deviceModelObj;

class DeviceLocker
{
    private $locked = false;

    public function __construct(deviceModelObj $device)
    {
        $guid = $device->lock();
        if ($guid) {
            $this->locked = true;
            register_shutdown_function(
                function () use ($device, $guid) {
                    $device->unlock($guid);
                }
            );
        }
    }

    /**
     * @return bool
     */
    public function isLocked(): bool
    {
        return $this->locked;
    }
}
