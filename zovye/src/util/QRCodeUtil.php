<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */


namespace zovye\util;

use Exception;
use QRcode;
use zovye\Log;
use zovye\We7;
use function zovye\err;
use function zovye\load;

class QRCodeUtil
{
    public static function renderTxt($file, $text)
    {
        $file_size = getimagesize($file);
        $ext = $file_size['mime'];
        if (strpos(strtolower($ext), 'jpeg') !== false || strpos(strtolower($ext), 'jpg') !== false) {
            $im = imagecreatefromjpeg($file);
        } elseif (strpos(strtolower($ext), 'png') !== false) {
            $im = imagecreatefrompng($file);
        } else {
            return;
        }

        $i_w = imagesx($im);
        $i_h = imagesy($im);

        $x_offset = ($i_w + 44 - 18 * strlen($text)) / 2;
        $n_w = $i_w + 44;
        $n_h = $i_h + 44;

        $im2 = imagecreatetruecolor($n_w, $n_h);
        $background = imagecolorallocate($im2, 255, 255, 255);
        imagefill($im2, 0, 0, $background);

        imagecopyresized($im2, $im, 22, 0, 0, 0, floor($i_w), floor($i_h), floor($i_w), floor($i_h));
        $black = imagecolorallocate($im2, 0, 0, 0);
        imagefttext(
            $im2,
            24,
            0,
            $x_offset,
            floor($i_h) + 24,
            $black,
            realpath(realpath(ZOVYE_CORE_ROOT.'../static/fonts/arial.ttf')),
            $text
        );

        if (strpos(strtolower($ext), 'jpeg') !== false || strpos(strtolower($ext), 'jpg') !== false) {
            imagejpeg($im2, $file);
        } elseif (strpos(strtolower($ext), 'png') !== false) {
            imagepng($im2, $file);
        }

        imagedestroy($im);
        imagedestroy($im2);
    }

    /**
     * 创建二维码 $id = type.uid形式指定.
     *
     * @param $id
     * @param $text
     * @param callable|null $cb
     * @return string|array
     */
    public static function createFile($id, $text, callable $cb = null)
    {
        if (stripos($id, '.') !== false) {
            list($type, $id) = explode('.', $id, 2);
            if (empty($type)) {
                $type = 'default';
            }
            if (empty($id)) {
                $id = sha1($text);
            }
        } else {
            $type = 'default';
        }

        $filename = "$id.png";
        $dirname = "zovye/$type/";

        $full_filename = Helper::getAttachmentFileName($dirname, $filename);

        load()->library('qrcode');

        $error_correction_level = 'L';
        $matrix_point_size = '8';

        QRcode::png($text, $full_filename, $error_correction_level, $matrix_point_size);

        if (file_exists($full_filename)) {
            if ($cb != null) {
                $cb($full_filename);
            }

            try {
                We7::file_remote_upload("$dirname$filename");
            } catch (Exception $e) {
                Log::error('createQrcodeFile', $e->getMessage());
            }

            return "$dirname$filename";
        }

        return err('创建文件失败！');
    }

    public static function download($url, $dirname, $filename): string
    {
        $content = HttpUtil::get($url);
        if ($content !== null) {
            if (stripos($filename, '{hash}') !== false) {
                $filename = str_replace('{hash}', sha1($content), $filename);
            }
            $full_filename = Helper::getAttachmentFileName($dirname, $filename);
            if (file_put_contents($full_filename, $content) !== false) {
                return "$dirname$filename";
            }
        }

        return 'error';
    }

    /**
     * 下载指定的二维码
     * @param $url
     * @return string
     */
    public static function downloadQRCode($url): string
    {
        return self::download($url, 'download/qrcode/', '{hash}.png');
    }
}