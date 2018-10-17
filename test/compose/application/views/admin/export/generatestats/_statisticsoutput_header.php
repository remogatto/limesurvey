<?php
/**
 * Statistic output
 *
 * @var $outputs
 * @var $bSum
 * @var $bAnswer
 * @var $nbcols
 */
?>
<!-- _statisticsoutput_header -->
<div class="col-lg-<?php echo $nbcols; ?> sol-sm-12 printable" >
<table class='statisticstable table table-bordered printable' id="quid_<?php echo $outputs['parentqid'];?>">
    <thead>
        <tr class='success'>
            <th colspan='4' align='center' style='text-align: center; '>
                <strong>
                    <?php echo sprintf(gT("Field summary for %s"),$outputs['qtitle']); ?>
                </strong>
                <button class="pull-right action_js_export_to_pdf btn btn-default btn-sm" data-question-id="quid_<?php echo $outputs['parentqid'];?>" data-toggle="tooltip" title="<?php eT('Export this question to PDF.'); ?>">
                    <i class="fa fa-file-pdf-o"></i>
                </button>
            </th>
        </tr>
        <tr>
            <th colspan='4' align='center' style='text-align: center; '>
                <!-- question title -->
                <strong>
                    <?php echo $outputs['qquestion'];?>
                </strong>
            </th>
        </tr>
        <!-- width depend on how much items... -->
        <tr>
            <th width='' align='center' >
                <strong>
                    <?php eT("Answer");?>
                </strong>
            </th>

            <?php if ($bShowCount  = true): ?>
                <th width='' align='center' >
                    <strong><?php eT("Count"); ?></strong>
                </th>
            <?php endif;?>

            <?php if ($bShowPercentage  = true): ?>
                <th width='' align='center' >
                    <strong><?php eT("Percentage");?></strong>
                </th>
            <?php endif;?>

            <?php if($bSum): ?>
                <th width='' align='center' >
                    <strong>
                        <?php eT("Sum");?>
                    </strong>
                </th>
            <?php endif; ?>
        </tr>
    </thead>
<!-- end of _statisticsoutput_header -->
