<?php
/**
 * @author jin@stariture.com
 * @url www.stariture.com
 */

namespace zovye\base;

use Exception;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;
use zovye\Contract\ISettings;
use zovye\LogObj;
use zovye\Settings;
use zovye\traits\DirtyChecker;
use zovye\traits\GettersAndSetters;
use zovye\Util;
use function zovye\getArray;
use function zovye\ifEmpty;
use function zovye\setArray;

/**
 * @method getQrcode()
 * @method setData($data)
 * @method getCreatetime()
 * @method hasCreatetime()
 */
class modelObj implements ISettings
{
    const OP_UNKNOWN = null;
    const OP_READ = 1;
    const OP_WRITE = 2;

    /** @var int */
    protected $id;

    private $factory;
    private $logger;

    private $settings_container = [];

    private $settingsObj;
    private $settingsUseCache;

    use DirtyChecker;
    use GettersAndSetters;

    public function __construct($id, modelFactory $factory, $settingsUseCache = SETTINGS_USE_CACHE)
    {
        $this->configFilter('set', ['id', 'factory']);

        $this->id = $id;
        $this->factory = $factory;
        $this->settingsUseCache = $settingsUseCache;
    }

    /**
     * 返回指定字段从哪里读取数据,默认全部db
     * @param $obj
     * @param $seg
     * @return array
     */
    public static function fromDbOrCache($obj, $seg): array
    {
        unset($obj);

        $seg_arr = is_array($seg) ? $seg : [$seg];

        return [
            'db' => $seg_arr ?: '*',
            'cache' => [],
        ];
    }

    public static function getTableName($readOrWrite): string
    {
        $tb_name = get_called_class().'::TB_NAME';
        if (defined($tb_name)) {
            return constant($tb_name);
        }

        unset($readOrWrite);

        trigger_error(
            'tb_name() must be implemented or constant TB_NAME must be defined by '.get_called_class(),
            E_USER_ERROR
        );
    }

    public function log(int $level, string $title, $data): bool
    {
        if ($level && $title) {
            if (!isset($this->logger)) {
                $this->logger = new LogObj($this->factory->shortName());
            }

            if ($this->logger) {
                return $this->logger->create($level, $title, $data);
            }
        }

        return false;
    }

    public function forceReloadPropertyValue($name, $params = null)
    {
        unset($params);

        if ($name) {
            $res = $this->factory->__loadFromDb($this, $name, true);
            if ($res) {
                $this->$name = $res[$name];

                return $this->$name;
            }
        }
        $classname = get_called_class();
        throw new RuntimeException("加载$classname::{$name}失败！");
    }

    public function factory(): ?modelFactory
    {
        return $this->factory;
    }

    //ISettings implements

    /**
     * 删除指定的settings值
     * @param string $key
     * @param string $sub
     * @return bool
     */
    public function removeSettings(string $key, string $sub): bool
    {
        if ($key && $sub) {
            $data = $this->settings($key, []);
            unset($data[$sub]);

            return $this->updateSettings($key, $data);
        }

        return false;
    }


    public function settings($key, $default = null)
    {
        if (empty($key)) {
            return $default;
        }

        $keys = is_array($key) ? $key : explode('.', $key);
        if (empty($keys)) {
            return $default;
        }

        $index = array_shift($keys);
        if (!isset($this->settings_container[$index])) {
            $this->get($index, []);
        }

        $result = getArray($this->settings_container[$index], $keys, $default);

        return $this->convert($result, gettype($default));
    }

    public function get($key, $default = null)
    {
        if ($this->id && $key) {
            $settings_key = $this->getSettingsKey($key);
            $data = $this->createSettings()->get($settings_key);
            $this->settings_container[$key] = $data;

            return ifEmpty($data, $default);
        }

        return null;
    }

    /**
     * 使用sha1值代替原key值，过渡函数
     * @param $key
     * @param string $classname
     * @return string
     */
    protected function getSettingsKey($key, string $classname = ''): string
    {
        if (empty($classname)) {
            $classname = get_called_class();
        }

        return sha1("$classname:$this->id:$key");
    }

    public function createSettings(): Settings
    {
        if (!isset($this->settingsObj)) {
            $this->settingsObj = new Settings(
                'settings',
                $this->getSettingsBindClassName() ?: $this->factory->shortName(),
                $this->settingsUseCache
            );
        }

        return $this->settingsObj;
    }

    protected function getSettingsBindClassName(): string
    {
        return '';
    }

