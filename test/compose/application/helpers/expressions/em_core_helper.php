<?php
/**
 * LimeSurvey
 * Copyright (C) 2007-2013 The LimeSurvey Project Team / Carsten Schmitz
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
 * Description of ExpressionManager
 * (1) Does safe evaluation of PHP expressions.  Only registered Functions, and known Variables are allowed.
 *   (a) Functions include any math, string processing, conditional, formatting, etc. functions
 * (2) This class replaces LimeSurvey's <= 1.91+  process of resolving strings that contain LimeReplacementFields
 *   (a) String is split by expressions (by curly braces, but safely supporting strings and escaped curly braces)
 *   (b) Expressions (things surrounded by curly braces) are evaluated - thereby doing LimeReplacementField substitution and/or more complex calculations
 *   (c) Non-expressions are left intact
 *   (d) The array of stringParts are re-joined to create the desired final string.
 * (3) The core of Expression Manager is a Recursive Descent Parser (RDP), based off of one build via JavaCC by TMSWhite in 1999.
 *   (a) Functions that start with RDP_ should not be touched unless you really understand compiler design.
 *
 * @author LimeSurvey Team (limesurvey.org)
 * @author Thomas M. White (TMSWhite)
 */

class ExpressionManager {
    // These are the allowable suffixes for variables - each represents an attribute of a variable.
    public static $RDP_regex_var_attr = 'code|gid|grelevance|gseq|jsName|mandatory|NAOK|qid|qseq|question|readWrite|relevanceStatus|relevance|rowdivid|sgqa|shown|type|valueNAOK|value';

    // These three variables are effectively static once constructed
    private $RDP_ExpressionRegex;
    private $RDP_TokenType;
    private $RDP_TokenizerRegex;
    private $RDP_CategorizeTokensRegex;
    private $RDP_ValidFunctions; // names and # params of valid functions

    // Thes variables are used while  processing the equation
    private $RDP_expr;  // the source expression
    private $RDP_tokens;    // the list of generated tokens
    private $RDP_count; // total number of $RDP_tokens
    private $RDP_pos;   // position within the $token array while processing equation
    private $RDP_errs;    // array of syntax errors
    private $RDP_onlyparse;
    private $RDP_stack; // stack of intermediate results
    private $RDP_result;    // final result of evaluating the expression;
    private $RDP_evalStatus;    // true if $RDP_result is a valid result, and  there are no serious errors
    private $varsUsed;  // list of variables referenced in the equation

    // These  variables are only used by sProcessStringContainingExpressions
    private $allVarsUsed;   // full list of variables used within the string, even if contains multiple expressions
    private $prettyPrintSource; // HTML formatted output of running sProcessStringContainingExpressions
    private $substitutionNum; // Keeps track of number of substitions performed XXX

    /**
     * @var array
     */
    private $substitutionInfo; // array of JavaScripts to managing dynamic substitution
    private $jsExpression;  // caches computation of JavaScript equivalent for an Expression

    private $questionSeq;   // sequence order of question - so can detect if try to use variable before it is set
    private $groupSeq;  // sequence order of groups - so can detect if try to use variable before it is set
    private $surveyMode='group';

    // The following are only needed to enable click on variable names within pretty print and open new window to edit them
    private $sid=NULL; // the survey ID
    private $hyperlinkSyntaxHighlighting=true;  // TODO - change this back to false
    private $sgqaNaming=false;

    function __construct()
    {
        /* EM core string must be in adminlang : keep the actual for resetting at end. See bug #12208 */
        /**
         * @var string|null $baseLang set the previous language if need to be set
         */
        $baseLang=null;
        if(Yii::app() instanceof CWebApplication && Yii::app()->session['adminlang']){
            $baseLang=Yii::app()->getLanguage();
            Yii::app()->setLanguage(Yii::app()->session['adminlang']);
        }
        // List of token-matching regular expressions
        // Note, this is effectively a Lexer using Regular Expressions.  Don't change this unless you understand compiler design.
        $RDP_regex_dq_string = '(?<!\\\\)".*?(?<!\\\\)"';
        $RDP_regex_sq_string = '(?<!\\\\)\'.*?(?<!\\\\)\'';
        $RDP_regex_whitespace = '\s+';
        $RDP_regex_lparen = '\(';
        $RDP_regex_rparen = '\)';
        $RDP_regex_comma = ',';
        $RDP_regex_not = '!';
        $RDP_regex_inc_dec = '\+\+|--';
        $RDP_regex_binary = '[+*/-]';
        $RDP_regex_compare = '<=|<|>=|>|==|!=|\ble\b|\blt\b|\bge\b|\bgt\b|\beq\b|\bne\b';
        $RDP_regex_assign = '=';    // '=|\+=|-=|\*=|/=';
        $RDP_regex_sgqa = '(?:INSERTANS:)?[0-9]+X[0-9]+X[0-9]+[A-Z0-9_]*\#?[01]?(?:\.(?:' . ExpressionManager::$RDP_regex_var_attr . '))?';
        $RDP_regex_word = '(?:TOKEN:)?(?:[A-Z][A-Z0-9_]*)?(?:\.(?:[A-Z][A-Z0-9_]*))*(?:\.(?:' . ExpressionManager::$RDP_regex_var_attr . '))?';
        $RDP_regex_number = '[0-9]+\.?[0-9]*|\.[0-9]+';
        $RDP_regex_andor = '\band\b|\bor\b|&&|\|\|';
        $RDP_regex_lcb = '{';
        $RDP_regex_rcb = '}';
        $RDP_regex_sq = '\'';
        $RDP_regex_dq= '"';
        $RDP_regex_bs = '\\\\';

        $RDP_StringSplitRegex = array(
            $RDP_regex_lcb,
            $RDP_regex_rcb,
            $RDP_regex_sq,
            $RDP_regex_dq,
            $RDP_regex_bs,
        );

        // RDP_ExpressionRegex is the regular expression that splits apart strings that contain curly braces in order to find expressions
        $this->RDP_ExpressionRegex =  '#(' . implode('|',$RDP_StringSplitRegex) . ')#i';

        // asTokenRegex and RDP_TokenType must be kept in sync  (same number and order)
        $RDP_TokenRegex = array(
            $RDP_regex_dq_string,
            $RDP_regex_sq_string,
            $RDP_regex_whitespace,
            $RDP_regex_lparen,
            $RDP_regex_rparen,
            $RDP_regex_comma,
            $RDP_regex_andor,
            $RDP_regex_compare,
            $RDP_regex_sgqa,
            $RDP_regex_word,
            $RDP_regex_number,
            $RDP_regex_not,
            $RDP_regex_inc_dec,
            $RDP_regex_assign,
            $RDP_regex_binary,
            );

        $this->RDP_TokenType = array(
            'DQ_STRING',
            'SQ_STRING',
            'SPACE',
            'LP',
            'RP',
            'COMMA',
            'AND_OR',
            'COMPARE',
            'SGQA',
            'WORD',
            'NUMBER',
            'NOT',
            'OTHER',
            'ASSIGN',
            'BINARYOP',
           );

        // $RDP_TokenizerRegex - a single regex used to split and equation into tokens
        $this->RDP_TokenizerRegex = '#(' . implode('|',$RDP_TokenRegex) . ')#i';

        // $RDP_CategorizeTokensRegex - an array of patterns so can categorize the type of token found - would be nice if could get this from preg_split
        // Adding ability to capture 'OTHER' type, which indicates an error - unsupported syntax element
        $this->RDP_CategorizeTokensRegex = preg_replace("#^(.*)$#","#^$1$#i",$RDP_TokenRegex);
        $this->RDP_CategorizeTokensRegex[] = '/.+/';
        $this->RDP_TokenType[] = 'OTHER';
        // Each allowed function is a mapping from local name to external name + number of arguments
        // Functions can have a list of serveral allowable #s of arguments.
        // If the value is -1, the function must have a least one argument but can have an unlimited number of them
        // -2 means that at least one argument is required.  -3 means at least two arguments are required, etc.
        $this->RDP_ValidFunctions = array(
'abs' => array('abs', 'Math.abs', gT('Absolute value'), 'number abs(number)', 'http://php.net/abs', 1),
'acos' => array('acos', 'Math.acos', gT('Arc cosine'), 'number acos(number)', 'http://php.net/acos', 1),
'addslashes' => array('addslashes', gT('addslashes'), 'Quote string with slashes', 'string addslashes(string)', 'http://php.net/addslashes', 1),
'asin' => array('asin', 'Math.asin', gT('Arc sine'), 'number asin(number)', 'http://php.net/asin', 1),
'atan' => array('atan', 'Math.atan', gT('Arc tangent'), 'number atan(number)', 'http://php.net/atan', 1),
'atan2' => array('atan2', 'Math.atan2', gT('Arc tangent of two variables'), 'number atan2(number, number)', 'http://php.net/atan2', 2),
'ceil' => array('ceil', 'Math.ceil', gT('Round fractions up'), 'number ceil(number)', 'http://php.net/ceil', 1),
'checkdate' => array('checkdate', 'checkdate', gT('Returns true(1) if it is a valid date in gregorian calendar'), 'bool checkdate(month,day,year)', 'http://php.net/checkdate', 3),
'cos' => array('cos', 'Math.cos', gT('Cosine'), 'number cos(number)', 'http://php.net/cos', 1),
'count' => array('exprmgr_count', 'LEMcount', gT('Count the number of answered questions in the list'), 'number count(arg1, arg2, ... argN)', '', -1),
'countif' => array('exprmgr_countif', 'LEMcountif', gT('Count the number of answered questions in the list equal the first argument'), 'number countif(matches, arg1, arg2, ... argN)', '', -2),
'countifop' => array('exprmgr_countifop', 'LEMcountifop', gT('Count the number of answered questions in the list which pass the critiera (arg op value)'), 'number countifop(op, value, arg1, arg2, ... argN)', '', -3),
'date' => array('date', 'date', gT('Format a local date/time'), 'string date(format [, timestamp=time()])', 'http://php.net/date', 1,2),
'exp' => array('exp', 'Math.exp', gT('Calculates the exponent of e'), 'number exp(number)', 'http://php.net/exp', 1),
'fixnum' => array('exprmgr_fixnum', 'LEMfixnum', gT('Display numbers with comma as decimal separator, if needed'), 'string fixnum(number)', '', 1),
'floor' => array('floor', 'Math.floor', gT('Round fractions down'), 'number floor(number)', 'http://php.net/floor', 1),
'gmdate' => array('gmdate', 'gmdate', gT('Format a GMT date/time'), 'string gmdate(format [, timestamp=time()])', 'http://php.net/gmdate', 1,2),
'html_entity_decode' => array('html_entity_decode', 'html_entity_decode', gT('Convert all HTML entities to their applicable characters (always uses ENT_QUOTES and UTF-8)'), 'string html_entity_decode(string)', 'http://php.net/html-entity-decode', 1),
'htmlentities' => array('htmlentities', 'htmlentities', gT('Convert all applicable characters to HTML entities (always uses ENT_QUOTES and UTF-8)'), 'string htmlentities(string)', 'http://php.net/htmlentities', 1),
'htmlspecialchars' => array('expr_mgr_htmlspecialchars', 'htmlspecialchars', gT('Convert special characters to HTML entities (always uses ENT_QUOTES and UTF-8)'), 'string htmlspecialchars(string)', 'http://php.net/htmlspecialchars', 1),
'htmlspecialchars_decode' => array('expr_mgr_htmlspecialchars_decode', 'htmlspecialchars_decode', gT('Convert special HTML entities back to characters (always uses ENT_QUOTES and UTF-8)'), 'string htmlspecialchars_decode(string)', 'http://php.net/htmlspecialchars-decode', 1),
'idate' => array('idate', 'idate', gT('Format a local time/date as integer'), 'string idate(string [, timestamp=time()])', 'http://php.net/idate', 1,2),
'if' => array('exprmgr_if', 'LEMif', gT('Conditional processing'), 'if(test,result_if_true,result_if_false)', '', 3),
'implode' => array('exprmgr_implode', 'LEMimplode', gT('Join array elements with a string'), 'string implode(glue,arg1,arg2,...,argN)', 'http://php.net/implode', -2),
'intval' => array('intval', 'LEMintval', gT('Get the integer value of a variable'), 'int intval(number [, base=10])', 'http://php.net/intval', 1,2),
'is_empty' => array('exprmgr_empty', 'LEMempty', gT('Determine whether a variable is considered to be empty'), 'bool is_empty(var)', 'http://php.net/empty', 1),
'is_float' => array('is_float', 'LEMis_float', gT('Finds whether the type of a variable is float'), 'bool is_float(var)', 'http://php.net/is-float', 1),
'is_int' => array('exprmgr_int', 'LEMis_int', gT('Check if the content of a variable is a valid integer value'), 'bool is_int(var)', 'http://php.net/is-int', 1),
'is_nan' => array('is_nan', 'isNaN', gT('Finds whether a value is not a number'), 'bool is_nan(var)', 'http://php.net/is-nan', 1),
'is_null' => array('is_null', 'LEMis_null', gT('Finds whether a variable is NULL'), 'bool is_null(var)', 'http://php.net/is-null', 1),
'is_numeric' => array('is_numeric', 'LEMis_numeric', gT('Finds whether a variable is a number or a numeric string'), 'bool is_numeric(var)', 'http://php.net/is-numeric', 1),
'is_string' => array('is_string', 'LEMis_string', gT('Find whether the type of a variable is string'), 'bool is_string(var)', 'http://php.net/is-string', 1),
'join' => array('exprmgr_join', 'LEMjoin', gT('Join strings, return joined string.This function is an alias of implode("",argN)'), 'string join(arg1,arg2,...,argN)', '', -1),
'list' => array('exprmgr_list', 'LEMlist', gT('Return comma-separated list of values'), 'string list(arg1, arg2, ... argN)', '', -2),
'log' => array('exprmgr_log', 'LEMlog', gT('The logarithm of number to base, if given, or the natural logarithm. '), 'number log(number,base=e)', 'http://php.net/log', -2),
'ltrim' => array('ltrim', 'ltrim', gT('Strip whitespace (or other characters) from the beginning of a string'), 'string ltrim(string [, charlist])', 'http://php.net/ltrim', 1,2),
'max' => array('max', 'Math.max', gT('Find highest value'), 'number max(arg1, arg2, ... argN)', 'http://php.net/max', -2),
'min' => array('min', 'Math.min', gT('Find lowest value'), 'number min(arg1, arg2, ... argN)', 'http://php.net/min', -2),
'mktime' => array('exprmgr_mktime', 'mktime', gT('Get UNIX timestamp for a date (each of the 6 arguments are optional)'), 'number mktime([hour [, minute [, second [, month [, day [, year ]]]]]])', 'http://php.net/mktime', 0,1,2,3,4,5,6),
'nl2br' => array('nl2br', 'nl2br', gT('Inserts HTML line breaks before all newlines in a string'), 'string nl2br(string)', 'http://php.net/nl2br', 1,1),
'number_format' => array('number_format', 'number_format', gT('Format a number with grouped thousands'), 'string number_format(number)', 'http://php.net/number-format', 1),
'pi' => array('pi', 'LEMpi', gT('Get value of pi'), 'number pi()', '', 0),
'pow' => array('pow', 'Math.pow', gT('Exponential expression'), 'number pow(base, exp)', 'http://php.net/pow', 2),
'quoted_printable_decode' => array('quoted_printable_decode', 'quoted_printable_decode', gT('Convert a quoted-printable string to an 8 bit string'), 'string quoted_printable_decode(string)', 'http://php.net/quoted-printable-decode', 1),
'quoted_printable_encode' => array('quoted_printable_encode', 'quoted_printable_encode', gT('Convert a 8 bit string to a quoted-printable string'), 'string quoted_printable_encode(string)', 'http://php.net/quoted-printable-encode', 1),
'quotemeta' => array('quotemeta', 'quotemeta', gT('Quote meta characters'), 'string quotemeta(string)', 'http://php.net/quotemeta', 1),
'rand' => array('rand', 'rand', gT('Generate a random integer'), 'int rand() OR int rand(min, max)', 'http://php.net/rand', 0,2),
'regexMatch' => array('exprmgr_regexMatch', 'LEMregexMatch', gT('Compare a string to a regular expression pattern'), 'bool regexMatch(pattern,input)', '', 2),
'round' => array('round', 'round', gT('Rounds a number to an optional precision'), 'number round(val [, precision])', 'http://php.net/round', 1,2),
'rtrim' => array('rtrim', 'rtrim', gT('Strip whitespace (or other characters) from the end of a string'), 'string rtrim(string [, charlist])', 'http://php.net/rtrim', 1,2),
'sin' => array('sin', 'Math.sin', gT('Sine'), 'number sin(arg)', 'http://php.net/sin', 1),
'sprintf' => array('sprintf', 'sprintf', gT('Return a formatted string'), 'string sprintf(format, arg1, arg2, ... argN)', 'http://php.net/sprintf', -2),
'sqrt' => array('sqrt', 'Math.sqrt', gT('Square root'), 'number sqrt(arg)', 'http://php.net/sqrt', 1),
'stddev' => array('exprmgr_stddev', 'LEMstddev', gT('Calculate the Sample Standard Deviation for the list of numbers'), 'number stddev(arg1, arg2, ... argN)', '', -2),
'str_pad' => array('str_pad', 'str_pad', gT('Pad a string to a certain length with another string'), 'string str_pad(input, pad_length [, pad_string])', 'http://php.net/str-pad', 2,3),
'str_repeat' => array('str_repeat', 'str_repeat', gT('Repeat a string'), 'string str_repeat(input, multiplier)', 'http://php.net/str-repeat', 2),
'str_replace' => array('str_replace', 'LEMstr_replace', gT('Replace all occurrences of the search string with the replacement string'), 'string str_replace(search,  replace, subject)', 'http://php.net/str-replace', 3),
'strcasecmp' => array('strcasecmp', 'strcasecmp', gT('Binary safe case-insensitive string comparison'), 'int strcasecmp(str1, str2)', 'http://php.net/strcasecmp', 2),
'strcmp' => array('strcmp', 'strcmp', gT('Binary safe string comparison'), 'int strcmp(str1, str2)', 'http://php.net/strcmp', 2),
'strip_tags' => array('strip_tags', 'strip_tags', gT('Strip HTML and PHP tags from a string'), 'string strip_tags(str, allowable_tags)', 'http://php.net/strip-tags', 1,2),
'stripos' => array('exprmgr_stripos', 'stripos', gT('Find position of first occurrence of a case-insensitive string'), 'int stripos(haystack, needle [, offset=0])', 'http://php.net/stripos', 2,3),
'stripslashes' => array('stripslashes', 'stripslashes', gT('Un-quotes a quoted string'), 'string stripslashes(string)', 'http://php.net/stripslashes', 1),
'stristr' => array('exprmgr_stristr', 'stristr', gT('Case-insensitive strstr'), 'string stristr(haystack, needle [, before_needle=false])', 'http://php.net/stristr', 2,3),
'strlen' => array('exprmgr_strlen', 'LEMstrlen', gT('Get string length'), 'int strlen(string)', 'http://php.net/strlen', 1),
'strpos' => array('exprmgr_strpos', 'LEMstrpos', gT('Find position of first occurrence of a string'), 'int strpos(haystack, needle [ offset=0])', 'http://php.net/strpos', 2,3),
'strrev' => array('strrev', 'strrev', gT('Reverse a string'), 'string strrev(string)', 'http://php.net/strrev', 1),
'strstr' => array('exprmgr_strstr', 'strstr', gT('Find first occurrence of a string'), 'string strstr(haystack, needle [, before_needle=false])', 'http://php.net/strstr', 2,3),
'strtolower' => array('exprmgr_strtolower', 'LEMstrtolower', gT('Make a string lowercase'), 'string strtolower(string)', 'http://php.net/strtolower', 1),
'strtotime' => array('strtotime', 'strtotime', gT('Convert a date/time string to unix timestamp'), 'int strtotime(string)', 'http://php.net/manual/de/function.strtotime', 1),
'strtoupper' => array('exprmgr_strtoupper', 'LEMstrtoupper', gT('Make a string uppercase'), 'string strtoupper(string)', 'http://php.net/strtoupper', 1),
'substr' => array('exprmgr_substr', 'substr', gT('Return part of a string'), 'string substr(string, start [, length])', 'http://php.net/substr', 2,3),
'sum' => array('array_sum', 'LEMsum', gT('Calculate the sum of values in an array'), 'number sum(arg1, arg2, ... argN)', '', -2),
'sumifop' => array('exprmgr_sumifop', 'LEMsumifop', gT('Sum the values of answered questions in the list which pass the critiera (arg op value)'), 'number sumifop(op, value, arg1, arg2, ... argN)', '', -3),
'tan' => array('tan', 'Math.tan', gT('Tangent'), 'number tan(arg)', 'http://php.net/tan', 1),
'convert_value' => array('exprmgr_convert_value', 'LEMconvert_value', gT('Convert a numerical value using a inputTable and outputTable of numerical values'), 'number convert_value(fValue, iStrict, sTranslateFromList, sTranslateToList)', '', 4),
'time' => array('time', 'time', gT('Return current UNIX timestamp'), 'number time()', 'http://php.net/time', 0),
'trim' => array('trim', 'trim', gT('Strip whitespace (or other characters) from the beginning and end of a string'), 'string trim(string [, charlist])', 'http://php.net/trim', 1,2),
'ucwords' => array('ucwords', 'ucwords', gT('Uppercase the first character of each word in a string'), 'string ucwords(string)', 'http://php.net/ucwords', 1),
'unique' => array('exprmgr_unique', 'LEMunique', gT('Returns true if all non-empty responses are unique'), 'boolean unique(arg1, ..., argN)', '', -1),
        );
        /* Reset the language */
        if($baseLang){
            Yii::app()->setLanguage($baseLang);
        }
    }

