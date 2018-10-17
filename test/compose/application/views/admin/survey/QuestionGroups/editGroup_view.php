<?php
    echo PrepareEditorScript(false, $this);
    $count = 0;
?>
<div id='edit-group' class='side-body <?php echo getSideBodyClass(false); ?>'>
    <div class="row">
        <div class="col-sm-12 content-right">
            <?php $this->renderPartial('/admin/survey/breadcrumb', array('oQuestionGroup'=>$oQuestionGroup, 'active'=>gT("Edit Group"))); ?>
            <h3><?php eT("Edit Group"); ?></h3>
            <ul class="nav nav-tabs" id="edit-group-language-selection">
                <?php foreach ($tabtitles as $i=>$eachtitle):?>
                    <li role="presentation" class="<?php if($count==0) {echo "active"; $count++;}?>">
                        <a data-toggle="tab" href="#editgrp_<?php echo $i;?>">
                            <?php echo $eachtitle;?>
                        </a>
                    </li>
                <?php endforeach;?>
            </ul>
        </div>
        <div class="row">
            <div class="col-sm-12">
                <?php echo CHtml::form(array("admin/questiongroups/sa/update/gid/{$gid}"), 'post', array('id'=>'frmeditgroup', 'name'=>'frmeditgroup', 'class'=>'form30 form-horizontal')); ?>

                    <div class="tab-content">

                        <?php foreach ($tabtitles as $i=>$eachtitle):?>
                            <div id="editgrp_<?php echo $i;?>" class="tab-pane fade in <?php if($count==1) {echo "active"; $count++;}?> center-box">

                                <div class="form-group">
                                    <label class="control-label col-sm-2" id="question-group-title"><?php eT("Title:"); ?></label>
                                    <div class="col-sm-3">
                                        <?php echo CHtml::textField("group_name_{$aGroupData[$i]['language']}",$aGroupData[$i]['group_name'],array('class'=>'form-control','size'=>"80",'maxlength'=>'100','id'=>"group_name_{$aGroupData[$i]['language']}")); ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label class="col-sm-2 control-label" for="description_<?php echo $aGroupData[$i]['language']; ?>"><?php eT("Description:"); ?></label>
                                    <div class="col-sm-5">
                                        <div class="htmleditor input-group">
                                            <?php echo CHtml::textArea("description_{$aGroupData[$i]['language']}",$aGroupData[$i]['description'],array('class'=>'form-control','cols'=>'60','rows'=>'8','id'=>"description_{$aGroupData[$i]['language']}")); ?>
                                            <?php echo getEditor("group-desc","description_".$aGroupData[$i]['language'], "[".gT("Description:", "js")."](".$aGroupData[$i]['language'].")",$surveyid,$gid,'',$action); ?>
                                        </div>
                                    </div>

                                </div>

                            </div>

                        <?php endforeach; ?>

                    </div>

                    <div class="form-group">
                        <label class="control-label col-sm-2" id="randomization-group"><?php eT("Randomization group:"); ?></label>
                        <div class="col-sm-3">
                            <?php echo CHtml::textField("randomization_group",$aGroupData[$aBaseLanguage]['randomization_group'],array('class'=>'form-control','size'=>"20",'maxlength'=>'20','id'=>"randomization_group")); ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="control-label col-sm-2" id="randomization-group"><?php eT("Relevance equation:"); ?></label>
                        <div class="col-sm-3">
                            <?php echo CHtml::textArea("grelevance",$aGroupData[$aBaseLanguage]['grelevance'],array('class'=>'form-control','cols'=>'20','rows'=>'1','id'=>"grelevance")); ?>
                        </div>
                    </div>

                    <input type="submit" class="btn btn-primary hidden" value="Save" role="button" aria-disabled="false">
                </form>
            </div>
        </div>
    </div>
</div>
