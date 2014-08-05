<?php
namespace Shared\Wui;

/**
 * Taskboard widget.
 *
 * @author Alex Pagnoni <alex.pagnoni@innomatic.io>
 */
class WuiTaskboard extends \Innomatic\Wui\Widgets\WuiWidget
{
    /**
     * This widget uses the WUI session.
     *
     * @var bool
     * @access public
     */
    public $mUseSession = 'true';

    /**
     * Widget constructor
     */
    public function __construct (
        $elemName,
        $elemArgs = '',
        $elemTheme = '',
        $dispEvents = ''
    ) {
        parent::__construct($elemName, $elemArgs, $elemTheme, $dispEvents);

        if (!isset($this->mArgs['taskboardid'])) {
            $args = $this->retrieveSession();
            if (isset($args['taskboardid'])) {
                $this->mArgs['taskboardid'] = $args['taskboardid'];
            }
        } else {
            $this->storeSession($this->mArgs);
        }
    }

    /* protected generateSource() {{{ */
    /**
     * Generates widget HTML source.
     *
     * @access protected
     * @return void
     */
    protected function generateSource()
    {
        $innomaticCore = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');

        // Users list
        $usersQuery = $innomaticCore->getCurrentDomain()->getDataAccess()->execute("SELECT id,fname,lname FROM domain_users");
        $usersList = array();
        while (!$usersQuery->eof) {
            $usersList[$usersQuery->getFields('id')] = $usersQuery->getFields('fname').' '.$usersQuery->getFields('lname');
            $usersQuery->moveNext();
        }
        $usersQuery->free();

        // Taskboard projects list
        $projectsQuery = $innomaticCore->getCurrentDomain()->getDataAccess()->execute(
            'SELECT projectid
            FROM innowork_taskboards_projects
            WHERE taskboardid = '.$this->mArgs['taskboardid']
        );

        $projectIdList = array();

        if ($projectsQuery->getNumberRows() > 0) {
            while (!$projectsQuery->eof) {
                $projectIdList[] = $projectsQuery->getFields('projectid');
                $projectsQuery->moveNext();
            }
        }
        $projectsQuery->free();

        $taskboardId = $this->mArgs['taskboardid'];

        $localeCatalog = new LocaleCatalog(
            'innowork-projects-taskboard::widget',
            $innomaticCore->getCurrentUser()->getLanguage()
        );

        $innoworkCore = InnoworkCore::instance(
            'innoworkcore',
            $innomaticCore->getDataAccess(),
            $innomaticCore->getCurrentDomain()->getDataAccess()
        );

        // Get innowork summaries
        $summaries               = $innoworkCore->getSummaries();

        // Get summaries for user stories item types
        $userStoriesSummaries    = $innoworkCore->getSummaries('', false, array('userstory'));

        // Get summaries for technical tasks item types
        $technicalTasksSummaries = $innoworkCore->getSummaries('', false, array('technicaltask'));

        $userStoriesList = array();
        $taskList = array();
        $userStoriesTasksList = array();

        // Build user story list
        foreach ($userStoriesSummaries as $type => $values) {
            $userStoryClassName = $values['classname'];
            $tempObject = new $userStoryClassName(
                $innomaticCore->getDataAccess(),
                $innomaticCore->getCurrentDomain()->getDataAccess()
            );
            foreach ($projectIdList as $projectId) {
                $searchResults = $tempObject->search(
                    array('projectid' => $projectId, 'done' => $innomaticCore->getCurrentDomain()->getDataAccess()->fmtfalse),
                    $innomaticCore->getCurrentUser()->getUserId()
                );
                $userStoriesList = array_merge($userStoriesList, $searchResults);
            }
        }

        // Build technical task list
        foreach ($technicalTasksSummaries as $type => $values) {
            $taskClassName = $values['classname'];
            $tempObject = new $taskClassName(
                $innomaticCore->getDataAccess(),
                $innomaticCore->getCurrentDomain()->getDataAccess()
            );
            foreach ($projectIdList as $projectId) {
                $searchResults = $tempObject->search(
                    array('projectid' => $projectId, 'done' => $innomaticCore->getCurrentDomain()->getDataAccess()->fmtfalse),
                    $innomaticCore->getCurrentUser()->getUserId()
                );
                $taskList = array_merge($taskList, $searchResults);
            }
        }

        foreach ($taskList as $id => $values) {
            if (strlen($values['userstoryid']) && $values['userstoryid'] != 0) {
                $userStoriesTasksList[$values['userstoryid']][$id] = $values;
                unset($taskList[$id]);
            }
        }

        // Backlog and iteration tasks
        $backlogTasks = array();
        $iterationTasks = array();

        foreach ($taskList as $id => $values) {
            if (!(strlen($values['iterationid']) > 0 && $values['iterationid'] != 0)) {
                $backlogTasks['task-'.$id] = $values;
            } else {
                $iterationTasks[$id] = $values;
            }
        }

        // Backlog and iteration user stories
        $backlogUserStories = array();
        $iterationUserStories = array();

        // This keeps track of whole iteration story points
        $iterationStoryPoints = 0;

        foreach ($userStoriesList as $id => $values) {
            if (!(strlen($values['iterationid']) > 0 && $values['iterationid'] != 0)) {
                $backlogUserStories['userstory-'.$id] = $values;
            } else {
                $iterationUserStories[$id] = $values;
                // Add user story points to the iteration story points total
                $iterationStoryPoints += $values['storypoints'];
            }
        }

        // Merge backlog items
        $backlogItems = array_merge($backlogUserStories, $backlogTasks);

        // Task statuses
        $taskStatusList = InnoworkTaskField::getFields(InnoworkTaskField::TYPE_STATUS);

        // @todo sort by order

        /*
        print_r($userStoriesList);
        print_r($taskList);
        print_r($userStoriesTasksList);
         */

        $this->mLayout = ($this->mComments ? '<!-- begin ' . $this->mName . ' taskboard -->' : '');

        // Build the iteration story points label
        if (!($iterationStoryPoints > 0)) {
            $iterationStoryPoints = 0;
        }
        $iterationStoryPointsLabel = sprintf($localeCatalog->getStr('iteration_story_points'), $iterationStoryPoints);

        $this->mLayout .= '<table style="width: 100%; padding: 5px;">
    <tr>
        <td style="width: 250px; vertical-align: top;">
<table>
<tr>
<td>'.$localeCatalog->getStr('productbacklog.label').'</td>
</tr>
</table>
            <div id="backlog">
<table id="backlogtable">
<tr><td>
<div id="backlog" style="width: 250px;">';

        // Backlog items (user stories, tasks, bugs)
        foreach ($backlogItems as $itemTypeId => $item) {
            list($itemType, $itemId) = explode('-', $itemTypeId);

            // Story points
            if ($itemType == 'userstory' && $item['storypoints'] > 0) {
                $storyPoints = '<br/><strong>'.$item['storypoints'].'</strong>';
            } else {
                $storyPoints = '';
            }

            $this->mLayout .= '<div class="card '.$itemType.'" draggable="true" id="'.$itemType.'-'.$item['id'].'">'.
               '<a href="'.InnoworkCore::getShowItemAction($itemType, $item['id']).'">'.$summaries[$itemType]['label'].' '.$item['id'].'</a><br/>'.
                $item['title'].$storyPoints.'</div>';
        }

        $this->mLayout .= '
</div>
<!--
<p>Support Queue</p>
<div id="queue" style="width: 200px;">
  <div class="card" draggable="true"><header>A</header></div>
  <div class="card" draggable="true"><header>B</header></div>
  <div class="card" draggable="true"><header>C</header></div>
</div>
-->
</td></tr></table>
</div>
</td><td id="taskboard" class="taskboard" style="vertical-align: top;">
    <table style="width: 100%">
<tr>
<td>'.$localeCatalog->getStr('currentiteration.label').' - '.$iterationStoryPointsLabel.'</td>
<td style="align: right">';

        $buttonsXml = '<horizgroup><args><width>0%</width><groupalign>right</groupalign></args><children>            <button>
              <args>
                <themeimage>cycle</themeimage>
                <label>'.$localeCatalog->getstr('refreshboard_button').'</label>
                <frame>false</frame>
                <horiz>true</horiz>
                <action>javascript:void(0)</action>
                </args>
                  <events>
                  <click>
                  xajax_WuiTaskboardRefreshBoard('.$taskboardId.');
                  </click>
                  </events>
            </button>
            ';

        if ($innomaticCore->getCurrentUser()->hasPermission('add_taskboards')) {
            $buttonsXml .= '<button>
              <args>
                <themeimage>settings1</themeimage>
                <label>'.$localeCatalog->getstr('boardsettings_button').'</label>
                <frame>false</frame>
                <horiz>true</horiz>
                <action>'.WuiXml::cdata(
                            \Innomatic\Wui\Dispatch\WuiEventsCall::buildEventsCallString(
                                '',
                                array(array('view', 'settings', array('taskboardid' => $taskboardId)))
                            )).'</action>
            </args>
            </button>';
        }

