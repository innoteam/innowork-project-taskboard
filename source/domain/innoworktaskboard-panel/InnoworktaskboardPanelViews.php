<?php

use \Innomatic\Core\InnomaticContainer;
use \Innomatic\Wui\Widgets;
use \Shared\Wui;

require_once('innowork/projects/InnoworkTask.php');
require_once('innowork/projects/InnoworkTaskField.php');
require_once('innowork/projects/InnoworkProject.php');

class InnoworktaskboardPanelViews extends \Innomatic\Desktop\Panel\PanelViews
{
    public $pageTitle;
    public $toolbars;
    public $pageStatus;
    public $innoworkCore;
    public $xml;
    protected $localeCatalog;

    public function update($observable, $arg = '')
    {
        switch ($arg) {
            case 'status':
                $this->pageStatus = $this->_controller->getAction()->status;
                break;
        }
    }

    public function beginHelper()
    {
        $this->localeCatalog = new LocaleCatalog(
            'innowork-projects-taskboard::taskboard_panel',
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getLanguage()
        );

        $this->innoworkCore = InnoworkCore::instance('innoworkcore',
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()
        );

$this->pageTitle = $this->localeCatalog->getStr('taskboard.title');
$this->toolbars['taskboards'] = array(
    'tasks' => array(
        'label' => $this->localeCatalog->getStr('taskboards.toolbar'),
        'themeimage' => 'listbulletleft',
        'horiz' => 'true',
        'action' => \Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString('', array(array(
            'view',
            'default',
            array('done' => 'false'))))
       ),
    'donetasks' => array(
        'label' => $this->localeCatalog->getStr('donetaskboards.toolbar'),
        'themeimage' => 'drawer',
        'horiz' => 'true',
        'action' => \Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString('', array(array(
            'view',
            'default',
            array('done' => 'true'))))
        )
    );

        if (\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->hasPermission('add_taskboards')) {
            $this->toolbars['taskboards']['newtask'] = array(
                'label' => $this->localeCatalog->getStr('newtaskboard.toolbar'),
                'themeimage' => 'mathadd',
                'horiz' => 'true',
                'action' => \Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString('', array(array(
                    'view',
                    'newtaskboard',
                    '')))
               );
        }
    }

    public function endHelper()
    {
        $this->_wuiContainer->addChild(new WuiInnomaticPage('page', array(
            'pagetitle' => $this->pageTitle,
            'icon' => 'card2',
            'toolbars' => array(
                new WuiInnomaticToolbar(
                    'view',
                    array(
                        'toolbars' => $this->toolbars, 'toolbar' => 'true'
                       )),
                new WuiInnomaticToolBar(
                    'core',
                    array(
                        'toolbars' => $this->innoworkCore->getMainToolBar(), 'toolbar' => 'true'
                       ))
                   ),
            'maincontent' => new WuiXml(
                'page', array(
                    'definition' => $this->xml
                   )),
            'status' => $this->pageStatus
           )));
    }

