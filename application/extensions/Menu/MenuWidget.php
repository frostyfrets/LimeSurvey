<?php

    class MenuWidget extends CWidget
    {
        /**
         * @var Limesurvey_lang
         */
        public $clang = null;
        
        public $menu = array();
        
        public function __construct($owner = null) {
            parent::__construct($owner);
            Yii::import('application.helpers.surveytranslator_helper', true);
            $this->clang = App()->lang;
        }
        public $defaults = array(
            'title' => '',
            'alt' => '',
            'type' => 'link'
        );
        
        public $surveyId = null;
        public $groupId = null;
        public $questionId = null;
        
        public function run()
        {
            $this->render('adminmenu', array('menu' => $this->menuMain()));
            if (isset($this->surveyId))
            {
                $this->render('adminmenu', array('menu' => $this->menuSurvey($this->surveyId)));
            }
            if (isset($this->groupId))
            {
                $this->render('adminmenu', array('menu' => $this->menuGroup($this->groupId)));
            }
        }

        
        
        protected function menuMain()
        {
            $menu['title'] = App()->getConfig('sitename');
            $menu['role'] = 'main';
            $menu['imageUrl'] = App()->getConfig('adminimageurl');
            $menu['items']['left'][] = array(
                'href' => array('admin/survey'),
                'image' => 'home.png',
            );
            $menu['items']['left'][] = 'separator';
            $menu['items']['left'][] = array(
                'href' => array('admin/user'),
                'alt' => gT('Manage survey administrators'),
                'image' => 'security.png',
            );
            $menu['items']['left'][] = array(
                'href' => array('admin/usergroups/sa/index'),
                'alt' => gT('Create/edit user groups'),
                'image' => 'usergroup.png'
            );

            $menu['items']['left'][] = $this->globalSettings();
            $menu['items']['left'][] = 'separator';
            $menu['items']['left'][] = $this->checkIntegrity();
            $menu['items']['left'][] = $this->dumpDatabase();
            $menu['items']['left'][] = $this->editLabels();
            $menu['items']['left'][] = 'separator';
            $menu['items']['left'][] = $this->editTemplates();
            $menu['items']['left'][] = 'separator';
            $menu['items']['left'][] = $this->participantDatabase();
            $menu['items']['left'][] = array(
                'href' => array('/plugins'),
                'alt' => gT('Plugin manager'),
                'image' => 'share.png'
            );

            $surveys = getSurveyList(true);
            $surveyList = array();
            foreach ($surveys as $survey)
            {
                $surveyList[] = array(
                    'id' => $survey['sid'],
                    'title' => $survey['surveyls_title']
                );
            }
            $menu['items']['right'][] = array(
                'title' => 'Surveys:',
                'type' => 'select',
                'name' => 'surveylist',
                'values' => $surveyList,
                'value' => $this->surveyId
            );
            $menu['items']['right'][] = array(
                'href' => array('/surveys'),
                'alt' => gT('Detailed list of surveys'),
                'image' => 'surveylist.png'
            );
            
            $menu['items']['right'][] = $this->createSurvey();
            $menu['items']['right'][] = 'separator';

            
            $menu['items']['right'][] = array(
                'href' => array('admin/user/sa/personalsettings'),
                'alt' => gT('Edit your personal preferences'),
                'image' => 'edit.png'
            );
            $menu['items']['right'][] = array(
                'href' => array('admin/authentication/sa/logout'),
                'alt' => gT('Logout'),
                'image' => 'logout.png'
            );
            
            $menu['items']['right'][] = array(
                'href' => "http://docs.limesurvey.org",
                'alt' => gT('LimeSurvey online manual'),
                'image' => 'showhelp.png'
            );

            $event = new PluginEvent('afterAdminMenuLoaded', $this);
            $event->set('menu', $menu);
            
            $result = App()->getPluginManager()->dispatchEvent($event);
            
            $menu = $result->get('menu');
            return $menu;
        }

        protected function menuSurvey($surveyId)
        {
            /**
             * @todo Remove direct session access.
             * @todo Remove admin specific setting; language is a property of any session.
             */
            $surveyInfo = getSurveyInfo($surveyId, Yii::app()->session['adminlang']);
            $menu['title'] = "Survey {$surveyInfo['surveyls_title']} (id: {$surveyId})";
            $menu['role'] = 'survey';
            $menu['imageUrl'] = App()->getConfig('adminimageurl');
            
            if ($surveyInfo['active'] == 'Y')
            {
                $menu['items']['left'][] = array(
                    'type' => 'image',
                    'image' => 'active.png',
                );
                /**
                 * @todo Get request changes state.
                 */
                $menu['items']['left'][] = array(
                    'type' => 'image',
                    'image' => 'deactivate.png',
                );
            }
            else
            {
                $menu['items']['left'][] = array(
                    'type' => 'image',
                    'image' => 'inactive.png',
                );
                $menu['items']['left'][] = array(
                    'href' => array('admin/survey', 'sa' => 'activate', 'surveyid' => $surveyId),
                    'image' => 'activate.png',
                );
                
            }
            $menu['items']['left'][] = 'separator';
            $languages = array($surveyInfo['language']);
            if (isset($surveyInfo['additional_languages']))
            {
                $languages = array_merge($languages, array_filter(explode(' ', $surveyInfo['additional_languages'])));
            }
            foreach ($languages as $language)
            {
                $subitems[] = array(
                    'type' => 'link',
                    'title' => getLanguageNameFromCode($language, false),
                    'image' => 'do_30.png',
                    'href' => array('survey/index', 'sid' => $surveyId, 'newtest' => 'Y', 'lang' => $language)
                );
            }
            $menu['items']['left'][] = array(
                'type' => 'sub',
                'href' => array('survey/index', 'sid' => $surveyId, 'newtest' => 'Y'),
                'image' => 'do.png',
                'items' => array(
                    array(
                        'type' => 'sub',
                        'items' => $subitems,
                        'href' => array('survey/index', 'sid' => $surveyId, 'newtest' => 'Y'),
                        'title' => gT('Test this survey'),
                        'image' => 'do_30.png'
                    )
                )
            );
            $menu['items']['right'][] = array(
                'title' => 'Groups:',
                'type' => 'select',
                'name' => 'grouplist',
                'values' => Groups::model()->findListByAttributes(array('sid' => $surveyId), 'group_name', 'gid'),
                'value' => $this->groupId
            );
            $menu['items']['right'][] = array(
                'alt' => gT('Add new group to survey'),
                'type' => 'link',
                'image' => 'add.png',
                'href' => array('admin/questiongroup', 'sa' =>  'add', 'surveyid' => $surveyId)
                
            );
            
            http://ls20.befound.nl/index.php?r=admin/questiongroup/sa/add/surveyid/597865
            return $menu;
        }
        
        protected function menuGroup($groupId)
        {
            $group = Groups::model()->findByAttributes(array('gid' => $groupId));
            $menu['title'] = "Group {$group->group_name} (id: {$groupId})";
            $menu['role'] = 'group';
            $menu['imageUrl'] = App()->getConfig('adminimageurl');
            
            $menu['items']['left'][] = array(
                'alt' => gT('Preview this group'),
                'type' => 'link',
                'image' => 'preview.png',
                'href' => array('survey/index/', 'action' => 'previewgroup', 'sid' => $group->sid, 'gid' => $groupId)
            );
            $menu['items']['left'][] = 'separator';
            $menu['items']['left'][] = array(
                'alt' => gT('Edit current question group'),
                'type' => 'link',
                'image' => 'edit.png',
                'href' => array('admin/questiongroup', 'sa' => 'edit', 'surveyid' => $group->sid, 'gid' => $groupId)
            );
            $menu['items']['left'][] = 'separator';
            
            $menu['items']['left'][] = 'separator';

            $menu['items']['right'][] = array(
                'type' => 'select',
                'title' => gT('Questions'),
                'name' => 'questionlist',
                'values' => Questions::model()->findListByAttributes(array('sid' => $group->sid, 'gid' => $groupId), 'code', 'qid'),
                'value' => $this->questionId
            );
            return $menu;
        }
        
        protected function renderItem($item, &$allowSeparator, $imageUrl, $level = 0)
        {
            $result = '';
            if (is_array($item))
            {
                $allowSeparator = true;
                if (isset($item['image']))
                {
                    $result .= CHtml::image($imageUrl . $item['image'], isset($item['alt']) ? $item['alt'] : '');
                }
                if (isset($item['title']))
                {
                    $result .= $item['title'];
                }
                
                if(isset($item['values']))
                {
                    
                    $result = $this->renderSelect($item);
                }
                
                if(isset($item['items']))
                {
                    $result = $this->renderSub($item, $imageUrl, $level + 1);
                }
                
                
                if (isset($item['href']))
                {
                    $result = CHtml::link($result, $item['href']);
                }
            }
            elseif (is_string($item) && $item == 'separator' && $allowSeparator)
            {
                $result = CHtml::image($imageUrl . 'separator.gif');
                $allowSeparator = false;
            }

            
            return CHtml::tag('li', array(), $result);
        }
        
        protected function renderMenu($menu)
        {
            foreach ($menu['items'] as $class => $menuItems)
            {
                echo CHtml::openTag('ol', array('class' => "menubar-$class level0"));
                $allowSeparator = false;
                foreach($menuItems as $item)
                {
                    echo $this->renderItem($item, $allowSeparator, $menu['imageUrl']);
                }
                echo CHtml::closeTag('ol');

            }
        }
        
        protected function renderSelect($item)
        {
            $result = CHtml::label($item['title'],  $item['name']);
            if (is_array(current($item['values'])))
            {
                $listData = CHtml::listData($item['values'], 'id', 'title');
            }
            else
            {
                $listData = $item['values'];
            }
            $result .= CHtml::dropDownList($item['name'], $item['value'], $listData, array(
                'id' => $item['name'],
                'prompt' => gT('Please choose...')
            ));
            
            return $result;
        }
        
        protected function renderSub($item, $imageUrl, $level)
        {
            $result = '';
            if (isset($item['image']))
            {
                $result .= CHtml::image($imageUrl . $item['image']);
            }
            if (isset($item['title']))
            {
                $result .= $item['title'];
            }
            if (isset($item['href']))    
            {
                $result = CHtml::link($result, $item['href']);
            }
            
            $result .= CHtml::openTag('ol', array('class' => "level$level"));
            
            foreach ($item['items'] as $subItem)
            {
                $allowSeparator = false;
                $result .= $this->renderItem($subItem, $allowSeparator, $imageUrl, $level);
            }
            $result .= CHtml::closeTag('ol');
            return $result;
        }
        
        protected function globalSettings()
        {
            if ($this->hasRight('USER_RIGHT_CONFIGURATOR'))
            {
                return array(
                    'href' => array('admin/globalsettings'),
                    'image' => 'global.png',
                    'alt' => gT('Global Settings')
                );
            }
        }

        protected function checkIntegrity()
        {
            if ($this->hasRight('USER_RIGHT_CONFIGURATOR'))
            {
                return array(
                    'href' => array('admin/checkintegrity'),
                    'image' => 'checkdb.png',
                    'alt' => gT('Check Data Integrity')
                );
            }
        }

        
        protected function createSurvey()
        {
            if ($this->hasRight('USER_RIGHT_CREATE_SURVEY'))
            {
                return array(
                    'href' => array("admin/survey/sa/newsurvey"),
                    'image' => 'add.png',
                    'alt' => gT('Create, import, or copy a survey')
                );
            }
        }
        protected function dumpDatabase()
        {
            if ($this->hasRight('USER_RIGHT_SUPERADMIN'))
            {
                if (in_array(Yii::app()->db->getDriverName(), array('mysql', 'mysqli')) || Yii::app()->getConfig('demo_mode') == true)
                {
                    return array(
                        'image' => 'backup.png',
                        'href' => array("admin/dumpdb"),
                        'alt' => gT('Backup Entire Database')
                    );
                }
                else
                {
                    return array(
                        'image' => 'backup_disabled.png',
                        'alt' => gT('The database export is only available for MySQL databases. For other database types please use the according backup mechanism to create a database dump.'),
                        'type' => 'image'
                    );
                }
            }
        }

        protected function editLabels()
        {
            if ($this->hasRight("USER_RIGHT_MANAGE_LABEL"))
            {
                return array(
                    'href' => array('admin/labels'),
                    'image' => 'labels.png',
                    'alt' => gT('Edit label sets')
                );
            }
        }

        protected function editTemplates()
        {
            if ($this->hasRight('USER_RIGHT_MANAGE_TEMPLATE'))
            {
                return array(
                    'href' => array('admin/templates/'),
                    'alt' => gT('Template Editor'),
                    'image' => 'templates.png'
                );
            }
        }

        protected function participantDatabase()
        {
            if ($this->hasRight('USER_RIGHT_PARTICIPANT_PANEL'))
            {
                return array(
                    'alt' => gT('Central participant database/panel'),
                    'href' => array('admin/participants'),
                    'image' => 'cpdb.png'
                 );
            }
        }

        /**
         * Function to check for rights for the current user.
         * Currently these rights are stored in the session directly. Since
         * this is bad practice this function is created to easily refactor
         * changing in the way rights are checked.
         * 
         * @param type $right
         */
        protected function hasRight($right)
        {
            return (Yii::app()->session[$right] == 1);
        }
    }

?>