    /**
     * Add an error to the error log
     *
     * @param string $errMsg
     * @param array|null $token
     * @return void
     */
    private function RDP_AddError($errMsg, $token)
    {
        $this->RDP_errs[] = array($errMsg, $token);
    }

    /**
     * @return array
     */
    public function RDP_GetErrors()
    {
        return $this->RDP_errs;
    }

    /**
     * Get informatin about type mismatch between arguments.
     * @param array $arg1
     * @param array $arg2
     * @return array Like (boolean $bMismatchType, boolean $bBothNumeric, boolean $bBothString)
     */
    private function getMismatchInformation(array $arg1, array $arg2)
    {
        /* When value come from DB : it's set to 1.000000 (DECIMAL) : must be fixed see #11163. Response::model() must fix this . or not ? */
        /* Don't return true always : user can entre non numeric value in a numeric value : we must compare as string then */
        $arg1[0]=($arg1[2]=="NUMBER" && strpos($arg1[0], ".")) ? rtrim(rtrim($arg1[0], "0"), ".") : $arg1[0];
        $arg2[0]=($arg2[2]=="NUMBER" && strpos($arg2[0], ".")) ? rtrim(rtrim($arg2[0], "0"), ".") : $arg2[0];
        $bNumericArg1 = !$arg1[0] || strval(floatval($arg1[0]))==strval($arg1[0]);
        $bNumericArg2 = !$arg2[0] || strval(floatval($arg2[0]))==strval($arg2[0]);

        $bStringArg1 = !$arg1[0] || !$bNumericArg1;
        $bStringArg2 = !$arg2[0] || !$bNumericArg2;

        $bBothNumeric = ($bNumericArg1 && $bNumericArg2);
        $bBothString = ($bStringArg1 && $bStringArg2);
        $bMismatchType = (!$bBothNumeric && !$bBothString);

        return array($bMismatchType, $bBothNumeric, $bBothString);
    }

