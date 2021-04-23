<?php
// copyrights by 微擎

/**
 * 格式化SQL语句
 *
 */

namespace we7;

class SqlParser
{
    private static $checkcmd = array('SELECT', 'UPDATE', 'INSERT', 'REPLAC', 'DELETE');
    private static $disable = array(
        'function' => array('load_file', 'floor', 'hex', 'substring', 'if', 'ord', 'char', 'benchmark', 'reverse', 'strcmp', 'datadir', 'updatexml', 'extractvalue', 'name_const', 'multipoint', 'database', 'user'),
        'action' => array('@', 'intooutfile', 'intodumpfile', 'unionselect', 'uniondistinct', 'information_schema', 'current_user', 'current_date'),
        'note' => array('/*', '*/', '#', '--'),
    );

    public static function checkquery($sql)
    {
        $cmd = strtoupper(substr(trim($sql), 0, 6));
        if (in_array($cmd, self::$checkcmd)) {
            $sql = str_replace(array('\\\\', '\\\'', '\\"', '\'\''), '', $sql);
            if (false === strpos($sql, '/') && false === strpos($sql, '#') && false === strpos($sql, '-- ') && false === strpos($sql, '@') && false === strpos($sql, '`')) {
                $cleansql = preg_replace("/'(.+?)'/s", '', $sql);
            } else {
                $cleansql = self::stripSafeChar($sql);
            }

            $cleansql = preg_replace("/[^a-z0-9_\-()#*\/\"]+/is", '', strtolower($cleansql));
            if (is_array(self::$disable['function'])) {
                foreach (self::$disable['function'] as $fun) {
                    if (false !== strpos($cleansql, $fun . '(')) {
                        return error(1, 'SQL中包含禁用函数 - ' . $fun);
                    }
                }
            }

            if (is_array(self::$disable['action'])) {
                foreach (self::$disable['action'] as $action) {
                    if (false !== strpos($cleansql, $action)) {
                        return error(2, 'SQL中包含禁用操作符 - ' . $action);
                    }
                }
            }

            if (is_array(self::$disable['note'])) {
                foreach (self::$disable['note'] as $note) {
                    if (false !== strpos($cleansql, $note)) {
                        return error(3, 'SQL中包含注释信息');
                    }
                }
            }
        } elseif ('/*' === substr($cmd, 0, 2)) {
            return error(3, 'SQL中包含注释信息');
        }

        return true;
    }

    private static function stripSafeChar($sql)
    {
        $len = strlen($sql);
        $mark = $clean = '';
        for ($i = 0; $i < $len; ++$i) {
            $str = $sql[$i];
            switch ($str) {
                case '\'':
                    if (!$mark) {
                        $mark = '\'';
                        $clean .= $str;
                    } elseif ('\'' == $mark) {
                        $mark = '';
                    }
                    break;
                case '/':
                    if (empty($mark) && '*' == $sql[$i + 1]) {
                        $mark = '/*';
                        $clean .= $mark;
                        ++$i;
                    } elseif ('/*' == $mark && '*' == $sql[$i - 1]) {
                        $mark = '';
                        $clean .= '*';
                    }
                    break;
                case '#':
                    if (empty($mark)) {
                        $mark = $str;
                        $clean .= $str;
                    }
                    break;
                case "\n":
                    if ('#' == $mark || '--' == $mark) {
                        $mark = '';
                    }
                    break;
                case '-':
                    if (empty($mark) && '-- ' == substr($sql, $i, 3)) {
                        $mark = '-- ';
                        $clean .= $mark;
                    }
                    break;
                default:
                    break;
            }
            $clean .= $mark ? '' : $str;
        }

        return $clean;
    }

