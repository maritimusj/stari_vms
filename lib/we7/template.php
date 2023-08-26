<?php

namespace we7;

use zovye\We7;

defined('IN_IA') or exit('Access Denied');

class template
{
    /**
     *   说明: 展示特定模板内容.
     *
     *   参数:
     *    $filename 模板名称，格式为: '模板文件夹/模板名称无后缀'，如: common/header
     *    $flag 模板展示方式
     *   $flag含义:
     *    TEMPLATE_DISPLAY     导入全局变量，渲染并直接展示模板内容(默认值)
     *    TEMPLATE_FETCH       导入全局变量，渲染模板内容，但不展示模板内容，而是将其作为返回值获取。 可用于静态化页面。
     *    TEMPLATE_INCLUDEPATH 不导入全局变量，也不渲染模板内容，只是将编译后的模板文件路径返回，返回的模板编译路径可以直接使用 include 嵌入至当前上下文。
     *   示例: 以下三种调用方式效果相同
     *    $list = array();
     *    ... // 其他更多上下文数据
     *    template('common/template');
     *    //直接展示模板
     *    $content = template('common/template', TEMPLATE_FETCH);
     *    //获取模板渲染出的内容
     *    echo $content;
     *    //输出渲染的内容
     *    include template('common/template', TEMPLATE_INCLUDEPATH);
     *    //嵌入模板编译路径`
     * @param $filename
     * @param int $flag
     * @return string
     */
    public static function load($filename, $flag = TEMPLATE_DISPLAY)
    {
        if (defined('IN_SYS')) {
            $source = ZOVYE_ROOT . "template/$filename.html";
            $compile = ZOVYE_ROOT . "data/tpl/$filename.tpl.php";
        } else {
            $source = ZOVYE_ROOT . "$filename.html";
            $compile = ZOVYE_ROOT . "data/tpl/$filename.tpl.php";
        }
       
        if (!is_file($source)) {
            echo "template source '{$filename}' is not exist!";
            return '';
        }
        if (DEVELOPMENT || !is_file($compile) || filemtime($source) > filemtime($compile)) {
            self::compile($source, $compile);
        }
        switch ($flag) {
            case TEMPLATE_DISPLAY:
            default:
                extract($GLOBALS, EXTR_SKIP);
                include $compile;
                return '';
            case TEMPLATE_FETCH:
                extract($GLOBALS, EXTR_SKIP);
                ob_flush();
                ob_clean();
                ob_start();
                include $compile;
                $contents = ob_get_contents();
                ob_clean();
                return $contents;
            case TEMPLATE_INCLUDEPATH:
                return $compile;
        }
    }

    /**
     * 将模板文件编译为 PHP 文件.
     *
     * @param string $from 模板文件(HTML)路径
     * @param string $to 编译后的 PHP 文件路径
     * @param bool $inmodule
     */
    public static function compile($from, $to, $inmodule = false)
    {
        $path = dirname($to);
        if (!is_dir($path)) {
            We7::make_dirs($path);
        }
        $content = self::parse(file_get_contents($from), $inmodule);
        file_put_contents($to, $content);
    }

    /**
     * 编译模板文件.
     *
     * @param string $str 模板文件字符内容
     *
     * @param bool $inmodule
     * @return string 将 html 编译为 php 后的文件内容
     */
    public static function parse($str, $inmodule = false)
    {
        $str = preg_replace('/<!--{(.+?)}-->/s', '{$1}', $str);
        $str = preg_replace('/{template\s+(.+?)}/', '<?php include self::template($1, TEMPLATE_INCLUDEPATH);?>', $str);
        $str = preg_replace('/{php\s+(.+?)}/', '<?php $1?>', $str);
        $str = preg_replace('/{if\s+(.+?)}/', '<?php if($1) { ?>', $str);
        $str = preg_replace('/{else}/', '<?php } else { ?>', $str);
        $str = preg_replace('/{else ?if\s+(.+?)}/', '<?php } else if($1) { ?>', $str);
        $str = preg_replace('/{\/if}/', '<?php } ?>', $str);
        $str = preg_replace('/{loop\s+(\S+)\s+(\S+)}/', '<?php if(is_array($1)) { foreach($1 as $2) { ?>', $str);
        $str = preg_replace('/{loop\s+(\S+)\s+(\S+)\s+(\S+)}/', '<?php if(is_array($1)) { foreach($1 as $2 => $3) { ?>', $str);
        $str = preg_replace('/{\/loop}/', '<?php } } ?>', $str);
        $str = preg_replace('/{(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)}/', '<?php echo $1;?>', $str);
        $str = preg_replace('/{(\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff\[\]\'\"\$]*)}/', '<?php echo $1;?>', $str);
        $str = preg_replace('/{url\s+(\S+)}/', '<?php echo url($1);?>', $str);
        $str = preg_replace('/{url\s+(\S+)\s+(array\(.+?\))}/', '<?php echo url($1, $2);?>', $str);
        $str = preg_replace('/{media\s+(\S+)}/', '<?php echo tomedia($1);?>', $str);
        $str = preg_replace_callback('/<\?php([^\?]+)\?>/s', __NAMESPACE__ . '\template::addquote', $str);
        $str = preg_replace('/{\/hook}/', '<?php ; ?>', $str);
        $str = preg_replace('/{([A-Z_\x7f-\xff][A-Z0-9_\x7f-\xff]*)}/s', '<?php echo $1;?>', $str);
        $str = str_replace('{##', '{', $str);
        $str = str_replace('##}', '}', $str);

        $str = "<?php defined('IN_IA') or exit('Access Denied');?>" . $str;

        return $str;
    }

    public static function addquote($matchs)
    {
        $code = "<?php $matchs[1]?>";
        $code = preg_replace('/\[([a-zA-Z0-9_\-\.\x7f-\xff]+)\](?![a-zA-Z0-9_\-\.\x7f-\xff\[\]]*[\'"])/s', "['$1']", $code);

        return str_replace('\\\"', '\"', $code);
    }

}