    /**
     * RDP_EvaluateBinary() computes binary expressions, such as (a or b), (c * d), popping  the top two entries off the
     * stack and pushing the result back onto the stack.
     *
     * @param array $token
     * @return boolean - false if there is any error, else true
     */
    public function RDP_EvaluateBinary(array $token)
    {
        if (count($this->RDP_stack) < 2)
        {
            $this->RDP_AddError(self::gT("Unable to evaluate binary operator - fewer than 2 entries on stack"), $token);
            return false;
        }
        $arg2 = $this->RDP_StackPop();
        $arg1 = $this->RDP_StackPop();
        if (is_null($arg1) or is_null($arg2))
        {
            $this->RDP_AddError(self::gT("Invalid value(s) on the stack"), $token);
            return false;
        }

        list($bMismatchType, $bBothNumeric, $bBothString) = $this->getMismatchInformation($arg1, $arg2);

        // Set bBothString if one is forced to be string, only if both can be numeric. Mimic JS and PHP
        // Not sure if needed to test if [2] is set. : TODO review
        if($bBothNumeric){
            $aForceStringArray=array('DQ_STRING','DS_STRING','STRING');// Question can return NUMBER or WORD : DQ and DS is string entered by user, STRING is a result of a String function
            if( (isset($arg1[2]) && in_array($arg1[2],$aForceStringArray) || (isset($arg2[2]) && in_array($arg2[2],$aForceStringArray)) ) )
            {
                $bBothNumeric=false;
                $bBothString=true;
                $bMismatchType=false;
                $arg1[0]=strval($arg1[0]);
                $arg2[0]=strval($arg2[0]);
            }
        }
        switch(strtolower($token[0]))
        {
            case 'or':
            case '||':
                $result = array(($arg1[0] or $arg2[0]),$token[1],'NUMBER');
                break;
            case 'and':
            case '&&':
                $result = array(($arg1[0] and $arg2[0]),$token[1],'NUMBER');
                break;
            case '==':
            case 'eq':
                $result = array(($arg1[0] == $arg2[0]),$token[1],'NUMBER');
                break;
            case '!=':
            case 'ne':
                $result = array(($arg1[0] != $arg2[0]),$token[1],'NUMBER');
                break;
            case '<':
            case 'lt':
                if ($bMismatchType) {
                    $result = array(false,$token[1],'NUMBER');
                }
                else {
                    $result = array(($arg1[0] < $arg2[0]),$token[1],'NUMBER');
                }
                break;
                case '<=';
            case 'le':
                if ($bMismatchType) {
                    $result = array(false,$token[1],'NUMBER');
                }
                else {
                    // Need this explicit comparison in order to be in agreement with JavaScript
                    if (($arg1[0] == '0' && $arg2[0] == '') || ($arg1[0] == '' && $arg2[0] == '0')) {
                        $result = array(true,$token[1],'NUMBER');
                    }
                    else {
                        $result = array(($arg1[0] <= $arg2[0]),$token[1],'NUMBER');
                    }
                }
                break;
            case '>':
            case 'gt':
                if ($bMismatchType) {
                    $result = array(false,$token[1],'NUMBER');
                }
                else {
                    // Need this explicit comparison in order to be in agreement with JavaScript : still needed since we use ==='' ?
                    if (($arg1[0] == '0' && $arg2[0] == '') || ($arg1[0] == '' && $arg2[0] == '0')) {
                        $result = array(false,$token[1],'NUMBER');
                    }
                    else {
                        $result = array(($arg1[0] > $arg2[0]),$token[1],'NUMBER');
                    }
                }
                break;
                case '>=';
            case 'ge':
                if ($bMismatchType) {
                    $result = array(false,$token[1],'NUMBER');
                }
                else {
                    $result = array(($arg1[0] >= $arg2[0]),$token[1],'NUMBER');

                }
                break;
            case '+':
                if ($bBothNumeric) {
                    $result = array(($arg1[0] + $arg2[0]),$token[1],'NUMBER');
                }
                else {
                    $result = array($arg1[0] . $arg2[0],$token[1],'STRING');
                }
                break;
            case '-':
                if ($bBothNumeric) {
                    $result = array(($arg1[0] - $arg2[0]),$token[1],'NUMBER');
                }
                else {
                    $result = array(NAN,$token[1],'NUMBER');
                }
                break;
            case '*':
                if ($bBothNumeric) {
                    $result = array(($arg1[0] * $arg2[0]),$token[1],'NUMBER');
                }
                else {
                    $result = array(NAN,$token[1],'NUMBER');
                }
                break;
            case '/';
                if ($bBothNumeric) {
                    if ($arg2[0] == 0) {
                        $result = array(NAN,$token[1],'NUMBER');
                    }
                    else {
                        $result = array(($arg1[0] / $arg2[0]),$token[1],'NUMBER');
                    }
                }
                else {
                    $result = array(NAN,$token[1],'NUMBER');
                }
                break;
        }
        $this->RDP_StackPush($result);
        return true;
    }

    /**
     * Processes operations like +a, -b, !c
     * @param array $token
     * @return boolean - true if success, false if any error occurred
     */

    private function RDP_EvaluateUnary(array $token)
    {
        if (count($this->RDP_stack) < 1)
        {
            $this->RDP_AddError(self::gT("Unable to evaluate unary operator - no entries on stack"), $token);
            return false;
        }
        $arg1 = $this->RDP_StackPop();
        if (is_null($arg1))
        {
            $this->RDP_AddError(self::gT("Invalid value(s) on the stack"), $token);
            return false;
        }
        // TODO:  try to determine datatype?
        switch($token[0])
        {
            case '+':
                $result = array((+$arg1[0]),$token[1],'NUMBER');
                break;
            case '-':
                $result = array((-$arg1[0]),$token[1],'NUMBER');
                break;
            case '!';
                $result = array((!$arg1[0]),$token[1],'NUMBER');
                break;
        }
        $this->RDP_StackPush($result);
        return true;
    }


    /**
     * Main entry function
     * @param string $expr
     * @param boolean $onlyparse - if true, then validate the syntax without computing an answer
     * @return boolean - true if success, false if any error occurred
     */
    public function RDP_Evaluate($expr, $onlyparse=false)
    {
        $this->RDP_expr = $expr;
        $this->RDP_tokens = $this->RDP_Tokenize($expr);
        $this->RDP_count = count($this->RDP_tokens);
        $this->RDP_pos = -1; // starting position within array (first act will be to increment it)
        $this->RDP_errs = array();
        $this->RDP_onlyparse = $onlyparse;
        $this->RDP_stack = array();
        $this->RDP_evalStatus = false;
        $this->RDP_result = NULL;
        $this->varsUsed = array();
        $this->jsExpression = NULL;

        if ($this->HasSyntaxErrors()) {
            return false;
        }
        elseif ($this->RDP_EvaluateExpressions())
        {
            if ($this->RDP_pos < $this->RDP_count)
            {
                $this->RDP_AddError(self::gT("Extra tokens found"), $this->RDP_tokens[$this->RDP_pos]);
                return false;
            }
            $this->RDP_result = $this->RDP_StackPop();
            if (is_null($this->RDP_result))
            {
                return false;
            }
            if (count($this->RDP_stack) == 0)
            {
                $this->RDP_evalStatus = true;
                return true;
            }
            else
            {
                $this-RDP_AddError(self::gT("Unbalanced equation - values left on stack"),NULL);
                return false;
            }
        }
        else
        {
            $this->RDP_AddError(self::gT("Not a valid expression"),NULL);
            return false;
        }
    }


    /**
     * Process "a op b" where op in (+,-,concatenate)
     * @return boolean - true if success, false if any error occurred
     */
    private function RDP_EvaluateAdditiveExpression()
    {
        if (!$this->RDP_EvaluateMultiplicativeExpression())
        {
            return false;
        }
        while (($this->RDP_pos + 1) < $this->RDP_count)
        {
            $token = $this->RDP_tokens[++$this->RDP_pos];
            if ($token[2] == 'BINARYOP')
            {
                switch ($token[0])
                {
                    case '+':
                    case '-';
                        if ($this->RDP_EvaluateMultiplicativeExpression())
                        {
                            if (!$this->RDP_EvaluateBinary($token))
                            {
                                return false;
                            }
                            // else continue;
                        }
                        else
                        {
                            return false;
                        }
                        break;
                    default:
                        --$this->RDP_pos;
                        return true;
                }
            }
            else
            {
                --$this->RDP_pos;
                return true;
            }
        }
        return true;
    }

    /**
     * Process a Constant (number of string), retrieve the value of a known variable, or process a function, returning result on the stack.
     * @return boolean|null - true if success, false if any error occurred
     */

    private function RDP_EvaluateConstantVarOrFunction()
    {
        if ($this->RDP_pos + 1 >= $this->RDP_count)
        {
             $this->RDP_AddError(self::gT("Poorly terminated expression - expected a constant or variable"), NULL);
             return false;
        }
        $token = $this->RDP_tokens[++$this->RDP_pos];
        switch ($token[2])
        {
            case 'NUMBER':
            case 'DQ_STRING':
            case 'SQ_STRING':
                $this->RDP_StackPush($token);
                return true;
                // NB: No break needed
            case 'WORD':
            case 'SGQA':
                if (($this->RDP_pos + 1) < $this->RDP_count and $this->RDP_tokens[($this->RDP_pos + 1)][2] == 'LP')
                {
                    return $this->RDP_EvaluateFunction();
                }
                else
                {
                    if ($this->RDP_isValidVariable($token[0]))
                    {
                        $this->varsUsed[] = $token[0];  // add this variable to list of those used in this equation
                        if (preg_match("/\.(gid|grelevance|gseq|jsName|mandatory|qid|qseq|question|readWrite|relevance|rowdivid|sgqa|type)$/",$token[0]))
                        {
                            $relStatus=1;   // static, so always relevant
                        }
                        else
                        {
                            $relStatus = $this->GetVarAttribute($token[0],'relevanceStatus',1);
                        }
                        if ($relStatus==1)
                        {
                            $argtype=($this->GetVarAttribute($token[0],'onlynum',0))?"NUMBER":"WORD";
                            $result = array($this->GetVarAttribute($token[0],NULL,''),$token[1],$argtype);
                        }
                        else
                        {
                            $result = array(NULL,$token[1],'NUMBER');   // was 0 instead of NULL
                        }
                        $this->RDP_StackPush($result);
                        return true;
                    }
                    else
                    {
                        $this->RDP_AddError(self::gT("Undefined variable"), $token);
                        return false;
                    }
                }
                // NB: No break needed
            case 'COMMA':
                --$this->RDP_pos;
                $this->RDP_AddError("Should never get to this line?",$token);
                return false;
                // NB: No break needed
            default:
                return false;
                // NB: No break needed
        }
    }

    /**
     * Process "a == b", "a eq b", "a != b", "a ne b"
     * @return boolean - true if success, false if any error occurred
     */
    private function RDP_EvaluateEqualityExpression()
    {
        if (!$this->RDP_EvaluateRelationExpression())
        {
            return false;
        }
        while (($this->RDP_pos + 1) < $this->RDP_count)
        {
            $token = $this->RDP_tokens[++$this->RDP_pos];
            switch (strtolower($token[0]))
            {
                case '==':
                case 'eq':
                case '!=':
                case 'ne':
                    if ($this->RDP_EvaluateRelationExpression())
                    {
                        if (!$this->RDP_EvaluateBinary($token))
                        {
                            return false;
                        }
                        // else continue;
                    }
                    else
                    {
                        return false;
                    }
                    break;
                default:
                    --$this->RDP_pos;
                    return true;
            }
        }
        return true;
    }

    /**
     * Process a single expression (e.g. without commas)
     * @return boolean - true if success, false if any error occurred
     */

    private function RDP_EvaluateExpression()
    {
        if ($this->RDP_pos + 2 < $this->RDP_count)
        {
            $token1 = $this->RDP_tokens[++$this->RDP_pos];
            $token2 = $this->RDP_tokens[++$this->RDP_pos];
            if ($token2[2] == 'ASSIGN')
            {
                if ($this->RDP_isValidVariable($token1[0]))
                {
                    $this->varsUsed[] = $token1[0];  // add this variable to list of those used in this equation
                    if ($this->RDP_isWritableVariable($token1[0]))
                    {
                        $evalStatus = $this->RDP_EvaluateLogicalOrExpression();
                        if ($evalStatus)
                        {
                            $result = $this->RDP_StackPop();
                            if (!is_null($result))
                            {
                                $newResult = $token2;
                                $newResult[2] = 'NUMBER';
                                $newResult[0] = $this->RDP_SetVariableValue($token2[0], $token1[0], $result[0]);
                                $this->RDP_StackPush($newResult);
                            }
                            else
                            {
                                $evalStatus = false;
                            }
                        }
                        return $evalStatus;
                    }
                    else
                    {
                        $this->RDP_AddError(self::gT('The value of this variable can not be changed'), $token1);
                        return false;
                    }
                }
                else
                {
                    $this->RDP_AddError(self::gT('Only variables can be assigned values'), $token1);
                    return false;
                }
            }
            else
            {
                // not an assignment expression, so try something else
                $this->RDP_pos -= 2;
                return $this->RDP_EvaluateLogicalOrExpression();
            }
        }
        else
        {
            return $this->RDP_EvaluateLogicalOrExpression();
        }
    }

    /**
     * Process "expression [, expression]*
     * @return boolean - true if success, false if any error occurred
     */

    private function RDP_EvaluateExpressions()
    {
        $evalStatus = $this->RDP_EvaluateExpression();
        if (!$evalStatus)
        {
            return false;
        }

        while (++$this->RDP_pos < $this->RDP_count) {
            $token = $this->RDP_tokens[$this->RDP_pos];
            if ($token[2] == 'RP')
            {
                return true;    // presumbably the end of an expression
            }
            elseif ($token[2] == 'COMMA')
            {
                if ($this->RDP_EvaluateExpression())
                {
                    $secondResult = $this->RDP_StackPop();
                    $firstResult = $this->RDP_StackPop();
                    if (is_null($firstResult))
                    {
                        return false;
                    }
                    $this->RDP_StackPush($secondResult);
                    $evalStatus = true;
                }
                else
                {
                    return false;   // an error must have occurred
                }
            }
            else
            {
                $this->RDP_AddError(self::gT("Expected expressions separated by commas"),$token);
                $evalStatus = false;
                break;
            }
        }
        while (++$this->RDP_pos < $this->RDP_count)
        {
            $token = $this->RDP_tokens[$this->RDP_pos];
            $this->RDP_AddError(self::gT("Extra token found after expressions"),$token);
            $evalStatus = false;
        }
        return $evalStatus;
    }

