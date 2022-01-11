<?php
/**
 * 数据库操作类.
 */

namespace we7;

use PDO;
use zovye\We7;
use function zovye\is_error;

defined('IN_IA') or exit('Access Denied');
define('PDO_DEBUG', true);

class db
{
    protected $pdo;
    protected $cfg;
    protected $tablepre;
    protected $result;
    protected $statement;
    protected $errors = array();
    protected $link = array();
    protected $name = '';

    public function __construct($cfg = [])
    {
        $this->cfg = $cfg;
        $this->connect('master');
    }

    public function reConnect($errorInfo, $params)
    {
        if (in_array($errorInfo[1], array(1317, 2013))) {
            $this->pdo = null;
            $this->connect($this->name);
            $method = $params['method'];
            unset($params['method']);
            return call_user_func_array(array($this, $method), $params);
        }
        return false;
    }

    public function connect($name)
    {
        $cfg = $this->cfg[$name];

        $this->tablepre = $cfg['tablepre'];
        if (empty($cfg)) {
            exit("The master database is not found, Please checking 'data/config.php'");
        }

        $dsn = "mysql:dbname={$cfg['database']};host={$cfg['host']};port={$cfg['port']};charset={$cfg['charset']}";
        $pdo = new PDO($dsn, $cfg['username'], $cfg['password'], array(PDO::ATTR_PERSISTENT => $cfg['pconnect']));
        $this->pdo = $pdo;

        $sql = "SET NAMES '{$cfg['charset']}';";
        $this->pdo->exec($sql);
        $this->pdo->exec("SET sql_mode='';");
        if ('root' == $cfg['username'] && in_array($cfg['host'], array('localhost', '127.0.0.1'))) {
            $this->pdo->exec('SET GLOBAL max_allowed_packet = 2*1024*1024*10;');
        }
        if (is_string($name)) {
            $this->link[$name] = $this->pdo;
        }
    }

    public function prepare($sql)
    {
        $sqlsafe = SqlParser::checkquery($sql);
        if (is_error($sqlsafe)) {
            trigger_error($sqlsafe['message'], E_USER_ERROR);
        }
        return $this->pdo->prepare($sql);
    }

    /**
     * 执行一条非查询语句.
     *
     * @param string $sql
     * @param array or string $params
     *
     * @return mixed
     *               成功返回受影响的行数
     *               失败返回FALSE
     */
    public function query($sql, $params = array())
    {
        if (empty($params)) {
            $sqlsafe = SqlParser::checkquery($sql);
            if (is_error($sqlsafe)) {
                trigger_error($sqlsafe['message'], E_USER_ERROR);
            }
            $result = $this->pdo->exec($sql);
            $error_info = $this->pdo->errorInfo();
            if (in_array($error_info[1], array(1317, 2013))) {
                $reConnect = $this->reConnect($error_info, array(
                    'method' => __METHOD__,
                    'sql' => $sql,
                    'params' => $params,
                ));
                return empty($reConnect) ? false : $reConnect;
            }
            return $result;
        }

        $statement = $this->prepare($sql);
        $statement->execute($params);

        $error_info = $statement->errorInfo();
        if (in_array($error_info[1], array(1317, 2013))) {
            $reConnect = $this->reConnect($error_info, array(
                'method' => __METHOD__,
                'sql' => $sql,
                'params' => $params,
            ));
            return empty($reConnect) ? false : $reConnect;
        } else {
            return $statement->rowCount();
        }
    }

    /**
     * 执行SQL返回第一个字段.
     *
     * @param string $sql
     * @param array $params
     * @param int $column 返回查询结果的某列，默认为第一列
     *
     * @return mixed
     */
    public function fetchcolumn($sql, $params = array(), $column = 0)
    {
        $statement = $this->prepare($sql);
        $statement->execute($params);

        $error_info = $statement->errorInfo();
        if (in_array($error_info[1], array(1317, 2013))) {
            $reConnect = $this->reConnect($error_info, array(
                'method' => __METHOD__,
                'sql' => $sql,
                'params' => $params,
                'column' => $column,
            ));
            return empty($reConnect) ? false : $reConnect;
        } else {
            return $statement->fetchColumn($column);
        }
    }

