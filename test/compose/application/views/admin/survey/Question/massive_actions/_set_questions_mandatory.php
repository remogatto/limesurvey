<?php
/**
 * Set question group and position modal body (parsed to massive action widget)
 */
?>
<form class="custom-modal-datas">
    <div id='MandatorySelection' class="form-group">
        <label class="col-sm-4 control-label"><?php eT("Mandatory:"); ?></label>
        <div class="col-sm-8">
            <!-- Todo : replace by direct use of bootstrap switch. See statistics -->
            <?php $this->widget(
                'yiiwheels.widgets.switch.WhSwitch',
                array(
                    'name' => 'mandatory',
                    'htmlOptions'=>array(
                        'class'=>'custom-data bootstrap-switch-boolean',
                        'uncheckValue'=>false,
                    ),


                'onLabel'=>gT('On'),
                'offLabel'=>gT('Off')));
            ?>

            <input type="hidden" name="sid" value="<?php echo $_GET['surveyid']; ?>" class="custom-data"/>
        </div>
    </div>
</form>
