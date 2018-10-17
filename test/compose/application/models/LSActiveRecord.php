<?php
/*
 * LimeSurvey
 * Copyright (C) 2013 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 *
  *     Extensions to the CActiveRecord class
 */
class LSActiveRecord extends CActiveRecord
{

    /**
     * Lists the behaviors of this model
     *
     * Below is a list of all behaviors we register:
     * @see CTimestampBehavior
     * @see PluginEventBehavior
     * @return array
     */
    public function behaviors()
    {
        $aBehaviors=array();
        $sCreateFieldName=($this->hasAttribute('created')?'created':null);
        $sUpdateFieldName=($this->hasAttribute('modified')?'modified':null);
        $sDriverName = Yii::app()->db->getDriverName();
        if ($sDriverName=='sqlsrv' || $sDriverName=='dblib')
        {
            $sTimestampExpression=new CDbExpression('GETDATE()');
        }
        else
        {
            $sTimestampExpression=new CDbExpression('NOW()');
        }
        $aBehaviors['CTimestampBehavior'] = array(
            'class' => 'zii.behaviors.CTimestampBehavior',
            'createAttribute' => $sCreateFieldName,
            'updateAttribute' => $sUpdateFieldName,
            'timestampExpression' =>  $sTimestampExpression
        );
        // Some tables might not exist/not be up to date during a database upgrade so in that case disconnect plugin events
        if (!Yii::app()->getConfig('Updating'))
        {
            $aBehaviors['PluginEventBehavior']= array(
                'class' => 'application.models.behaviors.PluginEventBehavior'
            );
        }
        return $aBehaviors;
    }

    /**
     * Modified version that default to do the same as the original, but allows via a
     * third parameter to retrieve the result as array instead of active records. This
     * solves a joining problem. Usage via findAllAsArray method
     *
     * Performs the actual DB query and populates the AR objects with the query result.
     * This method is mainly internally used by other AR query methods.
     * @param CDbCriteria $criteria the query criteria
     * @param boolean $all whether to return all data
     * @return mixed the AR objects populated with the query result
     * @since 1.1.7
     */
    protected function query($criteria, $all = false, $asAR = true)
    {
        if ($asAR === true)
        {
            return parent::query($criteria, $all);
        } else
        {
            $this->beforeFind();
            $this->applyScopes($criteria);
            if (!$all)
            {
                $criteria->limit = 1;
            }

            $command = $this->getCommandBuilder()->createFindCommand($this->getTableSchema(), $criteria);
            //For debug, this command will get you the generated sql:
            //echo $command->getText();

            return $all ? $command->queryAll() : $command->queryRow();
        }
    }

    /**
     * Finds all active records satisfying the specified condition but returns them as array
     *
     * See {@link find()} for detailed explanation about $condition and $params.
     * @param mixed $condition query condition or criteria.
     * @param array $params parameters to be bound to an SQL statement.
     * @return array list of active records satisfying the specified condition. An empty array is returned if none is found.
     */
    public function findAllAsArray($condition = '', $params = array())
    {
        Yii::trace(get_class($this) . '.findAll()', 'system.db.ar.CActiveRecord');
        $criteria = $this->getCommandBuilder()->createCriteria($condition, $params);
        return $this->query($criteria, true, false);  //Notice the third parameter 'false'
    }


    /**
     * Return the max value for a field
     *
     * This is a convenience method, that uses the primary key of the model to
     * retrieve the highest value.
     *
     * @param string  $field        The field that contains the Id, when null primary key is used if it is a single field
     * @param boolean $forceRefresh Don't use value from static cache but always requery the database
     * @return false|int
     */
    public function getMaxId($field = null, $forceRefresh = false)
    {
        static $maxIds = array();

        if (is_null($field)) {
            $primaryKey = $this->getMetaData()->tableSchema->primaryKey;
            if (is_string($primaryKey)) {
                $field = $primaryKey;
            } else {
                // Composite key, throw a warning to the programmer
                throw new Exception(sprintf('Table %s has a composite primary key, please explicitly state what field you need the max value for.', $this->tableName()));
            }
        }

        if ($forceRefresh || !array_key_exists($field, $maxIds)) {
            $maxId = $this->dbConnection->createCommand()
                    ->select('MAX(' .  $this->dbConnection->quoteColumnName($field) . ')')
                    ->from($this->tableName())
                    ->queryScalar();

            // Save so we can reuse in the same request
            $maxIds[$field] = $maxId;
        }

        return $maxIds[$field];
    }
    
    /**
     * Return the min value for a field
     *
     * This is a convenience method, that uses the primary key of the model to
     * retrieve the highest value.
     *
     * @param string  $field        The field that contains the Id, when null primary key is used if it is a single field
     * @param boolean $forceRefresh Don't use value from static cache but always requery the database
     * @return false|int
     */
    public function getMinId($field = null, $forceRefresh = false)
    {
        static $minIds = array();

        if (is_null($field)) {
            $primaryKey = $this->getMetaData()->tableSchema->primaryKey;
            if (is_string($primaryKey)) {
                $field = $primaryKey;
            } else {
                // Composite key, throw a warning to the programmer
                throw new Exception(sprintf('Table %s has a composite primary key, please explicitly state what field you need the min value for.', $this->tableName()));           }
        }

        if ($forceRefresh || !array_key_exists($field, $minIds)) {
            $minId = $this->dbConnection->createCommand()
                    ->select('MIN(' .  $this->dbConnection->quoteColumnName($field) . ')')
                    ->from($this->tableName())
                    ->queryScalar();

            // Save so we can reuse in the same request
            $minIds[$field] = $minId;
        }

        return $minIds[$field];
    }

    /**
     * @todo This should also be moved to the behavior at some point.
     * This method overrides the parent in order to raise PluginEvents for Bulk delete operations.
     *
     * Filter Criteria are wrapped into a CDBCriteria instance so we have a single instance responsible for holding the filter criteria
     * to be passed to the PluginEvent,
     * this also enables us to pass the fully configured CDBCriteria instead of the original Parameters.
     *
     * See {@link find()} for detailed explanation about $condition and $params.
     * @param array $attributes list of attribute values (indexed by attribute names) that the active records should match.
     * An attribute value can be an array which will be used to generate an IN condition.
     * @param mixed $condition query condition or criteria.
     * @param array $params parameters to be bound to an SQL statement.
     * @return integer number of rows affected by the execution.
     */
    public function deleteAllByAttributes($attributes,$condition='',$params=array())
    {
        $builder=$this->getCommandBuilder();
        $table=$this->getTableSchema();
        $criteria=$builder->createColumnCriteria($table,$attributes,$condition,$params);
        $this->dispatchPluginModelEvent('before'.get_class($this).'DeleteMany', $criteria);
        $this->dispatchPluginModelEvent('beforeModelDeleteMany',                $criteria);
        return parent::deleteAllByAttributes(array(), $criteria, array());
    }

}