    /**
     * 执行SQL返回第一行.
     *
     * @param string $sql
     * @param array $params
     *
     * @return mixed
     */
    public function fetch($sql, $params = array())
    {
        $statement = $this->prepare($sql);
        $statement->execute($params);

        $error_info = $statement->errorInfo();
        if (in_array($error_info[1], array(1317, 2013))) {
            $reConnect = $this->reConnect($error_info, array(
                'method' => __METHOD__,
                'sql' => $sql,
                'params' => $params,
            ));
            return empty($reConnect) ? false : $reConnect;
        } else {
            return $statement->fetch(pdo::FETCH_ASSOC);
        }
    }

    /**
     * 执行SQL返回全部记录.
     *
     * @param string $sql
     * @param array $params
     *
     * @param string $keyfield
     * @return mixed
     */
    public function fetchall($sql, $params = array(), $keyfield = '')
    {
        $statement = $this->prepare($sql);
        $statement->execute($params);

        $error_info = $statement->errorInfo();
        if (in_array($error_info[1], array(1317, 2013))) {
            $reConnect = $this->reConnect($error_info, array(
                'method' => __METHOD__,
                'sql' => $sql,
                'params' => $params,
                'keyfield' => $keyfield,
            ));
            return empty($reConnect) ? false : $reConnect;
        } else {
            if (empty($keyfield)) {
                $result = $statement->fetchAll(pdo::FETCH_ASSOC);
            } else {
                $temp = $statement->fetchAll(pdo::FETCH_ASSOC);
                $result = array();
                if (!empty($temp)) {
                    foreach ($temp as $key => &$row) {
                        if (isset($row[$keyfield])) {
                            $result[$row[$keyfield]] = $row;
                        } else {
                            $result[] = $row;
                        }
                    }
                }
            }

            return $result;
        }
    }

    public function get($tablename, $params = array(), $fields = array(), $orderby = array())
    {
        $select = SqlParser::parseSelect($fields);
        $condition = SqlParser::parseParameter($params, 'AND');
        $orderbysql = SqlParser::parseOrderby($orderby);

        $sql = "{$select} FROM " . $this->tablename($tablename) . (!empty($condition['fields']) ? " WHERE {$condition['fields']}" : '') . " $orderbysql LIMIT 1";

        return $this->fetch($sql, $condition['params']);
    }

    public function getall($tablename, $params = array(), $fields = array(), $keyfield = '', $orderby = array(), $limit = array())
    {
        $select = SqlParser::parseSelect($fields);
        $condition = SqlParser::parseParameter($params, 'AND');

        $limitsql = SqlParser::parseLimit($limit);
        $orderbysql = SqlParser::parseOrderby($orderby);

        $sql = "{$select} FROM " . $this->tablename($tablename) . (!empty($condition['fields']) ? " WHERE {$condition['fields']}" : '') . $orderbysql . $limitsql;

        return $this->fetchall($sql, $condition['params'], $keyfield);
    }

    public function getslice($tablename, $params = array(), $limit = array(), &$total = null, $fields = array(), $keyfield = '', $orderby = array())
    {
        $select = SqlParser::parseSelect($fields);
        $condition = SqlParser::parseParameter($params, 'AND');
        $limitsql = SqlParser::parseLimit($limit);

        if (!empty($orderby)) {
            if (is_array($orderby)) {
                $orderbysql = implode(',', $orderby);
            } else {
                $orderbysql = $orderby;
            }
        }
        $sql = "{$select} FROM " . $this->tablename($tablename) . (!empty($condition['fields']) ? " WHERE {$condition['fields']}" : '') . (!empty($orderbysql) ? " ORDER BY $orderbysql " : '') . $limitsql;
        $total = We7::pdo_fetchcolumn('SELECT COUNT(*) FROM ' . $this->tablename($tablename) . (!empty($condition['fields']) ? " WHERE {$condition['fields']}" : ''), $condition['params']);

        return $this->fetchall($sql, $condition['params'], $keyfield);
    }

