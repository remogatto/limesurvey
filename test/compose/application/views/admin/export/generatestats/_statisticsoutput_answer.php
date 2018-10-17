<?php
/**
 * This view render the list of answers in top of the graph
 *
 * @var $label
 * @var $grawdata
 * @var $gdata
 * @var $i
 * @var $extraline
 * @var $sColumnNameForView
 * potentialy not set when controller will try to load them in $aData (so will need defaut values)
 * @var $aggregated
 * @var $aggregatedPercentage
 * @var $sumitems
 * @var $sumpercentage
 * @var $TotalCompleted
 * @var $casepercentage
 */
?>

<!-- _statisticsoutput_answer -->
<tr>
    <td align='right' ><?php echo $label[$i]; ?></td>
    <td align='right' ><?php echo $grawdata[$i]; ?></td>

    <?php if ($bNAgData): ?>

        <td  align='right' >
            <?php echo sprintf("%01.2f", $gdata[$i]); ?>%
        </td>

        <?php if ($bNAgDataExtraLine): ?>
            <?php echo $bNAgDataExtraLine; ?>
        <?php endif;?>

        <?php if ($showAggregatedPercentage):?>
            <td  align='right' >
                <?php echo sprintf("%01.2f", $aggregatedPercentage); ?>%
            </td>
        <?php elseif($showEmptyAggregatedPercentage):?>
            <td  align='right' >
                &nbsp;
            </td>
        <?php endif;?>

    <?php endif; ?>
</tr>

<?php if ($bShowSumAnswer ):?>
    <tr>
        <td align='right'>
            <strong>
                <?php eT("Sum");?>(<?php eT("Answers"); ?>)
            </strong>
        </td>
        <td align='right'>
            <strong>
                <?php echo $sumitems; ?>
            </strong>
        </td>
        <td align='right'>
            <strong>
                <?php echo $sumpercentage; ?>%
            </strong>
        </td>
        <td align='right'>
            <strong>
                <?php echo $sumpercentage; ?>%
            </strong>
        </td>
    </tr>
<?php endif; ?>



<!-- end of _statisticsoutput_answer -->
