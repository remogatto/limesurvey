<?php
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
class Load_answers {

    function run($args) {
        extract($args);
        $redata = compact(array_keys(get_defined_vars()));
        $sTemplatePath=$_SESSION['survey_'.$surveyid]['templatepath'];
        sendCacheHeaders();
         doHeader();

        $oTemplate = Template::model()->getInstance(null, $surveyid);

        echo templatereplace(file_get_contents($oTemplate->viewPath."startpage.pstpl"),array(),$redata);

        echo "\n\n<!-- JAVASCRIPT FOR CONDITIONAL QUESTIONS -->\n"
        ."\t<script type='text/javascript'>\n"
        ."function checkconditions(value, name, type, evt_type)\n"
        ."\t{\n"
        ."\t}\n"
        ."\t</script>\n\n";

        echo CHtml::form(array("/survey/index","sid"=>$surveyid), 'post')."\n";
        echo templatereplace(file_get_contents($oTemplate->viewPath."load.pstpl"),array(),$redata);

        //PRESENT OPTIONS SCREEN (Replace with Template Later)
        //END
        echo "<input type='hidden' name='loadall' value='reload' />\n";
        if (isset($clienttoken) && $clienttoken != "")
        {
            echo CHtml::hiddenField('token',$clienttoken);
        }
        echo "</form>";

        echo templatereplace(file_get_contents($oTemplate->viewPath."endpage.pstpl"),array(),$redata);
        doFooter($surveyid);
        exit;


    }
}