    /**
     * Process a function call
     * @return boolean|null - true if success, false if any error occurred
     */
    private function RDP_EvaluateFunction()
    {
        $funcNameToken = $this->RDP_tokens[$this->RDP_pos]; // note that don't need to increment position for functions
        $funcName = $funcNameToken[0];
        if (!$this->RDP_isValidFunction($funcName))
        {
            $this->RDP_AddError(self::gT("Undefined function"), $funcNameToken);
            return false;
        }
        $token2 = $this->RDP_tokens[++$this->RDP_pos];
        if ($token2[2] != 'LP')
        {
            $this->RDP_AddError(self::gT("Expected left parentheses after function name"), $funcNameToken);
        }
        $params = array();  // will just store array of values, not tokens
        while ($this->RDP_pos + 1 < $this->RDP_count)
        {
            $token3 = $this->RDP_tokens[$this->RDP_pos + 1];
            if (count($params) > 0)
            {
                // should have COMMA or RP
                if ($token3[2] == 'COMMA')
                {
                    ++$this->RDP_pos;   // consume the token so can process next clause
                    if ($this->RDP_EvaluateExpression())
                    {
                        $value = $this->RDP_StackPop();
                        if (is_null($value))
                        {
                            return false;
                        }
                        $params[] = $value[0];
                        continue;
                    }
                    else
                    {
                        $this->RDP_AddError(self::gT("Extra comma found in function"), $token3);
                        return false;
                    }
                }
            }
            if ($token3[2] == 'RP')
            {
                ++$this->RDP_pos;   // consume the token so can process next clause
                return $this->RDP_RunFunction($funcNameToken,$params);
            }
            else
            {
                if ($this->RDP_EvaluateExpression())
                {
                    $value = $this->RDP_StackPop();
                    if (is_null($value))
                    {
                        return false;
                    }
                    $params[] = $value[0];
                    continue;
                }
                else
                {
                    return false;
                }
            }
        }
    }

    /**
     * Process "a && b" or "a and b"
     * @return boolean - true if success, false if any error occurred
     */

    private function RDP_EvaluateLogicalAndExpression()
    {
        if (!$this->RDP_EvaluateEqualityExpression())
        {
            return false;
        }
        while (($this->RDP_pos + 1) < $this->RDP_count)
        {
            $token = $this->RDP_tokens[++$this->RDP_pos];
            switch (strtolower($token[0]))
            {
                case '&&':
                case 'and':
                    if ($this->RDP_EvaluateEqualityExpression())
                    {
                        if (!$this->RDP_EvaluateBinary($token))
                        {
                            return false;
                        }
                        // else continue
                    }
                    else
                    {
                        return false;   // an error must have occurred
                    }
                    break;
                default:
                    --$this->RDP_pos;
                    return true;
            }
        }
        return true;
    }

    /**
     * Process "a || b" or "a or b"
     * @return boolean - true if success, false if any error occurred
     */
    private function RDP_EvaluateLogicalOrExpression()
    {
        if (!$this->RDP_EvaluateLogicalAndExpression())
        {
            return false;
        }
        while (($this->RDP_pos + 1) < $this->RDP_count)
        {
            $token = $this->RDP_tokens[++$this->RDP_pos];
            switch (strtolower($token[0]))
            {
                case '||':
                case 'or':
                    if ($this->RDP_EvaluateLogicalAndExpression())
                    {
                        if (!$this->RDP_EvaluateBinary($token))
                        {
                            return false;
                        }
                        // else  continue
                    }
                    else
                    {
                        // an error must have occurred
                        return false;
                    }
                    break;
                default:
                    // no more expressions being  ORed together, so continue parsing
                    --$this->RDP_pos;
                    return true;
            }
        }
        // no more tokens to parse
        return true;
    }

    /**
     * Process "a op b" where op in (*,/)
     * @return boolean - true if success, false if any error occurred
     */

    private function RDP_EvaluateMultiplicativeExpression()
    {
        if (!$this->RDP_EvaluateUnaryExpression())
        {
            return  false;
        }
        while (($this->RDP_pos + 1) < $this->RDP_count)
        {
            $token = $this->RDP_tokens[++$this->RDP_pos];
            if ($token[2] == 'BINARYOP')
            {
                switch ($token[0])
                {
                    case '*':
                    case '/';
                        if ($this->RDP_EvaluateUnaryExpression())
                        {
                            if (!$this->RDP_EvaluateBinary($token))
                            {
                                return false;
                            }
                            // else  continue
                        }
                        else
                        {
                            // an error must have occurred
                            return false;
                        }
                        break;
                    default:
                        --$this->RDP_pos;
                        return true;
                }
            }
            else
            {
                --$this->RDP_pos;
                return true;
            }
        }
        return true;
    }

    /**
     * Process expressions including functions and parenthesized blocks
     * @return boolean|null - true if success, false if any error occurred
     */

    private function RDP_EvaluatePrimaryExpression()
    {
        if (($this->RDP_pos + 1) >= $this->RDP_count) {
            $this->RDP_AddError(self::gT("Poorly terminated expression - expected a constant or variable"), NULL);
            return false;
        }
        $token = $this->RDP_tokens[++$this->RDP_pos];
        if ($token[2] == 'LP')
        {
            if (!$this->RDP_EvaluateExpressions())
            {
                return false;
            }
            $token = $this->RDP_tokens[$this->RDP_pos];
            if ($token[2] == 'RP')
            {
                return true;
            }
            else
            {
                $this->RDP_AddError(self::gT("Expected right parentheses"), $token);
                return false;
            }
        }
        else
        {
            --$this->RDP_pos;
            return $this->RDP_EvaluateConstantVarOrFunction();
        }
    }

    /**
     * Process "a op b" where op in (lt, gt, le, ge, <, >, <=, >=)
     * @return boolean - true if success, false if any error occurred
     */
    private function RDP_EvaluateRelationExpression()
    {
        if (!$this->RDP_EvaluateAdditiveExpression())
        {
            return false;
        }
        while (($this->RDP_pos + 1) < $this->RDP_count)
        {
            $token = $this->RDP_tokens[++$this->RDP_pos];
            switch (strtolower($token[0]))
            {
                case '<':
                case 'lt':
                case '<=';
                case 'le':
                case '>':
                case 'gt':
                case '>=';
                case 'ge':
                    if ($this->RDP_EvaluateAdditiveExpression())
                    {
                        if (!$this->RDP_EvaluateBinary($token))
                        {
                            return false;
                        }
                        // else  continue
                    }
                    else
                    {
                        // an error must have occurred
                        return false;
                    }
                    break;
                default:
                    --$this->RDP_pos;
                    return true;
            }
        }
        return true;
    }

    /**
     * Process "op a" where op in (+,-,!)
     * @return boolean|null - true if success, false if any error occurred
     */

    private function RDP_EvaluateUnaryExpression()
    {
        if (($this->RDP_pos + 1) >= $this->RDP_count) {
            $this->RDP_AddError(self::gT("Poorly terminated expression - expected a constant or variable"), NULL);
            return false;
        }
        $token = $this->RDP_tokens[++$this->RDP_pos];
        if ($token[2] == 'NOT' || $token[2] == 'BINARYOP')
        {
            switch ($token[0])
            {
                case '+':
                case '-':
                case '!':
                    if (!$this->RDP_EvaluatePrimaryExpression())
                    {
                        return false;
                    }
                    return $this->RDP_EvaluateUnary($token);
                    // NB: No break needed
                    break;
                default:
                    --$this->RDP_pos;
                    return $this->RDP_EvaluatePrimaryExpression();
            }
        }
        else
        {
            --$this->RDP_pos;
            return $this->RDP_EvaluatePrimaryExpression();
        }
    }

    /**
     * Returns array of all JavaScript-equivalent variable names used when parsing a string via sProcessStringContainingExpressions
     * @return array
     */
    public function GetAllJsVarsUsed()
    {
        if (is_null($this->allVarsUsed)){
            return array();
        }
        $names = array_unique($this->allVarsUsed);
        if (is_null($names)) {
            return array();
        }
        $jsNames = array();
        foreach ($names as $name)
        {
            if (preg_match("/\.(gid|grelevance|gseq|jsName|mandatory|qid|qseq|question|readWrite|relevance|rowdivid|sgqa|type)$/",$name))
            {
                continue;
            }
            $val = $this->GetVarAttribute($name,'jsName','');
            if ($val != '') {
                $jsNames[] = $val;
            }
        }
        return array_unique($jsNames);
    }

    /**
     * Return the list of all of the JavaScript variables used by the most recent expression - only those that are set on the current page
     * This is used to control static vs dynamic substitution.  If an expression is entirely made up of off-page changes, it can be statically replaced.
     * @return array
     */
    public function GetOnPageJsVarsUsed()
    {
        if (is_null($this->varsUsed)){
            return array();
        }
        if ($this->surveyMode=='survey')
        {
            return $this->GetJsVarsUsed();
        }
        $names = array_unique($this->varsUsed);
        if (is_null($names)) {
            return array();
        }
        $jsNames = array();
        foreach ($names as $name)
        {
            if (preg_match("/\.(gid|grelevance|gseq|jsName|mandatory|qid|qseq|question|readWrite|relevance|rowdivid|sgqa|type)$/",$name))
            {
                continue;
            }
            $val = $this->GetVarAttribute($name,'jsName','');
            switch ($this->surveyMode)
            {
                case 'group':
                    $gseq = $this->GetVarAttribute($name,'gseq','');
                    $onpage = ($gseq == $this->groupSeq);
                    break;
                case 'question':
                    $qseq = $this->GetVarAttribute($name,'qseq','');
                    $onpage = ($qseq == $this->questionSeq);
                    break;
                case 'survey':
                    $onpage = true;
                    break;
            }
            if ($val != '' && $onpage) {
                $jsNames[] = $val;
            }
        }
        return array_unique($jsNames);
    }

    /**
     * Return the list of all of the JavaScript variables used by the most recent expression
     * @return array
     */
    public function GetJsVarsUsed()
    {
        if (is_null($this->varsUsed)){
            return array();
        }
        $names = array_unique($this->varsUsed);
        if (is_null($names)) {
            return array();
        }
        $jsNames = array();
        foreach ($names as $name)
        {
            if (preg_match("/\.(gid|grelevance|gseq|jsName|mandatory|qid|qseq|question|readWrite|relevance|rowdivid|sgqa|type)$/",$name))
            {
                continue;
            }
            $val = $this->GetVarAttribute($name,'jsName','');
            if ($val != '') {
                $jsNames[] = $val;
            }
        }
        return array_unique($jsNames);
    }

    /**
     * @return void
     */
    public function SetJsVarsUsed($vars)
    {
        $this->varsUsed = $vars;
    }

    /**
     * Return the JavaScript variable name for a named variable
     * @param string $name
     * @return string
     */
    public function GetJsVarFor($name)
    {
        return $this->GetVarAttribute($name,'jsName','');
    }

    /**
     * Returns array of all variables used when parsing a string via sProcessStringContainingExpressions
     * @return array
     */
    public function GetAllVarsUsed()
    {
        return array_unique($this->allVarsUsed);
    }

    /**
     * Return the result of evaluating the equation - NULL if  error
     * @return mixed
     */
    public function GetResult()
    {
        return $this->RDP_result[0];
    }

    /**
     * Return an array of errors
     * @return array
     */
    public function GetErrors()
    {
        return $this->RDP_errs;
    }

    /**
     * Converts the most recent expression into a valid JavaScript expression, mapping function and variable names and operators as needed.
     * @return string the JavaScript expresssion
     */
    public function GetJavaScriptEquivalentOfExpression()
    {
        if (!is_null($this->jsExpression))
        {
            return $this->jsExpression;
        }
        if ($this->HasErrors())
        {
            $this->jsExpression = '';
            return '';
        }
        $tokens = $this->RDP_tokens;
        $stringParts=array();
        $numTokens = count($tokens);
        for ($i=0;$i<$numTokens;++$i)
        {
            $token = $tokens[$i];
            // When do these need to be quoted?

            switch ($token[2])
            {
                case 'DQ_STRING':
                    $stringParts[] = '"' . addcslashes($token[0],'\"') . '"'; // htmlspecialchars($token[0],ENT_QUOTES,'UTF-8',false) . "'";
                    break;
                case 'SQ_STRING':
                    $stringParts[] = "'" . addcslashes($token[0],"\'") . "'"; // htmlspecialchars($token[0],ENT_QUOTES,'UTF-8',false) . "'";
                    break;
                case 'SGQA':
                case 'WORD':
                    if ($i+1<$numTokens && $tokens[$i+1][2] == 'LP')
                    {
                        // then word is a function name
                        $funcInfo = $this->RDP_ValidFunctions[$token[0]];
                        if ($funcInfo[1] == 'NA')
                        {
                            return '';  // to indicate that this is trying to use a undefined function.  Need more graceful solution
                        }
                        $stringParts[] = $funcInfo[1];  // the PHP function name
                    }
                    elseif ($i+1<$numTokens && $tokens[$i+1][2] == 'ASSIGN')
                    {
                        $jsName = $this->GetVarAttribute($token[0],'jsName','');
                        $stringParts[] = "document.getElementById('" . $jsName . "').value";
                        if ($tokens[$i+1][0] == '+=')
                        {
                            // Javascript does concatenation unless both left and right side are numbers, so refactor the equation
                            $varName = $this->GetVarAttribute($token[0],'varName',$token[0]);
                            $stringParts[] = " = LEMval('" . $varName . "') + ";
                            ++$i;
                        }
                    }
                    else
                    {
                        $jsName = $this->GetVarAttribute($token[0],'jsName','');
                        $code = $this->GetVarAttribute($token[0],'code','');
                        if ($jsName != '')
                        {
                            $varName = $this->GetVarAttribute($token[0],'varName',$token[0]);
                            $stringParts[] = "LEMval('" . $varName . "') ";
                        }
                        else
                        {
                            $stringParts[] = "'" . addcslashes($code,"'") . "'";
                        }
                    }
                    break;
                case 'LP':
                case 'RP':
                    $stringParts[] = $token[0];
                    break;
                case 'NUMBER':
                    $stringParts[] = is_numeric($token[0]) ? $token[0] : ("'" . $token[0] . "'");
                    break;
                case 'COMMA':
                    $stringParts[] = $token[0] . ' ';
                    break;
                default:
                    // don't need to check type of $token[2] here since already handling SQ_STRING and DQ_STRING above
                    switch (strtolower($token[0]))
                    {
                        case 'and': $stringParts[] = ' && '; break;
                        case 'or':  $stringParts[] = ' || '; break;
                        case 'lt':  $stringParts[] = ' < '; break;
                        case 'le':  $stringParts[] = ' <= '; break;
                        case 'gt':  $stringParts[] = ' > '; break;
                        case 'ge':  $stringParts[] = ' >= '; break;
                        case 'eq':  case '==': $stringParts[] = ' == '; break;
                        case 'ne':  case '!=': $stringParts[] = ' != '; break;
                        default:    $stringParts[] = ' ' . $token[0] . ' '; break;
                    }
                    break;
            }
        }
        // for each variable that does not have a default value, add clause to throw error if any of them are NA
        $nonNAvarsUsed = array();
        foreach ($this->GetVarsUsed() as $var)    // this function wants to see the NAOK suffix
        {
            if (!preg_match("/^.*\.(NAOK|relevanceStatus)$/", $var))
            {
                if ($this->GetVarAttribute($var,'jsName','') != '')
                {
                    $nonNAvarsUsed[] = $var;
                }
            }
        }
        $mainClause = implode('', $stringParts);
        $varsUsed = implode("', '", $nonNAvarsUsed);
        if ($varsUsed != '')
        {
            $this->jsExpression = "LEMif(LEManyNA('" . $varsUsed . "'),'',(" . $mainClause . "))";
        }
        else
        {
            $this->jsExpression = '(' . $mainClause . ')';
        }
        return $this->jsExpression;
    }

