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

        $this->mLayout = ($this->mComments ? '<!-- begin ' . $this->mName . ' dropzone -->' : '');

        $this->mLayout .= '<link rel="stylesheet" type="text/css" href="'.
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getExternalBaseUrl().'/shared/taskboard/taskboard.css">';

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

$this->mLayout .= WuiXml::getContentFromXml('', '            <button>
              <args>
                <themeimage>cycle</themeimage>
                <label>'.$localeCatalog->getstr('refreshboard.button').'</label>
                <frame>false</frame>
                <horiz>true</horiz>
                <action>javascript:void(0)</action>
                </args>
                  <events>
                  <click>
                  xajax_WuiTaskboardRefreshBoard('.$taskboardId.')</click>
                  </events>
            </button>
');
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

        $this->mLayout .= "<script>
(function() {
var dragSrcEl = null;

// ----------------------------------------------------------------------------
// Backlog reordering and send to taskboard
// ----------------------------------------------------------------------------

function handleBacklogDragStart(e) {
  this.style.opacity = '0.4';  // this / e.target is the source node.
  dragSrcEl = this;

  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/html', this.innerHTML);

    [].forEach.call(backlogCards, function(col) {
      col.addEventListener('dragenter', handleBacklogDragEnter, false);
      col.addEventListener('dragover', handleBacklogDragOver, false);
      col.addEventListener('dragleave', handleBacklogDragLeave, false);
      col.addEventListener('drop', handleBacklogDrop, false);
      col.addEventListener('dragend', handleBacklogDragEnd, false);
    });

  taskboard = document.getElementById('taskboardtable');
  taskboard.addEventListener('drop', handleToTaskboardDrop, false);
  taskboard.addEventListener('dragover', handleToTaskboardDragOver, false);
  taskboard.addEventListener('dragenter', handleToTaskboardDragEnter, false);
  taskboard.addEventListener('dragleave', handleToTaskboardDragLeave, false);
  //taskboard.style.background = '#f1f1f1';
  taskboard.classList.add('taskboardtarget');
}

function handleToTaskboardDragOver(e) {
    this.classList.remove('taskboardtarget');
    this.classList.add('taskboardover');
  if (e.preventDefault) {
    e.preventDefault(); // Necessary. Allows us to drop.
  }

  e.dataTransfer.dropEffect = 'move';  // See the section on the DataTransfer object.

  return false;
}

function handleToTaskboardDragEnter(e) {
}

function handleToTaskboardDragLeave(e) {
    this.classList.remove('taskboardover');
    this.classList.add('taskboardtarget');
}

function handleToTaskboardDrop(ev) {
    ev.preventDefault();
    xajax_WuiTaskboardAddToTaskboard(".$taskboardId.", dragSrcEl.id);
//        var data = ev.dataTransfer.getData('Text');
//        this.appendChild(document.getElementById(data));
}

function handleBacklogDragOver(e) {
  if (e.preventDefault) {
    e.preventDefault(); // Necessary. Allows us to drop.
  }

  e.dataTransfer.dropEffect = 'move';  // See the section on the DataTransfer object.

  return false;
}

function handleBacklogDragEnter(e) {
  // this / e.target is the current hover target.
    if (this.parentNode.id == dragSrcEl.parentNode.id) {
        this.classList.add('over');
    }
}

function handleBacklogDragLeave(e) {
    this.classList.remove('over');  // this / e.target is previous target element.
}

function handleBacklogDrop(e) {
  // this / e.target is current target element.

  if (e.stopPropagation) {
    e.stopPropagation(); // stops the browser from redirecting.
  }
  // Don't do anything if dropping the same card we're dragging.
  if (dragSrcEl != this && this.parentNode.id == dragSrcEl.parentNode.id) {
    // Set the source card's HTML to the HTML of the card we dropped on.
    dragSrcEl.innerHTML = this.innerHTML;
    this.innerHTML = e.dataTransfer.getData('text/html');
  }

  return false;
}

function handleBacklogDragEnd(e) {
  // this/e.target is the source node.
  this.style.opacity = '1';

    [].forEach.call(backlogCards, function(col) {
      col.removeEventListener('dragenter', handleBacklogDragEnter, false);
      col.removeEventListener('dragover', handleBacklogDragOver, false);
      col.removeEventListener('dragleave', handleBacklogDragLeave, false);
      col.removeEventListener('drop', handleBacklogDrop, false);
      col.removeEventListener('dragend', handleBacklogDragEnd, false);
    });

  taskboard = document.getElementById('taskboardtable');
  taskboard.classList.remove('taskboardtarget');
    taskboard.classList.remove('taskboardover');
  taskboard.removeEventListener('drop', handleToTaskboardDrop, false);
  taskboard.removeEventListener('dragover', handleToTaskboardDragOver, false);
  taskboard.removeEventListener('dragenter', handleToTaskboardDragEnter, false);
  taskboard.removeEventListener('dragleave', handleToTaskboardDragLeave, false);

  [].forEach.call(backlogCards, function (col) {
    col.classList.remove('over');
  });
}

// ----------------------------------------------------------------------------
// User Stories reordering and back to backlog
// ----------------------------------------------------------------------------

function handleUserStoryDragStart(e) {
  this.style.opacity = '0.4';  // this / e.target is the source node.
  dragSrcEl = this;

  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/html', this.innerHTML);

  taskboard = document.getElementById('backlogtable');
  taskboard.addEventListener('drop', handleToBacklogDrop, false);
  taskboard.addEventListener('dragover', handleToBacklogDragOver, false);
  taskboard.addEventListener('dragenter', handleToBacklogDragEnter, false);
  taskboard.addEventListener('dragleave', handleToBacklogDragLeave, false);
  //taskboard.style.background = '#f1f1f1';
  taskboard.classList.add('backlogtarget');

    [].forEach.call(userstoryCards, function(col) {
      col.addEventListener('dragenter', handleUserStoryDragEnter, false);
      col.addEventListener('dragover', handleUserStoryDragOver, false);
      col.addEventListener('dragleave', handleUserStoryDragLeave, false);
      col.addEventListener('drop', handleUserStoryDrop, false);
      col.addEventListener('dragend', handleUserStoryDragEnd, false);
    });
}

function handleToBacklogDragOver(e) {
    this.classList.remove('backlogtarget');
    this.classList.add('backlogover');
  if (e.preventDefault) {
    e.preventDefault(); // Necessary. Allows us to drop.
  }

  e.dataTransfer.dropEffect = 'move';  // See the section on the DataTransfer object.

  return false;
}

function handleToBacklogDragEnter(e) {
}

function handleToBacklogDragLeave(e) {
    this.classList.remove('backlogover');
    this.classList.add('backlogtarget');
}

function handleToBacklogDrop(ev) {
    ev.preventDefault();
    xajax_WuiTaskboardBackToBacklog(".$taskboardId.", dragSrcEl.id);
//        var data = ev.dataTransfer.getData('Text');
//        this.appendChild(document.getElementById(data));
}

function handleUserStoryDragOver(e) {
  if (e.preventDefault) {
    e.preventDefault(); // Necessary. Allows us to drop.
  }

  e.dataTransfer.dropEffect = 'move';  // See the section on the DataTransfer object.

  return false;
}

function handleUserStoryDragEnter(e) {
  // this / e.target is the current hover target.
    if (this.parentNode.class == dragSrcEl.parentNode.class) {
        this.classList.add('over');
   }
}

function handleUserStoryDragLeave(e) {
    this.classList.remove('over');  // this / e.target is previous target element.
}

function handleUserStoryDrop(e) {
  // this / e.target is current target element.

  if (e.stopPropagation) {
    e.stopPropagation(); // stops the browser from redirecting.
  }
  // Don't do anything if dropping the same card we're dragging.
    //&& this.parentNode.id == dragSrcEl.parentNode.id
  if (dragSrcEl != this ) {
alert('ok');
    // Set the source card's HTML to the HTML of the card we dropped on.
    dragSrcEl.innerHTML = this.innerHTML;
    this.innerHTML = e.dataTransfer.getData('text/html');
  }

  return false;
}

function handleUserStoryDragEnd(e) {
  // this/e.target is the source node.
  this.style.opacity = '1';

  taskboard = document.getElementById('backlogtable');
  taskboard.classList.remove('backlogtarget');
    taskboard.classList.remove('backlogover');
  taskboard.removeEventListener('drop', handleToBacklogDrop, false);
  taskboard.removeEventListener('dragover', handleToBacklogDragOver, false);
  taskboard.removeEventListener('dragenter', handleToBacklogDragEnter, false);
  taskboard.removeEventListener('dragleave', handleToBacklogDragLeave, false);

  [].forEach.call(userstoryCards, function (col) {
    col.classList.remove('over');
      col.removeEventListener('dragenter', handleUserStoryDragEnter, false);
      col.removeEventListener('dragover', handleUserStoryDragOver, false);
      col.removeEventListener('dragleave', handleUserStoryDragLeave, false);
      col.removeEventListener('drop', handleUserStoryDrop, false);
      col.removeEventListener('dragend', handleUserStoryDragEnd, false);
  });
}

// ----------------------------------------------------------------------------
// Task Board tasks
// ----------------------------------------------------------------------------

function handleTaskboardDragOver(ev) {
    ev.preventDefault();
}

function handleTaskboardDragLeave(e) {
    this.classList.remove('over');  // this / e.target is previous target element.
}

function handleTaskboardDragStart(e) {
  dragSrcEl = this;
    e.dataTransfer.setData('Text', e.target.id);

    [].forEach.call(taskboardCells, function(col) {
      col.addEventListener('drop', handleTaskboardDrop, false);
      col.addEventListener('dragover', handleTaskboardDragOver, false);
      col.addEventListener('dragenter', handleTaskboardDragEnter, false);
      col.addEventListener('dragleave', handleTaskboardDragLeave, false);
      col.addEventListener('dragend', handleTaskboardDragEnd, false);
    });
}

function handleTaskboardDrop(ev) {
    ev.preventDefault();
    if (dragSrcEl != this && this.parentNode.id == dragSrcEl.parentNode.parentNode.id) {
        var data = ev.dataTransfer.getData('Text');
        this.appendChild(document.getElementById(data));
        statusCell = this.id.split('-');
        xajax_WuiTaskboardUpdateTaskStatus(".$taskboardId.", dragSrcEl.id, statusCell[2]);
    }
}

function handleTaskboardDragEnter(e) {
  // this / e.target is the current hover target.
    if (this.parentNode.id == dragSrcEl.parentNode.parentNode.id) {
        this.classList.add('over');
    }
}

function handleTaskboardDragEnd(e) {
  // this/e.target is the source node.
  this.style.opacity = '1';
  [].forEach.call(taskboardCells, function (col) {
    col.classList.remove('over');
      col.removeEventListener('drop', handleTaskboardDrop, false);
      col.removeEventListener('dragover', handleTaskboardDragOver, false);
      col.removeEventListener('dragenter', handleTaskboardDragEnter, false);
      col.removeEventListener('dragleave', handleTaskboardDragLeave, false);
      col.removeEventListener('dragend', handleTaskboardDragEnd, false);
  });
}

var backlogCards = document.querySelectorAll('#backlog .card');
[].forEach.call(backlogCards, function(col) {
  col.setAttribute('draggable', 'true');  // Enable backlog cards to be draggable.
  col.addEventListener('dragstart', handleBacklogDragStart, false);
});

var userstoryCards = document.querySelectorAll('#taskboard .card.story');
[].forEach.call(userstoryCards, function(col) {
  col.setAttribute('draggable', 'true');  // Enable backlog cards to be draggable.
  col.addEventListener('dragstart', handleUserStoryDragStart, false);
});

var taskboardCells = document.querySelectorAll('#taskboard .cell.task');

var taskboardCards = document.querySelectorAll('#taskboard .card.task');
[].forEach.call(taskboardCards, function(col) {
  col.setAttribute('draggable', 'true');  // Enable taskboard cards to be draggable.
  col.addEventListener('dragstart', handleTaskboardDragStart, false);
});

})();
</script>
";

        $this->mLayout .= $this->mComments ? '<!-- end ' . $this->mName . " dropzone -->\n" : '';

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
