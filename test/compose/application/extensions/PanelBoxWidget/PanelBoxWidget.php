<?php

class PanelBoxWidget extends CWidget
{
    public $fromDb = false; // If set to 1, the widget will look for the box definition inside the database
    public $dbPosition = 1; // Id of the box in the database
    public $position; // Position of the box in the list
    public $url;
    public $title;
    public $ico;
    public $description;
    public $usergroup;
    public $offset = 3;
    public $display = 'singlebox';
    public $boxesbyrow = 3;
    public $external = false;

    public function run()
    {
        if ($this->display == 'singlebox') {
            if ($this->fromDb) {
                $this->setValuesFromDb();
            }

            return $this->renderBox();
        } elseif ($this->display = 'allboxesinrows') {
            return $this->renderRows();
        }
    }

    public function getBoxes()
    {
        $boxes = Boxes::model()->findAll(array('order' => 'position ASC'));
        return $boxes;
    }

    protected function setValuesFromDb()
    {
        $box = Boxes::model()->find(array(
            'condition' => 'position=:positionId',
            'params' => array(':positionId' => $this->dbPosition)
        ));
        if ($box) {
            $this->position = $box->position;
            if (!preg_match("/^(http|https)/", $box->url)) {
                $this->url = Yii::app()->createUrl($box->url);
            } else {
                $this->url = $box->url;
                $this->external = true;
            }
            $this->title = $box->title;
            $this->ico = $box->ico;
            $this->description = $box->desc;
            $this->usergroup = $box->usergroup;
        } else {
            $this->position = '1';
            $this->url = '';
            $this->title = gT('Error');
            $this->description = gT('Unknown box ID!');
        }
    }

    /**
     * Render a single box
     */
    protected function renderBox()
    {
        if (self::canSeeBox()) {
            $offset = ($this->offset != '') ? 'col-sm-offset-1 col-lg-offset-' . $this->offset : '';

            $this->render('box', array(
                'position' => $this->position,
                'offset' => $offset,
                'url' => $this->url,
                'title' => $this->title,
                'ico' => $this->ico,
                'description' => $this->description,
                'external' => $this->external,
            ));
        }
    }

    /**
     * Render all boxes in row
     */
    protected function renderRows()
    {
        // We get all the boxes in the database
        $boxes = self::getBoxes();
        $boxcount = 0;
        $bIsRowOpened = false;
        foreach ($boxes as $box) {

            $canSeeBox = self::canSeeBox($box);
            if ($canSeeBox) {
                $boxcount = $boxcount + 1;
            }

            // It's the first box to show, we must display row header, and have an offset
            if ($boxcount == 1 && $canSeeBox) {


                $this->render('row_header');
                $bIsRowOpened = true;
                $this->controller->widget('ext.PanelBoxWidget.PanelBoxWidget', array(
                    'display' => 'singlebox',
                    'fromDb' => true,
                    'dbPosition' => $box->position,
                    'offset' => $this->offset,
                ));
            } elseif ($canSeeBox) {
                $this->controller->widget('ext.PanelBoxWidget.PanelBoxWidget', array(
                    'display' => 'singlebox',
                    'fromDb' => true,
                    'dbPosition' => $box->position,
                    'offset' => '',
                ));
            }

            // If it is the last box, we should close the box
            if ($boxcount == $this->boxesbyrow) {
                $this->render('row_footer');
                $boxcount = 0;
                $bIsRowOpened = false;
            }
        }

        // If the last row has not been closed, we close it
        if ($bIsRowOpened == true) {
            $this->render('row_footer');
        }
    }

    protected function canSeeBox($box = '')
    {
        $box = ($box == '') ? $this : $box;
        if ($box->usergroup == '-1') {
            return true;
        } // If the usergroup is not set, or set to -2, only admin can see the box
        elseif (empty($box->usergroup) || $box->usergroup == '-2') {
            if (Permission::model()->hasGlobalPermission('superadmin', 'read') ? 1 : 0) {
                return true;
            } else {
                return false;
            }
        } // If usergroup is set to -3, nobody can see the box
        elseif ($box->usergroup == '-3') {
            return false;
        } // If usegroup is set and exist, if the user belong to it, he can see the box
        else {
            $oUsergroup = UserGroup::model()->findByPk($box->usergroup);

            // The group doesn't exist anymore, so only admin can see it
            if (!is_object($oUsergroup)) {
                if (Permission::model()->hasGlobalPermission('superadmin', 'read') ? 1 : 0) {
                    return true;
                } else {
                    return false;
                }
            }

            if (Yii::app()->user->isInUserGroup($box->usergroup)) {
                return true;
            }
        }
    }

}