    /**
     * JavaScript Test function - simply writes the result of the current JavaScriptEquivalentFunction to the output buffer.
     * @param string $expected
     * @param integer $num
     * @return string
     */
    public function GetJavascriptTestforExpression($expected,$num)
    {
        // assumes that the hidden variables have already been declared
        $expr = $this->GetJavaScriptEquivalentOfExpression();
        if (is_null($expr) || $expr == '') {
            $expr = "'NULL'";
        }
        $jsmultiline_expr = str_replace("\n","\\\n",$expr);
        $jsmultiline_expected = str_replace("\n","\\\n",addslashes($expected));
        $jsParts = array();
        $jsParts[] = "val = " . $jsmultiline_expr . ";\n";
        $jsParts[] = "klass = (LEMeq(addslashes(val),'" . $jsmultiline_expected . "')) ? 'ok' : 'error';\n";
        $jsParts[] = "document.getElementById('test_" . $num . "').innerHTML=(val);\n";
        $jsParts[] = "document.getElementById('test_" . $num . "').className=klass;\n";
        return implode('',$jsParts);

    }

    /**
     * Generate the function needed to dynamically change the value of a <span> section
     * @param string $name - the ID name for the function
     * @param string $eqn
     * @param integer $questionNum
     * @return string
     */
    public function GetJavaScriptFunctionForReplacement($questionNum, $name,$eqn)
    {
        $jsParts = array();
//        $jsParts[] = "\n  // Tailor Question " . $questionNum . " - " . $name . ": { " . $eqn . " }\n";
        $jsParts[] = "  try{\n";
        $jsParts[] = "  document.getElementById('" . $name . "').innerHTML=LEMfixnum(\n    ";
        $jsParts[] = $this->GetJavaScriptEquivalentOfExpression();
        $jsParts[] = ");\n";
        $jsParts[] = "  } catch (e) { console.log(e); }\n";
        return implode('',$jsParts);
    }

    /**
     * Returns the most recent PrettyPrint string generated by sProcessStringContainingExpressions
     */
    public function GetLastPrettyPrintExpression()
    {
        return $this->prettyPrintSource;
    }

    /**
     * This is only used when there are no needed substitutions
     * @param string $expr
     */
    public function SetPrettyPrintSource($expr)
    {
        $this->prettyPrintSource = $expr;
    }

    /**
     * Color-codes Expressions (using HTML <span> tags), showing variable types and values.
     * @return string HTML
     */
    public function GetPrettyPrintString()
    {
        //~ Yii::app()->setLanguage(Yii::app()->session['adminlang']);
        // color code the equation, showing not only errors, but also variable attributes
        $errs = $this->RDP_errs;
        $tokens = $this->RDP_tokens;
        $errCount = count($errs);
        $errIndex = 0;
        $aClass=array();
        if ($errCount > 0)
        {
            usort($errs,"cmpErrorTokens");
        }
        $stringParts=array();
        $numTokens = count($tokens);
        $globalErrs=array();
        $bHaveError=false;
        while ($errIndex < $errCount)
        {
            if ($errs[$errIndex++][1][1]==0)
            {
                // General message, associated with position 0
                $globalErrs[] = $errs[$errIndex-1][0];
                $bHaveError=true;
            }
            else
            {
                --$errIndex;
                break;
            }
        }
        for ($i=0;$i<$numTokens;++$i)
        {
            $token = $tokens[$i];
            $messages=array();
            $thisTokenHasError=false;
            if ($i==0 && count($globalErrs) > 0)
            {
                $messages = array_merge($messages,$globalErrs);
                $thisTokenHasError=true;
            }
            if ($errIndex < $errCount && $token[1] == $errs[$errIndex][1][1])
            {
                $messages[] = $errs[$errIndex][0];
                $thisTokenHasError=true;
            }
            if ($thisTokenHasError)
            {
                $stringParts[] = "<span title='" . implode('; ',$messages) . "' class='em-error'>";
                $bHaveError=true;
            }
            switch ($token[2])
            {
                case 'DQ_STRING':
                    $stringParts[] = "<span title='" . implode('; ',$messages) . "' class='em-var-string'>\"";
                    $stringParts[] = $token[0]; // htmlspecialchars($token[0],ENT_QUOTES,'UTF-8',false);
                    $stringParts[] = "\"</span>";
                    break;
                case 'SQ_STRING':
                    $stringParts[] = "<span title='" . implode('; ',$messages) . "' class='em-var-string'>'";
                    $stringParts[] = $token[0]; // htmlspecialchars($token[0],ENT_QUOTES,'UTF-8',false);
                    $stringParts[] = "'</span>";
                    break;
                case 'SGQA':
                case 'WORD':
                    if ($i+1<$numTokens && $tokens[$i+1][2] == 'LP')
                    {
                        // then word is a function name
                        if ($this->RDP_isValidFunction($token[0])) {
                            $funcInfo = $this->RDP_ValidFunctions[$token[0]];
                            $messages[] = $funcInfo[2];
                            $messages[] = $funcInfo[3];
                        }
                        $stringParts[] = "<span title='" . implode('; ',$messages) . "' class='em-function' >";
                        $stringParts[] = $token[0];
                        $stringParts[] = "</span>";
                    }
                    else
                    {
                        if (!$this->RDP_isValidVariable($token[0]))
                        {
                            $class = 'em-var-error';
                            $displayName = $token[0];
                        }
                        else
                        {
                            $jsName = $this->GetVarAttribute($token[0],'jsName','');
                            $code = $this->GetVarAttribute($token[0],'code','');
                            $question = $this->GetVarAttribute($token[0], 'question', '');
                            $qcode= $this->GetVarAttribute($token[0],'qcode','');
                            $questionSeq = $this->GetVarAttribute($token[0],'qseq',-1);
                            $groupSeq = $this->GetVarAttribute($token[0],'gseq',-1);
                            $ansList = $this->GetVarAttribute($token[0],'ansList','');
                            $gid = $this->GetVarAttribute($token[0],'gid',-1);
                            $qid = $this->GetVarAttribute($token[0],'qid',-1);

                            if ($jsName != '') {
                                $descriptor = '[' . $jsName . ']';
                            }
                            else {
                                $descriptor = '';
                            }
                            // Show variable name instead of SGQA code, if available
                            if ($qcode != '') {
                                if (preg_match('/^INSERTANS:/',$token[0])) {
                                    $displayName = $qcode . '.shown';
                                    $descriptor = '[' . $token[0] . ']';
                                }
                                else {
                                    $args = explode('.',$token[0]);
                                    if (count($args) == 2) {
                                        $displayName = $qcode . '.' . $args[1];
                                    }
                                    else {
                                        $displayName = $qcode;
                                    }
                                }
                            }
                            else {
                                $displayName = $token[0];
                            }
                            if ($questionSeq != -1) {
                                $descriptor .= '[G:' . $groupSeq . ']';
                            }
                            if ($groupSeq != -1) {
                                $descriptor .= '[Q:' . $questionSeq . ']';
                            }
                            if (strlen($descriptor) > 0) {
                                $descriptor .= ': ';
                            }

                            $messages[] = $descriptor . htmlspecialchars($question,ENT_QUOTES,'UTF-8',false);
                            if ($ansList != '')
                            {
                                $messages[] = htmlspecialchars($ansList,ENT_QUOTES,'UTF-8',false);
                            }
                            if ($code != '') {
                                if ($token[2] == 'SGQA' && preg_match('/^INSERTANS:/',$token[0])) {
                                    $shown = $this->GetVarAttribute($token[0], 'shown', '');
                                    $messages[] = 'value=[' . htmlspecialchars($code,ENT_QUOTES,'UTF-8',false) . '] '
                                            . htmlspecialchars($shown,ENT_QUOTES,'UTF-8',false);
                                }
                                else {
                                    $messages[] = 'value=' . htmlspecialchars($code,ENT_QUOTES,'UTF-8',false);
                                }
                            }

                            if ($this->groupSeq == -1 || $groupSeq == -1 || $questionSeq == -1 || $this->questionSeq == -1) {
                                $class = 'em-var-static';
                            }
                            elseif ($groupSeq > $this->groupSeq) {
                                $class = 'em-var-before em-var-diffgroup';
                            }
                            elseif ($groupSeq < $this->groupSeq) {
                                $class = 'em-var-after ';
                            }
                            elseif ($questionSeq > $this->questionSeq) {
                                $class = 'em-var-before em-var-inpage';
                            }
                            else {
                                $class = 'em-var-after em-var-inpage';
                            }
                        }
                        // prevent EM prcessing of messages within span
                        $message = implode('; ',$messages);
                        $message = str_replace(array('{','}'), array('{ ', ' }'), $message);

                        if ($this->hyperlinkSyntaxHighlighting && isset($gid) && isset($qid) && $qid>0)
                        {
                            $editlink = Yii::app()->getController()->createUrl('admin/questions/sa/view/surveyid/' . $this->sid . '/gid/' . $gid . '/qid/' . $qid);
                            $stringParts[] = "<a title='{$message}' class='em-var {$class}' href='{$editlink}' >";
                        }
                        else
                        {
                            $stringParts[] = "<span title='"  . $message . "' class='em-var {$class}' >";
                        }
                        if ($this->sgqaNaming)
                        {
                            $sgqa = substr($jsName,4);
                            $nameParts = explode('.',$displayName);
                            if (count($nameParts)==2)
                            {
                                $sgqa .= '.' . $nameParts[1];
                            }
                            $stringParts[] = $sgqa;
                        }
                        else
                        {
                            $stringParts[] = $displayName;
                        }
                        if ($this->hyperlinkSyntaxHighlighting && isset($gid) && isset($qid) && $qid>0)
                        {
                            $stringParts[] = "</a>";
                        }
                        else
                        {
                            $stringParts[] = "</span>";
                        }
                    }
                    break;
                case 'ASSIGN':
                    $messages[] = self::gT('Assigning a new value to a variable.');
                    $stringParts[] = "<span title='" . implode('; ',$messages) . "' class='em-assign'>";
                    $stringParts[] = $token[0];
                    $stringParts[] =  "</span>";
                    break;
                case 'COMMA':
                    $stringParts[] = $token[0] . ' ';
                    break;
                case 'LP':
                case 'RP':
                case 'NUMBER':
                    $stringParts[] = $token[0];
                    break;
                default:
                    $stringParts[] = ' ' . $token[0] . ' ';
                    break;
            }
            if ($thisTokenHasError)
            {
                $stringParts[] = "</span>";
                ++$errIndex;
            }
        }
        if($this->sid && Permission::model()->hasSurveyPermission($this->sid, 'surveycontent', 'update'))
        {
            /*
            $oAdminTheme = AdminTheme::getInstance();
            $oAdminTheme->registerCssFile( 'PUBLIC', 'expressions.css' );
            $oAdminTheme->registerScriptFile( 'ADMIN_SCRIPT_PATH', 'expression.js');
            */

            App()->getClientScript()->registerCssFile( Yii::app()->getConfig('publicstyleurl') . "expressions.css" );
            App()->getClientScript()->registerScriptFile( Yii::app()->getConfig('adminscripts') . "expression.js");

        }
        $sClass='em-expression';
        $sClass.=($bHaveError)?" em-haveerror":"";
        return "<span class='$sClass'>" . implode('', $stringParts) . "</span>";
    }