        $buttonsXml .= '</children></horizgroup>';

        $this->mLayout .= WuiXml::getContentFromXml('', $buttonsXml);
        $this->mLayout .= '
</td>
</tr>
</table>

    <div id="taskboardDiv">';

        //<table id="taskboardtable" style="width: 100%; vertical-align: top;" border="1">
        //<tr><td style="text-align: center; width: 0%;">Story</td>';

        $cellWidth = 100 / count($taskStatusList);

        $this->mLayout .= '<table id="taskboardtable"' . ' border="0" cellspacing="2" cellpadding="1" width="100%" style="width: 100%; vertical-align: top; margin: 1px;"' . "><tr><td bgcolor=\"" . $this->mThemeHandler->mColorsSet['tables']['gridcolor'] . "\">\n";
        $this->mLayout .= '<table border="0" width="100%" cellspacing="1" cellpadding="3" bgcolor="' . $this->mThemeHandler->mColorsSet['tables']['bgcolor'] . "\">\n";
        $this->mLayout .= "<tr>\n";

        // Task Board column headers

        $this->mLayout .= '<td valign="top" width="0%"><table cellpadding="4" cellspacing="1" width="100%"><tr>';
        $this->mLayout .= '<td></td>';
        $this->mLayout .= '<td width="100%" valign="top" align="center" nowrap style="white-space: nowrap" bgcolor="' . $this->mThemeHandler->mColorsSet['tables']['headerbgcolor'] . '">' .
        $localeCatalog->getStr('user_story_header') . "</td>\n";
        $this->mLayout .= '</tr></table></td>';