    public function viewDefault($eventData)
    {
        $currentTaskBoard = 0;

        // Build the list of the available task boards
        $taskboard = InnoworkCore::getItem('taskboard');
        $taskboardSearchResults = $taskboard->search(
            array('done' => \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess()->fmtfalse),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserId()
        );

        $taskboardsComboList = array();

        // If there is no task board, give the no task boards found message
        if (count($taskboardSearchResults) == 0) {
            // @todo
        }

        // Check if the saved current board still exists/is accessible
        if (!isset($taskboardSearchResults[$currentTaskBoard])) {
            $currentTaskBoard = 0;
        }

        // Build the task board combo list
        foreach ($taskboardSearchResults as $id => $taskboardValues) {
            if ($currentTaskBoard == 0) {
                $currentTaskBoard = $id;
            }
            $taskboardsComboList[$id] = $taskboardValues['title'];
        }

        $this->xml = '<vertgroup><children>

            <horizgroup><args><width>0%</width></args>
            <children>
            <label><args><label>'.WuiXml::cdata($this->localeCatalog->getStr('taskboard_selection_label')).'</label></args></label>
            <combobox><args><id>taskboard_selector</id><default>'.WuiXml::cdata($currentTaskBoard).'</default><elements type="array">'.WuiXml::encode($taskboardsComboList).'</elements></args>
              <events>
              <change>
              var taskboard = document.getElementById(\'taskboard_selector\');
              var taskboardvalue = taskboard.options[taskboard.selectedIndex].value;
              var elements = taskboardvalue.split(\'/\');
              xajax_WuiTaskboardRefreshBoard(taskboardvalue)</change>
              </events>
            </combobox>
            </children>
            </horizgroup>

                <horizbar />

                <divframe><args><id>taskboard_widget</id></args><children>';

        if ($currentTaskBoard != 0 and strlen($currentTaskBoard)) {
            $this->xml .= '<taskboard><args><taskboardid>'.$currentTaskBoard.'</taskboardid></args></taskboard>';
        } else {
            $this->xml .= '<void/>';
        }

        $this->xml .= '</children></divframe></children></vertgroup>';
    }

    public function viewNewtaskboard(
            $eventData
    )
    {
        // Check if the user has enough permissions to create taskboards
        if (!\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->hasPermission('add_taskboards')) {
            return $this->viewDefault();
        }
        // Projects

        require_once('innowork/projects/InnoworkProject.php');
        $innowork_projects = new InnoworkProject(
                \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
                \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()
        );
        $search_results = $innowork_projects->search(
                array('done' => \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess()->fmtfalse),
                \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserId()
        );
        $projects[0] = $this->localeCatalog->getStr('noproject.label');
        while (list($id, $fields) = each($search_results)) {
            $projects[$id] = $fields['name'];
        }

        $headers[0]['label'] = $this->localeCatalog->getStr('newtaskboard.header');

        $this->xml =
        '
<vertgroup>
  <children>

    <table>
      <args>
        <headers type="array">'.WuiXml::encode($headers).'</headers>
      </args>
      <children>

        <form row="0" col="0"><name>newtask</name>
          <args>
                <action>'.WuiXml::cdata(
                            \Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString(
                                    '',
                                    array(
                                            array(
                                                    'view',
                                                    'default'
                                            ),
                                            array(
                                                    'action',
                                                    'newtaskboard'
                                            )
                                    )
                            )
                    ).'</action>
          </args>
          <children>
            <grid>
              <children>

                <label row="0" col="0">
                  <args>
                    <label>'.$this->localeCatalog->getStr('taskboardname.label').'</label>
                  </args>
                </label>

    			<string row="0" col="1"><name>title</name>
              <args>
            		<id>title</id>
                <disp>action</disp>
                <size>30</size>
              </args>
            		</string>

              </children>
            </grid>
          </children>
        </form>

        <horizgroup row="1" col="0">
          <children>

            <button>
              <args>
                <themeimage>buttonok</themeimage>
                <label>'.$this->localeCatalog->getstr('new_taskboard.button').'</label>
                <formsubmit>newtask</formsubmit>
                <frame>false</frame>
                <horiz>true</horiz>
                <action>'.wuixml::cdata(
                            \innomatic\wui\dispatch\wuieventscall::buildeventscallstring(
                                    '',
                                    array(
                                            array(
                                                    'view',
                                                    'default'
                                            ),
                                            array(
                                                    'action',
                                                    'newtaskboard'
                                            )
                                    )
                            )
                    ).'</action>
              </args>
            </button>

          </children>
        </horizgroup>

      </children>
    </table>

  </children>
</vertgroup>';
    }

