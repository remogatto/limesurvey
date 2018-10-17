<div class="col-xs-12">
    <h3 class="pagetitle row">
        <div class="col-xs-8 ">
            <?php eT("Central participant management"); ?>
        </div>
        <div class="col-xs-4 text-right">
            <button class="btn btn-default" id="addParticipantToCPP">
                <i class="fa fa-plus-circle text-success"></i> 
                &nbsp;
                <?php eT("Add new participant"); ?>
            </button>
        </div>
    </h3>
<div class="row" style="margin-bottom: 100px">
  <div class="container-fluid">
        <?php 
        $hiddenFilterValues = "";
        if($searchcondition)
        {
            echo "<div class='container-fluid' id='ParticipantFilters'>" 
                    . "<div class='row'>"
                        . "<div class='col-xs-12'>"
                                . gT("Active filters:")
                            . "<span class=''>&nbsp;&nbsp;</span>"
                            . "<button id='removeAllFilters' class='btn btn-warning btn-xs'>".gT("Remove filters")."</button>"
                        . "</div>"
                    . "</div>";
            $i=0;
            $iNumberOfConditions = (count($searchcondition)+1)/4;
            while ($i < $iNumberOfConditions)
            {
                $sFieldname=$searchcondition[$i*4];
                $sOperator=$searchcondition[($i*4)+1];
                switch($sOperator)
                    {
                        case 'equal':
                            $operator= gT('equals');
                            break;
                        case 'contains':
                        case 'beginswith':
                            $operator=gT("is like");
                            break;
                        case 'notequal':
                        case 'notcontains':
                            $operator=gT("is not like");
                            break;
                        case 'greaterthan':
                            $operator=gT("is greater than");
                            break;
                        case 'lessthan':
                            $operator=gT("is lesser than");
                            break;
                    }
                $sValue=$searchcondition[($i*4)+2];
                
                    echo "<div class='row'>"
                        . "<div class='col-xs-12'>"
                           ." ".$model->getAttributeLabel($sFieldname)." ".$operator." ".$sValue
                        . "</div>"
                    . "</div>";
                $i++;
            }
            echo "</div>";
        }
            
        ?>
    <div class="row">
        <?php
            echo "<input type='hidden' id='searchcondition' name='searchcondition[]' value='".join("||",$searchcondition)."' />";
            $this->widget('bootstrap.widgets.TbGridView', array(
                'id' => 'list_central_participants',
                'emptyText'=>gT('No participants found.'),
                'itemsCssClass' => 'table table-striped items',
                'dataProvider' => $model->search(),
                'columns' => $model->columns,
                'rowHtmlOptionsExpression' => '["data-participant_id" => $data->id]',
                'filter'=>$model,
                'htmlOptions' => array('class'=> 'table-responsive'),
                'itemsCssClass' => 'table table-responsive table-striped',
                'afterAjaxUpdate' => 'LS.CPDB.bindButtons',
                'ajaxType' => 'POST',
                'beforeAjaxUpdate' => 'insertSearchCondition',
                'template'  => "{items}\n<div id='tokenListPager'><div class=\"col-sm-4\" id=\"massive-action-container\">$massiveAction</div><div class=\"col-sm-4 pager-container \">{pager}</div><div class=\"col-sm-4 summary-container\">{summary}</div></div>",
                'summaryText'   => gT('Displaying {start}-{end} of {count} result(s).').' '. sprintf(gT('%s rows per page'),
                    CHtml::dropDownList(
                        'pageSizeParticipantView',
                        Yii::app()->user->getState('pageSizeParticipantView', Yii::app()->params['defaultPageSize']),
                        Yii::app()->params['pageSizeOptionsTokens'],
                        array('class'=>'changePageSize form-control', 'style'=>'display: inline; width: auto')
                    )
                ),
            ));
            ?>
    </div>
  </div>
</div>
<span id="locator" data-location="participants">&nbsp;</span>
