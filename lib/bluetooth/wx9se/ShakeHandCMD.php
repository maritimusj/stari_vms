<?php
/**
 * @author jjs@zovye.com
 * @url www.zovye.com
 */

namespace bluetooth\wx9se;

class ShakeHandCMD extends cmd
{
    public function __construct($device_id, $data)
    {
        $data[] = 0;
        $data[] = 0;
        parent::__construct($device_id, protocol::CMD_SHAKE_HAND, protocol::KEY_SHAKE, $data);
    }
}