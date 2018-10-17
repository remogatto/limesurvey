

        <h3 class="pagetitle"><?php eT("Upload template file") ?></h3>
        <?php echo CHtml::form(array('admin/templates/sa/upload'), 'post', array('id'=>'importtemplate', 'name'=>'importtemplate', 'enctype'=>'multipart/form-data', 'onsubmit'=>'return validatefilename(this,"'.gT('Please select a file to import!', 'js').'");')); ?>
            <input type='hidden' name='lid' value='$lid' />
            <input type='hidden' name='action' value='templateupload' />
                <div  class="form-group">
                    <label for='the_file'><?php eT("Select template ZIP file:") ?></label>
                    <input id='the_file' name='the_file' type="file" />
                </div>
                <div  class="form-group">

                <?php if (!function_exists("zip_open")) {?>
                    <?php eT("The ZIP library is not activated in your PHP configuration thus importing ZIP files is currently disabled.", "js") ?>
                <?php } else {?>
                    <input class="btn btn-default" type='button' value='<?php eT("Import") ?>' onclick='if (validatefilename(this.form,"<?php eT('Please select a file to import!', 'js') ?>")) { this.form.submit();}' />
                <?php }?>
            </div>

        </form>
