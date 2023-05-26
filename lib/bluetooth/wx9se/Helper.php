<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace bluetooth\wx9se;

use mermshaus\CRC\CRC16XModem;

class Helper
{
    const LOW = 0;
    const HIGH = 1;

    public static function getCrc16Data($mac, array $code, $lowOrHigh): array
    {
        $mac = hex2bin(implode('', array_reverse(explode(':', $mac))));

        $crc16 = new CRC16XModem();

        $result = [];
        foreach ($code as $c) {
            $crc16->update(pack('C', $c));
            $crc16->update($mac);
            $v = $crc16->finish();

            $v = unpack('C2', $v);
            if ($v === false) {
                $result[] = 0;
            } else {
                $result[] = $lowOrHigh === self::LOW ? $v[2] : $v[1];
            }

            $crc16->reset();
        }

        return $result;
    }

    public static function verifyCRC16Data($mac, array $code, array $crc16): bool
    {
        $data = self::getCrc16Data($mac, $code, self::LOW);
        if (count($data) != count($crc16)) {
            return false;
        }
        foreach ($data as $index => $v) {
            if ($v != $crc16[$index]) {
                return false;
            }
        }

        return true;
    }
}