<?php
namespace Shared\Wui;

class WuiTaskboard extends \Innomatic\Wui\Widgets\WuiWidget
{
    public $mUseSession = 'true';

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

    protected function generateSource()
    {
        $projectsQuery = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()->execute(
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

        $taskboardId = $this->mArgs['taskboardid'];

        $localeCatalog = new LocaleCatalog(
            'innowork-projects-taskboard::widget',
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getLanguage()
        );

        $innoworkCore = InnoworkCore::instance('innoworkcore',
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()
        );

        $userStoriesSummaries = $innoworkCore->getSummaries('', false, array('userstory'));
        $technicalTasksSummaries = $innoworkCore->getSummaries('', false, array('technicaltask'));

        $userStoriesList = array();
        $taskList = array();
        $userStoriesTasksList = array();

        foreach ($userStoriesSummaries as $type => $values) {
            $userStoryClassName = $values['classname'];
            $tempObject = new $userStoryClassName(
                \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
                \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()
            );
            foreach ($projectIdList as $projectId) {
                $searchResults = $tempObject->search(
                    array('projectid' => $projectId, 'done' => \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()->fmtfalse),
                    \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserId()
                );
                $userStoriesList = array_merge($userStoriesList, $searchResults);
            }
        }

        foreach ($technicalTasksSummaries as $type => $values) {
            $taskClassName = $values['classname'];
            $tempObject = new $taskClassName(
                \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
                \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()
            );
            foreach ($projectIdList as $projectId) {
                $searchResults = $tempObject->search(
                    array('projectid' => $projectId, 'done' => \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()->fmtfalse),
                    \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserId()
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

        $backlogUserStories = array();
        $iterationUserStories = array();

        foreach ($userStoriesList as $id => $values) {
            if (!(strlen($values['iterationid']) > 0 && $values['iterationid'] != 0)) {
                $backlogUserStories[$id] = $values;
            } else {
                $iterationUserStories[$id] = $values;
            }
        }

        // Task statuses
        $taskStatusList = InnoworkTaskField::getFields(InnoworkTaskField::TYPE_STATUS);

        // @todo sort by order

        /*
        print_r($userStoriesList);
        print_r($taskList);
        print_r($userStoriesTasksList);
         */

        $this->mLayout = ($this->mComments ? '<!-- begin ' . $this->mName . ' taskboard -->' : '');

        $this->mLayout .= '<table style="width: 100%; padding: 5px;">
    <tr>
        <td style="width: 200px; vertical-align: top;">
<table>
<tr>
<td>'.$localeCatalog->getStr('productbacklog.label').'</td>
</tr>
</table>
            <div id="backlog">
<table id="backlogtable">
<tr><td>
<div id="backlog" style="width: 200px;">';

foreach ($backlogUserStories as $id => $item) {
    $this->mLayout .= '<div class="card" draggable="true" id="userstory-'.$item['id'].'">'.$item['title'].'</div>';
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
<td>'.$localeCatalog->getStr('currentiteration.label').'</td>
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

    if (\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->hasPermission('add_taskboards')) {
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

$storyCounter = 0;
foreach ($iterationUserStories as $userStory) {
    $this->mLayout .= '<tr id="taskboard-userstory-row-'.$userStory['id'].'">'."\n";
    $this->mLayout .= '<td id="div-row'.$userStory['id'].'-0" class="cell" style="background-color: white; width: 0%;"><div id="card-userstory-'.$userStory['id'].'" class="card story"><header>'.$userStory['title']."</header></div></td>\n";

    // Draw task cards

    foreach ($taskStatusList as $statusId => $statusLabel) {
        $this->mLayout .= '<td id="div-row'.$userStory['id'].'-'.$statusId.'" class="cell task"'."style=\"background-color: white; width: {$cellWidth}%;\">";
        foreach ($userStoriesTasksList[$userStory['id']] as $taskId => $taskValues) {
            if ($taskValues['statusid'] == $statusId) {
                $this->mLayout .= '<div id="card-task-'.$taskValues['id'].'" class="card task" draggable="true"><header>'.$taskValues['title'].'</header></div>';
            }
        }
        $this->mLayout .= "</td>\n";
    }

    $this->mLayout .= "</tr>\n";
    $storyCounter++;
}

$this->mLayout .= '</table></td></tr>' . "\n" . '</table>' . "\n";

$this->mLayout .= '
    </div>
</td><td style="width: 200px; vertical-align: top;">
    <p>'.$localeCatalog->getStr('increments.label').'</p>
        </td>
    </tr>
    </table>
';

        $this->mLayout .= WuiXml::getContentFromXml('', '<formarg><args><id>taskboardid</id><value>'.$taskboardId.'</value></args></formarg>');

        $this->mLayout .= '<script>'.file_get_contents(\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getHome().'/shared/taskboard/taskboard.js').'</script>';

        $this->mLayout .= $this->mComments ? '<!-- end ' . $this->mName . " taskboard -->\n" : '';

        return true;
    }

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

    public static function ajaxRefreshBoard($taskBoardId)
    {
        $objResponse = new XajaxResponse();

        $xml = '<taskboard><name>taskboard</name><args><taskboardid>'.$taskBoardId.'</taskboardid></args></taskboard>';
        $html = WuiXml::getContentFromXml('', $xml);
        $objResponse->addAssign('taskboard_widget', 'innerHTML', $html);

        return $objResponse;
    }
}
