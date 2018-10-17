<?php
/**
* Exports results in Microsoft Excel format.  By default the Writer sends
* HTTP headers and the file contents via HTTP.  For testing purposes a
* file name can be  to the constructor which will cause the ExcelWriter to
* output to a file.
*/
class ExcelWriter extends Writer
{
    private $workbook;
    private $currentSheet;
    private $separator;
    private $hasOutputHeader;
    private $rowCounter;
    private $forceDownload=true;

    //Indicates if the Writer is outputting to a file rather than sending via HTTP.
    private $outputToFile = false;

    /**
    * The presence of a filename will cause the writer to output to
    * a file rather than send.
    *
    * @param string $filename
    * @return ExcelWriter
    */
    public function __construct($filename = null)
    {
        require_once(APPPATH.'/third_party/xlsx_writer/xlsxwriter.class.php');
        $this->separator = '~|';
        $this->hasOutputHeader = false;
        $this->rowCounter = 0;
    }

    public function init(SurveyObj $survey, $sLanguageCode, FormattingOptions $oOptions)
    {
        parent::init($survey, $sLanguageCode, $oOptions);

        $this->workbook = new XLSXWriter();
        $this->workbook->setTempDir(Yii::app()->getConfig('tempdir'));
        $worksheetName = $survey->languageSettings['surveyls_title'];
        $worksheetName=mb_substr(str_replace(array('*', ':', '/', '\\', '?', '[', ']'),array(' '),$worksheetName),0,31,'utf-8'); // Remove invalid characters
        if ($worksheetName=='')
        {
            $worksheetName='survey_'.$survey->id;
        }
        $this->currentSheet = $worksheetName;
        $this->forceDownload=!($oOptions->output=='file');
    }

    protected function outputRecord($headers, $values, FormattingOptions $oOptions)
    {
        if (!$this->hasOutputHeader)
        {
            $columnCounter = 0;
            $this->workbook->writeSheetRow($this->currentSheet, $headers );
            $this->hasOutputHeader = true;
        }
        $this->workbook->writeSheetRow($this->currentSheet, $values );
    }

    public function close()
    {
        $this->workbook->writeToFile($this->filename);
        if ($this->forceDownload){
            header("Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet");
            header("Content-Disposition: attachment; filename=\"{$this->webfilename}.xlsx\"");
            header('Content-Length: ' . filesize($this->filename));
            readfile($this->filename);
        }
        return $this->workbook;
    }
}