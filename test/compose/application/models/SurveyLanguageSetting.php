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

/**
 * Class SurveyLanguageSetting
 *
 * @property integer $surveyls_survey_id
 * @property string $surveyls_language
 * @property string $surveyls_title
 * @property string $surveyls_description
 * @property string $surveyls_welcometext
 * @property string $surveyls_endtext
 * @property string $surveyls_url
 * @property string $surveyls_urldescription
 *
 * @property string $surveyls_email_invite_subj
 * @property string $surveyls_email_invite
 * @property string $surveyls_email_remind_subj
 * @property string $surveyls_email_remind
 * @property string $surveyls_email_register
 * @property string $surveyls_email_register_subj
 * @property string $surveyls_email_confirm_subj
 * @property string $surveyls_email_confirm
 *
 * @property integer $surveyls_dateformat
 * @property string $surveyls_attributecaptions
 *
 * @property string $email_admin_notification_subj
 * @property string $email_admin_notification
 * @property string $email_admin_responses_subj
 * @property string $email_admin_responses
 *
 * @property integer $surveyls_numberformat
 * @property string $attatchments
 *
 */
class SurveyLanguageSetting extends LSActiveRecord
{
    /**
     * Returns the table's name
     *
     * @access public
     * @return string
     */
    public function tableName()
    {
        return '{{surveys_languagesettings}}';
    }

    /**
     * Returns the table's primary key
     *
     * @access public
     * @return string[]
     */
    public function primaryKey()
    {
        return array('surveyls_survey_id', 'surveyls_language');
    }

    /**
     * Returns the static model of Settings table
     *
     * @static
     * @access public
     * @param string $class
     * @return SurveyLanguageSetting
     */
    public static function model($class = __CLASS__)
    {
        return parent::model($class);
    }

    /**
     * Returns the relations of this model
     *
     * @access public
     * @return array
     */
    public function relations()
    {
        $alias = $this->getTableAlias();
        return array(
            'survey' => array(self::BELONGS_TO, 'Survey', '', 'on' => "$alias.surveyls_survey_id = survey.sid"),
            'owner' => array(self::BELONGS_TO, 'User', '', 'on' => 'survey.owner_id = owner.uid'),
        );
    }


    /**
    * Returns this model's validation rules
    *
    */
    public function rules()
    {
        return array(
            array('surveyls_email_invite_subj','lsdefault'),
            array('surveyls_email_invite','lsdefault'),
            array('surveyls_email_remind_subj','lsdefault'),
            array('surveyls_email_remind','lsdefault'),
            array('surveyls_email_confirm_subj','lsdefault'),
            array('surveyls_email_confirm','lsdefault'),
            array('surveyls_email_register_subj','lsdefault'),
            array('surveyls_email_register','lsdefault'),
            array('email_admin_notification_subj','lsdefault'),
            array('email_admin_notification','lsdefault'),
            array('email_admin_responses_subj','lsdefault'),
            array('email_admin_responses','lsdefault'),

            array('surveyls_email_invite_subj','LSYii_Validators'),
            array('surveyls_email_invite','LSYii_Validators'),
            array('surveyls_email_remind_subj','LSYii_Validators'),
            array('surveyls_email_remind','LSYii_Validators'),
            array('surveyls_email_confirm_subj','LSYii_Validators'),
            array('surveyls_email_confirm','LSYii_Validators'),
            array('surveyls_email_register_subj','LSYii_Validators'),
            array('surveyls_email_register','LSYii_Validators'),
            array('email_admin_notification_subj','LSYii_Validators'),
            array('email_admin_notification','LSYii_Validators'),
            array('email_admin_responses_subj','LSYii_Validators'),
            array('email_admin_responses','LSYii_Validators'),

            array('surveyls_title','LSYii_Validators'),
            array('surveyls_description','LSYii_Validators'),
            array('surveyls_welcometext','LSYii_Validators'),
            array('surveyls_endtext','LSYii_Validators'),
            array('surveyls_url','LSYii_Validators','isUrl'=>true),
            array('surveyls_urldescription','LSYii_Validators'),

            array('surveyls_dateformat', 'numerical', 'integerOnly'=>true, 'min'=>'1', 'max'=>'12', 'allowEmpty'=>true),
            array('surveyls_numberformat', 'numerical', 'integerOnly'=>true, 'min'=>'0', 'max'=>'1', 'allowEmpty'=>true),
        );
    }

