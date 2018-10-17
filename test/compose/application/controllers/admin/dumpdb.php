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
/**
 * Dump Database
 *
 * @package LimeSurvey
 * @copyright 2011
  * @access public
 */
class Dumpdb extends Survey_Common_Action {

    function __construct($controller, $id)
    {
        parent::__construct($controller, $id);

        if (!Permission::model()->hasGlobalPermission('superadmin','read'))
        {
            die();
        }

        if (!in_array(Yii::app()->db->getDriverName(), array('mysql', 'mysqli')) || Yii::app()->getConfig('demoMode') == true)
        {
            die(gT('This feature is only available for MySQL databases.'));
        }
    }

    /**
     * Base function
     *
     * This functions receives the request to generate a dump file for the
     * database and does so! Only superadmins are allowed to do this!
     */
    public function index()
    {
        Yii::app()->loadHelper("admin/backupdb");
        $sDbName=_getDbName();
        $sFileName = 'LimeSurvey_'.$sDbName.'_dump_'.dateShift(date('Y-m-d H:i:s'), 'Y-m-d', Yii::app()->getConfig('timeadjust')).'.sql';
        $this->_outputHeaders($sFileName);
        outputDatabase();
        exit;
    }


    /**
     * Send the headers so that it is shown as a download
     * @param string $sFileName
     */
    private function _outputHeaders($sFileName)
    {
        header('Content-type: application/octet-stream');
        header('Content-Disposition: attachment; filename='.$sFileName);
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    }

}