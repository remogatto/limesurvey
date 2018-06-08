-- Create the DB

DROP DATABASE IF EXISTS `limesurvey_test`;
CREATE DATABASE limesurvey_test;
USE limesurvey_test;

-- Only prefixed tables with: lime_
-- Date of Dump: 08-Jun-2018
--

-- --------------------------------------------------------

--
-- Table structure for table `lime_answers`
--

DROP TABLE IF EXISTS `lime_answers`;
CREATE TABLE `lime_answers` (
  `qid` int(11) NOT NULL DEFAULT '0',
  `code` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `answer` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `sortorder` int(11) NOT NULL,
  `assessment_value` int(11) NOT NULL DEFAULT '0',
  `language` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `scale_id` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`qid`,`code`,`language`,`scale_id`),
  KEY `answers_idx2` (`sortorder`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_answers`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_assessments`
--

DROP TABLE IF EXISTS `lime_assessments`;
CREATE TABLE `lime_assessments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sid` int(11) NOT NULL DEFAULT '0',
  `scope` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `gid` int(11) NOT NULL DEFAULT '0',
  `name` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `minimum` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `maximum` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `language` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  PRIMARY KEY (`id`,`language`),
  KEY `assessments_idx2` (`sid`),
  KEY `assessments_idx3` (`gid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_assessments`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_boxes`
--

DROP TABLE IF EXISTS `lime_boxes`;
CREATE TABLE `lime_boxes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `position` int(11) DEFAULT NULL COMMENT 'position of the box',
  `url` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'URL the box points',
  `title` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Box title',
  `ico` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'the ico name in font',
  `desc` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Box description',
  `page` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Page name where the box should be shown ',
  `usergroup` int(11) NOT NULL COMMENT 'Those boxes will be shown for that user group',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_boxes`
--

INSERT INTO `lime_boxes` (`id`,`position`,`url`,`title`,`ico`,`desc`,`page`,`usergroup`) VALUES
('1', '1', 'admin/survey/sa/newsurvey', 'Create survey', 'add', 'Create a new survey', 'welcome', '-2'),
('2', '2', 'admin/survey/sa/listsurveys', 'List surveys', 'list', 'List available surveys', 'welcome', '-1'),
('3', '3', 'admin/globalsettings', 'Global settings', 'settings', 'Edit global settings', 'welcome', '-2'),
('4', '4', 'admin/update', 'ComfortUpdate', 'shield', 'Stay safe and up to date', 'welcome', '-2'),
('5', '5', 'admin/labels/sa/view', 'Label sets', 'label', 'Edit label sets', 'welcome', '-2'),
('6', '6', 'admin/templates/sa/view', 'Template editor', 'templates', 'Edit LimeSurvey templates', 'welcome', '-2');


-- --------------------------------------------------------

--
-- Table structure for table `lime_conditions`
--

DROP TABLE IF EXISTS `lime_conditions`;
CREATE TABLE `lime_conditions` (
  `cid` int(11) NOT NULL AUTO_INCREMENT,
  `qid` int(11) NOT NULL DEFAULT '0',
  `cqid` int(11) NOT NULL DEFAULT '0',
  `cfieldname` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `method` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `value` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `scenario` int(11) NOT NULL DEFAULT '1',
  PRIMARY KEY (`cid`),
  KEY `conditions_idx2` (`qid`),
  KEY `conditions_idx3` (`cqid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_conditions`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_defaultvalues`
--

DROP TABLE IF EXISTS `lime_defaultvalues`;
CREATE TABLE `lime_defaultvalues` (
  `qid` int(11) NOT NULL DEFAULT '0',
  `scale_id` int(11) NOT NULL DEFAULT '0',
  `sqid` int(11) NOT NULL DEFAULT '0',
  `language` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `specialtype` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `defaultvalue` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`qid`,`specialtype`,`language`,`scale_id`,`sqid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_defaultvalues`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_expression_errors`
--

DROP TABLE IF EXISTS `lime_expression_errors`;
CREATE TABLE `lime_expression_errors` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `errortime` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sid` int(11) DEFAULT NULL,
  `gid` int(11) DEFAULT NULL,
  `qid` int(11) DEFAULT NULL,
  `gseq` int(11) DEFAULT NULL,
  `qseq` int(11) DEFAULT NULL,
  `type` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `eqn` text COLLATE utf8mb4_unicode_ci,
  `prettyprint` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_expression_errors`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_failed_login_attempts`
--

DROP TABLE IF EXISTS `lime_failed_login_attempts`;
CREATE TABLE `lime_failed_login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_attempt` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `number_attempts` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_failed_login_attempts`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_groups`
--

DROP TABLE IF EXISTS `lime_groups`;
CREATE TABLE `lime_groups` (
  `gid` int(11) NOT NULL AUTO_INCREMENT,
  `sid` int(11) NOT NULL DEFAULT '0',
  `group_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `group_order` int(11) NOT NULL DEFAULT '0',
  `description` text COLLATE utf8mb4_unicode_ci,
  `language` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `randomization_group` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `grelevance` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`gid`,`language`),
  KEY `groups_idx2` (`sid`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_groups`
--

INSERT INTO `lime_groups` (`gid`,`sid`,`group_name`,`group_order`,`description`,`language`,`randomization_group`,`grelevance`) VALUES
('1', '181911', 'Group 1', '0', '', 'it', '', ''),
('2', '297751', 'Group 2', '0', '', 'it', '', '');


-- --------------------------------------------------------

--
-- Table structure for table `lime_labels`
--

DROP TABLE IF EXISTS `lime_labels`;
CREATE TABLE `lime_labels` (
  `lid` int(11) NOT NULL DEFAULT '0',
  `code` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `title` text COLLATE utf8mb4_unicode_ci,
  `sortorder` int(11) NOT NULL,
  `language` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `assessment_value` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`lid`,`sortorder`,`language`),
  KEY `labels_code_idx` (`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_labels`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_labelsets`
--

DROP TABLE IF EXISTS `lime_labelsets`;
CREATE TABLE `lime_labelsets` (
  `lid` int(11) NOT NULL AUTO_INCREMENT,
  `label_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `languages` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT 'en',
  PRIMARY KEY (`lid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_labelsets`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_notifications`
--

DROP TABLE IF EXISTS `lime_notifications`;
CREATE TABLE `lime_notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Should be either survey or user',
  `entity_id` int(11) NOT NULL,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'new' COMMENT 'new or read',
  `importance` int(11) NOT NULL DEFAULT '1',
  `display_class` varchar(31) COLLATE utf8mb4_unicode_ci DEFAULT 'default' COMMENT 'Bootstrap class, like warning, info, success',
  `created` datetime NOT NULL,
  `first_read` datetime DEFAULT NULL,
  `hash` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `entity` (`entity`,`entity_id`,`status`),
  KEY `hash` (`hash`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_notifications`
--

INSERT INTO `lime_notifications` (`id`,`entity`,`entity_id`,`title`,`message`,`status`,`importance`,`display_class`,`created`,`first_read`,`hash`) VALUES
('1', 'user', '1', 'Nuovo aggiornamento disponibile (Versione corrente:170728)', 'E&#039; disponibile un aggiornamento di sicurezza.<a href=/index.php/admin/update>Fare click qui per utilizzare ComfortUpdate.</a>', 'read', '1', 'default', '2018-06-08 09:35:16', '2018-06-08 09:35:32', 'c2aabc4987fd335218a75a1de692b0054caa05274bec700c83537ce6e58e71f8');


-- --------------------------------------------------------

--
-- Table structure for table `lime_participant_attribute`
--

DROP TABLE IF EXISTS `lime_participant_attribute`;
CREATE TABLE `lime_participant_attribute` (
  `participant_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `attribute_id` int(11) NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`participant_id`,`attribute_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_participant_attribute`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_participant_attribute_names`
--

DROP TABLE IF EXISTS `lime_participant_attribute_names`;
CREATE TABLE `lime_participant_attribute_names` (
  `attribute_id` int(11) NOT NULL AUTO_INCREMENT,
  `attribute_type` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `defaultname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `visible` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`attribute_id`,`attribute_type`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_participant_attribute_names`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_participant_attribute_names_lang`
--

DROP TABLE IF EXISTS `lime_participant_attribute_names_lang`;
CREATE TABLE `lime_participant_attribute_names_lang` (
  `attribute_id` int(11) NOT NULL,
  `attribute_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `lang` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`attribute_id`,`lang`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_participant_attribute_names_lang`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_participant_attribute_values`
--

DROP TABLE IF EXISTS `lime_participant_attribute_values`;
CREATE TABLE `lime_participant_attribute_values` (
  `value_id` int(11) NOT NULL AUTO_INCREMENT,
  `attribute_id` int(11) NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`value_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_participant_attribute_values`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_participant_shares`
--

DROP TABLE IF EXISTS `lime_participant_shares`;
CREATE TABLE `lime_participant_shares` (
  `participant_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `share_uid` int(11) NOT NULL,
  `date_added` datetime NOT NULL,
  `can_edit` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`participant_id`,`share_uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_participant_shares`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_participants`
--

DROP TABLE IF EXISTS `lime_participants`;
CREATE TABLE `lime_participants` (
  `participant_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firstname` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastname` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` text COLLATE utf8mb4_unicode_ci,
  `language` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blacklisted` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_uid` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`participant_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_participants`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_permissions`
--

DROP TABLE IF EXISTS `lime_permissions`;
CREATE TABLE `lime_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `entity` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `entity_id` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  `permission` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `create_p` int(11) NOT NULL DEFAULT '0',
  `read_p` int(11) NOT NULL DEFAULT '0',
  `update_p` int(11) NOT NULL DEFAULT '0',
  `delete_p` int(11) NOT NULL DEFAULT '0',
  `import_p` int(11) NOT NULL DEFAULT '0',
  `export_p` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `idxPermissions` (`entity_id`,`entity`,`permission`,`uid`)
) ENGINE=MyISAM AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_permissions`
--

INSERT INTO `lime_permissions` (`id`,`entity`,`entity_id`,`uid`,`permission`,`create_p`,`read_p`,`update_p`,`delete_p`,`import_p`,`export_p`) VALUES
('1', 'global', '0', '1', 'superadmin', '0', '1', '0', '0', '0', '0'),
('2', 'survey', '181911', '1', 'surveyactivation', '0', '0', '1', '0', '0', '0'),
('3', 'survey', '181911', '1', 'surveycontent', '1', '1', '1', '1', '1', '1'),
('4', 'survey', '181911', '1', 'surveylocale', '0', '1', '1', '0', '0', '0'),
('5', 'survey', '181911', '1', 'survey', '0', '1', '0', '1', '0', '0'),
('6', 'survey', '181911', '1', 'tokens', '1', '1', '1', '1', '1', '1'),
('7', 'survey', '181911', '1', 'surveysettings', '0', '1', '1', '0', '0', '0'),
('8', 'survey', '181911', '1', 'quotas', '1', '1', '1', '1', '0', '0'),
('9', 'survey', '181911', '1', 'responses', '1', '1', '1', '1', '1', '1'),
('10', 'survey', '181911', '1', 'surveysecurity', '1', '1', '1', '1', '0', '0'),
('11', 'survey', '181911', '1', 'statistics', '0', '1', '0', '0', '0', '0'),
('12', 'survey', '181911', '1', 'translations', '0', '1', '1', '0', '0', '0'),
('13', 'survey', '181911', '1', 'assessments', '1', '1', '1', '1', '0', '0'),
('14', 'survey', '297751', '1', 'surveyactivation', '0', '0', '1', '0', '0', '0'),
('15', 'survey', '297751', '1', 'surveycontent', '1', '1', '1', '1', '1', '1'),
('16', 'survey', '297751', '1', 'surveylocale', '0', '1', '1', '0', '0', '0'),
('17', 'survey', '297751', '1', 'survey', '0', '1', '0', '1', '0', '0'),
('18', 'survey', '297751', '1', 'tokens', '1', '1', '1', '1', '1', '1'),
('19', 'survey', '297751', '1', 'surveysettings', '0', '1', '1', '0', '0', '0'),
('20', 'survey', '297751', '1', 'quotas', '1', '1', '1', '1', '0', '0'),
('21', 'survey', '297751', '1', 'responses', '1', '1', '1', '1', '1', '1'),
('22', 'survey', '297751', '1', 'surveysecurity', '1', '1', '1', '1', '0', '0'),
('23', 'survey', '297751', '1', 'statistics', '0', '1', '0', '0', '0', '0'),
('24', 'survey', '297751', '1', 'translations', '0', '1', '1', '0', '0', '0'),
('25', 'survey', '297751', '1', 'assessments', '1', '1', '1', '1', '0', '0');


-- --------------------------------------------------------

--
-- Table structure for table `lime_plugin_settings`
--

DROP TABLE IF EXISTS `lime_plugin_settings`;
CREATE TABLE `lime_plugin_settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `plugin_id` int(11) NOT NULL,
  `model` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `model_id` int(11) DEFAULT NULL,
  `key` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_plugin_settings`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_plugins`
--

DROP TABLE IF EXISTS `lime_plugins`;
CREATE TABLE `lime_plugins` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `active` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_plugins`
--

INSERT INTO `lime_plugins` (`id`,`name`,`active`) VALUES
('1', 'Authdb', '1');


-- --------------------------------------------------------

--
-- Table structure for table `lime_question_attributes`
--

DROP TABLE IF EXISTS `lime_question_attributes`;
CREATE TABLE `lime_question_attributes` (
  `qaid` int(11) NOT NULL AUTO_INCREMENT,
  `qid` int(11) NOT NULL DEFAULT '0',
  `attribute` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `value` text COLLATE utf8mb4_unicode_ci,
  `language` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`qaid`),
  KEY `question_attributes_idx2` (`qid`),
  KEY `question_attributes_idx3` (`attribute`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_question_attributes`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_questions`
--

DROP TABLE IF EXISTS `lime_questions`;
CREATE TABLE `lime_questions` (
  `qid` int(11) NOT NULL AUTO_INCREMENT,
  `parent_qid` int(11) NOT NULL DEFAULT '0',
  `sid` int(11) NOT NULL DEFAULT '0',
  `gid` int(11) NOT NULL DEFAULT '0',
  `type` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'T',
  `title` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `question` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `preg` text COLLATE utf8mb4_unicode_ci,
  `help` text COLLATE utf8mb4_unicode_ci,
  `other` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `mandatory` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `question_order` int(11) NOT NULL,
  `language` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `scale_id` int(11) NOT NULL DEFAULT '0',
  `same_default` int(11) NOT NULL DEFAULT '0' COMMENT 'Saves if user set to use the same default value across languages in default options dialog',
  `relevance` text COLLATE utf8mb4_unicode_ci,
  `modulename` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`qid`,`language`),
  KEY `questions_idx2` (`sid`),
  KEY `questions_idx3` (`gid`),
  KEY `questions_idx4` (`type`),
  KEY `parent_qid_idx` (`parent_qid`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_questions`
--

INSERT INTO `lime_questions` (`qid`,`parent_qid`,`sid`,`gid`,`type`,`title`,`question`,`preg`,`help`,`other`,`mandatory`,`question_order`,`language`,`scale_id`,`same_default`,`relevance`,`modulename`) VALUES
('1', '0', '181911', '1', 'T', 'q1', 'What\'s your name', '', '', 'N', 'N', '1', 'it', '0', '0', '1', NULL),
('2', '0', '297751', '2', 'S', 'q1', 'What\'s your name?', '', '', 'N', 'N', '1', 'it', '0', '0', '1', NULL);


-- --------------------------------------------------------

--
-- Table structure for table `lime_quota`
--

DROP TABLE IF EXISTS `lime_quota`;
CREATE TABLE `lime_quota` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sid` int(11) DEFAULT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `qlimit` int(11) DEFAULT NULL,
  `action` int(11) DEFAULT NULL,
  `active` int(11) NOT NULL DEFAULT '1',
  `autoload_url` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `quota_idx2` (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_quota`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_quota_languagesettings`
--

DROP TABLE IF EXISTS `lime_quota_languagesettings`;
CREATE TABLE `lime_quota_languagesettings` (
  `quotals_id` int(11) NOT NULL AUTO_INCREMENT,
  `quotals_quota_id` int(11) NOT NULL DEFAULT '0',
  `quotals_language` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `quotals_name` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quotals_message` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `quotals_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `quotals_urldescrip` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`quotals_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_quota_languagesettings`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_quota_members`
--

DROP TABLE IF EXISTS `lime_quota_members`;
CREATE TABLE `lime_quota_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sid` int(11) DEFAULT NULL,
  `qid` int(11) DEFAULT NULL,
  `quota_id` int(11) DEFAULT NULL,
  `code` varchar(11) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `sid` (`sid`,`qid`,`quota_id`,`code`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_quota_members`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_saved_control`
--

DROP TABLE IF EXISTS `lime_saved_control`;
CREATE TABLE `lime_saved_control` (
  `scid` int(11) NOT NULL AUTO_INCREMENT,
  `sid` int(11) NOT NULL DEFAULT '0',
  `srid` int(11) NOT NULL DEFAULT '0',
  `identifier` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `access_code` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(254) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ip` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `saved_thisstep` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `saved_date` datetime NOT NULL,
  `refurl` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`scid`),
  KEY `saved_control_idx2` (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_saved_control`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_sessions`
--

DROP TABLE IF EXISTS `lime_sessions`;
CREATE TABLE `lime_sessions` (
  `id` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expire` int(11) DEFAULT NULL,
  `data` longblob,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_sessions`
--

INSERT INTO `lime_sessions` (`id`,`expire`,`data`) VALUES
('gstsbwijhwxzvcxq2rsgcg78uwkn75km', '1528457826', 'admin'),
('iau3qraxhhztw95adttrxw55rzz73bpq', '1528458010', 'admin'),
('cx4zemkmjwra9cae68zkff8t5zyxrhs4', '1528458033', 'admin'),
('ps4gyi7u2icsjn5zdxg6brean322xy5z', '1528458264', 'admin'),
('j68r5i2hdjef6tcmi3nzvc3kh8njw7ww', '1528458325', 'admin'),
('rdqw869x6bupwwhqnxew27i5izh3bjtm', '1528458393', 'admin'),
('4srg9epyxha9pigufsb6c9fbmdhwargt', '1528458438', 'admin'),
('zce8dkd3i7d3a5xgzky56nw3wni2k2es', '1528458453', 'admin'),
('a3kx847n7bei3bpe6ktuksepx9bk747h', '1528458473', 'admin'),
('pvcuip9epqqrd3anw2htesbm78ci34mk', '1528458584', 'admin'),
('qj89mga9ttekjutx6nbvgir7k2jfu2ju', '1528458594', 'admin'),
('aqmpbhtfxv39jfndbn9efq7tzbcwxufw', '1528458608', 'admin');


-- --------------------------------------------------------

--
-- Table structure for table `lime_settings_global`
--

DROP TABLE IF EXISTS `lime_settings_global`;
CREATE TABLE `lime_settings_global` (
  `stg_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `stg_value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`stg_name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_settings_global`
--

INSERT INTO `lime_settings_global` (`stg_name`,`stg_value`) VALUES
('DBVersion', '263'),
('SessionName', 'Xps+8\\\\iKlPjj#]>w\\~DP\"oJwU:Bg{f@mZK\'7(patAL_D+|<g|_8~Pbw&{:mw!/f'),
('sitename', 'LimeSurvey'),
('siteadminname', 'Administrator'),
('siteadminemail', 'your-email@example.net'),
('siteadminbounce', 'your-email@example.net'),
('defaultlang', 'it'),
('AssetsVersion', '2673'),
('restrictToLanguages', ''),
('defaulthtmleditormode', 'inline'),
('defaultquestionselectormode', 'default'),
('defaulttemplateeditormode', 'default'),
('defaulttemplate', 'default'),
('x_frame_options', 'allow'),
('admintheme', 'Sea_Green'),
('emailmethod', 'mail'),
('emailsmtphost', ''),
('emailsmtppassword', ''),
('bounceaccounthost', ''),
('bounceaccounttype', 'off'),
('bounceencryption', 'off'),
('bounceaccountuser', ''),
('bounceaccountpass', ''),
('emailsmtpssl', ''),
('emailsmtpdebug', '0'),
('emailsmtpuser', ''),
('filterxsshtml', '1'),
('shownoanswer', '1'),
('showxquestions', 'choose'),
('showgroupinfo', 'choose'),
('showqnumcode', 'choose'),
('repeatheadings', '25'),
('maxemails', '50'),
('iSessionExpirationTime', '7200'),
('ipInfoDbAPIKey', ''),
('pdffontsize', '9'),
('pdfshowheader', 'N'),
('pdflogowidth', '50'),
('pdfheadertitle', ''),
('pdfheaderstring', ''),
('bPdfQuestionFill', '1'),
('bPdfQuestionBold', '0'),
('bPdfQuestionBorder', '1'),
('bPdfResponseBorder', '1'),
('googleMapsAPIKey', ''),
('googleanalyticsapikey', ''),
('googletranslateapikey', ''),
('force_ssl', 'neither'),
('surveyPreview_require_Auth', '1'),
('RPCInterface', 'json'),
('rpc_publish_api', '1'),
('characterset', 'auto'),
('sideMenuBehaviour', 'adaptive'),
('timeadjust', '+0 minutes'),
('usercontrolSameGroupPolicy', '1'),
('last_survey_1', '297751'),
('last_question_1', '2'),
('last_question_sid_1', '297751'),
('last_question_gid_1', '2'),
('last_question_1_181911', '1'),
('last_question_1_181911_gid', '1'),
('last_question_1_297751', '2'),
('last_question_1_297751_gid', '2');


-- --------------------------------------------------------

--
-- Table structure for table `lime_survey_181911`
--

DROP TABLE IF EXISTS `lime_survey_181911`;
CREATE TABLE `lime_survey_181911` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `submitdate` datetime DEFAULT NULL,
  `lastpage` int(11) DEFAULT NULL,
  `startlanguage` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `181911X1X1` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_survey_token_181911_18138` (`token`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_survey_181911`
--

INSERT INTO `lime_survey_181911` (`id`,`token`,`submitdate`,`lastpage`,`startlanguage`,`181911X1X1`) VALUES
('1', NULL, '1980-01-01 00:00:00', '1', 'it', 'Andrea');


-- --------------------------------------------------------

--
-- Table structure for table `lime_survey_297751`
--

DROP TABLE IF EXISTS `lime_survey_297751`;
CREATE TABLE `lime_survey_297751` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `token` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `submitdate` datetime DEFAULT NULL,
  `lastpage` int(11) DEFAULT NULL,
  `startlanguage` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `297751X2X2` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `idx_survey_token_297751_7718` (`token`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_survey_297751`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_survey_links`
--

DROP TABLE IF EXISTS `lime_survey_links`;
CREATE TABLE `lime_survey_links` (
  `participant_id` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token_id` int(11) NOT NULL,
  `survey_id` int(11) NOT NULL,
  `date_created` datetime DEFAULT NULL,
  `date_invited` datetime DEFAULT NULL,
  `date_completed` datetime DEFAULT NULL,
  PRIMARY KEY (`participant_id`,`token_id`,`survey_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_survey_links`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_survey_url_parameters`
--

DROP TABLE IF EXISTS `lime_survey_url_parameters`;
CREATE TABLE `lime_survey_url_parameters` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sid` int(11) NOT NULL,
  `parameter` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `targetqid` int(11) DEFAULT NULL,
  `targetsqid` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_survey_url_parameters`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_surveys`
--

DROP TABLE IF EXISTS `lime_surveys`;
CREATE TABLE `lime_surveys` (
  `sid` int(11) NOT NULL,
  `owner_id` int(11) NOT NULL,
  `admin` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `active` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `expires` datetime DEFAULT NULL,
  `startdate` datetime DEFAULT NULL,
  `adminemail` varchar(254) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `anonymized` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `faxto` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `format` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `savetimings` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `template` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT 'default',
  `language` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `additional_languages` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `datestamp` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `usecookie` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `allowregister` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `allowsave` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Y',
  `autonumber_start` int(11) NOT NULL DEFAULT '0',
  `autoredirect` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `allowprev` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `printanswers` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `ipaddr` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `refurl` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `datecreated` date DEFAULT NULL,
  `publicstatistics` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `publicgraphs` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `listpublic` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `htmlemail` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `sendconfirmation` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'Y',
  `tokenanswerspersistence` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `assessments` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `usecaptcha` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `usetokens` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'N',
  `bounce_email` varchar(254) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attributedescriptions` text COLLATE utf8mb4_unicode_ci,
  `emailresponseto` text COLLATE utf8mb4_unicode_ci,
  `emailnotificationto` text COLLATE utf8mb4_unicode_ci,
  `tokenlength` int(11) NOT NULL DEFAULT '15',
  `showxquestions` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'Y',
  `showgroupinfo` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'B',
  `shownoanswer` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'Y',
  `showqnumcode` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'X',
  `bouncetime` int(11) DEFAULT NULL,
  `bounceprocessing` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `bounceaccounttype` varchar(4) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bounceaccounthost` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bounceaccountpass` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bounceaccountencryption` varchar(3) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `bounceaccountuser` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `showwelcome` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'Y',
  `showprogress` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'Y',
  `questionindex` int(11) NOT NULL DEFAULT '0',
  `navigationdelay` int(11) NOT NULL DEFAULT '0',
  `nokeyboard` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `alloweditaftercompletion` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `googleanalyticsstyle` varchar(1) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `googleanalyticsapikey` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_surveys`
--

INSERT INTO `lime_surveys` (`sid`,`owner_id`,`admin`,`active`,`expires`,`startdate`,`adminemail`,`anonymized`,`faxto`,`format`,`savetimings`,`template`,`language`,`additional_languages`,`datestamp`,`usecookie`,`allowregister`,`allowsave`,`autonumber_start`,`autoredirect`,`allowprev`,`printanswers`,`ipaddr`,`refurl`,`datecreated`,`publicstatistics`,`publicgraphs`,`listpublic`,`htmlemail`,`sendconfirmation`,`tokenanswerspersistence`,`assessments`,`usecaptcha`,`usetokens`,`bounce_email`,`attributedescriptions`,`emailresponseto`,`emailnotificationto`,`tokenlength`,`showxquestions`,`showgroupinfo`,`shownoanswer`,`showqnumcode`,`bouncetime`,`bounceprocessing`,`bounceaccounttype`,`bounceaccounthost`,`bounceaccountpass`,`bounceaccountencryption`,`bounceaccountuser`,`showwelcome`,`showprogress`,`questionindex`,`navigationdelay`,`nokeyboard`,`alloweditaftercompletion`,`googleanalyticsstyle`,`googleanalyticsapikey`) VALUES
('181911', '1', 'Administrator', 'Y', NULL, NULL, 'your-email@example.net', 'N', '', 'G', 'N', 'default', 'it', '', 'N', 'N', 'N', 'Y', '0', 'N', 'N', 'N', 'N', 'N', '2018-06-08', 'N', 'N', 'N', 'Y', 'Y', 'N', 'N', 'N', 'N', 'your-email@example.net', NULL, '', '', '15', 'Y', 'B', 'N', 'X', NULL, 'N', NULL, NULL, NULL, NULL, NULL, 'Y', 'Y', '0', '0', 'N', 'N', NULL, NULL),
('297751', '1', 'Administrator', 'Y', NULL, NULL, 'your-email@example.net', 'N', '', 'G', 'N', 'default', 'it', '', 'N', 'N', 'N', 'Y', '0', 'N', 'N', 'N', 'N', 'N', '2018-06-08', 'N', 'N', 'N', 'Y', 'Y', 'N', 'N', 'N', 'N', 'your-email@example.net', '{\"attribute_1\":{\"description\":\"\",\"mandatory\":\"Y\",\"show_register\":\"N\",\"cpdbmap\":\"\"},\"attribute_2\":{\"description\":\"\",\"mandatory\":\"Y\",\"show_register\":\"N\",\"cpdbmap\":\"\"}}', '', '', '15', 'Y', 'B', 'N', 'X', NULL, 'N', NULL, NULL, NULL, NULL, NULL, 'Y', 'Y', '0', '0', 'N', 'N', NULL, NULL);


-- --------------------------------------------------------

--
-- Table structure for table `lime_surveys_languagesettings`
--

DROP TABLE IF EXISTS `lime_surveys_languagesettings`;
CREATE TABLE `lime_surveys_languagesettings` (
  `surveyls_survey_id` int(11) NOT NULL,
  `surveyls_language` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'en',
  `surveyls_title` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `surveyls_description` text COLLATE utf8mb4_unicode_ci,
  `surveyls_welcometext` text COLLATE utf8mb4_unicode_ci,
  `surveyls_endtext` text COLLATE utf8mb4_unicode_ci,
  `surveyls_url` text COLLATE utf8mb4_unicode_ci,
  `surveyls_urldescription` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surveyls_email_invite_subj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surveyls_email_invite` text COLLATE utf8mb4_unicode_ci,
  `surveyls_email_remind_subj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surveyls_email_remind` text COLLATE utf8mb4_unicode_ci,
  `surveyls_email_register_subj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surveyls_email_register` text COLLATE utf8mb4_unicode_ci,
  `surveyls_email_confirm_subj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `surveyls_email_confirm` text COLLATE utf8mb4_unicode_ci,
  `surveyls_dateformat` int(11) NOT NULL DEFAULT '1',
  `surveyls_attributecaptions` text COLLATE utf8mb4_unicode_ci,
  `email_admin_notification_subj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_admin_notification` text COLLATE utf8mb4_unicode_ci,
  `email_admin_responses_subj` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email_admin_responses` text COLLATE utf8mb4_unicode_ci,
  `surveyls_numberformat` int(11) NOT NULL DEFAULT '0',
  `attachments` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`surveyls_survey_id`,`surveyls_language`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_surveys_languagesettings`
--

INSERT INTO `lime_surveys_languagesettings` (`surveyls_survey_id`,`surveyls_language`,`surveyls_title`,`surveyls_description`,`surveyls_welcometext`,`surveyls_endtext`,`surveyls_url`,`surveyls_urldescription`,`surveyls_email_invite_subj`,`surveyls_email_invite`,`surveyls_email_remind_subj`,`surveyls_email_remind`,`surveyls_email_register_subj`,`surveyls_email_register`,`surveyls_email_confirm_subj`,`surveyls_email_confirm`,`surveyls_dateformat`,`surveyls_attributecaptions`,`email_admin_notification_subj`,`email_admin_notification`,`email_admin_responses_subj`,`email_admin_responses`,`surveyls_numberformat`,`attachments`) VALUES
('181911', 'it', 'TestSurvey', '', '', '', '', '', 'Invito per partecipare all\'indagine', 'Egregio/a {FIRSTNAME},<br />\n<br />\nè invitato a partecipare ad un\'indagine on line.<br />\n<br />\nL\'indagine è intitolata:<br />\n\"{SURVEYNAME}\"<br />\n<br />\n\"{SURVEYDESCRIPTION}\"<br />\n<br />\nPer partecipare fare click sul link in basso.<br />\n<br />\nCordiali saluti,{ADMINNAME} ({ADMINEMAIL})<br />\n<br />\n----------------------------------------------<br />\nFare click qui per accedere al questionario e rispondere alle domande relative:<br />\n{SURVEYURL}<br />\n<br />\nSe non si intende partecipare a questa indagine e non si vogliono ricevere altri inviti, si può cliccare sul seguente collegamento:<br />\n{OPTOUTURL}<br />\n<br />\nSe è presente in blacklist ma vuole partecipare a questa indagine e ricevere inviti, vada al seguente link:<br />\n{OPTINURL}', 'Promemoria per partecipare all\'indagine', 'Egregio/a {FIRSTNAME},<br />\nRecentemente ha ricevuto un invito a partecipare ad un\'indagine on line.<br />\n<br />\nAbbiamo notato che non ha ancora completato il questionario. Con l\'occasione Le ricordiamo che il questionario è ancora disponibile.<br />\n<br />\nL\'indagine è intitolata:<br />\n\"{SURVEYNAME}\"<br />\n<br />\n\"{SURVEYDESCRIPTION}\"<br />\n<br />\nPer partecipare fare clic sul link qui sotto.<br />\n<br />\nCordiali saluti,<br />\n<br />\n{ADMINNAME} ({ADMINEMAIL})<br />\n<br />\n----------------------------------------------<br />\nFare clic qui per accedere all\'indagine e rispondere al questionario:<br />\n{SURVEYURL}<br />\n<br />\nSe non si intende partecipare a questa indagine e non si vogliono ricevere altri inviti, si può cliccare sul seguente collegamento:<br />\n{OPTOUTURL}', 'Conferma di registrazione all\'indagine', 'Egregio/a {FIRSTNAME},<br />\n<br />\nLei (o qualcuno che ha utilizzato il suo indirizzo e-mail) si è registrato per partecipare all\'indagine on line intitolata {SURVEYNAME}.<br />\n<br />\nPer completare il questionario fare clic sul seguente indirizzo:<br />\n<br />\n{SURVEYURL}<br />\n<br />\nSe ha qualche domanda, o se non si è registrato e ritiene che questa e-mail ti sia pervenuta per errore, la preghiamo di contattare  {ADMINNAME} all\'indirizzo {ADMINEMAIL}.', 'Confermare la partecipazione all&#039;indagine', 'Egregio/a {FIRSTNAME},<br />\n<br />\nQuesta e-mail le è stata inviata per confermarle che ha completato correttamente il questionario intitolato {SURVEYNAME}  e che le sue risposte sono state salvate. Grazie per la partecipazione.<br />\n<br />\nSe ha ulteriori domande circa questo messaggio, la prego di contattare {ADMINNAME} all\'indirizzo e-mail {ADMINEMAIL}.<br />\n<br />\nCordiali saluti<br />\n<br />\n{ADMINNAME}', '5', NULL, 'Invio di una risposta all\'indagine {SURVEYNAME}', 'Salve,<br />\n<br />\nUna nuova risposta é stata inviata per l\'indagine \'{SURVEYNAME}\'.<br />\n<br />\nFare click sul link seguente per ricaricare l\'indagine:<br />\n{RELOADURL}<br />\n<br />\nFare click sul link seguente per vedere le risposte individuali:<br />\n{VIEWRESPONSEURL}<br />\n<br />\nFare click sul link seguente per modificare le risposte individuali:<br />\n{EDITRESPONSEURL}<br />\n<br />\nFare clic sul link seguente per visualizzare le statistiche:<br />\n{STATISTICSURL}', 'Invio di una risposta all\'indagine {SURVEYNAME} con risultati', 'Salve,<br />\n<br />\nUna nuova risposta è stata inviata dall\'indagine \'{SURVEYNAME}\'.<br />\n<br />\nFare clic sul link seguente per ricaricare l\'indagine:<br />\n{RELOADURL}<br />\n<br />\nFare clic sul link seguente per vedere la risposta individuale:<br />\n{VIEWRESPONSEURL}<br />\n<br />\nFare clic sul link seguente per modificare la risposta individuale:<br />\n{EDITRESPONSEURL}<br />\n<br />\nFare clic sul link seguente per visualizzare le statistiche:<br />\n{STATISTICSURL}<br />\n<br />\n<br />\nLe seguenti risposte sono state date dal partecipante:<br />\n{ANSWERTABLE}', '1', NULL),
('297751', 'it', 'TestSurvey with Participants', '', '', '', '', '', 'Invito per partecipare all\'indagine', 'Egregio/a {FIRSTNAME},<br />\n<br />\nè invitato a partecipare ad un\'indagine on line.<br />\n<br />\nL\'indagine è intitolata:<br />\n\"{SURVEYNAME}\"<br />\n<br />\n\"{SURVEYDESCRIPTION}\"<br />\n<br />\nPer partecipare fare click sul link in basso.<br />\n<br />\nCordiali saluti,{ADMINNAME} ({ADMINEMAIL})<br />\n<br />\n----------------------------------------------<br />\nFare click qui per accedere al questionario e rispondere alle domande relative:<br />\n{SURVEYURL}<br />\n<br />\nSe non si intende partecipare a questa indagine e non si vogliono ricevere altri inviti, si può cliccare sul seguente collegamento:<br />\n{OPTOUTURL}<br />\n<br />\nSe è presente in blacklist ma vuole partecipare a questa indagine e ricevere inviti, vada al seguente link:<br />\n{OPTINURL}', 'Promemoria per partecipare all\'indagine', 'Egregio/a {FIRSTNAME},<br />\nRecentemente ha ricevuto un invito a partecipare ad un\'indagine on line.<br />\n<br />\nAbbiamo notato che non ha ancora completato il questionario. Con l\'occasione Le ricordiamo che il questionario è ancora disponibile.<br />\n<br />\nL\'indagine è intitolata:<br />\n\"{SURVEYNAME}\"<br />\n<br />\n\"{SURVEYDESCRIPTION}\"<br />\n<br />\nPer partecipare fare clic sul link qui sotto.<br />\n<br />\nCordiali saluti,<br />\n<br />\n{ADMINNAME} ({ADMINEMAIL})<br />\n<br />\n----------------------------------------------<br />\nFare clic qui per accedere all\'indagine e rispondere al questionario:<br />\n{SURVEYURL}<br />\n<br />\nSe non si intende partecipare a questa indagine e non si vogliono ricevere altri inviti, si può cliccare sul seguente collegamento:<br />\n{OPTOUTURL}', 'Conferma di registrazione all\'indagine', 'Egregio/a {FIRSTNAME},<br />\n<br />\nLei (o qualcuno che ha utilizzato il suo indirizzo e-mail) si è registrato per partecipare all\'indagine on line intitolata {SURVEYNAME}.<br />\n<br />\nPer completare il questionario fare clic sul seguente indirizzo:<br />\n<br />\n{SURVEYURL}<br />\n<br />\nSe ha qualche domanda, o se non si è registrato e ritiene che questa e-mail ti sia pervenuta per errore, la preghiamo di contattare  {ADMINNAME} all\'indirizzo {ADMINEMAIL}.', 'Confermare la partecipazione all&#039;indagine', 'Egregio/a {FIRSTNAME},<br />\n<br />\nQuesta e-mail le è stata inviata per confermarle che ha completato correttamente il questionario intitolato {SURVEYNAME}  e che le sue risposte sono state salvate. Grazie per la partecipazione.<br />\n<br />\nSe ha ulteriori domande circa questo messaggio, la prego di contattare {ADMINNAME} all\'indirizzo e-mail {ADMINEMAIL}.<br />\n<br />\nCordiali saluti<br />\n<br />\n{ADMINNAME}', '5', '{\"attribute_1\":\"\",\"attribute_2\":\"\"}', 'Invio di una risposta all\'indagine {SURVEYNAME}', 'Salve,<br />\n<br />\nUna nuova risposta é stata inviata per l\'indagine \'{SURVEYNAME}\'.<br />\n<br />\nFare click sul link seguente per ricaricare l\'indagine:<br />\n{RELOADURL}<br />\n<br />\nFare click sul link seguente per vedere le risposte individuali:<br />\n{VIEWRESPONSEURL}<br />\n<br />\nFare click sul link seguente per modificare le risposte individuali:<br />\n{EDITRESPONSEURL}<br />\n<br />\nFare clic sul link seguente per visualizzare le statistiche:<br />\n{STATISTICSURL}', 'Invio di una risposta all\'indagine {SURVEYNAME} con risultati', 'Salve,<br />\n<br />\nUna nuova risposta è stata inviata dall\'indagine \'{SURVEYNAME}\'.<br />\n<br />\nFare clic sul link seguente per ricaricare l\'indagine:<br />\n{RELOADURL}<br />\n<br />\nFare clic sul link seguente per vedere la risposta individuale:<br />\n{VIEWRESPONSEURL}<br />\n<br />\nFare clic sul link seguente per modificare la risposta individuale:<br />\n{EDITRESPONSEURL}<br />\n<br />\nFare clic sul link seguente per visualizzare le statistiche:<br />\n{STATISTICSURL}<br />\n<br />\n<br />\nLe seguenti risposte sono state date dal partecipante:<br />\n{ANSWERTABLE}', '1', NULL);


-- --------------------------------------------------------

--
-- Table structure for table `lime_templates`
--

DROP TABLE IF EXISTS `lime_templates`;
CREATE TABLE `lime_templates` (
  `folder` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `creator` int(11) NOT NULL,
  PRIMARY KEY (`folder`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_templates`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_tokens_297751`
--

DROP TABLE IF EXISTS `lime_tokens_297751`;
CREATE TABLE `lime_tokens_297751` (
  `tid` int(11) NOT NULL AUTO_INCREMENT,
  `participant_id` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `firstname` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `lastname` varchar(150) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` text COLLATE utf8mb4_unicode_ci,
  `emailstatus` text COLLATE utf8mb4_unicode_ci,
  `token` varchar(35) CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `language` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `blacklisted` varchar(17) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `sent` varchar(17) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `remindersent` varchar(17) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `remindercount` int(11) DEFAULT '0',
  `completed` varchar(17) COLLATE utf8mb4_unicode_ci DEFAULT 'N',
  `usesleft` int(11) DEFAULT '1',
  `validfrom` datetime DEFAULT NULL,
  `validuntil` datetime DEFAULT NULL,
  `mpid` int(11) DEFAULT NULL,
  `attribute_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `attribute_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`tid`),
  KEY `idx_token_token_297751_44431` (`token`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_tokens_297751`
--

INSERT INTO `lime_tokens_297751` (`tid`,`participant_id`,`firstname`,`lastname`,`email`,`emailstatus`,`token`,`language`,`blacklisted`,`sent`,`remindersent`,`remindercount`,`completed`,`usesleft`,`validfrom`,`validuntil`,`mpid`,`attribute_1`,`attribute_2`) VALUES
('1', NULL, 'John', 'Doe', '', 'OK', 'eRWln1b85xEShOQ', 'it', NULL, 'N', 'N', '0', 'N', '1', NULL, NULL, NULL, 'Foo 1', 'Foo 2'),
('2', NULL, 'Jack', 'London', '', 'OK', '0ZBh2Yz6hd6OrPN', 'it', NULL, 'N', 'N', '0', 'N', '1', NULL, NULL, NULL, 'Foo 3', 'Foo 4');


-- --------------------------------------------------------

--
-- Table structure for table `lime_user_groups`
--

DROP TABLE IF EXISTS `lime_user_groups`;
CREATE TABLE `lime_user_groups` (
  `ugid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner_id` int(11) NOT NULL,
  PRIMARY KEY (`ugid`),
  UNIQUE KEY `lug_name` (`name`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_user_groups`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_user_in_groups`
--

DROP TABLE IF EXISTS `lime_user_in_groups`;
CREATE TABLE `lime_user_in_groups` (
  `ugid` int(11) NOT NULL,
  `uid` int(11) NOT NULL,
  PRIMARY KEY (`ugid`,`uid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_user_in_groups`
--


-- --------------------------------------------------------

--
-- Table structure for table `lime_users`
--

DROP TABLE IF EXISTS `lime_users`;
CREATE TABLE `lime_users` (
  `uid` int(11) NOT NULL AUTO_INCREMENT,
  `users_name` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `password` blob NOT NULL,
  `full_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_id` int(11) NOT NULL,
  `lang` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(254) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `htmleditormode` varchar(7) COLLATE utf8mb4_unicode_ci DEFAULT 'default',
  `templateeditormode` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `questionselectormode` varchar(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'default',
  `one_time_pw` blob,
  `dateformat` int(11) NOT NULL DEFAULT '1',
  `created` datetime DEFAULT NULL,
  `modified` datetime DEFAULT NULL,
  PRIMARY KEY (`uid`),
  UNIQUE KEY `users_name` (`users_name`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `lime_users`
--

INSERT INTO `lime_users` (`uid`,`users_name`,`password`,`full_name`,`parent_id`,`lang`,`email`,`htmleditormode`,`templateeditormode`,`questionselectormode`,`one_time_pw`,`dateformat`,`created`,`modified`) VALUES
('1', 'admin', '8c6976e5b5410415bde908bd4dee15dfb167a9c873fc4bb8a81f6f2ab448a918', 'Administrator', '0', 'it', 'your-email@example.net', 'default', 'default', 'default', NULL, '1', '2018-06-08 09:35:07', NULL);

