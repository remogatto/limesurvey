<div class='menubar surveybar' id="tokenbarid">
    <div class='row container-fluid'>

        <!-- left buttons -->
        <div class="col-md-10">

            <!-- Token view buttons -->
            <?php if( isset($token_bar['buttons']['view']) ): ?>

                <!-- Display tokens -->
                <?php if (Permission::model()->hasSurveyPermission($surveyid, 'tokens', 'read')): ?>
                    <a class="btn btn-default" href='<?php echo $this->createUrl("admin/tokens/sa/browse/surveyid/$surveyid"); ?>' role="button">
                        <span class="glyphicon glyphicon-list-alt text-success"></span>
                        <?php eT("Display participants"); ?>
                    </a>
                <?php endif; ?>

                <!-- Create tokens -->
                <div class="btn-group">
                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                    <span class="icon-add text-success"></span>
                    <?php eT("Create...");?> <span class="caret"></span>
                </button>

                <!-- Add new token entry -->
                <ul class="dropdown-menu">
                <?php if (Permission::model()->hasSurveyPermission($surveyid, 'tokens', 'create')): ?>
                <li>
                    <a href="<?php echo $this->createUrl("admin/tokens/sa/addnew/surveyid/$surveyid"); ?>" >
                        <span class="icon-add"></span>
                        <?php eT("Add participant"); ?>
                    </a>
                </li>

                <!-- Create dummy tokens -->
                <li>
                    <a href="<?php echo $this->createUrl("admin/tokens/sa/adddummies/surveyid/$surveyid"); ?>" >
                       <span class="fa fa-plus-square"></span>
                       <?php eT("Create dummy participants"); ?>
                    </a>
                </li>
                <?php endif; ?>

                <!-- Import tokens -->
                <?php if (Permission::model()->hasSurveyPermission($surveyid, 'tokens', 'import')): ?>
                    <li role="separator" class="divider"></li>
                    <small><?php eT("Import participants from:"); ?></small>

                    <!-- from CSV file -->
                    <li>
                       <a href="<?php echo $this->createUrl("admin/tokens/sa/import/surveyid/$surveyid") ?>" >
                           <span class="icon-importcsv"></span>
                           <?php eT("CSV file"); ?>
                       </a>
                    </li>

                    <!-- from LDAP query -->
                    <li>
                        <a href="<?php echo $this->createUrl("admin/tokens/sa/importldap/surveyid/$surveyid") ?>" >
                            <span class="icon-importldap"></span>
                            <?php eT("LDAP query"); ?>
                        </a>
                    </li>
                <?php endif; ?>
                </ul>
                </div>

                <!-- Manage additional attribute fields -->
                <?php if (Permission::model()->hasSurveyPermission($surveyid, 'tokens', 'update') || Permission::model()->hasSurveyPermission($iSurveyID, 'surveysettings', 'update')): ?>
                    <a class="btn btn-default" href='<?php echo $this->createUrl("admin/tokens/sa/managetokenattributes/surveyid/$surveyid"); ?>' role="button">
                       <span class="icon-token_manage text-success"></span>
                       <?php eT("Manage attributes"); ?>
                    </a>
                <?php endif; ?>

                <!-- Export tokens to CSV file -->
                <?php if (Permission::model()->hasSurveyPermission($surveyid, 'tokens', 'export')): ?>
                    <a class="btn btn-default" href="<?php echo $this->createUrl("admin/tokens/sa/exportdialog/surveyid/$surveyid"); ?>" role="button">
                       <span class="icon-exportcsv"></span>
                       <?php eT("Export"); ?>
                    </a>
                <?php endif; ?>

                <!-- EMAILS -->
                <?php if (Permission::model()->hasSurveyPermission($surveyid, 'tokens', 'update')):?>
                <div class="btn-group">
                    <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="icon-emailtemplates text-success"></span>
                        <?php eT("Invitations & reminders");?> <span class="caret"></span>
                    </button>

                    <ul class="dropdown-menu">
                        <?php if (Permission::model()->hasSurveyPermission($surveyid, 'tokens', 'update')): ?>

                        <!-- Send email invitation -->
                        <li>
                            <a href="<?php echo $this->createUrl("admin/tokens/sa/email/surveyid/$surveyid"); ?>" >
                                <span class="icon-invite"></span>
                                <?php eT("Send email invitation"); ?>
                            </a>
                        </li>

                        <!-- Send email reminder -->
                        <li>
                            <a href="<?php echo $this->createUrl("admin/tokens/sa/email/action/remind/surveyid/$surveyid"); ?>" >
                                <span class="icon-remind"></span>
                                <?php eT("Send email reminder"); ?>
                            </a>
                        </li>

                        <!-- Edit email template -->
                        <!-- Send email invitation -->
                        <li>
                            <a href="<?php echo $this->createUrl("admin/emailtemplates/sa/index/surveyid/$surveyid"); ?>" >
                                <span class="fa fa-envelope-o"></span>
                                <?php eT("Edit email templates"); ?>
                            </a>
                        </li>
                        <?php endif; ?>

                        <li role="separator" class="divider"></li>

                        <!-- Bounce processing -->
                        <?php if (Permission::model()->hasSurveyPermission($iSurveyId, 'tokens', 'update')):?>
                            <?php if($thissurvey['bounceprocessing'] != 'N' ||  ($thissurvey['bounceprocessing'] == 'G' && getGlobalSetting('bounceaccounttype') != 'off')):?>
                                <?php if (function_exists('imap_open')):?>
                                    <li>
                                        <a href="#" id="startbounceprocessing" data-url="<?php echo $this->createUrl("admin/tokens/sa/bounceprocessing/surveyid/$surveyid"); ?>" >
                                            <span class="ui-bounceprocessing"></span>
                                            <?php eT("Start bounce processing"); ?>
                                        </a>
                                    </li>
                                <?php else: ?>
                                    <?php $eMessage = gT("The imap PHP library is not installed or not activated. Please contact your system administrator."); ?>
                                <?php endif;?>
                            <?php else: ?>
                                <?php $eMessage = gT("Bounce processing is deactivated either application-wide or for this survey in particular."); ?>
                            <?php endif;?>
                        <?php else:?>
                            <?php $eMessage = gT("We are sorry but you don't have permissions to do this."); ?>
                        <?php endif;?>

                        <?php if (isset($eMessage)):?>
                            <li>
                                <a  href="#" disabled="disabled" data-toggle="tooltip" data-placement="bottom" title='<?php echo $eMessage; ?>'>
                                    <span class="ui-bounceprocessing"></span>
                                    <?php eT("Start bounce processing"); ?>
                                </a>
                            </li>
                        <?php endif;?>

                        <!-- Bounce settings -->
                        <li>
                            <a href="<?php echo $this->createUrl("admin/tokens/sa/bouncesettings/surveyid/$surveyid"); ?>" >
                                <span class="icon-settings"></span>
                                <?php eT("Bounce settings"); ?>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Generate tokens -->
                <a class="btn btn-default" href="<?php echo $this->createUrl("admin/tokens/sa/tokenify/surveyid/$surveyid"); ?>" role="button">
                    <span class="icon-do text-success"></span>
                    <?php eT("Generate tokens"); ?>
                </a>

                <!-- View participants of this survey in CPDB -->
                <a class="btn btn-default" href="#" role="button" onclick="sendPost('<?php echo $this->createUrl("/admin/participants/sa/displayParticipants"); ?>','',['searchcondition'],['surveyid||equal|| <?php echo $surveyid ?>']);">
                    <span class="ui-icon ui-participant-link"></span>
                    <?php eT("View in CPDB"); ?>
                </a>
                <?php endif; ?>
            <?php endif;?>
        </div>

        <!-- Right buttons -->
        <div class="col-md-2 text-right">

            <!-- View token buttons -->
            <?php if( isset($token_bar['buttons']['view'] )): ?>

                <!-- Delete tokens table -->
                <?php if (Permission::model()->hasSurveyPermission($surveyid, 'surveysettings', 'update') || Permission::model()->hasSurveyPermission($surveyid, 'tokens','delete')): ?>
                    <a class="btn btn-danger" href="<?php echo $this->createUrl("admin/tokens/sa/kill/surveyid/$surveyid"); ?>" role="button">
                        <?php eT("Delete participants table"); ?>
                    </a>
                <?php endif; ?>
            <?php endif; ?>


            <!-- Send invitations buttons -->
            <?php if(isset($token_bar['sendinvitationbutton'])):?>
                <a class="btn btn-default" href="#" role="button" id="send-invitation-button">
                    <span class="icon-invite" ></span>
                    <?php eT("Send invitations");?>
                </a>
            <?php endif;?>

            <!-- Send reminder buttons -->
            <?php if(isset($token_bar['sendreminderbutton'])):?>
                <a class="btn btn-default" href="#" role="button" id="send-reminders-button">
                    <span class="icon-invite" ></span>
                    <?php eT("Send reminders");?>
                </a>
            <?php endif;?>


            <!-- Save buttons -->
            <?php if(isset($token_bar['savebutton']['form'])):?>

                <a class="btn btn-success" href="#" role="button" id="save-button" data-use-form-id="<?php if (isset($token_bar['savebutton']['useformid'])){ echo '1';}?>" data-form-to-save="<?php if (is_string($token_bar['savebutton']['form'])) {echo $token_bar['savebutton']['form']; }?>">
                    <span class="glyphicon glyphicon-ok" ></span>
                    <?php eT("Save");?>
                </a>
            <?php endif;?>

            <?php if(isset($token_bar['exportbutton']['form'])):?>
                <a class="btn btn-success" href="#" role="button" id="save-button">
                    <span class="glyphicon glyphicon glyphicon-export" ></span>
                       <?php eT("Download CSV file"); ?>
                </a>
            <?php endif;?>

            <!-- Close -->
            <?php if(isset($token_bar['closebutton']['url'])):?>
                <a class="btn btn-danger" href="<?php echo $token_bar['closebutton']['url']; ?>" role="button">
                    <span class="glyphicon glyphicon-close" ></span>
                    <?php eT("Close");?>
                </a>
            <?php endif;?>

            <!-- Return -->
            <?php if(isset($token_bar['returnbutton'])):?>
                <a class="btn btn-default" href="<?php echo $token_bar['returnbutton']['url']; ?>" role="button">
                    <span class="glyphicon glyphicon-step-backward" ></span>
                    <?php echo $token_bar['returnbutton']['text'];?>
                </a>
            <?php endif;?>
        </div>

    </div>
</div>

<!-- Token Bounce -->
<div id="tokenBounceModal" class="modal fade" tabindex="-1" role="dialog">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title"><?php eT('Bounce processing');?></h4>
            </div>
            <div class="modal-body">
                <!-- Here will come the result of the ajax request -->
                <p class='modal-body-text'>

                </p>

                <!-- the ajax loader -->
                <div id="ajaxContainerLoading" >
                    <p><?php eT('Please wait, loading data...');?></p>
                    <div class="preloader loading">
                        <span class="slice"></span>
                        <span class="slice"></span>
                        <span class="slice"></span>
                        <span class="slice"></span>
                        <span class="slice"></span>
                        <span class="slice"></span>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-danger" data-dismiss="modal"><?php eT("Cancel");?></button>
            </div>
        </div><!-- /.modal-content -->
    </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