    /**
     * 将数组格式化为具体的字符串
     * 增加支持 大于 小于, 不等于, not in, +=, -=等操作符.
     *
     * @param array $params
     *                       要格式化的数组
     * @param string $glue
     *                       字符串分隔符
     *
     * @param string $alias
     *
     * @return array
     *               array['fields']是格式化后的字符串
     */
    public static function parseParameter($params, $glue = ',', $alias = '')
    {
        $result = array('fields' => ' 1 ', 'params' => array());
        $split = '';
        $suffix = '';
        $allow_operator = array('>', '<', '<>', '!=', '>=', '<=', '+=', '-=', 'LIKE', 'like', 'REGEXP', 'regexp');
        if (in_array(strtolower($glue), array('and', 'or'))) {
            $suffix = '__';
        }
        if (!is_array($params)) {
            $result['fields'] = $params;

            return $result;
        }
        if (is_array($params)) {
            $result['fields'] = '';
            foreach ($params as $fields => $value) {
                //update或是insert语句，值为null时按空处理，仅当值为NULL时，才按 IS null 处理
                if (',' == $glue) {
                    $value = null === $value ? '' : $value;
                }
                $operator = '';
                if (false !== strpos($fields, ' ')) {
                    list($fields, $operator) = explode(' ', $fields, 2);
                    if (!in_array($operator, $allow_operator)) {
                        $operator = '';
                    }
                }
                if (empty($operator)) {
                    $fields = trim($fields);
                    if (is_array($value) && !empty($value)) {
                        $operator = 'IN';
                    } elseif ('NULL' === $value) {
                        $operator = 'IS';
                    } else {
                        $operator = '=';
                    }
                } elseif ('+=' == $operator) {
                    $operator = " = `$fields` + ";
                } elseif ('-=' == $operator) {
                    $operator = " = `$fields` - ";
                } elseif ('!=' == $operator || '<>' == $operator) {
                    //如果是数组不等于情况，则转换为NOT IN
                    if (is_array($value) && !empty($value)) {
                        $operator = 'NOT IN';
                    } elseif ('NULL' === $value) {
                        $operator = 'IS NOT';
                    }
                }

                //当条件为having时，可以使用聚合函数
                $select_fields = self::parseFieldAlias($fields, $alias);
                if (is_array($value) && !empty($value)) {
                    $insql = array();
                    //忽略数组的键值，防止SQL注入
                    $value = array_values($value);
                    foreach ($value as $v) {
                        $placeholder = self::parsePlaceholder($fields, $suffix);
                        $insql[] = $placeholder;
                        $result['params'][$placeholder] = is_null($v) ? '' : $v;
                    }
                    $result['fields'] .= $split . "$select_fields {$operator} (" . implode(',', $insql) . ')';
                    $split = ' ' . $glue . ' ';
                } else {
                    $placeholder = self::parsePlaceholder($fields, $suffix);
                    $result['fields'] .= $split . "$select_fields {$operator} " . ('NULL' === $value ? 'NULL' : $placeholder);
                    $split = ' ' . $glue . ' ';
                    if ('NULL' !== $value) {
                        $result['params'][$placeholder] = is_array($value) ? '' : $value;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * 处理字段占位符.
     *
     * @param string $field
     * @param string $suffix
     */
    private static function parsePlaceholder($field, $suffix = '')
    {
        static $params_index = 0;
        ++$params_index;

        $illegal_str = array('(', ')', ',', '.', '*');
        return ":{$suffix}" . str_replace($illegal_str, '_', $field) . "_{$params_index}";
    }

    private static function parseFieldAlias($field, $alias = '')
    {
        if (strexists($field, '.') || strexists($field, '*')) {
            return $field;
        }
        if (strexists($field, '(')) {
            $select_fields = str_replace(array('(', ')'), array('(' . (!empty($alias) ? "`{$alias}`." : '') . '`', '`)'), $field);
        } else {
            $select_fields = (!empty($alias) ? "`{$alias}`." : '') . "`$field`";
        }

        return $select_fields;
    }

    /**
     * 格式化select字段.
     *
     * @param array $field 字段
     * @param string $alias 表别名
     * @return string
     */
    public static function parseSelect($field = array(), $alias = '')
    {
        if (empty($field) || '*' == $field) {
            return ' SELECT *';
        }
        if (!is_array($field)) {
            $field = array($field);
        }
        $select = array();
        $index = 0;
        foreach ($field as $field_row) {
            if (strexists($field_row, '*')) {
                if (!strexists(strtolower($field_row), 'as')) {
                    //此代码暂时注释，否则会造成 * AS 0 的问题，忘了是为什么要加
                    //$field_row .= " AS '{$index}'";
                }
            } elseif (strexists(strtolower($field_row), 'select')) {
                //当前可能包含子查询，但不推荐此写法
                if ('(' != $field_row[0]) {
                    $field_row = "($field_row) AS '{$index}'";
                }
            } elseif (strexists($field_row, '(')) {
                if (strexists($field_row, '"')) {
                    $field_row = str_replace('"', '`' , $field_row);
                } elseif (strexists($field_row, "'")) {
                    $field_row = str_replace("'", '`' , $field_row);
                } elseif (!strexists($field_row, '`')) {
                    $field_row = str_replace(array('(', ')'), array('(' . (!empty($alias) ? "`{$alias}`." : '') . '`', '`)'), $field_row);
                }

                //如果聚合函数没有指定AS字段，则添加当前索引为AS
                if (!strexists(strtolower($field_row), 'as')) {
                    $field_row .= " AS '{$index}'";
                }
                
            } else {
                $field_row = self::parseFieldAlias($field_row, $alias);
            }
            $select[] = $field_row;
            ++$index;
        }

        return ' SELECT ' . implode(',', $select);
    }

    public static function parseLimit($limit, $inpage = true)
    {
        $limitsql = '';
        if (empty($limit)) {
            return $limitsql;
        }
        if (is_array($limit)) {
            //兼容第一个值为0的写法
            if (empty($limit[0]) && !empty($limit[1])) {
                $limitsql = ' LIMIT 0, ' . $limit[1];
            } else {
                $limit[0] = max(intval($limit[0]), 1);
                !empty($limit[1]) && $limit[1] = max(intval($limit[1]), 1);
                if (empty($limit[0]) && empty($limit[1])) {
                    $limitsql = '';
                } elseif (!empty($limit[0]) && empty($limit[1])) {
                    $limitsql = ' LIMIT ' . $limit[0];
                } else {
                    $limitsql = ' LIMIT ' . ($inpage ? ($limit[0] - 1) * $limit[1] : $limit[0]) . ', ' . $limit[1];
                }
            }
        } else {
            $limit = trim($limit);
            if (preg_match('/^(?:limit)?[\s,0-9]+$/i', $limit)) {
                $limitsql = strexists(strtoupper($limit), 'LIMIT') ? " $limit " : " LIMIT $limit";
            }
        }

        return $limitsql;
    }

    public static function parseOrderby($orderby, $alias = '')
    {
        $orderbysql = '';
        if (empty($orderby)) {
            return $orderbysql;
        }
        if (!is_array($orderby)) {
            $orderby = explode(',', $orderby);
        }
        foreach ($orderby as $i => &$row) {
            if (strtoupper($row) == 'RAND()') {
                $row = strtoupper($row);
            } else {
                $row = strtolower($row);
                list($field, $orderbyrule) = explode(' ', $row);

                if ('asc' != $orderbyrule && 'desc' != $orderbyrule) {
                    unset($orderby[$i]);
                }
                $field = self::parseFieldAlias($field, $alias);
                $row = "{$field} {$orderbyrule}";
            }
        }
        $orderbysql = implode(',', $orderby);
        return !empty($orderbysql) ? " ORDER BY $orderbysql " : '';
    }

    public static function parseGroupby($statement, $alias = '')
    {
        if (empty($statement)) {
            return $statement;
        }
        if (!is_array($statement)) {
            $statement = explode(',', $statement);
        }
        foreach ($statement as $i => &$row) {
            $row = self::parseFieldAlias($row, $alias);
            if (strexists($row, ' ')) {
                unset($statement[$i]);
            }
        }
        $statementsql = implode(', ', $statement);

        return !empty($statementsql) ? " GROUP BY $statementsql " : '';
    }
}
