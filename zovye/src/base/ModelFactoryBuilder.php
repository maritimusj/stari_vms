<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\base;

use zovye\util\DBUtil;
use zovye\We7;
use function zovye\tb;

class ModelFactoryBuilder
{
    protected $cache = [];

    public function build($name): ?ModelFactory
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
                We7::make_dirs(MOD_CACHE_DIR);
                if ($this->createClassFile($name, $classname, $mod_filename)) {
                    require_once $mod_filename;
                }
            } else {
                require_once $mod_filename;
            }
        }

        $class_full_name = "\\zovye\\model\\$classname";
        if (class_exists($class_full_name)) {
            $this->cache[$name] = new ModelFactory($class_full_name, $name);

            return $this->cache[$name];
        }

        if (DEBUG) {
            trigger_error('Cannot load Model '.$name.',class: '.$class_full_name.',file: '.$mod_filename, E_USER_ERROR);
        }

        return null;
    }

    protected function createClassFile($tb_name, $classname, $mod_filename): bool
    {
        //生成modelObj文件
        $theme = DBUtil::tableSchema(tb($tb_name));
        if ($theme) {
            $debug = DEBUG ? 'true' : 'false';

            $c = <<<DEBUG_MODE
\tpublic static function debugMode(): bool
\t{
\t    return $debug;
\t}
\n
DEBUG_MODE;

            foreach ($theme['fields'] as $field => $property) {
                // if (DEBUG) {
                //     $c .= '/*'.PHP_EOL.json_encode($property, JSON_PRETTY_PRINT).PHP_EOL.'*/'.PHP_EOL;
                // }
                $c .= "\t/** @var {$property['type']} */".PHP_EOL;
                $c .= "\tprotected \$$field;".PHP_EOL.PHP_EOL;
            }

            if (isset($theme['fields']['extra']) && ($theme['fields']['extra']['type'] == 'text' || $theme['fields']['extra']['type'] == 'json')) {
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

use zovye\\base\\modelObj;
use function zovye\\tb;

class $classname extends modelObj
{
    public static function getTableName(\$read_or_write): string
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