        foreach ($taskStatusList as $id => $status) {
            $this->mLayout .= '<td valign="top" width="'.$cellWidth.'%"><table cellpadding="4" cellspacing="1" width="100%"><tr>';
            $this->mLayout .= '<td></td>';
            $this->mLayout .= '<td width="100%" valign="top" align="center" bgcolor="' . $this->mThemeHandler->mColorsSet['tables']['headerbgcolor'] . '">' .
            $status . "</td>\n";
            $this->mLayout .= '</tr></table></td>';
        }

        $this->mLayout .= "</tr>\n";

        // User stories and related tasks

        $storyCounter = 0;

        foreach ($iterationUserStories as $userStory) {
            // Story points
            if ($userStory['storypoints'] > 0) {
                $storyPoints = '<strong>'.$userStory['storypoints'].'</strong>';
            } else {
                $storyPoints = '';
            }

            $this->mLayout .= '<tr id="taskboard-userstory-row-'.$userStory['id'].'">'."\n";
            $this->mLayout .= '<td id="div-row'.$userStory['id'].'-0" class="cell" style="background-color: white; width: 0%;">
                <div id="card-userstory-'.$userStory['id'].'" class="card story">
                <header><a href="'.InnoworkCore::getShowItemAction('userstory', $userStory['id']).'">'.$userStoriesSummaries['userstory']['label'].' '.$userStory['id'].'</a><br/>'.mb_strimwidth($userStory['title'], 0, 50, '...').
                "<br/><br/>".
                $storyPoints.
                "</header>".
                "</div></td>\n";

            // Draw task cards
            foreach ($taskStatusList as $statusId => $statusLabel) {
                $this->mLayout .= '<td id="div-row'.$userStory['id'].'-'.$statusId.'" class="cell task"'."style=\"background-color: white; width: {$cellWidth}%;\">";
                if (isset($userStoriesTasksList[$userStory['id']])) {
                    foreach ($userStoriesTasksList[$userStory['id']] as $taskId => $taskValues) {
                        // Assigned to label
                        if ($taskValues['assignedto'] != 0 and $taskValues['assignedto'] != null) {
                            $assignedToLabel = $usersList[$taskValues['assignedto']];
                        } else {
                            $assignedToLabel = $localeCatalog->getStr('unassigned_card');
                        }

                        if ($taskValues['statusid'] == $statusId) {
                            $this->mLayout .= '<div id="card-task-'.$taskValues['id'].'" class="card task" draggable="true">'.
                                '<header><a href="'.InnoworkCore::getShowItemAction('task', $taskValues['id']).'">'.
                                $technicalTasksSummaries['task']['label'].' '.$taskValues['id'].'</a><br/>'.mb_strimwidth($taskValues['title'], 0, 50, '...').
                                "<br/><i>$assignedToLabel</i>".
                                '</header></div>';
                        }
                    }
                }
                $this->mLayout .= "</td>\n";
            }

            $this->mLayout .= "</tr>\n";
            $storyCounter++;
        }