    public function getcolumn($tablename, $params = array(), $field = '')
    {
        $result = $this->get($tablename, $params, $field);
        if (!empty($result)) {
            if (We7::strexists($field, '(')) {
                return array_shift($result);
            } else {
                return $result[$field];
            }
        } else {
            return false;
        }
    }

    /**
     * 更新记录.
     *
     * @param string $table
     * @param array $data
     *                       要更新的数据数组
     *                       array(
     *                       '字段名' => '值'
     *                       )
     * @param array $params
     *                       更新条件
     *                       array(
     *                       '字段名' => '值'
     *                       )
     * @param string $glue
     *                       可以为AND OR
     *
     * @return mixed
     */
    public function update($table, $data = array(), $params = array(), $glue = 'AND')
    {
        $fields = SqlParser::parseParameter($data, ',');
        $condition = SqlParser::parseParameter($params, $glue);
        $params = array_merge($fields['params'], $condition['params']);
        $sql = 'UPDATE ' . $this->tablename($table) . " SET {$fields['fields']}";
        $sql .= $condition['fields'] ? ' WHERE ' . $condition['fields'] : '';

        return $this->query($sql, $params);
    }

    /**
     * 更新记录.
     *
     * @param string $table
     * @param array $data
     *                        要更新的数据数组
     *                        array(
     *                        '字段名' => '值'
     *                        )
     * @param bool $replace
     *                        是否执行REPLACE INTO
     *                        默认为FALSE
     *
     * @return mixed
     */
    public function insert($table, $data = array(), $replace = false)
    {
        $cmd = $replace ? 'REPLACE INTO' : 'INSERT INTO';
        $condition = SqlParser::parseParameter($data, ',');

        return $this->query("$cmd " . $this->tablename($table) . " SET {$condition['fields']}", $condition['params']);
    }

    /**
     * 返回lastInsertId.
     */
    public function insertid()
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * 删除记录.
     *
     * @param string $table
     * @param array $params
     *                       更新条件
     *                       array(
     *                       '字段名' => '值'
     *                       )
     * @param string $glue
     *                       可以为AND OR
     *
     * @return mixed
     */
    public function delete($table, $params = array(), $glue = 'AND')
    {
        $condition = SqlParser::parseParameter($params, $glue);
        $sql = 'DELETE FROM ' . $this->tablename($table);
        $sql .= $condition['fields'] ? ' WHERE ' . $condition['fields'] : '';

        return $this->query($sql, $condition['params']);
    }

