<?php
/**
 * Class represents records from table ban
 * {autogenerated}
 * @property int $ban_id
 * @property string $type
 * @property string $value
 * @property string $comment
 * @see Am_Table
 */
class Ban extends Am_Record {
}

class BanTable extends Am_Table {
    protected $_key = 'ban_id';
    protected $_table = '?_ban';
    protected $_recordClass = 'Ban';

    /**
     * Check if params matches the records in "ban" table
     *
     * @param array $params like (array('ip' => 'xx', 'email'=>'xx',
     *              'login' => 'xxx')
     * @return array() if ok, array of keys matched like array('ip','login')
     */
    function findBan(array $params)
    {
        $db = $this->_db;
        $where = [];
        foreach ($params as $k => $v)
            $where[] = sprintf("(`type` = %s AND %s LIKE `value` )",
                $db->escape($k), $db->escape($v));
        if (!$where) return [];
        $arr = $db->selectCol($sql = "SELECT DISTINCT `type` FROM ?_ban WHERE " . join(" OR ", $where));
        return $arr;
    }
    /**
     * Function suitable to use as "callback2" function in the Am_Form
     * it returns null if all ok, and dies or returns error message if banned action found
     * @param array $params
     * @return type
     */
    function checkBan(array $params)
    {
        $foundBan = $this->findBan($params);
        foreach ($foundBan as $key)
        {
            $this->getDi()->logger->info("Attempt to signup from denied [$key]: " . htmlentities($params[$key]));
            if ($this->getDi()->config->get("ban.{$key}_action")=="die")
            {
                header("HTTP/1.0 500 Internal Error");
                header("Status: 500 Internal Error");
                exit();
            } else {
                 return ___("This %s is blocked. Please contact site support to find out why", $key);
            }
            return;
            ___('email'); ___('login'); ___('ip');
        }
    }
}