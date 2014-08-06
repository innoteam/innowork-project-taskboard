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
        // InnomaticCore
        $innomaticCore = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');

        // Taskboard id, mainly used in the html as id for javascript routines
        $taskboardId = $this->mArgs['taskboardid'];

        // Widget locale catalog
        $localeCatalog = new LocaleCatalog(
            'innowork-projects-taskboard::widget',
            $innomaticCore->getCurrentUser()->getLanguage()
        );

        // Taskboard object
        $taskboard = InnoworkCore::getItem('taskboard', $taskboardId);
        $board = $taskboard->getBoardStructure();

        // Innowork core
        $innoworkCore = InnoworkCore::instance(
            'innoworkcore',
            $innomaticCore->getDataAccess(),
            $innomaticCore->getCurrentDomain()->getDataAccess()
        );

        // Get innowork summaries
        $summaries               = $innoworkCore->getSummaries();
        // Users list
        $usersQuery = $innomaticCore->getCurrentDomain()->getDataAccess()->execute("SELECT id,fname,lname FROM domain_users");
        $usersList = array();
        while (!$usersQuery->eof) {
            $usersList[$usersQuery->getFields('id')] = $usersQuery->getFields('fname').' '.$usersQuery->getFields('lname');
            $usersQuery->moveNext();
        }
        $usersQuery->free();

        // Widget locale catalog
        $localeCatalog = new LocaleCatalog(
            'innowork-projects-taskboard::widget',
            $innomaticCore->getCurrentUser()->getLanguage()
        );

        $this->mLayout = ($this->mComments ? '<!-- begin ' . $this->mName . ' taskboard -->' : '');

        // Build the backlog story points label
        $backlogStoryPointsLabel = sprintf($localeCatalog->getStr('backlog_story_points'), $board['backlogstorypoints']);

        // Build the iteration story points label
        $iterationStoryPointsLabel = sprintf($localeCatalog->getStr('iteration_story_points'), $board['iterationstorypoints']);

        $this->mLayout .= '<table style="width: 100%; padding: 5px;">
    <tr>
        <td style="width: 250px; vertical-align: top;">
<table>
<tr>
<td>'.$localeCatalog->getStr('productbacklog.label').' - '.$backlogStoryPointsLabel.'</td>
</tr>
</table>
            <div id="backlog">
