<?php
/**
 * Class represents records from table aff_keyword
 * {autogenerated}
 * @property int $keyword_id
 * @property int $aff_id
 * @property string $value
 * @property int $amount
 * @see Am_Table
 */
class AffKeyword extends Am_Record
{
    /** @return User|null */
    function getAff()
    {
        return $this->getDi()->userTable->load($this->aff_id, false);
    }

    function delete()
    {
        parent::delete();
        $this->_db->query("UPDATE ?_aff_click SET keyword_id = NULL WHERE keyword_id=?", $this->pk());
        $this->_db->query("UPDATE ?_aff_lead SET keyword_id = NULL WHERE keyword_id=?", $this->pk());
        $this->_db->query("UPDATE ?_aff_commission SET keyword_id = NULL WHERE keyword_id=?", $this->pk());
        $this->_db->query("UPDATE ?_invoice SET keyword_id = NULL WHERE keyword_id=?", $this->pk());
    }
}

class AffKeywordTable extends Am_Table
{
    protected $_key = 'keyword_id';
    protected $_table = '?_aff_keyword';
}