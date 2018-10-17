<?php
class RSyntaxWriter extends Writer
{
    private $output;
    private $separator;
    private $hasOutputHeader;
    private $maxLength = 25500; // From old code, max length of string fields

    /**
     * The open filehandle
     */
    protected $handle = null;
    protected $customFieldmap = array();
    protected $headers = array();

    function __construct()
    {
        $this->output = '';
        $this->separator = ',';
        $this->hasOutputHeader = false;
    }

    public function init(SurveyObj $survey, $sLanguageCode, FormattingOptions $oOptions)
    {
        parent::init($survey, $sLanguageCode, $oOptions);
        if ($oOptions->output=='display') {
            header("Content-Disposition: attachment; filename=survey_" . $survey->id . "_R_syntax_file.R");
            header("Content-type: application/download; charset=UTF-8");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");
            $this->handle = fopen('php://output', 'w');
        } elseif ($oOptions->output == 'file') {
            $this->handle = fopen($this->filename, 'w');
        }

        $this->out('data <- read.csv("survey_' . $survey->id .'_R_data_file.csv", quote = "\'\"", na.strings=c("", "\"\""), stringsAsFactors=FALSE, fileEncoding="UTF-8")');
        $this->out("");
        $this->out("");

        $oOptions->headingFormat = 'code';  // Always use fieldcodes

        // R specific stuff
        Yii::app()->loadHelper("export");
        $tmpFieldmap = SPSSFieldMap($survey->id);
        foreach ($tmpFieldmap as $field => $values)
        {
            $fieldmap[$values['title']] = $values;
            if (array_key_exists('sql_name', $values)) {
                $fieldmap[$values['sql_name']] = $values;
            }
        }
        $this->customFieldmap = $fieldmap;
    }

    protected function out($content)
    {
        fwrite($this->handle, $content . "\n");
    }

    protected function outputRecord($headers, $values, FormattingOptions $oOptions)
    {
        $this->headers = $oOptions->selectedColumns;
        foreach ($oOptions->selectedColumns as $id => $title)
        {
            $field = $this->customFieldmap[$title];

            if ( ! isset($field['answers']) )
            {
                $strTmp = mb_substr(stripTagsFull($values[$id]), 0, $this->maxLength);

                $len = mb_strlen($strTmp);

                if ( $len > $field['size'] ) $field['size'] = $len;

                if ( trim($strTmp) != '' )
                {
                    if ( $field['SPSStype'] == 'F' && (isNumericExtended($strTmp) === FALSE || $field['size'] > 16) )
                    {
                        $field['SPSStype'] = 'A';
                    }
                }
                $this->customFieldmap[$title] = $field;
            }
        }
    }

    public function close()
    {
        $errors = '';
         foreach ($this->headers as $id => $title) {
            $field = $this->customFieldmap[$title];
            $i = $id + 1;
            if ($field['SPSStype'] == 'DATETIME23.2')
                $field['size'] = '';

            if ($field['LStype'] == 'N' || $field['LStype'] == 'K')
            {
                $field['size'] .= '.' . ($field['size'] - 1);
            }

            switch ($field['SPSStype']) {
                case 'F':
                    $type = "numeric";
                    break;
                case 'A':
                    $type = "character";
                    break;
                case 'DATETIME23.2':
                case 'SDATE':
                    $type = "character";
                    //@TODO set $type to format for date
                    break;
            }

                $this->out("# LimeSurvey Field type: {$field['SPSStype']}");
                $this->out("data[, " . $i . "] <- "
                . "as.$type(data[, " . $i . "])");
                $this->out('attributes(data)$variable.labels[' . $i . '] <- "'
                . addslashes(
                        htmlspecialchars_decode(
                                mb_substr(
                                        stripTagsFull(
                                                $field['VariableLabel']
                                        ), 0, $this->maxLength
                                )
                        )
                )
                . '"');

                // Create the value Labels!
                if (isset($field['answers']))
                {
                    $answers = $field['answers'];

                    //print out the value labels!
                    $str = 'data[, ' . $i . '] <- factor(data[, ' . $i . '], levels=c(';
                    foreach ($answers as $answer) {
                        if ($field['SPSStype'] == "F" && isNumericExtended($answer['code']))
                        {
                            $str .= "{$answer['code']},";
                        } else
                        {
                            $str .= "\"{$answer['code']}\",";
                        }
                    }

                    $str = mb_substr($str, 0, -1);
                    $str .= '),labels=c(';

                    foreach ($answers as $answer) {
                        $str .= '"'.addslashes($answer['value']).'", ';
                    }

                    $str = mb_substr($str, 0, -2);

                    if ($field['scale'] !== '' && $field['scale'] == 2)
                    {
                        $scale = ", ordered=TRUE";
                    } else
                    {
                        $scale = "";
                    }

                    $this->out("{$str}){$scale})");
                }

                //Rename the Variables (in case somethings goes wrong, we still have the OLD values
                if (isset($field['sql_name']))
                {
                    $ftitle = $field['title'];
                    if (!preg_match("/^([a-z]|[A-Z])+.*$/", $ftitle))
                    {
                        $ftitle = "q_" . $ftitle;
                    }

                    $ftitle = str_replace(array("-", ":", ";", "!"), array("_hyph_", "_dd_", "_dc_", "_excl_"), $ftitle);

                    if ($ftitle != $field['title'])
                    {
                        $errors .= "# Variable name was incorrect and was changed from {$field['title']} to $ftitle .\n";
                    }

                    $this->out("names(data)[" . $i . "] <- "
                    . "\"" . $ftitle . "\"");  // <AdV> added \n
                } else {
                    $this->out("#sql_name not set");
                }
        }  // end foreach
        if (!empty($errors)) {
            $this->out($errors);
        }

        fclose($this->handle);
    }
}