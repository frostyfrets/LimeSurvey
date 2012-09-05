<?php
/*
 * LimeSurvey
 * Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 *
 *  $Id$
 */

/*
 * Created 12-2008 by Maziminke (maziminke@web.de)
 *
 * This file handles the "Show results to users" option:
 * Survey Settings -> Presentation & navigation -> "Public statistics?"
 *
 * The admin can set a question attribute "public_statistics" for each question
 * to determine whether the results of a certain question should be shown to the user
 * after he/she has submitted the survey.
 *
 * See http://docs.limesurvey.org/tiki-index.php?page=Question+attributes#public_statistics
 */

class Statistics_userController extends LSYii_Controller {


    public function _remap($method, $params = array())
    {
        array_unshift($params, $method);
        return call_user_func_array(array($this, "action"), $params);
    }

    function actionAction($surveyid,$language)
    {
        $iSurveyID=(int)$surveyid;
        //$postlang = returnglobal('lang');
        Yii::import('application.libraries.admin.progressbar',true);
        Yii::app()->loadHelper("admin/statistics");
        Yii::app()->loadHelper('database');
        Yii::app()->loadHelper('surveytranslator');

        $data = array();

        //XXX enable/disable this for testing
        //$publicgraphs = 1;
        //$showaggregateddata = 1;

        /*
         * List of important settings:
         * - publicstatistics: General survey setting which determines if public statistics for this survey
         *   should be shown at all.
         *
         * - publicgraphs: General survey setting which determines if public statistics for this survey
         *   should include graphs or only show a tabular overview.
         *
         * - public_statistics: Question attribute which has to be applied to each question so that
         *   its statistics will be shown to the user. If not set no statistics for this question will be shown.
         *
         * - filterout_incomplete_answers: Setting taken from config-defaults.php which determines if
         *   not completed answers will be filtered.
         */

        if(!isset($iSurveyID))
        {
            $iSurveyID=returnGlobal('sid');
        }
        else
        {
            $iSurveyID = (int) $iSurveyID;
        }
        if (!$iSurveyID)
        {
            //This next line ensures that the $iSurveyID value is never anything but a number.
            safeDie('You have to provide a valid survey ID.');
        }


        if ($iSurveyID)
        {
            $actresult = Survey::model()->findAll('sid = :sid AND active = :active', array(':sid' => $iSurveyID, ':active' => 'Y'));      //Checked
            if (count($actresult) == 0)
            {
                safeDie('You have to provide a valid survey ID.');
            }
            else
            {
                $surveyinfo = getSurveyInfo($iSurveyID);
                // CHANGE JSW_NZ - let's get the survey title for display
                $thisSurveyTitle = $surveyinfo["name"];
                // CHANGE JSW_NZ - let's get css from individual template.css - so define path
                $thisSurveyCssPath = getTemplateURL($surveyinfo["template"]);
                if ($surveyinfo['publicstatistics']!='Y')
                {
                    safeDie('The public statistics for this survey are deactivated.');
                }

                //check if graphs should be shown for this survey
                if ($surveyinfo['publicgraphs']=='Y')
                {
                    $publicgraphs = 1;
                }
                else
                {
                    $publicgraphs = 0;
                }
            }
        }

        //we collect all the output within this variable
        $statisticsoutput ='';


        //for creating graphs we need some more scripts which are included here
        //True -> include
        //False -> forget about charts
        if (isset($publicgraphs) && $publicgraphs == 1)
        {
            require_once(APPPATH.'third_party/pchart/pchart/pChart.class');
            require_once(APPPATH.'third_party/pchart/pchart/pData.class');
            require_once(APPPATH.'third_party/pchart/pchart/pCache.class');

            $MyCache = new pCache(Yii::app()->getConfig("tempdir").'/');
            //$currentuser is created as prefix for pchart files
            if (isset($_SERVER['REDIRECT_REMOTE_USER']))
            {
                $currentuser=$_SERVER['REDIRECT_REMOTE_USER'];
            }
            else if (session_id())
            {
                $currentuser=substr(session_id(), 0, 15);
            }
            else
            {
                $currentuser="standard";
            }
        }


        // Set language for questions and labels to base language of this survey
        if (isset($postlang) && $postlang != null )
            $language = $postlang;
        else
            $language = Survey::model()->findByPk($iSurveyID)->language;



        $chartfontfile = Yii::app()->getConfig("chartfontfile");
        //pick the best font file if font setting is 'auto'
        if ($chartfontfile=='auto')
        {
            $chartfontfile='vera.ttf';
            if ( $language=='ar')
            {
                $chartfontfile='KacstOffice.ttf';
            }
            elseif  ($language=='fa' )
            {
                $chartfontfile='KacstFarsi.ttf';
            }
        }

        //set survey language for translations
        $clang = SetSurveyLanguage($iSurveyID, $language);


        //Create header (fixes bug #3097)
        $surveylanguage= $language;
        sendCacheHeaders();
        $condition = false;
        $header=  "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Transitional//EN\" \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd\">\n"
        . "<html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"".$surveylanguage."\" lang=\"".$surveylanguage."\"";
        if (getLanguageRTL($surveylanguage))
        {
            $condition = true;
            $header.=" dir=\"rtl\" ";
        }
        $sitename = Yii::app()->getConfig("sitename");

        $data['surveylanguage'] = $surveylanguage;
        $data['sitename'] = $sitename;
        $data['condition'] = $condition;
        $data['thisSurveyCssPath'] = $thisSurveyCssPath;

        /*
         * only show questions where question attribute "public_statistics" is set to "1"
         */

        $query = "SELECT q.* , group_name, group_order FROM {{questions}} q, {{groups}} g, {{question_attributes}} qa WHERE 'g.gid' = 'q.gid' AND 'g.language' = :lang AND 'q.language' = :lang AND 'q.sid' = :surveyid AND 'q.qid' = 'qa.qid' AND 'q.parent_qid' = 0 AND 'qa.attribute' = 'public_statistics'";
        $databasetype = Yii::app()->db->getDriverName();
        if ($databasetype=='mssql' || $databasetype=="sqlsrv")
        {
            $query .="AND CAST(CAST(qa.value as varchar) as int)='1'\n";
        }
        else
        {
            $query .="AND qa.value='1'\n";
        }

        //execute query
        $oAllQuestions = Questions::model()->with('question_types')->with('question_attributes')->with('groups')->findAllByAttributes(
            array('sid' => $iSurveyID, 'parent_qid'=>'0','language'=>$surveylanguage),
            array('order' => 'groups.group_order, question_order', 'condition' => 'question_attributes.value = "1"')
        );

        //number of records for this survey
        $totalrecords = 0;

        //count number of answers
        $query = "SELECT count(*) FROM {{survey_".intval($iSurveyID)."}}";

        //if incompleted answers should be filtert submitdate has to be not null
        //this setting is taken from config-defaults.php
        if (Yii::app()->getConfig("filterout_incomplete_answers") == true)
        {
            $query .= " WHERE {{survey_".intval($iSurveyID)."}}.submitdate is not null";
        }
        $result = Yii::app()->db->createCommand($query)->queryAll();

        //$totalrecords = total number of answers
        foreach($result as $row)
        {
            $totalrecords = reset($row);
        }

        //---------- CREATE SGQA OF ALL QUESTIONS WHICH USE "PUBLIC_STATISTICS" ----------
        $summary = createCompleteSGQA($iSurveyID,$oAllQuestions,$surveylanguage);

        //---------- CREATE STATISTICS ----------


        //some progress bar stuff

        // Create progress bar which is shown while creating the results
        $prb = new ProgressBar();
        $prb->pedding = 2; // Bar Pedding
        $prb->brd_color = "#404040 #dfdfdf #dfdfdf #404040"; // Bar Border Color

        $prb->setFrame(); // set ProgressBar Frame
        $prb->frame['left'] = 50; // Frame position from left
        $prb->frame['top'] = 80; // Frame position from top
        $prb->addLabel('text','txt1',$clang->gT("Please wait ...")); // add Text as Label 'txt1' and value 'Please wait'
        $prb->addLabel('percent','pct1'); // add Percent as Label 'pct1'
        $prb->addButton('btn1',$clang->gT('Go back'),'?action=statistics&amp;sid='.$iSurveyID); // add Button as Label 'btn1' and action '?restart=1'

        //progress bar starts with 35%
        $process_status = 35;
        $prb->show(); // show the ProgressBar


        // 1: Get list of questions with answers chosen
        //"Getting Questions and Answers ..." is shown above the bar
        $prb->setLabelValue('txt1',$clang->gT('Getting questions and answers ...'));
        $prb->moveStep(5);

        // creates array of post variable names
        for (reset($_POST); $key=key($_POST); next($_POST))
        {
            $postvars[]=$key;
        }
        $data['thisSurveyTitle'] = $thisSurveyTitle;
        $data['totalrecords'] = $totalrecords;
        $data['clang'] = $clang;
        $data['summary'] = $summary;
        //show some main data at the beginnung
        // CHANGE JSW_NZ - let's allow html formatted questions to show


        //push progress bar from 35 to 40
        $process_status = 40;

        //Show Summary results
        //"Generating Summaries ..." is shown above the progress bar
        $prb->setLabelValue('txt1',$clang->gT('Generating summaries ...'));
        $prb->moveStep($process_status);

        //let's run through the survey // Fixed bug 3053 with array_unique
        $runthrough=array_unique($summary);

        //loop through all selected questions
        foreach ($runthrough as $rt)
        {

            //update progress bar
            if ($process_status < 100) $process_status++;
            $prb->moveStep($process_status);

        } // end foreach -> loop through all questions

        $statisticsoutput .= generate_statistics($iSurveyID, $summary, $publicgraphs, 'html',null,$language,false);


        //done! set progress bar to 100%
        if (isset($prb))
        {
            $prb->setLabelValue('txt1',$clang->gT('Completed'));
            $prb->moveStep(100);
            $prb->hide();
        }

        $this->render('/statistics_user_view',$data);

        //output footer
        echo getFooter();

        //Delete all Session Data
        Yii::app()->session['finished'] = true;
    }

}
