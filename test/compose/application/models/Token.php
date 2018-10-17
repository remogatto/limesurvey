<?php
    /**
     *
     * For code completion we add the available scenario's here
     * Attributes
     * @property int      $tid
     * @property string   $firstname
     * @property string   $lastname
     * @property string   $email
     * @property string   $emailstatus
     * @property string   $token
     * @property string   $language
     * @property string   $blacklisted
     * @property string   $sent
     * @property string   $remindersent
     * @property int      $remindercount
     * @property string   $completed
     * @property int      $usesleft
     * @property DateTime $validfrom
     * @property DateTime $validuntil
     *
     * Relations
     * @property Survey $survey The survey this token belongs to.
     *
     * Scopes
     * @method Token incomplete() incomplete() Select only uncompleted tokens
     * @method Token usable() usable() Select usable tokens: valid daterange and userleft > 0
     *
     */
    abstract class Token extends Dynamic
    {

        public function attributeLabels() {
            $labels = array(
                'tid' => gT('Token ID'),
                'partcipant' => gT('Participant ID'),
                'firstname' => gT('First name'),
                'lastname' => gT('Last name'),
                'email' => gT('Email address'),
                'emailstatus' => gT('Email status'),
                'token' => gT('Token'),
                'language' => gT('Language code'),
                'blacklisted' => gT('Blacklisted'),
                'sent' => gT('Invitation sent date'),
                'remindersent' => gT('Last reminder sent date'),
                'remindercount' =>gT('Total numbers of sent reminders'),
                'completed' => gT('Completed'),
                'usesleft' => gT('Uses left'),
                'validfrom' => gT('Valid from'),
                'validuntil' => gT('Valid until'),
            );
            foreach (decodeTokenAttributes($this->survey->attributedescriptions) as $key => $info)
            {
                $labels[$key] = $info['description'];
            }
            return $labels;
        }

        public function beforeDelete() {
            $result = parent::beforeDelete();
            if ($result && isset($this->surveylink))
            {
                if (!$this->surveylink->delete())
                {
                    throw new CException('Could not delete survey link. Token was not deleted.');
                }
                return true;
            }
            return $result;
        }

        public static function createTable($surveyId, array $extraFields  = array())
        {
            $surveyId=intval($surveyId);
            // Specify case sensitive collations for the token
            $sCollation='';
            if  (Yii::app()->db->driverName=='mysql' || Yii::app()->db->driverName=='mysqli'){
                $sCollation="COLLATE 'utf8mb4_bin'";
            }
            if  (Yii::app()->db->driverName=='sqlsrv' || Yii::app()->db->driverName=='dblib' || Yii::app()->db->driverName=='mssql'){
                $sCollation="COLLATE SQL_Latin1_General_CP1_CS_AS";
            }
            $fields = array(
                'tid' => 'pk',
                'participant_id' => 'string(50)',
                'firstname' => 'string(150)',
                'lastname' => 'string(150)',
                'email' => 'text',
                'emailstatus' => 'text',
                'token' => "string(35) {$sCollation}",
                'language' => 'string(25)',
                'blacklisted' => 'string(17)',
                'sent' => "string(17) DEFAULT 'N'",
                'remindersent' => "string(17) DEFAULT 'N'",
                'remindercount' => 'integer DEFAULT 0',
                'completed' => "string(17) DEFAULT 'N'",
                'usesleft' => 'integer DEFAULT 1',
                'validfrom' => 'datetime',
                'validuntil' => 'datetime',
                'mpid' => 'integer'
            );
            foreach ($extraFields as $extraField) {
                $fields[$extraField] = 'text';
            }

            // create fields for the custom token attributes associated with this survey
            $tokenattributefieldnames = Survey::model()->findByPk($surveyId)->getTokenAttributes();
            foreach($tokenattributefieldnames as $attrname=>$attrdetails)
            {
                if (!isset($fields[$attrname])) {
                    $fields[$attrname] = 'text';
                }
            }

            $db = \Yii::app()->db;
            $sTableName="{{tokens_{$surveyId}}}";

            $db->createCommand()->createTable($sTableName, $fields);
            /**
             * @todo Check if this random component in the index name is needed.
             * As far as I (sam) know index names need only be unique per table.
             */
            $db->createCommand()->createIndex("idx_token_token_{$surveyId}_".rand(1,50000),  $sTableName,'token');

            // Refresh schema cache just in case the table existed in the past, and return if table exist
            return $db->schema->getTable($sTableName, true);
        }
        public function findByToken($token)
        {
            return $this->findByAttributes(array(
                'token' => $token
            ));
        }
        /**
         * Generates a token for this object.
         * @throws CHttpException
         */
        public function generateToken()
        {
            $iTokenLength = $this->survey->tokenlength;
            $this->token = $this::generateRandomToken($iTokenLength);
            $counter = 0;
            while (!$this->validate(array('token')))
            {
                $this->token = $this::generateRandomToken($iTokenLength);
                $counter++;
                // This is extremely unlikely.
                if ($counter > 10)
                {
                    throw new CHttpException(500, 'Failed to create unique token in 10 attempts.');
                }
            }
        }

        /**
        * Creates a random token string without special characters
        *
        * @param mixed $iTokenLength
        */
        public static function generateRandomToken($iTokenLength){
            return str_replace(array('~','_'),array('a','z'),Yii::app()->securityManager->generateRandomString($iTokenLength));
        }

        /**
         * Sanitize token show to the user (replace sanitize_helper sanitize_token)
         * @param string token to sanitize
         * @return string sanitized token
         */
        public static function sanitizeToken($token)
        {
            // According to Yii doc : http://www.yiiframework.com/doc/api/1.1/CSecurityManager#generateRandomString-detail
            return preg_replace('/[^0-9a-zA-Z_~]/', '', $token);
        }
        /**
         * Generates a token for all token objects in this survey.
         * Syntax: Token::model(12345)->generateTokens();
         */
        public function generateTokens() {
            if ($this->scenario != '') {
                throw new \Exception("This function should only be called like: Token::model(12345)->generateTokens");
            }
            $surveyId = $this->dynamicId;
            $iTokenLength = isset($this->survey) && is_numeric($this->survey->tokenlength) ? $this->survey->tokenlength : 15;

            $tkresult = Yii::app()->db->createCommand("SELECT tid FROM {{tokens_{$surveyId}}} WHERE token IS NULL OR token=''")->queryAll();
            //Exit early if there are not empty tokens
            if (count($tkresult)===0) return array(0,0);

            //get token length from survey settings
            $tlrow = Survey::model()->findByAttributes(array("sid"=>$surveyId));

            //Add some criteria to select only the token field
            $criteria = $this->getDbCriteria();
            $criteria->select = 'token';
            $ntresult = $this->findAllAsArray($criteria);   //Use AsArray to skip active record creation
            // select all existing tokens
            foreach ($ntresult as $tkrow)
            {
                $existingtokens[$tkrow['token']] = true;
            }
            $newtokencount = 0;
            $invalidtokencount=0;
            foreach ($tkresult as $tkrow)
            {
                $bIsValidToken = false;
                while ($bIsValidToken == false && $invalidtokencount<50)
                {
                    $newtoken =$this::generateRandomToken($iTokenLength);
                    if (!isset($existingtokens[$newtoken]))
                    {
                        $existingtokens[$newtoken] = true;
                        $bIsValidToken = true;
                        $invalidtokencount=0;
                    }
                    else
                    {
                        $invalidtokencount ++;
                    }
                }
                if($bIsValidToken)
                {
                    $itresult = $this->updateByPk($tkrow['tid'], array('token' => $newtoken));
                    $newtokencount++;
                }
                else
                {
                    break;
                }
            }

            return array($newtokencount,count($tkresult));

        }
        /**
         *
         * @param mixed $className Either the classname or the survey id.
         * @return Token
         */
        public static function model($className = null) {
            return parent::model($className);
        }

        /**
         *
         * @param int $surveyId
         * @param string $scenario
         * @return Token Description
         */
        public static function create($surveyId, $scenario = 'insert') {
            return parent::create($surveyId, $scenario);
        }

        public function relations()
        {
            $result = array(
                'responses' => array(self::HAS_MANY, 'Response_' . $this->dynamicId, array('token' => 'token')),
                'survey' =>  array(self::BELONGS_TO, 'Survey', '', 'on' => "sid = {$this->dynamicId}"),
                'surveylink' => array(self::BELONGS_TO, 'SurveyLink', array('participant_id' => 'participant_id'), 'on' => "survey_id = {$this->dynamicId}")
            );
            return $result;
        }

        public function save($runValidation = true, $attributes = null)
        {
            $beforeTokenSave = new PluginEvent('beforeTokenSave');
            $beforeTokenSave->set('model',$this);
            $beforeTokenSave->set('iSurveyID',$this->dynamicId);
            App()->getPluginManager()->dispatchEvent($beforeTokenSave);
            return parent::save($runValidation, $attributes);
        }

        public function rules()
        {
            $aRules= array(
                array('token', 'unique', 'allowEmpty' => true),
                array('firstname','LSYii_Validators','except'=>'FinalSubmit'),
                array('lastname','LSYii_Validators','except'=>'FinalSubmit'),
                array(implode(',', $this->tableSchema->columnNames), 'safe'),
                array('remindercount','numerical', 'integerOnly'=>true,'allowEmpty'=>true),
                array('email','filter','filter'=>'trim'),
                array('email','LSYii_EmailIDNAValidator', 'allowEmpty'=>true, 'allowMultiple'=>true,'except'=>'allowinvalidemail'),
                array('usesleft','numerical', 'integerOnly'=>true,'allowEmpty'=>true),
                array('mpid','numerical', 'integerOnly'=>true,'allowEmpty'=>true),
                array('blacklisted', 'in','range'=>array('Y','N'), 'allowEmpty'=>true),
                array('emailstatus', 'default', 'value' => 'OK'),
            );
            foreach (decodeTokenAttributes($this->survey->attributedescriptions) as $key => $info)
            {
                 $aRules[]=array($key,'LSYii_Validators','except'=>'FinalSubmit');
            }
            return $aRules;
        }

        public function scopes()
        {
            $now = dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", Yii::app()->getConfig("timeadjust"));
            return array(
                'incomplete' => array(
                    'condition' => "completed = 'N'"
                ),
                'usable' => array(
                    'condition' => "COALESCE(validuntil, '$now') >= '$now' AND COALESCE(validfrom, '$now') <= '$now'"
                ),
                'editable' => array(
                    'condition' => "COALESCE(validuntil, '$now') >= '$now' AND COALESCE(validfrom, '$now') <= '$now'"
                ),
                'empty' => array(
                    'condition' => 'token is null or token = ""'
                )
            );
        }

        public function summary()
        {
            $criteria = $this->getDbCriteria();
            $criteria->select = array(
                "COUNT(*) as count",
                "COUNT(CASE WHEN (token IS NULL OR token='') THEN 1 ELSE NULL END) as invalid",
                "COUNT(CASE WHEN (sent!='N' AND sent<>'') THEN 1 ELSE NULL END) as sent",
                "COUNT(CASE WHEN (emailstatus LIKE 'OptOut%') THEN 1 ELSE NULL END) as optout",
                "COUNT(CASE WHEN (completed!='N' and completed<>'') THEN 1 ELSE NULL END) as completed",
                "COUNT(CASE WHEN (completed='Q') THEN 1 ELSE NULL END) as screenout",
            );
            $command = $this->getCommandBuilder()->createFindCommand($this->getTableSchema(),$criteria);
            return $command->queryRow();
        }

        public function tableName()
        {
            return '{{tokens_' . $this->dynamicId . '}}';
        }
    }

?>