    /**
    * Defines the customs validation rule lsdefault
    *
    * @param mixed $attribute
    * @param mixed $params
    */
    public function lsdefault($attribute,$params)
    {
        $oSurvey=Survey::model()->findByPk($this->surveyls_survey_id);
        $sEmailFormat=$oSurvey->htmlemail=='Y'?'html':'';
        $aDefaultTexts=templateDefaultTexts($this->surveyls_language,'unescaped', $sEmailFormat);

         $aDefaultTextData=array('surveyls_email_invite_subj' => $aDefaultTexts['invitation_subject'],
                        'surveyls_email_invite' => $aDefaultTexts['invitation'],
                        'surveyls_email_remind_subj' => $aDefaultTexts['reminder_subject'],
                        'surveyls_email_remind' => $aDefaultTexts['reminder'],
                        'surveyls_email_confirm_subj' => $aDefaultTexts['confirmation_subject'],
                        'surveyls_email_confirm' => $aDefaultTexts['confirmation'],
                        'surveyls_email_register_subj' => $aDefaultTexts['registration_subject'],
                        'surveyls_email_register' => $aDefaultTexts['registration'],
                        'email_admin_notification_subj' => $aDefaultTexts['admin_notification_subject'],
                        'email_admin_notification' => $aDefaultTexts['admin_notification'],
                        'email_admin_responses_subj' => $aDefaultTexts['admin_detailed_notification_subject'],
                        'email_admin_responses' => $aDefaultTexts['admin_detailed_notification']);
        if ($sEmailFormat == "html")
        {
            $aDefaultTextData['admin_detailed_notification']=$aDefaultTexts['admin_detailed_notification_css'].$aDefaultTexts['admin_detailed_notification'];
        }

         if (empty($this->$attribute)) $this->$attribute=$aDefaultTextData[$attribute];
    }


    /**
     * Returns the token's captions
     *
     * @access public
     * @return array
     */
    public function getAttributeCaptions()
    {
        $captions = @json_decode($this->surveyls_attributecaptions,true);
        return $captions !== false ? $captions : array();
    }

    function getAllRecords($condition=FALSE, $return_query = TRUE)
    {
        $query = Yii::app()->db->createCommand()->select('*')->from('{{surveys_languagesettings}}');
        if ($condition != FALSE)
        {
            $query->where($condition);
        }
        return ( $return_query ) ? $query->queryAll() : $query;
    }

    function getDateFormat($surveyid,$languagecode)
    {
        return Yii::app()->db->createCommand()->select('surveyls_dateformat')
            ->from('{{surveys_languagesettings}}')
            ->join('{{surveys}}','{{surveys}}.sid = {{surveys_languagesettings}}.surveyls_survey_id AND surveyls_survey_id = :surveyid')
            ->where('surveyls_language = :langcode')
            ->bindParam(":langcode", $languagecode, PDO::PARAM_STR)
            ->bindParam(":surveyid", $surveyid, PDO::PARAM_INT)
            ->queryScalar();
    }

    function getAllSurveys($hasPermission = FALSE)
    {
        $this->db->select('a.*, surveyls_title, surveyls_description, surveyls_welcometext, surveyls_url');
        $this->db->from('surveys AS a');
        $this->db->join('surveys_languagesettings','surveyls_survey_id=a.sid AND surveyls_language=a.language');

        if ($hasPermission)
        {
            $this->db->where('a.sid IN (SELECT sid FROM {{permissions}} WHERE uid=:uid AND permission=\'survey\' and read_p=1) ')->bindParam(":uid", $this->session->userdata("loginID"), PDO::PARAM_INT);
        }
        $this->db->order_by('active DESC, surveyls_title');
        return $this->db->get();
    }

    function getAllData($sid,$lcode)
    {
        $query = 'SELECT * FROM {{surveys}}, {{surveys_languagesettings}} WHERE sid=? AND surveyls_survey_id=? AND surveyls_language=?';
        return $this->db->query($query, array($sid, $sid, $lcode));
    }

    function insertNewSurvey($data)
    {
        return $this->insertSomeRecords($data);
    }

    /**
     * Updates a single record identified by $condition with the
     * key/value pairs in the $data array.
     *
     * @param type $data
     * @param type $condition
     * @param type $xssfiltering
     * @return boolean
     */
    function updateRecord($data,$condition='', $xssfiltering = false)
    {
        $record = $this->findByPk($condition);
        foreach ($data as $key => $value)
        {
            $record->$key = $value;
        }
        $record->save($xssfiltering);

        return true;
    }

    function insertSomeRecords($data)
    {
        $lang = new self;
        foreach ($data as $k => $v)
            $lang->$k = $v;
        return $lang->save();
    }
}
