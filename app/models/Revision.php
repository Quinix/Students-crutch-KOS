<?php
/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Functions
 *
 * @author Honza
 */
class Revision extends Model {

    public static function find($where = NULL, $order = NULL, $offset = NULL, $limit = NULL) {
        $q=dibi::select("*")->from("[rozvrh_main].[revision]");
        if($where!=NULL) $q->where ($where);
        if($order!=NULL) $q->orderBy ($order);
        if($limit!=NULL) $q->limit ($limit);
        if($offset!=NULL) $q->offset ($offset);

        return $q->execute()->setRowClass(get_called_class())->fetchAssoc('rev_id');
    }

}
?>