    /**
     * Get information about the variable, including JavaScript name, read-write status, and whether set on current page.
     * @param string $name
     * @param string|null $attr
     * @param string default
     * @return string
     */
    private function GetVarAttribute($name,$attr,$default)
    {
        return LimeExpressionManager::GetVarAttribute($name,$attr,$default,$this->groupSeq,$this->questionSeq);
    }

    /**
     * Return array of the list of variables used  in the equation
     * @return array
     */
    public function GetVarsUsed()
    {
        return array_unique($this->varsUsed);
    }

    /**
     * Return true if there were syntax or processing errors
     * @return boolean
     */
    public function HasErrors()
    {
        return (count($this->RDP_errs) > 0);
    }

    /**
     * Return true if there are syntax errors
     * @return boolean
     */
    private function HasSyntaxErrors()
    {
        // check for bad tokens
        // check for unmatched parentheses
        // check for undefined variables
        // check for undefined functions (but can't easily check allowable # elements?)

        $nesting = 0;

        for ($i=0;$i<$this->RDP_count;++$i)
        {
            $token = $this->RDP_tokens[$i];
            switch ($token[2])
            {
                case 'LP':
                    ++$nesting;
                    break;
                case 'RP':
                    --$nesting;
                    if ($nesting < 0)
                    {
                        $this->RDP_AddError(self::gT("Extra right parentheses detected"), $token);
                    }
                    break;
                case 'WORD':
                case 'SGQA':
                    if ($i+1 < $this->RDP_count and $this->RDP_tokens[$i+1][2] == 'LP')
                    {
                        if (!$this->RDP_isValidFunction($token[0]))
                        {
                            $this->RDP_AddError(self::gT("Undefined function"), $token);
                        }
                    }
                    else
                    {
                        if (!($this->RDP_isValidVariable($token[0])))
                        {
                            $this->RDP_AddError(self::gT("Undefined variable"), $token);
                        }
                    }
                    break;
                case 'OTHER':
                    $this->RDP_AddError(self::gT("Unsupported syntax"), $token);
                    break;
                default:
                    break;
            }
        }
        if ($nesting != 0)
        {
            $this->RDP_AddError(sprintf(self::gT("Missing %s closing right parentheses"),$nesting),NULL);
        }
        return (count($this->RDP_errs) > 0);
    }

    /**
     * Return true if the function name is registered
     * @param string $name
     * @return boolean
     */

    private function RDP_isValidFunction($name)
    {
        return array_key_exists($name,$this->RDP_ValidFunctions);
    }

    /**
     * Return true if the variable name is registered
     * @param string $name
     * @return boolean
     */
    private function RDP_isValidVariable($name)
    {
        $varName = preg_replace("/^(?:INSERTANS:)?(.*?)(?:\.(?:" . ExpressionManager::$RDP_regex_var_attr . "))?$/", "$1", $name);
        return LimeExpressionManager::isValidVariable($varName);
    }

    /**
     * Return true if the variable name is writable
     * @param string $name
     * @return boolean
     */
    private function RDP_isWritableVariable($name)
    {
        return ($this->GetVarAttribute($name, 'readWrite', 'N') == 'Y');
    }

    /**
     * Process an expression and return its boolean value
     * @param string $expr
     * @param int $groupSeq - needed to determine whether using variables before they are declared
     * @param int $questionSeq - needed to determine whether using variables before they are declared
     * @return boolean
     */
    public function ProcessBooleanExpression($expr,$groupSeq=-1,$questionSeq=-1)
    {
        $this->groupSeq = $groupSeq;
        $this->questionSeq = $questionSeq;

        $expr = $this->ExpandThisVar($expr);
        $status = $this->RDP_Evaluate($expr);
        if (!$status) {
            return false;    // if there are errors in the expression, hide it?
        }
        $result = $this->GetResult();
        if (is_null($result)) {
            return false;    // if there are errors in the expression, hide it?
        }
//        if ($result == 'false') {
//            return false;    // since the string 'false' is not considered boolean false, but an expression in JavaScript can return 'false'
//        }
//        return !empty($result);

        // Check whether any variables are irrelevant - making this comparable to JavaScript which uses LEManyNA(varlist) to do the same thing
        foreach ($this->GetVarsUsed() as $var)    // this function wants to see the NAOK suffix
        {
            if (!preg_match("/^.*\.(NAOK|relevanceStatus)$/", $var))
            {
                if (!LimeExpressionManager::GetVarAttribute($var,'relevanceStatus',false,$groupSeq,$questionSeq))
                {
                    return false;
                }
            }
        }
        return (boolean) $result;
    }

    /**
     * Start processing a group of substitions - will be incrementally numbered
     */

    public function StartProcessingGroup($sid=NULL,$rooturl='',$hyperlinkSyntaxHighlighting=true)
    {
        $this->substitutionNum=0;
        $this->substitutionInfo=array(); // array of JavaScripts for managing each substitution
        $this->sid=$sid;
        $this->hyperlinkSyntaxHighlighting=$hyperlinkSyntaxHighlighting;
    }

    /**
     * Clear cache of tailoring content.
     * When re-displaying same page, need to avoid generating double the amount of tailoring content.
     */
    public function ClearSubstitutionInfo()
    {
        $this->substitutionNum=0;
        $this->substitutionInfo=array(); // array of JavaScripts for managing each substitution
    }

    /**
     * Process multiple substitution iterations of a full string, containing multiple expressions delimited by {}, return a consolidated string
     * @param string $src
     * @param int $questionNum
     * @param int $numRecursionLevels - number of levels of recursive substitution to perform
     * @param int $whichPrettyPrintIteration - if recursing, specify which pretty-print iteration is desired
     * @param int $groupSeq - needed to determine whether using variables before they are declared
     * @param int $questionSeq - needed to determine whether using variables before they are declared
     * @param boolean $staticReplacement
     * @return string
     */
    public function sProcessStringContainingExpressions($src, $questionNum=0, $numRecursionLevels=1, $whichPrettyPrintIteration=1, $groupSeq=-1, $questionSeq=-1, $staticReplacement=false)
    {
        // tokenize string by the {} pattern, properly dealing with strings in quotations, and escaped curly brace values
        $this->allVarsUsed = array();
        $this->questionSeq = $questionSeq;
        $this->groupSeq = $groupSeq;
        $result = $src;
        $prettyPrint = '';
        $errors = array();

        for($i=1;$i<=$numRecursionLevels;++$i)
        {
            // TODO - Since want to use <span> for dynamic substitution, what if there are recursive substititons?
            $result = $this->sProcessStringContainingExpressionsHelper($result ,$questionNum, $staticReplacement);
            if ($i == $whichPrettyPrintIteration)
            {
                $prettyPrint = $this->prettyPrintSource;
            }
            $errors = array_merge($errors, $this->RDP_errs);
        }
        $this->prettyPrintSource = $prettyPrint;    // ensure that if doing recursive substition, can get original source to pretty print
        $this->RDP_errs = $errors;
        $result = str_replace(array('\{', '\}',), array('{', '}'), $result);
        return $result;
    }

    /**
     * Process one substitution iteration of a full string, containing multiple expressions delimited by {}, return a consolidated string
     * @param string $src
     * @param integer $questionNum - used to generate substitution <span>s that indicate to which question they belong
     * @param boolean $staticReplacement
     * @return string
     */
    public function sProcessStringContainingExpressionsHelper($src, $questionNum, $staticReplacement=false)
    {
        // tokenize string by the {} pattern, properly dealing with strings in quotations, and escaped curly brace values
        $stringParts = $this->asSplitStringOnExpressions($src);
        $resolvedParts = array();
        $prettyPrintParts = array();
        $allErrors=array();

        foreach ($stringParts as $stringPart)
        {
            if ($stringPart[2] == 'STRING') {
                $resolvedParts[] =  $stringPart[0];
                $prettyPrintParts[] = $stringPart[0];
            }
            else {
                ++$this->substitutionNum;
                $expr = $this->ExpandThisVar(substr($stringPart[0],1,-1));
                if ($this->RDP_Evaluate($expr))
                {
                    $resolvedPart = $this->GetResult();
                }
                else
                {
                    // show original and errors in-line only if user have the rigth to update survey content
                    if($this->sid && Permission::model()->hasSurveyPermission($this->sid, 'surveycontent', 'update'))
                    {
                        $resolvedPart = $this->GetPrettyPrintString();
                    }
                    else
                    {
                        $resolvedPart = '';
                    }
                    $allErrors[] = $this->GetErrors();
                }
                $onpageJsVarsUsed = $this->GetOnPageJsVarsUsed();
                $jsVarsUsed = $this->GetJsVarsUsed();
                $prettyPrintParts[] = $this->GetPrettyPrintString();
                $this->allVarsUsed = array_merge($this->allVarsUsed,$this->GetVarsUsed());

                if (count($onpageJsVarsUsed) > 0 && !$staticReplacement)
                {
                    $idName = "LEMtailor_Q_" . $questionNum . "_" . $this->substitutionNum;
//                    $resolvedParts[] = "<span id='" . $idName . "'>" . htmlspecialchars($resolvedPart,ENT_QUOTES,'UTF-8',false) . "</span>"; // TODO - encode within SPAN?
                    $resolvedParts[] = "<span id='" . $idName . "'>" . $resolvedPart . "</span>";
                    $this->substitutionVars[$idName] = 1;
                    $this->substitutionInfo[] = array(
                        'questionNum' => $questionNum,
                        'num' => $this->substitutionNum,
                        'id' => $idName,
                        'raw' => $stringPart[0],
                        'result' => $resolvedPart,
                        'vars' => implode('|',$jsVarsUsed),
                        'js' => $this->GetJavaScriptFunctionForReplacement($questionNum, $idName, $expr),
                    );
                }
                else
                {
                    $resolvedParts[] = $resolvedPart;
                }
            }
        }
        $result = implode('',$this->flatten_array($resolvedParts));
        $this->prettyPrintSource = implode('',$this->flatten_array($prettyPrintParts));
        $this->RDP_errs = $allErrors;   // so that has all errors from this string
        return $result;    // recurse in case there are nested ones, avoiding infinite loops?
    }

    /**
     * If the equation contains refernece to this, expand to comma separated list if needed.
     * @param string $src
     */
    function ExpandThisVar($src)
    {
        $splitter = '(?:\b(?:self|that))(?:\.(?:[A-Z0-9_]+))*';
        $parts = preg_split("/(" . $splitter . ")/i",$src,-1,(PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE));
        $result = '';
        foreach ($parts as $part)
        {
            if (preg_match("/" . $splitter . "/",$part))
            {
                $result .= LimeExpressionManager::GetAllVarNamesForQ($this->questionSeq,$part);
            }
            else
            {
                $result .= $part;
            }
        }

        return $result;
    }

    /**
     * Get info about all <span> elements needed for dynamic tailoring
     * @return array
     */
    public function GetCurrentSubstitutionInfo()
    {
        return $this->substitutionInfo;
    }

    /**
     * Flatten out an array, keeping it in the proper order
     * @param array $a
     * @return array
     */
    private function flatten_array(array $a) {
        $i = 0;
        while ($i < count($a)) {
            if (is_array($a[$i])) {
                array_splice($a, $i, 1, $a[$i]);
            } else {
                $i++;
            }
        }
        return $a;
    }


