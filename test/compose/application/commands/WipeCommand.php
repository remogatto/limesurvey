<?php
/*
* LimeSurvey (tm)
* Copyright (C) 2011 The LimeSurvey Project Team / Carsten Schmitz
* All rights reserved.
* License: GNU/GPL License v2 or later, see LICENSE.php
* LimeSurvey is free software. This version may have been modified pursuant
* to the GNU General Public License, and as distributed it includes or
* is derivative of works licensed under the GNU General Public License or
* other free or open source software licenses.
* See COPYRIGHT.php for copyright notices and details.
*/
class WipeCommand extends CConsoleCommand
{

    public function run($sArgument)
    {
        if (isset($sArgument) && isset($sArgument[0]) && $sArgument[0]='yes')
        {

            Yii::import('application.helpers.common_helper', true);
            Yii::import('application.helpers.database_helper', true);

            $actquery="truncate table {{assessments}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{answers}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{conditions}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{defaultvalues}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{labels}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{labelsets}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{groups}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{questions}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{surveys}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{surveys_languagesettings}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="delete from {{permissions}} where uid<>1";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{quota}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{quota_members}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{quota_languagesettings}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{question_attributes}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{user_groups}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{user_in_groups}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{templates}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{participants}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{participant_attribute_names}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{participant_attribute_names_lang}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{participant_attribute_values}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{participant_shares}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{failed_login_attempts}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{saved_control}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="truncate table {{survey_links}}";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="delete from {{users}} where uid<>1";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="update {{users}} set lang='en'";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="update {{users}} set lang='auto'";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="delete from {{settings_global}} where stg_name LIKE 'last_question%'";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="delete from {{settings_global}} where stg_name LIKE 'last_survey%'";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="update {{users}} set email = 'test@domain.test', full_name='Administrator'";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="update {{settings_global}} set stg_value='' where stg_name='googleanalyticsapikey' or stg_name='googleMapsAPIKey' or stg_name='googletranslateapikey' or stg_name='ipInfoDbAPIKey' or stg_name='pdfheadertitle' or stg_name='pdfheaderstring'";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="update {{settings_global}} set stg_value='test@domain.test' where stg_name='siteadminbounce' or stg_name='siteadminemail'";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="update {{settings_global}} set stg_value='Administrator' where stg_name='siteadminname'";
            Yii::app()->db->createCommand($actquery)->execute();
            $actquery="update {{settings_global}} set stg_value='Sea_Green' where stg_name='admintheme'";
            Yii::app()->db->createCommand($actquery)->execute();

            $surveyidresult = dbGetTablesLike("tokens%");
            foreach ( $surveyidresult as $sv )
            {
                Yii::app()->db->createCommand("drop table ".$sv)->execute();
            }

            $surveyidresult = dbGetTablesLike("old\_%");
            foreach ( $surveyidresult as $sv )
            {
                Yii::app()->db->createCommand("drop table ".$sv)->execute();
            }

            $surveyidresult = dbGetTablesLike("survey\_%");
            foreach ( $surveyidresult as $sv )
            {
                if (strpos($sv, 'survey_links')===false && strpos($sv, 'survey_url_parameters')===false)
                    Yii::app()->db->createCommand("drop table ".$sv)->execute();
            }
            $sBaseUploadDir=dirname(dirname(dirname(__FILE__))).DIRECTORY_SEPARATOR.'upload';

            SureRemoveDir($sBaseUploadDir.DIRECTORY_SEPARATOR.'surveys',false);
            SureRemoveDir($sBaseUploadDir.DIRECTORY_SEPARATOR.'templates',false);
        }
        else
        {
            // TODO: a valid error process
            echo 'This CLI command wipes a LimeSurvey installation clean (including all user except for the user ID 1 and user-uploaded content). For security reasons this command can only started if you add the parameter \'yes\' to the command line.';
        }

    }


}


function SureRemoveDir($dir, $DeleteMe) {
    if(!$dh = @opendir($dir)) return;
    while (false !== ($obj = readdir($dh))) {
        if($obj=='.' || $obj=='..') continue;
        if (!@unlink($dir.'/'.$obj)) SureRemoveDir($dir.'/'.$obj, true);
    }
    closedir($dh);
    if ($DeleteMe){
        if (!@rmdir($dir))
        {
            echo "Error: could not delete ".$dir;
        }

    }
}
