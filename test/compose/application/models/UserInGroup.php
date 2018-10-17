<?php if ( ! defined('BASEPATH')) die('No direct script access allowed');
/*
 * LimeSurvey
 * Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 *
 */
class UserInGroup extends LSActiveRecord {

    /**
     * Returns the static model of Settings table
     *
     * @static
     * @access public
     * @param string $class
     * @return CActiveRecord
     */
    public static function model($class = __CLASS__)
    {
        return parent::model($class);
    }

    /**
     * Returns the setting's table name to be used by the model
     *
     * @access public
     * @return string
     */
    public function tableName()
    {
        return '{{user_in_groups}}';
    }

    /**
     * Returns the primary key of this table
     *
     * @access public
     * @return string[]
     */
    public function primaryKey()
    {
        return array('ugid', 'uid');
    }

    /**
     * @return array relational rules.
     */
    public function relations()
    {
        // NOTE: you may need to adjust the relation name and the related
        // class name for the relations automatically generated below.
        return array(
            'users' => array(self::BELONGS_TO, 'User', '', 'on' => 't.uid = users.uid')
        );
    }

    public function getAllRecords($condition=FALSE)
    {
        $criteria = new CDbCriteria;

        if ($condition != FALSE)
        {
            foreach ($condition as $item => $value)
            {
                $criteria->addCondition($item.'='.Yii::app()->db->quoteValue($value));
            }
        }

        $data = $this->findAll($criteria);

        return $data;
    }

    function insertRecords($data)
    {
        $user = Yii::app()->db->createCommand()->insert($this->tableName(), $data);
        return (bool) $user;
    }

    function join($fields, $from, $condition=FALSE, $join=FALSE, $order=FALSE)
    {
        $user = Yii::app()->db->createCommand();
        foreach ($fields as $field)
        {
            $user->select($field);
        }

        $user->from($from);

        if ($condition != FALSE)
        {
            $user->where($condition);
        }

        if ($order != FALSE)
        {
            $user->order($order);
        }

        if (isset($join['where'], $join['on']))
        {
            if (isset($join['left'])) {
                $user->leftjoin($join['where'], $join['on']);
            }else
            {
                $user->join($join['where'], $join['on']);
            }
        }

        $data = $user->queryRow();
        return $data;
    }

}