    /**
     * 更新指定的settings值
     * @param mixed $key
     * @param mixed $val
     * @return bool
     */
    public function updateSettings($key, $val): bool
    {
        if (empty($key)) {
            return false;
        }

        $keys = is_array($key) ? $key : explode('.', $key);
        if (empty($keys)) {
            return false;
        }

        $index = array_shift($keys);
        $data = $this->settings($index, []);

        return $this->set($index, setArray($data, $keys, $val));
    }

    public function set($key, $val): bool
    {
        if ($this->id && $key) {
            $settings_key = $this->getSettingsKey($key);
            if ($this->createSettings()->set($settings_key, $val)) {
                $this->settings_container[$key] = $val;

                return true;
            }
        }

        return false;
    }

    public function has($key): bool
    {
        if ($this->id && $key) {
            $settings_key = $this->getSettingsKey($key);

            return $this->createSettings()->has($settings_key);
        }

        return false;
    }

    public function remove($key): bool
    {
        if ($this->id && $key) {
            unset($this->settings_container[$key]);

            $settings_key = $this->getSettingsKey($key);
            $this->createSettings()->remove($settings_key);

            return true;
        }

        return false;
    }

    /**
     * @param $key
     * @param null $default
     * @return mixed
     */
    public function pop($key, $default = null)
    {
        if ($this->id && $key) {
            unset($this->settings_container[$key]);
            $settings_key = $this->getSettingsKey($key);

            return $this->createSettings()->pop($settings_key, $default);
        }

        return null;
    }

    public function save(): bool
    {
        if ($this->factory) {
            return $this->factory->__saveToDb($this) !== false;
        }

        return false;
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function saveWhen($condition = []): bool
    {
        if ($this->factory) {
            return $this->factory->__saveToDb($this, null, $condition) > 0;
        }

        return false;
    }

    /**
     * @return bool
     */
    public function destroy(): bool
    {
        if ($this->factory()) {
            $res = $this->factory->remove($this);
            if ($res) {
                foreach ($this as $key => $val) {
                    $this->$key = null;
                }

                return true;
            }
        }

        return false;
    }

    public static function convert($val, $type_hints)
    {
        switch ($type_hints) {
            case 'int':
            case 'integer':
                return intval($val);
            case 'string':
            case 'str':
                return strval($val);
            case 'bool':
            case 'boolean':
                return boolval($val);
            case 'float':
                return floatval($val);
            case 'double':
                return doubleval($val);
            case 'array':
                return (array)$val;
            case 'json':
                return json_decode($val, true);
            default:
                return $val;
        }
    }

    public static function parseType($doc): string
    {
        if (preg_match('/@var\s+(\w+)[\s|*]+/', $doc, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * @param array $data
     * @return $this
     */
    public function __setData(array $data): modelObj
    {
        foreach (self::getProps(true) as $prop => $type_hints) {
            $this->$prop = self::convert($data[$prop], $type_hints);
        }

        return $this;
    }

    /**
     * @param bool $doc
     * @return mixed
     */
    public static function getProps(bool $doc = false)
    {
        static $props_cache = [];

        $classname = get_called_class();
        if (!isset($props_cache[$classname])) {

            try {
                $ref = new ReflectionClass($classname);
                foreach ($ref->getProperties(
                    ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PROTECTED
                ) as $prop) {
                    $props_cache[$classname][$prop->getName()] = self::parseType($prop->getDocComment());
                }
            } catch (Exception $e) {
            }

        }

        return $doc ? $props_cache[$classname] : array_keys($props_cache[$classname]);
    }

    /**
     * @param mixed $seg
     * @return array
     */
    public function __getData($seg = null): array
    {
        $isOk = function () {
            return true;
        };

        if ($seg) {
            if (is_array($seg)) {
                $isOk = function ($key) use ($seg) {
                    return in_array($key, $seg);
                };
            } elseif (is_callable($seg)) {
                $isOk = function ($key) use ($seg) {
                    return $seg($key);
                };
            } elseif ($seg == 'all') {
                $isOk = function () {
                    return true;
                };
            }
        } else {
            if ($seg === null) {
                if (Util::traitUsed($this, 'DirtyChecker')) {
                    $isOk = function ($key) {
                        return $this->isDirty($key);
                    };
                }
            }
        }

        $data = [];
        foreach (self::getProps() as $prop) {
            if ($isOk($prop)) {
                $data[$prop] = $this->$prop;
            }
        }

        return $data;
    }
}
