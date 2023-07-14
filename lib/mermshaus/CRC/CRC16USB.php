<?php

namespace mermshaus\CRC;

class CRC16USB extends CRC16
{
    protected $initChecksum = 0xffff;
    protected $xorMask = 0xffff;
}