    public function viewShowtask(
            $eventData
    )
    {
        $locale_country = new \Innomatic\Locale\LocaleCountry(
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getCountry()
        );

        if (isset($GLOBALS['innowork-tasks']['newtaskid'])) {
            $eventData['id'] = $GLOBALS['innowork-tasks']['newtaskid'];
            $newTask = true;
        } else {
            $newTask = false;
        }

        $innowork_task = new InnoworkTask(
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess(),
            $eventData['id']
        );

        $task_data = $innowork_task->getItem(\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserId());

        // Projects list

        $innowork_projects = new InnoworkProject(
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()
        );
        $search_results = $innowork_projects->Search(
            '',
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserId()
        );

        $projects['0'] = $this->localeCatalog->getStr('noproject.label');

        while (list($id, $fields) = each($search_results)) {
            $projects[$id] = $fields['name'];
        }

        // Companies

        // "Assigned to" user
        if ($task_data['assignedto'] != '') {
            $assignedto_user = $task_data['assignedto'];
        } else {
            $assignedto_user = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserId();
        }

        // "Opened by" user
        if ($task_data['openedby'] != '') {
            $openedby_user = $task_data['openedby'];
        } else {
            $openedby_user = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserId();
        }

        $users_query = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()->execute(
                'SELECT id,fname,lname '.
                'FROM domain_users '.
                'WHERE username<>'.\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()->formatText(User::getAdminUsername(\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDomainId())).' '.
                'ORDER BY lname,fname');

        $users = array();
        $users[0] = $this->localeCatalog->getStr('noone.label');

        while (!$users_query->eof) {
            $users[$users_query->getFields('id')] = $users_query->getFields('lname').' '.$users_query->getFields('fname');
            $users_query->moveNext();
        }

        $statuses = InnoworkTaskField::getFields(InnoworkTaskField::TYPE_STATUS);
        if (($newTask == false and $task_data['statusid'] == 0) or !count($statuses)) {
            $statuses['0'] = $this->localeCatalog->getStr('nostatus.label');
        }

        $priorities = InnoworkTaskField::getFields(InnoworkTaskField::TYPE_PRIORITY);
        if (($newTask == false and $task_data['priorityid'] == 0) or !count($priorities)) {
            $priorities['0'] = $this->localeCatalog->getStr('nopriority.label');
        }

        $resolutions = InnoworkTaskField::getFields(InnoworkTaskField::TYPE_RESOLUTION);
        if (($newTask == false and $task_data['resolutionid'] == 0) or !count($resolutions)) {
            $resolutions['0'] = $this->localeCatalog->getStr('noresolution.label');
        }

        $types = InnoworkTaskField::getFields(InnoworkTaskField::TYPE_TYPE);
        if (($newTask == false and $task_data['typeid'] == 0) or !count($types)) {
            $types['0'] = $this->localeCatalog->getStr('notype.label');
        }

        if ($task_data['done'] == \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()->fmttrue) {
            $done_icon = 'misc3';
            $done_action = 'false';
            $done_label = 'setundone.button';
        } else {
            $done_icon = 'drawer';
            $done_action = 'true';
            $done_label = 'archive_task.button';
        }

        $headers[0]['label'] = sprintf($this->localeCatalog->getStr('showtask.header'), $task_data['id']).(strlen($task_data['title']) ? ' - '.$task_data['title'] : '');

        $this->xml =
        '
<horizgroup>
  <children>

    <table><name>task</name>
      <args>
        <headers type="array">'.WuiXml::encode($headers).'</headers>
      </args>
      <children>

        <form row="0" col="0"><name>task</name>
          <args>
                <action>'.WuiXml::cdata(
                            \Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString(
                                    '',
                                    array(
                                            array(
                                                    'view',
                                                    'showtask',
                                                    array(
                                                            'id' => $eventData['id']
                                                    )
                                            ),
                                            array(
                                                    'action',
                                                    'edittask',
                                                    array(
                                                            'id' => $eventData['id']
                                                    )
                                            )
                                    )
                            )
                    ).'</action>
          </args>
          <children>

            <vertgroup>
              <children>

                <horizgroup>
                  <args>
                    <align>middle</align>
                    <width>0%</width>
                  </args>
                  <children>

                    <label>
                      <args>
                        <label>'.$this->localeCatalog->getStr('project.label').'</label>
                      </args>
                    </label>

                    <combobox><name>projectid</name>
                      <args>
                        <disp>action</disp>
                        <elements type="array">'.WuiXml::encode($projects).'</elements>
                        <default>'.$task_data['projectid'].'</default>
                      </args>
                    </combobox>

