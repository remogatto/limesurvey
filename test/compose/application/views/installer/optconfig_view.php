<div class="row">
    <div class="col-md-3">
        <?php $this->renderPartial('/installer/sidebar_view', compact('progressValue', 'classesForStep')); ?>
    </div>
    <div class="col-md-9">
    <h2><?php echo $title; ?></h2>
    <legend><?php echo $descp; ?></legend>
    <?php if (isset($confirmation)) echo "<div class='alert alert-success'>".$confirmation."</div>"; ?>
    <div style="color:red; font-size:12px;">
        <?php echo CHtml::errorSummary($model, null, null, array('class' => 'alert alert-danger')); ?>
    </div>
    <?php  ?>
    <?php echo CHtml::beginForm($this->createUrl('installer/optional'), 'post', array('class' => 'form-horizontal')); ?>
    <div class='form-group'>
        <div class='col-sm-12'>
            <i class='fa fa-info-circle'></i><?php eT("You can leave these settings blank and change them later"); ?>
        </div>
    </div>

    <?php
        $rows = array();
        $rows[] = array(
            'label' => CHtml::activeLabelEx($model, 'adminLoginName', array('class' => 'control-label col-sm-2', 'label' => gT("Admin login name"), 'autofocus' => 'autofocus')),
            'description' => gT("This will be the userid by which admin of board will login."),
            'control' => CHtml::activeTextField($model, 'adminLoginName', array('class' => 'form-control'))
        );
        $rows[] = array(
            'label' => CHtml::activeLabelEx($model, 'adminLoginPwd', array('class' => 'control-label col-sm-2', 'label' => gT("Admin login password"))),
            'description' => gT("This will be the password of admin user."),
            'control' => CHtml::activePasswordField($model, 'adminLoginPwd', array('class' => 'form-control'))
        );
        $rows[] = array(
            'label' => CHtml::activeLabelEx($model, 'confirmPwd', array('class' => 'control-label col-sm-2', 'label' => gT("Confirm your admin password"))),
            'control' => CHtml::activePasswordField($model, 'confirmPwd', array('class' => 'form-control'))
        );
        $rows[] = array(
            'label' => CHtml::activeLabelEx($model, 'adminName', array('class' => 'control-label col-sm-2', 'label' => gT("Administrator name"))),
            'description' => gT("This is the default name of the site administrator and used for system messages and contact options."),
            'control' => CHtml::activeTextField($model, 'adminName', array('class' => 'form-control'))
        );
        $rows[] = array(
            'label' => CHtml::activeLabelEx($model, 'adminEmail', array('class' => 'control-label col-sm-2', 'label' => gT("Administrator email"))),
            'description' => gT("This is the default email address of the site administrator and used for system messages, contact options and default bounce email."),
            'control' => CHtml::activeTextField($model, 'adminEmail', array('class' => 'form-control'))
        );
        $rows[] = array(
            'label' => CHtml::activeLabelEx($model, 'siteName', array('class' => 'control-label col-sm-2', 'label' => gT("Site name"))),
            'description' => gT("This name will appear in the survey list overview and in the administration header."),
            'control' => CHtml::activeTextField($model, 'siteName', array('class' => 'form-control'))
        );
        foreach(getLanguageData(true, Yii::app()->session['installerLang']) as $langkey => $languagekind)
        {
            $languages[$langkey] = sprintf('%s - %s', $languagekind['nativedescription'], $languagekind['description']);
        }

        $rows[] = array(
            'label' => CHtml::activeLabelEx($model, 'surveylang', array('class' => 'control-label col-sm-2', 'label' => gT("Default language"))),
            'description' => gT("This will be your default language."),
            'control' => CHtml::activeDropDownList($model, 'surveylang', $languages, array('style' => '', 'class'=>'form-control', 'encode' => false, 'options'=>array('en' => array('selected' => true))))
        );

        foreach ($rows as $row)
        {
            echo CHtml::openTag('div', array('class' => 'form-group'));
                echo $row['label'];

                echo CHtml::openTag('div', array('class' => 'col-sm-5'));
                echo $row['control'];
                if (isset($row['description']))
                {
                    echo CHtml::tag('div', array('class' => 'help-block'), $row['description']);
                }
                echo CHtml::closeTag('div');
            echo CHtml::closeTag('div');
        }
    ?>
        <div class="row navigator">
            <div class="col-md-4">
                <input class="btn btn-default" type="button" value="<?php eT("Previous"); ?>" onclick="javascript: window.open('<?php echo $this->createUrl("installer/welcome"); ?>', '_top')" />
            </div>
            <div class="col-md-4"></div>
            <div class="col-md-4">
                <?php echo CHtml::submitButton(gT("Next",'unescaped'), array('class' => 'btn btn-default')); ?>
            </div>
        </div>

    <?php echo CHtml::endForm(); ?>
    </div>
</div>