        // Tasks not related to a user story
        if (count($iterationTasks) > 0) {
            $this->mLayout .= '<tr id="taskboard-userstory-row-0">'."\n";
            $this->mLayout .= '<td class="cell" style="background-color: white; width: 0%;"></td>'."\n";

            // Draw task cards
            foreach ($taskStatusList as $statusId => $statusLabel) {
                $this->mLayout .= '<td id="div-row0-'.$statusId.'" class="cell task"'."style=\"background-color: white; width: {$cellWidth}%;\">";
                foreach ($iterationTasks as $taskId => $taskValues) {
                    if ($taskValues['statusid'] == $statusId) {
                        $this->mLayout .= '<div id="card-task-'.$taskValues['id'].'" class="card task" draggable="true">'.
                            '<header><a href="'.InnoworkCore::getShowItemAction('task', $taskValues['id']).'">'.$technicalTasksSummaries['task']['label'].' '.$taskValues['id'].'</a> - '.mb_strimwidth($taskValues['title'], 0, 50, '...').'</header></div>';
                    }
                }
                $this->mLayout .= "</td>\n";
            }

            $this->mLayout .= "</tr>\n";
        }

        $this->mLayout .= '</table></td></tr>' . "\n" . '</table>' . "\n";

        $this->mLayout .= '
    </div>
</td><td style="width: 200px; vertical-align: top;">
<table>
<tr>
<td>'.$localeCatalog->getStr('increments.label').'</td>
</tr>
</table>
        </td>
    </tr>
    </table>
';

        $this->mLayout .= WuiXml::getContentFromXml('', '<formarg><args><id>taskboardid</id><value>'.$taskboardId.'</value></args></formarg>');

        $this->mLayout .= '<script>'.file_get_contents(\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getHome().'/shared/taskboard/taskboard.js').'</script>';

        $this->mLayout .= $this->mComments ? '<!-- end ' . $this->mName . " taskboard -->\n" : '';