                  </children>
                </horizgroup>

                <horizgroup><args><width>0%</width></args><children>

            <label><name>openedby</name>
              <args>
                <label>'.WuiXml::cdata($this->localeCatalog->getStr('openedby.label')).'</label>
              </args>
            </label>
            <combobox><name>openedby</name>
              <args>
                <disp>action</disp>
                <elements type="array">'.WuiXml::encode($users).'</elements>
                <default>'.$openedby_user.'</default>
              </args>
            </combobox>

            <label><name>assignedto</name>
              <args>
                <label>'.WuiXml::cdata($this->localeCatalog->getStr('assignedto.label')).'</label>
              </args>
            </label>
            <combobox><name>assignedto</name>
              <args>
                <disp>action</disp>
                <elements type="array">'.WuiXml::encode($users).'</elements>
                <default>'.$assignedto_user.'</default>
              </args>
            </combobox>

                </children></horizgroup>

                <horizbar/>

                <grid>
                  <children>

                    <label row="0" col="0" halign="right">
                      <args>
                        <label>'.$this->localeCatalog->getStr('type.label').'</label>
                      </args>
                    </label>

                    <combobox row="0" col="1"><name>typeid</name>
                      <args>
                        <disp>action</disp>
                        <elements type="array">'.WuiXml::encode($types).'</elements>
                        <default>'.$task_data['typeid'].'</default>
                      </args>
                    </combobox>

                    <label row="0" col="2" halign="right">
                      <args>
                        <label>'.$this->localeCatalog->getStr('status.label').'</label>
                      </args>
                    </label>

                    <combobox row="0" col="3"><name>statusid</name>
                      <args>
                        <disp>action</disp>
                        <elements type="array">'.WuiXml::encode($statuses).'</elements>
                        <default>'.$task_data['statusid'].'</default>
                      </args>
                    </combobox>

                    <label row="0" col="4" halign="right">
                      <args>
                        <label>'.$this->localeCatalog->getStr('priority.label').'</label>
                      </args>
                    </label>

                    <combobox row="0" col="5"><name>priorityid</name>
                      <args>
                        <disp>action</disp>
                        <elements type="array">'.WuiXml::encode($priorities).'</elements>
                        <default>'.$task_data['priorityid'].'</default>
                      </args>
                    </combobox>

                    <label row="1" col="0" halign="right">
                      <args>
                        <label>'.$this->localeCatalog->getStr('resolution.label').'</label>
                      </args>
                    </label>

                    <combobox row="1" col="1"><name>resolutionid</name>
                      <args>
                        <disp>action</disp>
                        <elements type="array">'.WuiXml::encode($resolutions).'</elements>
                        <default>'.$task_data['resolutionid'].'</default>
                      </args>
                    </combobox>

                  </children>
                </grid>

                <horizbar/>

                <horizgroup><args><width>0%</width></args>
                  <children>

                    <label>
                      <args>
                        <label>'.$this->localeCatalog->getStr('title.label').'</label>
                      </args>
                    </label>

                    <string><name>title</name>
                      <args>
                        <disp>action</disp>
                        <size>80</size>
                        <value>'.WuiXml::cdata($task_data['title']).'</value>
                      </args>
                    </string>

                  </children>
                </horizgroup>

                <label>
                  <args>
                    <label>'.$this->localeCatalog->getStr('description.label').'</label>
                  </args>
                </label>

                <text><name>description</name>
                  <args>
                    <disp>action</disp>
                    <rows>6</rows>
                    <cols>100</cols>
                    <value>'.WuiXml::cdata($task_data['description']).'</value>
                  </args>
                </text>

              </children>
            </vertgroup>

          </children>
        </form>

