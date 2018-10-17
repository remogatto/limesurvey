<?php

/**
 * @since 2017-09-20
 * @author Olle Härstedt
 */

?>

<div id='global-settings-storage'>
    <input type='hidden' name='global-settings-storage-url' value='<?php echo Yii::app()->createUrl('admin/globalsettings', array('sa' => 'getStorageData')); ?>' />
    <button id='global-settings-calculate-storage' class='btn btn-default '>
        <span class='fa fa-cogs'></span>&nbsp;
        <?php eT('Calculate storage'); ?>
    </button>
    <br/>
    <span class='text-muted'>
        <?php eT('Depending on the number of uploaded files, this might take some time.'); ?>
    </span>
</div>
