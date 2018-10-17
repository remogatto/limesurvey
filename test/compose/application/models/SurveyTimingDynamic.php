<?php if (!defined('BASEPATH')) exit('No direct script access allowed');
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
  * 	Files Purpose: lots of common functions
 */

class SurveyTimingDynamic extends LSActiveRecord
{
    protected static $sid = 0;
    
	/**
	 * Returns the static model
	 *
	 * @static
	 * @access public
	 * @return CActiveRecord
	 */
    public static function model($sid = NULL)
    {         
        $refresh = false;
        if (!is_null($sid)) {
            self::sid($sid);
            $refresh = true;
        }
        
        $model = parent::model(__CLASS__);
        
        //We need to refresh if we changed sid
        if ($refresh === true) $model->refreshMetaData();
        return $model;
    }
    
	/**
	 * Sets the survey ID for the next model
	 *
	 * @static
	 * @access public
	 * @param int $sid
	 * @return void
	 */
	public static function sid($sid)
	{
		self::$sid = (int) $sid;
	}

    /**
     * Returns the primary key of this table
     *
     * @access public
     * @return string
     */
    public function primaryKey()
    {
        return 'id';
    }

    /**
    * Defines the relations for this model
    *
    * @access public
    * @return array
    */
    public function relations()
    {
        return array(
        'id' => array(self::BELONGS_TO, 'SurveyDynamic', 'id'),
        );
    }

    /**
     * Returns the setting's table name to be used by the model
     *
     * @access public
     * @return string
     */
    public function tableName()
    {
        return '{{survey_' . intval(self::$sid) . '_timings}}';
    }

    /**
     * Returns Time statistics for this answer table
     *
     * @access public
     * @return array
     */
    public function statistics()
    {
        $sid = self::$sid;
		if(Yii::app()->db->schema->getTable($this->tableName())){
            $queryAvg=Yii::app()->db->createCommand()
                ->select("AVG(interviewtime) AS avg, COUNT(*) as count")
                ->from($this->tableName()." t")
                ->join("{{survey_{$sid}}} s","t.id = s.id")
                ->where("s.submitdate IS NOT NULL")
                ->queryRow();
            if($queryAvg['count']){
                $statistics['avgmin'] = (int) ($queryAvg['avg'] / 60);
                $statistics['avgsec'] = $queryAvg['avg'] % 60;
                $statistics['count'] = $queryAvg['count'];
                $queryAll=Yii::app()->db->createCommand()
                    ->select("interviewtime")
                    ->from($this->tableName()." t")
                    ->join("{{survey_{$sid}}} s","t.id = s.id")
                    ->where("s.submitdate IS NOT NULL")
                    ->order("t.interviewtime")
                    ->queryAll();
                $middleval = intval($statistics['count'] / 2);
                $statistics['middleval'] = $middleval;
                if ($statistics['count'] % 2 && $statistics['count']>1)
                {
                    $median=($queryAll[$middleval]['interviewtime'] + $queryAll[$middleval-1]['interviewtime']) / 2;
                }
                else
                {
                    $median=$queryAll[$middleval]['interviewtime'];
                }
                $statistics['median'] = $median;
                $statistics['allmin'] = (int) ($median / 60);
                $statistics['allsec'] = $median % 60;
            }
            else
            {
                $statistics['count'] = 0;
            }
        }
        else
        {
            $statistics['count'] = 0;
        }
        return $statistics;
    }

    public function insertRecords($data)
    {
        $record = new self;
        foreach ($data as $k=>$v) {
            $record->$k = $v;
        }

        try {
            $record->save();
            return $record->id;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Search function, used by TbGridView
     *
     * @return DataProvider
     */
    public function search($iSurveyID, $language)
    {
        $pageSize = Yii::app()->user->getState('pageSize', Yii::app()->params['defaultPageSize']);

        $oCriteria = new CdbCriteria();
        $oCriteria->join = "INNER JOIN {{survey_{$iSurveyID}}} s ON t.id=s.id";
        $oCriteria->condition = 'submitdate IS NOT NULL';
        $oCriteria->order = "s.id " . (Yii::app()->request->getParam('order') == 'desc' ? 'desc' : 'asc');
        //$oCriteria->offset = $start;
        //$oCriteria->limit = $limit;

        $dataProvider = new CActiveDataProvider('SurveyTimingDynamic', array(
            //'sort'=>$sort,
            'criteria' => $oCriteria,
            'pagination' => array(
                'pageSize' => $pageSize,
            ),
        ));

        return $dataProvider;
    }

    /**
     * Buttons for actions in the grid view
     *
     * @return string HTML
     */
    public function getButtons()
    {
        // View details
        $viewUrl = App()->createUrl("admin/responses/sa/view/surveyid/" . self::$sid . "/id/" . $this->id);
        $buttons = '<a class="btn btn-xs btn-default" href="' . $viewUrl . '" role="button" data-toggle="tooltip" title="'.gT('View response details').'"><span class="glyphicon glyphicon-list-alt" ></span></a>';

        // Edit
        if (Permission::model()->hasSurveyPermission(self::$sid, 'responses', 'update'))
        {
            $editUrl = App()->createUrl("admin/dataentry/sa/editdata/subaction/edit/surveyid/" . self::$sid . "/id/" . $this->id);
            $buttons .= '&nbsp;<a class="btn btn-xs btn-default" href="' . $editUrl . '" role="button" data-toggle="tooltip" title="'.gT('Edit this response').'"><span class="glyphicon glyphicon-pencil" ></span></a>';
        }

        // Delete
        if (Permission::model()->hasSurveyPermission(self::$sid, 'responses', 'delete'))
        {
            $deleteUrl = App()->createUrl("admin/dataentry/sa/delete/subaction/edit/surveyid/" . self::$sid . "/id/" . $this->id);
            $buttons .= '&nbsp;<a class="btn btn-xs btn-default" data-target="#confirmation-modal" data-href="' . $deleteUrl . '" role="button" data-toggle="modal" data-tooltip="true" title="'.gT('Delete this response').'"><span class="text-danger glyphicon glyphicon-trash" ></span></a>';
        }

        return $buttons;
    }

}
