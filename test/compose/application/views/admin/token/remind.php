<?php
/**
 * Send email reminder
 */
?>

<div class='side-body <?php echo getSideBodyClass(false); ?>'>
    <?php $this->renderPartial('/admin/survey/breadcrumb', array('oSurvey'=>$oSurvey, 'token'=>true, 'active'=>gT("Send email reminder"))); ?>
    <h3><?php eT("Send email reminder"); ?></h3>
    <div class="row">
        <div class="col-lg-12 content-right">
            <?php echo PrepareEditorScript(true, $this); ?>

            <?php if ($thissurvey['active'] != 'Y'):?>
                <?php if ($thissurvey[$baselang]['active'] != 'Y'): ?>
                    <div class="jumbotron message-box message-box-error">
                        <h2 class='text-warning'><?php eT('Warning!'); ?></h2>
                        <p class="lead text-warning">
                            <?php eT("This survey is not yet activated and so your participants won't be able to fill out the survey."); ?>
                        </p>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php echo CHtml::form(array("admin/tokens/sa/email/action/remind/surveyid/{$surveyid}"), 'post', array('id'=>'sendreminder', 'class'=>'form-horizontal')); ?>
            <div>
                <ul class="nav nav-tabs">
                    <?php
                    $c = true;
                    foreach ($surveylangs as $language)
                    {
                        //GET SURVEY DETAILS
                        echo '<li role="presentation"';

                        if ($c)
                        {
                            $c=false;
                            echo ' class="active"';
                        }

                        echo ' ><a data-toggle="tab" href="#'.$language.'">' . getLanguageNameFromCode($language, false);
                        if ($language == $baselang)
                        {
                            echo " (" . gT("Base language") . ")";
                        }
                        echo "</a></li>";
                    }
                    ?>
                </ul>


                    <div class="tab-content">

                    <?php
                    $c = true;
                    foreach ($surveylangs as $language)
                    {
                        $fieldsarray["{ADMINNAME}"] = $thissurvey['adminname'];
                        $fieldsarray["{ADMINEMAIL}"] = $thissurvey['adminemail'];
                        $fieldsarray["{SURVEYNAME}"] = $thissurvey[$language]['name'];
                        $fieldsarray["{SURVEYDESCRIPTION}"] = $thissurvey[$language]['description'];
                        $fieldsarray["{EXPIRY}"] = $thissurvey["expiry"];

                        $subject = Replacefields($thissurvey[$language]['email_remind_subj'], $fieldsarray, false);
                        $textarea = Replacefields($thissurvey[$language]['email_remind'], $fieldsarray, false);
                        if ($ishtml !== true)
                        {
                            $textarea = str_replace(array('<x>', '</x>'), array(''), $textarea); // ?????
                        }
                        ?>

                        <div id="<?php echo $language; ?>" class="tab-pane fade in <?php if ($c){$c=false;echo ' active';}?>">

                            <div class='form-group'>
                                <label class='control-label col-sm-2' for='from_<?php echo $language; ?>'><?php eT("From:"); ?></label>
                                <div class='col-sm-4'>
                                    <?php echo CHtml::textField("from_{$language}",$thissurvey[$baselang]['adminname']." <".$thissurvey[$baselang]['adminemail'].">",array('class' => 'form-control')); ?>
                                </div>
                            </div>

                            <div class='form-group'>
                                <label class='control-label col-sm-2' for='subject_<?php echo $language; ?>'><?php eT("Subject:"); ?></label>
                                <div class='col-sm-4'>
                                    <?php echo CHtml::textField("subject_{$language}",$subject,array('class' => 'form-control')); ?>
                                </div>
                            </div>

                            <div class='form-group'>
                                <label class='control-label col-sm-2' for='message_<?php echo $language; ?>'><?php eT("Message:"); ?></label>
                                <div class="htmleditor col-sm-6">
                                    <?php echo CHtml::textArea("message_{$language}",$textarea,array('cols'=>80,'rows'=>20, 'class' => 'form-control')); ?>
                                    <?php echo getEditor("email-reminder", "message_$language", "[" . gT("Reminder Email:", "js") . "](" . $language . ")", $surveyid, '', '', "tokens"); ?>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
                    <?php if (count($tokenids)>0): ?>
                        <div class='form-group'>
                            <label class='control-label col-sm-2'><?php eT("Send reminder to token ID(s):"); ?></label>
                            <div class='col-sm-4'>
                                <?php echo short_implode(", ", "-", (array) $tokenids); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class='form-group'>
                        <label class='control-label col-sm-2' for='bypassbademails'><?php eT("Bypass token with failing email addresses:"); ?></label>
                        <div class='col-sm-1'>
                            <?php
                            $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                                'name' => "bypassbademails",
                                'id'=>"bypassbademails",
                                'value' => '1',
                                'onLabel'=>gT('On'),
                                'offLabel' => gT('Off')));
                            ?>
                        </div>
                    </div>

                    <div class='form-group'>
                        <label class='control-label col-sm-2' for='minreminderdelay'><?php eT("Min days between reminders:"); ?></label>
                        <div class='col-sm-4'>
                            <?php echo CHtml::textField('minreminderdelay'); ?>
                        </div>
                    </div>

                    <div class='form-group'>

                        <label class='control-label col-sm-2' for='maxremindercount'><?php eT("Max reminders:"); ?></label>
                        <div class='col-sm-4'>
                            <?php echo CHtml::textField('maxremindercount'); ?>
                        </div>
                    </div>

                    <div class='form-group'>
                          <?php echo CHtml::label(gT("Bypass date control before sending email:"),'bypassdatecontrol', array('title'=>gt("If some tokens have a 'valid from' date set which is in the future, they will not be able to access the survey before that 'valid from' date."),'unescaped', 'class' => 'control-label col-sm-2')); ?>
                          <div class='col-sm-1'>
                          <?php
                            $this->widget('yiiwheels.widgets.switch.WhSwitch', array(
                                'name' => "bypassdatecontrol",
                                'id'=>"bypassdatecontrol",
                                'value' => '0',
                                'onLabel'=>gT('On'),
                                'offLabel' => gT('Off')));
                            ?>
                          </div>
                          <div class='col-sm-9'></div>
                    </div>

                    <div class='form-group'>
                        <div class='col-sm-2'></div>
                        <div class='col-sm-1'>
                            <?php echo CHtml::submitButton(gT("Send Reminders",'unescaped'), array('class'=>'btn btn-default')); ?>
                        </div>

                            <?php
                                echo CHtml::hiddenField('ok','absolutely');
                                echo CHtml::hiddenField('subaction','remind');
                                if (!empty($tokenids)) {
                                    echo CHtml::hiddenField('tokenids',implode('|', (array) $tokenids));
                                }
                            ?>
                    </div>
            </div>
        </form>
    </form>
</div>


<script>
    $( document ).ready(function(){
        $('#send-reminders-button').on('click', function(){
            $("#sendreminder").submit();
        })
    });
</script>
