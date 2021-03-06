<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace bluetooth\wx9se;

class OpenDeviceCMD extends cmd
{

    public function __construct($lane = 0)
    {
        $data = [$lane, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00, 0x00];
        parent::__construct(0, protocol::CMD_CONFIG, protocol::KEY_LOCKER, $data);
    }
}