        <horizgroup row="1" col="0">
          <args><width>0%</width></args>
          <children>
            <button>
              <args>
                <themeimage>buttonok</themeimage>
                <label>'.WuiXml::cdata($this->localeCatalog->getStr('update_task.button')).'</label>
                <formsubmit>task</formsubmit>
                <frame>false</frame>
                <horiz>true</horiz>
                <action>'.WuiXml::cdata(
                            \Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString(
                                    '',
                                    array(
                                            array(
                                                    'view',
                                                    'showtask',
                                                    array(
                                                            'id' => $eventData['id']
                                                    )
                                            ),
                                            array(
                                                    'action',
                                                    'edittask',
                                                    array(
                                                            'id' => $eventData['id']
                                                    )
                                            )
                                    )
                            )
                    ).'</action>
              </args>
            </button>

            <button>
              <args>
                <themeimage>attach</themeimage>
                <label>'.$this->localeCatalog->getStr('add_message.button').'</label>
                <frame>false</frame>
                <horiz>true</horiz>
                <target>messages</target>
                <action>'.WuiXml::cdata(
                            \Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString(
                                    '',
                                    array(
                                            array(
                                                    'view',
                                                    'addmessage',
                                                    array(
                                                            'taskid' => $eventData['id']
                                                    )
                                            )
                                    )
                            )
                    ).'</action>
              </args>
            </button>

        <button><name>setdone</name>
          <args>
            <themeimage>'.$done_icon.'</themeimage>
            <horiz>true</horiz>
            <frame>false</frame>
            <action>'.WuiXml::cdata(\Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString('', array(
                        array(
                                'view',
                                'default',
                                ''
                        ),
                        array(
                                'action',
                                'edittask',
                                array(
                                        'id' => $eventData['id'],
                                        'done' => $done_action
                                ))
                ))).'</action>
            <label>'.$this->localeCatalog->getStr($done_label).'</label>
            <formsubmit>task</formsubmit>
          </args>
        </button>

            <button>
              <args>
                <themeimage>trash</themeimage>
                <label>'.$this->localeCatalog->getStr('trash_task.button').'</label>
                <frame>false</frame>
                <horiz>true</horiz>
                <action>'.WuiXml::cdata(
                            \Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString(
                                    '',
                                    array(
                                            array(
                                                    'view',
                                                    'default'
                                            ),
                                            array(
                                                    'action',
                                                    'trashtask',
                                                    array(
                                                            'id' => $eventData['id']
                                                    )
                                            )
                                    )
                            )
                    ).'</action>
              </args>
            </button>

          </children>
        </horizgroup>

        <iframe row="2" col="0"><name>messages</name>
          <args>
            <width>450</width>
            <height>200</height>
            <source>'.WuiXml::cdata(
                        \Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString(
                                '',
                                array(
                                        array(
                                                'view',
                                                'taskmessages',
                                                array(
                                                        'taskid' => $eventData['id']
                                                )
                                        )
                                )
                        )
                ).'</source>
            <scrolling>auto</scrolling>
          </args>
        </iframe>

      </children>
    </table>

  <innoworkitemacl><name>itemacl</name>
    <args>
      <itemtype>taskboard</itemtype>
      <itemid>'.$eventData['id'].'</itemid>
      <itemownerid>'.$task_data['ownerid'].'</itemownerid>
      <defaultaction>'.WuiXml::cdata(\Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString('', array(
            array('view', 'showtask', array('id' => $eventData['id']))))).'</defaultaction>
    </args>
  </innoworkitemacl>

  </children>
</horizgroup>';
    }

    public function viewSearchproject($eventData)
    {
        $domain_da = InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess();

        $query = $domain_da->execute('SELECT id, name FROM innowork_projects WHERE name LIKE "%'.$_GET['term'].'%" AND done <> '.$domain_da->formatText($domain_da->fmttrue));
        $k = 0;

        while (!$query->eof) {
            $content[$k]['id'] = $query->getFields('id');
            $content[$k++]['value'] = $query->getFields('name');
            $query->moveNext();
        }
        echo json_encode($content);
        InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->halt();
    }
}

function tasks_list_action_builder($pageNumber)
{
	return \Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString('', array(array(
			'view',
			'default',
			array('pagenumber' => $pageNumber)
	)));
}
