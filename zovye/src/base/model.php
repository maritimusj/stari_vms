<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\base;

use zovye\Util;
use zovye\We7;
use function zovye\tb;

class model
{
    protected $cache = [];

    /**
     * @param $name
     * @return null|modelFactory
     */
    public function load($name): ?modelFactory
    {
        if (isset($this->cache[$name])) {
            return $this->cache[$name];
        }

        //加载用户自定义的modelObj文件
        $classname = "{$name}ModelObj";
        $mod_filename = ZOVYE_CORE_ROOT."src/model/$classname.php";
        if (!is_file($mod_filename)) {
            //加载已生成的modelObj文件
            $mod_filename = MOD_CACHE_DIR."{$name}_auto_.mod.php";
            if (DEBUG || !is_file($mod_filename)) {
                We7::mkDirs(MOD_CACHE_DIR);
                if ($this->createClassFile($name, $classname, $mod_filename)) {
                    require_once $mod_filename;
                }
            } else {
                require_once $mod_filename;
            }
        }

        $class_full_name = "\\zovye\\model\\$classname";
        if (class_exists($class_full_name, true)) {
            $this->cache[$name] = new modelFactory($class_full_name, $name);

            return $this->cache[$name];
        }

        if (DEBUG) {
            trigger_error('Cannot load Model '.$name.',class: '.$class_full_name.',file: '.$mod_filename, E_USER_ERROR);
        }

        return null;
    }

    /**
     * @param $tb_name
     * @param $classname
     * @param $mod_filename
     * @return bool
     */
    protected function createClassFile($tb_name, $classname, $mod_filename): bool
    {
        //生成modelObj文件
        $theme = Util::tableSchema(tb($tb_name));
        if ($theme) {
            $debug = DEBUG ? 'true' : 'false';

            $c = <<<DEBUG_MODE
\tpublic static function debugMode()
\t{
\t    return $debug;
\t}

DEBUG_MODE;

            foreach ($theme['fields'] as $field => $property) {
                if (DEBUG) {
                    $c .= '/*'.PHP_EOL.json_encode($property, JSON_PRETTY_PRINT).PHP_EOL.'*/'.PHP_EOL;
                }
                $c .= "\tprotected \$$field;".PHP_EOL;
            }

            if (isset($theme['fields']['extra']) && $theme['fields']['extra']['type'] == 'text') {
                $c .= PHP_EOL."\tuse \zovye\\traits\ExtraDataGettersAndSetters;";
            }
            $result = file_put_contents(
                $mod_filename,
                <<<CLASS_FILE
<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */
namespace zovye\model;

use zovye\base\modelObj;
use function zovye\\tb;

class $classname extends modelObj
{
    public static function getTableName(\$readOrWrite): string
    {
        return tb('$tb_name');
    }
    
$c
}
CLASS_FILE
            );

            return $result && is_file($mod_filename);
        }

        return false;
    }
}