        return true;
    }
    /* }}} */

    /* public ajaxAddToTaskboard($taskBoardId, $card) {{{ */
    /**
     * Ajax call to add a card to the task board.
     *
     * @param integer $taskBoardId Destination taskboard id
     * @param string $card Card string in the "<tasktype>-<taskid>" format
     * @static
     * @access public
     * @return XajaxResponse
     */
    public static function ajaxAddToTaskboard($taskBoardId, $card)
    {
        $objResponse = new XajaxResponse();

        require_once('innowork/taskboard/InnoworkTaskBoard.php');
        $taskboard = new InnoworkTaskBoard(
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess(),
            $taskBoardId
        );
        list($taskType, $taskId) = explode('-', $card);
        $taskboard->addTaskToCurrentIteration($taskType, $taskId);

        $xml = '<taskboard><name>taskboard</name><args><taskboardid>'.$taskBoardId.'</taskboardid></args></taskboard>';
        $html = WuiXml::getContentFromXml('', $xml);
        $objResponse->addAssign('taskboard_widget', 'innerHTML', $html);

        return $objResponse;
    }
    /* }}} */

    /* public ajaxBackToBacklog($taskBoardId, $card) {{{ */
    /**
     * Ajax call to send a taskboard card back to the backlog.
     *
     * @param integer $taskBoardId
     * @param string $card Card in the "card-<tasktype>-<taskid>" format
     * @static
     * @access public
     * @return XajaxResponse
     */
    public static function ajaxBackToBacklog($taskBoardId, $card)
    {
        $objResponse = new XajaxResponse();

        require_once('innowork/taskboard/InnoworkTaskBoard.php');
        $taskboard = new InnoworkTaskBoard(
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess(),
            $taskBoardId
        );

        list($cardName, $taskType, $taskId) = explode('-', $card);
        $taskboard->removeTaskFromCurrentIteration($taskType, $taskId);

        $xml = '<taskboard><name>taskboard</name><args><taskboardid>'.$taskBoardId.'</taskboardid></args></taskboard>';
        $html = WuiXml::getContentFromXml('', $xml);
        $objResponse->addAssign('taskboard_widget', 'innerHTML', $html);

        return $objResponse;
    }
    /* }}} */

    /* public ajaxUpdateTaskStatus($taskBoardId, $card, $statusId) {{{ */
    /**
     * Ajax call to update task status column.
     *
     * @param integer $taskBoardId Taskboard id
     * @param string $card Card name in the "card-<tasktype>-<taskid>" format
     * @param integer $statusId New status id
     * @static
     * @access public
     * @return XajaxResponse
     */
    public static function ajaxUpdateTaskStatus($taskBoardId, $card, $statusId)
    {
        $objResponse = new XajaxResponse();

        require_once('innowork/taskboard/InnoworkTaskBoard.php');
        $taskboard = new InnoworkTaskBoard(
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess(),
            $taskBoardId
        );

        list($cardName, $taskType, $taskId) = explode('-', $card);
        $taskboard->setTaskStatus($taskType, $taskId, $statusId);

        $xml = '<taskboard><name>taskboard</name><args><taskboardid>'.$taskBoardId.'</taskboardid></args></taskboard>';
        $html = WuiXml::getContentFromXml('', $xml);
        $objResponse->addAssign('taskboard_widget', 'innerHTML', $html);

        return $objResponse;
    }
    /* }}} */

    /* public ajaxRefreshBoard($taskBoardId) {{{ */
    /**
     * Ajax call to refresh the whole taskboard (including the product backlog
     * and the previous iterations list).
     *
     * @param integer $taskBoardId Taskboard id
     * @static
     * @access public
     * @return XajaxResponse
     */
    public static function ajaxRefreshBoard($taskBoardId)
    {
        $objResponse = new XajaxResponse();

        $xml = '<taskboard><name>taskboard</name><args><taskboardid>'.$taskBoardId.'</taskboardid></args></taskboard>';
        $html = WuiXml::getContentFromXml('', $xml);
        $objResponse->addAssign('taskboard_widget', 'innerHTML', $html);

        return $objResponse;
    }
    /* }}} */
}