<table id="backlogtable">
<tr><td>
<div id="backlog" style="width: 250px;">';

        // Backlog items (user stories, tasks, bugs)
        foreach ($board['backlogitems'] as $itemTypeId => $item) {
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

        // Add close iteration button if the user has enough permissions
        if ($innomaticCore->getCurrentUser()->hasPermission('close_iterations')) {
            $buttonsXml .= '<button>
              <args>
                <themeimage>lock</themeimage>
                <label>'.$localeCatalog->getstr('close_iteration_button').'</label>
                <frame>false</frame>
                <horiz>true</horiz>
                <needconfirm>true</needconfirm>
                <confirmmessage></confirmmessage>
                <action>javascript:void(0)</action>
                </args>
                  <events>
                  <click>
if (confirm(\''.addslashes($localeCatalog->getStr('close_iteration_confirm')).'\')) {
    xajax_WuiTaskboardCloseIteration('.$taskboardId.');
}
                  </click>
                  </events>
            </button>';
        }

        // Add settings button if the user has enough permissions
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

        $cellWidth = 100 / count($board['taskstatuslist']);

        $this->mLayout .= '<table id="taskboardtable"' . ' border="0" cellspacing="2" cellpadding="1" width="100%" style="width: 100%; vertical-align: top; margin: 1px;"' . "><tr><td bgcolor=\"" . $this->mThemeHandler->mColorsSet['tables']['gridcolor'] . "\">\n";
        $this->mLayout .= '<table border="0" width="100%" cellspacing="1" cellpadding="3" bgcolor="' . $this->mThemeHandler->mColorsSet['tables']['bgcolor'] . "\">\n";
        $this->mLayout .= "<tr>\n";

        // Task Board column headers

        $this->mLayout .= '<td valign="top" width="0%"><table cellpadding="4" cellspacing="1" width="100%"><tr>';
        $this->mLayout .= '<td></td>';
        $this->mLayout .= '<td width="100%" valign="top" align="center" nowrap style="white-space: nowrap" bgcolor="' . $this->mThemeHandler->mColorsSet['tables']['headerbgcolor'] . '">' .
        $localeCatalog->getStr('user_story_header') . "</td>\n";
        $this->mLayout .= '</tr></table></td>';

        foreach ($board['taskstatuslist'] as $id => $status) {
            $this->mLayout .= '<td valign="top" width="'.$cellWidth.'%"><table cellpadding="4" cellspacing="1" width="100%"><tr>';
            $this->mLayout .= '<td></td>';
            $this->mLayout .= '<td width="100%" valign="top" align="center" bgcolor="' . $this->mThemeHandler->mColorsSet['tables']['headerbgcolor'] . '">' .
            $status . "</td>\n";
            $this->mLayout .= '</tr></table></td>';
        }

        $this->mLayout .= "</tr>\n";

        // User stories and related tasks

        $storyCounter = 0;

        foreach ($board['iterationuserstories'] as $userStory) {
            // Story points
            if ($userStory['storypoints'] > 0) {
                $storyPoints = '<strong>'.$userStory['storypoints'].'</strong>';
            } else {
                $storyPoints = '';
            }

            $this->mLayout .= '<tr id="taskboard-userstory-row-'.$userStory['id'].'">'."\n";
            $this->mLayout .= '<td id="div-row'.$userStory['id'].'-0" class="cell" style="background-color: white; width: 0%;">
                <div id="card-userstory-'.$userStory['id'].'" class="card story">
                <header><a href="'.InnoworkCore::getShowItemAction('userstory', $userStory['id']).'">'.$summaries['userstory']['label'].' '.$userStory['id'].'</a><br/>'.mb_strimwidth($userStory['title'], 0, 70, '...').
                "<br/><br/>".
                $storyPoints.
                "</header>".
                "</div></td>\n";

            // Draw task cards
            foreach ($board['taskstatuslist'] as $statusId => $statusLabel) {
                $this->mLayout .= '<td id="div-row'.$userStory['id'].'-'.$statusId.'" class="cell task"'."style=\"background-color: white; width: {$cellWidth}%;\">";
                if (isset($board['userstoriestasklist'][$userStory['id']])) {
                    foreach ($board['userstoriestasklist'][$userStory['id']] as $taskId => $taskValues) {
                        // Assigned to label
                        if ($taskValues['assignedto'] != 0 and $taskValues['assignedto'] != null) {
                            $assignedToLabel = $usersList[$taskValues['assignedto']];
                        } else {
                            $assignedToLabel = $localeCatalog->getStr('unassigned_card');
                        }

                        if ($taskValues['statusid'] == $statusId) {
                            $this->mLayout .= '<div id="card-task-'.$taskValues['id'].'" class="card task" draggable="true">'.
                                '<header><a href="'.InnoworkCore::getShowItemAction('task', $taskValues['id']).'">'.
                                $summaries['task']['label'].' '.$taskValues['id'].'</a><br/>'.mb_strimwidth($taskValues['title'], 0, 50, '...').
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
        if (count($board['iterationtasks']) > 0) {
            $this->mLayout .= '<tr id="taskboard-userstory-row-0">'."\n";
            $this->mLayout .= '<td class="cell" style="background-color: white; width: 0%;"></td>'."\n";

            // Draw task cards
            foreach ($board['taskstatuslist'] as $statusId => $statusLabel) {
                $this->mLayout .= '<td id="div-row0-'.$statusId.'" class="cell task"'."style=\"background-color: white; width: {$cellWidth}%;\">";
                foreach ($board['iterationtasks'] as $taskId => $taskValues) {
                    // Assigned to label
                    if ($taskValues['assignedto'] != 0 and $taskValues['assignedto'] != null) {
                        $assignedToLabel = $usersList[$taskValues['assignedto']];
                    } else {
                        $assignedToLabel = $localeCatalog->getStr('unassigned_card');
                    }

                    if ($taskValues['statusid'] == $statusId) {
                        $this->mLayout .= '<div id="card-task-'.$taskValues['id'].'" class="card task" draggable="true">'.
                            '<header><a href="'.InnoworkCore::getShowItemAction('task', $taskValues['id']).'">'.
                            $summaries['task']['label'].' '.$taskValues['id'].'</a> - '.mb_strimwidth($taskValues['title'], 0, 50, '...').
                            "<br/><i>$assignedToLabel</i>".
                            '</header></div>';
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
        $taskboard = InnoworkCore::getItem('taskboard', $taskBoardId);

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
        $taskboard = InnoworkCore::getItem('taskboard', $taskBoardId);

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
        $taskboard = InnoworkCore::getItem('taskboard', $taskBoardId);

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

    /* public ajaxCloseIteration($taskBoardId) {{{ */
    /**
     * Ajax call to close the current iteration, archive done stories/tasks
     * and open a new iteration.
     *
     * @param mixed $taskBoardId Taskboard id
     * @static
     * @access public
     * @return XajaxResponse
     */
    public static function ajaxCloseIteration($taskBoardId)
    {
        $objResponse = new XajaxResponse();

        // InnomaticCore
        $innomaticCore = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');

        if ($innomaticCore->getCurrentUser()->hasPermission('close_iterations')) {
            $taskboard = InnoworkCore::getItem('taskboard', $taskBoardId);
            $taskboard->closeCurrentIteration();
        }

        $xml = '<taskboard><name>taskboard</name><args><taskboardid>'.$taskBoardId.'</taskboardid></args></taskboard>';
        $html = WuiXml::getContentFromXml('', $xml);
        $objResponse->addAssign('taskboard_widget', 'innerHTML', $html);

        return $objResponse;
    }
    /* }}} */
}