    /**
     * Run a registered function
     * Some PHP functions require specific data types - those can be cast here.
     * @param array $funcNameToken
     * @param array $params
     * @return boolean|null
     */
    private function RDP_RunFunction($funcNameToken,$params)
    {
        $name = $funcNameToken[0];
        if (!$this->RDP_isValidFunction($name))
        {
            return false;
        }
        $func = $this->RDP_ValidFunctions[$name];
        $funcName = $func[0];
        $numArgs = count($params);
        $result=1;  // default value for $this->RDP_onlyparse
        if (function_exists($funcName)) {
            $numArgsAllowed = array_slice($func, 5);    // get array of allowable argument counts from end of $func
            $argsPassed = is_array($params) ? count($params) : 0;

            // for unlimited #  parameters (any value less than 0).
            try
            {
                if ($numArgsAllowed[0] < 0) {
                    $minArgs = abs($numArgsAllowed[0] + 1); // so if value is -2, means that requires at least one argument
                    if ($argsPassed < $minArgs)
                    {
                        $this->RDP_AddError(sprintf(Yii::t("Function must have at least %s argument|Function must have at least %s arguments",$minArgs), $minArgs), $funcNameToken);
                        return false;
                    }
                    if (!$this->RDP_onlyparse) {
                        switch($funcName) {
                            case 'sprintf':
                                // PHP doesn't let you pass array of parameters to function, so must use call_user_func_array
                                $result = call_user_func_array('sprintf',$params);
                                break;
                            default:
                                $result = $funcName($params);
                                break;
                        }
                    }
                // Call  function with the params passed
                } elseif (in_array($argsPassed, $numArgsAllowed)) {
                    switch ($argsPassed) {
                    case 0:
                        if (!$this->RDP_onlyparse) {
                            $result = $funcName();
                        }
                        break;
                    case 1:
                        if (!$this->RDP_onlyparse) {
                            switch($funcName) {
                                case 'acos':
                                case 'asin':
                                case 'atan':
                                case 'cos':
                                case 'exp':
                                case 'is_nan':
                                case 'sin':
                                case 'sqrt':
                                case 'tan':
                                    if (is_numeric($params[0]))
                                    {
                                        $result = $funcName(floatval($params[0]));
                                    }
                                    else
                                    {
                                        $result = NAN;
                                    }
                                    break;
                                default:
                                    $result = $funcName($params[0]);
                                    break;
                            }
                        }
                        break;
                    case 2:
                        if (!$this->RDP_onlyparse) {
                            switch($funcName) {
                                case 'atan2':
                                    if (is_numeric($params[0]) && is_numeric($params[1]))
                                    {
                                        $result = $funcName(floatval($params[0]),floatval($params[1]));
                                    }
                                    else
                                    {
                                        $result = NAN;
                                    }
                                    break;
                                default:
                                    $result = $funcName($params[0], $params[1]);
                                     break;
                            }
                        }
                        break;
                    case 3:
                        if (!$this->RDP_onlyparse) {
                            $result = $funcName($params[0], $params[1], $params[2]);
                        }
                        break;
                    case 4:
                        if (!$this->RDP_onlyparse) {
                            $result = $funcName($params[0], $params[1], $params[2], $params[3]);
                        }
                        break;
                    case 5:
                        if (!$this->RDP_onlyparse) {
                            $result = $funcName($params[0], $params[1], $params[2], $params[3], $params[4]);
                        }
                        break;
                    case 6:
                        if (!$this->RDP_onlyparse) {
                            $result = $funcName($params[0], $params[1], $params[2], $params[3], $params[4], $params[5]);
                        }
                        break;
                    default:
                        $this->RDP_AddError(sprintf(self::gT("Unsupported number of arguments: %s"), $argsPassed), $funcNameToken);
                        return false;
                    }

                } else {
                    $this->RDP_AddError(sprintf(self::gT("Function does not support %s arguments"), $argsPassed).' '
                            . sprintf(self::gT("Function supports this many arguments, where -1=unlimited: %s"), implode(',', $numArgsAllowed)), $funcNameToken);
                    return false;
                }
                if(function_exists("geterrors_".$funcName))
                {
                    if($sError=call_user_func_array("geterrors_".$funcName,$params))
                    {
                        $this->RDP_AddError($sError,$funcNameToken);
                        return false;
                    }
                }
            }
            catch (Exception $e)
            {
                $this->RDP_AddError($e->getMessage(),$funcNameToken);
                return false;
            }
            $token = array($result,$funcNameToken[1],'NUMBER');
            $this->RDP_StackPush($token);
            return true;
        }
    }

    /**
     * Add user functions to array of allowable functions within the equation.
     * $functions is an array of key to value mappings like this:
     * See $this->RDP_ValidFunctions for examples of the syntax
     * @param array $functions
     */

    public function RegisterFunctions(array $functions) {
        $this->RDP_ValidFunctions= array_merge($this->RDP_ValidFunctions, $functions);
    }

    /**
     * Set the value of a registered variable
     * @param string $op - the operator (=,*=,/=,+=,-=)
     * @param string $name
     * @param string $value
     * @return int
     */
    private function RDP_SetVariableValue($op,$name,$value)
    {
        if ($this->RDP_onlyparse)
        {
            return 1;
        }
        return LimeExpressionManager::SetVariableValue($op, $name, $value);
    }

  /**
     * Split a soure string into STRING vs. EXPRESSION, where the latter is surrounded by unescaped curly braces.
     * This verson properly handles nested curly braces and curly braces within strings within curly braces - both of which are needed to better support JavaScript
     * Users still need to add a space or carriage return after opening braces (and ideally before closing braces too) to avoid  having them treated as expressions.
     * @param string $src
     * @return string
     */
    public function asSplitStringOnExpressions($src)
    {

        $parts = preg_split($this->RDP_ExpressionRegex,$src,-1,(PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE));


        $count = count($parts);
        $tokens = array();
        $inSQString=false;
        $inDQString=false;
        $curlyDepth=0;
        $thistoken=array();
        $offset=0;
        for ($j=0;$j<$count;++$j)
        {
            switch($parts[$j])
            {
                case '{':
                    if ($j < ($count-1) && preg_match('/\s|\n|\r/',substr($parts[$j+1],0,1)))
                    {
                        // don't count this as an expression if the opening brace is followed by whitespace
                        $thistoken[] = '{';
                        $thistoken[] = $parts[++$j];
                    }
                    else if ($inDQString || $inSQString)
                    {
                        // just push the curly brace
                        $thistoken[] = '{';
                    }
                    else if ($curlyDepth>0)
                    {
                        // a nested curly brace - just push it
                        $thistoken[] = '{';
                        ++$curlyDepth;
                    }
                    else
                    {
                        // then starting an expression - save the out-of-expression string
                        if (count($thistoken) > 0)
                        {
                            $_token = implode('',$thistoken);
                            $tokens[] = array(
                                $_token,
                                $offset,
                                'STRING'
                                );
                            $offset += strlen($_token);
                        }
                        $curlyDepth=1;
                        $thistoken = array();
                        $thistoken[] = '{';
                    }
                    break;
                case '}':
                    // don't count this as an expression if the closing brace is preceded by whitespace
                    if ($j > 0 && preg_match('/\s|\n|\r/',substr($parts[$j-1],-1,1)))
                    {
                        $thistoken[] = '}';
                    }
                    else if ($curlyDepth==0)
                    {
                        // just push the token
                        $thistoken[] = '}';
                    }
                    else
                    {
                        if ($inSQString || $inDQString)
                        {
                            // just push the token
                            $thistoken[] = '}';
                        }
                        else
                        {
                            --$curlyDepth;
                            if ($curlyDepth==0)
                            {
                                // then closing expression
                                $thistoken[] = '}';
                                $_token = implode('',$thistoken);
                                $tokens[] = array(
                                    $_token,
                                    $offset,
                                    'EXPRESSION'
                                    );
                                $offset += strlen($_token);
                                $thistoken=array();
                            }
                            else
                            {
                                // just push the token
                                $thistoken[] = '}';
                            }
                        }
                    }
                    break;
                case '\'':
                    $thistoken[] = '\'';
                    if ($curlyDepth==0)
                    {
                        // only counts as part of a string if it is already within an expression
                    }
                    else
                    {
                        if ($inDQString)
                        {
                            // then just push the single quote
                        }
                        else
                        {
                            if ($inSQString) {
                                $inSQString=false;  // finishing a single-quoted string
                            }
                            else {
                                $inSQString=true;   // starting a single-quoted string
                            }
                        }
                    }
                    break;
                case '"':
                    $thistoken[] = '"';
                    if ($curlyDepth==0)
                    {
                        // only counts as part of a string if it is already within an expression
                    }
                    else
                    {
                        if ($inSQString)
                        {
                            // then just push the double quote
                        }
                        else
                        {
                            if ($inDQString) {
                                $inDQString=false;  // finishing a double-quoted string
                            }
                            else {
                                $inDQString=true;   // starting a double-quoted string
                            }
                        }
                    }
                    break;
                case '\\':
                    if ($j < ($count-1)) {
                        $thistoken[] = $parts[$j++];
                        $thistoken[] = $parts[$j];
                    }
                    break;
                default:
                    $thistoken[] = $parts[$j];
                    break;
            }
        }
        if (count($thistoken) > 0)
        {
            $tokens[] = array(
                implode('',$thistoken),
                $offset,
                'STRING',
            );
        }
        return $tokens;
    }

    /**
     * Specify the survey  mode for this survey.  Options are 'survey', 'group', and 'question'
     * @param string $mode
     */
    public function SetSurveyMode($mode)
    {
        if (preg_match('/^group|question|survey$/',$mode))
        {
            $this->surveyMode = $mode;
        }
    }


    /**
     * Pop a value token off of the stack
     * @return token
     */
    public function RDP_StackPop()
    {
        if (count($this->RDP_stack) > 0)
        {
            return array_pop($this->RDP_stack);
        }
        else
        {
            $this->RDP_AddError(self::gT("Tried to pop value off of empty stack"), NULL);
            return NULL;
        }
    }

    /**
     * Stack only holds values (number, string), not operators
     * @param array $token
     */
    public function RDP_StackPush(array $token)
    {
        if ($this->RDP_onlyparse)
        {
            // If only parsing, still want to validate syntax, so use "1" for all variables
            switch($token[2])
            {
                case 'DQ_STRING':
                case 'SQ_STRING':
                    $this->RDP_stack[] = array(1,$token[1],$token[2]);
                    break;
                case 'NUMBER':
                default:
                    $this->RDP_stack[] = array(1,$token[1],'NUMBER');
                    break;
            }
        }
        else
        {
            $this->RDP_stack[] = $token;
        }
    }

    /**
    * Public call of RDP_Tokenize
    *
    * @param string $sSource : the string to tokenize
    * @param bool $bOnEdit : on edition, actually don't remove space
    * @return array
    */
    public function Tokenize($sSource,$bOnEdit)
    {
        return $this->RDP_Tokenize($sSource,$bOnEdit);
    }

    /**
    * Split the source string into tokens, removing whitespace, and categorizing them by type.
    *
    * @param string $sSource : the string to tokenize
    * @param bool $bOnEdit : on edition, actually don't remove space
    * @return array
    */
    private function RDP_Tokenize($sSource,$bOnEdit=false)
    {
        // $aInitTokens = array of tokens from equation, showing value and offset position.  Will include SPACE.
        if($bOnEdit)
            $aInitTokens = preg_split($this->RDP_TokenizerRegex,$sSource,-1,(PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE));
        else
            $aInitTokens = preg_split($this->RDP_TokenizerRegex,$sSource,-1,(PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_OFFSET_CAPTURE));

        // $aTokens = array of tokens from equation, showing value, offsete position, and type.  Will not contain SPACE if !$bOnEdit, but will contain OTHER
        $aTokens = array();
        // Add token_type to $tokens:  For each token, test each categorization in order - first match will be the best.
        for ($j=0;$j<count($aInitTokens);++$j)
        {
            for ($i=0;$i<count($this->RDP_CategorizeTokensRegex);++$i)
            {
                $sToken = $aInitTokens[$j][0];
                if (preg_match($this->RDP_CategorizeTokensRegex[$i],$sToken))
                {
                    if ($this->RDP_TokenType[$i] !== 'SPACE' || $bOnEdit) {
                        $aInitTokens[$j][2] = $this->RDP_TokenType[$i];
                        if ($this->RDP_TokenType[$i] == 'DQ_STRING' || $this->RDP_TokenType[$i] == 'SQ_STRING')
                        {
                            // remove outside quotes
                            $sUnquotedToken = str_replace(array('\"',"\'","\\\\"),array('"',"'",'\\'),substr($sToken,1,-1));
                            $aInitTokens[$j][0] = $sUnquotedToken;
                        }
                        $aTokens[] = $aInitTokens[$j];   // get first matching non-SPACE token type and push onto $tokens array
                    }
                    break;  // only get first matching token type
                }
            }
        }
        return $aTokens;
    }


    /**
     * Show a table of allowable Expression Manager functions
     * @return string
     */
    static function ShowAllowableFunctions()
    {
        $em = new ExpressionManager();
        $output = "<h3>Functions Available within Expression Manager</h3>\n";
        $output .= "<table border='1'><tr><th>Function</th><th>Meaning</th><th>Syntax</th><th>Reference</th></tr>\n";
        foreach ($em->RDP_ValidFunctions as $name => $func) {
            $output .= "<tr><td>" . $name . "</td><td>" . $func[2] . "</td><td>" . $func[3] . "</td><td><a href='" . $func[4] . "'>" . $func[4] . "</a>&nbsp;</td></tr>\n";
        }
        $output .= "</table>\n";
        return $output;
    }

    /**
     * Show a translated string for admin user, always in admin language #12208
     * public for geterrors_exprmgr_regexMatch function only
     * @param string $string to translate
     * @return string : translated string
     */
    public static function gT($string)
    {
        /**
         * @var string|null $baseLang set the previous language if need to be set
         */
        $baseLang=null;
        if(Yii::app() instanceof CWebApplication && Yii::app()->session['adminlang']){
            $baseLang=Yii::app()->getLanguage();
            Yii::app()->setLanguage(Yii::app()->session['adminlang']);
        }
        $string=gT($string);
        if($baseLang){
            Yii::app()->setLanguage($baseLang);
        }
        return $string;
    }
}

/**
 * Used by usort() to order Error tokens by their position within the string
 * This must be outside of the class in order to work in PHP 5.2
 * @param array $a
 * @param array $b
 * @return int
 */
function cmpErrorTokens($a, $b)
{
    if (is_null($a[1])) {
        if (is_null($b[1])) {
            return 0;
        }
        return 1;
    }
    if (is_null($b[1])) {
        return -1;
    }
    if ($a[1][1] == $b[1][1]) {
        return 0;
    }
    return ($a[1][1] < $b[1][1]) ? -1 : 1;
}

/**
 * Count the number of answered questions (non-empty)
 * @param array $args
 * @return int
 */
