<?php

/* @var $this AdminController */
/* @var Survey $oSurvey */
/* @var CActiveDataProvider $oDataProvider Containing Quota objects*/
/* @var array $aEditUrls */
/* @var array $aDeleteUrls */
/* @var array $aQuotaItems */
/* @var integer $totalquotas */
/* @var integer $totalcompleted */
/* @var integer $iGridPageSize */
/* @var Quota $oQuota The last Quota as base for Massive edits */
/* @var QuotaLanguageSetting[] $aQuotaLanguageSettings The last Quota LanguageSettings */


?>
<!-- To update grid when pageSize is changed -->
<script type="text/javascript">
    $(document).ready(function() {
        jQuery(function($)
        {
            jQuery(document).on("change", '#pageSize', function()
            {
                $.fn.yiiGridView.update('quota-grid',{ data:{ pageSize: $(this).val() }});
            });
        });
    });
</script>

<div class='side-body <?php echo getSideBodyClass(false); ?>'>
    <div class="row">
        <div class="col-lg-12 content-right">
            <?php $this->renderPartial('/admin/survey/breadcrumb', array('oSurvey'=>$oSurvey, 'active'=> gT("Survey quotas"))); ?>
            <h3>
                <?php eT("Survey quotas");?>
            </h3>

            <?php if( isset($sShowError) ):?>
                <div class="alert alert-warning alert-dismissible" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <strong><?php eT("Quota could not be added!", 'js'); ?></strong><br/> <?php eT("It is missing a quota message for the following languages:", 'js'); ?><br/><?php echo $sShowError; ?>
                </div>
            <?php endif; ?>

            <?php if($oDataProvider->itemCount > 0):?>
            <!-- Grid -->
            <div class="row">
                <div class="col-sm-12 content-right">
                    <?php $this->widget('bootstrap.widgets.TbGridView', array(
                        'dataProvider' => $oDataProvider,
                        'id' => 'quota-grid',
                        'emptyText'=>gT('No quotas'),
                        'summaryText'=>gT('Displaying {start}-{end} of {count} result(s).').' '. sprintf(gT('%s rows per page'),
                                CHtml::dropDownList(
                                    'pageSize',
                                    $iGridPageSize,
                                    Yii::app()->params['pageSizeOptions'],
                                    array('class'=>'changePageSize form-control', 'style'=>'display: inline; width: auto'))),

                        'columns' => array(
                            array(
                                'id'=>'id',
                                'class'=>'CCheckBoxColumn',
                                'selectableRows' => '100',
                                'htmlOptions'=>array('style'=>'vertical-align:top'),
                            ),
                            array(
                                'name'=>gT('Quota members'),
                                'type'=>'raw',
                                'htmlOptions'=>array('style'=>'vertical-align:top'),
                                'value'=>function($oQuota) use($oSurvey,$aQuotaItems){
                                    /** @var Quota $oQuota */
                                    $out = '<p>'.$this->renderPartial('/admin/quotas/viewquotas_quota_members',
                                        array(
                                            'oSurvey'=>$oSurvey,
                                            'oQuota'=>$oQuota,
                                            'aQuotaItems'=>$aQuotaItems,
                                    )).'<p>';
                                    return $out;
                                },
                            ),
                            array(
                                'name'=>'completeCount',
                                'type'=>'raw',
                                'htmlOptions'=>array('style'=>'vertical-align:top'),
                                // 'value'=>function($oQuota)use($oSurvey){
                                //     $completerCount =getQuotaCompletedCount($oSurvey->sid, $oQuota->id);
                                //     $class = ($completerCount <= $oQuota->qlimit ? 'text-warning':null);
                                //     $span = CHtml::tag('span',array('class'=>$class),$completerCount);
                                //     return $span;
                                // },
                                'footer'=>$totalcompleted,
                            ),
                            array(
                                'name'=>'qlimit',
                                'htmlOptions'=>array('style'=>'vertical-align:top'),
                                'footer'=>$totalquotas,
                            ),
                            array(
                                'header'=>gT("Action"),
                                'value'=>function($oQuota)use($oSurvey,$aEditUrls,$aDeleteUrls,$aQuotaItems){
                                    /** @var Quota $oQuota */
                                    $this->renderPartial('/admin/quotas/viewquotas_quota_actions',
                                        array(
                                            'oSurvey'=>$oSurvey,
                                            'oQuota'=>$oQuota,
                                            'editUrl'=>$aEditUrls[$oQuota->getPrimaryKey()],
                                            'deleteUrl'=>$aDeleteUrls[$oQuota->getPrimaryKey()],
                                            'aQuotaItems'=>$aQuotaItems,
                                        ));
                                },
                                'headerHtmlOptions'=>array(
                                    'style'=>'text-align:right;',
                                ),
                                'htmlOptions'=>array(
                                    'align'=>'right',
                                    'style'=>'vertical-align:top',
                                ),
                            ),

                        ),
                        'itemsCssClass' =>'table-quotas table-striped table-condensed',
                        'ajaxUpdate' => true,
                    ));
                    ?>
                </div>
                <?php endif; ?>

                <?php if (Permission::model()->hasSurveyPermission($oSurvey->getPrimaryKey(), 'quotas','create')):?>
                    <?php if($oDataProvider->itemCount > 0):?>
                        <div class="pull-left">
                            <?php $this->renderPartial('/admin/quotas/viewquotas_massive_selector',
                                array(
                                    'oSurvey'=>$oSurvey,
                                    'oQuota'=>$oQuota,
                                    'aQuotaLanguageSettings'=>$aQuotaLanguageSettings,
                                ));?>
                        </div>
                    <?php endif; ?>
                    <div class="pull-right">
                        <?php echo CHtml::beginForm(array("admin/quotas/sa/newquota/surveyid/{$oSurvey->getPrimaryKey()}"), 'post'); ?>
                        <?php echo CHtml::hiddenField('sid',$oSurvey->getPrimaryKey());?>
                        <?php echo CHtml::hiddenField('action','quotas');?>
                        <?php echo CHtml::hiddenField('subaction','new_quota');?>
                        <input type="button" class="btn btn-default" value="<?php eT("Quick CSV report");?>" onClick="window.open('<?php echo $this->createUrl("admin/quotas/sa/index/surveyid/$surveyid/quickreport/y") ?>', '_top')" />                                                
                        <?php echo CHtml::submitButton(gT("Add new quota"),array(
                            'name'=>'submit',
                            'class'=>'quota_new btn btn-default',
                        ));?>
                        <?php echo CHtml::endForm();?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