    /**
     * 检测一条记录是否存在.
     *
     * @param string $tablename
     * @param array $params
     * @return bool
     */
    public function exists($tablename, $params = array())
    {
        $row = $this->get($tablename, $params);
        if (empty($row) || !is_array($row) || 0 == count($row)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * @param string $tablename
     * @param array $params
     * @return int
     */
    public function count($tablename, $params = array())
    {
        $total = We7::pdo_getcolumn($tablename, $params, 'count(*)');

        return intval($total);
    }

    /**
     * 启动一个事务，关闭自动提交.
     */
    public function begin()
    {
        $this->pdo->beginTransaction();
    }

    /**
     * 提交一个事务，恢复自动提交.
     */
    public function commit()
    {
        $this->pdo->commit();
    }

    /**
     * 回滚一个事务，恢复自动提交.
     *
     */
    public function rollback()
    {
        $this->pdo->rollBack();
    }

    /**
     * 执行SQL文件.
     * @param $sql
     * @param string $stuff
     * @return bool|void
     */
    public function run($sql, $stuff = 'ims_')
    {
        if (!isset($sql) || empty($sql)) {
            return;
        }

        $sql = str_replace("\r", "\n", str_replace(' ' . $stuff, ' ' . $this->tablepre, $sql));
        $sql = str_replace("\r", "\n", str_replace(' `' . $stuff, ' `' . $this->tablepre, $sql));
        $ret = array();
        $num = 0;
        $sql = preg_replace("/;[ \f\t\v]+/", ';', $sql);
        foreach (explode(";\n", trim($sql)) as $query) {
            $ret[$num] = '';
            $queries = explode("\n", trim($query));
            foreach ($queries as $q) {
                $ret[$num] .= (isset($q[0]) && '#' == $q[0]) || (isset($q[0]) && isset($q[1]) && $q[0] . $q[1] == '--') ? '' : $q;
            }
            ++$num;
        }
        unset($sql);
        foreach ($ret as $query) {
            $query = trim($query);
            if ($query) {
                $this->query($query, array());
            }
        }

        return true;
    }

    /**
     * 查询字段是否存在
     * 成功返回TRUE，失败返回FALSE.
     *
     * @param string $tablename
     *                          查询表名
     * @param string $fieldname
     *                          查询字段名
     *
     * @return boolean
     */
    public function fieldexists($tablename, $fieldname)
    {
        $isexists = $this->fetch('DESCRIBE ' . $this->tablename($tablename) . " `{$fieldname}`", array());

        return !empty($isexists);
    }

    /**
     * 查询字段类型是否匹配
     * 成功返回TRUE，失败返回FALSE，字段存在，但类型错误返回-1.
     *
     * @param string $tablename
     *                          查询表名
     * @param string $fieldname
     *                          查询字段名
     * @param string $datatype
     *                          查询字段类型
     * @param string $length
     *                          查询字段长度
     *
     * @return boolean
     */
    public function fieldmatch($tablename, $fieldname, $datatype = '', $length = '')
    {
        $datatype = strtolower($datatype);
        $field_info = $this->fetch('DESCRIBE ' . $this->tablename($tablename) . " `{$fieldname}`", array());
        if (empty($field_info)) {
            return false;
        }
        if (!empty($datatype)) {
            $find = We7::strexists($field_info['Type'], '(');
            if (empty($find)) {
                $length = '';
            }
            if (!empty($length)) {
                $datatype .= ("({$length})");
            }

            return 0 === strpos($field_info['Type'], $datatype) ? true : -1;
        }

        return true;
    }

    /**
     * 查询索引是否存在
     * 成功返回TRUE，失败返回FALSE.
     *
     * @param string $tablename
     *                          查询表名
     * @param array $indexname
     *                          查询索引名
     *
     * @return boolean
     */
    public function indexexists($tablename, $indexname)
    {
        if (!empty($indexname)) {
            $indexs = $this->fetchall('SHOW INDEX FROM ' . $this->tablename($tablename), array(), '');
            if (!empty($indexs) && is_array($indexs)) {
                foreach ($indexs as $row) {
                    if ($row['Key_name'] == $indexname) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * 返回完整数据表名(加前缀)(返回是主库的数据表前缀+表明).
     *
     * @param string $table 表名
     * @return string
     */
    public function tablename($table)
    {
        return (0 === strpos($table, $this->tablepre) || 0 === strpos($table, 'ims_')) ? $table : "`{$this->tablepre}{$table}`";
    }

    /**
     * 判断某个数据表是否存在.
     *
     * @param string $table 表名（不加表前缀）
     *
     * @return bool
     */
    public function tableexists($table)
    {
        if (!empty($table)) {
            $data = $this->fetch("SHOW TABLES LIKE '{$this->tablepre}{$table}'", array());
            if (!empty($data)) {
                $data = array_values($data);
                $tablename = $this->tablepre . $table;
                if (in_array($tablename, $data)) {
                    return true;
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }
}