function exprmgr_count($args)
{
    $j=0;    // keep track of how many non-null values seen
    foreach ($args as $arg)
    {
        if ($arg != '') {
            ++$j;
        }
    }
    return $j;
}

/**
 * Count the number of answered questions (non-empty) which match the first argument
 * @param array $args
 * @return int
 */
function exprmgr_countif($args)
{
    $j=0;    // keep track of how many non-null values seen
    $match = array_shift($args);
    foreach ($args as $arg)
    {
        if ($arg == $match) {
            ++$j;
        }
    }
    return $j;
}

/**
 * Count the number of answered questions (non-empty) which meet the criteria (arg op value)
 * @param array $args
 * @return int
 */
function exprmgr_countifop($args)
{
    $j=0;
    $op = array_shift($args);
    $value = array_shift($args);
    foreach ($args as $arg)
    {
        switch($op)
        {
            case '==':  case 'eq': if ($arg == $value) { ++$j; } break;
            case '>=':  case 'ge': if ($arg >= $value) { ++$j; } break;
            case '>':   case 'gt': if ($arg > $value) { ++$j; } break;
            case '<=':  case 'le': if ($arg <= $value) { ++$j; } break;
            case '<':   case 'lt': if ($arg < $value) { ++$j; } break;
            case '!=':  case 'ne': if ($arg != $value) { ++$j; } break;
            case 'RX':
                try {
                    if (@preg_match($value, $arg))
                    {
                        ++$j;
                    }
                }
                catch (Exception $e) {
                    // Do nothing
                }
                break;
        }
    }
    return $j;
}
/**
 * Find position of first occurrence of unicode string in a unicode string, case insensitive
 * @param string $haystack : checked string
 * @param string $needle : string to find
 * @param $offset : offset
 * @return int|false : position or false if not found
 */
function exprmgr_stripos($haystack , $needle ,$offset=0)
{
    if($offset > mb_strlen($haystack))
        return false;
    return mb_stripos($haystack , $needle ,$offset,'UTF-8');
}
/**
 * Finds first occurrence of a unicode string within another, case-insensitive
 * @param string $haystack : checked string
 * @param string $needle : string to find
 * @param boolean $before_needle : portion to return
 * @return string|false
 */
function exprmgr_stristr($haystack,$needle,$before_needle=false)
{
    return mb_stristr($haystack,$needle,$before_needle,'UTF-8');
}
/**
 * Get unicode string length
 * @param string $string
 * @return int
 */
function exprmgr_strlen($string)
{
    return mb_strlen ($string,'UTF-8');
}
/**
 * Find position of first occurrence of unicode string in a unicode string
 * @param string $haystack : checked string
 * @param string $needle : string to find
 * @param int $offset : offset
 * @return int|false : position or false if not found
 */
function exprmgr_strpos($haystack , $needle ,$offset=0)
{
    if($offset > mb_strlen($haystack))
        return false;
    return mb_strpos($haystack , $needle ,$offset,'UTF-8');
}
/**
 * Finds first occurrence of a unicode string within another
 * @param string $haystack : checked string
 * @param string $needle : string to find
 * @param boolean $before_needle : portion to return
 * @return string|false
 */
function exprmgr_strstr($haystack,$needle,$before_needle=false)
{
    return mb_strstr($haystack,$needle,$before_needle,'UTF-8');
}
/**
 * Make an unicode string lowercase
 * @param string $string
 * @return string
 */
function exprmgr_strtolower($string)
{
    return mb_strtolower ($string,'UTF-8');
}
/**
 * Make an unicode string uppercase
 * @param string $string
 * @return string
 */
function exprmgr_strtoupper($string)
{
    return mb_strtoupper ($string,'UTF-8');
}
/**
 * Get part of unicode string
 * @param string $string
 * @param int $start
 * @param int $end
 * @return string
 */
function exprmgr_substr($string,$start,$end=null)
{
    return mb_substr($string,$start,$end,'UTF-8');
}
/**
 * Sum of values of answered questions which meet the criteria (arg op value)
 * @param array $args
 * @return int
 */
function exprmgr_sumifop($args)
{
    $result=0;
    $op = array_shift($args);
    $value = array_shift($args);
    foreach ($args as $arg)
    {
        switch($op)
        {
            case '==':  case 'eq': if ($arg == $value) { $result += $arg; } break;
            case '>=':  case 'ge': if ($arg >= $value) { $result += $arg; } break;
            case '>':   case 'gt': if ($arg > $value) { $result += $arg; } break;
            case '<=':  case 'le': if ($arg <= $value) { $result += $arg; } break;
            case '<':   case 'lt': if ($arg < $value) { $result += $arg; } break;
            case '!=':  case 'ne': if ($arg != $value) { $result += $arg; } break;
            case 'RX':
                try {
                    if (@preg_match($value, $arg))
                    {
                        $result += $arg;
                    }
                }
                catch (Exception $e) {
                    // Do nothing
                }
                break;
        }
    }
    return $result;
}

/**
 * Find the closest matching numerical input values in a list an replace it by the
 * corresponding value within another list
 *
 * @author Johannes Weberhofer, 2013
 *
 * @param numeric $fValueToReplace
 * @param numeric $iStrict - 1 for exact matches only otherwise interpolation the
 * 		  closest value should be returned
 * @param string $sTranslateFromList - comma seperated list of numeric values to translate from
 * @param string $sTranslateToList - comma seperated list of numeric values to translate to
 * @return numeric
 */
function exprmgr_convert_value($fValueToReplace, $iStrict, $sTranslateFromList, $sTranslateToList)
{
    if ( (is_numeric($fValueToReplace)) && ($iStrict!=null) && ($sTranslateFromList!=null) && ($sTranslateToList!=null) )
    {
        $aFromValues = explode( ',', $sTranslateFromList);
        $aToValues = explode( ',', $sTranslateToList);
        if ( (count($aFromValues) > 0)  && (count($aFromValues) == count($aToValues)) )
        {
            $fMinimumDiff = null;
            $iNearestIndex = 0;
            for ( $i = 0; $i < count($aFromValues); $i++) {
                if ( !is_numeric($aFromValues[$i])) {
                    // break processing when non-numeric variables are about to be processed
                    return null;
                }
                $fCurrentDiff = abs($aFromValues[$i] - $fValueToReplace);
                if ($fCurrentDiff === 0) {
                    return $aToValues[$i];
                } else if ($i === 0) {
                    $fMinimumDiff = $fCurrentDiff;
                } else if ( $fMinimumDiff > $fCurrentDiff ) {
                    $fMinimumDiff = $fCurrentDiff;
                    $iNearestIndex = $i;
                }
            }
            if ( $iStrict != 1 ) {
                return $aToValues[$iNearestIndex];
            }
        }
    }
    return null;
}

/**
 * If $test is true, return $ok, else return $error
 * @param mixed $test
 * @param mixed $ok
 * @param mixed $error
 * @return mixed
 */
function exprmgr_if($test,$ok,$error)
{
    if ($test)
    {
        return $ok;
    }
    else
    {
        return $error;
    }
}

/**
 * Return true if the variable is an integer for LimeSurvey
 * Can not really use is_int due to SQL DECIMAL system. This function can surely be improved
 * @param string $arg
 * @return integer
 * @link http://php.net/is_int#82857
 */
function exprmgr_int($arg)
{
    if(strpos($arg,"."))
    {
        $arg=preg_replace("/\.$/","",rtrim(strval($arg),"0"));// DECIMAL from SQL return always .00000000, the remove all 0 and one . , see #09550
    }
    return (preg_match("/^-?[0-9]*$/",$arg));// Allow 000 for value, @link https://bugs.limesurvey.org/view.php?id=9550 DECIMAL sql type.
}
/**
 * Join together $args[0-N] with ', '
 * @param array $args
 * @return string
 */
function exprmgr_list($args)
{
    $result="";
    $j=1;    // keep track of how many non-null values seen
    foreach ($args as $arg)
    {
        if ($arg != '') {
            if ($j > 1) {
                $result .= ', ' . $arg;
            }
            else {
                $result .= $arg;
            }
            ++$j;
        }
    }
    return $result;
}

/**
 * return log($arg[0],$arg[1]=e)
 * @param array $args
 * @return float
 */
function exprmgr_log($args)
{
    if (count($args) < 1)
    {
        return NAN;
    }
    $number=$args[0];
    if(!is_numeric($number)){return NAN;}
    $base=(isset($args[1]))?$args[1]:exp(1);
    if(!is_numeric($base)){return NAN;}
    if(floatval($base)<=0){return NAN;}
    return log($number,$base);
}
/**
 * Get Unix timestamp for a date : false if parameters is invalid.
 * PHP 5.3.3 send E_STRICT notice without param, then replace by time if needed
 * @param int $hour
 * @param int $minute
 * @param int $second
 * @param int $month
 * @param int $day
 * @param int $year
 * @return int|boolean
 */
function exprmgr_mktime($hour=null,$minute=null,$second=null,$month=null,$day=null,$year=null)
{
    $iNumArg=count(array_filter(array($hour,$minute,$second,$month,$day,$year),create_function('$a','return $a !== null;')));
    switch($iNumArg)
    {
        case 0:
            return time();
        case 1:
            return mktime($hour);
        case 2:
            return mktime($hour,$minute);
        case 3:
            return mktime($hour,$minute,$second);
        case 4:
            return mktime($hour,$minute,$second,$month);
        case 5:
            return mktime($hour,$minute,$second,$month,$day);
        default:
            return mktime($hour,$minute,$second,$month,$day,$year);
    }
}

/**
 * Join together $args[N]
 * @param array $args
 * @return string
 */
function exprmgr_join($args)
{
    return implode("",$args);
}

/**
 * Join together $args[1-N] with $arg[0]
 * @param array $args
 * @return string
 */
function exprmgr_implode($args)
{
    if (count($args) <= 1)
    {
        return "";
    }
    $joiner = array_shift($args);
    return implode($joiner,$args);
}

/**
 * Return true if the variable is NULL or blank.
 * @param null|string|boolean $arg
 * @return boolean
 */
function exprmgr_empty($arg)
{
    if ($arg === NULL || $arg === "" || $arg === false) {
        return true;
    }
    return false;
}

/**
 * Compute the Sample Standard Deviation of a set of numbers ($args[0-N])
 * @param array $args
 * @return float
 */
function exprmgr_stddev($args)
{
    $vals = array();
    foreach ($args as $arg)
    {
        if (is_numeric($arg)) {
            $vals[] = $arg;
        }
    }
    $count = count($vals);
    if ($count <= 1) {
        return 0;   // what should default value be?
    }
    $sum = 0;
    foreach ($vals as $val) {
        $sum += $val;
    }
    $mean = $sum / $count;

    $sumsqmeans = 0;
    foreach ($vals as $val)
    {
        $sumsqmeans += ($val - $mean) * ($val - $mean);
    }
    $stddev = sqrt($sumsqmeans / ($count-1));
    return $stddev;
}

/**
 * Javascript equivalent does not cope well with ENT_QUOTES and related PHP constants, so set default to ENT_QUOTES
 * @param string $string
 * @return string
 */
function expr_mgr_htmlspecialchars($string)
{
    return htmlspecialchars($string,ENT_QUOTES);
}

/**
 * Javascript equivalent does not cope well with ENT_QUOTES and related PHP constants, so set default to ENT_QUOTES
 * @param string $string
 * @return string
 */
function expr_mgr_htmlspecialchars_decode($string)
{
    return htmlspecialchars_decode($string,ENT_QUOTES);
}

/**
 * Return true if $input matches the regular expression $pattern
 * @param string $pattern
 * @param string $input
 * @return boolean
 */
function exprmgr_regexMatch($pattern, $input)
{
    // Test the regexp pattern agains null : must always return 0, false if error happen
    if(@preg_match($pattern.'u', null) === false)
    {
        return false; // invalid : true or false ?
    }
    // 'u' is the regexp modifier for unicode so that non-ASCII string will be validated properly
    return preg_match($pattern.'u', $input);
}
/**
 * Return error information from pattern of regular expression $pattern
 * @param string $pattern
 * @param string $input
 * @return string|null
 */
function geterrors_exprmgr_regexMatch($pattern, $input)
{
    // @todo : use set_error_handler to get the preg_last_error
    if(@preg_match($pattern.'u', null) === false)
    {
        return sprintf(ExpressionManager::gT('Invalid PERL Regular Expression: %s'), htmlspecialchars($pattern));
    }
}

/**
 * Display number with comma as radix separator, if needed
 * @param string $value
 * @return string
 */
function exprmgr_fixnum($value)
{
    if (LimeExpressionManager::usingCommaAsRadix())
    {
        $newval = implode(',',explode('.',$value));
        return $newval;
    }
    return $value;
}
/**
 * Returns true if all non-empty values are unique
 * @param array $args
 * @return boolean
 */
function exprmgr_unique($args)
{
    $uniqs = array();
    foreach ($args as $arg)
    {
        if (trim($arg)=='')
        {
            continue;   // ignore blank answers
        }
        if (isset($uniqs[$arg]))
        {
            return false;
        }
        $uniqs[$arg]=1;
    }
    return true;
}
?>
