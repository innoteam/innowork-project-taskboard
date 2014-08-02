<?php

use \Innomatic\Core\InnomaticContainer;
use \Innomatic\Wui\Widgets;
use \Shared\Wui;

require_once('innowork/projects/InnoworkTask.php');
require_once('innowork/projects/InnoworkTaskField.php');
require_once('innowork/projects/InnoworkProject.php');

/**
 * Taskboard panel views.
 */
class InnoworktaskboardPanelViews extends \Innomatic\Desktop\Panel\PanelViews
{
    public $pageTitle;
    public $toolbars;
    public $pageStatus;
    public $taskboardId = 0;
    public $innoworkCore;
    public $xml;
    protected $localeCatalog;

    /**
     * Observer update method.
     *
     * @param Observable $observable
     * @param string $arg
     */
    public function update($observable, $arg = '')
    {
        switch ($arg) {
            case 'status':
                $this->pageStatus = $this->controller->getAction()->status;
                break;
            case 'taskboardid':
                $this->taskboardId = $this->controller->getAction()->taskboardId;
                break;
        }
    }

    /**
     * View begin helper.
     */
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

    /**
     * View end helper.
     */
    public function endHelper()
    {
        $this->wuiContainer->addChild(
            new WuiInnomaticPage('page', array(
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

    /**
     * Default view.
     *
     * @param array $eventData
     */
    public function viewDefault($eventData)
    {
        $innomaticCore = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');

        if (isset($eventData['taskboardid'])) {
            $currentTaskBoard = $eventData['taskboardid'];
        } else {
            $currentTaskBoard = 0;
        }

        $taskboardWidget = new \Shared\Wui\WuiTaskboard('taskboard');
        if (isset($taskboardWidget->mArgs['taskboardid'])) {
            $currentTaskBoard = $taskboardWidget->mArgs['taskboardid'];
        }

        // Archived taskboards?
        if (isset($eventData['done']) && $eventData['done'] == 'true') {
            $doneBoards = true;
            $doneFlag   = $innomaticCore->getCurrentDomain()->getDataAccess()->fmttrue;
        } else {
            $doneBoards = false;
            $doneFlag   = $innomaticCore->getCurrentDomain()->getDataAccess()->fmtfalse;
        }

        // Build the list of the available task boards
        $taskboard = InnoworkCore::getItem('taskboard');
        $taskboardSearchResults = $taskboard->search(
            array('done' => $doneFlag),
            $innomaticCore->getCurrentUser()->getUserId()
        );

        $this->xml = '<vertgroup><children>
            <raw><args><content>'.WuiXml::cdata('<link rel="stylesheet" type="text/css" href="'.
            $innomaticCore->getExternalBaseUrl().'/shared/taskboard/taskboard.css">').'</content></args></raw>';

        $taskboardsComboList = array();

        // If there is no task board, give the no task boards found message
        if (count($taskboardSearchResults) == 0) {
            // @todo
        } else {
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

        $this->xml .= '    <horizgroup><args><width>0%</width></args>
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

                <horizbar />';

            $this->xml .= '        <divframe><args><id>taskboard_widget</id></args><children>';

            if ($currentTaskBoard != 0 and strlen($currentTaskBoard)) {
                $this->xml .= '<taskboard><name>taskboard</name><args><taskboardid>'.$currentTaskBoard.'</taskboardid><archived>'.($doneBoards ? 'true' : 'false').'</archived></args></taskboard>';
            } else {
                $this->xml .= '<void/>';
            }

            $this->xml .= '</children></divframe>';
        }

        $this->xml .= '</children></vertgroup>';
    }

    /**
     * New taskboard view.
     *
     * @param array $eventData WUI event data
     *
     * @return void
     */
    public function viewNewtaskboard(
            $eventData
    )
    {
        $innomaticCore = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');

        // Check if the user has enough permissions to create taskboards
        if (!$innomaticCore->getCurrentUser()->hasPermission('add_taskboards')) {
            return $this->viewDefault();
        }
        // Projects

        require_once('innowork/projects/InnoworkProject.php');
        $innowork_projects = new InnoworkProject(
                $innomaticCore->getDataAccess(),
                $innomaticCore->getCurrentDomain()->getDataAccess()
        );
        $search_results = $innowork_projects->search(
                array('done' => $innomaticCore->getDataAccess()->fmtfalse),
                $innomaticCore->getCurrentUser()->getUserId()
        );
        $projects[0] = $this->localeCatalog->getStr('noproject.label');
        while (list($id, $fields) = each($search_results)) {
            $projects[$id] = $fields['name'];
        }

        // Table header with back to taskboard link
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
                                                    'settings'
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
                                                    'settings'
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

    /* public viewSettings($eventData) {{{ */
    /**
     * Settings view.
     *
     * @param array $eventData
     * @access public
     * @return void
     */
    public function viewSettings($eventData)
    {
        $innomaticCore = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');

        if ($this->taskboardId != 0) {
            $taskboardId = $this->taskboardId;
        } else {
            $taskboardId = $eventData['taskboardid'];
        }

        $taskboard = new InnoworkTaskboard(
            $innomaticCore->getDataAccess(),
            $innomaticCore->getCurrentDomain()->getDataAccess(),
            $taskboardId
        );

        $taskboardData = $taskboard->getItem($innomaticCore->getCurrentUser()->getUserId());

        // Projects list

        $innoworkProjects = new InnoworkProject(
            $innomaticCore->getDataAccess(),
            $innomaticCore->getCurrentDomain()->getDataAccess()
        );

        $projectsSearchResults = $innoworkProjects->search('', $innomaticCore->getCurrentUser()->getUserId());

        if (count($projectsSearchResults) == 0) {
            $projects['0'] = $this->localeCatalog->getStr('noproject_label');
        }

        while (list($id, $fields) = each($projectsSearchResults)) {
            $projects[$id] = $fields['name'];
        }

        // Archived taskboard?

        if ($taskboardData['done'] == $innomaticCore->getCurrentDomain()->getDataAccess()->fmttrue) {
            $done_icon = 'misc3';
            $done_action = 'false';
            $done_label = 'reopen_taskboard_button';
        } else {
            $done_icon = 'drawer';
            $done_action = 'true';
            $done_label = 'archive_taskboard_button';
        }

        $headers[0]['label'] = $this->localeCatalog->getStr('taskboard_settings_header').(strlen($taskboardData['title']) ? ' - '.$taskboardData['title'] : '');
        $headers[0]['link'] = \Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString(
                '',
                array(array('view', 'default', array('taskboardid' => $eventData['id'])))
            );

        $this->xml = '<horizgroup><children>

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
                                            array('view', 'settings', array('taskboardid' => $taskboardId)),
                                            array('action', 'edittaskboard', array('id' => $taskboardId))
                                    )
                            )
                    ).'</action>
          </args>
          <children>

            <vertgroup>
              <children>

                <horizgroup><args><width>0%</width></args>
                  <children>

                    <label>
                      <args>
                        <label>'.$this->localeCatalog->getStr('title_label').'</label>
                      </args>
                    </label>

                    <string><name>title</name>
                      <args>
                        <disp>action</disp>
                        <size>60</size>
                        <value>'.WuiXml::cdata($taskboardData['title']).'</value>
                      </args>
                    </string>

                  </children>
                </horizgroup>

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
                <label>'.WuiXml::cdata($this->localeCatalog->getStr('update_settings_button')).'</label>
                <formsubmit>task</formsubmit>
                <mainaction>true</mainaction>
                <frame>false</frame>
                <horiz>true</horiz>
                <action>'.WuiXml::cdata(
                            \Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString(
                                    '',
                                    array(
                                            array(
                                                    'view',
                                                    'settings',
                                                    array(
                                                            'taskboardid' => $taskboardId
                                                    )
                                            ),
                                            array(
                                                    'action',
                                                    'edittaskboard',
                                                    array(
                                                            'id' => $taskboardId
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
                                'settings',
                                array('taskboardid' => $taskboardId)
                        ),
                        array(
                                'action',
                                'edittaskboard',
                                array(
                                        'id' => $taskboardId,
                                        'done' => $done_action
                                ))
                ))).'</action>
            <label>'.WuiXml::cdata($this->localeCatalog->getStr($done_label)).'</label>
            <formsubmit>task</formsubmit>
          </args>
        </button>

            <button>
              <args>
                <themeimage>trash</themeimage>
                <label>'.WuiXml::cdata($this->localeCatalog->getStr('trash_taskboard_button')).'</label>
                <frame>false</frame>
                <horiz>true</horiz>
                <dangeraction>true</dangeraction>
                <needconfirm>true</needconfirm>
                <confirmmessage>'.$this->localeCatalog->getStr('remove_taskboard_confirm').'</confirmmessage>
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
                                                    'trashtaskboard',
                                                    array(
                                                            'id' => $taskboardId
                                                    )
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

  <innoworkitemacl><name>itemacl</name>
    <args>
      <itemtype>taskboard</itemtype>
      <itemid>'.$taskboardId.'</itemid>
      <itemownerid>'.$taskboardData['ownerid'].'</itemownerid>
      <defaultaction>'.WuiXml::cdata(\Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString('', array(
            array('view', 'settings', array('taskboardid' => $taskboardId))))).'</defaultaction>
    </args>
  </innoworkitemacl>
            </children></horizgroup>';
    }
    /* }}} */

    /* public viewSearchproject($eventData) {{{ */
    /**
     * Search project view.
     *
     * @param array $eventData
     * @access public
     * @return string json
     */
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
    /* }}} */
}
