<?php
    /**
    * LimeSurvey
    * Copyright (C) 2007-2015 The LimeSurvey Project Team / Carsten Schmitz
    * All rights reserved.
    * License: GNU/GPL License v2 or later, see LICENSE.php
    * LimeSurvey is free software. This version may have been modified pursuant
    * to the GNU General Public License, and as distributed it includes or
    * is derivative of works licensed under the GNU General Public License or
    * other free or open source software licenses.
    * See COPYRIGHT.php for copyright notices and details.
    *
    */
    /**
    * LimeExpressionManager
    * This is a wrapper class around ExpressionManager that implements a Singleton and eases
    * passing of LimeSurvey variable values into ExpressionManager
    *
    * @author LimeSurvey Team (limesurvey.org)
    * @author Thomas M. White (TMSWhite)
    * @author Denis Chenu <http://sondages.pro>
    */
    Yii::import('application.helpers.expressions.em_core_helper', true);
    Yii::app()->loadHelper('database');
    Yii::app()->loadHelper('frontend');
    Yii::app()->loadHelper('surveytranslator');
    Yii::import("application.libraries.Date_Time_Converter");
    define('LEM_DEBUG_TIMING',1);
    define('LEM_DEBUG_VALIDATION_SUMMARY',2);   // also includes  SQL error messages
    define('LEM_DEBUG_VALIDATION_DETAIL',4);
    define('LEM_PRETTY_PRINT_ALL_SYNTAX',32);

    define('LEM_DEFAULT_PRECISION',12);

    class LimeExpressionManager {
        /**
        * LimeExpressionManager is a singleton.  $instance is its storage location.
        * @var LimeExpressionManager
        */
        private static $instance;
        /**
        * Implements the recursive descent parser that processes expressions
        * @var ExpressionManager
        */
        private $em;
        /**
        *
        * @var array
        */
        private $groupRelevanceInfo;
        /**
        * The survey ID
        * @var integer
        */
        private $sid;
        /**
        * sum of LEM_DEBUG constants - use bitwise AND comparisons to identify which parts to use
        * @var int
        */
        private $debugLevel=0;
         /**
        * sPreviewMode used for relevance equation force to 1 in preview mode
        * Maybe we can set it public
        * @var string|boolean
        */
        private $sPreviewMode=false;
        /**
        * Collection of variable attributes, indexed by SGQA code
        *
        * Actual variables are stored in this structure:
        * $knownVars[$sgqa] = array(
        * 'jsName_on' => // the name of the javascript variable if it is defined on the current page - often 'answerSGQA'
        * 'jsName' => // the name of the javascript variable when referenced  on different pages - usually 'javaSGQA'
        * 'readWrite' => // 'Y' for yes, 'N' for no - currently not used
        * 'hidden' => // 1 if the question attribute 'hidden' is true, otherwise 0
        * 'question' => // the text of the question (or sub-question)
        * 'qid' => // the numeric question id - e.g. the Q part of the SGQA name
        * 'gid' => // the numeric group id - e.g. the G part of the SGQA name
        * 'grelevance' =>  // the group level relevance string
        * 'relevance' => // the question level relevance string
        * 'qcode' => // the qcode-style variable name for this question  (or sub-question)
        * 'qseq' => // the 0-based index of the question within the survey
        * 'gseq' => // the 0-based index of the group within the survey
        * 'type' => // the single character type code for the question
        * 'sgqa' => // the SGQA name for the variable
        * 'ansList' => // ansArray converted to a JavaScript fragment - e.g. ",'answers':{ 'M':'Male','F':'Female'}"
        * 'ansArray' => // PHP array of answer strings, keyed on the answer code = e.g. array['M']='Male';
        * 'scale_id' => // '0' for most answers.  '1' for second scale within dual-scale questions
        * 'rootVarName' => // the root code / name / title for the question, without any sub-question or answer-level suffix.  This is from the title column in the questions table
        * 'subqtext' => // the sub-question text
        * 'rowdivid' => // the JavaScript ID of the row identifier for a question.  This is used to show/hide entire question rows
        * 'onlynum' => // 1 if only numbers are allowed for this variable.  If so, then extra processing is needed to ensure that can use comma as a decimal separator
        * );
        *
        * Reserved variables (e.g. TOKEN:xxxx) are stored with this structure:
        * $knownVars[$token] = array(
        * 'code' => // the static value for the variable
        * 'type' => // ''
        * 'jsName_on' => // ''
        * 'jsName' => // ''
        * 'readWrite' => // 'N' - since these are always read-only variables
        * );
        *
        * @var array
        */
        private $knownVars;

        /**
        * maps qcode varname to SGQA code
        *
        * @example ['gender'] = '38612X10X145'
        * @var array
        */
        private $qcode2sgqa;

        /**
        * variables temporarily set for substitution purposes
        *
        * These are typically the LimeReplacement Fields passed in via templatereplace()
        * Each has the following structure:  array(
        * 'code' => // the static value of the variable
        * 'jsName_on' => // ''
        * 'jsName' => // ''
        * 'readWrite'  => // 'N'
        * );
        *
        * @var array
        */
        private $tempVars;

        /**
        * Array of relevance information for each page (gseq), indexed by gseq.
        * Within a page, it contains a sequential list of the results of each relevance equation processed
        * array(
        * 'qid' => // question id -- e.g. 154
        * 'gseq' => // 0-based group sequence -- e.g. 2
        * 'eqn' => // the raw relevance equation parsed -- e.g. "!is_empty(p2_sex)"
        * 'result' => // the Boolean result of parsing that equation in the current context -- e.g. 0
        * 'numJsVars' => // the number of dynamic JavaScript variables used in that equation -- e.g. 1
        * 'relevancejs' => // the actual JavaScript to insert for that relevance equation -- e.g. "LEMif(LEManyNA('p2_sex'),'',( ! LEMempty(LEMval('p2_sex') )))"
        * 'relevanceVars' => // a pipe-delimited list of JavaScript variables upon which that equation depends -- e.g. "java38612X12X153"
        * 'jsResultVar' => // the JavaScript variable in which that result will be stored -- e.g. "java38612X12X154"
        * 'type' => // the single character type of the question -- e.g. 'S'
        * 'hidden' => // 1 if the question should always be hidden
        * 'hasErrors' => // 1 if there were parsing errors processing that relevance equation
        * @var array
        */
        private $pageRelevanceInfo;

        /**
        *
        * @var array
        */
        private $pageTailorInfo;
        /**
        * internally set to true (1) for survey.php so get group-specific logging but keep javascript variable namings consistent on the page.
        * @var boolean
        */
        private $allOnOnePage=false;
        /**
        * survey mode.  One of 'survey', 'group', or 'question'
        * @var string
        */
        private $surveyMode='group';
        /**
        * a set of global survey options passed from LimeSurvey
        *
        * For example, array(
        * 'rooturl' => // URL prefix needed to be able to click on a syntax-highlighted variable name and have it open the needed editting window
        * 'hyperlinkSyntaxHighlighting' => // true if should be able to click on variables to edit them
        * 'active' => // 0 for inactive, 1 for active survey
        * 'allowsave' => // 0 for do not allow save; 1 for allow save
        * 'anonymized' => // 1 for anonymous
        * 'assessments' => // 1 for use assessments
        * 'datestamp' => // 1 for use date stamps
        * 'ipaddr' => // 1 for capture IP address
        * 'radix' => // '.' for use period as decimal separator; ',' for use comma as decimal separator
        * 'savetimings' => // "Y" if should save survey timings
        * 'startlanguage' => // the starting language -- e.g. 'en'
        * 'surveyls_dateformat' => // the index of the language specific date format -- e.g. 1
        * 'tablename' => // the name of the table storing the survey data, if active -- e.g. lime_survey_38612
        * 'target' => // the path for uploading files -- e.g. '/temp/files/'
        * 'timeadjust' => // the time offset -- e.g. 0
        * 'tempdir' => // the temporary directory for uploading files -- e.g. '/temp/'
        * );
        *
        * @var array
        */
        private $surveyOptions=array();
        /**
        * array of mappings of Question # (qid) to pipe-delimited list of SGQA codes used within it
        *
        * @example [150] = "38612X11X150|38612X11X150other"
        * @var array
        */
        private $qid2code;
        /**
        * array of mappings of JavaScript Variable names to Question number (qid)
        *
        * @example ['java38612X13X161other'] = '161'
        * @var array
        */
        private $jsVar2qid;
        /**
        * maps name of the variable to the SGQ name (without the A suffix)
        *
        * @example ['p1_sex'] = "38612X10X147"
        * @example ['afDS_sq1_1'] = "26626X37X705sq1#1"
        * @var array
        */
        private $qcode2sgq;
        /**
        * array of mappings of knownVar aliases to the JavaScript variable names.
        * This maps both the SGQA and qcode alias names to the same 2 dimensional array
        *
        * @example ['p1_sex'] = array(
        * 'jsName' => // the JavaScript variable name used by EM -- e.g. "java38612X11X147"
        * 'jsPart' => // the JavaScript fragment used in EM's ____ array -- e.g. "'p1_sex':'java38612X11X147'"
        * );
        * @example ['afDS_sq1_1] = array(
        * 'jsName' => "java26626X37X705sq1#1"
        * 'jsPart' => "'afDS_sq1_1':'java26626X37X705sq1#1'"
        * );
        * @var array
        */
        private $alias2varName;
        /**
        * JavaScript array of mappings of canonical JavaScript variable name to key attributes.
        * These fragments are used to create the JavaScript varNameAttr array.
        *
        * @example ['java38612X11X147'] = "'java38612X11X147':{ 'jsName':'java38612X11X147','jsName_on':'java38612X11X147','sgqa':'38612X11X147','qid':147,'gid':11,'type':'G','default':'','rowdivid':'','onlynum':'','gseq':1,'answers':{ 'M':'Male','F':'Female'}}"
        * @example ['java26626X37X705sq1#1'] = "'java26626X37X705sq1#1':{ 'jsName':'java26626X37X705sq1#1','jsName_on':'java26626X37X705sq1#1','sgqa':'26626X37X705sq1#1','qid':705,'gid':37,'type':'1','default':'','rowdivid':'26626X37X705sq1','onlynum':'','gseq':1,'answers':{ '0~1':'1|Low','0~2':'2|Medium','0~3':'3|High','1~1':'1|Never','1~2':'2|Sometimes','1~3':'3|Always'}}"
        *
        * @var array
        */
        private $varNameAttr;

        /**
        * array of enumerated answer lists indexed by qid
        * These use a tilde syntax to indicate which scale the answer is part of.
        *
        * @example ['0~4'] = "4|Child" // this means that code 4 in scale 0 has a coded value of 4 and a display value of 'Child'
        * @example (for [705]): ['1~2'] = '2|Sometimes' // this means that the second scale for this question uses the coded value of 2 to represent 'Sometimes'
        * @example // TODO - add example from survey using assessments
        *
        * @var array
        */
        private $qans;
        /**
        * map of gid to 0-based sequence number of groups
        *
        * @example [10] = 0 // means that the first group (gseq=0) has gid=10
        *
        * @var array
        */
        private $groupId2groupSeq;
        /**
        * map question # to an incremental count of question order across the whole survey
        *
        * @example [157] = 13 // means that that 14th question in the survey has qid=157
        *
        * @var array
        */
        private $questionId2questionSeq;
        /**
        * map question  # to the group it is within, using an incremental count of group order
        *
        * @example [157] = 2 // means that qid 157 is in the 3rd page of questions (gseq = 2)
        *
        * @var array
        */
        private $questionId2groupSeq;
        /**
        * array of info about each Group, indexed by GroupSeq
        *
        * @example [2] = array(
        * 'qstart' => 9 // the first qseq within that group
        * 'qend' => 13 //the last qseq within that group
        * );
        *
        * @var array
        */
        private $groupSeqInfo;

        /**
        * tracks which groups have at least one relevant, non-hidden question
        *
        * @example [2] = 0 // means that the third group (gseq==2) is currently irrelevant
        *
        * @var array
        */
        private $gseq2relevanceStatus;
        /**
        * maps question # to the validation equation(s) for that question.
        * These are grouped by qid then validation type, such as 'value_range', and 'num_answers'
        *
        * @example [703]  = array(
        * 'eqn' => array(
        *      'value_range' = "((is_empty(26626X34X703.NAOK) || 26626X34X703.NAOK >= (0)) and (is_empty(26626X34X703.NAOK) || 26626X34X703.NAOK <= (5)))"
        * ),
        * 'tips' => array(
        *      'value_range' = "Each answer must be between {fixnum(0)} and {fixnum(5)}"
        * ),
        * 'subqValidEqns' = array(
        *      [] = array(
        *          'subqValidSelector' => ''   //
        *          'subqValidEqn' => "(is_empty(26626X34X703.NAOK) || 26626X34X703.NAOK >= (0)) && (is_empty(26626X34X703.NAOK) || 26626X34X703.NAOK <= (5))"
        * ),
        * 'sumEqn' => '' // the equation to compute the current sum of the responses
        * 'sumRemainingEqn' => '' // the equation to how much is left (for the question attribute that lets you specify the exact value of the sum of the answers)
        * );
        *
        * @var array
        */
        private $qid2validationEqn;

        /**
        * keeps relevance in proper sequence so can minimize relevance processing to see what should be see on page and in indexes
        * Array is indexed on qseq
        *
        * @example [3] = array(
        * 'relevance' => "!is_empty(num)"  // the question-level relevance equation
        * 'grelevance' => ""   // the group-level relevance equation
        * 'qid' => "699" // the question id
        * 'qseq' => 3  // the 0-index question sequence
        * 'gseq' => 0  // the 0-index group sequence
        * 'jsResultVar_on' => 'answer26626X34X699' // the javascript variable holding the input value
        * 'jsResultVar' => 'java26226X34X699'  // the javascript variable (often hidden) holding the value to be submitted
        * 'type' => 'N'    // the one character question type
        * 'hidden' => 0    // 1 if it should be always_hidden
        * 'gid' => "34"    // group id
        * 'mandatory' => 'N'   // 'Y' if mandatory
        * 'eqn' => ""  // TODO ??
        * 'help' => "" // the help text
        * 'qtext' => "Enter a larger number than {num}"    // the question text
        * 'code' => 'afDS_sq5_1' // the full variable name
        * 'other' => 'N'   // whether the question supports the 'other' option - 'Y' if true
        * 'rowdivid' => '2626X37X705sq5'   // the javascript id for the row - in this case, the 5th sub-question
        * 'aid' => 'sq5'   // the answer id
        * 'sqid' => '791' // the sub-question's qid (only populated for some question types)
        * );
        *
        * @var array
        */
        private $questionSeq2relevance;
        /**
        * current Group sequence (0-based index)
        * @example 1
        * @var integer
        */
        private $currentGroupSeq;
        /**
        * for Question-by-Question mode, the 0-based index
        * @example 3
        * @var integer
        */
        private $currentQuestionSeq;
        /**
        * used in Question-by-Question mode
        * @var integer
        */
        private $currentQID;
        /**
        * set of the current set of questions to be displayed, indexed by QID - at least one must be relevant
        *
        * The array has N entries, where N is the number if qids in the Qset.  Each  has the following contents:
        * @example [705] = array(
        * 'info' => array()    // this is an exact copy of $questionSeq2relevance[$qseq] -- TODO - remove redundancy
        * 'relevant' => 1  // 1 if the question is currently relevant
        * 'hidden' => 0    // 1 if the question is always hidden
        * 'relEqn' => ''   // the relevance equation -- TODO - how different from ['info']['relevance']?
        * 'sgqa' => // pipe-separated list of SGQA codes for this question -- e.g. "26626X37X705sq1#0|26626X37X705sq1#1|26626X37X705sq2#0|26626X37X705sq2#1|26626X37X705sq3#0|26626X37X705sq3#1|26626X37X705sq4#0|26626X37X705sq4#1|26626X37X705sq5#0|26626X37X705sq5#1"
        * 'unansweredSQs' => // pipe-separated list of currently unanswered SGQA codes for this question -- e.g. "26626X37X705sq1#0|26626X37X705sq1#1|26626X37X705sq3#0|26626X37X705sq3#1|26626X37X705sq5#0|26626X37X705sq5#1"
        * 'valid' => 0 // 1 if the current answers  pass all of the validation criteria for the question
        * 'validEqn' => // the auto-generated validation criteria, based upon advanced question attributes -- e.g. "((count(if(count(26626X37X705sq1#0.NAOK,26626X37X705sq1#1.NAOK)==2,1,''), if(count(26626X37X705sq2#0.NAOK,26626X37X705sq2#1.NAOK)==2,1,''), if(count(26626X37X705sq3#0.NAOK,26626X37X705sq3#1.NAOK)==2,1,''), if(count(26626X37X705sq4#0.NAOK,26626X37X705sq4#1.NAOK)==2,1,''), if(count(26626X37X705sq5#0.NAOK,26626X37X705sq5#1.NAOK)==2,1,'')) >= (minSelect)) and (count(if(count(26626X37X705sq1#0.NAOK,26626X37X705sq1#1.NAOK)==2,1,''), if(count(26626X37X705sq2#0.NAOK,26626X37X705sq2#1.NAOK)==2,1,''), if(count(26626X37X705sq3#0.NAOK,26626X37X705sq3#1.NAOK)==2,1,''), if(count(26626X37X705sq4#0.NAOK,26626X37X705sq4#1.NAOK)==2,1,''), if(count(26626X37X705sq5#0.NAOK,26626X37X705sq5#1.NAOK)==2,1,'')) <= (maxSelect)))"
        * 'prettyValidEqn' => // syntax-highlighted version of validEqn, only showing syntax errors
        * 'validTip' => // html fragment to insert for the validation tip -- e.g. "<div id='vmsg_705_num_answers' class='em_num_answers'>Please select between 1 and 3 answer(s)</div>"
        * 'prettyValidTip' => // version of validTip that can be parsed by EM to create dynmamic validation -- e.g. "<div id='vmsg_705_num_answers' class='em_num_answers'>Please select between {fixnum(minSelect)} and {fixnum(maxSelect)} answer(s)</div>"
        * 'validJS' => // JavaScript fragment that can perform validation.  This is the result of parsing validEqn -- e.g. "LEMif(LEManyNA('minSelect', 'maxSelect'),'',(((LEMcount(LEMif(LEMcount(LEMval('26626X37X705sq1#0.NAOK') , LEMval('26626X37X705sq1#1.NAOK') ) == 2, 1, ''), LEMif(LEMcount(LEMval('26626X37X705sq2#0.NAOK') , LEMval('26626X37X705sq2#1.NAOK') ) == 2, 1, ''), LEMif(LEMcount(LEMval('26626X37X705sq3#0.NAOK') , LEMval('26626X37X705sq3#1.NAOK') ) == 2, 1, ''), LEMif(LEMcount(LEMval('26626X37X705sq4#0.NAOK') , LEMval('26626X37X705sq4#1.NAOK') ) == 2, 1, ''), LEMif(LEMcount(LEMval('26626X37X705sq5#0.NAOK') , LEMval('26626X37X705sq5#1.NAOK') ) == 2, 1, '')) >= (LEMval('minSelect') )) && (LEMcount(LEMif(LEMcount(LEMval('26626X37X705sq1#0.NAOK') , LEMval('26626X37X705sq1#1.NAOK') ) == 2, 1, ''), LEMif(LEMcount(LEMval('26626X37X705sq2#0.NAOK') , LEMval('26626X37X705sq2#1.NAOK') ) == 2, 1, ''), LEMif(LEMcount(LEMval('26626X37X705sq3#0.NAOK') , LEMval('26626X37X705sq3#1.NAOK') ) == 2, 1, ''), LEMif(LEMcount(LEMval('26626X37X705sq4#0.NAOK') , LEMval('26626X37X705sq4#1.NAOK') ) == 2, 1, ''), LEMif(LEMcount(LEMval('26626X37X705sq5#0.NAOK') , LEMval('26626X37X705sq5#1.NAOK') ) == 2, 1, '')) <= (LEMval('maxSelect') )))))"
        * 'invalidSQs' => // current list of subquestions that fail validation criteria -- e.g. "26626X37X705sq1#0|26626X37X705sq1#1|26626X37X705sq2#0|26626X37X705sq2#1|26626X37X705sq3#0|26626X37X705sq3#1|26626X37X705sq4#0|26626X37X705sq4#1|26626X37X705sq5#0|26626X37X705sq5#1"
        * 'relevantSQs' => // current list of subquestions that are relevant -- e.g. "26626X37X705sq1#0|26626X37X705sq1#1|26626X37X705sq2#0|26626X37X705sq2#1|26626X37X705sq3#0|26626X37X705sq3#1|26626X37X705sq4#0|26626X37X705sq4#1|26626X37X705sq5#0|26626X37X705sq5#1"
        * 'irrelevantSQs' => // current list of subquestions that are irrelevant -- e.g. "26626X37X705sq2#0|26626X37X705sq2#1|26626X37X705sq4#0|26626X37X705sq4#1"
        * 'subQrelEqn' => // TODO - ??
        * 'mandViolation' => 0 // 1 if the question is mandatory and fails the mandatory criteria
        * 'anyUnanswered' => 1 // 1 if any parts of the question are unanswered
        * 'mandTip' => '' // message to display if the question fails mandatory criteria
        * 'message' => '' // TODO ??
        * 'updatedValues' => // array of values that should be updated for this question, as [$sgqa] = $value
        * 'sumEqn' => '' //
        * 'sumRemainingEqn' => '' //
        * );
        *
        * @var array|null
        */
        private $currentQset=NULL;
        /**
        * last result of NavigateForwards, NavigateBackwards, or JumpTo
        * Array of status information about last movement, whether at question, group, or survey level
        *
        * @example = array(
        * 'finished' => 0  // 1 if the survey has been completed and needs to be finalized
        * 'message' => ''  // any error message that needs to be displayed
        * 'seq' => 1   // the sequence count, using gseq, or qseq units if in 'group' or 'question' mode, respectively
        * 'mandViolation' => 0 // whether there was any violation of mandatory constraints in the last movement
        * 'valid' => 0 // 1 if the last movement passed all validation constraints.  0 if there were any validation errors
        * 'unansweredSQs' => // pipe-separated list of any sub-questions that were not answered
        * 'invalidSQs' => // pipe-separated list of any sub-questions that failed validation constraints
        * );
        *
        * @var array|null
        */
        private $lastMoveResult=NULL;
        /**
        * array of information needed to generate navigation index in question-by-question mode
        * One entry for each question, indexed by qseq
        *
        * @example [4] = array(
        * 'qid' => "700" // the question id
        * 'qtext' => 'How old are you?' // the question text
        * 'qcode' => 'age' // the variable name
        * 'qhelp' => '' // the help text
        * 'anyUnanswered' => 0 // 1 if there are any sub-questions answered.  Used for index display
        * 'anyErrors' => 0 // 1 if there are any errors among the sub-questions.  Could be used for index display
        * 'show' => 1 // 1 if there are any relevant, non-hidden sub-questions.  Only if so, then display the index entry
        * 'gseq' => 0  // the group sequence
        * 'gtext' => // text description for the group
        * 'gname' => 'G1' // the group title
        * 'gid' => "34" // the group id
        * 'mandViolation' => 0 // 1 if the question as a whole fails the mandatory criteria
        * 'valid' => 1 // 0 if any part of the question fails validation criteria.
        * );
        *
        * @var array
        */
        private $indexQseq;
        /**
        * array of information needed to generate navigation index in group-by-group mode
        * One entry for each group, indexed by gseq
        *
        * @example [0] = array(
        * 'gtext' => // the description for the group
        * 'gname' => 'G1' // the group title
        * 'gid' => '34' // the group id
        * 'anyUnanswered' => 0 // 1 if any questions within the group are unanswered
        * 'anyErrors' => 0 // 1 if any of the questions within the group fail either validity or mandatory constraints
        * 'valid' => 1 // 1 if at least question in the group is relevant and non-hidden
        * 'mandViolation' => 0 // 1 if at least one relevant, non-hidden question in the group fails mandatory constraints
        * 'show' => 1 // 1 if there is at least one relevant, non-hidden question within the group
        * );
        *
        * @var array
        */
        private $indexGseq;
        /**
        * array of group sequence number to static info
        * One entry per group, indexed on gseq
        *
        * @example [0] = array(
        * 'group_order' => 0   // gseq
        * 'gid' => "34" // group id
        * 'group_name' => 'G2' // the group title
        * 'description' => // the description of the group (e.g. gtitle)
        * 'grelevance' => '' // the group-level relevance
        * );
        *
        * @var array
        */
        private $gseq2info;

        /**
        * the maximum groupSeq reached -  this is needed for Index
        * @var int
        */
        private $maxGroupSeq;
        /**
        * the maximum Question reached sequencly ordered, used to show error to the user if we stop before this step with indexed survey.
        * In question by question mode : $maxQuestionSeq==$_SESSION['survey_'.surveyid]['maxstep'], use it ?
        * @var integer
        */
        private $maxQuestionSeq=-1;
        /**
        /**
        * mapping of questions to information about their subquestions.
        * One entry per question, indexed on qid
        *
        * @example [702] = array(
        * 'qid' => 702 // the question id
        * 'qseq' => 6 // the question sequence
        * 'gseq' => 0 // the group sequence
        * 'sgqa' => '26626X34X702' // the root of the SGQA code (reallly just the SGQ)
        * 'varName' => 'afSrcFilter_sq1' // the full qcode variable name - note, if there are sub-questions, don't use this one.
        * 'type' => 'M' // the one-letter question type
        * 'fieldname' => '26626X34X702sq1' // the fieldname (used as JavaScript variable name, and also as database column name
        * 'rootVarName' => 'afDS'  // the root variable name
        * 'preg' => '/[A-Z]+/' // regular expression validation equation, if any
        * 'subqs' => array() of sub-questions, where each contains:
        *     'rowdivid' => '26626X34X702sq1' // the javascript id identifying the question row (so array_filter can hide rows)
        *     'varName' => 'afSrcFilter_sq1' // the full variable name for the sub-question
        *     'jsVarName_on' => 'java26626X34X702sq1' // the JavaScript variable name if the variable is defined on the current page
        *     'jsVarName' => 'java26626X34X702sq1' // the JavaScript variable name to use if the variable is defined on a different page
        *     'csuffix' => 'sq1' // the SGQ suffix to use for a fieldname
        *     'sqsuffix' => '_sq1' // the suffix to use for a qcode variable name
        *  );
        *
        * @var array
        */
        private $q2subqInfo;
        /**
        * array of advanced question attributes for each question
        * Indexed by qid; available for all quetions
        *
        * @example [784] = array(
        * 'array_filter_exclude' => 'afSrcFilter'
        * 'exclude_all_others' => 'sq5'
        * 'max_answers' => '3'
        * 'min_answers' => '1'
        * 'other_replace_text' => '{afSrcFilter_other}'
        * );
        *
        * @var array
        */
        private $qattr;
        /**
        * list of needed sub-question relevance (e.g. array_filter)
        * Indexed by qid then sgqa; only generated for current group of questions
        *
        * @example [708][26626X37X708sq2] = array(
        * 'qid' => '708' // the question id
        * 'eqn' => "((26626X34X702sq2 != ''))" // the auto-generated sub-question-level relevance equation
        * 'prettyPrintEqn' => '' // only generated if there errors - shows syntax highlighting of them
        * 'result' => 0 // result of processing the sub-question-level relevance equation in the current context
        * 'numJsVars' => 1 // the number of on-page javascript variables in 'eqn'
        * 'relevancejs' => // the generated javascript from 'eqn' -- e.g. "LEMif(LEManyNA('26626X34X702sq2'),'',(((LEMval('26626X34X702sq2')  != ""))))"
        * 'relevanceVars' => "java26626X34X702sq2" // the pipe-separated list of on-page javascript variables in 'eqn'
        * 'rowdivid' => "26626X37X708sq2" // the javascript id of the question row (so can apply array_filter)
        * 'type' => 'array_filter' // semicolon delimited list of types of subquestion relevance filters applied
        * 'qtype' => 'A' // the single character question type
        * 'sgqa' => "26626X37X708" // the SGQ portion of the fieldname
        * 'hasErrors' => 0 // 1 if there are any parse errors in the sub-question validation equations
        * );
        *
        * @var array
        */
        private $subQrelInfo=array();
        /**
        * array of Group-level relevance status
        * Indexed by gseq; only shows groups that have been visited
        *
        * @example [1] = array(
        * 'gseq' => 1 // group sequence
        * 'eqn' => '' // the group-level relevance
        * 'result' => 1 // result of processing the group-level relevance
        * 'numJsVars' => 0 // the number of on-page javascript variables in the group-level relevance equation
        * 'relevancejs' => '' // the javascript version of the relevance equation
        * 'relevanceVars' => '' // the pipe-delimited list of on-page javascript variable names used within the group-level relevance equation
        * 'prettyPrint' => '' // a pretty-print version of the group-level relevance equation, only if there are errors
        * );
        *
        * @var array
        */
        private $gRelInfo=array();

        /**
        * Array of timing information to debug how long it takes for portions of LEM to run.
        * Array of timing information (in seconds) for EM to help with debugging
        *
        * @example [1] = array(
        *   [0]="LimeExpressionManager::NavigateForwards"
        *   [1]=1.7079849243164
        * );
        *
        * @var array
        */
        private $runtimeTimings=array();
        /**
        * True (1) if calling LimeExpressionManager functions between StartSurvey and FinishProcessingPage
        * Used (mostly deprecated) to detect calls to LEM which happen outside of the normal processing scope
        * @var boolean
        */
        private $initialized=false;
        /**
        * True (1) if have already processed the relevance equations (so don't need to do it again)
        *
        * @var boolean
        */
        private $processedRelevance=false;
        /**
        * Message generated to show debug timing values, if debugLevel includes LEM_DEBUG_TIMING
        * @var string
        */
        private $debugTimingMsg='';
        /**
        * temporary variable to reduce need to parse same equation multiple times.  Used for relevance and validation
        * Array, indexed on equation, providing the following information:
        *
        * @example ['!is_empty(num)'] = array(
        * 'result' => 1 // result of processing the equation in the current scope
        * 'prettyPrint' => '' // syntax-highlighted version of equation if there are any errors
        * 'hasErrors' => 0 // 1 if there are any syntax errors
        * );
        *
        * @var array
        */
        private $ParseResultCache;
        /**
        * array of 2nd scale answer lists for types ':' and ';' -- needed for convenient print of logic file
        * Indexed on qid; available for all questions
        *
        * @example [706] = array(
        * '1~1' => '1|Never',
        * '1~2' => '2|Sometimes',
        * '1~3' => '3|Always'
        * );
        *
        * @var array
        */
        private $multiflexiAnswers;

        /**
        * used to specify whether to  generate equations using SGQA codes or qcodes
        * Default is to convert all qcode naming to sgqa naming when generating javascript, as that provides the greatest backwards compatibility
        * TSV export of survey structure sets this to false so as to force use of qcode naming
        *
        * @var Boolean
        */
        private $sgqaNaming = true;
        /**
        * Number of groups in survey (number of possible pages to display)
        * @var integer
        */
        private $numGroups=0;
        /**
        * Numer of questions in survey (counting display-only ones?)
        * @var integer
        */
        private $numQuestions=0;
        /**
        * String identifier for the active session
        * @var string
        */
        private $sessid;
        /**
         * Linked list of array filters
         * @var array
         */
        private $qrootVarName2arrayFilter = array();
        /**
         * Array, keyed on qid, to JavaScript and list of variables needed to implement exclude_all_others_auto
         * @var array
         */
        private $qid2exclusiveAuto = array();
        /**
         * Array of values to be updated
         * @var array
         */
        private $updatedValues = array();

        /**
        * A private constructor; prevents direct creation of object
        */
        private function __construct()
        {
            self::$instance =& $this;
            $this->em = new ExpressionManager();
            if (!isset($_SESSION['LEMlang'])) {
                $_SESSION['LEMlang'] = 'en';    // so that there is a default
            }
        }

        /**
        * Ensures there is only one instances of LEM.  Note, if switch between surveys, have to clear this cache
        * @return LimeExpressionManager
        */
        public static function &singleton()
        {
            $now = microtime(true);
            if (isset($_SESSION['LEMdirtyFlag'])) {
                $c = __CLASS__;
                self::$instance = new $c;
                unset($_SESSION['LEMdirtyFlag']);
            }
            else if (!isset(self::$instance)) {
                    if (isset($_SESSION['LEMsingleton'])) {
                        self::$instance = unserialize($_SESSION['LEMsingleton']);
                    }
                    else {
                        $c = __CLASS__;
                        self::$instance = new $c;
                    }
                }
                else {
                    // does exist, and OK to cache
                    return self::$instance;
            }
            // only record duration if have to create (or unserialize) an instance
            self::$instance->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
            return self::$instance;
        }

        /**
        * Prevent users to clone the instance
        */
        public function __clone()
        {
            trigger_error('Clone is not allowed.', E_USER_ERROR);
        }

        /**
        * Set the previewmode
        * @param boolean|string $previewmode 'question', 'group', false
        * @return void
        */
        public static function SetPreviewMode($previewmode=false)
        {
            $LEM =& LimeExpressionManager::singleton();
            $LEM->sPreviewMode=$previewmode;
            //$_SESSION[$LEM->sessid]['previewmode']=$previewmode;
        }

        /**
        * Tells Expression Manager that something has changed enough that needs to eliminate internal caching
        * @return void
        */
        public static function SetDirtyFlag()
        {
            $_SESSION['LEMdirtyFlag'] = true;// For fieldmap and other. question help {HELP} is taken from fieldmap
            $_SESSION['LEMforceRefresh'] = true;// For Expression manager string
            /* Bug #09589 : update a survey don't reset actual test => Force reloading of survey */
            $iSessionSurveyId=self::getLEMsurveyId();
            if($aSessionSurvey=Yii::app()->session["survey_{$iSessionSurveyId}"])
            {
                $aSessionSurvey['LEMtokenResume']=true;
                Yii::app()->session["survey_{$iSessionSurveyId}"]=$aSessionSurvey;
            }
        }

        /**
        * Set the SurveyId - really checks whether the survey you're about to work with is new, and if so, clears the LEM cache
        * @param integer|null $sid
        */
        public static function SetSurveyId($sid=NULL)
        {
            if (!is_null($sid)) {
                if (isset($_SESSION['LEMsid']) && $sid != $_SESSION['LEMsid']) {
                    // then trying to use a new survey - so clear the LEM cache
                    self::SetDirtyFlag();
                }
                $_SESSION['LEMsid'] = $sid;
            }
        }

        /**
        * Sets the language for Expression Manager.  If the language has changed, then EM cache must be invalidated and refreshed
        * @param string|null $lang
        * @return void
        */
        public static function SetEMLanguage($lang=NULL)
        {
            if (is_null($lang)) {
                return; // should never happen
            }
            if (!isset($_SESSION['LEMlang'])) {
                $_SESSION['LEMlang'] = $lang;
            }
            if ($_SESSION['LEMlang'] != $lang) {
                // then changing languages, so clear cache
                self::SetDirtyFlag();
            }
            $_SESSION['LEMlang'] = $lang;
        }

        /**
        * Do bulk-update/save of Condition to Relevance
        * @param integer|null $surveyId - if NULL, processes the entire database, otherwise just the specified survey
        * @param integer|null $qid - if specified, just updates that one question
        * @return array of query strings
        */
        public static function UpgradeConditionsToRelevance($surveyId=NULL, $qid=NULL)
        {
            LimeExpressionManager::SetDirtyFlag();  // set dirty flag even if not conditions, since must have had a DB change
            // Cheat and upgrade question attributes here too.
            self::UpgradeQuestionAttributes(true,$surveyId,$qid);

            if (is_null($surveyId))
            {
                $sQuery='SELECT sid FROM {{surveys}}';
                $aSurveyIDs = Yii::app()->db->createCommand($sQuery)->queryColumn();
            }
            else{
                $aSurveyIDs=array($surveyId);
            }
            foreach ($aSurveyIDs as $surveyId )     {
                // echo $surveyId.'<br>';flush();@ob_flush();
                $releqns = self::ConvertConditionsToRelevance($surveyId,$qid);
                if ( !empty( $releqns) ) {
                    foreach ($releqns as $key=>$value)
                    {
                        $sQuery = "UPDATE {{questions}} SET relevance=".Yii::app()->db->quoteValue($value)." WHERE qid=".$key;
                        Yii::app()->db->createCommand($sQuery)->execute();
                    }
                }
            }

            LimeExpressionManager::SetDirtyFlag();
        }

        /**
        * This reverses UpgradeConditionsToRelevance().  It removes Relevance for questions that have Condition
        * @param integer|null $surveyId
        * @param integer|null $qid
        * @return int
        */
        public static function RevertUpgradeConditionsToRelevance($surveyId=NULL, $qid=NULL)
        {
            LimeExpressionManager::SetDirtyFlag();  // set dirty flag even if not conditions, since must have had a DB change
            $releqns = self::ConvertConditionsToRelevance($surveyId,$qid);
            $num = count($releqns);
            if ($num == 0) {
                return NULL;
            }

            foreach ($releqns as $key=>$value) {
                $query = "UPDATE {{questions}} SET relevance=1 WHERE qid=".$key;
                dbExecuteAssoc($query);
            }
            return count($releqns);
        }

         /**
        * Return array database name as key, LEM name as value
        * @example (['gender'] => '38612X10X145')
        * @param integer $iSurveyId
        * @return array
        **/
        public static function getLEMqcode2sgqa($iSurveyId){
                $LEM =& LimeExpressionManager::singleton();

                $LEM->SetEMLanguage(Survey::model()->findByPk($iSurveyId)->language);
                $LEM->SetSurveyId($iSurveyId);
                $LEM->StartProcessingPage(true,true);
                return $LEM->qcode2sgqa;
        }

        /**
        * If $qid is set, returns the relevance equation generated from conditions (or NULL if there are no conditions for that $qid)
        * If $qid is NULL, returns an array of relevance equations generated from Condition, keyed on the question ID
        * @param integer|null $surveyId
        * @param integer|null $qid - if passed, only generates relevance equation for that question - otherwise genereates for all questions with conditions
        * @return array of generated relevance strings, indexed by $qid
        */
        public static function ConvertConditionsToRelevance($surveyId=NULL, $qid=NULL)
        {
            $query = LimeExpressionManager::getConditionsForEM($surveyId,$qid);
            $aConditions=$query->readAll();
            $_qid = -1;
            $relevanceEqns = array();
            $scenarios = array();
            $relAndList = array();
            $relOrList = array();
            foreach($aConditions as $row)
            {
                $row['method']=trim($row['method']); //For Postgres
                if ($row['qid'] != $_qid)
                {
                    // output the values for prior question is there was one
                    if ($_qid != -1)
                    {
                        if (count($relOrList) > 0)
                        {
                            $relAndList[] = '(' . implode(' or ', $relOrList) . ')';
                        }
                        if (count($relAndList) > 0)
                        {
                            $scenarios[] = '(' . implode(' and ', $relAndList) . ')';
                        }
                        $relevanceEqn = implode(' or ', $scenarios);
                        $relevanceEqns[$_qid] = $relevanceEqn;
                    }

                    // clear for next question
                    $_qid = $row['qid'];
                    $_scenario = $row['scenario'];
                    $_cqid = $row['cqid'];
                    $_subqid = -1;
                    $relAndList = array();
                    $relOrList = array();
                    $scenarios = array();
                    $releqn = '';
                }
                if ($row['scenario'] != $_scenario)
                {
                    if (count($relOrList) > 0)
                    {
                        $relAndList[] = '(' . implode(' or ', $relOrList) . ')';
                    }
                    $scenarios[] = '(' . implode(' and ', $relAndList) . ')';
                    $relAndList = array();
                    $relOrList = array();
                    $_scenario = $row['scenario'];
                    $_cqid = $row['cqid'];
                    $_subqid = -1;
                }
                if ($row['cqid'] != $_cqid)
                {
                    $relAndList[] = '(' . implode(' or ', $relOrList) . ')';
                    $relOrList = array();
                    $_cqid = $row['cqid'];
                    $_subqid = -1;
                }

                // fix fieldnames
                if ($row['type'] == '' && preg_match('/^{.+}$/',$row['cfieldname'])) {
                    $fieldname = substr($row['cfieldname'],1,-1);    // {TOKEN:xxxx}
                    $subqid = $fieldname;
                    $value = $row['value'];
                }
                else if ($row['type'] == 'M' || $row['type'] == 'P') {
                    if (substr($row['cfieldname'],0,1) == '+') {
                        // if prefixed with +, then a fully resolved name
                        $fieldname = substr($row['cfieldname'],1) . '.NAOK';
                        $subqid = $fieldname;
                        $value = $row['value'];
                    }
                    else {
                        // else create name by concatenating two parts together
                        $fieldname = $row['cfieldname'] . $row['value'] . '.NAOK';
                        $subqid = $row['cfieldname'];
                        $value = 'Y';
                    }
                }
                else {
                    $fieldname = $row['cfieldname'] . '.NAOK';
                    $subqid = $fieldname;
                    $value = $row['value'];
                }
                if ($_subqid != -1 && $_subqid != $subqid)
                {
                    $relAndList[] = '(' . implode(' or ', $relOrList) . ')';
                    $relOrList = array();
                }
                $_subqid = $subqid;

                // fix values
                if (preg_match('/^@\d+X\d+X\d+.*@$/',$value)) {
                    $value = substr($value,1,-1);
                }
                else if (preg_match('/^{.+}$/',$value)) {
                        $value = substr($value,1,-1);
                    }
                    else if ($row['method'] == 'RX') {
                            if (!preg_match('#^/.*/$#',$value))
                            {
                                $value = '"/' . $value . '/"';  // if not surrounded by slashes, add them.
                            }
                    }
                    else {
                        $value = '"' . $value . '"';
                }

                // add equation
                if  ($row['method'] == 'RX')
                {
                    $relOrList[] = "regexMatch(" . $value . "," . $fieldname . ")";
                }
                else
                {
                    // Condition uses ' ' to mean not answered, but internally it is really stored as ''.  Fix this
                    if ($value === '" "' || $value == '""') {
                        if ($row['method'] == '==')
                        {
                            $relOrList[] = "is_empty(" . $fieldname . ")";
                        }
                        else if ($row['method'] == '!=')
                        {
                            $relOrList[] = "!is_empty(" . $fieldname . ")";
                        }
                        else
                        {
                            $relOrList[] = $fieldname . " " . $row['method'] . " " . $value;
                        }
                    }
                    else
                    {
                        if ($value == '"0"' || !preg_match('/^".+"$/',$value))
                        {
                            switch ($row['method'])
                            {
                                case '==':
                                case '<':
                                case '<=':
                                case '>=':
                                    $relOrList[] = '(!is_empty(' . $fieldname . ') && (' . $fieldname . " " . $row['method'] . " " . $value . '))';
                                    break;
                                case '!=':
                                    $relOrList[] = '(is_empty(' . $fieldname . ') || (' . $fieldname . " != " . $value . '))';
                                    break;
                                default:
                                    $relOrList[] = $fieldname . " " . $row['method'] . " " . $value;
                                    break;
                            }
                        }
                        else
                        {
                            switch ($row['method'])
                            {
                                case '<':
                                case '<=':
                                    $relOrList[] = '(!is_empty(' . $fieldname . ') && (' . $fieldname . " " . $row['method'] . " " . $value . '))';
                                    break;
                                default:
                                    $relOrList[] = $fieldname . " " . $row['method'] . " " . $value;
                                    break;
                            }
                        }
                    }
                }
                if (($row['cqid'] == 0 && preg_match('/^{TOKEN:([^}]*)}$/',$row['cfieldname']) && preg_match('/^{TOKEN:([^}]*)}$/',isset($previousCondition)?$previousCondition['cfieldname']:'')) || substr($row['cfieldname'],0,1) == '+') {
                    $_cqid = -1;    // forces this statement to be ANDed instead of being part of a cqid OR group (except for TOKEN fields that follow a a token field)
                }
                $previousCondition=$row;
            }
            // output last one
            if ($_qid != -1)
            {
                if (count($relOrList) > 0)
                {
                    $relAndList[] = '(' . implode(' or ', $relOrList) . ')';
                }
                if (count($relAndList) > 0)
                {
                    $scenarios[] = '(' . implode(' and ', $relAndList) . ')';
                }
                $relevanceEqn = implode(' or ', $scenarios);
                $relevanceEqns[$_qid] = $relevanceEqn;
            }
            if (is_null($qid)) {
                return $relevanceEqns;
            }
            else {
                if (isset($relevanceEqns[$qid]))
                {
                    $result = array();
                    $result[$qid] = $relevanceEqns[$qid];
                    return $result;
                }
                else
                {
                    return NULL;
                }
            }
        }

        /**
        * Return list of relevance equations generated from conditions
        * @param integer|null $surveyId
        * @param integer|null $qid
        * @return array of relevance equations, indexed by $qid
        */
        public static function UnitTestConvertConditionsToRelevance($surveyId=NULL, $qid=NULL)
        {
            $LEM =& LimeExpressionManager::singleton();
            return $LEM->ConvertConditionsToRelevance($surveyId, $qid);
        }

        /**
        * Process all question attributes that apply to EM
        * (1) Sub-question-level relevance:  e.g. array_filter, array_filter_exclude, relevance equations entered in SQ-mask
        * (2) Validations: e.g. min/max number of answers; min/max/eq sum of answers
        * @param integer|null $onlyThisQseq - only process these attributes for the specified question
        * @return void
        */
        public function _CreateSubQLevelRelevanceAndValidationEqns($onlyThisQseq=NULL)
        {
            //        $now = microtime(true);
            $this->subQrelInfo=array();  // reset it each time this is called
            $subQrels = array();    // array of sub-question-level relevance equations
            $validationEqn = array();
            $validationTips = array();    // array of visible tips for validation criteria, indexed by $qid

            // Associate these with $qid so that can be nested under appropriate question-level relevance
            foreach ($this->q2subqInfo as $qinfo)
            {
                if (!is_null($onlyThisQseq) && $onlyThisQseq != $qinfo['qseq']) {
                    continue;
                }
                else if (!$this->allOnOnePage && $this->currentGroupSeq != $qinfo['gseq']) {
                    continue; // only need subq relevance for current page.
                }
                $questionNum = $qinfo['qid'];
                $type = $qinfo['type'];
                $hasSubqs = (isset($qinfo['subqs']) && count($qinfo['subqs'] > 0));
                $qattr = isset($this->qattr[$questionNum]) ? $this->qattr[$questionNum] : array();
                if (isset($qattr['input_boxes']) && $qattr['input_boxes'] == '1')
                {
                    $input_boxes='1';
                }
                else
                {
                    $input_boxes='';
                }

                if (isset($qattr['value_range_allows_missing']) && $qattr['value_range_allows_missing'] == '1')
                {
                    $value_range_allows_missing = true;
                }
                else
                {
                    $value_range_allows_missing = false;
                }

                // array_filter
                // If want to filter question Q2 on Q1, where each have subquestions SQ1-SQ3, this is equivalent to relevance equations of:
                // relevance for Q2_SQ1 is Q1_SQ1!=''
                $array_filter = NULL;
                if (isset($qattr['array_filter']) && trim($qattr['array_filter']) != '')
                {
                    $array_filter = $qattr['array_filter'];
                    $this->qrootVarName2arrayFilter[$qinfo['rootVarName']]['array_filter'] = $array_filter;
                }

                // array_filter_exclude
                // If want to filter question Q2 on Q1, where each have subquestions SQ1-SQ3, this is equivalent to relevance equations of:
                // relevance for Q2_SQ1 is Q1_SQ1==''
                $array_filter_exclude = NULL;
                if (isset($qattr['array_filter_exclude']) && trim($qattr['array_filter_exclude']) != '')
                {
                    $array_filter_exclude = $qattr['array_filter_exclude'];
                    $this->qrootVarName2arrayFilter[$qinfo['rootVarName']]['array_filter_exclude'] = $array_filter_exclude;
                }

                // array_filter and array_filter_exclude get processed together
                if (!is_null($array_filter) || !is_null($array_filter_exclude))
                {
                    if ($hasSubqs) {
                        $cascadedAF = array();
                        $cascadedAFE = array();

                        list($cascadedAF, $cascadedAFE) = $this->_recursivelyFindAntecdentArrayFilters($qinfo['rootVarName'],array(),array());

                        $cascadedAF = array_reverse($cascadedAF);
                        $cascadedAFE = array_reverse($cascadedAFE);

                        $subqs = $qinfo['subqs'];
                        if ($type == 'R') {
                            $subqs = array();
                            foreach ($this->qans[$qinfo['qid']] as $k=>$v)
                            {
                                $_code = explode('~',$k);
                                $subqs[] = array(
                                    'rowdivid'=>$qinfo['sgqa'] . $_code[1],
                                    'sqsuffix'=>'_' . $_code[1],
                                );
                            }
                        }
                        $last_rowdivid = '--';
                        foreach ($subqs as $sq) {
                            if ($sq['rowdivid'] == $last_rowdivid)
                            {
                                continue;
                            }
                            $last_rowdivid = $sq['rowdivid'];
                            $af_names = array();
                            $afe_names = array();
                            switch ($type)
                            {
                                case '1':   //Array (Flexible Labels) dual scale
                                case ':': //ARRAY (Multi Flexi) 1 to 10
                                case ';': //ARRAY (Multi Flexi) Text
                                case 'A': //ARRAY (5 POINT CHOICE) radio-buttons
                                case 'B': //ARRAY (10 POINT CHOICE) radio-buttons
                                case 'C': //ARRAY (YES/UNCERTAIN/NO) radio-buttons
                                case 'E': //ARRAY (Increase/Same/Decrease) radio-buttons
                                case 'F': //ARRAY (Flexible) - Row Format
                                case 'L': //LIST drop-down/radio-button list
                                case 'M': //Multiple choice checkbox
                                case 'P': //Multiple choice with comments checkbox + text
                                case 'K': //MULTIPLE NUMERICAL QUESTION
                                case 'Q': //MULTIPLE SHORT TEXT
                                case 'R': //Ranking
//                                    if ($this->sgqaNaming)
//                                    {
                                        foreach ($cascadedAF as $_caf)
                                        {
                                            $sgq = ((isset($this->qcode2sgq[$_caf])) ? $this->qcode2sgq[$_caf] : $_caf);
                                            $fqid = explode('X',$sgq);
                                            if (!isset($fqid[2]))
                                            {
                                                continue;
                                            }
                                            $fqid = $fqid[2];
                                            if ($this->q2subqInfo[$fqid]['type'] == 'R')
                                            {
                                                $rankables = array();
                                                foreach ($this->qans[$fqid] as $k=>$v)
                                                {
                                                    $rankable = explode('~',$k);
                                                    $rankables[] = '_' . $rankable[1];
                                                }
                                                if (array_search($sq['sqsuffix'],$rankables) === false)
                                                {
                                                    continue;
                                                }
                                            }
                                            $fsqs = array();
                                            foreach ($this->q2subqInfo[$fqid]['subqs'] as $fsq)
                                            {
                                                if (!isset($fsq['csuffix'])) $fsq['csuffix']='';
                                                if ($this->q2subqInfo[$fqid]['type'] == 'R')
                                                {
                                                    // we know the suffix exists
                                                    $fsqs[] = '(' . $sgq . $fsq['csuffix'] . ".NAOK == '" . substr($sq['sqsuffix'],1) . "')";
                                                }
                                                else if ($this->q2subqInfo[$fqid]['type'] == ':' && isset($this->qattr[$fqid]['multiflexible_checkbox']) && $this->qattr[$fqid]['multiflexible_checkbox']=='1')
                                                {
                                                    if ($fsq['sqsuffix'] == $sq['sqsuffix'])
                                                    {
                                                        $fsqs[] = $sgq . $fsq['csuffix'] . '.NAOK=="1"';
                                                    }
                                                }
                                                else
                                                {
                                                    if ($fsq['sqsuffix'] == $sq['sqsuffix'])
                                                    {
                                                        $fsqs[] = '!is_empty(' . $sgq . $fsq['csuffix'] . '.NAOK)';
                                                    }
                                                }
                                            }
                                            if (count($fsqs) > 0)
                                            {
                                                $af_names[] = '(' . implode(' or ', $fsqs) . ')';
                                            }
                                        }
                                        foreach ($cascadedAFE as $_cafe)
                                        {
                                            $sgq = ((isset($this->qcode2sgq[$_cafe])) ? $this->qcode2sgq[$_cafe] : $_cafe);
                                            $fqid = explode('X',$sgq);
                                            if (!isset($fqid[2]))
                                            {
                                                continue;
                                            }
                                            $fqid = $fqid[2];
                                            if ($this->q2subqInfo[$fqid]['type'] == 'R')
                                            {
                                                $rankables = array();
                                                foreach ($this->qans[$fqid] as $k=>$v)
                                                {
                                                    $rankable = explode('~',$k);
                                                    $rankables[] = '_' . $rankable[1];
                                                }
                                                if (array_search($sq['sqsuffix'],$rankables) === false)
                                                {
                                                    continue;
                                                }
                                            }
                                            $fsqs = array();
                                            foreach ($this->q2subqInfo[$fqid]['subqs'] as $fsq)
                                            {
                                                if ($this->q2subqInfo[$fqid]['type'] == 'R')
                                                {
                                                    // we know the suffix exists
                                                    $fsqs[] = '(' . $sgq . $fsq['csuffix'] . ".NAOK != '" . substr($sq['sqsuffix'],1) . "')";
                                                }
                                                else if ($this->q2subqInfo[$fqid]['type'] == ':' && isset($this->qattr[$fqid]['multiflexible_checkbox']) && $this->qattr[$fqid]['multiflexible_checkbox']=='1')
                                                {
                                                    if ($fsq['sqsuffix'] == $sq['sqsuffix'])
                                                    {
                                                        $fsqs[] = $sgq . $fsq['csuffix'] . '.NAOK!="1"';
                                                    }
                                                }
                                                else
                                                {
                                                    if ($fsq['sqsuffix'] == $sq['sqsuffix'])
                                                    {
                                                        $fsqs[] = 'is_empty(' . $sgq . $fsq['csuffix'] . '.NAOK)';
                                                    }
                                                }
                                            }
                                            if (count($fsqs) > 0)
                                            {
                                                $afe_names[] = '(' . implode(' and ', $fsqs) . ')';
                                            }
                                        }
//                                    }
//                                    else  // TODO - implement qcode naming for this
//                                    {
//                                        foreach ($cascadedAF as $_caf)
//                                        {
//                                            $sgq = $_caf . $sq['sqsuffix'];
//                                            if (isset($this->knownVars[$sgq]))
//                                            {
//                                                $af_names[] = $sgq . '.NAOK';
//                                            }
//                                        }
//                                        foreach ($cascadedAFE as $_cafe)
//                                        {
//                                            $sgq = $_cafe . $sq['sqsuffix'];
//                                            if (isset($this->knownVars[$sgq]))
//                                            {
//                                                $afe_names[] = $sgq . '.NAOK';
//                                            }
//                                        }
//                                    }
                                    break;
                                default:
                                    break;
                            }
                            $af_names = array_unique($af_names);
                            $afe_names= array_unique($afe_names);

                            if (count($af_names) > 0 || count($afe_names) > 0) {
                                $afs_eqn = '';
                                if (count($af_names) > 0)
                                {
                                    $afs_eqn .= implode(' && ', $af_names);
                                }
                                if (count($afe_names) > 0)
                                {
                                    if ($afs_eqn != '')
                                    {
                                        $afs_eqn .= ' && ';
                                    }
                                    $afs_eqn .= implode(' && ', $afe_names);
                                }

                                $subQrels[] = array(
                                'qtype' => $type,
                                'type' => 'array_filter',
                                'rowdivid' => $sq['rowdivid'],
                                'eqn' => '(' . $afs_eqn . ')',
                                'qid' => $questionNum,
                                'sgqa' => $qinfo['sgqa'],
                                );
                            }
                        }
                    }
                }

                // individual subquestion relevance
                if ($hasSubqs &&
                    $type!='|' && $type!='!' && $type !='L' && $type !='O'
                )
                {
                    $subqs = $qinfo['subqs'];
                    $last_rowdivid = '--';
                    foreach ($subqs as $sq)
                    {
                        if ($sq['rowdivid'] == $last_rowdivid)
                        {
                            continue;
                        }
                        $last_rowdivid = $sq['rowdivid'];
                        $rowdivid=NULL;
                        $rowdivid=$sq['rowdivid'];
                        switch($type)
                        {
                            case '1': //Array (Flexible Labels) dual scale
                                $rowdivid = $rowdivid . '#0';
                                break;
                            case ':': //ARRAY Numbers
                            case ';': //ARRAY Text
                                $aCsuffix=(explode('_',$sq['csuffix']));
                                $rowdivid = $rowdivid . '_'.$aCsuffix[1];
                                break;
                            case 'A': //ARRAY (5 POINT CHOICE) radio-buttons
                            case 'B': //ARRAY (10 POINT CHOICE) radio-buttons
                            case 'C': //ARRAY (YES/UNCERTAIN/NO) radio-buttons
                            case 'E': //ARRAY (Increase/Same/Decrease) radio-buttons
                            case 'F': //ARRAY (Flexible) - Row Format
                            case 'M': //Multiple choice checkbox
                            case 'P': //Multiple choice with comments checkbox + text
                            case 'K': //MULTIPLE NUMERICAL QUESTION
                            case 'Q': //MULTIPLE SHORT TEXT
                                break;
                            default:
                                break;
                        }

                        if (isset($this->knownVars[$rowdivid]['SQrelevance']) & $this->knownVars[$rowdivid]['SQrelevance']!='')
                        {
                            $subQrels[] = array(
                            'qtype' => $type,
                            'type' => 'SQ_relevance',
                            'rowdivid' => $sq['rowdivid'],
                            'eqn' => $this->knownVars[$rowdivid]['SQrelevance'],
                            'qid' => $questionNum,
                            'sgqa' => $qinfo['sgqa'],
                            );
                        }
                    }
                }

                // code_filter:  WZ
                // This can be skipped, since question types 'W' (list-dropdown-flexible) and 'Z'(list-radio-flexible) are no longer supported

                // Default validation for question type
                switch ($type)
                {
                    case 'I':
                    case '!':
                    case 'O':
                    case 'M': //NUMERICAL QUESTION TYPE
                    case 'L': //LIST drop-down/radio-button list
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'default',
                            'class' => 'default',
                            'eqn' =>  '1',
                            'qid' => $questionNum,
                            );
                        break;

                    case 'N': //NUMERICAL QUESTION TYPE
                        if ($hasSubqs) {
                            $subqs = $qinfo['subqs'];
                            $sq_equs=array();
                            foreach($subqs as $sq)
                            {
                                $sq_name = ($this->sgqaNaming)?$sq['rowdivid'].".NAOK":$sq['varName'].".NAOK";
                                $sq_equs[] = '( is_numeric('.$sq_name.') || is_empty('.$sq_name.') )';// Leave mandatory to mandatory attribute
                                if($type=="K")
                                    $subqValidSelector = $sq['jsVarName_on'];
                                else
                                    $subqValidSelector = "";
                            }
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'default',
                            'class' => 'default',
                            'eqn' =>  implode(' and ',$sq_equs),
                            'qid' => $questionNum,
                            );
                        }
                        break;
                    case 'K': //MULTI NUMERICAL QUESTION TYPE
                        if ($hasSubqs) {
                            $subqs = $qinfo['subqs'];
                            $sq_equs=array();
                            $subqValidEqns = array();
                            foreach($subqs as $sq)
                            {
                                $sq_name = ($this->sgqaNaming)?$sq['rowdivid'].".NAOK":$sq['varName'].".NAOK";
                                $sq_equ = '( is_numeric('.$sq_name.') || is_empty('.$sq_name.') )';// Leave mandatory to mandatory attribute
                                $subqValidSelector = $sq['jsVarName_on'];
                                if (!is_null($sq_name)) {
                                    $sq_equs[] = $sq_equ;
                                    $subqValidEqns[$subqValidSelector] = array(
                                    'subqValidEqn' => $sq_equ,
                                    'subqValidSelector' => $subqValidSelector,
                                    );
                                }
                            }
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'default',
                            'class' => 'default',
                            'eqn' =>  implode(' and ',$sq_equs),
                            'qid' => $questionNum,
                            'subqValidEqns' => $subqValidEqns,
                            );
                        }
                        break;
                    case 'R':
                        if ($hasSubqs) {
                            $subqs = $qinfo['subqs'];
                            $sq_names=array();
                            $sq_eqPart=array();
                            foreach($subqs as $subq)
                            {
                                $sq_names[] = $subq['varName'].".NAOK";
                                $sq_eqPart[] = "intval(!is_empty({$subq['varName']}.NAOK))*{$subq['csuffix']}";
                            }
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'default',
                            'class' => 'default',
                            'eqn' =>  'unique(' . implode(',',$sq_names) . ') and count(' . implode(',',$sq_names) . ')==max('. implode(',',$sq_eqPart) .')',
                            'qid' => $questionNum,
                            );
                        }
                        break;
                    case 'D':
                        // TODO: generic validation as to dateformat[SGQA].value : BUT not same in PHP and JS
                        break;
                    default:
                        break;
                }

                // commented_checkbox : only for checkbox with comment ("P")
                $commented_checkbox='';
                if (isset($qattr['commented_checkbox']) && trim($qattr['commented_checkbox']) != '')
                {
                    switch ($type)
                    {
                        case 'P':
                            if ($hasSubqs) {
                                $commented_checkbox=$qattr['commented_checkbox'];
                                $subqs = $qinfo['subqs'];
                                switch ($commented_checkbox)
                                {
                                    case 'checked':
                                        $sq_eqn_commented_checkbox=array();
                                        foreach($subqs as $subq)
                                        {
                                            $sq_eqn_commented_checkbox[] = "(is_empty({$subq['varName']}.NAOK) and !is_empty({$subq['varName']}comment.NAOK))";
                                        }
                                        $eqn="sum(".implode(",",$sq_eqn_commented_checkbox).")==0";
                                        break;
                                    case 'unchecked':
                                        $sq_eqn_commented_checkbox=array();
                                        foreach($subqs as $subq)
                                        {
                                            $sq_eqn_commented_checkbox[] = "(!is_empty({$subq['varName']}.NAOK) and !is_empty({$subq['varName']}comment.NAOK))";
                                        }
                                        $eqn="sum(".implode(",",$sq_eqn_commented_checkbox).")==0";
                                        break;
                                    case 'allways':
                                    default:
                                        break;
                                }
                                if($commented_checkbox!="allways")
                                {
                                    if (!isset($validationEqn[$questionNum]))
                                    {
                                        $validationEqn[$questionNum] = array();
                                    }
                                    $validationEqn[$questionNum][] = array(
                                    'qtype' => $type,
                                    'type' => 'commented_checkbox',
                                    'class' => 'commented_checkbox',
                                    'eqn' =>  $eqn,
                                    'qid' => $questionNum,
                                    );
                                }
                            }
                            break;
                        default:
                            break;
                    }
                }

                // dropdown_dates
                // dropdown box: validate that a complete date is entered
                if (isset($qattr['dropdown_dates']) && $qattr['dropdown_dates'])
                {
                    $dropdown_dates = $qattr['dropdown_dates'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        $subqValidEqns = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case 'D': //DATE QUESTION TYPE
                                    $sq_name = ($this->sgqaNaming)?$sq['rowdivid'].".NAOK":$sq['varName'].".NAOK";
                                    $sq_name = '('.$sq_name.'!="INVALID")';
                                    $subqValidSelector = '';
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                                $subqValidEqns[$subqValidSelector] = array(
                                'subqValidEqn' => $sq_name,
                                'subqValidSelector' => $subqValidSelector,
                                );
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'dropdown_dates',
                            'class' => 'dropdown_dates',
                            'eqn' => implode(' && ', $sq_names),
                            'qid' => $questionNum,
                            'subqValidEqns' => $subqValidEqns,
                            );
                        }
                    }
                }
                else
                {
                    $dropdown_dates='';
                }
                // date_min
                // Maximum date allowed in date question
                if (isset($qattr['date_min']) && trim($qattr['date_min']) != '')
                {
                    $date_min = $qattr['date_min'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        $subqValidEqns = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case 'D': //DATE QUESTION TYPE
                                    // date_min: Determine whether we have an expression, a full date (YYYY-MM-DD) or only a year(YYYY)
                                    if (trim($qattr['date_min'])!='')
                                    {
                                        $mindate=$qattr['date_min'];
                                        if ((strlen($mindate)==4) && ($mindate>=1900) && ($mindate<=2099))
                                        {
                                            // backward compatibility: if only a year is given, add month and day
                                            $date_min='\''.$mindate.'-01-01'.' 00:00\'';
                                        }
                                        elseif (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])/",$mindate))
                                        {
                                            $date_min='\''.$mindate.' 00:00\'';
                                        }
                                        elseif (array_key_exists($date_min, $this->qcode2sgqa))  // refers to another question
                                        {
                                            $date_min=$date_min.'.NAOK';
                                        }
                                    }

                                    $sq_name = ($this->sgqaNaming)?$sq['rowdivid'].".NAOK":$sq['varName'].".NAOK";
                                    $sq_name = '(is_empty(' . $sq_name . ') || ('. $sq_name . ' >= date("Y-m-d H:i", strtotime(' . $date_min . ')) ))';
                                    $subqValidSelector = '';
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                                $subqValidEqns[$subqValidSelector] = array(
                                'subqValidEqn' => $sq_name,
                                'subqValidSelector' => $subqValidSelector,
                                );
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'date_min',
                            'class' => 'value_range',
                            'eqn' => implode(' && ', $sq_names),
                            'qid' => $questionNum,
                            'subqValidEqns' => $subqValidEqns,
                            );
                        }
                    }
                }
                else
                {
                    $date_min='';
                }

                // date_max
                // Maximum date allowed in date question
                if (isset($qattr['date_max']) && trim($qattr['date_max']) != '')
                {
                    $date_max = $qattr['date_max'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        $subqValidEqns = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case 'D': //DATE QUESTION TYPE
                                    // date_max: Determine whether we have an expression, a full date (YYYY-MM-DD) or only a year(YYYY)
                                    if (trim($qattr['date_max'])!='')
                                    {
                                        $maxdate=$qattr['date_max'];
                                        if ((strlen($maxdate)==4) && ($maxdate>=1900) && ($maxdate<=2099))
                                        {
                                            // backward compatibility: if only a year is given, add month and day
                                            $date_max='\''.$maxdate.'-12-31 23:59'.'\'';
                                        }
                                        elseif (preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])/",$maxdate))
                                        {
                                            $date_max='\''.$maxdate.' 23:59\'';
                                        }
                                        elseif (array_key_exists($date_max, $this->qcode2sgqa))  // refers to another question
                                        {
                                            $date_max=$date_max.'.NAOK';
                                        }
                                    }

                                    $sq_name = ($this->sgqaNaming)?$sq['rowdivid'].".NAOK":$sq['varName'].".NAOK";
                                    $sq_name = '(is_empty(' . $sq_name . ') || is_empty(' . $date_max . ') || ('. $sq_name . ' <= date("Y-m-d H:i", strtotime(' . $date_max . ')) ))';
                                    $subqValidSelector = '';
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                                $subqValidEqns[$subqValidSelector] = array(
                                'subqValidEqn' => $sq_name,
                                'subqValidSelector' => $subqValidSelector,
                                );
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'date_max',
                            'class' => 'value_range',
                            'eqn' => implode(' && ', $sq_names),
                            'qid' => $questionNum,
                            'subqValidEqns' => $subqValidEqns,
                            );
                        }
                    }
                }
                else
                {
                    $date_max='';
                }

                // equals_num_value
                // Validation:= sum(sq1,...,sqN) == value (which could be an expression).
                if (isset($qattr['equals_num_value']) && trim($qattr['equals_num_value']) != '')
                {
                    $equals_num_value = $qattr['equals_num_value'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case 'K': //MULTIPLE NUMERICAL QUESTION
                                    if ($this->sgqaNaming)
                                    {
                                        $sq_name = $sq['rowdivid'] . '.NAOK';
                                    }
                                    else
                                    {
                                        $sq_name = $sq['varName'] . '.NAOK';
                                    }
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            // sumEqn and sumRemainingEqn may need to be rounded if using sliders
                            $precision=LEM_DEFAULT_PRECISION;    // default is not to round
                            if (isset($qattr['slider_layout']) && $qattr['slider_layout']=='1')
                            {
                                $precision=0;   // default is to round to whole numbers
                                if (isset($qattr['slider_accuracy']) && trim($qattr['slider_accuracy'])!='')
                                {
                                    $slider_accuracy = $qattr['slider_accuracy'];
                                    $_parts = explode('.',$slider_accuracy);
                                    if (isset($_parts[1]))
                                    {
                                        $precision = strlen($_parts[1]);    // number of digits after mantissa
                                    }
                                }
                            }
                            $sumEqn = 'sum(' . implode(', ', $sq_names) . ')';
                            $sumRemainingEqn = '(' . $equals_num_value . ' - sum(' . implode(', ', $sq_names) . '))';
                            $mainEqn = 'sum(' . implode(', ', $sq_names) . ')';

                            if (!is_null($precision))
                            {
                                $sumEqn = 'round(' . $sumEqn . ', ' . $precision . ')';
                                $sumRemainingEqn = 'round(' . $sumRemainingEqn . ', ' . $precision . ')';
                                $mainEqn = 'round(' . $mainEqn . ', ' . $precision . ')';
                            }

                            $noanswer_option = '';
                            if ($value_range_allows_missing)
                            {
                                $noanswer_option = ' || count(' . implode(', ', $sq_names) . ') == 0';
                            }

                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'equals_num_value',
                            'class' => 'sum_range',
                            'eqn' =>  ($qinfo['mandatory']=='Y')?'(' . $mainEqn . ' == (' . $equals_num_value . '))':'(' . $mainEqn . ' == (' . $equals_num_value . ')' . $noanswer_option . ')',
                            'qid' => $questionNum,
                            'sumEqn' => $sumEqn,
                            'sumRemainingEqn' => $sumRemainingEqn,
                            );
                        }
                    }
                }
                else
                {
                    $equals_num_value='';
                }

                // exclude_all_others
                // If any excluded options are true (and relevant), then disable all other input elements for that question
                if (isset($qattr['exclude_all_others']) && trim($qattr['exclude_all_others']) != '')
                {
                    $exclusive_options = explode(';',$qattr['exclude_all_others']);
                    if ($hasSubqs) {
                        foreach ($exclusive_options as $exclusive_option)
                        {
                            $exclusive_option = trim($exclusive_option);
                            if ($exclusive_option == '') {
                                continue;
                            }
                            $subqs = $qinfo['subqs'];
                            $sq_names = array();
                            foreach ($subqs as $sq) {
                                $sq_name = NULL;
                                if ($sq['csuffix'] == $exclusive_option)
                                {
                                    continue;   // so don't make the excluded option irrelevant
                                }
                                switch ($type)
                                {
                                    case ':': //ARRAY (Multi Flexi) 1 to 10
                                    case 'A': //ARRAY (5 POINT CHOICE) radio-buttons
                                    case 'B': //ARRAY (10 POINT CHOICE) radio-buttons
                                    case 'C': //ARRAY (YES/UNCERTAIN/NO) radio-buttons
                                    case 'E': //ARRAY (Increase/Same/Decrease) radio-buttons
                                    case 'F': //ARRAY (Flexible) - Row Format
                                    case 'M': //Multiple choice checkbox
                                    case 'P': //Multiple choice with comments checkbox + text
                                    case 'K': //MULTIPLE NUMERICAL QUESTION
                                    case 'Q': //MULTIPLE SHORT TEXT
                                        if ($this->sgqaNaming)
                                        {
                                            $sq_name = $qinfo['sgqa'] . trim($exclusive_option) . '.NAOK';
                                        }
                                        else
                                        {
                                            $sq_name = $qinfo['sgqa'] . trim($exclusive_option) . '.NAOK';
                                        }
                                        break;
                                    default:
                                        break;
                                }
                                if (!is_null($sq_name)) {
                                    $subQrels[] = array(
                                    'qtype' => $type,
                                    'type' => 'exclude_all_others',
                                    'rowdivid' => $sq['rowdivid'],
                                    'eqn' => 'is_empty(' . $sq_name . ')',
                                    'qid' => $questionNum,
                                    'sgqa' => $qinfo['sgqa'],
                                    );
                                }
                            }
                        }
                    }
                }
                else
                {
                    $exclusive_option = '';
                }

                // exclude_all_others_auto
                // if (count(this.relevanceStatus) == count(this)) { set exclusive option value to "Y" and call checkconditions() }
                // However, note that would need to blank the values, not use relevance, otherwise can't unclick the _auto option without having it re-enable itself
                if (isset($qattr['exclude_all_others_auto']) && trim($qattr['exclude_all_others_auto']) == '1'
                        && isset($qattr['exclude_all_others']) && trim($qattr['exclude_all_others']) != '' && count(explode(';',trim($qattr['exclude_all_others']))) == 1)
                {
                    $exclusive_option = trim($qattr['exclude_all_others']);
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case 'M': //Multiple choice checkbox
                                case 'P': //Multiple choice with comments checkbox + text
                                    if ($this->sgqaNaming)
                                    {
                                        $sq_name = substr($sq['jsVarName'],4);
                                    }
                                    else
                                    {
                                        $sq_name = $sq['varName'];
                                    }
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name))
                            {
                                if ($sq['csuffix'] == $exclusive_option)
                                {
                                    $eoVarName = substr($sq['jsVarName'],4);
                                }
                                else
                                {
                                    $sq_names[] = $sq_name;
                                }
                            }
                        }
                        if (count($sq_names) > 0 && isset($eoVarName)) { // eoVarName not set : exclude option don't exist in sub question code
                            $relpart = "sum(" . implode(".relevanceStatus, ", $sq_names) . ".relevanceStatus)";
                            $checkedpart = "count(" . implode(".NAOK, ", $sq_names) . ".NAOK)";
                            $eoRelevantAndUnchecked = "(" . $eoVarName . ".relevanceStatus && is_empty(" . $eoVarName . "))";
                            $eoEqn = "(" . $eoRelevantAndUnchecked . " && (" . $relpart . " == " . $checkedpart . "))";

                            $this->em->ProcessBooleanExpression($eoEqn, $qinfo['gseq'], $qinfo['qseq']);

                            $relevanceVars = implode('|',$this->em->GetJSVarsUsed());
                            $relevanceJS = $this->em->GetJavaScriptEquivalentOfExpression();

                            // Unset all checkboxes and hidden values for this question (irregardless of whether they are array filtered)
                            $eosaJS = "if (" . $relevanceJS . ") {\n";
                            $eosaJS .="  $('#question" . $questionNum . " [type=checkbox]').prop('checked',false);\n";
                            $eosaJS .="  $('#java" . $qinfo['sgqa'] . "other').val('');\n";
                            $eosaJS .="  $('#answer" . $qinfo['sgqa'] . "other').val('');\n";
                            $eosaJS .="  $('[id^=java" . $qinfo['sgqa'] . "]').val('');\n";
                            $eosaJS .="  $('#answer" . $eoVarName . "').prop('checked',true);\n";
                            $eosaJS .="  $('#java" . $eoVarName . "').val('Y');\n";
                            $eosaJS .="  LEMrel" . $questionNum . "();\n";
                            $eosaJS .="  relChange" . $questionNum ."=true;\n";
                            $eosaJS .="}\n";

                            $this->qid2exclusiveAuto[$questionNum] = array(
                                'js'=>$eosaJS,
                                'relevanceVars'=>$relevanceVars,    // so that EM knows which variables to declare
                                'rowdivid'=>$eoVarName, // to ensure that EM creates a hidden relevanceSGQA input for the exclusive option
                                );
                        }
                    }
                }
                // input_boxes
                if (isset($qattr['input_boxes']) && $qattr['input_boxes'] == 1) {
                    $input_boxes=1;
                    switch($type)
                    {
                        case ':': //Array Numbers
                            if ($hasSubqs) {
                                $subqs = $qinfo['subqs'];
                                $sq_equs=array();
                                $subqValidEqns = array();
                                foreach($subqs as $sq)
                                {
                                    $sq_name = ($this->sgqaNaming)?substr($sq['jsVarName'],4).".NAOK":$sq['varName'].".NAOK";
                                    $sq_equ = '( is_numeric('.$sq_name.') || is_empty('.$sq_name.') )';// Leave mandatory to mandatory attribute (see #08665)
                                    $subqValidSelector = $sq['jsVarName_on'];
                                    if (!is_null($sq_name)) {
                                        $sq_equs[] = $sq_equ;
                                        $subqValidEqns[$subqValidSelector] = array(
                                        'subqValidEqn' => $sq_equ,
                                        'subqValidSelector' => $subqValidSelector,
                                        );
                                    }
                                }
                                if (!isset($validationEqn[$questionNum]))
                                {
                                    $validationEqn[$questionNum] = array();
                                }
                                $validationEqn[$questionNum][] = array(
                                'qtype' => $type,
                                'type' => 'input_boxes',
                                'class' => 'input_boxes',
                                'eqn' =>  implode(' and ',$sq_equs),
                                'qid' => $questionNum,
                                'subqValidEqns' => $subqValidEqns,
                                );
                            }
                            break;
                        default:
                            break;
                    }
                }else{
                    $input_boxes="";
                }

                // min_answers
                // Validation:= count(sq1,...,sqN) >= value (which could be an expression).
                if (isset($qattr['min_answers']) && trim($qattr['min_answers']) != '' && trim($qattr['min_answers']) != '0')
                {
                    $min_answers = $qattr['min_answers'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case '1':   //Array (Flexible Labels) dual scale
                                    if (substr($sq['varName'],-1,1) == '0')
                                    {
                                        if ($this->sgqaNaming)
                                        {
                                            $base = $sq['rowdivid']."#";
                                            $sq_name = "if(count(" . $base . "0.NAOK," . $base . "1.NAOK)==2,1,'')";
                                        }
                                        else
                                        {
                                            $base = substr($sq['varName'],0,-1);
                                            $sq_name = "if(count(" . $base . "0.NAOK," . $base . "1.NAOK)==2,1,'')";
                                        }
                                    }
                                    break;
                                case ':': //ARRAY (Multi Flexi) 1 to 10
                                case ';': //ARRAY (Multi Flexi) Text
                                case 'A': //ARRAY (5 POINT CHOICE) radio-buttons
                                case 'B': //ARRAY (10 POINT CHOICE) radio-buttons
                                case 'C': //ARRAY (YES/UNCERTAIN/NO) radio-buttons
                                case 'E': //ARRAY (Increase/Same/Decrease) radio-buttons
                                case 'F': //ARRAY (Flexible) - Row Format
                                case 'K': //MULTIPLE NUMERICAL QUESTION
                                case 'Q': //MULTIPLE SHORT TEXT
                                case 'M': //Multiple choice checkbox
                                case 'R': //RANKING STYLE
                                    if ($this->sgqaNaming)
                                    {
                                        $sq_name = substr($sq['jsVarName'],4) . '.NAOK';
                                    }
                                    else
                                    {
                                        $sq_name = $sq['varName'] . '.NAOK';
                                    }
                                    break;
                                case 'P': //Multiple choice with comments checkbox + text
                                    if (!preg_match('/comment$/',$sq['varName'])) {
                                        if ($this->sgqaNaming)
                                        {
                                            $sq_name = $sq['rowdivid'] . '.NAOK';
                                        }
                                        else
                                        {
                                            $sq_name = $sq['rowdivid'] . '.NAOK';
                                        }
                                    }
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'min_answers',
                            'class' => 'num_answers',
                            'eqn' => 'if(is_empty('.$min_answers.'),1,(count(' . implode(', ', $sq_names) . ') >= (' . $min_answers . ')))',
                            'qid' => $questionNum,
                            );
                        }
                    }
                }
                else
                {
                    $min_answers='';
                }

                // max_answers
                // Validation:= count(sq1,...,sqN) <= value (which could be an expression).
                if (isset($qattr['max_answers']) && trim($qattr['max_answers']) != '')
                {
                    $max_answers = $qattr['max_answers'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case '1':   //Array (Flexible Labels) dual scale
                                    if (substr($sq['varName'],-1,1) == '0')
                                    {
                                        if ($this->sgqaNaming)
                                        {
                                            $base = $sq['rowdivid']."#";
                                            $sq_name = "if(count(" . $base . "0.NAOK," . $base . "1.NAOK)==2,1,'')";
                                        }
                                        else
                                        {
                                            $base = substr($sq['varName'],0,-1);
                                            $sq_name = "if(count(" . $base . "0.NAOK," . $base . "1.NAOK)==2,1,'')";
                                        }
                                    }
                                    break;
                                case ':': //ARRAY (Multi Flexi) 1 to 10
                                case ';': //ARRAY (Multi Flexi) Text
                                case 'A': //ARRAY (5 POINT CHOICE) radio-buttons
                                case 'B': //ARRAY (10 POINT CHOICE) radio-buttons
                                case 'C': //ARRAY (YES/UNCERTAIN/NO) radio-buttons
                                case 'E': //ARRAY (Increase/Same/Decrease) radio-buttons
                                case 'F': //ARRAY (Flexible) - Row Format
                                case 'K': //MULTIPLE NUMERICAL QUESTION
                                case 'Q': //MULTIPLE SHORT TEXT
                                case 'M': //Multiple choice checkbox
                                case 'R': //RANKING STYLE
                                    if ($this->sgqaNaming)
                                    {
                                        $sq_name = substr($sq['jsVarName'],4) . '.NAOK';
                                    }
                                    else
                                    {
                                        $sq_name = $sq['varName'] . '.NAOK';
                                    }
                                    break;
                                case 'P': //Multiple choice with comments checkbox + text
                                    if (!preg_match('/comment$/',$sq['varName'])) {
                                        if ($this->sgqaNaming)
                                        {
                                            $sq_name = $sq['rowdivid'] . '.NAOK';
                                        }
                                        else
                                        {
                                            $sq_name = $sq['varName'] . '.NAOK';
                                        }
                                    }
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'max_answers',
                            'class' => 'num_answers',
                            'eqn' => '(if(is_empty('.$max_answers.'),1,count(' . implode(', ', $sq_names) . ') <= (' . $max_answers . ')))',
                            'qid' => $questionNum,
                            );

                        }
                    }
                }
                else
                {
                    $max_answers='';
                }
                /* Specific for ranking : fix only the alert : test if needed (max_subquestions < count(answers) )*/
                if($type=='R' && (isset($qattr['max_subquestions']) && intval($qattr['max_subquestions'])>0))
                {
                    $max_subquestions=intval($qattr['max_subquestions']);
                    // We don't have another answer count in EM ?
                    $answerCount=Answer::model()->count("qid=:qid and language=:language",array(":qid"=>$questionNum,'language'=>$_SESSION['LEMlang']));
                    if($max_subquestions < $answerCount)
                    {
                        if($max_answers!='')
                        {
                            $max_answers='min('.$max_answers.','.$max_subquestions.')';
                        }
                        else
                        {
                            $max_answers=intval($qattr['max_subquestions']);
                        }
                    }
                }
                // Fix min_num_value_n and max_num_value_n for multinumeric with slider: see bug #7798
                if($type=="K" && isset($qattr['slider_min']) && ( !isset($qattr['min_num_value_n']) || trim($qattr['min_num_value_n'])==''))
                    $qattr['min_num_value_n']=$qattr['slider_min'];
                // min_num_value_n
                // Validation:= N >= value (which could be an expression).
                if (isset($qattr['min_num_value_n']) && trim($qattr['min_num_value_n']) != '')
                {
                    $min_num_value_n = $qattr['min_num_value_n'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        $subqValidEqns = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case 'K': //MULTIPLE NUMERICAL QUESTION
                                    if ($this->sgqaNaming)
                                    {
                                        $sq_name = '(is_empty(' . $sq['rowdivid'] . '.NAOK) || '. $sq['rowdivid'] . '.NAOK >= (' . $min_num_value_n . '))';
                                    }
                                    else
                                    {
                                        $sq_name = '(is_empty(' . $sq['varName'] . '.NAOK) || '. $sq['varName'] . '.NAOK >= (' . $min_num_value_n . '))';
                                    }
                                    $subqValidSelector = $sq['jsVarName_on'];
                                    break;
                                case 'N': //NUMERICAL QUESTION TYPE
                                    if ($this->sgqaNaming)
                                    {
                                        $sq_name = '(is_empty(' . $sq['rowdivid'] . '.NAOK) || '. $sq['rowdivid'] . '.NAOK >= (' . $min_num_value_n . '))';
                                    }
                                    else
                                    {
                                        $sq_name = '(is_empty(' . $sq['varName'] . '.NAOK) || '. $sq['varName'] . '.NAOK >= (' . $min_num_value_n . '))';
                                    }
                                    $subqValidSelector = '';
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                                $subqValidEqns[$subqValidSelector] = array(
                                'subqValidEqn' => $sq_name,
                                'subqValidSelector' => $subqValidSelector,
                                );
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'min_num_value_n',
                            'class' => 'value_range',
                            'eqn' => implode(' && ', $sq_names),
                            'qid' => $questionNum,
                            'subqValidEqns' => $subqValidEqns,
                            );
                        }
                    }
                }
                else
                {
                    $min_num_value_n='';
                }

                // Fix min_num_value_n and max_num_value_n for multinumeric with slider: see bug #7798
                if($type=="K" && isset($qattr['slider_max']) && ( !isset($qattr['max_num_value_n']) || trim($qattr['max_num_value_n'])==''))
                    $qattr['max_num_value_n']=$qattr['slider_max'];
                // max_num_value_n
                // Validation:= N <= value (which could be an expression).
                if (isset($qattr['max_num_value_n']) && trim($qattr['max_num_value_n']) != '')
                {
                    $max_num_value_n = $qattr['max_num_value_n'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        $subqValidEqns = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case 'K': //MULTIPLE NUMERICAL QUESTION
                                    if ($this->sgqaNaming)
                                    {
                                        $sq_name = '(is_empty(' . $sq['rowdivid'] . '.NAOK) || '. $sq['rowdivid'] . '.NAOK <= (' . $max_num_value_n . '))';
                                    }
                                    else
                                    {
                                        $sq_name = '(is_empty(' . $sq['varName'] . '.NAOK) || '. $sq['varName'] . '.NAOK <= (' . $max_num_value_n . '))';
                                    }
                                    $subqValidSelector = $sq['jsVarName_on'];
                                    break;
                                case 'N': //NUMERICAL QUESTION TYPE
                                    if ($this->sgqaNaming)
                                    {
                                        $sq_name = '(is_empty(' . $sq['rowdivid'] . '.NAOK) || '. $sq['rowdivid'] . '.NAOK <= (' . $max_num_value_n . '))';
                                    }
                                    else
                                    {
                                        $sq_name = '(is_empty(' . $sq['varName'] . '.NAOK) || '. $sq['varName'] . '.NAOK <= (' . $max_num_value_n . '))';
                                    }
                                    $subqValidSelector = '';
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                                $subqValidEqns[$subqValidSelector] = array(
                                'subqValidEqn' => $sq_name,
                                'subqValidSelector' => $subqValidSelector,
                                );
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'max_num_value_n',
                            'class' => 'value_range',
                            'eqn' => implode(' && ', $sq_names),
                            'qid' => $questionNum,
                            'subqValidEqns' => $subqValidEqns,
                            );
                        }
                    }
                }
                else
                {
                    $max_num_value_n='';
                }

                // min_num_value
                // Validation:= sum(sq1,...,sqN) >= value (which could be an expression).
                if (isset($qattr['min_num_value']) && trim($qattr['min_num_value']) != '')
                {
                    $min_num_value = $qattr['min_num_value'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case 'K': //MULTIPLE NUMERICAL QUESTION
                                    if ($this->sgqaNaming)
                                    {
                                        $sq_name = $sq['rowdivid'] . '.NAOK';
                                    }
                                    else
                                    {
                                        $sq_name = $sq['varName'] . '.NAOK';
                                    }
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }

                            $sumEqn = 'sum(' . implode(', ', $sq_names) . ')';
                            $precision = LEM_DEFAULT_PRECISION;
                            if (!is_null($precision))
                            {
                                $sumEqn = 'round(' . $sumEqn . ', ' . $precision . ')';
                            }

                            $noanswer_option = '';
                            if ($value_range_allows_missing)
                            {
                                $noanswer_option = ' || count(' . implode(', ', $sq_names) . ') == 0';
                            }

                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'min_num_value',
                            'class' => 'sum_range',
                            'eqn' => '(sum(' . implode(', ', $sq_names) . ') >= (' . $min_num_value . ')' . $noanswer_option . ')',
                            'qid' => $questionNum,
                            'sumEqn' => $sumEqn,
                            );
                        }
                    }
                }
                else
                {
                    $min_num_value='';
                }

                // max_num_value
                // Validation:= sum(sq1,...,sqN) <= value (which could be an expression).
                if (isset($qattr['max_num_value']) && trim($qattr['max_num_value']) != '')
                {
                    $max_num_value = $qattr['max_num_value'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case 'K': //MULTIPLE NUMERICAL QUESTION
                                    if ($this->sgqaNaming)
                                    {
                                        $sq_name = $sq['rowdivid'] . '.NAOK';
                                    }
                                    else
                                    {
                                        $sq_name = $sq['varName'] . '.NAOK';
                                    }
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }

                            $sumEqn = 'sum(' . implode(', ', $sq_names) . ')';
                            $precision = LEM_DEFAULT_PRECISION;
                            if (!is_null($precision))
                            {
                                $sumEqn = 'round(' . $sumEqn . ', ' . $precision . ')';
                            }

                            $noanswer_option = '';
                            if ($value_range_allows_missing)
                            {
                                $noanswer_option = ' || count(' . implode(', ', $sq_names) . ') == 0';
                            }

                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'max_num_value',
                            'class' => 'sum_range',
                            'eqn' =>  '(sum(' . implode(', ', $sq_names) . ') <= (' . $max_num_value . ')' . $noanswer_option . ')',
                            'qid' => $questionNum,
                            'sumEqn' => $sumEqn,
                            );
                        }
                    }
                }
                else
                {
                    $max_num_value='';
                }

                // multiflexible_min
                // Validation:= sqN >= value (which could be an expression).
                if (isset($qattr['multiflexible_min']) && trim($qattr['multiflexible_min']) != '' && $input_boxes=='1')
                {
                    $multiflexible_min = $qattr['multiflexible_min'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        $subqValidEqns = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case ':': //MULTIPLE NUMERICAL QUESTION
                                    if ($this->sgqaNaming)
                                    {
                                        $sgqa = substr($sq['jsVarName'],4);
                                        $sq_name = '(is_empty(' . $sgqa . '.NAOK) || ' . $sgqa . '.NAOK >= (' . $multiflexible_min . '))';
                                    }
                                    else
                                    {
                                        $sq_name = '(is_empty(' . $sq['varName'] . '.NAOK) || ' . $sq['varName'] . '.NAOK >= (' . $multiflexible_min . '))';
                                    }
                                    $subqValidSelector = $sq['jsVarName_on'];
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                                $subqValidEqns[$subqValidSelector] = array(
                                'subqValidEqn' => $sq_name,
                                'subqValidSelector' => $subqValidSelector,
                                );
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'multiflexible_min',
                            'class' => 'value_range',
                            'eqn' => implode(' && ', $sq_names),
                            'qid' => $questionNum,
                            'subqValidEqns' => $subqValidEqns,
                            );
                        }
                    }
                }
                else
                {
                    $multiflexible_min='';
                }

                // multiflexible_max
                // Validation:= sqN <= value (which could be an expression).
                if (isset($qattr['multiflexible_max']) && trim($qattr['multiflexible_max']) != '' && $input_boxes=='1')
                {
                    $multiflexible_max = $qattr['multiflexible_max'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        $subqValidEqns = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case ':': //MULTIPLE NUMERICAL QUESTION
                                    if ($this->sgqaNaming)
                                    {
                                        $sgqa = substr($sq['jsVarName'],4);
                                        $sq_name = '(is_empty(' . $sgqa . '.NAOK) || ' . $sgqa . '.NAOK <= (' . $multiflexible_max . '))';
                                    }
                                    else
                                    {
                                        $sq_name = '(is_empty(' . $sq['varName'] . '.NAOK) || ' . $sq['varName'] . '.NAOK <= (' . $multiflexible_max . '))';
                                    }
                                    $subqValidSelector = $sq['jsVarName_on'];
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                                $subqValidEqns[$subqValidSelector] = array(
                                'subqValidEqn' => $sq_name,
                                'subqValidSelector' => $subqValidSelector,
                                );
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'multiflexible_max',
                            'class' => 'value_range',
                            'eqn' => implode(' && ', $sq_names),
                            'qid' => $questionNum,
                            'subqValidEqns' => $subqValidEqns,
                            );
                        }
                    }
                }
                else
                {
                    $multiflexible_max='';
                }

                // min_num_of_files
                // Validation:= sq_filecount >= value (which could be an expression).
                if (isset($qattr['min_num_of_files']) && trim($qattr['min_num_of_files']) != '' && trim($qattr['min_num_of_files']) != '0')
                {
                    $min_num_of_files = $qattr['min_num_of_files'];

                    $eqn='';
                    $sgqa = $qinfo['sgqa'];
                    switch ($type)
                    {
                        case '|': //List - dropdown
                            $eqn = "(" . $sgqa . "_filecount >= (" . $min_num_of_files . "))";
                            break;
                        default:
                            break;
                    }
                    if ($eqn != '')
                    {
                        if (!isset($validationEqn[$questionNum]))
                        {
                            $validationEqn[$questionNum] = array();
                        }
                        $validationEqn[$questionNum][] = array(
                        'qtype' => $type,
                        'type' => 'min_num_of_files',
                        'class' => 'num_answers',
                        'eqn' => $eqn,
                        'qid' => $questionNum,
                        );
                    }
                }
                else
                {
                    $min_num_of_files = '';
                }
                // max_num_of_files
                // Validation:= sq_filecount <= value (which could be an expression).
                if (isset($qattr['max_num_of_files']) && trim($qattr['max_num_of_files']) != '')
                {
                    $max_num_of_files = $qattr['max_num_of_files'];
                    $eqn='';
                    $sgqa = $qinfo['sgqa'];
                    switch ($type)
                    {
                        case '|': //List - dropdown
                            $eqn = "(" . $sgqa . "_filecount <= (" . $max_num_of_files . "))";
                            break;
                        default:
                            break;
                    }
                    if ($eqn != '')
                    {
                        if (!isset($validationEqn[$questionNum]))
                        {
                            $validationEqn[$questionNum] = array();
                        }
                        $validationEqn[$questionNum][] = array(
                        'qtype' => $type,
                        'type' => 'max_num_of_files',
                        'class' => 'num_answers',
                        'eqn' => $eqn,
                        'qid' => $questionNum,
                        );
                    }
                }
                else
                {
                    $max_num_of_files = '';
                }

                // num_value_int_only
                // Validation fixnum(sqN)==int(fixnum(sqN)) : fixnum or not fix num ..... 10.00 == 10
                if (isset($qattr['num_value_int_only']) && trim($qattr['num_value_int_only']) == "1")
                {
                    $num_value_int_only="1";
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_eqns = array();
                        $subqValidEqns = array();
                        foreach ($subqs as $sq) {
                            $sq_eqn=null;
                            $subqValidSelector = '';
                            switch ($type)
                            {
                                case 'K': //MULTI NUMERICAL QUESTION TYPE (Need a attribute, not set in 131014)
                                    $subqValidSelector = $sq['jsVarName_on'];
                                case 'N': //NUMERICAL QUESTION TYPE
                                    $sq_name = ($this->sgqaNaming)?$sq['rowdivid'].".NAOK":$sq['varName'].".NAOK";
                                    $sq_eqn = '( is_int('.$sq_name.') || is_empty('.$sq_name.') )';
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_eqn)) {
                                $sq_eqns[] = $sq_eqn;
                                $subqValidEqns[$subqValidSelector] = array(
                                'subqValidEqn' => $sq_eqn,
                                'subqValidSelector' => $subqValidSelector,
                                );
                            }
                        }
                        if (count($sq_eqns) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'num_value_int_only',
                            'class' => 'value_integer',
                            'eqn' => implode(' and ', $sq_eqns),
                            'qid' => $questionNum,
                            'subqValidEqns' => $subqValidEqns,
                            );
                        }
                    }
                }
                else
                {
                    $num_value_int_only='';
                }

                // num_value_int_only
                // Validation is_numeric(sqN)
                if (isset($qattr['numbers_only']) && trim($qattr['numbers_only']) == "1")
                {
                    $numbers_only=1;
                    switch ($type)
                    {
                        case 'S': // Short text
                            if ($hasSubqs) {
                                $subqs = $qinfo['subqs'];
                                $sq_equs=array();
                                foreach($subqs as $sq)
                                {
                                    $sq_name = ($this->sgqaNaming)?$sq['rowdivid'].".NAOK":$sq['varName'].".NAOK";
                                    $sq_equs[] = '( is_numeric('.$sq_name.') || is_empty('.$sq_name.') )';
                                }
                                if (!isset($validationEqn[$questionNum]))
                                {
                                    $validationEqn[$questionNum] = array();
                                }
                                $validationEqn[$questionNum][] = array(
                                'qtype' => $type,
                                'type' => 'numbers_only',
                                'class' => 'numbers_only',
                                'eqn' =>  implode(' and ',$sq_equs),
                                'qid' => $questionNum,
                                );
                            }
                            break;
                        case 'Q': // multi text
                            if ($hasSubqs) {
                                $subqs = $qinfo['subqs'];
                                $sq_equs=array();
                                $subqValidEqns = array();
                                foreach($subqs as $sq)
                                {
                                    $sq_name = ($this->sgqaNaming)?$sq['rowdivid'].".NAOK":$sq['varName'].".NAOK";
                                    $sq_equ = '( is_numeric('.$sq_name.') || is_empty('.$sq_name.') )';// Leave mandatory to mandatory attribute
                                    $subqValidSelector = $sq['jsVarName_on'];
                                    if (!is_null($sq_name)) {
                                        $sq_equs[] = $sq_equ;
                                        $subqValidEqns[$subqValidSelector] = array(
                                        'subqValidEqn' => $sq_equ,
                                        'subqValidSelector' => $subqValidSelector,
                                        );
                                    }
                                }
                                if (!isset($validationEqn[$questionNum]))
                                {
                                    $validationEqn[$questionNum] = array();
                                }
                                $validationEqn[$questionNum][] = array(
                                'qtype' => $type,
                                'type' => 'numbers_only',
                                'class' => 'numbers_only',
                                'eqn' =>  implode(' and ',$sq_equs),
                                'qid' => $questionNum,
                                'subqValidEqns' => $subqValidEqns,
                                );
                            }
                            break;
                        case ';': // Array of text
                            if ($hasSubqs) {
                                $subqs = $qinfo['subqs'];
                                $sq_equs=array();
                                $subqValidEqns = array();
                                foreach($subqs as $sq)
                                {
                                    $sq_name = ($this->sgqaNaming)?substr($sq['jsVarName'],4).".NAOK":$sq['varName'].".NAOK";
                                    $sq_equ = '( is_numeric('.$sq_name.') || is_empty('.$sq_name.') )';// Leave mandatory to mandatory attribute
                                    $subqValidSelector = $sq['jsVarName_on'];
                                    if (!is_null($sq_name)) {
                                        $sq_equs[] = $sq_equ;
                                        $subqValidEqns[$subqValidSelector] = array(
                                        'subqValidEqn' => $sq_equ,
                                        'subqValidSelector' => $subqValidSelector,
                                        );
                                    }
                                }
                                if (!isset($validationEqn[$questionNum]))
                                {
                                    $validationEqn[$questionNum] = array();
                                }
                                $validationEqn[$questionNum][] = array(
                                'qtype' => $type,
                                'type' => 'numbers_only',
                                'class' => 'numbers_only',
                                'eqn' =>  implode(' and ',$sq_equs),
                                'qid' => $questionNum,
                                'subqValidEqns' => $subqValidEqns,
                                );
                            }
                            break;
                        case '*': // Don't think we need equation ?
                        default:
                            break;
                    }
                }
                else
                {
                    $numbers_only="";
                }

                // other_comment_mandatory
                // Validation:= sqN <= value (which could be an expression).
                if (isset($qattr['other_comment_mandatory']) && trim($qattr['other_comment_mandatory']) == '1')
                {
                    $other_comment_mandatory = $qattr['other_comment_mandatory'];
                    $eqn='';
                    if ($other_comment_mandatory == '1' && $this->questionSeq2relevance[$qinfo['qseq']]['other'] == 'Y')
                    {
                        $sgqa = $qinfo['sgqa'];
                        switch ($type)
                        {
                            case '!': //List - dropdown
                            case 'L': //LIST drop-down/radio-button list
                                $eqn = "(" . $sgqa . ".NAOK!='-oth-' || (" . $sgqa . ".NAOK=='-oth-' && !is_empty(trim(" . $sgqa . "other.NAOK))))";
                                break;
                            case 'P': //Multiple choice with comments
                                $eqn = "(is_empty(trim(" . $sgqa . "other.NAOK)) || (!is_empty(trim(" . $sgqa . "other.NAOK)) && !is_empty(trim(" . $sgqa . "othercomment.NAOK))))";
                                break;
                            default:
                                break;
                        }
                    }
                    if ($eqn != '')
                    {
                        if (!isset($validationEqn[$questionNum]))
                        {
                            $validationEqn[$questionNum] = array();
                        }
                        $validationEqn[$questionNum][] = array(
                        'qtype' => $type,
                        'type' => 'other_comment_mandatory',
                        'class' => 'other_comment_mandatory',
                        'eqn' => $eqn,
                        'qid' => $questionNum,
                        );
                    }
                }
                else
                {
                    $other_comment_mandatory = '';
                }

                // other_numbers_only
                // Validation:= is_numeric(sqN).
                if (isset($qattr['other_numbers_only']) && trim($qattr['other_numbers_only']) == '1')
                {
                    $other_numbers_only = 1;
                    $eqn='';
                    if ($this->questionSeq2relevance[$qinfo['qseq']]['other'] == 'Y')
                    {
                        $sgqa = $qinfo['sgqa'];
                        switch ($type)
                        {
                            //case '!': //List - dropdown
                            case 'L': //LIST drop-down/radio-button list
                            case 'M': //Multiple choice
                            case 'P': //Multiple choice with
                                $eqn = "(is_empty(trim(" . $sgqa . "other.NAOK)) ||is_numeric(" . $sgqa . "other.NAOK))";
                                break;
                            default:
                                break;
                        }
                    }
                    if ($eqn != '')
                    {
                        if (!isset($validationEqn[$questionNum]))
                        {
                            $validationEqn[$questionNum] = array();
                        }
                        $validationEqn[$questionNum][] = array(
                        'qtype' => $type,
                        'type' => 'other_numbers_only',
                        'class' => 'other_numbers_only',
                        'eqn' => $eqn,
                        'qid' => $questionNum,
                        );
                    }
                }
                else
                {
                    $other_numbers_only = '';
                }


                // show_totals
                // TODO - create equations for these?

                // assessment_value
                // TODO?  How does it work?
                // The assessment value (referenced how?) = count(sq1,...,sqN) * assessment_value
                // Since there are easy work-arounds to this, skipping it for now

                // preg - a PHP Regular Expression to validate text input fields
                if (isset($qinfo['preg']) && !is_null($qinfo['preg']))
                {
                    $preg = $qinfo['preg'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        $subqValidEqns = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            $subqValidSelector=NULL;
                            $sgqa = substr($sq['jsVarName'],4);
                            switch ($type)
                            {
                                case 'N': //NUMERICAL QUESTION TYPE
                                case 'K': //MULTIPLE NUMERICAL QUESTION
                                case 'Q': //MULTIPLE SHORT TEXT
                                case ';': //ARRAY (Multi Flexi) Text
                                case ':': //ARRAY (Multi Flexi) 1 to 10
                                case 'S': //SHORT FREE TEXT
                                case 'T': //LONG FREE TEXT
                                case 'U': //HUGE FREE TEXT
                                    if ($this->sgqaNaming)
                                    {
                                        $sq_name = '(if(is_empty('.$sgqa.'.NAOK),0,!regexMatch("' . $preg . '", ' . $sgqa . '.NAOK)))';
                                    }
                                    else
                                    {
                                        $sq_name = '(if(is_empty('.$sq['varName'].'.NAOK),0,!regexMatch("' . $preg . '", ' . $sq['varName'] . '.NAOK)))';
                                    }
                                    break;
                                default:
                                    break;
                            }
                            switch ($type)
                            {
                                case 'K': //MULTIPLE NUMERICAL QUESTION
                                case 'Q': //MULTIPLE SHORT TEXT
                                case ';': //ARRAY (Multi Flexi) Text
                                case ':': //ARRAY (Multi Flexi) 1 to 10
                                    if ($this->sgqaNaming)
                                    {
                                        $subqValidEqn = '(is_empty('.$sgqa.'.NAOK) || regexMatch("' . $preg . '", ' . $sgqa . '.NAOK))';
                                    }
                                    else
                                    {
                                        $subqValidEqn = '(is_empty('.$sq['varName'].'.NAOK) || regexMatch("' . $preg . '", ' . $sq['varName'] . '.NAOK))';
                                    }
                                    $subqValidSelector = $sq['jsVarName_on'];
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                                if (isset($subqValidSelector)) {
                                    $subqValidEqns[$subqValidSelector] = array(
                                    'subqValidEqn' => $subqValidEqn,
                                    'subqValidSelector' => $subqValidSelector,
                                    );
                                }
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'preg',
                            'class' => 'regex_validation',
                            'eqn' => '(sum(' . implode(', ', $sq_names) . ') == 0)',
                            'qid' => $questionNum,
                            'subqValidEqns' => $subqValidEqns,
                            );
                        }
                    }
                }
                else
                {
                    $preg='';
                }

                // em_validation_q_tip - a description of the EM validation equation that must be satisfied for the whole question.
                if (isset($qattr['em_validation_q_tip']) && !is_null($qattr['em_validation_q_tip']) && trim($qattr['em_validation_q_tip']) != '')
                {
                    $em_validation_q_tip = trim($qattr['em_validation_q_tip']);
                }
                else
                {
                    $em_validation_q_tip = '';
                }


                // em_validation_q - an EM validation equation that must be satisfied for the whole question.  Uses 'this' in the equation
                if (isset($qattr['em_validation_q']) && !is_null($qattr['em_validation_q']) && trim($qattr['em_validation_q']) != '')
                {
                    $em_validation_q = $qattr['em_validation_q'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case 'A': //ARRAY (5 POINT CHOICE) radio-buttons
                                case 'B': //ARRAY (10 POINT CHOICE) radio-buttons
                                case 'C': //ARRAY (YES/UNCERTAIN/NO) radio-buttons
                                case 'E': //ARRAY (Increase/Same/Decrease) radio-buttons
                                case 'F': //ARRAY (Flexible) - Row Format
                                case 'K': //MULTIPLE NUMERICAL QUESTION
                                case 'Q': //MULTIPLE SHORT TEXT
                                case ';': //ARRAY (Multi Flexi) Text
                                case ':': //ARRAY (Multi Flexi) 1 to 10
                                case 'M': //Multiple choice checkbox
                                case 'N': //NUMERICAL QUESTION TYPE
                                case 'O':
                                case 'P': //Multiple choice with comments checkbox + text
                                case 'R': //RANKING STYLE
                                case 'S': //SHORT FREE TEXT
                                case 'T': //LONG FREE TEXT
                                case 'U': //HUGE FREE TEXT
                                case 'D': //DATE
                                    if ($this->sgqaNaming)
                                    {
                                        $sq_name = '!(' . preg_replace('/\bthis\b/',substr($sq['jsVarName'],4), $em_validation_q) . ')';
                                    }
                                    else
                                    {
                                        $sq_name = '!(' . preg_replace('/\bthis\b/',$sq['varName'], $em_validation_q) . ')';
                                    }
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'em_validation_q',
                            'class' => 'q_fn_validation',
                            'eqn' => '(sum(' . implode(', ', array_unique($sq_names)) . ') == 0)',
                            'qid' => $questionNum,
                            );
                        }
                    }
                }
                else
                {
                    $em_validation_q='';
                }

                // em_validation_sq_tip - a description of the EM validation equation that must be satisfied for each subquestion.
                if (isset($qattr['em_validation_sq_tip']) && !is_null($qattr['em_validation_sq_tip']) && trim($qattr['em_validation_sq']) != '')
                {
                    $em_validation_sq_tip = trim($qattr['em_validation_sq_tip']);
                }
                else
                {
                    $em_validation_sq_tip = '';
                }


                // em_validation_sq - an EM validation equation that must be satisfied for each subquestion.  Uses 'this' in the equation
                if (isset($qattr['em_validation_sq']) && !is_null($qattr['em_validation_sq']) && trim($qattr['em_validation_sq']) != '')
                {
                    $em_validation_sq = $qattr['em_validation_sq'];
                    if ($hasSubqs) {
                        $subqs = $qinfo['subqs'];
                        $sq_names = array();
                        $subqValidEqns = array();
                        foreach ($subqs as $sq) {
                            $sq_name = NULL;
                            switch ($type)
                            {
                                case 'K': //MULTIPLE NUMERICAL QUESTION
                                case 'Q': //MULTIPLE SHORT TEXT
                                case ';': //ARRAY (Multi Flexi) Text
                                case ':': //ARRAY (Multi Flexi) 1 to 10
                                case 'N': //NUMERICAL QUESTION TYPE
                                case 'S': //SHORT FREE TEXT
                                case 'T': //LONG FREE TEXT
                                case 'U': //HUGE FREE TEXT
                                    if ($this->sgqaNaming)
                                    {
                                        $sq_name = '!(' . preg_replace('/\bthis\b/',substr($sq['jsVarName'],4), $em_validation_sq) . ')';
                                    }
                                    else
                                    {
                                        $sq_name = '!(' . preg_replace('/\bthis\b/',$sq['varName'], $em_validation_sq) . ')';
                                    }
                                    break;
                                default:
                                    break;
                            }
                            switch ($type)
                            {
                                case 'K': //MULTIPLE NUMERICAL QUESTION
                                case 'Q': //MULTIPLE SHORT TEXT
                                case ';': //ARRAY (Multi Flexi) Text
                                case ':': //ARRAY (Multi Flexi) 1 to 10
                                case 'N': //NUMERICAL QUESTION TYPE
                                case 'S': //SHORT FREE TEXT
                                case 'T': //LONG FREE TEXT
                                case 'U': //HUGE FREE TEXT
                                    if ($this->sgqaNaming)
                                    {
                                        $subqValidEqn = '(' . preg_replace('/\bthis\b/',substr($sq['jsVarName'],4), $em_validation_sq) . ')';
                                    }
                                    else
                                    {
                                        $subqValidEqn = '(' . preg_replace('/\bthis\b/',$sq['varName'], $em_validation_sq) . ')';
                                    }
                                    $subqValidSelector = $sq['jsVarName_on'];
                                    break;
                                default:
                                    break;
                            }
                            if (!is_null($sq_name)) {
                                $sq_names[] = $sq_name;
                                if (isset($subqValidSelector)) {
                                    $subqValidEqns[$subqValidSelector] = array(
                                    'subqValidEqn' => $subqValidEqn,
                                    'subqValidSelector' => $subqValidSelector,
                                    );
                                }
                            }
                        }
                        if (count($sq_names) > 0) {
                            if (!isset($validationEqn[$questionNum]))
                            {
                                $validationEqn[$questionNum] = array();
                            }
                            $validationEqn[$questionNum][] = array(
                            'qtype' => $type,
                            'type' => 'em_validation_sq',
                            'class' => 'sq_fn_validation',
                            'eqn' => '(sum(' . implode(', ', $sq_names) . ') == 0)',
                            'qid' => $questionNum,
                            'subqValidEqns' => $subqValidEqns,
                            );
                        }
                    }
                }
                else
                {
                    $em_validation_sq='';
                }

                ////////////////////////////////////////////
                // COMPOSE USER FRIENDLY MIN/MAX MESSAGES //
                ////////////////////////////////////////////

                // Put these in the order you with them to appear in messages.
                $qtips=array();

                // Default validation qtip without attribute
                switch ($type)
                {
                    case 'I':
                        $qtips['default']=$this->gT('Choose your language');
                        break;
                    case 'O':
                    case 'L':
                    case '!':
                        $qtips['default']=$this->gT('Choose one of the following answers');
                        break;
                    case 'M':
                         $qtips['default']=$this->gT('Check any that apply');
                         break;
                    case 'N':
                        $qtips['default']=$this->gT("Only numbers may be entered in this field.");
                        break;
                    case 'K':
                        $qtips['default']=$this->gT("Only numbers may be entered in these fields.");
                        break;
                    case 'R':
                        $qtips['default']=$this->gT("All your answers must be different and you must rank in order.");
                        break;
                    default:
                        break;
                }
                if($dropdown_dates)
                {
                    $qtips['dropdown_dates']=$this->gT("Please complete all parts of the date.");
                }
                if($commented_checkbox)
                {
                    switch ($commented_checkbox)
                    {
                        case 'checked':
                            $qtips['commented_checkbox']=$this->gT("Comment only when you choose an answer.");
                            break;
                        case 'unchecked':
                            $qtips['commented_checkbox']=$this->gT("Comment only when you don't choose an answer.");
                            break;
                        case 'allways':
                        default:
                            $qtips['commented_checkbox']=$this->gT("Comment your answers.");
                            break;
                    }
                }

                // equals_num_value
                if ($equals_num_value!='')
                {
                    $qtips['sum_range']=sprintf($this->gT("The sum must equal %s."),'{fixnum('.$equals_num_value.')}');
                }

                if($input_boxes)
                {
                    switch ($type)
                    {
                        case ':':
                            $qtips['input_boxes']=$this->gT("Only numbers may be entered in these fields.");
                            break;
                        default:
                            break;
                    }
                }

                // min/max answers
                if ($min_answers!='' || $max_answers!='')
                {
                    $_minA = (($min_answers == '') ? "''" : $min_answers);
                    $_maxA = (($max_answers == '') ? "''" : $max_answers    );
                    /* different messages for text and checkbox questions */
                    if($type == 'Q' || $type == 'K' || $type == ';' || $type == ':')
                    {
                        $_msgs = array(
                            'atleast_m' => $this->gT("Please fill in at least %s answers"),
                            'atleast_1' => $this->gT("Please fill in at least one answer"),
                            'atmost_m' => $this->gT("Please fill in at most %s answers"),
                            'atmost_1' => $this->gT("Please fill in at most one answer"),
                            '1' => $this->gT("Please fill in at most one answer"),
                            'n' => $this->gT("Please fill in %s answers"),
                            'between' => $this->gT("Please fill in from %s to %s answers.")
                        );
                    }
                    else
                    {
                        $_msgs = array(
                            'atleast_m' => $this->gT("Please select at least %s answers"),
                            'atleast_1' => $this->gT("Please select at least one answer"),
                            'atmost_m' => $this->gT("Please select at most %s answers"),
                            'atmost_1' => $this->gT("Please select at most one answer"),
                            '1' => $this->gT("Please select one answer"),
                            'n' => $this->gT("Please select %s answers"),
                            'between' => $this->gT("Please select from %s to %s answers.")
                        );
                    }
                    $qtips['num_answers']=
                        "{if(!is_empty($_minA) && is_empty($_maxA) && ($_minA)!=1,sprintf('".$_msgs['atleast_m']."',fixnum($_minA)),'')}" .
                        "{if(!is_empty($_minA) && is_empty($_maxA) && ($_minA)==1,sprintf('".$_msgs['atleast_1']."',fixnum($_minA)),'')}" .
                        "{if(is_empty($_minA) && !is_empty($_maxA) && ($_maxA)!=1,sprintf('".$_msgs['atmost_m']."',fixnum($_maxA)),'')}" .
                        "{if(is_empty($_minA) && !is_empty($_maxA) && ($_maxA)==1,sprintf('".$_msgs['atmost_1']."',fixnum($_maxA)),'')}" .
                        "{if(!is_empty($_minA) && !is_empty($_maxA) && ($_minA) == ($_maxA) && ($_minA) == 1,'".$_msgs['1']."','')}" .
                        "{if(!is_empty($_minA) && !is_empty($_maxA) && ($_minA) == ($_maxA) && ($_minA) != 1,sprintf('".$_msgs['n']."',fixnum($_minA)),'')}" .
                        "{if(!is_empty($_minA) && !is_empty($_maxA) && ($_minA) != ($_maxA),sprintf('".$_msgs['between']."',fixnum($_minA),fixnum($_maxA)),'')}";
                }

                // min/max value for each numeric entry
                if ($min_num_value_n!='' || $max_num_value_n!='')
                {
                    $_minV = (($min_num_value_n == '') ? "''" : $min_num_value_n);
                    $_maxV = (($max_num_value_n == '') ? "''" : $max_num_value_n);
                    if ($type!='N')
                    {
                        $qtips['value_range']=
                            "{if(!is_empty($_minV) && is_empty($_maxV), sprintf('".$this->gT("Each answer must be at least %s")."',fixnum($_minV)), '')}" .
                            "{if(is_empty($_minV) && !is_empty($_maxV), sprintf('".$this->gT("Each answer must be at most %s")."',fixnum($_maxV)), '')}" .
                            "{if(!is_empty($_minV) && ($_minV) == ($_maxV),sprintf('".$this->gT("Each answer must be %s")."', fixnum($_minV)), '')}" .
                            "{if(!is_empty($_minV) && !is_empty($_maxV) && ($_minV) != ($_maxV), sprintf('".$this->gT("Each answer must be between %s and %s")."', fixnum($_minV), fixnum($_maxV)), '')}";
                    }
                    else
                    {
                        $qtips['value_range']=
                            "{if(!is_empty($_minV) && is_empty($_maxV), sprintf('".$this->gT("Your answer must be at least %s")."',fixnum($_minV)), '')}" .
                            "{if(is_empty($_minV) && !is_empty($_maxV), sprintf('".$this->gT("Your answer must be at most %s")."',fixnum($_maxV)), '')}" .
                            "{if(!is_empty($_minV) && ($_minV) == ($_maxV),sprintf('".$this->gT("Your answer must be %s")."', fixnum($_minV)), '')}" .
                            "{if(!is_empty($_minV) && !is_empty($_maxV) && ($_minV) != ($_maxV), sprintf('".$this->gT("Your answer must be between %s and %s")."', fixnum($_minV), fixnum($_maxV)), '')}";
                    }
                }

                // min/max value for dates
                if ($date_min!='' || $date_max!='')
                {
                    //Get date format of current question and convert date in help text accordingly
                    $LEM =& LimeExpressionManager::singleton();
                    $aAttributes=$LEM->getQuestionAttributesForEM($LEM->sid, $questionNum,$_SESSION['LEMlang']);
                    $aDateFormatData=getDateFormatDataForQID($aAttributes[$questionNum],$LEM->surveyOptions);
                    $_minV = (($date_min == '') ? "''" : "if((strtotime(".$date_min.")), date('".$aDateFormatData['phpdate']."', strtotime(".$date_min.")),'')");
                    $_maxV = (($date_max == '') ? "''" : "if((strtotime(".$date_max.")), date('".$aDateFormatData['phpdate']."', strtotime(".$date_max.")),'')");
                    $qtips['value_range']=
                        "{if(!is_empty($_minV) && is_empty($_maxV), sprintf('".$this->gT("Answer must be greater or equal to %s")."',$_minV), '')}" .
                        "{if(is_empty($_minV) && !is_empty($_maxV), sprintf('".$this->gT("Answer must be less or equal to %s")."',$_maxV), '')}" .
                        "{if(!is_empty($_minV) && ($_minV) == ($_maxV),sprintf('".$this->gT("Answer must be %s")."', $_minV), '')}" .
                        "{if(!is_empty($_minV) && !is_empty($_maxV) && ($_minV) != ($_maxV), sprintf('".$this->gT("Answer must be between %s and %s")."', ($_minV), ($_maxV)), '')}";
                    }

                // min/max value for each numeric entry - for multi-flexible question type
                if ($multiflexible_min!='' || $multiflexible_max!='')
                {
                    $_minV = (($multiflexible_min == '') ? "''" : $multiflexible_min);
                    $_maxV = (($multiflexible_max == '') ? "''" : $multiflexible_max);
                    $qtips['value_range']=
                        "{if(!is_empty($_minV) && is_empty($_maxV), sprintf('".$this->gT("Each answer must be at least %s")."',fixnum($_minV)), '')}" .
                        "{if(is_empty($_minV) && !is_empty($_maxV), sprintf('".$this->gT("Each answer must be at most %s")."',fixnum($_maxV)), '')}" .
                        "{if(!is_empty($_minV) && ($_minV) == ($_maxV),sprintf('".$this->gT("Each answer must be %s")."', fixnum($_minV)), '')}" .
                        "{if(!is_empty($_minV) && !is_empty($_maxV) && ($_minV) != ($_maxV), sprintf('".$this->gT("Each answer must be between %s and %s")."', fixnum($_minV), fixnum($_maxV)), '')}";
                }

                // min/max sum value
                if ($min_num_value!='' || $max_num_value!='')
                {
                    $_minV = (($min_num_value == '') ? "''" : $min_num_value);
                    $_maxV = (($max_num_value == '') ? "''" : $max_num_value);
                    $qtips['sum_range']=
                        "{if(!is_empty($_minV) && is_empty($_maxV), sprintf('".$this->gT("The sum must be at least %s")."',fixnum($_minV)), '')}" .
                        "{if(is_empty($_minV) && !is_empty($_maxV), sprintf('".$this->gT("The sum must be at most %s")."',fixnum($_maxV)), '')}" .
                        "{if(!is_empty($_minV) && ($_minV) == ($_maxV),sprintf('".$this->gT("The sum must equal %s")."', fixnum($_minV)), '')}" .
                        "{if(!is_empty($_minV) && !is_empty($_maxV) && ($_minV) != ($_maxV), sprintf('".$this->gT("The sum must be between %s and %s")."', fixnum($_minV), fixnum($_maxV)), '')}";
                }

                // min/max num files
                if ($min_num_of_files !='' || $max_num_of_files !='')
                {
                    $_minA = (($min_num_of_files == '') ? "''" : $min_num_of_files);
                    $_maxA = (($max_num_of_files == '') ? "''" : $max_num_of_files);
                    // TODO - create em_num_files class so can sepately style num_files vs. num_answers
                    $qtips['num_answers']=
                        "{if(!is_empty($_minA) && is_empty($_maxA) && ($_minA)!=1,sprintf('".$this->gT("Please upload at least %s files")."',fixnum($_minA)),'')}" .
                        "{if(!is_empty($_minA) && is_empty($_maxA) && ($_minA)==1,sprintf('".$this->gT("Please upload at least one file")."',fixnum($_minA)),'')}" .
                        "{if(is_empty($_minA) && !is_empty($_maxA) && ($_maxA)!=1,sprintf('".$this->gT("Please upload at most %s files")."',fixnum($_maxA)),'')}" .
                        "{if(is_empty($_minA) && !is_empty($_maxA) && ($_maxA)==1,sprintf('".$this->gT("Please upload at most one file")."',fixnum($_maxA)),'')}" .
                        "{if(!is_empty($_minA) && !is_empty($_maxA) && ($_minA) == ($_maxA) && ($_minA) == 1,'".$this->gT("Please upload one file")."','')}" .
                        "{if(!is_empty($_minA) && !is_empty($_maxA) && ($_minA) == ($_maxA) && ($_minA) != 1,sprintf('".$this->gT("Please upload %s files")."',fixnum($_minA)),'')}" .
                        "{if(!is_empty($_minA) && !is_empty($_maxA) && ($_minA) != ($_maxA),sprintf('".$this->gT("Please upload between %s and %s files")."',fixnum($_minA),fixnum($_maxA)),'')}";
                }


                // integer for numeric
                if ($num_value_int_only!='')
                {
                    switch ($type)
                    {
                        case 'N':
                            unset($qtips['default']);
                            $qtips['value_integer']=$this->gT("Only an integer value may be entered in this field.");
                            break;
                        case 'K':
                            unset($qtips['default']);
                            $qtips['value_integer']=$this->gT("Only integer values may be entered in these fields.");
                            break;
                        default:
                            break;
                    }
                }

                // numbers only
                if($numbers_only)
                {
                    switch ($type)
                    {
                        case 'S':
                            $qtips['numbers_only']=$this->gT("Only numbers may be entered in this field.");
                            break;
                        case 'Q':
                        case ';':
                            $qtips['numbers_only']=$this->gT("Only numbers may be entered in these fields.");
                            break;
                        default:
                            break;
                    }
                }

                // other comment mandatory
                if ($other_comment_mandatory!='')
                {
                    if (isset($qattr['other_replace_text']) && trim($qattr['other_replace_text']) != '') {
                        $othertext = trim($qattr['other_replace_text']);
                    }
                    else {
                        $othertext = $this->gT('Other:');
                    }
                    $qtips['other_comment_mandatory']=sprintf($this->gT("If you choose '%s' please also specify your choice in the accompanying text field."),$othertext);
                }

                // other comment mandatory
                if ($other_numbers_only!='')
                {
                    if (isset($qattr['other_replace_text']) && trim($qattr['other_replace_text']) != '') {
                        $othertext = trim($qattr['other_replace_text']);
                    }
                    else {
                        $othertext = $this->gT('Other:');
                    }
                    $qtips['other_numbers_only']=sprintf($this->gT("Only numbers may be entered in '%s' accompanying text field."),$othertext);
                }

                // regular expression validation
                if ($preg!='')
                {
                    // do string replacement here so that curly braces within the regular expression don't trigger an EM error
                    //                $qtips['regex_validation']=sprintf($this->gT('Each answer must conform to this regular expression: %s'), str_replace(array('{','}'),array('{ ',' }'), $preg));
                    $qtips['regex_validation']=$this->gT('Please check the format of your answer.');
                }

                if ($em_validation_sq!='')
                {
                    if ($em_validation_sq_tip =='')
                    {
                        //                    $stringToParse = htmlspecialchars_decode($em_validation_sq,ENT_QUOTES);
                        //                    $gseq = $this->questionId2groupSeq[$qinfo['qid']];
                        //                    $result = $this->em->ProcessBooleanExpression($stringToParse,$gseq,  $qinfo['qseq']);
                        //                    $_validation_tip = $this->em->GetPrettyPrintString();
                        //                    $qtips['sq_fn_validation']=sprintf($this->gT('Each answer must conform to this expression: %s'),$_validation_tip);
                    }
                    else
                    {
                        $qtips['sq_fn_validation']=$em_validation_sq_tip;
                    }

                }

                // em_validation_q - whole-question validation equation
                if ($em_validation_q!='')
                {
                    if ($em_validation_q_tip =='')
                    {
                        //                    $stringToParse = htmlspecialchars_decode($em_validation_q,ENT_QUOTES);
                        //                    $gseq = $this->questionId2groupSeq[$qinfo['qid']];
                        //                    $result = $this->em->ProcessBooleanExpression($stringToParse,$gseq,  $qinfo['qseq']);
                        //                    $_validation_tip = $this->em->GetPrettyPrintString();
                        //                    $qtips['q_fn_validation']=sprintf($this->gT('The question must conform to this expression: %s'), $_validation_tip);
                    }
                    else
                    {
                        $qtips['q_fn_validation']=$em_validation_q_tip;
                    }
                }

                if (count($qtips) > 0)
                {
                    $validationTips[$questionNum] = $qtips;
                }
            }

            // Consolidate logic across array filters
            $rowdivids = array();
            $order=0;
            foreach ($subQrels as $sq)
            {
                $oldeqn = (isset($rowdivids[$sq['rowdivid']]['eqns']) ? $rowdivids[$sq['rowdivid']]['eqns'] : array());
                $oldtype = (isset($rowdivids[$sq['rowdivid']]['type']) ? $rowdivids[$sq['rowdivid']]['type'] : '');
                $neweqn = (($sq['type'] == 'exclude_all_others') ? array() : array($sq['eqn']));
                $oldeo = (isset($rowdivids[$sq['rowdivid']]['exclusive_options']) ? $rowdivids[$sq['rowdivid']]['exclusive_options'] : array());
                $neweo = (($sq['type'] == 'exclude_all_others') ? array($sq['eqn']) : array());
                $rowdivids[$sq['rowdivid']] = array(
                'order'=>$order++,
                'qid'=>$sq['qid'],
                'rowdivid'=>$sq['rowdivid'],
                'type'=>$sq['type'] . ';' . $oldtype,
                'qtype'=>$sq['qtype'],
                'sgqa'=>$sq['sgqa'],
                'eqns'=>array_merge($oldeqn, $neweqn),
                'exclusive_options'=>array_merge($oldeo, $neweo),
                );
            }

            foreach ($rowdivids as $sq)
            {
                $sq['eqn'] = implode(' and ', array_unique(array_merge($sq['eqns'],$sq['exclusive_options'])));   // without array_unique, get duplicate of filters for question types 1, :, and ;
                $eos = array_unique($sq['exclusive_options']);
                $isExclusive = '';
                $irrelevantAndExclusive = '';
                if (count($eos) > 0)
                {
                    $isExclusive = '!(' . implode(' and ', $eos) . ')';
                    $noneos = array_unique($sq['eqns']);
                    if (count($noneos) > 0)
                    {
                        $irrelevantAndExclusive = '(' . implode(' and ', $noneos) . ') and ' . $isExclusive;
                    }
                }
                $this->_ProcessSubQRelevance($sq['eqn'], $sq['qid'], $sq['rowdivid'], $sq['type'], $sq['qtype'],  $sq['sgqa'], $isExclusive, $irrelevantAndExclusive);
            }

            foreach ($validationEqn as $qid=>$eqns)
            {
                $parts = array();
                $tips = (isset($validationTips[$qid]) ? $validationTips[$qid] : array());
                $subqValidEqns = array();
                $sumEqn = '';
                $sumRemainingEqn = '';
                foreach ($eqns as $v) {
                    if (!isset($parts[$v['class']]))
                    {
                        $parts[$v['class']] = array();
                    }
                    $parts[$v['class']][] = $v['eqn'];
                    // even if there are min/max/preg, the count or total will always be the same
                    $sumEqn = (isset($v['sumEqn'])) ? $v['sumEqn'] : $sumEqn;
                    $sumRemainingEqn = (isset($v['sumRemainingEqn'])) ? $v['sumRemainingEqn'] : $sumRemainingEqn;
                    if (isset($v['subqValidEqns'])) {
                        $subqValidEqns[] = $v['subqValidEqns'];
                    }
                }
                // combine the sub-question level validation equations into a single validation equation per sub-question
                $subqValidComposite = array();
                foreach ($subqValidEqns as $sqs) {
                    foreach ($sqs as $sq)
                    {
                        if (!isset($subqValidComposite[$sq['subqValidSelector']]))
                        {
                            $subqValidComposite[$sq['subqValidSelector']] = array(
                            'subqValidSelector' => $sq['subqValidSelector'],
                            'subqValidEqns' => array(),
                            );
                        }
                        $subqValidComposite[$sq['subqValidSelector']]['subqValidEqns'][] = $sq['subqValidEqn'];
                    }
                }
                $csubqValidEqns = array();
                foreach ($subqValidComposite as $csq)
                {
                    $csubqValidEqns[$csq['subqValidSelector']] = array(
                    'subqValidSelector' => $csq['subqValidSelector'],
                    'subqValidEqn' => implode(' && ', $csq['subqValidEqns']),
                    );
                }
                // now combine all classes of validation equations
                $veqns = array();
                foreach ($parts as $vclass=>$eqns)
                {
                    $veqns[$vclass] = '(' . implode(' and ', $eqns) . ')';
                }


                $this->qid2validationEqn[$qid] = array(
                'eqn' => $veqns,
                'tips' => $tips,
                'subqValidEqns' => $csubqValidEqns,
                'sumEqn' => $sumEqn,
                'sumRemainingEqn' => $sumRemainingEqn,
                );
            }

            //        $this->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
        }

        /**
         * Recursively find all questions that logically preceded the current array_filter or array_filter_exclude request
         * Note, must support:
         * (a) semicolon-separated list of $qroot codes for either array_filter or array_filter_exclude
         * (b) mixed history of array_filter and array_filter_exclude values
         * @param string $qroot - the question root variable name
         * @param array $aflist - the list of array_filter $qroot codes
         * @param array $afelist - the list of array_filter_exclude $qroot codes
         * @return array
         */
        private function _recursivelyFindAntecdentArrayFilters($qroot, $aflist, $afelist)
        {
            if (isset($this->qrootVarName2arrayFilter[$qroot]))
            {
                if (isset($this->qrootVarName2arrayFilter[$qroot]['array_filter']))
                {
                    $_afs = explode(';',$this->qrootVarName2arrayFilter[$qroot]['array_filter']);
                    foreach ($_afs as $_af)
                    {
                        if (in_array($_af,$aflist))
                        {
                            continue;
                        }
                        $aflist[] = $_af;
                        list($aflist, $afelist) = $this->_recursivelyFindAntecdentArrayFilters($_af, $aflist, $afelist);
                    }
                }
                if (isset($this->qrootVarName2arrayFilter[$qroot]['array_filter_exclude']))
                {
                    $_afes = explode(';',$this->qrootVarName2arrayFilter[$qroot]['array_filter_exclude']);
                    foreach ($_afes as $_afe)
                    {
                        if (in_array($_afe,$afelist))
                        {
                            continue;
                        }
                        $afelist[] = $_afe;
                        list($aflist, $afelist) = $this->_recursivelyFindAntecdentArrayFilters($_afe, $aflist, $afelist);
                    }
                }
            }
            return array($aflist, $afelist);
        }

        /**
        * Create the arrays needed by ExpressionManager to process LimeSurvey strings.
        * The long part of this function should only be called once per page display (e.g. only if $fieldMap changes)
        *
        * @param integer $surveyid
        * @param boolean|null $forceRefresh
        * @param boolean|null $anonymized
        * @param boolean $allOnOnePage - if true (like for survey_format), uses certain optimizations
        * @return boolean - true if $fieldmap had been re-created, so ExpressionManager variables need to be re-set
        */
        public function setVariableAndTokenMappingsForExpressionManager($surveyid,$forceRefresh=false,$anonymized=false,$allOnOnePage=false)
        {
            if (isset($_SESSION['LEMforceRefresh'])) {
                unset($_SESSION['LEMforceRefresh']);
                $forceRefresh=true;
            }
            else if (!$forceRefresh && isset($this->knownVars) && !$this->sPreviewMode ) {
                return false;   // means that those variables have been cached and no changes needed
            }
            $now = microtime(true);
            $this->em->SetSurveyMode($this->surveyMode);

            // TODO - do I need to force refresh, or trust that createFieldMap will cache langauges properly?
            $fieldmap=createFieldMap($surveyid,$style='full',$forceRefresh,false,$_SESSION['LEMlang']);
            $this->sid= $surveyid;
            $this->sessid = 'survey_' . $this->sid;
            $this->runtimeTimings[] = array(__METHOD__ . '.createFieldMap',(microtime(true) - $now));
            //      LimeExpressionManager::ShowStackTrace();

            $now = microtime(true);

            if (!isset($fieldmap)) {
                return false; // implies an error occurred
            }
            $this->knownVars = array();   // mapping of VarName to Value
            $this->qcode2sgqa = array();
            $this->tempVars = array();
            $this->qid2code = array();    // List of codes for each question - needed to know which to NULL if a question is irrelevant
            $this->jsVar2qid = array();
            $this->qcode2sgq = array();
            $this->alias2varName = array();
            $this->varNameAttr = array();
            $this->questionId2questionSeq = array();
            $this->questionId2groupSeq = array();
            $this->questionSeq2relevance = array();
            $this->groupId2groupSeq = array();
            $this->qid2validationEqn = array();
            $this->groupSeqInfo = array();
            $this->gseq2relevanceStatus = array();
            /* Fill some static know vars , the used is always $this->knownVars (even if set in templatereplace function) */
            $this->knownVars['SID'] = array(
                'code'=>$this->sid,
                'jsName_on'=>'',
                'jsName'=>'',
                'readWrite'=>'N',
            );
            /* Add the core replacement before question code : needed if use it in equation , use SID to never send error */
            templatereplace("{SID}");

            // Since building array of allowable answers, need to know preset values for certain question types
            $presets = array();
            $presets['G'] = array(  //GENDER drop-down list
            'M' => $this->gT("Male"),
            'F' => $this->gT("Female"),
            );
            $presets['Y'] = array(  //YES/NO radio-buttons
            'Y' => $this->gT("Yes"),
            'N' => $this->gT("No"),
            );
            $presets['C'] = array(   //ARRAY (YES/UNCERTAIN/NO) radio-buttons
            'Y' => $this->gT("Yes"),
            'N' => $this->gT("No"),
            'U' => $this->gT("Uncertain"),
            );
            $presets['E'] = array(  //ARRAY (Increase/Same/Decrease) radio-buttons
            'I' => $this->gT("Increase"),
            'S' => $this->gT("Same"),
            'D' => $this->gT("Decrease"),
            );

            $this->gseq2info = $this->getGroupInfoForEM($surveyid,$_SESSION['LEMlang']);
            foreach ($this->gseq2info as $aGroupInfo)
            {
                $this->groupId2groupSeq[$aGroupInfo['gid']] = $aGroupInfo['group_order'];
            }

            $qattr = $this->getQuestionAttributesForEM($surveyid,0,$_SESSION['LEMlang']);

            $this->qattr = $qattr;

            $this->runtimeTimings[] = array(__METHOD__ . ' - question_attributes_model->getQuestionAttributesForEM',(microtime(true) - $now));
            $now = microtime(true);

            $this->qans = $this->getAnswerSetsForEM($surveyid,NULL,$_SESSION['LEMlang']);

            $this->runtimeTimings[] = array(__METHOD__ . ' - answers_model->getAnswerSetsForEM',(microtime(true) - $now));
            $now = microtime(true);

            $q2subqInfo = array();

            $this->multiflexiAnswers=array();
            foreach($fieldmap as $fielddata)
            {
                if (!isset($fielddata['fieldname']) || !preg_match('#^\d+X\d+X\d+#',$fielddata['fieldname']))
                {
                    continue;   // not an SGQA value
                }
                $sgqa = $fielddata['fieldname'];
                $type = $fielddata['type'];
                $mandatory = $fielddata['mandatory'];
                $fieldNameParts = explode('X',$sgqa);
                $groupNum = $fieldNameParts[1];
                $aid = (isset($fielddata['aid']) ? $fielddata['aid'] : '');
                $sqid = (isset($fielddata['sqid']) ? $fielddata['sqid'] : '');
                if($this->sPreviewMode=='question') $fielddata['relevance']=1;
                if($this->sPreviewMode=='group') $fielddata['grelevance']=1;

                $questionId = $fieldNameParts[2];
                $questionNum = $fielddata['qid'];
                $relevance = (isset($fielddata['relevance'])) ? $fielddata['relevance'] : 1;
                $SQrelevance = (isset($fielddata['SQrelevance'])) ? $fielddata['SQrelevance'] : 1;
                $grelevance = (isset($fielddata['grelevance'])) ? $fielddata['grelevance'] : 1;
                $hidden = (isset($qattr[$questionNum]['hidden'])) ? ($qattr[$questionNum]['hidden'] == '1') : false;
                $scale_id = (isset($fielddata['scale_id'])) ? $fielddata['scale_id'] : '0';
                $preg = (isset($fielddata['preg'])) ? $fielddata['preg'] : NULL; // a perl regular exrpession validation function
                $defaultValue = (isset($fielddata['defaultvalue']) ? $fielddata['defaultvalue'] : NULL);
                if (trim($preg) == '') {
                    $preg = NULL;
                }
                $help = (isset($fielddata['help'])) ? $fielddata['help']: '';
                $other = (isset($fielddata['other'])) ? $fielddata['other'] : '';

                if (isset($this->questionId2groupSeq[$questionNum])) {
                    $groupSeq = $this->questionId2groupSeq[$questionNum];
                }
                else {
                    $groupSeq = (isset($fielddata['groupSeq'])) ? $fielddata['groupSeq'] : -1;
                    $this->questionId2groupSeq[$questionNum] = $groupSeq;
                }

                if (isset($this->questionId2questionSeq[$questionNum])) {
                    $questionSeq = $this->questionId2questionSeq[$questionNum];
                }
                else {
                    $questionSeq = (isset($fielddata['questionSeq'])) ? $fielddata['questionSeq'] : -1;
                    $this->questionId2questionSeq[$questionNum] = $questionSeq;
                }

                if (!isset($this->groupSeqInfo[$groupSeq])) {
                    $this->groupSeqInfo[$groupSeq] = array(
                    'qstart' => $questionSeq,
                    'qend' => $questionSeq,
                    );
                }
                else {
                    $this->groupSeqInfo[$groupSeq]['qend'] = $questionSeq;  // with each question, update so know ending value
                }


                // Create list of codes associated with each question
                $codeList = (isset($this->qid2code[$questionNum]) ? $this->qid2code[$questionNum] : '');
                if ($codeList == '')
                {
                    $codeList = $sgqa;
                }
                else
                {
                    $codeList .= '|' . $sgqa;
                }
                $this->qid2code[$questionNum] = $codeList;

                $readWrite = 'Y';

                // Set $ansArray
                switch($type)
                {
                    case '!': //List - dropdown
                    case 'L': //LIST drop-down/radio-button list
                    case 'O': //LIST WITH COMMENT drop-down/radio-button list + textarea
                    case '1': //Array (Flexible Labels) dual scale  // need scale
                    case 'H': //ARRAY (Flexible) - Column Format
                    case 'F': //ARRAY (Flexible) - Row Format
                    case 'R': //RANKING STYLE
                        $ansArray = (isset($this->qans[$questionNum]) ? $this->qans[$questionNum] : NULL);
                        if ($other == 'Y' && ($type == 'L' || $type == '!'))
                        {
                            if (preg_match('/other$/',$sgqa))
                            {
                                $ansArray = NULL;   // since the other variable doesn't need it
                            }
                            else
                            {
                                $_qattr = isset($qattr[$questionNum]) ? $qattr[$questionNum] : array();
                                if (isset($_qattr['other_replace_text']) && trim($_qattr['other_replace_text']) != '') {
                                    $othertext = trim($_qattr['other_replace_text']);
                                }
                                else {
                                    $othertext = $this->gT('Other:');
                                }
                                $ansArray['0~-oth-'] = '0|' . $othertext;
                            }
                        }
                        break;
                    case 'A': //ARRAY (5 POINT CHOICE) radio-buttons
                    case 'B': //ARRAY (10 POINT CHOICE) radio-buttons
                    case ':': //ARRAY (Multi Flexi) 1 to 10
                    case '5': //5 POINT CHOICE radio-buttons
                        $ansArray=NULL;
                        break;
                    case 'N': //NUMERICAL QUESTION TYPE
                    case 'K': //MULTIPLE NUMERICAL QUESTION
                    case 'Q': //MULTIPLE SHORT TEXT
                    case ';': //ARRAY (Multi Flexi) Text
                    case 'S': //SHORT FREE TEXT
                    case 'T': //LONG FREE TEXT
                    case 'U': //HUGE FREE TEXT
                    case 'M': //Multiple choice checkbox
                    case 'P': //Multiple choice with comments checkbox + text
                    case 'D': //DATE
                    case '*': //Equation
                    case 'I': //Language Question
                    case '|': //File Upload
                    case 'X': //BOILERPLATE QUESTION
                        $ansArray = NULL;
                        break;
                    case 'G': //GENDER drop-down list
                    case 'Y': //YES/NO radio-buttons
                    case 'C': //ARRAY (YES/UNCERTAIN/NO) radio-buttons
                    case 'E': //ARRAY (Increase/Same/Decrease) radio-buttons
                        $ansArray = $presets[$type];
                        break;
                }

                // set $subqtext text - for display of primary sub-question
                $subqtext = '';
                switch ($type)
                {
                    default:
                        $subqtext = (isset($fielddata['subquestion']) ? $fielddata['subquestion'] : '');
                        break;
                    case ':': //ARRAY (Multi Flexi) 1 to 10
                    case ';': //ARRAY (Multi Flexi) Text
                        $subqtext = (isset($fielddata['subquestion1']) ? $fielddata['subquestion1'] : '');
                        $ansList = array();
                        if (isset($fielddata['answerList']))
                        {
                            foreach ($fielddata['answerList'] as $ans) {
                                $ansList['1~' . $ans['code']] = $ans['code'] . '|' . $ans['answer'];
                            }
                            $this->multiflexiAnswers[$questionNum] = $ansList;
                        }
                        break;
                }

                // Set $varName (question code / questions.title), $rowdivid, $csuffix, $sqsuffix, and $question
                $rowdivid=NULL;   // so that blank for types not needing it.
                $sqsuffix='';
                $csuffix = '';
                switch($type)
                {
                    case '!': //List - dropdown
                    case '5': //5 POINT CHOICE radio-buttons
                    case 'D': //DATE
                    case 'G': //GENDER drop-down list
                    case 'I': //Language Question
                    case 'L': //LIST drop-down/radio-button list
                    case 'N': //NUMERICAL QUESTION TYPE
                    case 'O': //LIST WITH COMMENT drop-down/radio-button list + textarea
                    case 'S': //SHORT FREE TEXT
                    case 'T': //LONG FREE TEXT
                    case 'U': //HUGE FREE TEXT
                    case 'X': //BOILERPLATE QUESTION
                    case 'Y': //YES/NO radio-buttons
                    case '|': //File Upload
                    case '*': //Equation
                        $csuffix = '';
                        $sqsuffix = '';
                        $varName = $fielddata['title'];
                        if ($fielddata['aid'] != '') {
                            $varName .= '_' . $fielddata['aid'];
                        }
                        $question = $fielddata['question'];
                        break;
                    case '1': //Array (Flexible Labels) dual scale
                        $csuffix = $fielddata['aid'] . '#' . $fielddata['scale_id'];
                        $sqsuffix = '_' . $fielddata['aid'];
                        $varName = $fielddata['title'] . '_' . $fielddata['aid'] . '_' . $fielddata['scale_id'];;
                        $question = $fielddata['subquestion'] . '[' . $fielddata['scale'] . ']';
                        //                    $question = $fielddata['question'] . ': ' . $fielddata['subquestion'] . '[' . $fielddata['scale'] . ']';
                        $rowdivid = substr($sgqa,0,-2);
                        break;
                    case 'A': //ARRAY (5 POINT CHOICE) radio-buttons
                    case 'B': //ARRAY (10 POINT CHOICE) radio-buttons
                    case 'C': //ARRAY (YES/UNCERTAIN/NO) radio-buttons
                    case 'E': //ARRAY (Increase/Same/Decrease) radio-buttons
                    case 'F': //ARRAY (Flexible) - Row Format
                    case 'H': //ARRAY (Flexible) - Column Format    // note does not have javatbd equivalent - so array filters don't work on it
                    case 'K': //MULTIPLE NUMERICAL QUESTION         // note does not have javatbd equivalent - so array filters don't work on it, but need rowdivid to process validations
                    case 'M': //Multiple choice checkbox
                    case 'P': //Multiple choice with comments checkbox + text
                    case 'Q': //MULTIPLE SHORT TEXT                 // note does not have javatbd equivalent - so array filters don't work on it
                    case 'R': //RANKING STYLE                       // note does not have javatbd equivalent - so array filters don't work on it
                        $csuffix = $fielddata['aid'];
                        $varName = $fielddata['title'] . '_' . $fielddata['aid'];
                        $question = $fielddata['subquestion'];
                        //                    $question = $fielddata['question'] . ': ' . $fielddata['subquestion'];
                        if ($type != 'H') {
                            if ($type == 'P' && preg_match("/comment$/", $sgqa)) {
                                //                            $rowdivid = substr($sgqa,0,-7);
                            }
                            else {
                                $sqsuffix = '_' . $fielddata['aid'];
                                $rowdivid = $sgqa;
                            }
                        }
                        break;
                    case ':': //ARRAY (Multi Flexi) 1 to 10
                    case ';': //ARRAY (Multi Flexi) Text
                        $csuffix = $fielddata['aid'];
                        $sqsuffix = '_' . substr($fielddata['aid'],0,strpos($fielddata['aid'],'_'));
                        $varName = $fielddata['title'] . '_' . $fielddata['aid'];
                        $question = $fielddata['subquestion1'] . '[' . $fielddata['subquestion2'] . ']';
                        //                    $question = $fielddata['question'] . ': ' . $fielddata['subquestion1'] . '[' . $fielddata['subquestion2'] . ']';
                        $rowdivid = substr($sgqa,0,strpos($sgqa,'_'));
                        break;
                    default:
                        // TODO: Internal error if this happens
                        $question = null;
                        break;
                }

                // $onlynum
                $onlynum=false; // the default
                switch($type)
                {
                    case 'K': //MULTIPLE NUMERICAL QUESTION
                    case 'N': //NUMERICAL QUESTION TYPE
                    case ':': //ARRAY (Multi Flexi) 1 to 10
                        $onlynum=true;
                        break;
                    case '*': // Equation
                    case ';': //ARRAY (Multi Flexi) Text
                    case 'Q': //MULTIPLE SHORT TEXT
                    case 'S': //SHORT FREE TEXT
                        if (isset($qattr[$questionNum]['numbers_only']) && $qattr[$questionNum]['numbers_only']=='1')
                        {
                            $onlynum=true;
                        }
                        break;
                    case 'L': //LIST drop-down/radio-button list
                    case 'M': //Multiple choice checkbox
                    case 'P': //Multiple choice with comments checkbox + text
                        if (isset($qattr[$questionNum]['other_numbers_only']) && $qattr[$questionNum]['other_numbers_only']=='1' && preg_match('/other$/',$sgqa))
                        {
                            $onlynum=true;
                        }
                        break;
                    default:
                        break;
                }

                // Set $jsVarName_on (for on-page variables - e.g. answerSGQA) and $jsVarName (for off-page  variables; the primary name - e.g. javaSGQA)
                switch($type)
                {
                    case 'R': //RANKING STYLE
                        $jsVarName_on = 'answer' . $sgqa;
                        $jsVarName = 'java' . $sgqa;
                        break;
                    case 'D': //DATE
                    case 'N': //NUMERICAL QUESTION TYPE
                    case 'S': //SHORT FREE TEXT
                    case 'T': //LONG FREE TEXT
                    case 'U': //HUGE FREE TEXT
                    case 'Q': //MULTIPLE SHORT TEXT
                    case 'K': //MULTIPLE NUMERICAL QUESTION
                    case 'X': //BOILERPLATE QUESTION
                        $jsVarName_on = 'answer' . $sgqa;
                        $jsVarName = 'java' . $sgqa;
                        break;
                    case '!': //List - dropdown
                        if (preg_match("/other$/",$sgqa))
                        {
                            $jsVarName = 'java' . $sgqa;
                            $jsVarName_on = 'othertext' . substr($sgqa,0,-5);
                        }
                        else
                        {
                            $jsVarName = 'java' . $sgqa;
                            $jsVarName_on = $jsVarName;
                        }
                        break;
                    case 'L': //LIST drop-down/radio-button list
                        if (preg_match("/other$/",$sgqa))
                        {
                            $jsVarName = 'java' . $sgqa;
                            $jsVarName_on = 'answer' . $sgqa . "text";
                        }
                        else
                        {
                            $jsVarName = 'java' . $sgqa;
                            $jsVarName_on = $jsVarName;
                        }
                        break;
                    case '5': //5 POINT CHOICE radio-buttons
                    case 'G': //GENDER drop-down list
                    case 'I': //Language Question
                    case 'Y': //YES/NO radio-buttons
                    case '*': //Equation
                    case 'A': //ARRAY (5 POINT CHOICE) radio-buttons
                    case 'B': //ARRAY (10 POINT CHOICE) radio-buttons
                    case 'C': //ARRAY (YES/UNCERTAIN/NO) radio-buttons
                    case 'E': //ARRAY (Increase/Same/Decrease) radio-buttons
                    case 'F': //ARRAY (Flexible) - Row Format
                    case 'H': //ARRAY (Flexible) - Column Format
                    case 'M': //Multiple choice checkbox
                    case 'O': //LIST WITH COMMENT drop-down/radio-button list + textarea
                        if ($type == 'O' && preg_match('/_comment$/', $varName))
                        {
                            $jsVarName_on = 'answer' . $sgqa;
                        }
                        else
                        {
                            $jsVarName_on = 'java' . $sgqa;
                        }
                        $jsVarName = 'java' . $sgqa;
                        break;
                    case '1': //Array (Flexible Labels) dual scale
                        $jsVarName = 'java' . str_replace('#','_',$sgqa);
                        $jsVarName_on = $jsVarName;
                        break;
                    case ':': //ARRAY (Multi Flexi) 1 to 10
                    case ';': //ARRAY (Multi Flexi) Text
                        $jsVarName = 'java' . $sgqa;
                        $jsVarName_on = 'answer' . $sgqa;;
                        break;
                    case '|': //File Upload
                        $jsVarName = $sgqa;
                        $jsVarName_on = $jsVarName;
                        break;
                    case 'P': //Multiple choice with comments checkbox + text
                        if (preg_match("/(other|comment)$/",$sgqa))
                        {
                            $jsVarName_on = 'answer' . $sgqa;  // is this true for survey.php and not for group.php?
                            $jsVarName = 'java' . $sgqa;
                        }
                        else
                        {
                            $jsVarName = 'java' . $sgqa;
                            $jsVarName_on = $jsVarName;
                        }
                        break;
                }
                // Hidden question are never on same page (except for equation)
                if($hidden && $type!="*"){
                    $jsVarName_on = '';
                }

                if (!is_null($rowdivid) || $type == 'L' || $type == 'N' || $type == '!' || $type == 'O'  || !is_null($preg)
                || $type == 'S' || $type == 'D' || $type == 'T' || $type == 'U' || $type == '|') {
                    if (!isset($q2subqInfo[$questionNum])) {
                        $q2subqInfo[$questionNum] = array(
                        'qid' => $questionNum,
                        'qseq' => $questionSeq,
                        'gseq' => $groupSeq,
                        'sgqa' => $surveyid . 'X' . $groupNum . 'X' . $questionNum,
                        'mandatory'=>$mandatory,
                        'varName' => $varName,
                        'type' => $type,
                        'fieldname' => $sgqa,
                        'preg' => $preg,
                        'rootVarName' => $fielddata['title'],
                        );
                    }
                    if (!isset($q2subqInfo[$questionNum]['subqs'])) {
                        $q2subqInfo[$questionNum]['subqs'] = array();
                    }
                    switch ($type)
                    {
                        case 'L':// What using sq: it's only on question + one other if other is set. This don't set the other subq here.
                        case '!':
                            if (!is_null($ansArray))
                            {
                                foreach (array_keys($ansArray) as $key)
                                {
                                    $parts = explode('~',$key);
                                    if ($parts[1] == '-oth-') {
                                        $parts[1] = 'other';
                                    }
                                    $q2subqInfo[$questionNum]['subqs'][] = array(
                                    'rowdivid' => $surveyid . 'X' . $groupNum . 'X' . $questionNum . $parts[1],
                                    'varName' => $varName,
                                    'sqsuffix' => '_' . $parts[1],
                                    );
                                }
                            }
                            break;
                        case 'O':
                            if(strlen($varName) > 8 && substr_compare($varName,'_comment',-8)===0)// The comment subquestion More speediest than regexp
                            {
                                $q2subqInfo[$questionNum]['subqs'][] = array(
                                    'varName' => $varName,
                                    'rowdivid' => $surveyid . 'X' . $groupNum . 'X' . $questionNum.'comment',// Not sure we need it
                                    'jsVarName' => $jsVarName,
                                    'jsVarName_on' => $jsVarName_on,
                                    'sqsuffix' => '_comment',
                                );
                            }
                            else // The question list
                            {
                                $q2subqInfo[$questionNum]['subqs'][] = array(
                                    'varName' => $varName,
                                    'rowdivid' => $surveyid . 'X' . $groupNum . 'X' . $questionNum,
                                    'jsVarName' => $jsVarName,
                                    'jsVarName_on' => $jsVarName_on,
                                );
                            }
                            break;
                        case 'N':
                        case 'S':
                        case 'D':
                        case 'T':
                        case 'U':
                            $q2subqInfo[$questionNum]['subqs'][] = array(
                                'varName' => $varName,
                                'rowdivid' => $surveyid . 'X' . $groupNum . 'X' . $questionNum,
                                'jsVarName' => 'java' . $surveyid . 'X' . $groupNum . 'X' . $questionNum,
                                'jsVarName_on' => $jsVarName_on,
                            );
                            break;
                        default:
                            $q2subqInfo[$questionNum]['subqs'][] = array(
                                'rowdivid' => $rowdivid,
                                'varName' => $varName,
                                'jsVarName_on' => $jsVarName_on,
                                'jsVarName' => $jsVarName,
                                'csuffix' => $csuffix,
                                'sqsuffix' => $sqsuffix,
                            );
                            break;
                    }
                }

                $ansList = '';
                if (isset($ansArray) && !is_null($ansArray)) {
                    $answers = array();
                    foreach ($ansArray as $key => $value) {
                        $answers[] = "'" . $key . "':'" . htmlspecialchars(preg_replace('/[[:space:]]/',' ',$value),ENT_QUOTES) . "'";
                    }
                    $ansList = ",'answers':{ " . implode(",",$answers) . "}";
                }

                // Set mappings of variable names to needed attributes
                $varInfo_Code = array(
                'jsName_on'=>$jsVarName_on,
                'jsName'=>$jsVarName,
                'readWrite'=>$readWrite,
                'hidden'=>$hidden,
                'question'=>$question,
                'qid'=>$questionNum,
                'gid'=>$groupNum,
                'grelevance'=>$grelevance,
                'relevance'=>$relevance,
                'SQrelevance'=>$SQrelevance,
                'qcode'=>$varName,
                'qseq'=>$questionSeq,
                'gseq'=>$groupSeq,
                'type'=>$type,
                'sgqa'=>$sgqa,
                'ansList'=>$ansList,
                'ansArray'=>$ansArray,
                'scale_id'=>$scale_id,
                'default'=>$defaultValue,
                'rootVarName'=>$fielddata['title'],
                'subqtext'=>$subqtext,
                'rowdivid'=>(is_null($rowdivid) ? '' : $rowdivid),
                'onlynum'=>$onlynum,
                );

                $this->questionSeq2relevance[$questionSeq] = array(
                'relevance'=>$relevance,
                'grelevance'=>$grelevance,
                //'SQrelevance'=>$SQrelevance,
                'qid'=>$questionNum,
                'qseq'=>$questionSeq,
                'gseq'=>$groupSeq,
                'jsResultVar_on'=>$jsVarName_on,
                'jsResultVar'=>$jsVarName,
                'type'=>$type,
                'hidden'=>$hidden,
                'gid'=>$groupNum,
                'mandatory'=>$mandatory,
                'eqn'=>'',
                'help'=>$help,
                'qtext'=>$fielddata['question'],    // $question,
                'code'=>$varName,
                'other'=>$other,
                'default'=>$defaultValue,
                'rootVarName'=>$fielddata['title'],
                'rowdivid'=>(is_null($rowdivid) ? '' : $rowdivid),
                'aid'=>$aid,
                'sqid'=>$sqid,
                );

                $this->knownVars[$sgqa] = $varInfo_Code;
                $this->qcode2sgqa[$varName]=$sgqa;
                $this->jsVar2qid[$jsVarName] = $questionNum;
                $this->qcode2sgq[$fielddata['title']] = $surveyid . 'X' . $groupNum . 'X' . $questionNum;

                // Create JavaScript arrays
                $this->alias2varName[$varName] = array('jsName'=>$jsVarName, 'jsPart' => "'" . $varName . "':'" . $jsVarName . "'");
                $this->alias2varName[$sgqa] = array('jsName'=>$jsVarName, 'jsPart' => "'" . $sgqa . "':'" . $jsVarName . "'");

                $this->varNameAttr[$jsVarName] = "'" . $jsVarName . "':{ "
                . "'jsName':'" . $jsVarName
                . "','jsName_on':'" . $jsVarName_on
                . "','sgqa':'" . $sgqa
                . "','qid':" . $questionNum
                . ",'gid':" . $groupNum
                //                . ",'mandatory':'" . $mandatory
                //                . "','question':'" . htmlspecialchars(preg_replace('/[[:space:]]/',' ',$question),ENT_QUOTES)
                . ",'type':'" . $type
                //                . "','relevance':'" . (($relevance != '') ? htmlspecialchars(preg_replace('/[[:space:]]/',' ',$relevance),ENT_QUOTES) : 1)
                //                . "','readWrite':'" . $readWrite
                //                . "','grelevance':'" . (($grelevance != '') ? htmlspecialchars(preg_replace('/[[:space:]]/',' ',$grelevance),ENT_QUOTES) : 1)
                . "','default':'" . (is_null($defaultValue) ? '' : str_replace("'", "\'", $defaultValue))
                . "','rowdivid':'" . (is_null($rowdivid) ?  '' : $rowdivid)
                . "','onlynum':'" . ($onlynum ? '1' : '')
                . "','gseq':" . $groupSeq
                //                . ",'qseq':" . $questionSeq
                .$ansList;

                if ($type == 'M' || $type == 'P')
                {
                    $this->varNameAttr[$jsVarName] .= ",'question':'" . htmlspecialchars(preg_replace('/[[:space:]]/',' ',$question),ENT_QUOTES) . "'";
                }
                $this->varNameAttr[$jsVarName] .= "}";
            }
            $this->q2subqInfo = $q2subqInfo;
            // Now set tokens
            if (Survey::model()->hasTokens($surveyid) && isset($_SESSION[$this->sessid]['token']) && $_SESSION[$this->sessid]['token'] != '')
            {
                //Gather survey data for tokenised surveys, for use in presenting questions
                $this->knownVars['TOKEN:TOKEN'] = array(
                    'code'=>$_SESSION[$this->sessid]['token'],
                    'jsName_on'=>'',
                    'jsName'=>'',
                    'readWrite'=>'N',
                );
                $this->knownVars['TOKEN'] = array(
                    'code'=>$_SESSION[$this->sessid]['token'],
                    'jsName_on'=>'',
                    'jsName'=>'',
                    'readWrite'=>'N',
                );

                $token = Token::model($surveyid)->findByToken($_SESSION[$this->sessid]['token']);
                if($token) {
                    foreach ($token as $key => $val)
                    {
                        $this->knownVars["TOKEN:" . strtoupper($key)] = array(
                            'code' => $anonymized ? '' : $val,
                            'jsName_on' => '',
                            'jsName' => '',
                            'readWrite' => 'N',
                        );
                    }
                }
            }
            else
            {
                // Read list of available tokens from the tokens table so that preview and error checking works correctly
                $attrs = array_keys(getTokenFieldsAndNames($surveyid));

                $blankVal = array(
                    'code'=>'',
                    'type'=>'',
                    'jsName_on'=>'',
                    'jsName'=>'',
                    'readWrite'=>'N',
                );
                // DON'T set $this->knownVars['TOKEN'] = $blankVal; becuase optout/optin can need it, then don't replace this from templatereplace
                foreach ($attrs as $key)
                {
                    if (preg_match('/^(firstname|lastname|email|usesleft|token|attribute_\d+)$/',$key))
                    {
                        $this->knownVars['TOKEN:' . strtoupper($key)] = $blankVal;
                    }
                }
            }
            // set default value for reserved 'this' variable
            $this->knownVars['this'] = array(
            'jsName_on'=>'',
            'jsName'=>'',
            'readWrite'=>'',
            'hidden'=>'',
            'question'=>'this',
            'qid'=>'',
            'gid'=>'',
            'grelevance'=>'',
            'relevance'=>'',
            'SQrelevance'=>'',
            'qcode'=>'this',
            'qseq'=>'',
            'gseq'=>'',
            'type'=>'',
            'sgqa'=>'',
            'rowdivid'=>'',
            'ansList'=>'',
            'ansArray'=>array(),
            'scale_id'=>'',
            'default'=>'',
            'rootVarName'=>'this',
            'subqtext'=>'',
            'rowdivid'=>'',
            );

            $this->runtimeTimings[] = array(__METHOD__ . ' - process fieldMap',(microtime(true) - $now));
            usort($this->questionSeq2relevance,'cmpQuestionSeq');
            $this->numQuestions = count($this->questionSeq2relevance);
            $this->numGroups = count($this->groupSeqInfo);

            return true;
        }

        /**
        * Return whether a sub-question is relevant
        * @param string $sgqa
        * @return boolean
        */
        static function SubQuestionIsRelevant($sgqa)
        {
            $LEM =& LimeExpressionManager::singleton();
            if (!isset($LEM->knownVars[$sgqa]))
            {
                return false;
            }
            $var = $LEM->knownVars[$sgqa];
            $sqrel=1;
            if (isset($var['rowdivid']) && $var['rowdivid'] != '')
            {
                $sqrel = (isset($_SESSION[$LEM->sessid]['relevanceStatus'][$var['rowdivid']]) ? $_SESSION[$LEM->sessid]['relevanceStatus'][$var['rowdivid']] : 1);
            }
            $qid = $var['qid'];
            $qrel = (isset($_SESSION[$LEM->sessid]['relevanceStatus'][$qid]) ? $_SESSION[$LEM->sessid]['relevanceStatus'][$qid] : 1);
            $gseq = $var['gseq'];
            $grel = (isset($_SESSION[$LEM->sessid]['relevanceStatus']['G' . $gseq]) ? $_SESSION[$LEM->sessid]['relevanceStatus']['G' . $gseq] : 1);   // group-level relevance based upon grelevance equation
            return ($grel && $qrel && $sqrel);
        }

        /**
        * Return whether question $qid is relevanct
        * @param int $qid
        * @return boolean
        */
        static function QuestionIsRelevant($qid)
        {
            $LEM =& LimeExpressionManager::singleton();
            $qrel = (isset($_SESSION[$LEM->sessid]['relevanceStatus'][$qid]) ? $_SESSION[$LEM->sessid]['relevanceStatus'][$qid] : 1);
            $gseq = (isset($LEM->questionId2groupSeq[$qid]) ? $LEM->questionId2groupSeq[$qid] : -1);
            $grel = (isset($_SESSION[$LEM->sessid]['relevanceStatus']['G' . $gseq]) ? $_SESSION[$LEM->sessid]['relevanceStatus']['G' . $gseq] : 1);   // group-level relevance based upon grelevance equation
            return ($grel && $qrel);
        }

        /**
         * Returns true if the group is relevant and should be shown
         *
         * @param int $gid
         * @return boolean
         */
        static function GroupIsRelevant($gid)
        {
            $LEM =& LimeExpressionManager::singleton();
            $gseq = $LEM->GetGroupSeq($gid);
            return !$LEM->GroupIsIrrelevantOrHidden($gseq);
        }

        /**
        * Return whether group $gseq is relevant
        * @param string $gseq
        * @return boolean
        */
        static function GroupIsIrrelevantOrHidden($gseq)
        {
            $LEM =& LimeExpressionManager::singleton();

            // We check again if it should really be false...
            if(isset($_SESSION[$LEM->sessid]['relevanceStatus']['G' . $gseq]) && $_SESSION[$LEM->sessid]['relevanceStatus']['G' . $gseq] == false)
            {
                $LEM->_ProcessGroupRelevance($gseq);
            }

            $grel = (isset($_SESSION[$LEM->sessid]['relevanceStatus']['G' . $gseq])) ? $_SESSION[$LEM->sessid]['relevanceStatus']['G' . $gseq] : 1;   // group-level relevance based upon grelevance equation
            $gshow = (isset($LEM->indexGseq[$gseq]['show'])) ? $LEM->indexGseq[$gseq]['show'] : true;   // default to true?

            return !($grel && $gshow);
        }

        /**
        * Check the relevance status of all questions on or before the current group.
        * This generates needed JavaScript for dynamic relevance, and sets flags about which questions and groups are relevant
        * @param string $onlyThisQseq
        * @return void
        */
        function ProcessAllNeededRelevance($onlyThisQseq=NULL)
        {
            // TODO - in a running survey, only need to process the current Group.  For Admin mode, do we need to process all prior questions or not?
            //        $now = microtime(true);

            $grelComputed=array();  // so only process it once per group
            foreach($this->questionSeq2relevance as $rel)
            {
                if (!is_null($onlyThisQseq) && $onlyThisQseq!=$rel['qseq']) {
                    continue;
                }
                $qid = $rel['qid'];
                $gseq = $rel['gseq'];
                if ($this->allOnOnePage) {
                    ;   // process relevance for all questions
                }
                else if ($gseq != $this->currentGroupSeq) {
                    continue;
                }
                $result = $this->_ProcessRelevance(htmlspecialchars_decode($rel['relevance'],ENT_QUOTES),
                    $qid,
                    $gseq,
                    $rel['jsResultVar'],
                    $rel['type'],
                    $rel['hidden']
                    );
                $_SESSION[$this->sessid]['relevanceStatus'][$qid] = $result;

                if (!isset($grelComputed[$gseq])) {
                    $this->_ProcessGroupRelevance($gseq);
                    $grelComputed[$gseq]=true;
                }
            }
            //        $this->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
        }

         /**
        * Translate all Expressions, Macros, registered variables, etc. in $string
        * @param string $string - the string to be replaced
        * @param integer $questionNum - the $qid of question being replaced - needed for properly alignment of question-level relevance and tailoring
        * @param array|null $replacementFields - optional replacement values
        * @param boolean $debug - deprecated
        * @param integer $numRecursionLevels - the number of times to recursively subtitute values in this string
        * @param integer $whichPrettyPrintIteration - if want to pretty-print the source string, which recursion  level should be pretty-printed
        * @param boolean $noReplacements - true if we already know that no replacements are needed (e.g. there are no curly braces)
        * @param boolean $timeit
        * @param boolean $staticReplacement - return HTML string without the system to update by javascript
        * @return string - the original $string with all replacements done.
        */
        static function ProcessString($string, $questionNum=NULL, $replacementFields=array(), $debug=false, $numRecursionLevels=1, $whichPrettyPrintIteration=1, $noReplacements=false, $timeit=true, $staticReplacement=false)
        {
            $now = microtime(true);
            $LEM =& LimeExpressionManager::singleton();

            if ($noReplacements) {
                $LEM->em->SetPrettyPrintSource($string);
                return $string;
            }

            if (isset($replacementFields) && is_array($replacementFields) && count($replacementFields) > 0)
            {
                $replaceArray = array();
                foreach ($replacementFields as $key => $value) {
                    $replaceArray[$key] = array(
                    'code'=>$value,
                    'jsName_on'=>'',
                    'jsName'=>'',
                    'readWrite'=>'N',
                    );
                }
                $LEM->tempVars = $replaceArray;
            }
            $questionSeq = -1;
            $groupSeq = -1;
            if (!is_null($questionNum)) {
                $questionSeq = isset($LEM->questionId2questionSeq[$questionNum]) ? $LEM->questionId2questionSeq[$questionNum] : -1;
                $groupSeq = isset($LEM->questionId2groupSeq[$questionNum]) ? $LEM->questionId2groupSeq[$questionNum] : -1;
            }
            $stringToParse = $string;   // decode called later htmlspecialchars_decode($string,ENT_QUOTES);
            $qnum = is_null($questionNum) ? 0 : $questionNum;
            $result = $LEM->em->sProcessStringContainingExpressions($stringToParse,$qnum, $numRecursionLevels, $whichPrettyPrintIteration, $groupSeq, $questionSeq, $staticReplacement);

            if ($timeit) {
                $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
            }

            return $result;
        }


        /**
        * Compute Relevance, processing $eqn to get a boolean value.  If there are syntax errors, return false.
        * @param string $eqn - the relevance equation
        * @param string $questionNum - needed to align question-level relevance and tailoring
        * @param string $jsResultVar - this variable determines whether irrelevant questions are hidden
        * @param string $type - question type
        * @param int $hidden - whether question should always be hidden
        * @return boolean
        */
        static function ProcessRelevance($eqn,$questionNum=NULL,$jsResultVar=NULL,$type=NULL,$hidden=0)
        {
            $LEM =& LimeExpressionManager::singleton();
            return $LEM->_ProcessRelevance($eqn,$questionNum,NULL,$jsResultVar,$type,$hidden);
        }

        /**
        * Compute Relevance, processing $eqn to get a boolean value.  If there are syntax errors, return false.
        * @param string $eqn - the relevance equation
        * @param string $questionNum - needed to align question-level relevance and tailoring
        * @param string $jsResultVar - this variable determines whether irrelevant questions are hidden
        * @param string $type - question type
        * @param int $hidden - whether question should always be hidden
        * @return boolean
        */
        private function _ProcessRelevance($eqn,$questionNum=NULL,$gseq=NULL,$jsResultVar=NULL,$type=NULL,$hidden=0)
        {
            // These will be called in the order that questions are supposed to be asked
            // TODO - cache results and generated JavaScript equations?
            if (!isset($eqn) || trim($eqn=='') || trim($eqn)=='1')
            {
                $this->groupRelevanceInfo[] = array(
                'qid' => $questionNum,
                'gseq' => $gseq,
                'eqn' => $eqn,
                'result' => true,
                'numJsVars' => 0,
                'relevancejs' => '',
                'relevanceVars' => '',
                'jsResultVar'=> $jsResultVar,
                'type'=>$type,
                'hidden'=>$hidden,
                'hasErrors'=>false,
                );
                return true;
            }
            $questionSeq = -1;
            $groupSeq = -1;
            if (!is_null($questionNum)) {
                $questionSeq = isset($this->questionId2questionSeq[$questionNum]) ? $this->questionId2questionSeq[$questionNum] : -1;
                $groupSeq = isset($this->questionId2groupSeq[$questionNum]) ? $this->questionId2groupSeq[$questionNum] : -1;
            }

            $stringToParse = htmlspecialchars_decode($eqn,ENT_QUOTES);
            $result = $this->em->ProcessBooleanExpression($stringToParse,$groupSeq, $questionSeq);
            $hasErrors = $this->em->HasErrors();

            if (!is_null($questionNum) && !is_null($jsResultVar)) { // so if missing either, don't generate JavaScript for this - means off-page relevance.
                $jsVars = $this->em->GetJSVarsUsed();
                $relevanceVars = implode('|',$this->em->GetJSVarsUsed());
                $relevanceJS = $this->em->GetJavaScriptEquivalentOfExpression();
                $this->groupRelevanceInfo[] = array(
                'qid' => $questionNum,
                'gseq' => $gseq,
                'eqn' => $eqn,
                'result' => $result,
                'numJsVars' => count($jsVars),
                'relevancejs' => $relevanceJS,
                'relevanceVars' => $relevanceVars,
                'jsResultVar' => $jsResultVar,
                'type'=>$type,
                'hidden'=>$hidden,
                'hasErrors'=>$hasErrors,
                );
            }
            return $result;
        }

        /**
        * Create JavaScript needed to process sub-question-level relevance (e.g. for array_filter and  _exclude)
        * @param string $eqn - the equation to parse
        * @param string $questionNum - the question number - needed to align relavance and tailoring blocks
        * @param string $rowdivid - the javascript ID that needs to be shown/hidden in order to control array_filter visibility
        * @param string $type - the type of sub-question relevance (e.g. 'array_filter', 'array_filter_exclude')
        * @param string $qtype
        * @param string $sgqa
        * @param string $isExclusive
        * @param string $irrelevantAndExclusive
        * @return boolean
        */
        private function _ProcessSubQRelevance($eqn,$questionNum=NULL,$rowdivid=NULL, $type=NULL, $qtype=NULL, $sgqa=NULL, $isExclusive='', $irrelevantAndExclusive='')
        {
            // These will be called in the order that questions are supposed to be asked
            if (!isset($eqn) || trim($eqn=='') || trim($eqn)=='1')
            {
                return true;
            }
            $questionSeq = -1;
            $groupSeq = -1;
            if (!is_null($questionNum)) {
                $questionSeq = isset($this->questionId2questionSeq[$questionNum]) ? $this->questionId2questionSeq[$questionNum] : -1;
                $groupSeq = isset($this->questionId2groupSeq[$questionNum]) ? $this->questionId2groupSeq[$questionNum] : -1;
            }

            $stringToParse = htmlspecialchars_decode($eqn,ENT_QUOTES);
            $result = $this->em->ProcessBooleanExpression($stringToParse,$groupSeq, $questionSeq);
            $hasErrors = $this->em->HasErrors();
            $prettyPrint = '';
            if (($this->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX) {
                $prettyPrint= $this->em->GetPrettyPrintString();
            }

            if (!is_null($questionNum)) {
                // make sure subquestions with errors in relevance equations are always shown and answers recorded  #7703
                if ($hasErrors)
                {
                    $result=true;
                    $relevanceJS=1;
                }
                else
                {
                    $relevanceJS = $this->em->GetJavaScriptEquivalentOfExpression();
                }
                $jsVars = $this->em->GetJSVarsUsed();
                $relevanceVars = implode('|',$this->em->GetJSVarsUsed());
                $isExclusiveJS='';
                $irrelevantAndExclusiveJS='';
                // Only need to extract JS, since will already have Vars and error counts from main equation
                if ($isExclusive != '')
                {
                    $this->em->ProcessBooleanExpression($isExclusive,$groupSeq, $questionSeq);
                    $isExclusiveJS  = $this->em->GetJavaScriptEquivalentOfExpression();
                }
                if ($irrelevantAndExclusive != '')
                {
                    $this->em->ProcessBooleanExpression($irrelevantAndExclusive,$groupSeq, $questionSeq);
                    $irrelevantAndExclusiveJS = $this->em->GetJavaScriptEquivalentOfExpression();
                }

                if (!isset($this->subQrelInfo[$questionNum])) {
                    $this->subQrelInfo[$questionNum] = array();
                }
                $this->subQrelInfo[$questionNum][$rowdivid] = array(
                'qid' => $questionNum,
                'eqn' => $eqn,
                'prettyPrintEqn' => $prettyPrint,
                'result' => $result,
                'numJsVars' => count($jsVars),
                'relevancejs' => $relevanceJS,
                'relevanceVars' => $relevanceVars,
                'rowdivid' => $rowdivid,
                'type'=>$type,
                'qtype'=>$qtype,
                'sgqa'=>$sgqa,
                'hasErrors'=>$hasErrors,
                'isExclusiveJS'=>$isExclusiveJS,
                'irrelevantAndExclusiveJS'=>$irrelevantAndExclusiveJS,
                );
            }
            return $result;
        }

        /**
         * @param int $groupSeq
         * @return void
         */
        private function _ProcessGroupRelevance($groupSeq)
        {
            // These will be called in the order that questions are supposed to be asked
            if ($groupSeq == -1) {
                return; // invalid group, so ignore
            }

            $eqn = (isset($this->gseq2info[$groupSeq]['grelevance']) ? $this->gseq2info[$groupSeq]['grelevance'] : 1);
            if (is_null($eqn) || trim($eqn=='') || trim($eqn)=='1')
            {
                $this->gRelInfo[$groupSeq] = array(
                'gseq' => $groupSeq,
                'eqn' => '',
                'result' => 1,
                'numJsVars' => 0,
                'relevancejs' => '',
                'relevanceVars' => '',
                'prettyprint'=> '',
                );
                $_SESSION[$this->sessid]['relevanceStatus']['G' . $groupSeq] = 1;
                return;
            }
            $stringToParse = htmlspecialchars_decode($eqn,ENT_QUOTES);
            $result = $this->em->ProcessBooleanExpression($stringToParse,$groupSeq);
            $hasErrors = $this->em->HasErrors();

            $jsVars = $this->em->GetJSVarsUsed();
            $relevanceVars = implode('|',$this->em->GetJSVarsUsed());
            $relevanceJS = $this->em->GetJavaScriptEquivalentOfExpression();
            $prettyPrint = $this->em->GetPrettyPrintString();

            $this->gRelInfo[$groupSeq] = array(
                'gseq' => $groupSeq,
                'eqn' => $stringToParse,
                'result' => $result,
                'numJsVars' => count($jsVars),
                'relevancejs' => $relevanceJS,
                'relevanceVars' => $relevanceVars,
                'prettyprint'=> $prettyPrint,
                'hasErrors' => $hasErrors,
                );
            $_SESSION[$this->sessid]['relevanceStatus']['G' . $groupSeq] = $result;
        }

        /**
        * Used to show potential syntax errors of processing Relevance or Equations.
        * @return string
        */
        static function GetLastPrettyPrintExpression()
        {
            $LEM =& LimeExpressionManager::singleton();
            return $LEM->em->GetLastPrettyPrintExpression();
        }

        /**
         * Expand "self.suffix" and "that.qcode.suffix" into canonical list of variable names
         * @param integer $qseq
         * @param string $varname
         * @return string
         */
        static function GetAllVarNamesForQ($qseq,$varname)
        {
            $LEM =& LimeExpressionManager::singleton();

            $parts = explode('.',$varname);
            $qroot = '';
            $suffix = '';
            $sqpatts = array();
            $nosqpatts = array();
            $comments = '';

            if ($parts[0] == 'self')
            {
                $type = 'self';
            }
            else
            {
                $type = 'that';
                array_shift($parts);
                if (isset($parts[0]))
                {
                    $qroot = $parts[0];
                }
                else
                {
                    return $varname;
                }
            }
            array_shift($parts);

            if (count($parts) > 0)
            {
                if (preg_match('/^' . ExpressionManager::$RDP_regex_var_attr . '$/',$parts[count($parts)-1]))
                {
                    $suffix = '.' . $parts[count($parts)-1];
                    array_pop($parts);
                }
            }

            foreach($parts as $part)
            {
                if ($part == 'nocomments')
                {
                    $comments = 'N';
                }
                else if ($part == 'comments')
                {
                    $comments = 'Y';
                }
                else if (preg_match('/^sq_.+$/',$part))
                {
                    $sqpatts[] = substr($part,3);
                }
                else if (preg_match('/^nosq_.+$/',$part))
                {
                    $nosqpatts[] = substr($part,5);
                }
                else
                {
                    return $varname;    // invalid
                }
            }
            $sqpatt = implode('|',$sqpatts);
            $nosqpatt = implode('|',$nosqpatts);
            $vars = array();
            if(isset($LEM->knownVars))
            {
                foreach ($LEM->knownVars as $kv)
                {
                    if ($type == 'self')
                    {
                        if (!isset($kv['qseq']) || $kv['qseq'] != $qseq || trim($kv['sgqa']) == '')
                        {
                            continue;
                        }
                    }
                    else
                    {
                        if (!isset($kv['rootVarName']) || $kv['rootVarName'] != $qroot)
                        {
                            continue;
                        }
                    }
                    if ($comments != '')
                    {
                        if ($comments == 'Y' && !preg_match('/comment$/',$kv['sgqa']))
                        {
                            continue;
                        }
                        if ($comments == 'N' && preg_match('/comment$/',$kv['sgqa']))
                        {
                            continue;
                        }
                    }
                    $sgq = $LEM->sid . 'X' . $kv['gid'] . 'X' . $kv['qid'];
                    $ext = substr($kv['sgqa'],strlen($sgq));

                    if ($sqpatt != '')
                    {
                        if (!preg_match('/'.$sqpatt.'/',$ext))
                        {
                            continue;
                        }
                    }
                    if ($nosqpatt != '')
                    {
                        if (preg_match('/'.$nosqpatt.'/',$ext))
                        {
                            continue;
                        }
                    }

                    $vars[] = $kv['sgqa'] . $suffix;
                }
            }
            if (count($vars) > 0)
            {
                return implode(',',$vars);
            }
            return $varname;    // invalid
        }

        /**
        * Should be first function called on each page - sets/clears internally needed variables
        * @param boolean $allOnOnePage - true if StartProcessingGroup will be called multiple times on this page - does some optimizatinos
        * @param boolean $initializeVars - if true, initializes the replacement variables to enable syntax highlighting on admin pages
        * @return void
        */
        static function StartProcessingPage($allOnOnePage=false,$initializeVars=false)
        {
            //        $now = microtime(true);
            $LEM =& LimeExpressionManager::singleton();
            $LEM->pageRelevanceInfo=array();
            $LEM->pageTailorInfo=array();
            $LEM->allOnOnePage=$allOnOnePage;
            $LEM->processedRelevance=false;
            $LEM->surveyOptions['hyperlinkSyntaxHighlighting']=true;    // this will be temporary - should be reset in running survey
            $LEM->qid2exclusiveAuto=array();

            $surveyinfo = (isset($LEM->sid) ? getSurveyInfo($LEM->sid) : null);
            if (isset($surveyinfo['assessments']) && $surveyinfo['assessments']=='Y')
            {
                $LEM->surveyOptions['assessments']=true;
            }
            //        $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));

            $LEM->initialized=true;

            if ($initializeVars)
            {
                $LEM->em->StartProcessingGroup(
                    isset($_SESSION['LEMsid']) ? $_SESSION['LEMsid'] : NULL,
                    '',
                    true
                );
                $LEM->setVariableAndTokenMappingsForExpressionManager($_SESSION['LEMsid']);
            }

        }

        /**
        * Initialize a survey so can use EM to manage navigation
        * @param int $surveyid
        * @param string $surveyMode
        * @param array $aSurveyOptions
        * @param bool $forceRefresh
        * @param int $debugLevel
        */
        static function StartSurvey($surveyid,$surveyMode='group',$aSurveyOptions=NULL,$forceRefresh=false,$debugLevel=0)
        {
            $LEM =& LimeExpressionManager::singleton();
            $LEM->sid=sanitize_int($surveyid);
            $LEM->sessid = 'survey_' . $LEM->sid;
            $LEM->em->StartProcessingGroup($surveyid);
            if (is_null($aSurveyOptions)) {
                $aSurveyOptions = array();
            }
            $LEM->surveyOptions['active'] = (isset($aSurveyOptions['active']) ? $aSurveyOptions['active'] : false);
            $LEM->surveyOptions['allowsave'] = (isset($aSurveyOptions['allowsave']) ? $aSurveyOptions['allowsave'] : false);
            $LEM->surveyOptions['anonymized'] = (isset($aSurveyOptions['anonymized']) ? $aSurveyOptions['anonymized'] : false);
            $LEM->surveyOptions['assessments'] = (isset($aSurveyOptions['assessments']) ? $aSurveyOptions['assessments'] : false);
            $LEM->surveyOptions['datestamp'] = (isset($aSurveyOptions['datestamp']) ? $aSurveyOptions['datestamp'] : false);
            $LEM->surveyOptions['deletenonvalues'] = (isset($aSurveyOptions['deletenonvalues']) ? ($aSurveyOptions['deletenonvalues']=='1') : true);
            $LEM->surveyOptions['hyperlinkSyntaxHighlighting'] = (isset($aSurveyOptions['hyperlinkSyntaxHighlighting']) ? $aSurveyOptions['hyperlinkSyntaxHighlighting'] : false);
            $LEM->surveyOptions['ipaddr'] = (isset($aSurveyOptions['ipaddr']) ? $aSurveyOptions['ipaddr'] : false);
            $LEM->surveyOptions['radix'] = (isset($aSurveyOptions['radix']) ? $aSurveyOptions['radix'] : '.');
            $LEM->surveyOptions['refurl'] = (isset($aSurveyOptions['refurl']) ? $aSurveyOptions['refurl'] : NULL);
            $LEM->surveyOptions['savetimings'] = (isset($aSurveyOptions['savetimings']) ? $aSurveyOptions['savetimings'] : '');
            $LEM->sgqaNaming = (isset($aSurveyOptions['sgqaNaming']) ? ($aSurveyOptions['sgqaNaming']=="Y") : true); // TODO default should eventually be false
            $LEM->surveyOptions['startlanguage'] = (isset($aSurveyOptions['startlanguage']) ? $aSurveyOptions['startlanguage'] : 'en');
            $LEM->surveyOptions['surveyls_dateformat'] = (isset($aSurveyOptions['surveyls_dateformat']) ? $aSurveyOptions['surveyls_dateformat'] : 1);
            $LEM->surveyOptions['tablename'] = (isset($aSurveyOptions['tablename']) ? $aSurveyOptions['tablename'] : '{{survey_' . $LEM->sid . '}}');
            $LEM->surveyOptions['tablename_timings'] = ((isset($aSurveyOptions['savetimings']) && $aSurveyOptions['savetimings'] == 'Y') ? '{{survey_' . $LEM->sid . '_timings}}' : '');
            $LEM->surveyOptions['target'] = (isset($aSurveyOptions['target']) ? $aSurveyOptions['target'] : '/temp/files/');
            $LEM->surveyOptions['timeadjust'] = (isset($aSurveyOptions['timeadjust']) ? $aSurveyOptions['timeadjust'] : 0);
            $LEM->surveyOptions['tempdir'] = (isset($aSurveyOptions['tempdir']) ? $aSurveyOptions['tempdir'] : '/temp/');
            $LEM->surveyOptions['token'] = (isset($aSurveyOptions['token']) ? $aSurveyOptions['token'] : NULL);

            $LEM->debugLevel=$debugLevel;
            $_SESSION[$LEM->sessid]['LEMdebugLevel']=$debugLevel; // need acces to SESSSION to decide whether to cache serialized instance of $LEM
            switch ($surveyMode) {
                case 'survey':
                    $LEM->allOnOnePage=true;
                    $LEM->surveyMode = 'survey';
                    break;
                case 'question':
                    $LEM->allOnOnePage=false;
                    $LEM->surveyMode = 'question';
                    break;
                case 'group':
                    /* FALLTHRU */
                default:
                    $LEM->allOnOnePage=false;
                    $LEM->surveyMode = 'group';
                    break;
            }

            $LEM->setVariableAndTokenMappingsForExpressionManager($surveyid,$forceRefresh,$LEM->surveyOptions['anonymized'],$LEM->allOnOnePage);
            $LEM->currentGroupSeq=-1;
            $LEM->currentQuestionSeq=-1;    // for question-by-question mode
            $LEM->indexGseq=array();
            $LEM->indexQseq=array();
            $LEM->qrootVarName2arrayFilter=array();
            if (isset($_SESSION[$LEM->sessid]['startingValues']) && is_array($_SESSION[$LEM->sessid]['startingValues']) && count($_SESSION[$LEM->sessid]['startingValues']) > 0)
            {
                foreach ($_SESSION[$LEM->sessid]['startingValues'] as $k=>$value)
                {
                    if (isset($LEM->knownVars[$k]))
                    {
                        $knownVar = $LEM->knownVars[$k];
                    }
                    else if (isset($LEM->qcode2sgqa[$k]))
                    {
                        $knownVar = $LEM->knownVars[$LEM->qcode2sgqa[$k]];
                    }
                    else
                    {
                        continue;
                    }
                    if (!isset($knownVar['jsName']))
                    {
                        continue;
                    }
                    switch ($knownVar['type'])
                    {
                        case 'D': //DATE
                            if (trim($value)=="")
                            {
                                $value = NULL;
                            }
                            else
                            {
                                // We don't really validate date here, anyone can send anything : forced too
                                $dateformatdatat=getDateFormatData($LEM->surveyOptions['surveyls_dateformat']);
                                $datetimeobj = new Date_Time_Converter($value, $dateformatdatat['phpdate']);
                                $value=$datetimeobj->convert("Y-m-d H:i");
                            }
                            break;
                        case 'N': //NUMERICAL QUESTION TYPE
                        case 'K': //MULTIPLE NUMERICAL QUESTION
                            if (trim($value)=="")
                            {
                                $value = NULL;
                            }
                            else
                            {
                                $value = sanitize_float($value);
                            }
                            break;
                        case '|': //File Upload
                            $value=NULL;  // can't upload a file via GET
                            break;
                    }
                    $_SESSION[$LEM->sessid][$knownVar['sgqa']] = $value;
                    $LEM->updatedValues[$knownVar['sgqa']]=array(
                        'type'=>$knownVar['type'],
                        'value'=>$value,
                    );
                }
                $LEM->_UpdateValuesInDatabase(NULL);
            }

            return array(
            'hasNext'=>true,
            'hasPrevious'=>false,
            );
        }

        /**
         * @return void
         */
        static function NavigateBackwards()
        {
            $now = microtime(true);
            $LEM =& LimeExpressionManager::singleton();

            $LEM->ParseResultCache=array();    // to avoid running same test more than once for a given group
            $LEM->updatedValues = array();

            switch ($LEM->surveyMode)
            {
                case 'survey':
                    // should never be called?
                    break;
                case 'group':
                    // First validate the current group
                    $LEM->StartProcessingPage();
                    $updatedValues=$LEM->ProcessCurrentResponses();
                    $message = '';
                    while (true)
                    {
                        $LEM->currentQset = array();    // reset active list of questions
                        if (is_null($LEM->currentGroupSeq)) {$LEM->currentGroupSeq=0;} // If moving backwards in preview mode and a question was removed then $LEM->currentGroupSeq is NULL and an endless loop occurs.
                        if (--$LEM->currentGroupSeq < 0) // Stop at start
                        {
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,false);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'at_start'=>true,
                                'finished'=>false,
                                'message'=>$message,
                                'unansweredSQs'=>(isset($result['unansweredSQs']) ? $result['unansweredSQs'] : ''),
                                'invalidSQs'=>(isset($result['invalidSQs']) ? $result['invalidSQs'] : ''),
                            );
                            return $LEM->lastMoveResult;
                        }

                        $result = $LEM->_ValidateGroup($LEM->currentGroupSeq);
                        if (is_null($result)) {
                            continue;   // this is an invalid group - skip it
                        }
                        $message .= $result['message'];
                        if (!$result['relevant'] || $result['hidden'])
                        {
                            // then skip this group - assume already saved?
                            continue;
                        }
                        else
                        {
                            // display new group
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,false);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'at_start'=>false,
                                'finished'=>false,
                                'message'=>$message,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentGroupSeq,
                                'mandViolation'=> (($LEM->maxGroupSeq > $LEM->currentGroupSeq) ? $result['mandViolation'] : false),
                                'valid'=> (($LEM->maxGroupSeq > $LEM->currentGroupSeq) ? $result['valid'] : false),
                                'unansweredSQs'=>$result['unansweredSQs'],
                                'invalidSQs'=>$result['invalidSQs'],
                            );
                            return $LEM->lastMoveResult;
                        }
                    }
                    break;
                case 'question':
                    $LEM->StartProcessingPage();
                    $updatedValues=$LEM->ProcessCurrentResponses();
                    $message = '';
                    while (true)
                    {
                        $LEM->currentQset = array();    // reset active list of questions
                        if (--$LEM->currentQuestionSeq < 0) // Stop at start : can be a question
                        {
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,false);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'at_start'=>true,
                                'finished'=>false,
                                'message'=>$message,
                                'unansweredSQs'=>(isset($result['unansweredSQs']) ? $result['unansweredSQs'] : ''),
                                'invalidSQs'=>(isset($result['invalidSQs']) ? $result['invalidSQs'] : ''),
                            );
                            return $LEM->lastMoveResult;
                        }

                        // Set certain variables normally set by StartProcessingGroup()
                        $LEM->groupRelevanceInfo=array();   // TODO only important thing from StartProcessingGroup?
                        $qInfo = $LEM->questionSeq2relevance[$LEM->currentQuestionSeq];
                        $LEM->currentQID=$qInfo['qid'];
                        $LEM->currentGroupSeq=$qInfo['gseq'];
                        if ($LEM->currentGroupSeq > $LEM->maxGroupSeq)// Did we need it ?
                            $LEM->maxGroupSeq = $LEM->currentGroupSeq;

                        $LEM->ProcessAllNeededRelevance($LEM->currentQuestionSeq);
                        $LEM->_CreateSubQLevelRelevanceAndValidationEqns($LEM->currentQuestionSeq);
                        $result = $LEM->_ValidateQuestion($LEM->currentQuestionSeq);
                        $message .= $result['message'];
                        $gRelInfo = $LEM->gRelInfo[$LEM->currentGroupSeq];
                        $grel = $gRelInfo['result'];

                        if (!$grel || !$result['relevant'] || $result['hidden'])
                        {
                            // then skip this question - assume already saved?
                            continue;
                        }
                        else
                        {
                            // display new question : Ging backward : maxQuestionSeq>currentQuestionSeq is always true.
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,false);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            return array(
                                'at_start'=>false,
                                'finished'=>false,
                                'message'=>$message,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentQuestionSeq,
                                'qseq'=>$LEM->currentQuestionSeq,
                                'mandViolation'=> $result['mandViolation'],
                                'valid'=> $result['valid'],
                                'unansweredSQs'=>$result['unansweredSQs'],
                                'invalidSQs'=>$result['invalidSQs'],
                            );
                        }
                    }
                    break;
            }
        }

        /**
        *
        * @param boolean $force - if true, continue to go forward even if there are violations to the mandatory and/or validity rules
        * @return array|null - lastMoveResult
        */
        static function NavigateForwards($force=false) {
            $now = microtime(true);
            $LEM =& LimeExpressionManager::singleton();

            $LEM->ParseResultCache=array();    // to avoid running same test more than once for a given group
            $LEM->updatedValues = array();

            switch ($LEM->surveyMode)
            {
                case 'survey':
                    $startingGroup = $LEM->currentGroupSeq;
                    $LEM->StartProcessingPage(true);
                    $updatedValues=$LEM->ProcessCurrentResponses();
                    $message = '';
                    $LEM->currentQset = array();    // reset active list of questions
                    $result = $LEM->_ValidateSurvey();
                    $message .= $result['message'];
                    $updatedValues = array_merge($updatedValues,$result['updatedValues']);
                    if (!$force && !is_null($result) && ($result['mandViolation'] || !$result['valid'] || $startingGroup == -1))
                    {
                        $finished=false;
                    }
                    else
                    {
                        $finished = true;
                    }
                    $message .= $LEM->_UpdateValuesInDatabase($updatedValues,$finished);
                    $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                    $LEM->lastMoveResult = array(
                        'finished'=>$finished,
                        'message'=>$message,
                        'gseq'=>1,
                        'seq'=>1,
                        'mandViolation'=>$result['mandViolation'],
                        'valid'=>$result['valid'],
                        'unansweredSQs'=>$result['unansweredSQs'],
                        'invalidSQs'=>$result['invalidSQs'],
                    );
                    return $LEM->lastMoveResult;
                    // NB: No break needed
                case 'group':
                    // First validate the current group
                    $LEM->StartProcessingPage();
                    $updatedValues=$LEM->ProcessCurrentResponses();
                    $message = '';
                    if (!$force && $LEM->currentGroupSeq != -1)
                    {
                        $result = $LEM->_ValidateGroup($LEM->currentGroupSeq);
                        $message .= $result['message'];
                        $updatedValues = array_merge($updatedValues,$result['updatedValues']);
                        if (!is_null($result) && ($result['mandViolation'] || !$result['valid']))
                        {
                            // redisplay the current group
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,false);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'finished'=>false,
                                'message'=>$message,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentGroupSeq,
                                'mandViolation'=>$result['mandViolation'],
                                'valid'=>$result['valid'],
                                'unansweredSQs'=>$result['unansweredSQs'],
                                'invalidSQs'=>$result['invalidSQs'],
                            );
                            return $LEM->lastMoveResult;
                        }
                    }
                    while (true)
                    {
                        $LEM->currentQset = array();    // reset active list of questions
                        if (++$LEM->currentGroupSeq >= $LEM->numGroups)
                        {
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,true);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'finished'=>true,
                                'message'=>$message,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentGroupSeq,
                                'mandViolation'=>(isset($result['mandViolation']) ? $result['mandViolation'] : false),
                                'valid'=>(isset($result['valid']) ? $result['valid'] : false), // Why return invalid if it's not set ?
                                'unansweredSQs'=>(isset($result['unansweredSQs']) ? $result['unansweredSQs'] : ''),
                                'invalidSQs'=>(isset($result['invalidSQs']) ? $result['invalidSQs'] : ''),
                            );
                            return $LEM->lastMoveResult;
                        }

                        $result = $LEM->_ValidateGroup($LEM->currentGroupSeq);
                        if (is_null($result)) {
                            continue;   // this is an invalid group - skip it
                        }
                        $message .= $result['message'];
                        $updatedValues = array_merge($updatedValues,$result['updatedValues']);
                        if (!$result['relevant'] || $result['hidden'])
                        {
                            // then skip this group
                            continue;
                        }
                        else
                        {
                            // display new group
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,false);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'finished'=>false,
                                'message'=>$message,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentGroupSeq,
                                'mandViolation'=> (($LEM->maxGroupSeq > $LEM->currentGroupSeq) ? $result['mandViolation'] : false),
                                'valid'=> (($LEM->maxGroupSeq > $LEM->currentGroupSeq) ? $result['valid'] : false),
                                'unansweredSQs'=>$result['unansweredSQs'],
                                'invalidSQs'=>$result['invalidSQs'],
                            );
                            return $LEM->lastMoveResult;
                        }
                    }
                    break;
                case 'question':
                    $LEM->StartProcessingPage();
                    $updatedValues=$LEM->ProcessCurrentResponses();
                    $message = '';
                    $result = array();
                    if (!$force && $LEM->currentQuestionSeq != -1)
                    {
                        $result = $LEM->_ValidateQuestion($LEM->currentQuestionSeq);
                        $message .= $result['message'];
                        $updatedValues = array_merge($updatedValues,$result['updatedValues']);
                        $gRelInfo = $LEM->gRelInfo[$LEM->currentGroupSeq];
                        $grel = $gRelInfo['result'];
                        if ($grel && !is_null($result) && ($result['mandViolation'] || !$result['valid']))
                        {
                            // redisplay the current question with all error
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,false);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'finished'=>false,
                                'message'=>$message,
                                'qseq'=>$LEM->currentQuestionSeq,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentQuestionSeq,
                                'mandViolation'=> $result['mandViolation'],
                                'valid'=> $result['valid'],
                                'unansweredSQs'=>$result['unansweredSQs'],
                                'invalidSQs'=>$result['invalidSQs'],
                            );
                            return $LEM->lastMoveResult;
                        }
                    }
                    while (true)
                    {
                        $LEM->currentQset = array();    // reset active list of questions
                        if (++$LEM->currentQuestionSeq >= $LEM->numQuestions) // Move next with finished, but without submit.
                        {
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,true);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'finished'=>true,
                                'message'=>$message,
                                'qseq'=>$LEM->currentQuestionSeq,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentQuestionSeq,
                                'mandViolation'=> (($LEM->maxQuestionSeq > $LEM->currentQuestionSeq) ? $result['mandViolation'] : false),
                                'valid'=> (($LEM->maxQuestionSeq > $LEM->currentQuestionSeq) ? $result['valid'] : true),
                                'unansweredSQs'=>(isset($result['unansweredSQs']) ? $result['unansweredSQs'] : ''),
                                'invalidSQs'=>(isset($result['invalidSQs']) ? $result['invalidSQs'] : ''),
                            );
                            return $LEM->lastMoveResult;
                        }

                        // Set certain variables normally set by StartProcessingGroup()
                        $LEM->groupRelevanceInfo=array();   // TODO only important thing from StartProcessingGroup?
                        $qInfo = $LEM->questionSeq2relevance[$LEM->currentQuestionSeq];
                        $LEM->currentQID=$qInfo['qid'];
                        $LEM->currentGroupSeq=$qInfo['gseq'];
                        if ($LEM->currentGroupSeq > $LEM->maxGroupSeq) {
                            $LEM->maxGroupSeq = $LEM->currentGroupSeq;
                        }

                        $LEM->ProcessAllNeededRelevance($LEM->currentQuestionSeq);
                        $LEM->_CreateSubQLevelRelevanceAndValidationEqns($LEM->currentQuestionSeq);
                        $result = $LEM->_ValidateQuestion($LEM->currentQuestionSeq);
                        $message .= $result['message'];
                        $updatedValues = array_merge($updatedValues,$result['updatedValues']);
                        $gRelInfo = $LEM->gRelInfo[$LEM->currentGroupSeq];
                        $grel = $gRelInfo['result'];

                        if (!$grel || !$result['relevant'] || $result['hidden'])
                        {
                            // then skip this question, $LEM->updatedValues updated in _ValidateQuestion
                            continue;
                        }
                        else
                        {
                            // Display new question
                            // Show error only if this question are not viewed before (question hidden by condition before <= maxQuestionSeq>currentQuestionSeq)
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,false);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'finished'=>false,
                                'message'=>$message,
                                'qseq'=>$LEM->currentQuestionSeq,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentQuestionSeq,
                                'mandViolation'=> (($LEM->maxQuestionSeq > $LEM->currentQuestionSeq) ? $result['mandViolation'] : false),
                                'valid'=> (($LEM->maxQuestionSeq > $LEM->currentQuestionSeq) ? $result['valid'] : false),
                                'unansweredSQs'=>$result['unansweredSQs'],
                                'invalidSQs'=>$result['invalidSQs'],
                            );
                            return $LEM->lastMoveResult;
                        }
                    }
                    break;
            }
        }

        /**
        * Write values to database.
        * @param array|null $updatedValues
        * @param boolean $finished - true if the survey needs to be finalized
        * @return string
        */
        private function _UpdateValuesInDatabase($updatedValues, $finished=false)
        {
            //  TODO - now that using $this->updatedValues, may be able to remove local copies of it (unless needed by other sub-systems)
            $updatedValues = $this->updatedValues;
            $message = '';
            if (!$this->surveyOptions['active'] || $this->sPreviewMode)
            {
                return $message;
            }

            if (!isset($_SESSION[$this->sessid]['srid']))// Create the response line, and fill Session with primaryKey
            {
                $_SESSION[$this->sessid]['datestamp']=dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", $this->surveyOptions['timeadjust']);
                // Create initial insert row for this record
                $sdata = array(
                "startlanguage"=>$this->surveyOptions['startlanguage']
                );
                if ($this->surveyOptions['anonymized'] == false)
                {
                    $sdata['token'] = $this->surveyOptions['token'];
                }
                if ($this->surveyOptions['datestamp'] == true)
                {
                    $sdata['datestamp'] = $_SESSION[$this->sessid]['datestamp'];
                    $sdata['startdate'] = $_SESSION[$this->sessid]['datestamp'];
                }
                if ($this->surveyOptions['ipaddr'] == true)
                {
                    $sdata['ipaddr'] = getIPAddress();
                }
                if ($this->surveyOptions['refurl'] == true)
                {
                    if (isset($_SESSION[$this->sessid]['refurl']))
                    {
                        $sdata['refurl'] = $_SESSION[$this->sessid]['refurl'];
                    }
                    else
                    {
                        $sdata['refurl'] = getenv("HTTP_REFERER");
                    }
                 }

                $sdata = array_filter($sdata);
                SurveyDynamic::sid($this->sid);
                $oSurvey = new SurveyDynamic;

                $iNewID = $oSurvey->insertRecords($sdata);
                if ($iNewID)    // Checked
                {
                    $srid = $iNewID;
                    $_SESSION[$this->sessid]['srid'] = $iNewID;
                }
                else
                {
                    $srid = null;
                    $message .= $this->gT("Unable to insert record into survey table"); // TODO - add SQL error?
                    echo submitfailed('');  // TODO - report SQL error?
                }
                //Insert Row for Timings, if needed
                if ($this->surveyOptions['savetimings']) {
                    SurveyTimingDynamic::sid($this->sid);
                    $oSurveyTimings = new SurveyTimingDynamic;

                    $tdata = array(
                    'id'=>$srid,
                    'interviewtime'=>0
                    );
                    switchMSSQLIdentityInsert("survey_{$this->sid}_timings", true);
                    $oSurveyTimings->insertRecords($tdata);
                    switchMSSQLIdentityInsert("survey_{$this->sid}_timings", false);
                }
            }
            if (count($updatedValues) > 0 || $finished)
            {
                $query = 'UPDATE ' . $this->surveyOptions['tablename'] . ' SET ';
                $setter = array();
                switch ($this->surveyMode)
                {
                    case 'question':
                        $thisstep = $this->currentQuestionSeq;
                        break;
                    case 'group':
                        $thisstep = $this->currentGroupSeq;
                        break;
                    case 'survey':
                        $thisstep = 1;
                        break;
                    default:
                        // TODO: Internal error if this happens
                        $thisstep = 0;
                        break;
                }
                $setter[] = dbQuoteID('lastpage') . "=" . dbQuoteAll($thisstep);

                if ($this->surveyOptions['datestamp'] && isset($_SESSION[$this->sessid]['datestamp'])) {
                    $_SESSION[$this->sessid]['datestamp']=dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", $this->surveyOptions['timeadjust']);
                    $setter[] = dbQuoteID('datestamp') . "=" . dbQuoteAll(dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", $this->surveyOptions['timeadjust']));
                }
                if ($this->surveyOptions['ipaddr']) {
                    $setter[] = dbQuoteID('ipaddr') . "=" . dbQuoteAll(getIPAddress());
                }

                foreach ($updatedValues as $key=>$value)
                {
                    $val = (is_null($value) ? NULL : $value['value']);
                    $type = (is_null($value) ? NULL : $value['type']);
                    // Clean up the values to cope with database storage requirements : some value are fitered in ProcessCurrentResponses
                    // @todo fix whole type according to DB : use Yii for this ?
                    switch($type)
                    {
                        case 'D': //DATE
                            if (trim($val)=='' || $val=="INVALID")// otherwise will already be in yyyy-mm-dd format after ProcessCurrentResponses() (not for default value, GET value, Expression set value etc ... cf todo
                            {
                                $val=NULL;  // since some databases can't store blanks in date fields
                            }
                            break;
                        case 'N': //NUMERICAL QUESTION TYPE
                        case 'K': //MULTIPLE NUMERICAL QUESTION
                            if (trim($val)=='' || !is_numeric($val)) // is_numeric error is done by EM : then show an error and same page again
                            {
                                $val=NULL;  // since some databases can't store blanks in numerical inputs
                            }
                            elseif(!preg_match("/^[-]?(\d{1,20}\.\d{0,10}|\d{1,20})$/",$val)) // DECIMAL(30,10)
                            {
                                // Here : we must ADD a message for the user and set the question "not valid" : show the same page + show with input-error class
                                $val=NULL;
                            }
                            break;
                        default:
                            // @todo : control length of DB string, if answers in single choice is valid too (for example) ?
                            break;
                    }
                    if (is_null($val))
                    {
                        $setter[] = dbQuoteID($key) . "=NULL";
                    }
                    else
                    {
                        $setter[] = dbQuoteID($key) . "=" . dbQuoteAll(stripCtrlChars($val));
                    }
                }
                $query .= implode(', ', $setter);
                $query .= " WHERE ID=";

                if (isset($_SESSION[$this->sessid]['srid']) && $this->surveyOptions['active'])
                {
                    $query .= $_SESSION[$this->sessid]['srid'];

                    if (!dbExecuteAssoc($query))
                    {
                        // TODO: This kills the session if adminemail is defined, so the queries below won't work.
                        $message = submitfailed('', $query);  // TODO - report SQL error?

                        if (($this->debugLevel & LEM_DEBUG_VALIDATION_SUMMARY) == LEM_DEBUG_VALIDATION_SUMMARY) {
                            $message .= $this->gT('Error in SQL update');  // TODO - add  SQL error?
                        }

                        LimeExpressionManager::addFrontendFlashMessage('error', $message, $this->sid);

                    }
                    // Save Timings if needed
                    elseif ($this->surveyOptions['savetimings']) {
                        Yii::import("application.libraries.Save");
                        $cSave = new Save();
                        $cSave->set_answer_time();
                    }

                    if ($finished)
                    {
                        // Delete the save control record if successfully finalize the submission
                        $query = "DELETE FROM {{saved_control}} where srid=".$_SESSION[$this->sessid]['srid'].' and sid='.$this->sid;
                        Yii::app()->db->createCommand($query)->execute();

                        if (($this->debugLevel & LEM_DEBUG_VALIDATION_SUMMARY) == LEM_DEBUG_VALIDATION_SUMMARY) {
                            $message .= ';<br />'.$query;
                        }

                    }
                    else if ($this->surveyOptions['allowsave'] && isset($_SESSION[$this->sessid]['scid']))
                    {
                        SavedControl::model()->updateByPk($_SESSION[$this->sessid]['scid'], array('saved_thisstep'=>$thisstep));
                    }
                    // Check Quotas
                    $aQuotas = checkCompletedQuota($this->sid,'return');
                    if ($aQuotas && !empty($aQuotas))
                    {
                        checkCompletedQuota($this->sid);  // will create a page and quit: why not use it directly ?
                    }
                    else
                    {
                        if ($finished) {
                            $sQuery = 'UPDATE '.$this->surveyOptions['tablename'] . " SET ";
                            if($this->surveyOptions['datestamp'])
                            {
                                // Replace with date("Y-m-d H:i:s") ? See timeadjust
                                $sQuery .= dbQuoteID('submitdate') . "=" . dbQuoteAll(dateShift(date("Y-m-d H:i:s"), "Y-m-d H:i:s", $this->surveyOptions['timeadjust']));
                            }
                            else
                            {
                                $sQuery .= dbQuoteID('submitdate') . "=" . dbQuoteAll(date("Y-m-d H:i:s",mktime(0,0,0,1,1,1980)));
                            }
                            $sQuery .= " WHERE ID=".$_SESSION[$this->sessid]['srid'];
                            dbExecuteAssoc($sQuery);   // Checked
                        }
                    }

                }
                if (($this->debugLevel & LEM_DEBUG_VALIDATION_SUMMARY) == LEM_DEBUG_VALIDATION_SUMMARY) {
                    $message .= $query;
                }
            }
            return $message;
        }

        /**
        * Get last move information, optionally clearing the substitution cache
        * @param boolean $clearSubstitutionInfo
        * @return array|null
        */
        static function GetLastMoveResult($clearSubstitutionInfo=false)
        {
            $LEM =& LimeExpressionManager::singleton();
            if ($clearSubstitutionInfo)
            {
                $LEM->em->ClearSubstitutionInfo();  // need to avoid double-generation of tailoring info
            }
            return (isset($LEM->lastMoveResult) ? $LEM->lastMoveResult : NULL);
        }

        /**
        * Jump to a specific question or group sequence.  If jumping forward, it re-validates everything in between
        * @param int $seq
        * @param boolean $preview - if true, then treat this group/question as relevant, even if it is not, so that it can be displayed
        * @param boolean $processPOST
        * @param boolean $force - if true, then skip validation of current group (e.g. will jump even if there are errors)
        * @param boolean $changeLang
        * @return array
        */
        static function JumpTo($seq,$preview=false,$processPOST=true,$force=false,$changeLang=false) {
            $now = microtime(true);
            $LEM =& LimeExpressionManager::singleton();

            if(!$preview)
                $preview=$LEM->sPreviewMode;
            if(!$LEM->sPreviewMode && $preview)
                $LEM->sPreviewMode=$preview;

            if ($changeLang)
            {
                $LEM->setVariableAndTokenMappingsForExpressionManager($LEM->sid,true,$LEM->surveyOptions['anonymized'],$LEM->allOnOnePage);
            }

            $LEM->ParseResultCache=array();    // to avoid running same test more than once for a given group
            $LEM->updatedValues = array();
            --$seq; // convert to 0-based numbering

            switch ($LEM->surveyMode)
            {
                case 'survey':
                    // This only happens if saving data so far, so don't want to submit it, just validate and return
                    $LEM->StartProcessingPage(true);
                    if ($processPOST) {
                        $updatedValues=$LEM->ProcessCurrentResponses();
                    }
                    else  {
                        $updatedValues = array();
                    }
                    $message = '';

                    $LEM->currentQset = array();    // reset active list of questions
                    $result = $LEM->_ValidateSurvey($force);
                    $message .= $result['message'];
                    $updatedValues = array_merge($updatedValues,$result['updatedValues']);
                    $finished=false;
                    $message .= $LEM->_UpdateValuesInDatabase($updatedValues,$finished);// This happen too for $processPOST=false : need to fix it ?
                    $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                    $LEM->lastMoveResult = array(
                        'finished'=>$finished,
                        'message'=>$message,
                        'gseq'=>1,
                        'seq'=>1,
                        'mandViolation'=>$result['mandViolation'],
                        'valid'=>$result['valid'],
                        'unansweredSQs'=>$result['unansweredSQs'],
                        'invalidSQs'=>$result['invalidSQs'],
                    );
                    return $LEM->lastMoveResult;
                    // NB: No break needed
                case 'group':
                    // First validate the current group
                    $LEM->StartProcessingPage();
                    if ($processPOST)
                        $updatedValues=$LEM->ProcessCurrentResponses();
                    else
                        $updatedValues = array();
                    $message = '';
                    if (!$force && $LEM->currentGroupSeq != -1 && $seq > $LEM->currentGroupSeq) // only re-validate if jumping forward
                    {
                        $result = $LEM->_ValidateGroup($LEM->currentGroupSeq);
                        $message .= $result['message'];
                        $updatedValues = array_merge($updatedValues,$result['updatedValues']);
                        if (!is_null($result) && ($result['mandViolation'] || !$result['valid']))
                        {
                            // redisplay the current group, showing error
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,false);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'finished'=>false,
                                'message'=>$message,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentGroupSeq,
                                'mandViolation'=>$result['mandViolation'],
                                'valid'=>$result['valid'],
                                'unansweredSQs'=>$result['unansweredSQs'],
                                'invalidSQs'=>$result['invalidSQs'],
                            );
                            return $LEM->lastMoveResult;
                        }
                    }
                    if ($seq <= $LEM->currentGroupSeq || $preview) {
                        $LEM->currentGroupSeq = $seq-1; // Try to jump to the requested group, but navigate to next if needed
                    }
                    while (true)
                    {
                        $LEM->currentQset = array();    // reset active list of questions
                        if (++$LEM->currentGroupSeq >= $LEM->numGroups)
                        {
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,true);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'finished'=>true,
                                'message'=>$message,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentGroupSeq,
                                'mandViolation'=>(isset($result['mandViolation']) ? $result['mandViolation'] : false),
                                'valid'=>(isset($result['valid']) ? $result['valid'] : false),
                                'unansweredSQs'=>(isset($result['unansweredSQs']) ? $result['unansweredSQs'] : ''),
                                'invalidSQs'=>(isset($result['invalidSQs']) ? $result['invalidSQs'] : ''),
                            );
                            return $LEM->lastMoveResult;
                        }

                        $result = $LEM->_ValidateGroup($LEM->currentGroupSeq,$force);
                        if (is_null($result)) {
                            return NULL;    // invalid group - either bad number, or no questions within it
                        }
                        $message .= $result['message'];
                        $updatedValues = array_merge($updatedValues,$result['updatedValues']);
                        if (!$preview && (!$result['relevant'] || $result['hidden']))
                        {
                            // then skip this group - assume already saved?
                            continue;
                        }
                        elseif (!($result['mandViolation'] || !$result['valid']) && $LEM->currentGroupSeq < $seq)
                        {
                            // if there is a violation while moving forward, need to stop and ask that set of questions
                            // if there are no violations, can skip this group as long as changed values are saved.
                            continue;
                        }
                        else
                        {
                            // display new group
                            if(!$preview){ // Save only if not in preview mode
                                $message .= $LEM->_UpdateValuesInDatabase($updatedValues,false);
                                $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            }
                            $LEM->lastMoveResult = array(
                                'finished'=>false,
                                'message'=>$message,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentGroupSeq,
                                'mandViolation'=> (($LEM->maxGroupSeq > $LEM->currentGroupSeq) ? $result['mandViolation'] : false),
                                'valid'=> (($LEM->maxGroupSeq > $LEM->currentGroupSeq) ? $result['valid'] : false),
                                'unansweredSQs'=>$result['unansweredSQs'],
                                'invalidSQs'=>$result['invalidSQs'],
                            );
                            return $LEM->lastMoveResult;
                        }
                    }
                    break;
                case 'question':
                    $LEM->StartProcessingPage();
                    if ($processPOST)
                        $updatedValues=$LEM->ProcessCurrentResponses();
                    else
                        $updatedValues = array();
                    $message = '';
                    if (!$force && $LEM->currentQuestionSeq != -1 && $seq > $LEM->currentQuestionSeq)
                    {
                        $result = $LEM->_ValidateQuestion($LEM->currentQuestionSeq,$force);
                        $message .= $result['message'];
                        $updatedValues = array_merge($updatedValues,$result['updatedValues']);
                        $gRelInfo = $LEM->gRelInfo[$LEM->currentGroupSeq];
                        $grel = $gRelInfo['result'];
                        if ($grel && ($result['mandViolation'] || !$result['valid']))
                        {
                            // Redisplay the current question, qhowning error
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,false);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'finished'=>false,
                                'message'=>$message,
                                'qseq'=>$LEM->currentQuestionSeq,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentQuestionSeq,
                                'mandViolation'=> (($LEM->maxQuestionSeq > $LEM->currentQuestionSeq) ? $result['mandViolation'] : false),
                                'valid'=> (($LEM->maxQuestionSeq > $LEM->currentQuestionSeq) ? $result['valid'] : true),
                                'unansweredSQs'=>$result['unansweredSQs'],
                                'invalidSQs'=>$result['invalidSQs'],
                            );
                            return $LEM->lastMoveResult;
                        }
                    }
                    if ($seq <= $LEM->currentQuestionSeq || $preview) {
                        $LEM->currentQuestionSeq = $seq-1; // Try to jump to the requested group, but navigate to next if needed
                    }
                    while (true)
                    {
                        $LEM->currentQset = array();    // reset active list of questions
                        if (++$LEM->currentQuestionSeq >= $LEM->numQuestions)
                        {
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,true);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'finished'=>true,
                                'message'=>$message,
                                'qseq'=>$LEM->currentQuestionSeq,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentQuestionSeq,
                                'mandViolation'=> (isset($result['mandViolation']) ? $result['mandViolation'] : false),
                                'valid'=> (isset($result['valid']) ? $result['valid'] : false),
                                'unansweredSQs'=>(isset($result['unansweredSQs']) ? $result['unansweredSQs'] : ''),
                                'invalidSQs'=>(isset($result['invalidSQs']) ? $result['invalidSQs'] : ''),
                            );
                            return $LEM->lastMoveResult;
                        }

                        // Set certain variables normally set by StartProcessingGroup()
                        $LEM->groupRelevanceInfo=array();   // TODO only important thing from StartProcessingGroup?
                        if (!isset($LEM->questionSeq2relevance[$LEM->currentQuestionSeq])) {
                            return NULL;    // means an invalid question - probably no sub-quetions
                        }
                        $qInfo = $LEM->questionSeq2relevance[$LEM->currentQuestionSeq];
                        $LEM->currentQID=$qInfo['qid'];
                        $LEM->currentGroupSeq=$qInfo['gseq'];
                        if ($LEM->currentGroupSeq > $LEM->maxGroupSeq) {
                            $LEM->maxGroupSeq = $LEM->currentGroupSeq;
                        }

                        $LEM->ProcessAllNeededRelevance($LEM->currentQuestionSeq);
                        $LEM->_CreateSubQLevelRelevanceAndValidationEqns($LEM->currentQuestionSeq);
                        $result = $LEM->_ValidateQuestion($LEM->currentQuestionSeq,$force);
                        $message .= $result['message'];
                        $updatedValues = array_merge($updatedValues,$result['updatedValues']);
                        $gRelInfo = $LEM->gRelInfo[$LEM->currentGroupSeq];
                        $grel = $gRelInfo['result'];

                        if (!$preview && (!$grel || !$result['relevant'] || $result['hidden']))
                        {
                            // then skip this question
                            continue;
                        }
                        elseif (!$preview && !($result['mandViolation'] || !$result['valid']) && $LEM->currentQuestionSeq < $seq)
                        {
                            // if there is a violation while moving forward, need to stop and ask that set of questions
                            // if there are no violations, can skip this group as long as changed values are saved.
                            continue;
                        }
                        else
                        {
                            // Display new question
                            // Showing error if question are before the maxstep
                            $message .= $LEM->_UpdateValuesInDatabase($updatedValues,false);
                            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));
                            $LEM->lastMoveResult = array(
                                'finished'=>false,
                                'message'=>$message,
                                'qseq'=>$LEM->currentQuestionSeq,
                                'gseq'=>$LEM->currentGroupSeq,
                                'seq'=>$LEM->currentQuestionSeq,
                                'mandViolation'=> (($LEM->maxQuestionSeq > $LEM->currentQuestionSeq) ? $result['mandViolation'] : false),
                                'valid'=> (($LEM->maxQuestionSeq > $LEM->currentQuestionSeq) ? $result['valid'] : true),
                                'unansweredSQs'=>$result['unansweredSQs'],
                                'invalidSQs'=>$result['invalidSQs'],
                            );
                            return $LEM->lastMoveResult;
                        }
                    }
                    break;
            }
        }

        /**
        * Check the entire survey
        * @param boolean $force : force validation to true, even if there are error, used at survey start to fill EM
        * @return array with information on validated question
        */
        private function _ValidateSurvey($force=false)
        {
            $LEM =& $this;

            $message = '';
            $srel=false;
            $shidden=true;
            $smandViolation=false;
            $svalid=true;
            $unansweredSQs = array();
            $invalidSQs = array();
            $updatedValues = array();
            $sanyUnanswered = false;

            ///////////////////////////////////////////////////////
            // CHECK EACH GROUP, AND SET SURVEY-LEVEL PROPERTIES //
            ///////////////////////////////////////////////////////
            for ($i=0;$i<$LEM->numGroups;++$i) {
                $LEM->currentGroupSeq=$i;
                $gStatus = $LEM->_ValidateGroup($i,$force);
                if (is_null($gStatus)) {
                    continue;   // invalid group, so skip it
                }
                $message .= $gStatus['message'];

                if ($gStatus['relevant']) {
                    $srel = true;
                }
                if ($gStatus['relevant'] && !$gStatus['hidden']) {
                    $shidden=false;
                }
                if ($gStatus['relevant'] && !$gStatus['hidden'] && $gStatus['mandViolation']) {
                    $smandViolation = true;
                }
                if ($gStatus['relevant'] && !$gStatus['hidden'] && !$gStatus['valid']) {
                    $svalid=false;
                }
                if ($gStatus['anyUnanswered']) {
                    $sanyUnanswered = true;
                }

                if (strlen($gStatus['unansweredSQs']) > 0) {
                    $unansweredSQs = array_merge($unansweredSQs, explode('|',$gStatus['unansweredSQs']));
                }
                if (strlen($gStatus['invalidSQs']) > 0) {
                    $invalidSQs = array_merge($invalidSQs, explode('|',$gStatus['invalidSQs']));
                }
                $updatedValues = array_merge($updatedValues, $gStatus['updatedValues']);
                // array_merge destroys the key, so do it manually
                foreach ($gStatus['qset'] as $key=>$value) {
                    $LEM->currentQset[$key] = $value;
                }

                $LEM->FinishProcessingGroup();
            }
            return array(
            'relevant' => $srel,
            'hidden' => $shidden,
            'mandViolation' => $smandViolation,
            'valid' => $svalid,
            'anyUnanswered' => $sanyUnanswered,
            'message' => $message,
            'unansweredSQs' => implode('|',$unansweredSQs),
            'invalidSQs' => implode('|',$invalidSQs),
            'updatedValues' => $updatedValues,
            'seq'=>1,
            );
        }

        /**
        * Check a group and all of the questions it contains
        * @param integer $groupSeq - the index-0 sequence number for this group
        * @param boolean $force : force validation to true, even if there are error
        * @return <array> - detailed information about this group
        */
        function _ValidateGroup($groupSeq,$force=false)
        {
            $LEM =& $this;

            if ($groupSeq < 0 || $groupSeq >= $LEM->numGroups) {
                return NULL;    // TODO - what is desired behavior?
            }
            $groupSeqInfo = (isset($LEM->groupSeqInfo[$groupSeq]) ? $LEM->groupSeqInfo[$groupSeq] : NULL);
            if (is_null($groupSeqInfo)) {
                // then there are no questions in this group
                return NULL;
            }
            $qInfo = $LEM->questionSeq2relevance[$groupSeqInfo['qstart']];
            $gseq = $qInfo['gseq'];
            $gid = $qInfo['gid'];
            $LEM->StartProcessingGroup($gseq, $LEM->surveyOptions['anonymized'], $LEM->sid); // analyze the data we have about this group

            $grel=false;  // assume irrelevant until find a relevant question
            $ghidden=true;   // assume hidden until find a non-hidden question.  If there are no relevant questions on this page, $ghidden will stay true
            $gmandViolation=false;  // assume that the group contains no manditory questions that have not been fully answered
            $gvalid=true;   // assume valid until discover otherwise
            $debug_message = '';
            $messages = array();
            $currentQset = array();
            $unansweredSQs = array();
            $invalidSQs = array();
            $updatedValues = array();
            $ganyUnanswered = false;

            $gRelInfo = $LEM->gRelInfo[$groupSeq];

            /////////////////////////////////////////////////////////
            // CHECK EACH QUESTION, AND SET GROUP-LEVEL PROPERTIES //
            /////////////////////////////////////////////////////////
            for ($i=$groupSeqInfo['qstart'];$i<=$groupSeqInfo['qend']; ++$i)
            {
                $qStatus = $LEM->_ValidateQuestion($i,$force);

                $updatedValues = array_merge($updatedValues,$qStatus['updatedValues']);

                if ($gRelInfo['result']==true && $qStatus['relevant']==true) {
                    $grel = $gRelInfo['result'];    // true;   // at least one question relevant
                }
                if ($qStatus['hidden']==false && $qStatus['relevant'] == true) {
                    $ghidden=false; // at least one question is visible
                }
                if ($qStatus['relevant']==true && $qStatus['hidden']==false && $qStatus['mandViolation']==true) {
                    $gmandViolation=true;   // at least one relevant question fails mandatory test
                }
                if ($qStatus['anyUnanswered']==true) {
                    $ganyUnanswered=true;
                }
                if ($qStatus['relevant']==true && $qStatus['hidden']==false && $qStatus['valid']==false) {
                    $gvalid=false;  // at least one question fails validity constraints
                }
                $currentQset[$qStatus['info']['qid']] = $qStatus;
                $messages[] = $qStatus['message'];
                if (strlen($qStatus['unansweredSQs']) > 0) {
                    $unansweredSQs[] = $qStatus['unansweredSQs'];
                }
                if (strlen($qStatus['invalidSQs']) > 0) {
                    $invalidSQs[] = $qStatus['invalidSQs'];
                }
            }
            $unansweredSQList = implode('|',$unansweredSQs);
            $invalidSQList = implode('|',$invalidSQs);

            /////////////////////////////////////////////////////////
            // OPTIONALLY DISPLAY (DETAILED) DEBUGGING INFORMATION //
            /////////////////////////////////////////////////////////
            if (($LEM->debugLevel & LEM_DEBUG_VALIDATION_SUMMARY) == LEM_DEBUG_VALIDATION_SUMMARY)
            {
                $editlink = Yii::app()->getController()->createUrl('admin/questiongroups/sa/view/surveyid/' . $LEM->sid . '/gid/' . $gid);
                $debug_message .= '<br />[G#' . $LEM->currentGroupSeq . ']'
                . '[' . $groupSeqInfo['qstart'] . '-' . $groupSeqInfo['qend'] . ']'
                . "[<a href='$editlink'>"
                .  'GID:' . $gid . "</a>]:  "
                . ($grel ? 'relevant ' : " <span style='color:red'>irrelevant</span> ")
                . (($gRelInfo['eqn'] != '') ? $gRelInfo['prettyprint'] : '')
                . (($ghidden && $grel) ? " <span style='color:red'>always-hidden</span> " : ' ')
                . ($gmandViolation ? " <span style='color:red'>(missing a relevant mandatory)</span> " : ' ')
                . ($gvalid ? '' : " <span style='color:red'>(fails at least one validation rule)</span> ")
                . "<br />\n"
                . implode('', $messages);

                if ($grel == true)
                {
                    if (!$gvalid)
                    {
                        if (($LEM->debugLevel & LEM_DEBUG_VALIDATION_DETAIL) == LEM_DEBUG_VALIDATION_DETAIL)
                        {
                            $debug_message .= "**At least one relevant question was invalid, so re-show this group<br />\n";
                            $debug_message .= "**Validity Violators: " . implode(', ', explode('|',$invalidSQList)) . "<br />\n";
                        }
                    }
                    if ($gmandViolation)
                    {
                        if (($LEM->debugLevel & LEM_DEBUG_VALIDATION_DETAIL) == LEM_DEBUG_VALIDATION_DETAIL)
                        {
                            $debug_message .= "**At least one relevant question was mandatory but unanswered, so re-show this group<br />\n";
                            $debug_message .= '**Mandatory Violators: ' . implode(', ', explode('|',$unansweredSQList)). "<br />\n";
                        }
                    }

                    if ($ghidden == true)
                    {
                        if (($LEM->debugLevel & LEM_DEBUG_VALIDATION_DETAIL) == LEM_DEBUG_VALIDATION_DETAIL)
                        {
                            $debug_message .= '** Page is relevant but hidden, so NULL irrelevant values and save relevant Equation results:</br>';
                        }
                    }
                }
                else
                {
                    if (($LEM->debugLevel & LEM_DEBUG_VALIDATION_DETAIL) == LEM_DEBUG_VALIDATION_DETAIL)
                    {
                        $debug_message .= '** Page is irrelevant, so NULL all questions in this group<br />';
                    }
                }
            }

            //////////////////////////////////////////////////////////////////////////
            // STORE METADATA NEEDED FOR SUBSEQUENT PROCESSING AND DISPLAY PURPOSES //
            //////////////////////////////////////////////////////////////////////////
            $currentGroupInfo = array(
            'gseq' => $groupSeq,
            'message' => $debug_message,
            'relevant' => $grel,
            'hidden' => $ghidden,
            'mandViolation' => $gmandViolation,
            'valid' => $gvalid,
            'qset' => $currentQset,
            'unansweredSQs' => $unansweredSQList,
            'anyUnanswered' => $ganyUnanswered,
            'invalidSQs' => $invalidSQList,
            'updatedValues' => $updatedValues,
            );

            ////////////////////////////////////////////////////////
            // STORE METADATA NEEDED TO GENERATE NAVIGATION INDEX //
            ////////////////////////////////////////////////////////
            $LEM->indexGseq[$groupSeq] = array(
            'gtext' => $LEM->gseq2info[$groupSeq]['description'],
            'gname' => $LEM->gseq2info[$groupSeq]['group_name'],
            'gid' => $LEM->gseq2info[$groupSeq]['gid'], // TODO how used if random?
            'anyUnanswered' => $ganyUnanswered,
            'anyErrors' => (($gmandViolation || !$gvalid) ? true : false),
            'valid' => $gvalid,
            'mandViolation' => $gmandViolation,
            'show' => (($grel && !$ghidden) ? true : false),
            );

            $LEM->gseq2relevanceStatus[$gseq] = $grel;

            return $currentGroupInfo;
        }



        /**
        * For the current set of questions (whether in survey, gtoup, or question-by-question mode), assesses the following:
        * (a) mandatory - if so, then all relevant sub-questions must be answered (e.g. pay attention to array_filter and array_filter_exclude)
        * (b) always-hidden
        * (c) relevance status - including sub-question-level relevance
        * (d) answered - if $_SESSION[$LEM->sessid][sgqa]=='' or NULL, then it is not answered
        * (e) validity - whether relevant questions pass their validity tests
        * @param integer $questionSeq - the 0-index sequence number for this question
        * @param boolean $force : force validation to true, even if there are error, this allow to save in DB even with error
        * @return <array> of information about this question and its sub-questions
        */

        function _ValidateQuestion($questionSeq,$force=false)
        {
            $LEM =& $this;
            $qInfo = $LEM->questionSeq2relevance[$questionSeq];   // this array is by group and question sequence
            // We try to validate this question, then update the maxQuestionSeq, TODO : validate if we can update the maxGroupSeq too.
            if($questionSeq > $LEM->maxQuestionSeq) {  // max() take a little time more (2/3)
                $LEM->maxQuestionSeq=$questionSeq;
            }
            $prettyPrintRelEqn='';    //  assume no relevance eqn by default
            $qid=$qInfo['qid'];
            $gid=$qInfo['gid'];
            $qhidden = $qInfo['hidden'];
            $debug_qmessage='';

            $gRelInfo = $LEM->gRelInfo[$qInfo['gseq']];
            $grel = $gRelInfo['result'];

            ///////////////////////////
            // IS QUESTION RELEVANT? //
            ///////////////////////////
            if (!isset($qInfo['relevance']) || $qInfo['relevance'] == '' )
            {
                $relevanceEqn = 1;
            }
            else
            {
                $relevanceEqn = $qInfo['relevance'];
            }
            // cache results
            $relevanceEqn = htmlspecialchars_decode($relevanceEqn,ENT_QUOTES);  // TODO is this needed?
            if (isset($LEM->ParseResultCache[$relevanceEqn]))
            {
                $qrel = $LEM->ParseResultCache[$relevanceEqn]['result'];
                if (($LEM->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX)
                {
                    $prettyPrintRelEqn = $LEM->ParseResultCache[$relevanceEqn]['prettyprint'];
                }
            }
            else
            {
                $qrel = $LEM->em->ProcessBooleanExpression($relevanceEqn,$qInfo['gseq'], $qInfo['qseq']);    // assumes safer to re-process relevance and not trust POST values
                $hasErrors = $LEM->em->HasErrors();
                if (($LEM->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX)
                {
                    $prettyPrintRelEqn = $LEM->em->GetPrettyPrintString();
                }
                $LEM->ParseResultCache[$relevanceEqn] = array(
                'result'=>$qrel,
                'prettyprint'=>$prettyPrintRelEqn,
                'hasErrors'=>$hasErrors,
                );
            }
            // Do NOT hide the questions if there is an error in the relevance equation
            if ($LEM->ParseResultCache[$relevanceEqn]['hasErrors'] == true)
            {
                $qrel=true;
            }
            //////////////////////////////////////
            // ARE ANY SUB-QUESTION IRRELEVANT? //
            //////////////////////////////////////
            // identify the relevant subquestions (array_filter and array_filter_exclude may make some irrelevant)
            $relevantSQs=array();
            $irrelevantSQs=array();
            $prettyPrintSQRelEqns=array();
            $prettyPrintSQRelEqn='';
            $prettyPrintValidTip='';
            $anyUnanswered = false;
            $sgqas = array();

            if (!$qrel)
            {
                // All sub-questions are irrelevant
                $irrelevantSQs = explode('|', $LEM->qid2code[$qid]);
            }
            else
            {
                // Check filter status to determine which subquestions are relevant
                if ($qInfo['type'] == 'X') {
                    $sgqas = array();   // Boilerplate questions can be ignored
                }
                else
                {
                    $sgqas = explode('|',$LEM->qid2code[$qid]);
                }
                foreach ($sgqas as $sgqa)
                {
                    // for each subq, see if it is part of an array_filter or array_filter_exclude
                    if (!isset($LEM->subQrelInfo[$qid]))
                    {
                        $relevantSQs[] = $sgqa;
                        continue;
                    }
                    $foundSQrelevance=false;
                    if($qInfo['type']=='R')
                    {
                        // Relevance of subquestion for ranking question depend of the count of relevance of answers.
                        $iCountRank=(isset($iCountRank) ? $iCountRank+1 : 1);
                        // Relevant count is : Total answers less Unrelevant answers. subQrelInfo give only array with relevance equation, not this without any relevance.
                        $iCountRelevant=isset($iCountRelevant) ? $iCountRelevant : count($sgqas)-count(array_filter($LEM->subQrelInfo[$qid],function($sqRankAnwsers){ return !$sqRankAnwsers['result']; }));
                        if($iCountRank >  $iCountRelevant)
                        {
                            $irrelevantSQs[] = $sgqa;
                        }
                        else
                        {
                            $relevantSQs[] = $sgqa;
                        }
                        // This just remove the last ranking : don't control validity of answers done: user can rank unrelevant answers .... See Bug #09774
                        continue;
                    }

                    foreach ($LEM->subQrelInfo[$qid] as $sq)
                    {
                        switch ($sq['qtype'])
                        {
                            case '1':   //Array (Flexible Labels) dual scale
                                if ($sgqa == ($sq['rowdivid'] . '#0') || $sgqa == ($sq['rowdivid'] . '#1')) {
                                    $foundSQrelevance=true;
                                    if (isset($LEM->ParseResultCache[$sq['eqn']]))
                                    {
                                        $sqrel = $LEM->ParseResultCache[$sq['eqn']]['result'];
                                        if (($LEM->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX)
                                        {
                                            $prettyPrintSQRelEqns[$sq['rowdivid']] = $LEM->ParseResultCache[$sq['eqn']]['prettyprint'];
                                        }
                                    }
                                    else
                                    {
                                        $stringToParse = htmlspecialchars_decode($sq['eqn'],ENT_QUOTES);  // TODO is this needed?
                                        $sqrel = $LEM->em->ProcessBooleanExpression($stringToParse,$qInfo['gseq'], $qInfo['qseq']);
                                        $hasErrors = $LEM->em->HasErrors();
                                        // make sure subquestions with errors in relevance equations are always shown and answers recorded  #7703
                                        if ($hasErrors)
                                        {
                                            $sqrel=true;
                                        }
                                        if (($LEM->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX)
                                        {
                                            $prettyPrintSQRelEqn = $LEM->em->GetPrettyPrintString();
                                            $prettyPrintSQRelEqns[$sq['rowdivid']] = $prettyPrintSQRelEqn;
                                        }
                                        $LEM->ParseResultCache[$sq['eqn']] = array(
                                        'result'=>$sqrel,
                                        'prettyprint'=>$prettyPrintSQRelEqn,
                                        'hasErrors'=>$hasErrors,
                                        );
                                    }
                                    if ($sqrel)
                                    {
                                        $relevantSQs[] = $sgqa;
                                        $_SESSION[$LEM->sessid]['relevanceStatus'][$sq['rowdivid']]=true;
                                    }
                                    else
                                    {
                                        $irrelevantSQs[] = $sgqa;
                                        $_SESSION[$LEM->sessid]['relevanceStatus'][$sq['rowdivid']]=false;
                                    }
                                }
                                break;
                            case ':': //ARRAY (Multi Flexi) 1 to 10
                            case ';': //ARRAY (Multi Flexi) Text
                                if (preg_match('/^' . $sq['rowdivid'] . '_/', $sgqa))
                                {
                                    $foundSQrelevance=true;
                                    if (isset($LEM->ParseResultCache[$sq['eqn']]))
                                    {
                                        $sqrel = $LEM->ParseResultCache[$sq['eqn']]['result'];
                                        if (($LEM->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX)
                                        {
                                            $prettyPrintSQRelEqns[$sq['rowdivid']] = $LEM->ParseResultCache[$sq['eqn']]['prettyprint'];
                                        }
                                    }
                                    else
                                    {
                                        $stringToParse = htmlspecialchars_decode($sq['eqn'],ENT_QUOTES);  // TODO is this needed?
                                        $sqrel = $LEM->em->ProcessBooleanExpression($stringToParse,$qInfo['gseq'], $qInfo['qseq']);
                                        $hasErrors = $LEM->em->HasErrors();
                                        // make sure subquestions with errors in relevance equations are always shown and answers recorded  #7703
                                        if ($hasErrors)
                                        {
                                            $sqrel=true;
                                        }
                                        if (($LEM->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX)
                                        {
                                            $prettyPrintSQRelEqn = $LEM->em->GetPrettyPrintString();
                                            $prettyPrintSQRelEqns[$sq['rowdivid']] = $prettyPrintSQRelEqn;
                                        }
                                        $LEM->ParseResultCache[$sq['eqn']] = array(
                                        'result'=>$sqrel,
                                        'prettyprint'=>$prettyPrintSQRelEqn,
                                        'hasErrors'=>$hasErrors,
                                        );
                                    }
                                    if ($sqrel)
                                    {
                                        $relevantSQs[] = $sgqa;
                                        $_SESSION[$LEM->sessid]['relevanceStatus'][$sq['rowdivid']]=true;
                                    }
                                    else
                                    {
                                        $irrelevantSQs[] = $sgqa;
                                        $_SESSION[$LEM->sessid]['relevanceStatus'][$sq['rowdivid']]=false;
                                    }
                                }
                            case 'A': //ARRAY (5 POINT CHOICE) radio-buttons
                            case 'B': //ARRAY (10 POINT CHOICE) radio-buttons
                            case 'C': //ARRAY (YES/UNCERTAIN/NO) radio-buttons
                            case 'E': //ARRAY (Increase/Same/Decrease) radio-buttons
                            case 'F': //ARRAY (Flexible) - Row Format
                            case 'M': //Multiple choice checkbox
                            case 'P': //Multiple choice with comments checkbox + text
                                // Note, for M and P, Mandatory should mean that at least one answer was picked - not that all were checked
                            case 'K': //MULTIPLE NUMERICAL QUESTION
                            case 'Q': //MULTIPLE SHORT TEXT
                                if ($sgqa == $sq['rowdivid'] || $sgqa == ($sq['rowdivid'] . 'comment'))     // to catch case 'P'
                                {
                                    $foundSQrelevance=true;
                                    if (isset($LEM->ParseResultCache[$sq['eqn']]))
                                    {
                                        $sqrel = $LEM->ParseResultCache[$sq['eqn']]['result'];
                                        if (($LEM->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX)
                                        {
                                            $prettyPrintSQRelEqns[$sq['rowdivid']] = $LEM->ParseResultCache[$sq['eqn']]['prettyprint'];
                                        }
                                    }
                                    else
                                    {
                                        $stringToParse = htmlspecialchars_decode($sq['eqn'],ENT_QUOTES);  // TODO is this needed?
                                        $sqrel = $LEM->em->ProcessBooleanExpression($stringToParse,$qInfo['gseq'], $qInfo['qseq']);
                                        $hasErrors = $LEM->em->HasErrors();
                                        // make sure subquestions with errors in relevance equations are always shown and answers recorded  #7703
                                        if ($hasErrors)
                                        {
                                            $sqrel=true;
                                        }
                                        if (($LEM->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX)
                                        {
                                            $prettyPrintSQRelEqn = $LEM->em->GetPrettyPrintString();
                                            $prettyPrintSQRelEqns[$sq['rowdivid']] = $prettyPrintSQRelEqn;
                                        }
                                        $LEM->ParseResultCache[$sq['eqn']] = array(
                                        'result'=>$sqrel,
                                        'prettyprint'=>$prettyPrintSQRelEqn,
                                        'hasErrors'=>$hasErrors,
                                        );
                                    }
                                    if ($sqrel)
                                    {
                                        $relevantSQs[] = $sgqa;
                                        $_SESSION[$LEM->sessid]['relevanceStatus'][$sq['rowdivid']]=true;
                                    }
                                    else
                                    {
                                        $irrelevantSQs[] = $sgqa;
                                        $_SESSION[$LEM->sessid]['relevanceStatus'][$sq['rowdivid']]=false;
                                    }
                                }
                                break;
                            case 'L': //LIST drop-down/radio-button list
                                if ($sgqa == ($sq['sgqa'] . 'other') && $sgqa == $sq['rowdivid'])   // don't do sub-q level validition to main question, just to other option
                                {
                                    $foundSQrelevance=true;
                                    if (isset($LEM->ParseResultCache[$sq['eqn']]))
                                    {
                                        $sqrel = $LEM->ParseResultCache[$sq['eqn']]['result'];
                                        if (($LEM->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX)
                                        {
                                            $prettyPrintSQRelEqns[$sq['rowdivid']] = $LEM->ParseResultCache[$sq['eqn']]['prettyprint'];
                                        }
                                    }
                                    else
                                    {
                                        $stringToParse = htmlspecialchars_decode($sq['eqn'],ENT_QUOTES);  // TODO is this needed?
                                        $sqrel = $LEM->em->ProcessBooleanExpression($stringToParse,$qInfo['gseq'], $qInfo['qseq']);
                                        $hasErrors = $LEM->em->HasErrors();
                                        // make sure subquestions with errors in relevance equations are always shown and answers recorded  #7703
                                        if ($hasErrors)
                                        {
                                            $sqrel=true;
                                        }
                                        if (($LEM->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX)
                                        {
                                            $prettyPrintSQRelEqn = $LEM->em->GetPrettyPrintString();
                                            $prettyPrintSQRelEqns[$sq['rowdivid']] = $prettyPrintSQRelEqn;
                                        }
                                        $LEM->ParseResultCache[$sq['eqn']] = array(
                                        'result'=>$sqrel,
                                        'prettyprint'=>$prettyPrintSQRelEqn,
                                        'hasErrors'=>$hasErrors,
                                        );
                                    }
                                    if ($sqrel)
                                    {
                                        $relevantSQs[] = $sgqa;
                                    }
                                    else
                                    {
                                        $irrelevantSQs[] = $sgqa;
                                    }
                                }
                                break;
                            default:
                                break;
                        }
                    }   // end foreach($LEM->subQrelInfo) [checking array-filters]
                    if (!$foundSQrelevance)
                    {
                        // then this question is relevant
                        $relevantSQs[] = $sgqa; // TODO - check this
                    }
                }
            } // end of processing relevant question for sub-questions
            if (($LEM->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX)
            {
                // TODO - why is array_unique needed here?
                //            $prettyPrintSQRelEqns = array_unique($prettyPrintSQRelEqns);
            }
            // These array_unique only apply to array_filter of type L (list)
            $relevantSQs = array_unique($relevantSQs);
            $irrelevantSQs = array_unique($irrelevantSQs);

            ////////////////////////////////////////////////////////////////////
            // WHICH RELEVANT, VISIBLE (SUB)-QUESTIONS HAVEN'T BEEN ANSWERED? //
            ////////////////////////////////////////////////////////////////////
            // check that all mandatories have been fully answered (but don't require answers for sub-questions that are irrelevant
            $unansweredSQs = array();   // list of sub-questions that weren't answered
            foreach ($relevantSQs as $sgqa)
            {
                if (($qInfo['type'] != '*') && (!isset($_SESSION[$LEM->sessid][$sgqa]) || ($_SESSION[$LEM->sessid][$sgqa] === '' || is_null($_SESSION[$LEM->sessid][$sgqa]))))
                {
                    // then a relevant, visible, mandatory question hasn't been answered
                    // Equations are ignored, since set automatically
                    $unansweredSQs[] = $sgqa;
                }
            }

            //////////////////////////////////////////////
            // DETECT ANY VIOLATIONS OF MANDATORY RULES //
            //////////////////////////////////////////////
            $qmandViolation = false;    // assume there is no mandatory violation until discover otherwise
            $mandatoryTip = '';
            if ($qrel && !$qhidden && ($qInfo['mandatory'] == 'Y'))
            {
                //$mandatoryTip = "<p class='errormandatory alert alert-danger' role='alert'><span class='glyphicon glyphicon-exclamation-sign'></span>&nbsp" . $LEM->gT('This question is mandatory') . "</p>";
                $mandatoryTip = Yii::app()->getController()->renderPartial('/survey/system/questionhelp/mandatory_tip', array(
                        'sMandatoryText'=>$LEM->gT('This question is mandatory'),
                ), true);
                switch ($qInfo['type'])
                {
                    case 'M':
                    case 'P':
                    case '!': //List - dropdown
                    case 'L': //LIST drop-down/radio-button list
                        // If at least one checkbox is checked, we're OK
                        if (count($relevantSQs) > 0 && (count($relevantSQs) == count($unansweredSQs)))
                        {
                            $qmandViolation = true;
                        }
                        if (!($qInfo['type'] == '!' || $qInfo['type'] == 'L'))
                        {
                            $sMandatoryText = $LEM->gT('Please check at least one item.');
                            $mandatoryTip .= Yii::app()->getController()->renderPartial('/survey/system/questionhelp/mandatory_tip', array(
                                    'sMandatoryText'=>$sMandatoryText,
                            ), true);
                        }
                        if ($qInfo['other']=='Y')
                        {
                            $qattr = isset($LEM->qattr[$qid]) ? $LEM->qattr[$qid] : array();
                            if (isset($qattr['other_replace_text']) && trim($qattr['other_replace_text']) != '') {
                                $othertext = trim($qattr['other_replace_text']);
                            }
                            else {
                                $othertext = $LEM->gT('Other:');
                            }
                            //$mandatoryTip .= "\n".sprintf($this->gT("If you choose '%s' please also specify your choice in the accompanying text field."),$othertext);
                            $sMandatoryText = "\n".sprintf($this->gT("If you choose '%s' please also specify your choice in the accompanying text field."),$othertext);
                            $mandatoryTip .= Yii::app()->getController()->renderPartial('/survey/system/questionhelp/mandatory_tip', array(
                                    'sMandatoryText'=>$sMandatoryText,
                            ), true);

                        }
                        break;
                    case 'X':   // Boilerplate can never be mandatory
                    case '*':   // Equation is auto-computed, so can't violate mandatory rules
                        break;
                    case 'A':
                    case 'B':
                    case 'C':
                    case 'Q':
                    case 'K':
                    case 'E':
                    case 'F':
                    case 'J':
                    case 'H':
                    case ';':
                    case '1':
                        // In general, if any relevant questions aren't answered, then it violates the mandatory rule
                        if (count($unansweredSQs) > 0)
                        {
                            $qmandViolation = true; // TODO - what about 'other'?
                        }
                        $sMandatoryText = $LEM->gT('Please complete all parts').'.';
                        $mandatoryTip .= Yii::app()->getController()->renderPartial('/survey/system/questionhelp/mandatory_tip', array(
                                'sMandatoryText'=>$sMandatoryText,
                        ), true);
                        break;
                    case ':':
                        $qattr = isset($LEM->qattr[$qid]) ? $LEM->qattr[$qid] : array();
                        if (isset($qattr['multiflexible_checkbox']) && $qattr['multiflexible_checkbox'] == 1)
                        {
                            // Need to check whether there is at least one checked box per row
                            foreach ($LEM->q2subqInfo[$qid]['subqs'] as $sq)
                            {
                                if (!isset($_SESSION[$LEM->sessid]['relevanceStatus'][$sq['rowdivid']]) || $_SESSION[$LEM->sessid]['relevanceStatus'][$sq['rowdivid']])
                                {
                                    $rowCount=0;
                                    $numUnanswered=0;
                                    foreach ($sgqas as $s)
                                    {
                                        if (strpos($s, $sq['rowdivid']."_") !== false) // Test complete subquestion code (#09493)
                                        {
                                            ++$rowCount;
                                            if (array_search($s,$unansweredSQs) !== false) {
                                                ++$numUnanswered;
                                            }
                                        }
                                    }
                                    if ($rowCount > 0 && $rowCount == $numUnanswered)
                                    {
                                        $qmandViolation = true;
                                    }
                                }
                            }
                            $sMandatoryText = $LEM->gT('Please check at least one box per row').'.';
                            $mandatoryTip .= Yii::app()->getController()->renderPartial('/survey/system/questionhelp/mandatory_tip', array(
                                    'sMandatoryText'=>$sMandatoryText,
                            ), true);

                        }
                        else
                        {
                            if (count($unansweredSQs) > 0)
                            {
                                $qmandViolation = true; // TODO - what about 'other'?
                            }
                            $sMandatoryText = $LEM->gT('Please complete all parts').'.';
                            $mandatoryTip .= Yii::app()->getController()->renderPartial('/survey/system/questionhelp/mandatory_tip', array(
                                    'sMandatoryText'=>$sMandatoryText,
                            ), true);
                        }
                        break;
                    case 'R':
                        if (count($unansweredSQs) > 0)
                        {
                            $qmandViolation = true; // TODO - what about 'other'?
                        }
                        $sMandatoryText = $LEM->gT('Please rank all items').'.';
                        $mandatoryTip .= Yii::app()->getController()->renderPartial('/survey/system/questionhelp/mandatory_tip', array(
                                'sMandatoryText'=>$sMandatoryText,
                        ), true);
                        break;
                    case 'O': //LIST WITH COMMENT drop-down/radio-button list + textarea
                        $_count=0;
                        for ($i=0;$i<count($unansweredSQs);++$i)
                        {
                            if (preg_match("/comment$/",$unansweredSQs[$i])) {
                                continue;
                            }
                            ++$_count;
                        }
                        if ($_count > 0)
                        {
                            $qmandViolation = true;
                        }
                        break;
                    default:
                        if (count($unansweredSQs) > 0)
                        {
                            $qmandViolation = true;
                        }
                        break;
                }
            }

            /////////////////////////////////////////////////////////////
            // DETECT WHETHER QUESTION SHOULD BE FLAGGED AS UNANSWERED //
            /////////////////////////////////////////////////////////////

            if ($qrel && !$qhidden)
            {
                switch ($qInfo['type'])
                {
                    case 'M':
                    case 'P':
                    case '!': //List - dropdown
                    case 'L': //LIST drop-down/radio-button list
                        // If at least one checkbox is checked, we're OK
                        if (count($relevantSQs) > 0 && (count($relevantSQs) == count($unansweredSQs)))
                        {
                            $anyUnanswered = true;
                        }
                        // what about optional vs. mandatory comment and 'other' fields?
                        break;
                    default:
                        $anyUnanswered = (count($unansweredSQs) > 0);
                        break;
                }
            }

            ///////////////////////////////////////////////
            // DETECT ANY VIOLATIONS OF VALIDATION RULES //
            ///////////////////////////////////////////////
            $qvalid=true;   // assume valid unless discover otherwise
            $hasValidationEqn=false;
            $prettyPrintValidEqn='';    //  assume no validation eqn by default
            $validationEqn='';
            $validationJS='';       // assume can't generate JavaScript to validate equation
            $validTip='';           // default is none
            if (isset($LEM->qid2validationEqn[$qid]))
            {
                $hasValidationEqn=true;

                // do this even is starts irrelevant, else will never show this information.
                if (!$qhidden)
                {
                    $validationEqns = $LEM->qid2validationEqn[$qid]['eqn'];
                    $validationEqn = implode(' and ', $validationEqns);
                    $qvalid = $LEM->em->ProcessBooleanExpression($validationEqn,$qInfo['gseq'], $qInfo['qseq']);
                    $hasErrors = $LEM->em->HasErrors();

                    if (!$hasErrors){
                        $validationJS = $LEM->em->GetJavaScriptEquivalentOfExpression();
                    }


                    $prettyPrintValidEqn = $validationEqn;
                    if ((($this->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX))
                    {
                        $prettyPrintValidEqn = $LEM->em->GetPrettyPrintString();
                    }

                    $stringToParse = '';
                    foreach ($LEM->qid2validationEqn[$qid]['tips'] as $vclass=>$vtip)
                    {
                        // Only add non-empty tip
                        if (trim($vtip) != "")
                        {
                            $tipsDatas = array(
                                'qid'   =>$qid,
                                'vclass'=>$vclass,
                                'vtip'  =>$vtip,
                            );
                            $stringToParse .= Yii::app()->getController()->renderPartial('/survey/system/questionhelp/tips', $tipsDatas, true);
                        }
                    }

                    $prettyPrintValidTip = $stringToParse;
                    $validTip = $LEM->ProcessString($stringToParse, $qid,NULL,false,1,1,false,false);
                    // TODO check for errors?
                    if ((($this->debugLevel & LEM_PRETTY_PRINT_ALL_SYNTAX) == LEM_PRETTY_PRINT_ALL_SYNTAX))
                    {
                        $prettyPrintValidTip = $LEM->GetLastPrettyPrintExpression();
                    }
                    $sumEqn = $LEM->qid2validationEqn[$qid]['sumEqn'];
                    $sumRemainingEqn = $LEM->qid2validationEqn[$qid]['sumRemainingEqn'];
                    //                $countEqn = $LEM->qid2validationEqn[$qid]['countEqn'];
                    //                $countRemainingEqn = $LEM->qid2validationEqn[$qid]['countRemainingEqn'];

                }
                else
                {
                    if (($LEM->debugLevel & LEM_DEBUG_VALIDATION_DETAIL) == LEM_DEBUG_VALIDATION_DETAIL)
                    {
                        $prettyPrintValidEqn = 'Question is Irrelevant, so no need to further validate it';
                    }
                }
            }

            if (!$qvalid)
            {
                $invalidSQs = $LEM->qid2code[$qid]; // TODO - currently invalidates all - should only invalidate those that truly fail validation rules.
            }
            /////////////////////////////////////////////////////////
            // OPTIONALLY DISPLAY (DETAILED) DEBUGGING INFORMATION //
            /////////////////////////////////////////////////////////
            if (($LEM->debugLevel & LEM_DEBUG_VALIDATION_SUMMARY) == LEM_DEBUG_VALIDATION_SUMMARY)
            {
                $editlink = Yii::app()->getController()->createUrl('admin/questions/sa/view/surveyid/' . $LEM->sid . '/gid/' . $gid . '/qid/' . $qid);
                $debug_qmessage .= '--[Q#' . $qInfo['qseq'] . ']'
                . "[<a href='$editlink'>"
                . 'QID:'. $qid . '</a>][' . $qInfo['type'] . ']: '
                . ($qrel ? 'relevant' : " <span style='color:red'>irrelevant</span> ")
                . ($qhidden ? " <span style='color:red'>always-hidden</span> " : ' ')
                . (($qInfo['mandatory'] == 'Y')? ' mandatory' : ' ')
                . (($hasValidationEqn) ? (!$qvalid ? " <span style='color:red'>(fails validation rule)</span> " : ' valid') : '')
                . ($qmandViolation ? " <span style='color:red'>(missing a relevant mandatory)</span> " : ' ')
                . $prettyPrintRelEqn
                . "<br />\n";

                if (($LEM->debugLevel & LEM_DEBUG_VALIDATION_DETAIL) == LEM_DEBUG_VALIDATION_DETAIL)
                {
                    if ($mandatoryTip != '')
                    {
                        $debug_qmessage .= '----Mandatory Tip: ' . flattenText($mandatoryTip) . "<br />\n";
                    }

                    if ($prettyPrintValidTip != '')
                    {
                        $debug_qmessage .= '----Pretty Validation Tip: <br />' . $prettyPrintValidTip . "<br />\n";
                    }
                    if ($validTip != '')
                    {
                        $debug_qmessage .= '----Validation Tip: <br />' . $validTip . "<br />\n";
                    }

                    if ($prettyPrintValidEqn != '')
                    {
                        $debug_qmessage .= '----Validation Eqn: ' . $prettyPrintValidEqn . "<br />\n";
                    }
                    if ($validationJS != '')
                    {
                        $debug_qmessage .= '----Validation JavaScript: ' . $validationJS . "<br />\n";
                    }

                    // what are the database question codes for this question?
                    $subQList = '{' . implode('}, {', explode('|',$LEM->qid2code[$qid])) . '}';
                    // pretty-print them
                    $LEM->ProcessString($subQList, $qid,NULL,false,1,1,false,false);
                    $prettyPrintSubQList = $LEM->GetLastPrettyPrintExpression();
                    $debug_qmessage .= '----SubQs=> ' . $prettyPrintSubQList . "<br />\n";

                    if (count($prettyPrintSQRelEqns) > 0)
                    {
                        $debug_qmessage .= "----Array Filters Applied:<br />\n";
                        foreach ($prettyPrintSQRelEqns as $key => $value)
                        {
                            $debug_qmessage .= '------' . $key . ': ' . $value . "<br />\n";
                        }
                        $debug_qmessage .= "<br />\n";
                    }

                    if (count($relevantSQs) > 0)
                    {
                        $subQList = '{' . implode('}, {', $relevantSQs) . '}';
                        // pretty-print them
                        $LEM->ProcessString($subQList, $qid,NULL,false,1,1,false,false);
                        $prettyPrintSubQList = $LEM->GetLastPrettyPrintExpression();
                        $debug_qmessage .= '----Relevant SubQs: ' . $prettyPrintSubQList . "<br />\n";
                    }

                    if (count($irrelevantSQs) > 0)
                    {
                        $subQList = '{' . implode('}, {', $irrelevantSQs) . '}';
                        // pretty-print them
                        $LEM->ProcessString($subQList, $qid,NULL,false,1,1,false,false);
                        $prettyPrintSubQList = $LEM->GetLastPrettyPrintExpression();
                        $debug_qmessage .= '----Irrelevant SubQs: ' . $prettyPrintSubQList . "<br />\n";
                    }

                    // show which relevant subQs were not answered
                    if (count($unansweredSQs) > 0)
                    {
                        $subQList = '{' . implode('}, {', $unansweredSQs) . '}';
                        // pretty-print them
                        $LEM->ProcessString($subQList, $qid,NULL,false,1,1,false,false);
                        $prettyPrintSubQList = $LEM->GetLastPrettyPrintExpression();
                        $debug_qmessage .= '----Unanswered Relevant SubQs: ' . $prettyPrintSubQList . "<br />\n";
                    }
                }
            }

            /////////////////////////////////////////////////////////////
            // CREATE ARRAY OF VALUES THAT NEED TO BE SILENTLY UPDATED //
            /////////////////////////////////////////////////////////////
            $updatedValues=array();
            if ((!$qrel || !$grel) && $LEM->surveyOptions['deletenonvalues'])
            {
                // If not relevant, then always NULL it in the database
                $sgqas = explode('|',$LEM->qid2code[$qid]);
                foreach ($sgqas as $sgqa)
                {
                    $_SESSION[$LEM->sessid][$sgqa] = NULL;
                    $updatedValues[$sgqa] = NULL;
                    $LEM->updatedValues[$sgqa] = NULL;
                }
            }
            elseif ($qInfo['type'] == '*')
            {
                // Process relevant equations, even if hidden, and write the result to the database
                $textToParse=(isset($LEM->qattr[$qid]['equation']) && trim($LEM->qattr[$qid]['equation'])!="") ? $LEM->qattr[$qid]['equation'] : $qInfo['qtext'];
                //$result = flattenText($LEM->ProcessString($textToParse, $qInfo['qid'],NULL,false,1,1,false,false,true));// More numRecursionLevels ?
                $sgqa = $LEM->qid2code[$qid];
                $redata=array();
                $result = flattenText(templatereplace( // Why flattenText ? htmlspecialchars($string,ENT_NOQUOTES) seem better ?
                    $textToParse,
                    array('QID'=>$qInfo['qid'],'GID'=>$qInfo['gid'],'SGQ'=>$sgqa), // Some date for replacement, other are only for "view"
                    $redata,
                    '',
                    false,
                    $qInfo['qid'],
                    array(),
                    true // Static replace
                ));

                    if($LEM->knownVars[$sgqa]['onlynum'])
                    {
                        $result=(is_numeric($result)?$result:"");
                    }
                    // Store the result of the Equation in the SESSION
                    $_SESSION[$LEM->sessid][$sgqa] = $result;
                    $_update = array(
                    'type'=>'*',
                    'value'=>$result,
                    );
                    $updatedValues[$sgqa] = $_update;
                    $LEM->updatedValues[$sgqa] = $_update;

                if (($LEM->debugLevel & LEM_DEBUG_VALIDATION_DETAIL) == LEM_DEBUG_VALIDATION_DETAIL)
                {
                    $prettyPrintEqn = $LEM->em->GetPrettyPrintString();
                    $debug_qmessage .= '** Process Hidden but Relevant Equation [' . $sgqa . '](' . $prettyPrintEqn . ') => ' . $result . "<br />\n";
                }
            }

            // Process Default : 1st part : update in DB if actually relevant and not already set
            if ($qrel && $grel)
            {
                $allSQs = explode('|',$LEM->qid2code[$qid]);
                foreach ($allSQs as $sgqa)
                {
                    if (!isset($_SESSION[$LEM->sessid][$sgqa]) && !is_null($LEM->knownVars[$sgqa]['default']))
                    {
                        $_SESSION[$LEM->sessid][$sgqa]=$LEM->ProcessString($LEM->knownVars[$sgqa]['default'], $qInfo['qid'], NULL, false, 1, 1, false, false, true);// Fill the $_SESSION to don't do it again a second time
                        $LEM->updatedValues[$sgqa] = $updatedValues[$sgqa] = array('type'=>$qInfo['type'],'value'=>$_SESSION[$LEM->sessid][$sgqa]);
                    }
                }
            }

            if ($LEM->surveyOptions['deletenonvalues'])
            {
                foreach ($irrelevantSQs as $sq)
                {
                    // NULL irrelevant sub-questions
                    $_SESSION[$LEM->sessid][$sq] = NULL;
                    $updatedValues[$sq] = NULL;
                    $LEM->updatedValues[$sq]= NULL;
                }
            }
            // Regardless of whether relevant or hidden, allways set a $_SESSION for quanda_helper, use default value if exist
            // Set this after testing relevance for default value hidden by relevance
            $allSQs = explode('|', $LEM->qid2code[$qid]);
            foreach($allSQs as $sgqa)
            {
                if(!isset($_SESSION[$LEM->sessid][$sgqa]))
                {
                    if(!is_null($LEM->knownVars[$sgqa]['default']))
                    {
                        $_SESSION[$LEM->sessid][$sgqa]=$LEM->ProcessString($LEM->knownVars[$sgqa]['default'],  $qInfo['qid'], NULL, false, 1, 1, false, false, true);
                    }
                    else
                    {
                        $_SESSION[$LEM->sessid][$sgqa]=null;
                    }
                }
            }

            //////////////////////////////////////////////////////////////////////////
            // STORE METADATA NEEDED FOR SUBSEQUENT PROCESSING AND DISPLAY PURPOSES //
            //////////////////////////////////////////////////////////////////////////

            $qStatus = array(
            'info' => $qInfo,   // collect all questions within the group - includes mandatory and always-hiddden status
            'relevant' => $qrel,
            'hidden' => $qInfo['hidden'],
            'relEqn' => $prettyPrintRelEqn,
            'sgqa' => $LEM->qid2code[$qid],
            'unansweredSQs' => implode('|',$unansweredSQs),
            'valid' => $force || $qvalid,
            'validEqn' => $validationEqn,
            'prettyValidEqn' => $prettyPrintValidEqn,
            'validTip' => $validTip,
            'prettyValidTip' => $prettyPrintValidTip,
            'validJS' => $validationJS,
            'invalidSQs' => (isset($invalidSQs) && !$force) ? $invalidSQs : '',
            'relevantSQs' => implode('|',$relevantSQs),
            'irrelevantSQs' => implode('|',$irrelevantSQs),
            'subQrelEqn' => implode('<br />',$prettyPrintSQRelEqns),
            'mandViolation' => (!$force) ? $qmandViolation : false,
            'anyUnanswered' => $anyUnanswered,
            'mandTip' => (!$force) ? $mandatoryTip : '',
            'message' => $debug_qmessage,
            'updatedValues' => $updatedValues,
            'sumEqn' => (isset($sumEqn) ? $sumEqn : ''),
            'sumRemainingEqn' => (isset($sumRemainingEqn) ? $sumRemainingEqn : ''),
            //            'countEqn' => (isset($countEqn) ? $countEqn : ''),
            //            'countRemainingEqn' => (isset($countRemainingEqn) ? $countRemainingEqn : ''),

            );

            $LEM->currentQset[$qid] = $qStatus;

            ////////////////////////////////////////////////////////
            // STORE METADATA NEEDED TO GENERATE NAVIGATION INDEX //
            ////////////////////////////////////////////////////////

            $groupSeq = $qInfo['gseq'];
            $LEM->indexQseq[$questionSeq] = array(
            'qid' => $qInfo['qid'],
            'qtext' => $qInfo['qtext'],
            'qcode' => $qInfo['code'],
            'qhelp' => $qInfo['help'],
            'anyUnanswered' => $anyUnanswered,
            'anyErrors' => (($qmandViolation || !$qvalid) ? true : false),
            'show' => (($qrel && !$qInfo['hidden']) ? true : false),
            'gseq' => $groupSeq,
            'gtext' => $LEM->gseq2info[$groupSeq]['description'],
            'gname' => $LEM->gseq2info[$groupSeq]['group_name'],
            'gid' => $LEM->gseq2info[$groupSeq]['gid'],
            'mandViolation' => $qmandViolation,
            'valid' => $qvalid,
            );
            $_SESSION[$LEM->sessid]['relevanceStatus'][$qid] = $qrel;
            return $qStatus;
        }

        static function GetQuestionStatus($qid)
        {
            $LEM =& LimeExpressionManager::singleton();
            if (isset($LEM->currentQset[$qid]))
            {
                return $LEM->currentQset[$qid];
            }
            return NULL;
        }

        /**
        * Get array of info needed to display the Group Index
        * @param string $gseq
        * @return array
        */
        static function GetGroupIndexInfo($gseq=NULL)
        {
            $LEM =& LimeExpressionManager::singleton();
            if (is_null($gseq)) {
                return $LEM->indexGseq;
            }
            else {
                return $LEM->indexGseq[$gseq];
            }
        }

        /**
        * Translate GID to 0-index Group Sequence number
        * @param int $gid
        * @return int
        */
        static function GetGroupSeq($gid)
        {
            $LEM =& LimeExpressionManager::singleton();
            return (isset($LEM->groupId2groupSeq[$gid]) ? $LEM->groupId2groupSeq[$gid] : -1);
        }

        /**
        * Get question sequence number from QID
        * @param int $qid
        * @return int
        */
        static function GetQuestionSeq($qid)
        {
            $LEM =& LimeExpressionManager::singleton();
            return (isset($LEM->questionId2questionSeq[$qid]) ? $LEM->questionId2questionSeq[$qid] : -1);
        }

        /**
        * Get array of info needed to display the Question Index
        * @return array
        */
        static function GetQuestionIndexInfo()
        {
            $LEM =& LimeExpressionManager::singleton();
            return $LEM->indexQseq;
        }

        /**
        * Return entries needed to build the navigation index
        * @param int|null $step - if specified, return a single value, otherwise return entire array
        * @return array - will be either question or group-level, depending upon $surveyMode
        */
        static function GetStepIndexInfo($step=NULL)
        {
            $LEM =& LimeExpressionManager::singleton();
            switch ($LEM->surveyMode)
            {
                case 'survey':
                    return $LEM->lastMoveResult;
                    // NB: No break needed
                case 'group':
                    if (is_null($step)) {
                        return $LEM->indexGseq;
                    }
                    return $LEM->indexGseq[$step];
                    // NB: No break needed
                case 'question':
                    if (is_null($step)) {
                        return $LEM->indexQseq;
                    }
                    return $LEM->indexQseq[$step];
                    // NB: No break needed
            }
        }

        /**
        * This should be called each time a new group is started, whether on same or different pages. Sets/Clears needed internal parameters.
        * @param int|null $gseq - the group sequence
        * @param boolean|null $anonymized - whether anonymized
        * @param int|null $surveyid - the surveyId
        * @param boolean|null $forceRefresh - whether to force refresh of setting variable and token mappings (should be done rarely)
        * @return void
        */
        static function StartProcessingGroup($gseq=NULL,$anonymized=false,$surveyid=NULL,$forceRefresh=false)
        {
            $LEM =& LimeExpressionManager::singleton();
            $LEM->em->StartProcessingGroup(
                isset($surveyid) ? $surveyid : NULL,
                '',
                isset($LEM->surveyOptions['hyperlinkSyntaxHighlighting']) ? $LEM->surveyOptions['hyperlinkSyntaxHighlighting'] : false
                );
            $LEM->groupRelevanceInfo = array();
            if (!is_null($gseq))
            {
                $LEM->currentGroupSeq = $gseq;

                if (!is_null($surveyid))
                {
                    $LEM->setVariableAndTokenMappingsForExpressionManager($surveyid,$forceRefresh,$anonymized,$LEM->allOnOnePage);
                    if ($gseq > $LEM->maxGroupSeq) {
                        $LEM->maxGroupSeq = $gseq;
                    }

                    if (!$LEM->allOnOnePage || ($LEM->allOnOnePage && !$LEM->processedRelevance)) {
                        $LEM->ProcessAllNeededRelevance();  // TODO - what if this is called using Survey or Data Entry format?
                        $LEM->_CreateSubQLevelRelevanceAndValidationEqns();
                        $LEM->processedRelevance=true;
                    }
                }
            }
        }

        /**
        * Should be called after each group finishes
        * @param boolean|null $skipReprocessing
        * @return void
        */
        static function FinishProcessingGroup($skipReprocessing=false)
        {
            //        $now = microtime(true);
            $LEM =& LimeExpressionManager::singleton();
            if ($skipReprocessing && $LEM->surveyMode != 'survey')
            {
                $LEM->pageTailorInfo=array();
                $LEM->pageRelevanceInfo=array();
            }
            $LEM->pageTailorInfo[] = $LEM->em->GetCurrentSubstitutionInfo();
            $LEM->pageRelevanceInfo[] = $LEM->groupRelevanceInfo;
            //        $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));

        }

        /**
        * Returns an array of string parts, splitting out expressions
        * @param string $src
        * @return string
        */
        static function SplitStringOnExpressions($src)
        {
            $LEM =& LimeExpressionManager::singleton();
            return $LEM->em->asSplitStringOnExpressions($src);
        }

        /**
        * Return a formatted table showing how much time each part of EM consumed
        * @return string
        */
        static function GetDebugTimingMessage()
        {
            $LEM =& LimeExpressionManager::singleton();
            return $LEM->debugTimingMsg;
        }

        /**
        * Should be called at end of each page
        * @return void
        */
        static function FinishProcessingPage()
        {
            $LEM =& LimeExpressionManager::singleton();

            $totalTime = 0.;
            if ((($LEM->debugLevel & LEM_DEBUG_TIMING) == LEM_DEBUG_TIMING) && count($LEM->runtimeTimings)>0) {
                $LEM->debugTimingMsg='';
                foreach($LEM->runtimeTimings as $unit) {
                    $totalTime += $unit[1];
                }
                $LEM->debugTimingMsg .= "<table class='table' border='1'><tr><td colspan=2><b>Total time attributable to EM = " . $totalTime . "</b></td></tr>\n";
                foreach ($LEM->runtimeTimings as $t)
                {
                    $LEM->debugTimingMsg .= "<tr><td>" . $t[0] . "</td><td>" . $t[1] . "</td></tr>\n";
                }
                $LEM->debugTimingMsg .= "</table>\n";
            }

            $LEM->runtimeTimings = array(); // reset them

            $LEM->initialized=false;    // so detect calls after done
            $LEM->ParseResultCache=array(); // don't need to persist it in session
            $_SESSION['LEMsingleton']=serialize($LEM);
        }

        /*
        * Generate JavaScript needed to do dynamic relevance and tailoring
        * Also create list of variables that need to be declared
        * @return string
        */
        static function GetRelevanceAndTailoringJavaScript()
        {
            $aQuestionsWithDependencies = array();
            $now = microtime(true);
            $LEM =& LimeExpressionManager::singleton();

            $jsParts=array();
            $allJsVarsUsed=array();
            $rowdividList=array();   // list of subquestions needing relevance entries
            App()->getClientScript()->registerScriptFile(Yii::app()->getConfig('generalscripts')."expressions/em_javascript.js");;
            $jsParts[] = "\n<script type='text/javascript'>\n<!--\n";
            $jsParts[] = "var LEMmode='" . $LEM->surveyMode . "';\n";
            if ($LEM->surveyMode == 'group')
            {
                $jsParts[] = "var LEMgseq=" . $LEM->currentGroupSeq . ";\n";
            }
            if ($LEM->surveyMode == 'question' && isset($LEM->currentQID))
            {
                $jsParts[] = "var LEMqid=" . $LEM->currentQID . ";\n";  // current group num so can compute isOnCurrentPage
            }

            $jsParts[] = "function ExprMgr_process_relevance_and_tailoring(evt_type,sgqa,type){\n";
            $jsParts[] = "if (typeof LEM_initialized == 'undefined') {\nLEM_initialized=true;\nLEMsetTabIndexes();\n}\n";
            $jsParts[] = "if (evt_type == 'onchange' && (typeof last_sgqa !== 'undefined' && sgqa==last_sgqa) && (typeof last_evt_type !== 'undefined' && last_evt_type == 'TAB' && type != 'checkbox')) {\n";
            $jsParts[] = "  last_evt_type='onchange';\n";
            $jsParts[] = "  last_sgqa=sgqa;\n";
            $jsParts[] = "  return;\n";
            $jsParts[] = "}\n";
            $jsParts[] = "last_evt_type = evt_type;\n";
            $jsParts[] = "last_sgqa=sgqa;\n";

            // flatten relevance array, keeping proper order

            $pageRelevanceInfo=array();
            $qidList = array(); // list of questions used in relevance and tailoring
            $gseqList = array(); // list of gseqs on this page
            $gseq_qidList = array(); // list of qids using relevance/tailoring within each group

            if (is_array($LEM->pageRelevanceInfo))
            {
                foreach($LEM->pageRelevanceInfo as $prel)
                {
                    foreach($prel as $rel)
                    {
                        $pageRelevanceInfo[] = $rel;
                    }
                }
            }

            $valEqns = array();
            $relEqns = array();
            $relChangeVars = array();

            $dynamicQinG = array(); // array of questions, per group, that might affect group-level visibility in all-in-one mode
            $GalwaysRelevant = array(); // checks whether a group is always relevant (e.g. has at least one question that is always shown)

            if (is_array($pageRelevanceInfo))
            {
                foreach ($pageRelevanceInfo as $arg)
                {
                    if (!$LEM->allOnOnePage && $LEM->currentGroupSeq != $arg['gseq']) {
                        continue;
                    }

                    $gseqList[$arg['gseq']] = $arg['gseq'];    // so keep them in order
                    // First check if there is any tailoring  and construct the tailoring JavaScript if needed
                    $tailorParts = array();
                    $relParts = array();    // relevance equation
                    $valParts = array();    // validation
                    $relJsVarsUsed = array();   // vars used in relevance and tailoring
                    $valJsVarsUsed = array();   // vars used in validations
                    foreach ($LEM->pageTailorInfo as $tailor)
                    {
                        if (is_array($tailor))
                        {
                            foreach ($tailor as $sub)
                            {
                                if ($sub['questionNum'] == $arg['qid'])
                                {
                                    $tailorParts[] = $sub['js'];
                                    $vars = explode('|',$sub['vars']);
                                    if (is_array($vars))
                                    {
                                        $allJsVarsUsed = array_merge($allJsVarsUsed,$vars);
                                        $relJsVarsUsed = array_merge($relJsVarsUsed,$vars);
                                    }
                                }
                            }
                        }
                    }

                    // Now check whether there is sub-question relevance to perform for this question
                    $subqParts = array();
                    if (isset($LEM->subQrelInfo[$arg['qid']]))
                    {
                        foreach ($LEM->subQrelInfo[$arg['qid']] as $subq)
                        {
                            $subqParts[$subq['rowdivid']] = $subq;
                        }
                    }

                    $qidList[$arg['qid']] = $arg['qid'];
                    if (!isset($gseq_qidList[$arg['gseq']]))
                    {
                        $gseq_qidList[$arg['gseq']] = array();
                    }
                    $gseq_qidList[$arg['gseq']][$arg['qid']] = '0';   // means the qid is within this gseq, but may not have a relevance equation

                    // Now check whether any sub-question validation needs to be performed
                    $subqValidations = array();
                    $validationEqns = array();
                    if (isset($LEM->qid2validationEqn[$arg['qid']]))
                    {
                        if (isset($LEM->qid2validationEqn[$arg['qid']]['subqValidEqns']))
                        {
                            $_veqs = $LEM->qid2validationEqn[$arg['qid']]['subqValidEqns'];
                            foreach($_veqs as $_veq)
                            {
                                // generate JavaScript for each - tests whether invalid.
                                if (strlen(trim($_veq['subqValidEqn'])) == 0) {
                                    continue;
                                }
                                $subqValidations[] = array(
                                'subqValidEqn' => $_veq['subqValidEqn'],
                                'subqValidSelector' => $_veq['subqValidSelector'],
                                );
                            }
                        }
                        $validationEqns = $LEM->qid2validationEqn[$arg['qid']]['eqn'];
                    }

                    // Process relevance for question $arg['qid'];
                    $relevance = $arg['relevancejs'];

                    $relChangeVars[] = "  relChange" . $arg['qid'] . "=false;\n"; // detect change in relevance status

                    if (($relevance == '' || $relevance == '1' || ($arg['result'] == true && $arg['numJsVars']==0)) && count($tailorParts) == 0 && count($subqParts) == 0 && count($subqValidations) == 0 && count($validationEqns) == 0)
                    {
                        // Only show constitutively true relevances if there is tailoring that should be done.
                        // After we can assign var with EM and change again relevance : then doing it second time (see bug #08315).
                        $relParts[] = "$('#relevance" . $arg['qid'] . "').val('1');  // always true\n";
                        $GalwaysRelevant[$arg['gseq']] = true;
                        continue;
                    }
                    $relevance = ($relevance == '' || ($arg['result'] == true && $arg['numJsVars']==0)) ? '1' : $relevance;
                    $relParts[] = "\nif (" . $relevance . ")\n{\n";
                    ////////////////////////////////////////////////////////////////////////
                    // DO ALL ARRAY FILTERING FIRST - MAY AFFECT VALIDATION AND TAILORING //
                    ////////////////////////////////////////////////////////////////////////

                    // Do all sub-question filtering (e..g array_filter)
                    /**
                     * $afHide - if true, then use jQuery.show().  If false, then disable/enable the row
                     */
                    $afHide = (isset($LEM->qattr[$arg['qid']]['array_filter_style']) ? ($LEM->qattr[$arg['qid']]['array_filter_style'] == '0') : true);
                    $inputSelector = (($arg['type'] == 'R') ? '' :  ' :input:not(:hidden)');
                    $updateColors = false;
                    $updateHeadings = false;
                    foreach ($subqParts as $sq)
                    {
                        $rowdividList[$sq['rowdivid']] = $sq['result'];
                        // make sure to update headings and colors for filtered questions (array filter and individual SQ relevance)
                        if( ! empty($sq['type'])) {
                            $updateColors = true;
                            // js to fix colors
                            // js to fix headings
                            $repeatheadings = Yii::app()->getConfig("repeatheadings");
                            if(isset($LEM->qattr[$arg['qid']]['repeat_headings']) && $LEM->qattr[$arg['qid']]['repeat_headings'] !== "") {
                                $repeatheadings = $LEM->qattr[$arg['qid']]['repeat_headings'];
                            }
                            if($repeatheadings > 0)
                            {
                                $updateHeadings = true;
                            }
                        }
                        // end
                        //this change is optional....changes to array should prevent "if( )"
                        $relParts[] = "  if ( " . (empty($sq['relevancejs'])?'1':$sq['relevancejs']) . " ) {\n";
                        if ($afHide)
                        {
                            $relParts[] = "    $('#javatbd" . $sq['rowdivid'] . "').show();\n";
                        }
                        else
                        {
                            $relParts[] = "    $('#javatbd" . $sq['rowdivid'] . "$inputSelector').removeAttr('disabled');\n";
                        }
                        if ($sq['isExclusiveJS'] != '')
                        {
                            $relParts[] = "    if ( " . $sq['isExclusiveJS'] . " ) {\n";
                            $relParts[] = "      $('#javatbd" . $sq['rowdivid'] . "$inputSelector').attr('disabled','disabled');\n";
                            $relParts[] = "    }\n";
                            $relParts[] = "    else {\n";
                            $relParts[] = "      $('#javatbd" . $sq['rowdivid'] . "$inputSelector').removeAttr('disabled');\n";
                            $relParts[] = "      $('#javatbd" . $sq['rowdivid'] . " th.answertext').removeClass('text-muted');\n";
                            $relParts[] = "    }\n";
                        }
                        $relParts[] = "    relChange" . $arg['qid'] . "=true;\n";
                        if($arg['type']!='R') // Ranking: rowdivid are subquestion, but array filter apply to answers and not SQ.
                            $relParts[] = "    $('#relevance" . $sq['rowdivid'] . "').val('1');\n";
                        $relParts[] = "  }\n  else {\n";
                        if ($sq['isExclusiveJS'] != '')
                        {
                            if ($sq['irrelevantAndExclusiveJS'] != '')
                            {
                                $relParts[] = "    if ( " . $sq['irrelevantAndExclusiveJS'] . " ) {\n";
                                $relParts[] = "      $('#javatbd" . $sq['rowdivid'] . "$inputSelector').attr('disabled','disabled');\n";
                                $relParts[] = "      $('#javatbd" . $sq['rowdivid'] . " th.answertext').addClass('text-muted');\n";
                                $relParts[] = "    }\n";
                                $relParts[] = "    else {\n";
                                $relParts[] = "      $('#javatbd" . $sq['rowdivid'] . "$inputSelector').removeAttr('disabled');\n";
                                $relParts[] = "      $('#javatbd" . $sq['rowdivid'] . " th.answertext').removeClass('text-muted');\n";
                                if ($afHide)
                                {
                                    $relParts[] = "     $('#javatbd" . $sq['rowdivid'] . "').hide();\n";
                                }
                                else
                                {
                                    $relParts[] = "     $('#javatbd" . $sq['rowdivid'] . "$inputSelector').attr('disabled','disabled');\n";
                                }
                                $relParts[] = "    }\n";
                            }
                            else
                            {
                                $relParts[] = "      $('#javatbd" . $sq['rowdivid'] . "$inputSelector').attr('disabled','disabled');\n";
                            }
                        }
                        else
                        {
                            if ($afHide)
                            {
                                $relParts[] = "    $('#javatbd" . $sq['rowdivid'] . "').hide();\n";
                            }
                            else
                            {
                                $relParts[] = "    $('#javatbd" . $sq['rowdivid'] . "$inputSelector').attr('disabled','disabled');\n";
                            }
                        }
                        $relParts[] = "    relChange" . $arg['qid'] . "=true;\n";
                        if($arg['type']!='R') // Ranking: rowdivid are subquestion, but array filter apply to answers and not SQ.
                            $relParts[] = "    $('#relevance" . $sq['rowdivid'] . "').val('');\n";
                        switch ($sq['qtype'])
                        {
                            case 'L': //LIST drop-down/radio-button list
                                $listItem = substr($sq['rowdivid'],strlen($sq['sgqa']));    // gets the part of the rowdiv id past the end of the sgqa code.
                                $relParts[] = "    if (($('#java" . $sq['sgqa'] ."').val() == '" . $listItem . "')";
                                if ($listItem == 'other') {
                                    $relParts[] = " || ($('#java" . $sq['sgqa'] ."').val() == '-oth-')";
                                }
                                $relParts[] = "){\n";
                                $relParts[] = "      $('#answer" . $sq['sgqa'] . "').click();\n"; // trigger click : no need other think, and whole event happen
                                $relParts[] = "    }\n";
                                break;
                            case 'R':
                                $listItem = substr($sq['rowdivid'],strlen($sq['sgqa']));
                                $relParts[] = " $('#question{$arg['qid']} .select-list select').each(function(){ \n";
                                $relParts[] = "   if($(this).val()=='{$listItem}'){ \n";
                                $relParts[] = "     $(this).val('').trigger('change'); \n";
                                $relParts[] = "   }; \n";
                                $relParts[] = " }); \n";
                                break;
                            default:
                                break;
                        }
                        $relParts[] = "  }\n";

                        $sqvars = explode('|',$sq['relevanceVars']);
                        if (is_array($sqvars))
                        {
                            $allJsVarsUsed = array_merge($allJsVarsUsed,$sqvars);
                            $relJsVarsUsed = array_merge($relJsVarsUsed,$sqvars);
                        }
                    }

                    if ($updateColors) {
                        $relParts[] = "updateColors('question".$arg['qid']."');\n";
                    }

                    if ($updateHeadings) {
                        $relParts[] = "updateHeadings('question".$arg['qid']."', " .$repeatheadings.");\n";
                    }

                    // Do all tailoring
                    $relParts[] = implode("\n",$tailorParts);

                    // Do custom validation
                    foreach ($subqValidations as $_veq)
                    {
                        if ($_veq['subqValidSelector'] == '') {
                            continue;
                        }
                        $LEM->em->ProcessBooleanExpression($_veq['subqValidEqn'],$arg['gseq'],$LEM->questionId2questionSeq[$arg['qid']]);
                        $_sqValidVars = $LEM->em->GetJSVarsUsed();
                        $allJsVarsUsed = array_merge($allJsVarsUsed,$_sqValidVars);
                        $valJsVarsUsed = array_merge($valJsVarsUsed,$_sqValidVars);
                        $validationJS = $LEM->em->GetJavaScriptEquivalentOfExpression();
                        if($validationJS!='')
                        {
                            $valParts[] = "\n  if(" . $validationJS . "){\n";
                            $valParts[] = "    $('#" . $_veq['subqValidSelector'] . "').addClass('em_sq_validation').removeClass('error').addClass('good').trigger('classChangeGood');;\n";
                            $valParts[] = "  }\n  else {\n";
                            $valParts[] = "    $('#" . $_veq['subqValidSelector'] . "').addClass('em_sq_validation').removeClass('good').addClass('error').trigger('classChangeError');\n";
                            $valParts[] = "  }\n";
                        }
                    }

                    // Set color-coding for validation equations
                    if (count($validationEqns) > 0) {
                        $valParts[] = "  isValidSum" . $arg['qid'] . "=true;\n";    // assume valid until proven otherwise
                        $valParts[] = "  isValidOther" . $arg['qid'] . "=true;\n";    // assume valid until proven otherwise
                        $valParts[] = "  isValidOtherComment" . $arg['qid'] . "=true;\n";    // assume valid until proven otherwise
                        foreach ($validationEqns as $vclass=>$validationEqn)
                        {
                            if ($validationEqn == '') {
                                continue;
                            }

                            $LEM->em->ProcessBooleanExpression($validationEqn,$arg['gseq'],$LEM->questionId2questionSeq[$arg['qid']]);
                            $_vars = $LEM->em->GetJSVarsUsed();
                            $allJsVarsUsed = array_merge($allJsVarsUsed,$_vars);
                            $valJsVarsUsed = array_merge($valJsVarsUsed,$_vars);
                            $_validationJS = $LEM->em->GetJavaScriptEquivalentOfExpression();
                            if($_validationJS!='')
                            {
                                $valParts[] = "\n  if(" . $_validationJS . "){\n";
                                $valParts[] = "    $('#vmsg_" . $arg['qid'] . '_' . $vclass . "').removeClass('error').addClass('good').trigger('classChangeGood');\n";
                                $valParts[] = "  }\n  else {\n";
                                $valParts[] = "    $('#vmsg_" . $arg['qid'] . '_' . $vclass ."').removeClass('good').addClass('error').trigger('classChangeError');\n";
                                switch ($vclass)
                                {
                                    case 'sum_range':
                                        $valParts[] = "    isValidSum" . $arg['qid'] . "=false;\n";
                                        break;
                                    case 'other_comment_mandatory':
                                        $valParts[] = "    isValidOtherComment" . $arg['qid'] . "=false;\n";
                                        break;
                                        //                            case 'num_answers':
                                        //                            case 'value_range':
                                        //                            case 'sq_fn_validation':
                                        //                            case 'q_fn_validation':
                                        //                            case 'regex_validation':
                                    default:
                                        $valParts[] = "    isValidOther" . $arg['qid'] . "=false;\n";
                                        break;

                                }
                                $valParts[] = "  }\n";
                            }
                        }

                        $valParts[] = "\n  if(isValidSum" . $arg['qid'] . "){\n";
                        $valParts[] = "    $('#totalvalue_" . $arg['qid'] . "').removeClass('error').addClass('good').trigger('classChangeGood');\n";
                        $valParts[] = "  }\n  else {\n";
                        $valParts[] = "    $('#totalvalue_" . $arg['qid'] . "').removeClass('good').addClass('error').trigger('classChangeError');\n";
                        $valParts[] = "  }\n";

                        // color-code single-entry fields as needed
                        switch ($arg['type'])
                        {
                            case 'N':
                            case 'S':
                            case 'D':
                            case 'T':
                            case 'U':
                                $valParts[] = "\n  if(isValidOther" . $arg['qid'] . "){\n";
                                $valParts[] = "    $('#question" . $arg['qid'] . " :input').addClass('em_sq_validation').removeClass('error').addClass('good').trigger('classChangeGood');\n";
                                $valParts[] = "  }\n  else {\n";
                                $valParts[] = "    $('#question" . $arg['qid'] . " :input').addClass('em_sq_validation').removeClass('good').addClass('error').trigger('classChangeError');\n";
                                $valParts[] = "  }\n";
                                break;
                            default:
                                break;
                        }

                        // color-code mandatory other comment fields
                        switch ($arg['type'])
                        {
                            case '!':
                            case 'L':
                            case 'P':
                            switch ($arg['type'])
                            {
                                case '!':
                                    $othervar = 'othertext' . substr($arg['jsResultVar'],4,-5);
                                    break;
                                case 'L':
                                    $othervar = 'answer' . substr($arg['jsResultVar'],4) . 'text';
                                    break;
                                case 'P':
                                    $othervar = 'answer' . substr($arg['jsResultVar'],4);
                                    break;
                                default:
                                    // TODO: Internal error if this happens
                                    $othervar = '';
                                    break;
                            }
                            $valParts[] = "\n  if(isValidOtherComment" . $arg['qid'] . "){\n";
                            $valParts[] = "    $('#" . $othervar . "').addClass('em_sq_validation').removeClass('error').addClass('good').trigger('classChangeGood');\n";
                            $valParts[] = "  }\n  else {\n";
                            $valParts[] = "    $('#" . $othervar . "').addClass('em_sq_validation').removeClass('good').addClass('error').trigger('classChangeError');\n";
                            $valParts[] = "  }\n";
                            break;
                            default:
                                break;
                        }
                    }

                    if (count($valParts) > 0)
                    {
                        $valJsVarsUsed = array_unique($valJsVarsUsed);
                        $qvalJS = "function LEMval" . $arg['qid'] . "(sgqa){\n";
                        //                    $qvalJS .= "  var UsesVars = ' " . implode(' ', $valJsVarsUsed) . " ';\n";
                        //                    $qvalJS .= "  if (typeof sgqa !== 'undefined' && !LEMregexMatch('/ java' + sgqa + ' /', UsesVars)) {\n return;\n }\n";
                        $qvalJS .= implode("",$valParts);
                        $qvalJS .= "}\n";
                        $valEqns[] = $qvalJS;

                        $relParts[] = "  LEMval" . $arg['qid'] . "(sgqa);\n";
                    }

                    if ($arg['hidden']) {
                        $relParts[] = "  // This question should always be hidden\n";
                        $relParts[] = "  $('#question" . $arg['qid'] . "').hide();\n";
                    }
                    else {
                        if (!($relevance == '' || $relevance == '1' || ($arg['result'] == true && $arg['numJsVars']==0)))
                        {
                            // In such cases, PHP will make the question visible by default.  By not forcing a re-show(), template.js can hide questions with impunity
                            $relParts[] = "  $('#question" . $arg['qid'] . "').show();\n";
                            if ($arg['type'] == 'S')
                            {
                                $relParts[] = "  if($('#question" . $arg['qid'] . " div[id^=\"gmap_canvas\"]').length > 0)\n";
                                $relParts[] = "  {\n";
                                $relParts[] = "      resetMap(" . $arg['qid'] . ");\n";
                                $relParts[] = "  }\n";
                            }
                        }
                    }
                    // If it is an equation, and relevance is true, then write the value from the question to the answer field storing the result
                    if ($arg['type'] == '*')
                    {
                        $relParts[] = "  // Write value from the question into the answer field\n";
                        $jsResultVar = $LEM->em->GetJsVarFor($arg['jsResultVar']);
                        // Note, this will destroy embedded HTML in the equation (e.g. if it is a report, can use {QCODE.question} for this purpose)
                        // This make same than flattenText to be same in JS and in PHP
                        $relParts[] = "  $('#" . substr($jsResultVar,1,-1) . "').val($.trim($('#question" . $arg['qid'] . " .em_equation').text()));\n";
                    }
                    $relParts[] = "  relChange" . $arg['qid'] . "=true;\n"; // any change to this value should trigger a propagation of changess
                    $relParts[] = "  $('#relevance" . $arg['qid'] . "').val('1');\n";

                    $relParts[] = "}\n";
                    if (!($relevance == '' || $relevance == '1' || ($arg['result'] == true && $arg['numJsVars']==0)))
                    {
                        if (!isset($dynamicQinG[$arg['gseq']]))
                        {
                            $dynamicQinG[$arg['gseq']] = array();
                        }
                        if( !($arg['hidden'] && $arg['type']=="*"))// Equation question type don't update visibility of group if hidden ( child of bug #08315).
                            $dynamicQinG[$arg['gseq']][$arg['qid']]=true;
                        $relParts[] = "else {\n";
                        $relParts[] = "  $('#question" . $arg['qid'] . "').hide();\n";
                        $relParts[] = "  if ($('#relevance" . $arg['qid'] . "').val()=='1') { relChange" . $arg['qid'] . "=true; }\n";  // only propagate changes if changing from relevant to irrelevant
                        $relParts[] = "  $('#relevance" . $arg['qid'] . "').val('0');\n";
                        $relParts[] = "}\n";
                    }
                    else
                    {
                        // Second time : now if relevance is true: Group is allways visible (see bug #08315).
                        $relParts[] = "$('#relevance" . $arg['qid'] . "').val('1');  // always true\n";
                        if( !($arg['hidden'] && $arg['type']=="*"))// Equation question type don't update visibility of group if hidden ( child of bug #08315).
                            $GalwaysRelevant[$arg['gseq']] = true;
                    }

                    $vars = explode('|',$arg['relevanceVars']);
                    if (is_array($vars))
                    {
                        $allJsVarsUsed = array_merge($allJsVarsUsed,$vars);
                        $relJsVarsUsed = array_merge($relJsVarsUsed,$vars);
                    }

                    $relJsVarsUsed = array_merge($relJsVarsUsed,$valJsVarsUsed);
                    $relJsVarsUsed = array_unique($relJsVarsUsed);
                    $qrelQIDs = array();
                    $qrelgseqs = array();    // so that any group-level change is also propagated
                    foreach ($relJsVarsUsed as $jsVar)
                    {
                        if ($jsVar != '' && isset($LEM->knownVars[substr($jsVar,4)]['qid']))
                        {
                            $knownVar = $LEM->knownVars[substr($jsVar,4)];
                            if ($LEM->surveyMode=='group' && $knownVar['gseq'] != $LEM->currentGroupSeq) {
                                continue;   // don't make dependent upon off-page variables
                            }
                            $_qid = $knownVar['qid'];
                            if ($_qid == $arg['qid']) {
                                continue;   // don't make dependent upon itself
                            }
                            $qrelQIDs[] = 'relChange' . $_qid;
                            $qrelgseqs[] = 'relChangeG' . $knownVar['gseq'];
                        }
                    }

                    $qrelgseqs[] = 'relChangeG' . $arg['gseq'];   // so if current group changes visibility, re-tailor it.
                    $qrelQIDs = array_unique($qrelQIDs);
                    $qrelgseqs = array_unique($qrelgseqs);
                    if ($LEM->surveyMode=='question')
                    {
                        $qrelQIDs=array();  // in question-by-questin mode, should never test for dependencies on self or other questions.
                        $qrelgseqs=array();
                    }

                    /**
                     * https://bugs.limesurvey.org/view.php?id=8308#c26972
                     * Thomas White explained: "LEMrelXX functions were specifically designed to only be called for questions that have some dependency upon others "
                     * So $qrelQIDs contains those questions.
                     */
                    foreach($qrelQIDs as $qrelQID)
                    {
                        $sQid = str_replace("relChange","",$qrelQID);
                        if(!in_array($sQid, $aQuestionsWithDependencies)  )
                        {
                            $aQuestionsWithDependencies[]=$sQid;
                        }
                    }


                    $qrelJS = "function LEMrel" . $arg['qid'] . "(sgqa){\n";
                    $qrelJS .= "  var UsesVars = ' " . implode(' ', $relJsVarsUsed) . " ';\n";
                    if (count($qrelQIDs) > 0)
                    {
                        $qrelJS .= "  if(" . implode(' || ', $qrelQIDs) . "){\n    ;\n  }\n  else";
                    }
                    if (count($qrelgseqs) > 0)
                    {
                        $qrelJS .= "  if(" . implode(' || ', $qrelgseqs) . "){\n    ;\n  }\n  else";
                    }
                    $qrelJS .= "  if (typeof sgqa !== 'undefined' && !LEMregexMatch('/ java' + sgqa + ' /', UsesVars)) {\n";
                    $qrelJS .= "  return;\n }\n";
                    $qrelJS .= implode("",$relParts);
                    $qrelJS .= "}\n";
                    $relEqns[] = $qrelJS;

                    $gseq_qidList[$arg['gseq']][$arg['qid']] = '1';   // means has an explicit LEMrel() function
                }
            }

            foreach(array_keys($gseq_qidList) as $_gseq)
            {
                $relChangeVars[] = "  relChangeG" . $_gseq . "=false;\n";
            }
            $jsParts[] = implode("",$relChangeVars);

            // Process relevance for each group; and if group is relevant, process each contained question in order
            foreach ($LEM->gRelInfo as $gr)
            {
                if (!array_key_exists($gr['gseq'],$gseqList)) {
                    continue;
                }
                if ($gr['relevancejs'] != '')
                {
                    //                $jsParts[] = "\n// Process Relevance for Group " . $gr['gid'];
                    //                $jsParts[] = ": { " . $gr['eqn'] . " }";
                    $jsParts[] = "\nif (" . $gr['relevancejs'] . ") {\n";
                    $jsParts[] = "  $('#group-" . $gr['gseq'] . "').show();\n";
                    $jsParts[] = "  relChangeG" . $gr['gseq'] . "=true;\n";
                    $jsParts[] = "  $('#relevanceG" . $gr['gseq'] . "').val(1);\n";

                    $qids = $gseq_qidList[$gr['gseq']];
                    foreach ($qids as $_qid=>$_val)
                    {
                        $qid2exclusiveAuto = (isset($LEM->qid2exclusiveAuto[$_qid]) ? $LEM->qid2exclusiveAuto[$_qid] : array());
                        if ($_val==1)
                        {
                            $jsParts[] = "  LEMrel" . $_qid . "(sgqa);\n";
                            if (isset($LEM->qattr[$_qid]['exclude_all_others_auto']) && $LEM->qattr[$_qid]['exclude_all_others_auto'] == '1'
                                && isset($qid2exclusiveAuto['js']) && strlen($qid2exclusiveAuto['js']) > 0)
                            {
                                $jsParts[] = $qid2exclusiveAuto['js'];
                                $vars = explode('|',$qid2exclusiveAuto['relevanceVars']);
                                if (is_array($vars))
                                {
                                    $allJsVarsUsed = array_merge($allJsVarsUsed,$vars);
                                }
                                if (!isset($rowdividList[$qid2exclusiveAuto['rowdivid']]))
                                {
                                    $rowdividList[$qid2exclusiveAuto['rowdivid']] = true;
                                }
                            }
                            if (isset($LEM->qattr[$_qid]['exclude_all_others']))
                            {
                                foreach (explode(';',trim($LEM->qattr[$_qid]['exclude_all_others'])) as $eo)
                                {
                                    // then need to call the function twice so that cascading of array filter onto an excluded option works
                                    $jsParts[] = "  LEMrel" . $_qid . "(sgqa);\n";
                                }
                            }
                        }
                    }

                    $jsParts[] = "}\nelse {\n";
                    $jsParts[] = "  $('#group-" . $gr['gseq'] . "').hide();\n";
                    $jsParts[] = "  if ($('#relevanceG" . $gr['gseq'] . "').val()=='1') { relChangeG" . $gr['gseq'] . "=true; }\n";
                    $jsParts[] = "  $('#relevanceG" . $gr['gseq'] . "').val(0);\n";
                    $jsParts[] = "}\n";
                }
                else
                {
                    $qids = $gseq_qidList[$gr['gseq']];
                    foreach ($qids as $_qid=>$_val)
                    {
                        $qid2exclusiveAuto = (isset($LEM->qid2exclusiveAuto[$_qid]) ? $LEM->qid2exclusiveAuto[$_qid] : array());
                        if ($_val == 1)
                        {
                            $jsParts[] = "  LEMrel" . $_qid . "(sgqa);\n";
                            if (isset($LEM->qattr[$_qid]['exclude_all_others_auto']) && $LEM->qattr[$_qid]['exclude_all_others_auto'] == '1'
                                && isset($qid2exclusiveAuto['js']) && strlen($qid2exclusiveAuto['js']) > 0)
                            {
                                $jsParts[] = $qid2exclusiveAuto['js'];
                                $vars = explode('|',$qid2exclusiveAuto['relevanceVars']);
                                if (is_array($vars))
                                {
                                    $allJsVarsUsed = array_merge($allJsVarsUsed,$vars);
                                }
                                if (!isset($rowdividList[$qid2exclusiveAuto['rowdivid']]))
                                {
                                    $rowdividList[$qid2exclusiveAuto['rowdivid']] = true;
                                }
                            }
                            if (isset($LEM->qattr[$_qid]['exclude_all_others']))
                            {
                                foreach (explode(';',trim($LEM->qattr[$_qid]['exclude_all_others'])) as $eo)
                                {
                                    // then need to call the function twice so that cascading of array filter onto an excluded option works
                                    $jsParts[] = "  LEMrel" . $_qid . "(sgqa);\n";
                                }
                            }
                        }
                    }
                }

                // Add logic for all-in-one mode to show/hide groups as long as at there is at least one relevant question within the group
                // Only do this if there is no explicit group-level relevance equation, else may override group-level relevance
                $dynamicQidsInG = (isset($dynamicQinG[$gr['gseq']]) ? $dynamicQinG[$gr['gseq']] : array());
                $GalwaysVisible = (isset($GalwaysRelevant[$gr['gseq']]) ? $GalwaysRelevant[$gr['gseq']] : false);
                if ($LEM->surveyMode == 'survey' && !$GalwaysVisible && count($dynamicQidsInG) > 0 && strlen(trim($gr['relevancejs']))== 0)
                {
                    // check whether any dependent questions  have changed
                    $relStatusTest = "($('#relevance" . implode("').val()=='1' || $('#relevance", array_keys($dynamicQidsInG)) . "').val()=='1')";

                    $jsParts[] = "\nif (" . $relStatusTest . ") {\n";
                    $jsParts[] = "  $('#group-" . $gr['gseq'] . "').show();\n";
                    $jsParts[] = "  if ($('#relevanceG" . $gr['gseq'] . "').val()=='0') { relChangeG" . $gr['gseq'] . "=true; }\n";
                    $jsParts[] = "  $('#relevanceG" . $gr['gseq'] . "').val(1);\n";
                    $jsParts[] = "}\nelse {\n";
                    $jsParts[] = "  $('#group-" . $gr['gseq'] . "').hide();\n";
                    $jsParts[] = "  if ($('#relevanceG" . $gr['gseq'] . "').val()=='1') { relChangeG" . $gr['gseq'] . "=true; }\n";
                    $jsParts[] = "  $('#relevanceG" . $gr['gseq'] . "').val(0);\n";
                    $jsParts[] = "}\n";
                }

                // now make sure any needed variables are accessible
                $vars = explode('|',$gr['relevanceVars']);
                if (is_array($vars))
                {
                    $allJsVarsUsed = array_merge($allJsVarsUsed,$vars);
                }
            }

            $jsParts[] = "\n}\n";

            $jsParts[] = implode("\n",$relEqns);
            $jsParts[] = implode("\n",$valEqns);

            $allJsVarsUsed = array_unique($allJsVarsUsed);

            // Add JavaScript Mapping Arrays
            if (isset($LEM->alias2varName) && count($LEM->alias2varName) > 0)
            {
                $neededAliases=array();
                $neededCanonical=array();
                $neededCanonicalAttr=array();
                foreach ($allJsVarsUsed as $jsVar)
                {
                    if ($jsVar == '') {
                        continue;
                    }
                    if (preg_match("/^.*\.NAOK$/", $jsVar)) {
                        $jsVar = preg_replace("/\.NAOK$/","",$jsVar);
                    }
                    $neededCanonical[] = $jsVar;
                    foreach ($LEM->alias2varName as $key=>$value)
                    {
                        if ($jsVar == $value['jsName'])
                        {
                            $neededAliases[] = $value['jsPart'];
                        }
                    }
                }
                $neededCanonical = array_unique($neededCanonical);
                foreach ($neededCanonical as $nc)
                {
                    $neededCanonicalAttr[] = $LEM->varNameAttr[$nc];
                }
                $neededAliases = array_unique($neededAliases);
                if (count($neededAliases) > 0)
                {
                    $jsParts[] = "var LEMalias2varName = {\n";
                    $jsParts[] = implode(",\n",$neededAliases);
                    $jsParts[] = "};\n";
                }
                if (count($neededCanonicalAttr) > 0)
                {
                    $jsParts[] = "var LEMvarNameAttr = {\n";
                    $jsParts[] = implode(",\n",$neededCanonicalAttr);
                    $jsParts[] = "};\n";
                }
            }

            $jsParts[] = "//-->\n</script>\n";

            // Now figure out which variables have not been declared (those not on the current page)
            $undeclaredJsVars = array();
            $undeclaredVal = array();
            if (!$LEM->allOnOnePage)
            {
                foreach ($LEM->knownVars as $key=>$knownVar)
                {
                    if (!is_numeric($key[0])) {
                        continue;
                    }
                    if ($knownVar['jsName'] == '') {
                        continue;
                    }
                    foreach ($allJsVarsUsed as $jsVar)
                    {
                        if ($jsVar == $knownVar['jsName'])
                        {
                            if ($LEM->surveyMode=='group' && $knownVar['gseq'] == $LEM->currentGroupSeq) {
                                if ($knownVar['hidden'] && $knownVar['type'] != '*') {
                                    ;   // need to  declare a hidden variable for non-equation hidden variables so can do dynamic lookup.
                                }
                                else {
                                    continue;
                                }
                            }
                            if ($LEM->surveyMode=='question' && $knownVar['qid'] == $LEM->currentQID) {
                                continue;
                            }
                            $undeclaredJsVars[] = $jsVar;
                            $sgqa = $knownVar['sgqa'];
                            $codeValue = (isset($_SESSION[$LEM->sessid][$sgqa])) ? $_SESSION[$LEM->sessid][$sgqa] : '';
                            $undeclaredVal[$jsVar] = $codeValue;

                            if (isset($LEM->jsVar2qid[$jsVar])) {
                                $qidList[$LEM->jsVar2qid[$jsVar]] = $LEM->jsVar2qid[$jsVar];
                            }
                        }
                    }
                }
                $undeclaredJsVars = array_unique($undeclaredJsVars);
                foreach ($undeclaredJsVars as $jsVar)
                {
                    // TODO - is different type needed for text?  Or process value to striphtml?
                    if ($jsVar == '') continue;
                    $jsParts[] = "<input type='hidden' id='" . $jsVar . "' name='" . substr($jsVar,4) .  "' value='" . htmlspecialchars($undeclaredVal[$jsVar],ENT_QUOTES) . "'/>\n";
                }
            }
            else
            {
                // For all-in-one mode, declare the always-hidden variables, since qanda will not be called for them.
                foreach ($LEM->knownVars as $key=>$knownVar)
                {
                    if (!is_numeric($key[0])) {
                        continue;
                    }
                    if ($knownVar['jsName'] == '') {
                        continue;
                    }
                    if ($knownVar['hidden'])
                    {
                        $jsVar = $knownVar['jsName'];
                        $undeclaredJsVars[] = $jsVar;
                        $sgqa = $knownVar['sgqa'];
                        $codeValue = (isset($_SESSION[$LEM->sessid][$sgqa])) ? $_SESSION[$LEM->sessid][$sgqa] : '';
                        $undeclaredVal[$jsVar] = $codeValue;
                    }
                }

                $undeclaredJsVars = array_unique($undeclaredJsVars);
                foreach ($undeclaredJsVars as $jsVar)
                {
                    if ($jsVar == '') continue;
                    $jsParts[] = "<input type='hidden' id='" . $jsVar . "' name='" . $jsVar .  "' value='" . htmlspecialchars($undeclaredVal[$jsVar],ENT_QUOTES) . "'/>\n";
                }
            }
            foreach ($qidList as $qid)
            {
                if (isset($_SESSION[$LEM->sessid]['relevanceStatus'])) {
                    $relStatus = (isset($_SESSION[$LEM->sessid]['relevanceStatus'][$qid]) ? $_SESSION[$LEM->sessid]['relevanceStatus'][$qid] : 1);
                }
                else {
                    $relStatus = 1;
                }
                $jsParts[] = "<input type='hidden' id='relevance" . $qid . "' name='relevance" . $qid .  "' value='" . $relStatus . "'/>\n";
            }

            foreach ($gseqList as $gseq)
            {
                if (isset($_SESSION['relevanceStatus'])) {
                    $relStatus = (isset($_SESSION['relevanceStatus']['G' . $gseq]) ? $_SESSION['relevanceStatus']['G' . $gseq] : 1);
                }
                else {
                    $relStatus = 1;
                }
                $jsParts[] = "<input type='hidden' id='relevanceG" . $gseq . "' name='relevanceG" . $gseq .  "' value='" . $relStatus . "'/>\n";
            }
            foreach ($rowdividList as $key=>$val)
            {
                $jsParts[] = "<input type='hidden' id='relevance" . $key . "' name='relevance" . $key .  "' value='" . $val . "'/>\n";
            }
            $LEM->runtimeTimings[] = array(__METHOD__,(microtime(true) - $now));


            $jsParts[]="<input type='hidden' id='aQuestionsWithDependencies' data-qids='".json_encode($aQuestionsWithDependencies)."' />";

            return implode('',$jsParts);
        }

        static function setTempVars($vars)
        {
            $LEM =& LimeExpressionManager::singleton();
            $LEM->tempVars = $vars;
        }

        /**
        * Unit test strings containing expressions
        */
        static function UnitTestProcessStringContainingExpressions()
        {
            $vars = array(
            'name' => array('sgqa'=>'name', 'code'=>'Peter', 'jsName'=>'java61764X1X1', 'readWrite'=>'N', 'type'=>'X', 'question'=>'What is your first/given name?', 'qseq'=>10, 'gseq'=>1),
            'surname' => array('sgqa'=>'surname', 'code'=>'Smith', 'jsName'=>'java61764X1X1', 'readWrite'=>'Y', 'type'=>'X', 'question'=>'What is your last/surname?', 'qseq'=>20, 'gseq'=>1),
            'age' => array('sgqa'=>'age', 'code'=>45, 'jsName'=>'java61764X1X2', 'readWrite'=>'Y', 'type'=>'X', 'question'=>'How old are you?', 'qseq'=>30, 'gseq'=>2),
            'numKids' => array('sgqa'=>'numKids', 'code'=>2, 'jsName'=>'java61764X1X3', 'readWrite'=>'Y', 'type'=>'X', 'question'=>'How many kids do you have?', 'relevance'=>'1', 'qid'=>'40','qseq'=>40, 'gseq'=>2),
            'numPets' => array('sgqa'=>'numPets', 'code'=>1, 'jsName'=>'java61764X1X4', 'readWrite'=>'Y', 'type'=>'X','question'=>'How many pets do you have?', 'qseq'=>50, 'gseq'=>2),
            'gender' => array('sgqa'=>'gender', 'code'=>'M', 'jsName'=>'java61764X1X5', 'readWrite'=>'Y', 'type'=>'X', 'shown'=>'Male','question'=>'What is your gender (male/female)?', 'qseq'=>110, 'gseq'=>2),
            'notSetYet' => array('sgqa'=>'notSetYet', 'code'=>'?', 'jsName'=>'java61764X3X6', 'readWrite'=>'Y', 'type'=>'X', 'shown'=>'Unknown','question'=>'Who will win the next election?', 'qseq'=>200, 'gseq'=>3),
            // Constants
            '61764X1X1' => array('sgqa'=>'61764X1X1', 'code'=> '<Sergei>', 'jsName'=>'', 'readWrite'=>'N', 'type'=>'X', 'qseq'=>70, 'gseq'=>2),
            '61764X1X2' => array('sgqa'=>'61764X1X2', 'code'=> 45, 'jsName'=>'', 'readWrite'=>'N', 'type'=>'X', 'qseq'=>80, 'gseq'=>2),
            '61764X1X3' => array('sgqa'=>'61764X1X3', 'code'=> 2, 'jsName'=>'', 'readWrite'=>'N', 'type'=>'X', 'qseq'=>15, 'gseq'=>1),
            '61764X1X4' => array('sgqa'=>'61764X1X4', 'code'=> 1, 'jsName'=>'', 'readWrite'=>'N', 'type'=>'X', 'qseq'=>100, 'gseq'=>2),
            'TOKEN:ATTRIBUTE_1' => array('code'=> 'worker', 'jsName'=>'', 'readWrite'=>'N', 'type'=>'X'),
            );

            $tests = <<<EOD
This example shows escaping of the curly braces: \{\{test\}\} {if(1==1,'{{test}}', '1 is not 1?')} should not throw any errors.
<b>Here is an example of OK syntax with tooltips</b><br />Hello {if(gender=='M','Mr.','Mrs.')} {surname}, it is now {date('g:i a',time())}.  Do you know where your {sum(numPets,numKids)} chidren and pets are?
<b>Here are common errors so you can see the tooltips</b><br />Variables used before they are declared:  {notSetYet}<br />Unknown Function:  {iff(numPets>numKids,1,2)}<br />Unknown Variable: {sum(age,num_pets,numKids)}<br />Wrong # parameters: {sprintf()},{if(1,2)},{date()}<br />Assign read-only-vars:{TOKEN:ATTRIBUTE_1+=10},{name='Sally'}<br />Unbalanced parentheses: {pow(3,4},{(pow(3,4)},{pow(3,4))}
<b>Here is some of the unsupported syntax</b><br />No support for '++', '--', '%',';': {min(++age, --age,age % 2);}<br />Nor '|', '&', '^': {(sum(2 | 3,3 & 4,5 ^ 6)}}<br />Nor arrays: {name[2], name['mine']}
<b>Inline JavaScipt that forgot to add spaces after curly brace</b><br />[script type="text/javascript" language="Javascript"] var job='{TOKEN:ATTRIBUTE_1}'; if (job=='worker') {document.write('BOSSES');}[/script]
<b>Unknown/Misspelled Variables, Functions, and Operators</b><br />{if(sex=='M','Mr.','Mrs.')} {surname}, next year you will be {age++} years old.
<b>Warns if use = instead of == or perform value assignments</b><br>Hello, {if(gender='M','Mr.','Mrs.')} {surname}, next year you will be {age+=1} years old.
<b>Wrong number of arguments for functions:</b><br />{if(gender=='M','Mr.','Mrs.','Other')} {surname}, sum(age,numKids,numPets)={sum(age,numKids,numPets,)}
<b>Mismatched parentheses</b><br />pow(3,4)={pow(3,4)}<br />but these are wrong: {pow(3,4}, {(((pow(3,4)}, {pow(3,4))}
<b>Unsupported syntax</b><br />No support for '++', '--', '%',';': {min(++age, --age, age % 2);}<br />Nor '|', '&', '^':  {(sum(2 | 3, 3 & 4, 5 ^ 6)}}<br />Nor arrays:  {name[2], name['mine']}
<b>Invalid assignments</b><br />Assign values to equations or strings:  {(3 + 4)=5}, {'hi'='there'}<br />Assign read-only vars:  {TOKEN:ATTRIBUTE_1='boss'}, {name='Sally'}
<b>Values:</b><br />name={name}; surname={surname}<br />gender={gender}; age={age}; numPets={numPets}<br />numKids=INSERTANS:61764X1X3={numKids}={INSERTANS:61764X1X3}<br />TOKEN:ATTRIBUTE_1={TOKEN:ATTRIBUTE_1}
<b>Question attributes:</b><br />numKids.question={numKids.question}; Question#={numKids.qid}; .relevance={numKids.relevance}
<b>Math:</b><br/>5+7={5+7}; 2*pi={2*pi()}; sin(pi/2)={sin(pi()/2)}; max(age,numKids,numPets)={max(age,numKids,numPets)}
<b>Text Processing:</b><br />{str_replace('like','love','I like LimeSurvey')}<br />{ucwords('hi there')}, {name}<br />{implode('--',name,'this is','a convenient way','way to','concatenate strings')}
<b>Dates:</b><br />{name}, the current date/time is: {date('F j, Y, g:i a',time())}
<b>Conditional:</b><br />Hello, {if(gender=='M','Mr.','Mrs.')} {surname}, may I call you {name}?
<b>Tailored Paragraph:</b><br />{name}, you said that you are {age} years old, and that you have {numKids} {if((numKids==1),'child','children')} and {numPets} {if((numPets==1),'pet','pets')} running around the house. So, you have {numKids + numPets} wild {if((numKids + numPets ==1),'beast','beasts')} to chase around every day.<p>Since you have more {if((numKids > numPets),'children','pets')} than you do {if((numKids > numPets),'pets','children')}, do you feel that the {if((numKids > numPets),'pets','children')} are at a disadvantage?</p>
<b>EM processes within strings:</b><br />Here is your picture [img src='images/users_{name}_{surname}.jpg' alt='{if(gender=='M','Mr.','Mrs.')} {name} {surname}'/];
<b>EM doesn't process curly braces like these:</b><br />{name}, { this is not an expression}<br />{nor is this }, { nor  this }<br />\{nor this\},{this\},\{or this }
{INSERTANS:61764X1X1}, you said that you are {INSERTANS:61764X1X2} years old, and that you have {INSERTANS:61764X1X3} {if((INSERTANS:61764X1X3==1),'child','children')} and {INSERTANS:61764X1X4} {if((INSERTANS:61764X1X4==1),'pet','pets')} running around the house.  So, you have {INSERTANS:61764X1X3 + INSERTANS:61764X1X4} wild {if((INSERTANS:61764X1X3 + INSERTANS:61764X1X4 ==1),'beast','beasts')} to chase around every day.
Since you have more {if((INSERTANS:61764X1X3 > INSERTANS:61764X1X4),'children','pets')} than you do {if((INSERTANS:61764X1X3 > INSERTANS:61764X1X4),'pets','children')}, do you feel that the {if((INSERTANS:61764X1X3 > INSERTANS:61764X1X4),'pets','children')} are at a disadvantage?
{INSERTANS:61764X1X1}, you said that you are {INSERTANS:61764X1X2} years old, and that you have {INSERTANS:61764X1X3} {if((INSERTANS:61764X1X3==1),'child','children','kiddies')} and {INSERTANS:61764X1X4} {if((INSERTANS:61764X1X4==1),'pet','pets')} running around the house.  So, you have {INSERTANS:61764X1X3 + INSERTANS:61764X1X4} wild {if((INSERTANS:61764X1X3 + INSERTANS:61764X1X4 ==1),'beast','beasts')} to chase around every day.
This line should throw errors since the curly-brace enclosed functions do not have linefeeds after them (and before the closing curly brace): var job='{TOKEN:ATTRIBUTE_1}'; if (job=='worker') { document.write('BOSSES') } else { document.write('WORKERS') }
This line has a script section, but if you look at the source, you will see that it has errors: <script type="text/javascript" language="Javascript">var job='{TOKEN:ATTRIBUTE_1}'; if (job=='worker') {document.write('BOSSES')} else {document.write('WORKERS')} </script>.
Substitions that begin or end with a space should be ignored: { name} {age }
EOD;
            $alltests = explode("\n",$tests);

            $javascript1 = <<<EOST
                    var job='{TOKEN:ATTRIBUTE_1}';
                    if (job=='worker') {
                    document.write('BOSSES')
                    } else {
                    document.write('WORKERS')
                    }
EOST;
            $javascript2 = <<<EOST
var job='{TOKEN:ATTRIBUTE_1}';
    if (job=='worker') {
       document.write('BOSSES')
    } else { document.write('WORKERS')  }
EOST;
            $alltests[] = 'This line should have no errors - the Javascript has curly braces followed by line feeds:' . $javascript1;
            $alltests[] = 'This line should also be OK: ' . $javascript2;
            $alltests[] = 'This line has a hidden script: <script type="text/javascript" language="Javascript">' . $javascript1 . '</script>';
            $alltests[] = 'This line has a hidden script: <script type="text/javascript" language="Javascript">' . $javascript2 . '</script>';

            LimeExpressionManager::StartProcessingPage();
            LimeExpressionManager::StartProcessingGroup(1);

            $LEM =& LimeExpressionManager::singleton();
            $LEM->tempVars = $vars;

            $LEM->questionId2questionSeq = array();
            $LEM->questionId2groupSeq = array();
            $_SESSION[$LEM->sessid]['relevanceStatus'] = array();
            foreach ($vars as $var) {
                if (isset($var['qseq'])) {
                    $LEM->questionId2questionSeq[$var['qseq']] = $var['qseq'];
                    $LEM->questionId2groupSeq[$var['qseq']] = $var['gseq'];
                    $_SESSION[$LEM->sessid]['relevanceStatus'][$var['qseq']] = 1;
                }
            }

            print "<h3>Note, if the <i>Vars Used</i> column is red, then at least one error was found in the <b>Source</b>. In such cases, the <i>Vars Used</i> list may be missing names of variables from sub-expressions containing errors</h3>";
            print '<table class="table" border="1"><tr><th>Source</th><th>Pretty Print</th><th>Result</th><th>Vars Used</th></tr>';
            for ($i=0;$i<count($alltests);++$i)
            {
                $test = $alltests[$i];
                $result = LimeExpressionManager::ProcessString($test, 40, NULL, false, 1, 1);
                $prettyPrint = LimeExpressionManager::GetLastPrettyPrintExpression();
                $varsUsed = $LEM->em->GetAllVarsUsed();
                if (count($varsUsed) > 0) {
                    sort($varsUsed);
                    $varList = implode(',<br />', $varsUsed);
                }
                else {
                    $varList  = '&nbsp;';
                }

                print "<tr><td>" . htmlspecialchars($test,ENT_QUOTES) . "</td>\n";
                print "<td>" . $prettyPrint . "</td>\n";
                print "<td>" . $result . "</td>\n";
                if ($LEM->em->HasErrors()) {
                    print "<td style='background-color:  red'>";
                }
                else {
                    print "<td>";
                }
                print $varList . "</td>\n";
                print "</tr>\n";
            }
            print '</table>';
            LimeExpressionManager::FinishProcessingGroup();
            LimeExpressionManager::FinishProcessingPage();
        }

        /**
        * Unit test Relevance using a simplified syntax to represent questions.
        */
        static function UnitTestRelevance()
        {
            // Tests:  varName~relevance~inputType~message
            $tests = <<<EOT
name~1~text~What is your name?
age~1~text~How old are you (must be 16-80)?
badage~1~expr~{badage=((age<16) || (age>80))}
agestop~!is_empty(age) && ((age<16) || (age>80))~message~Sorry, {name}, you are too {if((age<16),'young',if((age>80),'old','middle-aged'))} for this test.
kids~!((age<16) || (age>80))~yesno~Do you have children (Y/N)?
kidsO~!is_empty(kids) && !(kids=='Y' or kids=='N')~message~Please answer the question about whether you have children with 'Y' or 'N'.
wantsKids~kids=='N'~yesno~Do you hope to have kids some day (Y/N)?
wantsKidsY~wantsKids=='Y'~message~{name}, I hope you are able to have children some day!
wantsKidsN~wantsKids=='N'~message~{name}, I hope you have a wonderfully fulfilling life!
wantsKidsO~!is_empty(wantsKids) && !(wantsKids=='Y' or wantsKids=='N')~message~Please answer the question about whether you want children with 'Y' or 'N'.
parents~1~expr~{parents = (!badage && kids=='Y')}
numKids~kids=='Y'~text~How many children do you have?
numKidsValidation~parents and strlen(numKids) > 0 and numKids <= 0~message~{name}, please check your entries.  You said you do have children, {numKids} of them, which makes no sense.
kid1~numKids >= 1~text~How old is your first child?
kid2~numKids >= 2~text~How old is your second child?
kid3~numKids >= 3~text~How old is your third child?
kid4~numKids >= 4~text~How old is your fourth child?
kid5~numKids >= 5~text~How old is your fifth child?
sumage~1~expr~{sumage=sum(kid1.NAOK,kid2.NAOK,kid3.NAOK,kid4.NAOK,kid5.NAOK)}
report~numKids > 0~message~{name}, you said you are {age} and that you have {numKids} kids.  The sum of ages of your first {min(numKids,5)} kids is {sumage}.
EOT;

            $vars = array();
            $varSeq = array();
            $testArgs = array();
            $argInfo = array();

            LimeExpressionManager::SetDirtyFlag();
            $LEM =& LimeExpressionManager::singleton();


            LimeExpressionManager::StartProcessingPage(true);
            LimeExpressionManager::StartProcessingGroup(1); // pretending this is group 1

            // collect variables
            $i=0;
            foreach(explode("\n",$tests) as $test)
            {
                $args = explode("~",$test);
                $type = (($args[1]=='expr') ? '*' : ($args[1]=='message') ? 'X' : 'S');
                $vars[$args[0]] = array('sgqa'=>$args[0], 'code'=>'', 'jsName'=>'java' . $args[0], 'jsName_on'=>'java' . $args[0], 'readWrite'=>'Y', 'type'=>$type, 'relevanceStatus'=>'1', 'gid'=>1, 'gseq'=>1, 'qseq'=>$i, 'qid'=>$i);
                $varSeq[] = $args[0];
                $testArgs[] = $args;
                $LEM->questionId2questionSeq[$i] = $i;
                $LEM->questionId2groupSeq[$i] = 1;
                $LEM->questionSeq2relevance[$i] = array(
                'relevance'=>htmlspecialchars(preg_replace('/[[:space:]]/',' ',$args[1]),ENT_QUOTES),
                'qid'=>$i,
                'qseq'=>$i,
                'gseq'=>1,
                'jsResultVar'=>'java' . $args[0],
                'type'=>$type,
                'hidden'=>false,
                'gid'=>1,   // ($i % 3),
                );
                ++$i;
            }

            $LEM->knownVars = $vars;
            $LEM->gRelInfo[1] = array(
            'gid' => 1,
            'gseq' => 1,
            'eqn' => '',
            'result' => 1,
            'numJsVars' => 0,
            'relevancejs' => '',
            'relevanceVars' => '',
            'prettyPrint'=> '',
            );
            $LEM->ProcessAllNeededRelevance();

            // collect relevance
            $alias2varName = array();
            $varNameAttr = array();
            for ($i=0;$i<count($testArgs);++$i)
            {
                $testArg = $testArgs[$i];
                $var = $testArg[0];
                $rel = LimeExpressionManager::QuestionIsRelevant($i);
                $question = LimeExpressionManager::ProcessString($testArg[3], $i, NULL, true, 1, 1);

                $jsVarName='java' . str_replace('#','_',$testArg[0]);

                $argInfo[] = array(
                'num' => $i,
                'name' => $jsVarName,
                'sgqa' => $testArg[0],
                'type' => $testArg[2],
                'question' => $question,
                'relevance' => $testArg[1],
                'relevanceStatus' => $rel
                );
                $alias2varName[$var] = array('jsName'=>$jsVarName, 'jsPart' => "'" . $var . "':'" . $jsVarName . "'");
                $alias2varName[$jsVarName] = array('jsName'=>$jsVarName, 'jsPart' => "'" . $jsVarName . "':'" . $jsVarName . "'");
                $varNameAttr[$jsVarName] = "'" . $jsVarName . "':{"
                . "'jsName':'" . $jsVarName
                . "','jsName_on':'" . $jsVarName
                . "','sgqa':'" . substr($jsVarName,4)
                . "','qid':" . $i
                . ",'gid':". 1  // ($i % 3)   // so have 3 possible group numbers
                . "}";
            }
            $LEM->alias2varName = $alias2varName;
            $LEM->varNameAttr = $varNameAttr;
            LimeExpressionManager::FinishProcessingGroup();
            LimeExpressionManager::FinishProcessingPage();


            print <<< EOD
    <script type='text/javascript'>
    <!--
    var LEMradix='.';
    function checkconditions(value, name, type, evt_type)
    {
        if (typeof evt_type === 'undefined')
        {
            evt_type = 'onchange';
        }
        ExprMgr_process_relevance_and_tailoring(evt_type,name,type);
    }
    // -->
    </script>
EOD;

            print LimeExpressionManager::GetRelevanceAndTailoringJavaScript();

            // Print Table of questions
            print "<h3>This is a test of dynamic relevance.</h3>";
            print "Enter your name and age, and try all the permutations of answers to whether you have or want children.<br />\n";
            print "Note how the text and sum of ages changes dynamically; that prior answers are remembered; and that irrelevant values are not included in the sum of ages.<br />";
            print "<table class='table' border='1'><tr><td>";
            foreach ($argInfo as $arg)
            {
                $rel = LimeExpressionManager::QuestionIsRelevant($arg['num']);
                print "<div id='question" . $arg['num'] . (($rel) ? "'" : "' style='display: none'") . ">\n";
                print "<input type='hidden' id='display" . $arg['num'] . "' name='" . $arg['num'] .  "' value='" . (($rel) ? 'on' : '') . "'/>\n";
                if ($arg['type'] == 'expr')
                {
                    // Hack for testing purposes - rather than using LimeSurvey internals to store the results of equations, process them via a hidden <div>
                    print "<div style='display: none' id='hack_" . $arg['name'] . "'>" . $arg['question'];
                    print "<input type='hidden' id='" . $arg['name'] . "' name='" . $arg['name'] . "' value=''/></div>\n";
                }
                else {
                    print "<table class='table' border='1' width='100%'>\n<tr>\n<td>[Q" . $arg['num'] . "] " . $arg['question'] . "</td>\n";
                    switch($arg['type'])
                    {
                        case 'yesno':
                        case 'text':
                            print "<td><input type='text' id='" . $arg['name'] . "' name='" . $arg['sgqa'] . "' value='' onchange='checkconditions(this.value, this.name, this.type)'/></td>\n";
                            break;
                        case 'message':
                            print "<input type='hidden' id='" . $arg['name'] . "' name='" . $arg['sgqa'] . "' value=''/>\n";
                            break;
                    }
                    print "</tr>\n</table>\n";
                }
                print "</div>\n";
            }
            print "</table>";
            LimeExpressionManager::SetDirtyFlag();  // so subsequent tests don't try to access these variables
        }

        /**
        * Set the 'this' variable as an alias for SGQA within the code.
        * @param string $sgqa
        * @return void
        */
        public static function SetThisAsAliasForSGQA($sgqa)
        {
            $LEM =& LimeExpressionManager::singleton();
            if (isset($LEM->knownVars[$sgqa]))
            {
                $LEM->qcode2sgqa['this']=$sgqa;
            }
        }

        public static function ShowStackTrace($msg=NULL,&$args=NULL)
        {
            $LEM =& LimeExpressionManager::singleton();

            $msg = array("**Stack Trace**" . (is_null($msg) ? '' : ' - ' . $msg));

            $count = 0;
            foreach (debug_backtrace(false) as $log)
            {
                if ($count++ == 0){
                    continue;   // skip this call
                }

                $subargs=array();
                if (!is_null($args) && $log['function'] == 'templatereplace') {
                    foreach ($args as $arg)
                    {
                        if (isset($log['args'][2][$arg])) {
                            $subargs[$arg] = $log['args'][2][$arg];
                        }
                    }
                    if (count($subargs) > 0) {
                        $arglist = print_r($subargs,true);
                    }
                    else {
                        $arglist = '';
                    }
                }
                else {
                    $arglist = '';
                }
                $msg[] = '  '
                .   (isset($log['file']) ? '[' . basename($log['file']) . ']': '')
                .   (isset($log['class']) ? $log['class'] : '')
                .   (isset($log['type']) ? $log['type'] : '')
                .   (isset($log['function']) ? $log['function'] : '')
                .   (isset($log['line']) ? '[' . $log['line'] . ']' : '')
                .   $arglist;
            }
        }

        /**
         * @param string $string
         */
        private function gT($string,  $escapemode = 'html')
        {
            return gT($string, $escapemode);
        }


        /**
         * @param string $sTextToTranslate
         * @param integer $number
         */
        private function ngT($sTextToTranslate, $number, $escapemode = 'html')
        {
            return ngT($sTextToTranslate, $number, $escapemode);
        }

        /**
        * Returns true if the survey is using comma as the radix
        * @return boolean
        */
        public static  function usingCommaAsRadix()
        {
            $LEM =& LimeExpressionManager::singleton();
            $usingCommaAsRadix = (($LEM->surveyOptions['radix']==',') ? true : false);
            return $usingCommaAsRadix;
        }

        private static function getConditionsForEM($surveyid, $qid=NULL)
        {
            if (!is_null($qid)) {
                $where = " c.qid = ".$qid." AND ";
            }
            else if (!is_null($surveyid)) {
                    $where = " qa.sid = {$surveyid} AND ";
                }
                else {
                    $where = "";
            }

            $query = "SELECT DISTINCT c.*, q.sid, q.type
                FROM {{conditions}} AS c
                LEFT JOIN {{questions}} q ON c.cqid=q.qid
                LEFT JOIN {{questions}} qa ON c.qid=qa.qid
                WHERE {$where} 1=1
                UNION
                SELECT DISTINCT c.*, q.sid, NULL AS TYPE
                FROM {{conditions}} AS c
                LEFT JOIN {{questions}} q ON c.cqid=q.qid
                LEFT JOIN {{questions}} qa ON c.qid=qa.qid
                WHERE {$where} c.cqid = 0";

            $databasetype = Yii::app()->db->getDriverName();
            if ($databasetype=='mssql' || $databasetype=='dblib')
            {
                $query .= " order by c.qid, scenario, cqid, cfieldname, value";
            }
            else
            {
                $query .= " order by qid, scenario, cqid, cfieldname, value";
            }

            return Yii::app()->db->createCommand($query)->query();
        }

        /**
        * Deprecate obsolete question attributes.
        * @param boolean|null $changeDB - if true, updates parameters and deletes old ones
        * @param int|null $iSurveyID - if set, then only for that survey
        * @param int|null $onlythisqid - if set, then only for this question ID
        */
        public static function UpgradeQuestionAttributes($changeDB=false,$iSurveyID=NULL,$onlythisqid=NULL)
        {
            $LEM =& LimeExpressionManager::singleton();
            if (is_null($iSurveyID))
            {
                $sQuery='SELECT sid FROM {{surveys}}';
                $aSurveyIDs = Yii::app()->db->createCommand($sQuery)->queryColumn();
            }
            else{
                $aSurveyIDs=array($iSurveyID);
            }

            $attibutemap = array(
                'max_num_value_sgqa' => 'max_num_value',
                'min_num_value_sgqa' => 'min_num_value',
                'num_value_equals_sgqa' => 'equals_num_value',
            );
            $reverseAttributeMap = array_flip($attibutemap);
            foreach ($aSurveyIDs as $iSurveyID)
            {
                $qattrs = $LEM->getQuestionAttributesForEM($iSurveyID,$onlythisqid,$_SESSION['LEMlang']);
                foreach ($qattrs as $qid => $qattr)
                {
                    $updates = array();
                    foreach ($attibutemap as $src=>$target)
                    {
                        if (isset($qattr[$src]) && trim($qattr[$src]) != '')
                        {
                            $updates[$target] = $qattr[$src];
                        }
                    }
                    if ($changeDB)
                    {
                        foreach  ($updates as $key=>$value)
                        {
                            $query = "UPDATE {{question_attributes}} SET value=".Yii::app()->db->quoteValue($value)." WHERE qid={$qid} and attribute=".Yii::app()->db->quoteValue($key);
                            Yii::app()->db->createCommand($query)->execute();
                            $query = "DELETE FROM {{question_attributes}} WHERE qid={$qid} and attribute=".Yii::app()->db->quoteValue($reverseAttributeMap[$key]);
                            Yii::app()->db->createCommand($query)->execute();

                        }
                    }
                }
            }

        }

        /**
        * Return array of language-specific answer codes
        * @param int|null $surveyid
        * @param int|null $qid
        * @param string|null $lang
        * @return array
        */
        private function getQuestionAttributesForEM($surveyid=0,$qid=0, $lang='')
        {
            // Fix old param (NULL)
            if(is_null($surveyid)) $surveyid=0;
            if(is_null($qid)) $qid=0;
            if(is_null($lang)) $lang='';
            // Fill $lang if possible
            if(!$lang && isset($_SESSION['LEMlang']))
                $lang=$_SESSION['LEMlang'];
            // Actually seem uncesserry : only one call for each page, then commented
#            static $aStaticQuestionAttributesForEM=array();
#            if(isset($aStaticQuestionAttributesForEM[$surveyid][$qid][$lang]))
#            {
#                return $aStaticQuestionAttributesForEM[$surveyid][$qid][$lang];
#            }
#            if($qid && isset($aStaticQuestionAttributesForEM[$surveyid][0][$lang]))
#            {
#                return $aStaticQuestionAttributesForEM[$surveyid][0][$lang][$qid];
#            }
            if($qid)
            {
                $oQids= Question::model()->findAll(array(
                    'select'=>'qid',
                    'group'=>'qid',
                    'distinct'=>true,
                    'condition'=>"qid=:qid and parent_qid=0",
                    'params'=>array(':qid'=>$qid)
                    ));
            }
            elseif($surveyid)
            {
                $oQids= Question::model()->findAll(array(
                    'select'=>'qid',
                    'group'=>'qid',
                    'distinct'=>true,
                    'condition'=>"sid=:sid and parent_qid=0",
                    'params'=>array(':sid'=>$surveyid)
                    ));
            }
            else
            {
                $oQids= Question::model()->findAll(array(
                    'select'=>'qid',
                    'group'=>'qid',
                    'distinct'=>true,
                    'condition'=>"parent_qid=0",
                    ));
            }
            $aQuestionAttributesForEM=array();
            foreach($oQids as $oQid)
            {
                $aAttributesValues=QuestionAttribute::model()->getQuestionAttributes($oQid->qid);
                // Change array lang to value
                foreach($aAttributesValues as &$aAttributeValue)
                {
                    if(is_array($aAttributeValue))
                    {
                        if(isset($aAttributeValue[$lang]))
                            $aAttributeValue=$aAttributeValue[$lang];
                        else
                        {
                            reset($aAttributeValue);
                            $aAttributeValue=current($aAttributeValue);
                        }
                    }
                }
                $aQuestionAttributesForEM[$oQid->qid]=$aAttributesValues;
            }
#            $aStaticQuestionAttributesForEM[$surveyid][$qid][$lang]=$aQuestionAttributesForEM;
            return $aQuestionAttributesForEM;
#            if (!is_null($qid)) {
#                $where = " a.qid = ".$qid." and a.qid=b.qid";
#            }
#            else if (!is_null($surveyid)) {
#                    $where = " a.qid=b.qid and b.sid=".$surveyid;
#                }
#                else {
#                    $where = " a.qid=b.qid";
#            }
#            if (!is_null($lang)) {
#                $lang = " and a.language='".$lang."' and b.language='".$lang."'";
#            }

#            $databasetype = Yii::app()->db->getDriverName();
#            if ($databasetype=='mssql' || $databasetype=="sqlsrv")
#            {
#                $query = "select distinct a.qid, a.attribute, CAST(a.value as varchar(max)) as value";
#            }
#            else
#            {
#                $query = "select distinct a.qid, a.attribute, a.value";
#            }

#            $query .= " from {{question_attributes}} as a, {{questions}} as b"
#            ." where " . $where
#            .$lang
#            ." order by a.qid, a.attribute";

#            $data = dbExecuteAssoc($query);
#            $qattr = array();

#            foreach($data->readAll() as $row) {
#                $qattr[$row['qid']][$row['attribute']] = $row['value'];
#            }

#            if (!is_null($lang))
#            {
#                // Then get non-language specific first, and overwrite with language-specific
#                $qattr2 = $qattr;
#                $qattr = $this->getQuestionAttributesForEM($surveyid,$qid);
#                foreach ($qattr2 as $q => $qattrs) {
#                    if (isset($qattrs) && is_array($qattrs)) {
#                        foreach ($qattrs as $attr=>$value) {
#                            $qattr[$q][$attr] = $value;
#                        }
#                    }
#                }
#            }
#            return $qattr;
        }

        /**
        * Return array of language-specific answer codes
        * @param int|null $surveyid
        * @param int|null $qid
        * @param string|null $lang
        * @return array
        */
        function getAnswerSetsForEM($surveyid=NULL,$qid=NULL,$lang=NULL)
        {
            if (!is_null($qid)) {
                $where = "a.qid = ".$qid;
            }
            else if (!is_null($surveyid)) {
                    $where = "a.qid = q.qid and q.sid = ".$surveyid;
                }
                else {
                    $where = "1";
            }
            if (!is_null($lang)) {
                $lang = " and a.language='".$lang."' and q.language='".$lang."'";
            }

            $query = "SELECT a.qid, a.code, a.answer, a.scale_id, a.assessment_value"
            ." FROM {{answers}} AS a, {{questions}} as q"
            ." WHERE ".$where
            .$lang
            ." ORDER BY a.qid, a.scale_id, a.sortorder";

            $data = dbExecuteAssoc($query);

            $qans = array();

            $useAssessments = ((isset($this->surveyOptions['assessments'])) ? $this->surveyOptions['assessments'] : false);

            foreach($data->readAll() as $row) {
                if (!isset($qans[$row['qid']])) {
                    $qans[$row['qid']] = array();
                }
                $qans[$row['qid']][$row['scale_id'].'~'.$row['code']] = ($useAssessments ? $row['assessment_value'] : '0') . '|' . $row['answer'];
            }

            return $qans;
        }

        /**
        * Returns group info needed for indexes
        * @param int $surveyid
        * @param string|null $lang
        * @return array
        */
        function getGroupInfoForEM($surveyid,$lang=NULL)
        {
            if (is_null($lang) && isset($_SESSION['LEMlang']))
            {
                $lang = $_SESSION['LEMlang'];
            }
            elseif(is_null($lang))
            {
                $lang=Survey::model()->findByPk($surveyid)->language;
            }
            $oQuestionGroups=QuestionGroup::model()->findAll(array('condition'=>"sid=:sid and language=:language",'order'=>'group_order','params'=>array(":sid"=>$surveyid,':language'=>$lang)));
            $qinfo = array();
            $_order=0;
            $gid = array();
            foreach ($oQuestionGroups as $oQuestionGroup)
            {
                $gid[$oQuestionGroup->gid] = array(
                    'group_order' => $_order,
                    'gid' =>  $oQuestionGroup->gid,
                    'group_name' => $oQuestionGroup->group_name,
                    'description' =>  $oQuestionGroup->description,
                    'grelevance' => (!($this->sPreviewMode=='question' || $this->sPreviewMode=='group')) ? $oQuestionGroup->grelevance:1,
                    'randomization_group' =>  $oQuestionGroup->randomization_group
                );
                $qinfo[$_order] = $gid[$oQuestionGroup->gid];
                ++$_order;
            }
            // Needed for Randomization group.
            $groupRemap= (!$this->sPreviewMode && !empty($_SESSION['survey_'.$surveyid]['groupReMap']) && !empty($_SESSION['survey_'.$surveyid]['grouplist']));
            if ($groupRemap)
            {
                $_order=0;
                $qinfo = array();
                foreach ($_SESSION['survey_'.$surveyid]['grouplist'] as $info)
                {
                    $gid[$info['gid']]['group_order'] = $_order;
                    $qinfo[$_order] = $gid[$info['gid']];
                    ++$_order;
                }
            }
            return $qinfo;
        }

        /**
        * Cleanse the $_POSTed data and update $_SESSION variables accordingly
        */
        static function ProcessCurrentResponses()
        {
            $LEM =& LimeExpressionManager::singleton();
            if (!isset($LEM->currentQset)) {
                return array();
            }
            $updatedValues=array();
            $radixchange = (($LEM->surveyOptions['radix']==',') ? true : false);
            foreach ($LEM->currentQset as $qinfo)
            {
                $qid = $qinfo['info']['qid'];
                $gseq = $qinfo['info']['gseq'];
                $relevant = (isset($_POST['relevance' . $qid]) ? ($_POST['relevance' . $qid] == 1) : false);
                $grelevant = (isset($_POST['relevanceG' . $gseq]) ? ($_POST['relevanceG' . $gseq] == 1) : false);
                $_SESSION[$LEM->sessid]['relevanceStatus'][$qid] = $relevant;
                $_SESSION[$LEM->sessid]['relevanceStatus']['G' . $gseq] = $grelevant;
                foreach (explode('|',$qinfo['sgqa']) as $sq)
                {
                    $sqrelevant=true;
                    if (isset($LEM->subQrelInfo[$qid][$sq]['rowdivid']))
                    {
                        $rowdivid = $LEM->subQrelInfo[$qid][$sq]['rowdivid'];
                        if ($rowdivid!='' && isset($_POST['relevance' . $rowdivid]))
                        {
                            $sqrelevant = ($_POST['relevance' . $rowdivid] == 1);
                            $_SESSION[$LEM->sessid]['relevanceStatus'][$rowdivid] = $sqrelevant;
                        }
                    }
                    $type = $qinfo['info']['type'];
                    if (($relevant && $grelevant && $sqrelevant) || !$LEM->surveyOptions['deletenonvalues'])
                    {
                        if ($qinfo['info']['hidden'] && !isset($_POST[$sq]))
                        {
                            $value = (isset($_SESSION[$LEM->sessid][$sq]) ? $_SESSION[$LEM->sessid][$sq] : '');    // if always hidden, use the default value, if any
                        }
                        else
                        {
                            $value = (isset($_POST[$sq]) ? $_POST[$sq] : '');
                        }

                        // Check for and adjust ',' and '.' in numbers
                        $isOnlyNum = isset($LEM->knownVars[$sq]['onlynum']) && $LEM->knownVars[$sq]['onlynum']=='1';
                        if ($radixchange && $isOnlyNum)
                        {
                            // Convert from comma back to decimal
                            // Also make sure to be able to convert numbers like 1.100,10
                            $value = preg_replace('|\.|', '', $value);
                            $value = preg_replace('|\,|', '.', $value);
                        }
                        elseif (!$radixchange && $isOnlyNum)
                        {
                            // Still have to remove all ',' introduced by the thousand separator
                            $value = preg_replace('|\,|', '', $value);
                        }

                        switch($type) // fix value before set it in $_SESSION : the data is reset when show it again to user.trying to save in DB : date only, but think it must be leave like it and filter oinly when save in DB
                        {
                            case 'D': //DATE

                                // Handle Arabic numerals
                                // TODO: Make a wrapper class around date converter, which constructor takes to-lang and from-lang
                                $lang = $_SESSION['LEMlang'];
                                $value = self::convertNonLatinNumerics($value, $lang);

                                $value = trim($value);
                                if ($value != "" && $value != "INVALID") {
                                    $aAttributes = $LEM->getQuestionAttributesForEM($LEM->sid, $qid,$_SESSION['LEMlang']);
                                    if (!isset($aAttributes[$qid])) {
                                        $aAttributes[$qid]=array();
                                    }
                                    $aDateFormatData = getDateFormatDataForQID($aAttributes[$qid],$LEM->surveyOptions);
                                    // We don't really validate date here : if date is invalid : return 1999-12-01 00:00
                                    // For an explanation of the exclamation mark, see this thread:
                                    // http://stackoverflow.com/questions/43740037/datetime-converts-wrong-when-system-time-is-30-march
                                    $dateTime = DateTime::createFromFormat('!' . $aDateFormatData['phpdate'], trim($value));
                                    if ($dateTime === false) {
                                        $message = sprintf(
                                            'Could not convert date %s to format %s. Please check your date format settings.',
                                            trim($value),
                                            $aDateFormatData['phpdate']
                                        );
                                        $value = "INVALID"; // This don't disable submitting survey  … ("" neither)
                                        LimeExpressionManager::addFrontendFlashMessage('error', $message, $LEM->sid);
                                    } else {
                                        $newValue = $dateTime->format("Y-m-d H:i");
                                        $newDateTime = DateTime::createFromFormat("!Y-m-d H:i", $newValue);
                                        if($value == $newDateTime->format($aDateFormatData['phpdate'])) { // control if inverse function original value
                                            $value = $newValue;
                                        } else {
                                            $value = "";// This don't disable submitting survey  … ("INVALID" neither)
                                        }
                                    }
                                }
                                break;
                            case '|': //File Upload
                                if (!preg_match('/_filecount$/', $sq))
                                {
                                    $json = $value;
                                    $phparray = json_decode($json);

                                    // if the files have not been saved already,
                                    // move the files from tmp to the files folder

                                    $tmp = $LEM->surveyOptions['tempdir'] . 'upload'. DIRECTORY_SEPARATOR;
                                    if (!is_null($phparray) && count($phparray) > 0)
                                    {
                                        // Move the (unmoved, temp) files from temp to files directory.
                                        // Check all possible file uploads
                                        for ($i = 0; $i < count($phparray); $i++)
                                        {
                                            if (file_exists($tmp . $phparray[$i]->filename))
                                            {
                                                $sDestinationFileName = 'fu_' . randomChars(15);
                                                if (!is_dir($LEM->surveyOptions['target']))
                                                {
                                                    mkdir($LEM->surveyOptions['target'], 0777, true);
                                                }
                                                if (!rename($tmp . $phparray[$i]->filename, $LEM->surveyOptions['target'] . $sDestinationFileName))
                                                {
                                                    echo "Error moving file to target destination";
                                                }
                                                $phparray[$i]->filename = $sDestinationFileName;
                                            }
                                        }
                                        $value = ls_json_encode($phparray);  // so that EM doesn't try to parse it.
                                    }
                                }
                                break;
                        }
                        $_SESSION[$LEM->sessid][$sq] = $value;
                        $_update = array (
                            'type'=>$type,
                            'value'=>$value,
                        );
                        $updatedValues[$sq] = $_update;
                        $LEM->updatedValues[$sq] = $_update;
                    }
                    else {  // irrelevant, so database will be NULLed separately
                        // Must unset the value, rather than setting to '', so that EM can re-use the default value as needed.
                        unset($_SESSION[$LEM->sessid][$sq]);
                        $_update = array (
                        'type'=>$type,
                        'value'=>NULL,
                        );
                        $updatedValues[$sq] = $_update;
                        $LEM->updatedValues[$sq] = $_update;
                    }
                }
            }
            if (isset($_POST['timerquestion']))
            {
                $_SESSION[$LEM->sessid][$_POST['timerquestion']]=sanitize_float($_POST[$_POST['timerquestion']]);
            }
            return $updatedValues;
        }

        static public function isValidVariable($varName)
        {
            $LEM =& LimeExpressionManager::singleton();
            if(isset($LEM->tempVars[$varName])) {
                return true;
            }
            if(isset($LEM->knownVars[$varName])) {
                return true;
            }
            if(isset($LEM->qcode2sgqa[$varName])) {
                return true;
            }
            return false;
        }

        /**
         * @param integer $gseq
         * @param integer $qseq
         * @param string|null $attr
         */
        static public function GetVarAttribute($name,$attr,$default,$gseq,$qseq)
        {
            $LEM =& LimeExpressionManager::singleton();
            return $LEM->_GetVarAttribute($name,$attr,$default,$gseq,$qseq);
        }

        /**
         * @param integer $gseq
         * @param integer $qseq
         */
        private function _GetVarAttribute($name,$attr,$default,$gseq,$qseq)
        {
            $args = explode(".", $name);
            $varName = $args[0];
            $varName = preg_replace("/^(?:INSERTANS:)?(.*?)$/", "$1", $varName);

            if( isset($this->tempVars[$varName]) ) {
                // Forced value
                $var = $this->tempVars[$varName];
            } elseif ( isset($this->knownVars[$varName]) ) {
                // SGQA from survey (session)
                $var = $this->knownVars[$varName];
            } elseif ( isset($this->qcode2sgqa[$varName]) ) {
                // QCODE from survey (session) or template_replace core value
                $var = $this->knownVars[$this->qcode2sgqa[$varName]];
            } else {
                return '{' . $name . '}';
            }
            $sgqa = isset($var['sgqa']) ? $var['sgqa'] : NULL;
            if (is_null($attr))
            {
                // then use the requested attribute, if any
                $_attr = 'code';
                if (preg_match("/INSERTANS:/",$args[0]))
                {
                    $_attr = 'shown';
                }
                $attr = (count($args)==2) ? $args[1] : $_attr;
            }

            // Like JavaScript, if an answer is irrelevant, always return ''
            if (preg_match('/^code|NAOK|shown|valueNAOK|value$/',$attr) && isset($var['qid']) && $var['qid']!='')
            {
                if  (!$this->_GetVarAttribute($varName,'relevanceStatus',false,$gseq,$qseq))
                {
                    return '';
                }
            }

            switch ($attr)
            {
                case 'varName':
                    return $name;
                    // NB: No break needed
                case 'code':
                case 'NAOK':
                    if (isset($var['code'])) {
                        return $var['code'];    // for static values like TOKEN
                    }
                    else {
                        if (isset($_SESSION[$this->sessid][$sgqa])) {
                            $type = $var['type'];
                            switch($type)
                            {
                                case 'Q': //MULTIPLE SHORT TEXT
                                case ';': //ARRAY (Multi Flexi) Text
                                case 'S': //SHORT FREE TEXT
                                case 'D': //DATE
                                case 'T': //LONG FREE TEXT
                                case 'U': //HUGE FREE TEXT
                                    return htmlspecialchars($_SESSION[$this->sessid][$sgqa],ENT_NOQUOTES);// Minimum sanitizing the string entered by user
                                case '!': //List - dropdown
                                case 'L': //LIST drop-down/radio-button list
                                case 'O': //LIST WITH COMMENT drop-down/radio-button list + textarea
                                case 'M': //Multiple choice checkbox
                                case 'P': //Multiple choice with comments checkbox + text
                                    if (preg_match('/comment$/',$sgqa) || preg_match('/other$/',$sgqa) || preg_match('/_other$/',$name))
                                    {
                                        return htmlspecialchars($_SESSION[$this->sessid][$sgqa],ENT_NOQUOTES);// Minimum sanitizing the string entered by user
                                    }
                                    else
                                    {
                                        return $_SESSION[$this->sessid][$sgqa];
                                    }
                                default:
                                    return $_SESSION[$this->sessid][$sgqa];
                            }
                        }
                        elseif (isset($var['default']) && !is_null($var['default'])) {
                            return $var['default'];
                        }
                        return $default;
                    }
                    // NB: No break needed
                case 'value':
                case 'valueNAOK':
                    $type = $var['type'];
                    $code = $this->_GetVarAttribute($name,'code',$default,$gseq,$qseq);
                    switch($type)
                    {
                        case '!': //List - dropdown
                        case 'L': //LIST drop-down/radio-button list
                        case 'O': //LIST WITH COMMENT drop-down/radio-button list + textarea
                        case '1': //Array (Flexible Labels) dual scale  // need scale
                        case 'H': //ARRAY (Flexible) - Column Format
                        case 'F': //ARRAY (Flexible) - Row Format
                        case 'R': //RANKING STYLE
                            if ($type == 'O' && preg_match('/comment\.value/',$name))
                            {
                                $value = $code;
                            }
                            else if (($type == 'L' || $type == '!') && preg_match('/_other\.value/',$name))
                            {
                                $value = $code;
                            }
                            else
                            {
                                $scale_id = $this->_GetVarAttribute($name,'scale_id','0',$gseq,$qseq);
                                $which_ans = $scale_id . '~' . $code;
                                $ansArray = $var['ansArray'];
                                if (is_null($ansArray))
                                {
                                    $value = $default;
                                }
                                else
                                {
                                    if (isset($ansArray[$which_ans])) {
                                        $answerInfo = explode('|',$ansArray[$which_ans]);
                                        $answer = $answerInfo[0];
                                    }
                                    else {
                                        $answer = $default;
                                    }
                                    $value = $answer;
                                }
                            }
                            break;
                        default:
                            $value = $code;
                            break;
                    }
                    return $value;
                    // NB: No break needed
                case 'jsName':
                    if ($this->surveyMode=='survey'
                    || ($this->surveyMode=='group' && $gseq != -1 && isset($var['gseq']) && $gseq == $var['gseq'])
                    || ($this->surveyMode=='question' && $qseq != -1 && isset($var['qseq']) && $qseq == $var['qseq']))
                    {
                        return (isset($var['jsName_on']) ? $var['jsName_on'] : (isset($var['jsName'])) ? $var['jsName'] : $default);
                    }
                    else {
                        return (isset($var['jsName']) ? $var['jsName'] : $default);
                    }
                    // NB: No break needed
                case 'sgqa':
                case 'mandatory':
                case 'qid':
                case 'gid':
                case 'grelevance':
                case 'question':
                case 'readWrite':
                case 'relevance':
                case 'rowdivid':
                case 'type':
                case 'qcode':
                case 'gseq':
                case 'qseq':
                case 'ansList':
                case 'scale_id':
                    return (isset($var[$attr])) ? $var[$attr] : $default;
                case 'shown':
                    if (isset($var['shown']))
                    {
                        return $var['shown'];    // for static values like TOKEN
                    }
                    else
                    {
                        $type = $var['type'];
                        $code = $this->_GetVarAttribute($name,'code',$default,$gseq,$qseq);
                        $shown = $default;  // Default value to satisfy Scrutinizer
                        switch($type)
                        {
                            case '!': //List - dropdown
                            case 'L': //LIST drop-down/radio-button list
                            case 'O': //LIST WITH COMMENT drop-down/radio-button list + textarea
                            case '1': //Array (Flexible Labels) dual scale  // need scale
                            case 'H': //ARRAY (Flexible) - Column Format
                            case 'F': //ARRAY (Flexible) - Row Format
                            case 'R': //RANKING STYLE
                                if ($type == 'O' && preg_match('/comment$/',$name))
                                {
                                    $shown = $code;
                                }
                                else if (($type == 'L' || $type == '!') && preg_match('/_other$/',$name))
                                {
                                    $shown = $code;
                                }
                                else
                                {
                                    $scale_id = $this->_GetVarAttribute($name,'scale_id','0',$gseq,$qseq);
                                    $which_ans = $scale_id . '~' . $code;
                                    $ansArray = $var['ansArray'];
                                    if (is_null($ansArray))
                                    {
                                        $shown=$code;
                                    }
                                    else
                                    {
                                        if (isset($ansArray[$which_ans])) {
                                            $answerInfo = explode('|',$ansArray[$which_ans]);
                                            array_shift($answerInfo);
                                            $answer = join('|',$answerInfo);
                                        }
                                        else {
                                            $answer = $code;
                                        }
                                        $shown = $answer;
                                    }
                                }
                                break;
                            case 'A': //ARRAY (5 POINT CHOICE) radio-buttons
                            case 'B': //ARRAY (10 POINT CHOICE) radio-buttons
                            case ':': //ARRAY (Multi Flexi) 1 to 10
                            case '5': //5 POINT CHOICE radio-buttons
                                $shown = $code;
                                break;
                            case 'D': //DATE
                                $LEM =& LimeExpressionManager::singleton();
                                $aAttributes=$LEM->getQuestionAttributesForEM($LEM->sid, $var['qid'],$_SESSION['LEMlang']);
                                $aDateFormatData=getDateFormatDataForQID($aAttributes[$var['qid']],$LEM->surveyOptions);
                                $shown='';
                                if (strtotime($code))
                                {
                                    $shown=date($aDateFormatData['phpdate'], strtotime($code));
                                }
                                break;
                            case 'N': //NUMERICAL QUESTION TYPE
                            case 'K': //MULTIPLE NUMERICAL QUESTION
                            case 'Q': //MULTIPLE SHORT TEXT
                            case ';': //ARRAY (Multi Flexi) Text
                            case 'S': //SHORT FREE TEXT
                            case 'T': //LONG FREE TEXT
                            case 'U': //HUGE FREE TEXT
                            case '*': //Equation
                            case 'I': //Language Question
                            case '|': //File Upload
                            case 'X': //BOILERPLATE QUESTION
                                $shown = $code;
                                break;
                            case 'M': //Multiple choice checkbox
                            case 'P': //Multiple choice with comments checkbox + text
                                if ($code == 'Y' && isset($var['question']) && !preg_match('/comment$/',$sgqa))
                                {
                                    $shown = $var['question'];
                                }
                                elseif (preg_match('/comment$/',$sgqa)) {
                                    $shown=$code; // This one return sgqa.code
                                }
                                else
                                {
                                    $shown = $default;
                                }
                                break;
                            case 'G': //GENDER drop-down list
                            case 'Y': //YES/NO radio-buttons
                            case 'C': //ARRAY (YES/UNCERTAIN/NO) radio-buttons
                            case 'E': //ARRAY (Increase/Same/Decrease) radio-buttons
                                $ansArray = $var['ansArray'];
                                if (is_null($ansArray))
                                {
                                    $shown=$default;
                                }
                                else
                                {
                                    if (isset($ansArray[$code])) {
                                        $answer = $ansArray[$code];
                                    }
                                    else {
                                        $answer = $default;
                                    }
                                    $shown = $answer;
                                }
                                break;
                        }
                        return $shown;
                    }
                    // NB: No break needed
                case 'relevanceStatus':
                    $gseq = (isset($var['gseq'])) ? $var['gseq'] : -1;
                    $qid = (isset($var['qid'])) ? $var['qid'] : -1;
                    $rowdivid = (isset($var['rowdivid']) && $var['rowdivid']!='') ? $var['rowdivid'] : -1;
                    if ($qid == -1 || $gseq == -1) {
                        return 1;
                    }
                    if (isset($args[1]) && $args[1]=='NAOK') {
                        return 1;
                    }
                    $grel = (isset($_SESSION[$this->sessid]['relevanceStatus']['G'.$gseq]) ? $_SESSION[$this->sessid]['relevanceStatus']['G'.$gseq] : 1);   // true by default
                    $qrel = (isset($_SESSION[$this->sessid]['relevanceStatus'][$qid]) ? $_SESSION[$this->sessid]['relevanceStatus'][$qid] : 0);
                    $sqrel = (isset($_SESSION[$this->sessid]['relevanceStatus'][$rowdivid]) ? $_SESSION[$this->sessid]['relevanceStatus'][$rowdivid] : 1);    // true by default - only want false if a subquestion is irrelevant
                    return ($grel && $qrel && $sqrel);
                    // NB: No break needed
                case 'onlynum':
                    if (isset($args[1]) && ($args[1]=='value' || $args[1]=='valueNAOK')) {
                        return 1;
                    }
                    return (isset($var[$attr])) ? $var[$attr] : $default;
                    // NB: No break needed
                default:
                    print 'UNDEFINED ATTRIBUTE: ' . $attr . "<br />\n";
                    return $default;
                    // NB: No break needed
            }
            return $default;    // and throw and error?
        }

        /**
         * @param string $op
         * @param string $name
         * @param string $value
         * @return int
         */
        public static function SetVariableValue($op,$name,$value)
        {
            $LEM =& LimeExpressionManager::singleton();

            if (isset($LEM->tempVars[$name]))
            {
                switch($op)
                {
                    case '=':
                        $LEM->tempVars[$name]['code'] = $value;
                        break;
                    case '*=':
                        $LEM->tempVars[$name]['code'] *= $value;
                        break;
                    case '/=':
                        $LEM->tempVars[$name]['code'] /= $value;
                        break;
                    case '+=':
                        $LEM->tempVars[$name]['code'] += $value;
                        break;
                    case '-=':
                        $LEM->tempVars[$name]['code'] -= $value;
                        break;
                }
                $_result = $LEM->tempVars[$name]['code'];
                $_SESSION[$LEM->sessid][$name] = $_result;
                $LEM->updatedValues[$name] = array(
                    'type'=>'*',
                    'value'=>$_result,
                );
                return $_result;
            }
            else
            {
                if (!isset($LEM->knownVars[$name]))
                {
                    if (isset($LEM->qcode2sgqa[$name]))
                    {
                        $name = $LEM->qcode2sgqa[$name];
                    }
                    else
                    {
                        return '';  // shouldn't happen
                    }
                }
                if (isset($_SESSION[$LEM->sessid][$name]))
                {
                    $_result = $_SESSION[$LEM->sessid][$name];
                }
                else
                {
                    $_result = (isset($LEM->knownVars[$name]['default']) ? $LEM->knownVars[$name]['default'] : 0);
                }

                switch($op)
                {
                    case '=':
                        $_result = $value;
                        break;
                    case '*=':
                        $_result *= $value;
                        break;
                    case '/=':
                        $_result /= $value;
                        break;
                    case '+=':
                        $_result += $value;
                        break;
                    case '-=':
                        $_result -= $value;
                        break;
                }
                $_SESSION[$LEM->sessid][$name] = $_result;
                $_type = $LEM->knownVars[$name]['type'];
                $LEM->updatedValues[$name] = array(
                    'type'=>$_type,
                    'value'=>$_result,
                );
                return $_result;
            }
        }


        /**
        * Create HTML view of the survey, showing everything that uses EM
        * @param int $sid
        * @param int|null $gid
        * @param int|null
        * @param int|null $LEMdebugLevel
        * @param boolean|null $assessments
        * @return array
        */
        static public function ShowSurveyLogicFile($sid, $gid=NULL, $qid=NULL,$LEMdebugLevel=0,$assessments=false)
        {
            // Title
            // Welcome
            // G1, name, relevance, text
            // *Q1, name [type], relevance [validation], text, help, default, help_msg
            // SQ1, name [scale], relevance [validation], text
            // A1, code, assessment_value, text
            // End Message

            $LEM =& LimeExpressionManager::singleton();
            $LEM->sPreviewMode='logic';
            $aSurveyInfo=getSurveyInfo($sid,$_SESSION['LEMlang']);
            /* All final survey string must be shown in survey language #12208 */
            Yii::app()->setLanguage(Yii::app()->session['LEMlang']);
            $allErrors = array();
            $warnings = 0;

            $surveyOptions = array(
            'assessments'=>($aSurveyInfo['assessments']=='Y'),
            'hyperlinkSyntaxHighlighting'=>true,
            );

            $varNamesUsed = array(); // keeps track of whether variables have been declared

            if (!is_null($qid))
            {
                $surveyMode='question';
                LimeExpressionManager::StartSurvey($sid, 'question', $surveyOptions, false,$LEMdebugLevel);
                $qseq = LimeExpressionManager::GetQuestionSeq($qid);
                $moveResult = LimeExpressionManager::JumpTo($qseq+1,true,false,true);
            }
            else if (!is_null($gid))
            {
                $surveyMode='group';
                LimeExpressionManager::StartSurvey($sid, 'group', $surveyOptions, false,$LEMdebugLevel);
                $gseq = LimeExpressionManager::GetGroupSeq($gid);
                $moveResult = LimeExpressionManager::JumpTo($gseq+1,true,false,true);
            }
            else
            {
                $surveyMode='survey';
                LimeExpressionManager::StartSurvey($sid, 'survey', $surveyOptions, false,$LEMdebugLevel);
                $moveResult = LimeExpressionManager::NavigateForwards();
            }
            $surveyname = viewHelper::stripTagsEM(templatereplace('{SURVEYNAME}',array('SURVEYNAME'=>$aSurveyInfo['surveyls_title'])));
            /* We now get all survey string, then reset to admin language */
            Yii::app()->setLanguage(Yii::app()->session['adminlang']);
            $qtypes=getQuestionTypeList('','array');

            if (is_null($moveResult) || is_null($LEM->currentQset) || count($LEM->currentQset) == 0) {
                return array(
                'errors'=>1,
                'html'=>sprintf($LEM->gT('Invalid question - probably missing sub-questions or language-specific settings for language %s'),$_SESSION['LEMlang'])
                );
            }


            $out = '<div id="showlogicfilediv" class="table-responsive"><h3>' . $LEM->gT('Logic File for Survey # ') . '[' . $LEM->sid . "]: $surveyname</h3>\n";
            $out .= "<table id='logicfiletable' class='table table-bordered'>";

            if (is_null($gid) && is_null($qid))
            {
                if ($aSurveyInfo['surveyls_description'] != '')
                {
                    $LEM->ProcessString($aSurveyInfo['surveyls_description'],0);
                    $sPrint= viewHelper::purified(viewHelper::filterScript($LEM->GetLastPrettyPrintExpression()));
                    $errClass = ($LEM->em->HasErrors() ? 'danger' : '');
                    $out .= "<tr class='LEMgroup $errClass'><td colspan=2>" . $LEM->gT("Description:") . "</td><td colspan=2>" . $sPrint . "</td></tr>";
                }
                if ($aSurveyInfo['surveyls_welcometext'] != '')
                {
                    $LEM->ProcessString($aSurveyInfo['surveyls_welcometext'],0);
                    $sPrint= viewHelper::purified(viewHelper::filterScript($LEM->GetLastPrettyPrintExpression()));
                    $errClass = ($LEM->em->HasErrors() ? 'danger' : '');
                    $out .= "<tr class='LEMgroup $errClass'><td colspan=2>" . $LEM->gT("Welcome:") . "</td><td colspan=2>" . $sPrint . "</td></tr>";
                }
                if ($aSurveyInfo['surveyls_endtext'] != '')
                {
                    $LEM->ProcessString($aSurveyInfo['surveyls_endtext']);
                    $sPrint= viewHelper::purified(viewHelper::filterScript($LEM->GetLastPrettyPrintExpression()));
                    $errClass = ($LEM->em->HasErrors() ? 'danger' : '');
                    $out .= "<tr class='LEMgroup $errClass'><td colspan=2>" . $LEM->gT("End message:") . "</td><td colspan=2>" . $sPrint . "</td></tr>";
                }
                if ($aSurveyInfo['surveyls_url'] != '')
                {
                    $LEM->ProcessString($aSurveyInfo['surveyls_urldescription']." - ".$aSurveyInfo['surveyls_url']);
                    $sPrint= viewHelper::purified($LEM->GetLastPrettyPrintExpression());
                    $errClass = ($LEM->em->HasErrors() ? 'danger' : '');
                    $out .= "<tr class='LEMgroup $errClass'><td colspan=2>" . $LEM->gT("End URL:") . "</td><td colspan=2>" . $sPrint . "</td></tr>";
                }
            }

            $out .= "<tr><th>#</th><th>".$LEM->gT('Name [ID]')."</th><th>".$LEM->gT('Relevance [Validation] (Default value)')."</th><th>".$LEM->gT('Text [Help] (Tip)')."</th></tr>\n";

            $_gseq=-1;
            foreach ($LEM->currentQset as $q) {
                $gseq = $q['info']['gseq'];
                $gid = $q['info']['gid'];
                $qid = $q['info']['qid'];
                $qseq = $q['info']['qseq'];

                $errorCount=0;

                //////
                // SHOW GROUP-LEVEL INFO
                //////
                if ($gseq != $_gseq) {
                    $bGroupHaveError=false;
                    $errClass='';
                    $LEM->ParseResultCache=array(); // reset for each group so get proper color coding?
                    $_gseq = $gseq;
                    $ginfo = $LEM->gseq2info[$gseq];
                    $sGroupRelevance= '{'.($ginfo['grelevance']=='' ? 1 : $ginfo['grelevance']).'}';
                    $LEM->ProcessString($sGroupRelevance, $qid,NULL,false,1,1,false,false);
                    $bGroupHaveError=$bGroupHaveError || $LEM->em->HasErrors();
                    $sGroupRelevance= viewHelper::stripTagsEM($LEM->GetLastPrettyPrintExpression());
                    $sGroupText = ((trim($ginfo['description']) == '') ? '&nbsp;' : $ginfo['description']);
                    $LEM->ProcessString($sGroupText, $qid,NULL,false,1,1,false,false);
                    $bGroupHaveError=$bGroupHaveError || $LEM->em->HasErrors();
                    $sGroupText= viewHelper::purified(viewHelper::filterScript($LEM->GetLastPrettyPrintExpression()));
                    $editlink = Yii::app()->getController()->createUrl('admin/questiongroups/sa/view/surveyid/' . $LEM->sid . '/gid/' . $gid);
                    if($bGroupHaveError)
                    {
                        $errClass='text-danger';
                    }
                    $groupRow = "<tr class='LEMgroup'>"
                    . "<td class='$errClass'>G-$gseq</td>"
                    . "<td><b>".viewHelper::flatEllipsizeText($ginfo['group_name'])."</b><br />[<a target='_blank' href='$editlink'>GID ".$gid."</a>]</td>"
                    . "<td>".$sGroupRelevance."</td>"
                    . "<td>".$sGroupText."</td>"
                    . "</tr>\n";
                    $out .= $groupRow;
                }

                //////
                // SHOW QUESTION-LEVEL INFO
                //////
                $mandatory = (($q['info']['mandatory']=='Y') ? "<span class='mandatory'>*</span>" : '');
                $type = $q['info']['type'];
                $typedesc = $qtypes[$type]['description'];

                $sgqas = explode('|',$q['sgqa']);
                if (count($sgqas) == 1 && !is_null($q['info']['default']))
                {
                    $LEM->ProcessString($q['info']['default'], $qid,NULL,false,1,1,false,false);// Default value is Y or answer code or go to input/textarea, then we can filter it
                    $_default = viewHelper::stripTagsEM($LEM->GetLastPrettyPrintExpression());
                    if ($LEM->em->HasErrors())
                    {
                        ++$errorCount;
                    }
                    $default = '<br />(' . $LEM->gT('Default:') . '  ' . $_default . ')';
                }
                else
                {
                    $default = '';
                }

                $sQuestionText = (($q['info']['qtext'] != '') ? $q['info']['qtext'] : '&nbsp');
                $LEM->ProcessString($sQuestionText, $qid,NULL,false,1,1,false,false);
                $sQuestionText = viewHelper::purified(viewHelper::filterScript($LEM->GetLastPrettyPrintExpression()));
                if ($LEM->em->HasErrors())
                {
                    ++$errorCount;
                }
                $sQuestionHelp="";
                if(trim($q['info']['help'])!="")
                {
                    $sQuestionHelp=$q['info']['help'];
                    $LEM->ProcessString($sQuestionHelp, $qid,NULL,false,1,1,false,false);
                    $sQuestionHelp = viewHelper::purified(viewHelper::filterScript($LEM->GetLastPrettyPrintExpression()));
                    if ($LEM->em->HasErrors())
                    {
                        ++$errorCount;
                    }
                    $sQuestionHelp = '<hr/>[' . $LEM->gT("Help:") . ' ' . $sQuestionHelp . ']';
                }
                $prettyValidTip = (($q['prettyValidTip'] == '') ? '' : '<hr/>(' . $LEM->gT("Tip:") . ' ' . viewHelper::stripTagsEM($q['prettyValidTip']) . ')');// Unsure need to filter

                //////
                // SHOW QUESTION ATTRIBUTES THAT ARE PROCESSED BY EM
                //////
                $attrTable = '';

                $attrs = (isset($LEM->qattr[$qid]) ? $LEM->qattr[$qid] : array());
                if (isset($LEM->q2subqInfo[$qid]['preg']))
                {
                    $attrs['regex_validation'] = $LEM->q2subqInfo[$qid]['preg'];
                }
                if (isset($LEM->questionSeq2relevance[$qseq]['other']))
                {
                    $attrs['other'] = $LEM->questionSeq2relevance[$qseq]['other'];
                }
                if (count($attrs) > 0)
                {
                    $attrTable = "<table id='logicfileattributetable'><tr><th>" . $LEM->gT("Question attribute") . "</th><th>" . $LEM->gT("Value"). "</th></tr>\n";
                    $count=0;
                    foreach ($attrs as $key=>$value) {
                        if (is_null($value) || trim($value) == '') {
                            continue;
                        }
                        switch ($key)
                        {
                            // @todo: Rather compares the current attribute value to the defaults in the question attributes array to decide which ones should show (only the ones that are non-standard)
                            default:
                            case 'exclude_all_others':
                            case 'exclude_all_others_auto':
                            case 'hidden':
                                if ($value == false || $value == '0') {
                                    $value = NULL; // so can skip this one - just using continue here doesn't work.
                                }
                                break;
                            case 'time_limit_action':
                                if ( $value == '1') {
                                    $value = NULL; // so can skip this one - just using continue here doesn't work.
                                }
                                break;
                            case 'relevance':
                                $value = NULL;  // means an outdate database structure
                                break;
                            case 'array_filter':
                            case 'array_filter_exclude':
                            case 'code_filter':
                            case 'date_max':
                            case 'date_min':
                                break;
                            case 'em_validation_q_tip':
                            case 'em_validation_sq_tip':
                            case 'equation':
                                $LEM->ProcessString($value, $qid,NULL,false,1,1,false,false);
                                $value = viewHelper::stripTagsEM($LEM->GetLastPrettyPrintExpression());
                                if ($LEM->em->HasErrors())
                                {
                                    ++$errorCount;
                                }
                                break;
                            case 'equals_num_value':
                            case 'em_validation_q':
                            case 'em_validation_sq':
                            case 'max_answers':
                            case 'max_num_value':
                            case 'max_num_value_n':
                            case 'min_answers':
                            case 'min_num_value':
                            case 'min_num_value_n':
                            case 'min_num_of_files':
                            case 'max_num_of_files':
                            case 'multiflexible_max':
                            case 'multiflexible_min':
                            case 'slider_accuracy':
                            case 'slider_min':
                            case 'slider_max':
                            case 'slider_default':
                                $value = '{' . $value . '}';
                                $LEM->ProcessString($value, $qid,NULL,false,1,1,false,false);
                                $value = viewHelper::stripTagsEM($LEM->GetLastPrettyPrintExpression());
                                if ($LEM->em->HasErrors())
                                {
                                    ++$errorCount;
                                }
                                break;
                            case 'other_replace_text':
                            case 'show_totals':
                            case 'regex_validation':
                                break;
                            case 'other':
                                if ($value == 'N') {
                                    $value = NULL; // so can skip this one
                                }
                                break;
                        }
                        if (is_null($value)) {
                            continue;   // since continuing from within a switch statement doesn't work
                        }
                        ++$count;
                        $attrTable .= "<tr><td>$key</td><td>$value</td></tr>\n";
                    }
                    $attrTable .= "</table>\n";
                    if ($count == 0) {
                        $attrTable = '';
                    }
                }

                $qdetails= $sQuestionText . $sQuestionHelp . $prettyValidTip . $attrTable;

                //////
                // SHOW RELEVANCE
                //////
                // Must parse Relevance this way, otherwise if try to first split expressions, regex equations won't work
                $relevanceEqn = (($q['info']['relevance'] == '') ? 1 : $q['info']['relevance']);
                if (!isset($LEM->ParseResultCache[$relevanceEqn]))
                {
                    $result = $LEM->em->ProcessBooleanExpression($relevanceEqn, $gseq, $qseq);
                    $prettyPrint = viewHelper::stripTagsEM($LEM->em->GetPrettyPrintString());
                    $hasErrors =  $LEM->em->HasErrors();
                    $LEM->ParseResultCache[$relevanceEqn] = array(
                    'result' => $result,
                    'prettyprint' => $prettyPrint,
                    'hasErrors' => $hasErrors,
                    );
                }
                $relevance = $LEM->ParseResultCache[$relevanceEqn]['prettyprint'];
                if ($LEM->ParseResultCache[$relevanceEqn]['hasErrors'])
                {
                    ++$errorCount;
                }

                //////
                // SHOW VALIDATION EQUATION
                //////
                // Must parse Validation this way so that regex (preg) works
                $prettyValidEqn = '';
                if ($q['prettyValidEqn'] != '') {
                    $validationEqn = $q['validEqn'];
                    if (!isset($LEM->ParseResultCache[$validationEqn]))
                    {
                        $result = $LEM->em->ProcessBooleanExpression($validationEqn, $gseq, $qseq);
                        $prettyPrint = viewHelper::stripTagsEM($LEM->em->GetPrettyPrintString());
                        $hasErrors =  $LEM->em->HasErrors();
                        $LEM->ParseResultCache[$validationEqn] = array(
                        'result' => $result,
                        'prettyprint' => $prettyPrint,
                        'hasErrors' => $hasErrors,
                        );
                    }
                    $prettyValidEqn = '<hr/>(VALIDATION: ' . $LEM->ParseResultCache[$validationEqn]['prettyprint'] . ')';
                    if ($LEM->ParseResultCache[$validationEqn]['hasErrors']) {
                        ++$errorCount;
                    }
                }

                //////
                // TEST VALIDITY OF ROOT VARIABLE NAME AND WHETHER HAS BEEN USED
                //////
                $rootVarName = $q['info']['rootVarName'];
                $varNameErrorMsg = '';
                $varNameError = NULL;
                if (isset($varNamesUsed[$rootVarName]))
                {
                    $varNameErrorMsg .= $LEM->gT('This variable name has already been used.');
                }
                else
                {
                    $varNamesUsed[$rootVarName] = array(
                    'gseq'=>$gseq,
                    'qid'=>$qid
                    );
                }

                if (!preg_match('/^[a-zA-Z][0-9a-zA-Z]*$/', $rootVarName))
                {
                    $varNameErrorMsg .= $LEM->gT('Starting in 2.05, variable names should only contain letters and numbers; and may not start with a number. This variable name is deprecated.');
                }
                if ($varNameErrorMsg != '')
                {
                    $varNameError = array (
                    'message' => $varNameErrorMsg,
                    'gseq' => $varNamesUsed[$rootVarName]['gseq'],
                    'qid' => $varNamesUsed[$rootVarName]['qid'],
                    'gid' => $gid,
                    );
                    if (!$LEM->sgqaNaming)
                    {
                        ++$errorCount;
                    }
                    else
                    {
                        ++$warnings;
                    }
                }

                //////
                // SHOW ALL SUB-QUESTIONS
                //////
                $sqRows='';
                $i=0;
                $sawThis = array(); // array of rowdivids already seen so only show them once
                foreach ($sgqas as $sgqa)
                {
                    if ($LEM->knownVars[$sgqa]['qcode'] == $rootVarName)
                    {
                        continue;   // so don't show the main question as a sub-question too
                    }
                    $rowdivid=$sgqa;
                    $varName=$LEM->knownVars[$sgqa]['qcode'];
                    switch  ($q['info']['type'])
                    {
                        case '1':
                            if (preg_match('/#1$/',$sgqa)) {
                                $rowdivid = NULL;   // so that doesn't show same message for second scale
                            }
                            else {
                                $rowdivid = substr($sgqa,0,-2); // strip suffix
                                $varName = substr($LEM->knownVars[$sgqa]['qcode'],0,-2);
                            }
                            break;
                        case 'P':
                            if (preg_match('/comment$/',$sgqa)) {
                                $rowdivid = NULL;
                            }
                            break;
                        case ':':
                        case ';':
                            $_rowdivid = $LEM->knownVars[$sgqa]['rowdivid'];
                            if (isset($sawThis[$qid . '~' . $_rowdivid])) {
                                $rowdivid = NULL;   // so don't show again
                            }
                            else {
                                $sawThis[$qid . '~' . $_rowdivid] = true;
                                $rowdivid = $_rowdivid;
                                $sgqa_len = strlen($sid . 'X'. $gid . 'X' . $qid);
                                $varName = $rootVarName . '_' . substr($_rowdivid,$sgqa_len);
                            }
                    }
                    if (is_null($rowdivid)) {
                        continue;
                    }
                    ++$i;
                    $subQeqn = '&nbsp;';
                    if (isset($LEM->subQrelInfo[$qid][$rowdivid]))
                    {
                        $sq = $LEM->subQrelInfo[$qid][$rowdivid];
                        $subQeqn = viewHelper::stripTagsEM($sq['prettyPrintEqn']);   // {' . $sq['eqn'] . '}';  // $sq['prettyPrintEqn'];
                        if ($sq['hasErrors']) {
                            ++$errorCount;
                        }
                    }

                    $sgqaInfo = $LEM->knownVars[$sgqa];
                    $subqText = $sgqaInfo['subqtext'];
                    $LEM->ProcessString($subqText, $qid,NULL,false,1,1,false,false);
                    $subqText = viewHelper::purified(viewHelper::filterScript($LEM->GetLastPrettyPrintExpression()));
                    if ($LEM->em->HasErrors()) {
                        ++$errorCount;
                    }
                    if (isset($sgqaInfo['default']) && $sgqaInfo['default'] !== '')
                    {
                        $LEM->ProcessString($sgqaInfo['default'], $qid,NULL,false,1,1,false,false);
                        $_default = viewHelper::stripTagsEM($LEM->GetLastPrettyPrintExpression());
                        if ($LEM->em->HasErrors())
                        {
                            ++$errorCount;
                        }
                        $subQeqn .= '<br />(' . $LEM->gT('Default:') . '  ' . $_default . ')';
                    }
                    $sqRows .= "<tr class='LEMsubq'>"
                    . "<td>SQ-$i</td>"
                    . "<td><b>" . $varName . "</b></td>"
                    . "<td>$subQeqn</td>"
                    . "<td>" .$subqText . "</td>"
                    . "</tr>";
                }

                //////
                // SHOW ANSWER OPTIONS FOR ENUMERATED LISTS, AND FOR MULTIFLEXI
                //////
                $answerRows='';
                if (isset($LEM->qans[$qid]) || isset($LEM->multiflexiAnswers[$qid]))
                {
                    $_scale=-1;
                    if (isset($LEM->multiflexiAnswers[$qid])) {
                        $ansList = $LEM->multiflexiAnswers[$qid];
                    }
                    else {
                        $ansList = $LEM->qans[$qid];
                    }

                    foreach ($ansList as $ans=>$value)
                    {
                        $ansInfo = explode('~',$ans);
                        $valParts = explode('|',$value);
                        $valInfo = array();
                        $valInfo[0] = array_shift($valParts);
                        $valInfo[1] = implode('|',$valParts);
                        if ($_scale != $ansInfo[0]) {
                            $i=1;
                            $_scale = $ansInfo[0];
                        }

                        $subQeqn = '';
                        $rowdivid = $sgqas[0] . $ansInfo[1];
                        if ($q['info']['type'] == 'R')
                        {
                            $rowdivid = $LEM->sid . 'X' . $gid . 'X' . $qid . $ansInfo[1];
                        }
                        if (isset($LEM->subQrelInfo[$qid][$rowdivid]))
                        {
                            $sq = $LEM->subQrelInfo[$qid][$rowdivid];
                            $subQeqn = ' ' . viewHelper::stripTagsEM($sq['prettyPrintEqn']);
                            if ($sq['hasErrors'])
                            {
                                ++$errorCount;
                            }
                        }
                        $sAnswerText=$valInfo[1];
                        $LEM->ProcessString($sAnswerText, $qid,NULL,false,1,1,false,false);
                        $sAnswerText = viewHelper::purified(viewHelper::filterScript($LEM->GetLastPrettyPrintExpression()));
                        if ($LEM->em->HasErrors()) {
                            ++$errorCount;
                        }
                        $answerRows .= "<tr class='LEManswer'>"
                        . "<td>A[" . $ansInfo[0] . "]-" . $i++ . "</td>"
                        . "<td><b>" . $ansInfo[1]. "</b></td>"
                        . "<td>[VALUE: " . $valInfo[0] . "]".$subQeqn."</td>"
                        . "<td>" . $sAnswerText . "</td>"
                        . "</tr>\n";
                    }
                }

                //////
                // FINALLY, SHOW THE QUESTION ROW(S), COLOR-CODING QUESTIONS THAT CONTAIN ERRORS
                //////
                $errclass = ($errorCount > 0) ? 'danger': '';
                $errText=($errorCount > 0) ? "<br><em class='label label-danger'>".$LEM->ngT("This question has at least {n} error.|This question has at least {n} errors.",$errorCount)."<em>" : "";
                $questionRow = "<tr class='LEMquestion'>"
                . "<td class='$errclass'>Q-" . $q['info']['qseq'] . "</td>"
                . "<td><b>" . $mandatory;

                if ($varNameErrorMsg == '')
                {
                    $editlink = Yii::app()->getController()->createUrl('admin/questions/sa/view/surveyid/' . $sid . '/gid/' . $gid . '/qid/' . $qid);
                    $questionRow .= $rootVarName;
                }
                else
                {
                    $editlink = Yii::app()->getController()->createUrl('admin/questions/sa/view/surveyid/' . $LEM->sid . '/gid/' . $varNameError['gid'] . '/qid/' . $varNameError['qid']);
                    $questionRow .= "<span class='highlighterror' title='" . $varNameError['message'] . "' "
                    . "onclick='window.open(\"$editlink\",\"_blank\")'>"
                    . $rootVarName . "</span>";
                }
                $questionRow .= "</b><br />[<a target='_blank' href='$editlink'>QID $qid</a>]<br/>$typedesc [$type] $errText</td>"
                . "<td>" . $relevance . $prettyValidEqn . $default . "</td>"
                . "<td>" . $qdetails . "</td>"
                . "</tr>\n";

                $out .= $questionRow;
                $out .= $sqRows;
                $out .= $answerRows;

                if ($errorCount > 0) {
                    $allErrors[$gid . '~' . $qid] = $errorCount;
                }
            }
            $out .= "</table>";

            LimeExpressionManager::FinishProcessingPage();
            if (($LEMdebugLevel & LEM_DEBUG_TIMING) == LEM_DEBUG_TIMING) {
                $out .= LimeExpressionManager::GetDebugTimingMessage();
            }

            if (count($allErrors) > 0) {
                $out = "<p class='alert alert-danger'>". $LEM->ngT("{n} question contains errors that need to be corrected.|{n} questions contain errors that need to be corrected.", count($allErrors)) . "</p>\n" . $out;
            }
            else {
                switch ($surveyMode)
                {
                    case 'survey':
                        $message = $LEM->gT('No syntax errors detected in this survey.');
                        break;
                    case 'group':
                        $message = $LEM->gT('This group, by itself, does not contain any syntax errors.');
                        break;
                    case 'question':
                        $message = $LEM->gT('This question, by itself, does not contain any syntax errors.');
                        break;
                    default:
                        $message = '';
                        break;
                }
                $out = "<p class='LEMheading'>$message</p>\n" . $out."</div>";
            }
            return array(
                'errors'=>$allErrors,
                'html'=>$out
            );
        }

        /**
        * TSV survey definition in format readable by TSVSurveyImport
        * one line each per group, question, sub-question, and answer
        * does not use SGQA naming at all.
        * @param int $sid
        * @return array
        */
        static public function &TSVSurveyExport($sid)
        {

            $aBaseFields = array(
                'class',
                'type/scale',
                'name',
                'relevance',
                'text',
                'help',
                'language',
                'validation',
                'mandatory',
                'other',
                'default',
                'same_default',
            );

            // Advanced question attributes : @todo get used question attribute by question in survey ?
            $aQuestionAttributes=array_keys(\ls\helpers\questionHelper::getAttributesDefinitions());
            sort($aQuestionAttributes);
            $fields=array_merge($aBaseFields,$aQuestionAttributes);

            $rows = array();
            $primarylang='en';
            $otherlangs='';

            // Export survey-level information
            $query = "select * from {{surveys}} where sid = " . $sid;
            $data = dbExecuteAssoc($query);
            foreach ($data->readAll() as $r)
            {
                foreach ($r as $key=>$value)
                {
                    if ($value != '')
                    {
                        $row = array();
                        $row['class'] = 'S';
                        $row['name'] = $key;
                        $row['text'] = $value;
                        $rows[] = $row;
                    }
                    if ($key=='language')
                    {
                        $primarylang = $value;
                    }
                    if ($key=='additional_languages')
                    {
                        $otherlangs = $value;
                    }
                }
            }
            $langs = explode(' ',$primarylang . ' ' . $otherlangs);
            $langs = array_unique($langs);

            // Export survey language settings
            $query = "select * from {{surveys_languagesettings}} where surveyls_survey_id = " . $sid;
            $data = dbExecuteAssoc($query);
            foreach ($data->readAll() as $r)
            {
                $_lang = $r['surveyls_language'];
                foreach ($r as $key=>$value)
                {
                    if ($value != '' && $key != 'surveyls_language' && $key != 'surveyls_survey_id')
                    {
                        $row = array();
                        $row['class'] = 'SL';
                        $row['name'] = $key;
                        $row['text'] = $value;
                        $row['language'] = $_lang;
                        $rows[] = $row;
                    }
                }
            }

            $surveyinfo = getSurveyInfo($sid);
            $assessments = false;
            if (isset($surveyinfo['assessments']) && $surveyinfo['assessments']=='Y')
            {
                $assessments = true;
            }

            foreach($langs as $lang)
            {
                if (trim($lang) == '')
                {
                    continue;
                }
                SetSurveyLanguage($sid,$lang);
                LimeExpressionManager::StartSurvey($sid, 'survey', array('sgqaNaming'=>'N','assessments'=>$assessments), true);
                $moveResult = LimeExpressionManager::NavigateForwards();
                $LEM =& LimeExpressionManager::singleton();

                if (is_null($moveResult) || is_null($LEM->currentQset) || count($LEM->currentQset) == 0) {
                    continue;
                }

                $_gseq=-1;
                foreach ($LEM->currentQset as $q) {
                    $gseq = $q['info']['gseq'];
                    $gid = $q['info']['gid'];
                    $qid = $q['info']['qid'];

                    //////
                    // SHOW GROUP-LEVEL INFO
                    //////
                    if ($gseq != $_gseq) {
                        $_gseq = $gseq;
                        $ginfo = $LEM->gseq2info[$gseq];

                        // if relevance equation is using SGQA coding, convert to qcoding
                        $grelevance = (($ginfo['grelevance']=='') ? 1 : $ginfo['grelevance']);
                        $LEM->em->ProcessBooleanExpression($grelevance, $gseq, 0);    // $qseq
                        $grelevance = trim(strip_tags($LEM->em->GetPrettyPrintString()));
                        $gtext = ((trim($ginfo['description']) == '') ? '' : $ginfo['description']);

                        $row = array();
                        $row['class'] = 'G';
                        //create a group code to allow proper importing of multi-lang survey TSVs
                        $row['type/scale']='G'.$gseq;
                        $row['name'] = $ginfo['group_name'];
                        $row['relevance'] = $grelevance;
                        $row['text'] = $gtext;
                        $row['language'] = $lang;
                        $row['random_group'] = $ginfo['randomization_group'];
                        $rows[] = $row;
                    }

                    //////
                    // SHOW QUESTION-LEVEL INFO
                    //////
                    $row = array();

                    $mandatory = (($q['info']['mandatory']=='Y') ? 'Y' : '');
                    $type = $q['info']['type'];

                    $sgqas = explode('|',$q['sgqa']);
                    if (count($sgqas) == 1 && !is_null($q['info']['default']))
                    {
                        $default = $q['info']['default'];
                    }
                    else
                    {
                        $default = '';
                    }

                    $qtext = (($q['info']['qtext'] != '') ? $q['info']['qtext'] : '');
                    $help = (($q['info']['help'] != '') ? $q['info']['help']: '');

                    //////
                    // SHOW QUESTION ATTRIBUTES THAT ARE PROCESSED BY EM
                    //////
                    if (isset($LEM->qattr[$qid]) && count($LEM->qattr[$qid]) > 0) {
                        foreach ($LEM->qattr[$qid] as $key=>$value) {
                            if (is_null($value) || trim($value) == '') {
                                continue;
                            }
                            switch ($key)
                            {
                                default:
                                case 'exclude_all_others':
                                case 'exclude_all_others_auto':
                                case 'hidden':
                                    if ($value == false || $value == '0') {
                                        $value = NULL; // so can skip this one - just using continue here doesn't work.
                                    }
                                    break;
                                case 'relevance':
                                    $value = NULL;  // means an outdate database structure
                                    break;
                            }
                            if (is_null($value) || trim($value) == '') {
                                continue;   // since continuing from within a switch statement doesn't work
                            }
                            $row[$key] = $value;
                        }
                    }

                    // if relevance equation is using SGQA coding, convert to qcoding
                    $relevanceEqn = (($q['info']['relevance'] == '') ? 1 : $q['info']['relevance']);
                    $LEM->em->ProcessBooleanExpression($relevanceEqn, $gseq, $q['info']['qseq']);    // $qseq
                    $relevanceEqn = trim(preg_replace("#</(span|a)>#i", "", preg_replace("#<(span|a)[^>]+\>#i", "", $LEM->em->GetPrettyPrintString()))); // Relevance can not have HTML : only span and a are returned from GetPrettyPrintString
                    $rootVarName = $q['info']['rootVarName'];
                    $preg = '';
                    if (isset($LEM->q2subqInfo[$q['info']['qid']]['preg']))
                    {
                        $preg = $LEM->q2subqInfo[$q['info']['qid']]['preg'];
                        if (is_null($preg))
                        {
                            $preg = '';
                        }
                    }

                    $row['class'] = 'Q';
                    $row['type/scale'] = $type;
                    $row['name'] = $rootVarName;
                    $row['relevance'] = $relevanceEqn;
                    $row['text'] = $qtext;
                    $row['help'] = $help;
                    $row['language'] = $lang;
                    $row['validation'] = $preg;
                    $row['mandatory'] = $mandatory;
                    $row['other'] = $q['info']['other'];
                    $row['default'] = $default;
                    $row['same_default'] = 1;   // TODO - need this: $q['info']['same_default'];

                    $rows[] = $row;

                    //////
                    // SHOW ALL SUB-QUESTIONS
                    //////
                    $sawThis = array(); // array of rowdivids already seen so only show them once
                    foreach ($sgqas as $sgqa)
                    {
                        if ($LEM->knownVars[$sgqa]['qcode'] == $rootVarName) {
                            continue;   // so don't show the main question as a sub-question too
                        }
                        $rowdivid=$sgqa;
                        $varName=$LEM->knownVars[$sgqa]['qcode'];

                        // if SQrelevance equation is using SGQA coding, convert to qcoding
                        $SQrelevance = (($LEM->knownVars[$sgqa]['SQrelevance'] == '') ? 1 : $LEM->knownVars[$sgqa]['SQrelevance']);
                        $LEM->em->ProcessBooleanExpression($SQrelevance, $gseq, $q['info']['qseq']);
                        $SQrelevance = trim(preg_replace("#</(span|a)>#i", "", preg_replace("#<(span|a)[^>]+\>#i", "", $LEM->em->GetPrettyPrintString()))); // Relevance can not have HTML : only span and a are returned from GetPrettyPrintString

                        switch  ($q['info']['type'])
                        {
                            case '1':
                                if (preg_match('/#1$/',$sgqa)) {
                                    $rowdivid = NULL;   // so that doesn't show same message for second scale
                                }
                                else {
                                    $rowdivid = substr($sgqa,0,-2); // strip suffix
                                    $varName = substr($LEM->knownVars[$sgqa]['qcode'],0,-2);
                                }
                                break;
                            case 'P':
                                if (preg_match('/comment$/',$sgqa)) {
                                    $rowdivid = NULL;
                                }
                                break;
                            case ':':
                            case ';':
                                $_rowdivid = $LEM->knownVars[$sgqa]['rowdivid'];
                                if (isset($sawThis[$qid . '~' . $_rowdivid])) {
                                    $rowdivid = NULL;   // so don't show again
                                }
                                else {
                                    $sawThis[$qid . '~' . $_rowdivid] = true;
                                    $rowdivid = $_rowdivid;
                                    $sgqa_len = strlen($sid . 'X'. $gid . 'X' . $qid);
                                    $varName = $rootVarName . '_' . substr($_rowdivid,$sgqa_len);
                                }
                                break;
                        }
                        if (is_null($rowdivid)) {
                            continue;
                        }

                        $sgqaInfo = $LEM->knownVars[$sgqa];
                        $subqText = $sgqaInfo['subqtext'];

                        if (isset($sgqaInfo['default']))
                        {
                            $default = $sgqaInfo['default'];
                        }
                        else
                        {
                            $default = '';
                        }

                        $row = array();
                        $row['class'] = 'SQ';
                        $row['type/scale'] = 0;
                        $row['name'] = substr($varName,strlen($rootVarName)+1);
                        $row['relevance'] = $SQrelevance;
                        $row['text'] = $subqText;
                        $row['language'] = $lang;
                        $row['default'] = $default;
                        $rows[] = $row;
                    }

                    //////
                    // SHOW ANSWER OPTIONS FOR ENUMERATED LISTS, AND FOR MULTIFLEXI
                    //////
                    if (isset($LEM->qans[$qid]) || isset($LEM->multiflexiAnswers[$qid]))
                    {
                        $_scale=-1;
                        if (isset($LEM->multiflexiAnswers[$qid])) {
                            $ansList = $LEM->multiflexiAnswers[$qid];
                        }
                        else {
                            $ansList = $LEM->qans[$qid];
                        }
                        foreach ($ansList as $ans=>$value)
                        {
                            $ansInfo = explode('~',$ans);
                            $valParts = explode('|',$value);
                            $valInfo = array();
                            $valInfo[0] = array_shift($valParts);
                            $valInfo[1] = implode('|',$valParts);
                            if ($_scale != $ansInfo[0]) {
                                $_scale = $ansInfo[0];
                            }

                            $row = array();
                            if ($type == ':' || $type == ';')
                            {
                                $row['class'] = 'SQ';
                            }
                            else
                            {
                                $row['class'] = 'A';
                            }
                            $row['type/scale'] = $_scale;
                            $row['name'] = $ansInfo[1];
                            $row['relevance'] = $assessments==true ? $valInfo[0] : '';
                            $row['text'] = $valInfo[1];
                            $row['language'] = $lang;
                            $rows[] = $row;
                        }
                    }
                }
            }
            // Now generate the array out output data
            $out = array();
            $out[] = $fields;

            foreach ($rows as $row)
            {
                $tsv = array();
                foreach ($fields as $field)
                {
                    $val = (isset($row[$field]) ? $row[$field] : '');
                    $tsv[] = $val;
                }
                $out[] = $tsv;
            }
            return $out;
        }

        /**
        * Returns the survey ID of the EM singleton
        * @return int
        */
        public static function getLEMsurveyId() {
                $LEM =& LimeExpressionManager::singleton();
                return $LEM->sid;
        }

       /**
         * This function loads the relevant data about tokens for a survey.
         * If specific token is not given it loads empty values, this is used for
         * question previewing and the like.
         *
         * @param int $iSurveyId
         * @param string|null $sToken
         * @param boolean|null $bAnonymize
         * @return void
         */
        public function loadTokenInformation($iSurveyId, $sToken = null, $bAnonymize = false)
        {
            if (!Survey::model()->hasTokens($iSurveyId))
            {
                return;
            }
            if ($sToken === null && isset($_SESSION[$this->sessid]['token']))
            {
                $sToken = $_SESSION[$this->sessid]['token'];
            }

            $oToken = Token::model($iSurveyId)->findByAttributes(array(
                'token' => $sToken
            ));

            if ($oToken)
            {
                foreach ($oToken->attributes as $attribute => $value)
                {
                    if ($bAnonymize)
                    {
                        $value = "";
                    }
                    $this->knownVars["TOKEN:" . strtoupper($attribute)] = array(
                        'code'=>$value,
                        'jsName_on'=>'',
                        'jsName'=>'',
                        'readWrite'=>'N',
                    );
                }
            }
            else
            {
                // Read list of available tokens from the tokens table so that preview and error checking works correctly
                $blankVal = array(
                    'code'=>'',
                    'jsName_on'=>'',
                    'jsName'=>'',
                    'readWrite'=>'N',
                );
                foreach (Token::model($iSurveyId)->tableSchema->columnNames as $attribute)
                {
                    $this->knownVars['TOKEN:' . strtoupper($attribute)] = $blankVal;
                }
            }
        }

        /**
         * Add a flash message to state-key 'frontend{survey id}'
         * The flash messages are templatereplaced in startpage.tstpl, {FLASHMESSAGE}
         *
         * @param string $type Yii type of flash: `error`, `notice`, 'success'
         * @param string $message
         * @param int $surveyid
         * @return void
         */
        public static function addFrontendFlashMessage($type, $message, $surveyid) {
            $originalPrefix = Yii::app()->user->getStateKeyPrefix();
            Yii::app()->user->setStateKeyPrefix('frontend' . $surveyid);
            Yii::app()->user->setFlash($type, $message);
            Yii::app()->user->setStateKeyPrefix($originalPrefix);
        }

        /**
         * Convert non-latin numerics in string to latin numerics
         * Used for datepicker (Hindi, Arabic numbers)
         *
         * @param string str
         * @param string lang
         * @return string
         */
        public static function convertNonLatinNumerics($str, $lang)
        {
            $result = $str;

            $standard = array("0","1","2","3","4","5","6","7","8","9");

            if ($lang == 'ar')
            {
                $eastern_arabic_symbols = array("٠","١","٢","٣","٤","٥","٦","٧","٨","٩");
                $result = str_replace($eastern_arabic_symbols, $standard, $str);
            }
            else if ($lang == 'fa')
            {
                // NOTE: NOT the same UTF-8 letters as array above (Arabic)
                $extended_arabic_indic = array("۰","۱","۲","۳","۴","۵","۶","۷","۸","۹");
                $result = str_replace($extended_arabic_indic, $standard, $str);
            }
            else if ($lang == 'hi')
            {
                $hindi_symbols = array("०","१","२","३","४","५","६","७","८","९");
                $result = str_replace($hindi_symbols, $standard, $str);
            }

            return $result;
        }

        /**
         * Set currentQset. Used by unit-tests.
         * @param array $val
         * @return void
         */
        public function setCurrentQset(array $val)
        {
            $this->currentQset = $val;
        }

        /**
         * Used for unit tests.
         * @param mixed $val
         * @return void
         */
        public function setKnownVars($val) {
            $this->knownVars = $val;
        }

        /**
         * Used for unit tests.
         * @param mixed $info
         * @return void
         */
        public function setPageRelevanceInfo($info)
        {
            $this->pageRelevanceInfo = $info;
        }

    }

    /**
    * Used by usort() to order $this->questionSeq2relevance in proper order
    * @param array $a
    * @param array $b
    * @return int
    */
    function cmpQuestionSeq($a, $b)
    {
        if (is_null($a['qseq'])) {
            if (is_null($b['qseq'])) {
                return 0;
            }
            return 1;
        }
        if (is_null($b['qseq'])) {
            return -1;
        }
        if ($a['qseq'] == $b['qseq']) {
            return 0;
        }
        return ($a['qseq'] < $b['qseq']) ? -1 : 1;
    }


?>
