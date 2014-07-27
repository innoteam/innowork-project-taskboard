<?php
namespace Shared\Wui;

class WuiTaskboard extends \Innomatic\Wui\Widgets\WuiWidget
{
    public function __construct (
        $elemName,
        $elemArgs = '',
        $elemTheme = '',
        $dispEvents = ''
    ) {
        parent::__construct($elemName, $elemArgs, $elemTheme, $dispEvents);
    }

    protected function generateSource()
    {
        $projectIdList = array('249');
        $taskboardId = $this->mArgs['taskboardid'];

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

        /*
        print_r($userStoriesList);
        print_r($taskList);
        print_r($userStoriesTasksList);
         */

        $this->mLayout = ($this->mComments ? '<!-- begin ' . $this->mName . ' dropzone -->' : '');

        $this->mLayout .= '<style type="text/css">
/* Prevent the text contents of draggable elements from being selectable. */
[draggable] {
  -moz-user-select: none;
  -khtml-user-select: none;
  -webkit-user-select: none;
  user-select: none;
  /* Required to make elements draggable in old WebKit */
  -khtml-user-drag: element;
  -webkit-user-drag: element;
}
#backlog .card {
  width: 200px;
  background-color: #ddd;
  margin: 3px;
  -webkit-border-radius: 4px;
  -ms-border-radius: 4px;
  -moz-border-radius: 4px;
  border-radius: 4px;
  text-align: center;
  cursor: move;
}
#backlog .card header {
  color: #fff;
  padding: 5px;
  background-color: #cecece;
  border-bottom: 1px solid #ddd;
  -webkit-border-top-left-radius: 4px;
  -moz-border-radius-topleft: 4px;
  -ms-border-radius-topleft: 4px;
  border-top-left-radius: 4px;
  -webkit-border-top-right-radius: 4px;
  -ms-border-top-right-radius: 4px;
  -moz-border-radius-topright: 4px;
  border-top-right-radius: 4px;
}

#backlog .card.over {
  border: 1px dashed #000;
}

#taskboard .card {
  margin: 3px;
  -webkit-border-radius: 4px;
  -ms-border-radius: 4px;
  -moz-border-radius: 4px;
  border-radius: 4px;
  text-align: center;
  margin-left: auto;
  margin-right: auto;
}

#taskboard .card.task {
  cursor: move;
  height: 120px;
  width: 150px;
  background-color: #ddd;
  color: #fff;
}

#taskboard .card.story {
  height: 150px;
  width: 170px;
    background-color: yellow;
    color: black;
}

#taskboard .card header {
  padding: 5px;
  /*
  background-color: #cecece;
  border-bottom: 1px solid #ddd;
  */
  -webkit-border-top-left-radius: 4px;
  -moz-border-radius-topleft: 4px;
  -ms-border-radius-topleft: 4px;
  border-top-left-radius: 4px;
  -webkit-border-top-right-radius: 4px;
  -ms-border-top-right-radius: 4px;
  -moz-border-radius-topright: 4px;
  border-top-right-radius: 4px;
}

#taskboard .cell.over {
    border: 1px dashed #000;
    background-color: #f1f1f1;
}

#taskboard .cell {
    vertical-align: top;
    text-align: center;
    width: 25%;margin:10px;padding:10px;border:1px solid #aaaaaa;
}

#taskboardtable {
    /*border: 2px solid #fff;*/
    background-color: white;
}

.taskboardtarget {
    border: 1px dashed #000;
    background-color: black;
}

.taskboardover {
  border: 1px dashed green;
}

</style>
';

        $this->mLayout .= '<table style="width: 100%; padding: 5px;">
    <tr>
        <td style="width: 200px; vertical-align: top;">
            <p>Product Backlog</p>
            <div id="backlog">
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
</div>
</td><td id="taskboard" class="taskboard">
    <p>Current Iteration</p>
    <div id="taskboardDiv">
    <table id="taskboardtable" style="width: 100%; vertical-align: top;" border="1">
        <tr><td>Story</td><td>To Do</td><td>In Progress</td><td>Done</td></tr>';

$storyCounter = 0;
foreach ($iterationUserStories as $userStory) {
    $this->mLayout .= '<tr id="taskboard-userstory-row-'.$userStory['id'].'">'."\n";
    $this->mLayout .= '<td id="div-row'.$userStory['id'].'-0" class="cell"><div id="card'.$storyCounter.'" class="card story"><header>'.$userStory['title'].'</header></div></td>';
    $this->mLayout .= "</tr>\n";
    $storyCounter++;
}
$this->mLayout .= '
        <tr id="taskboard-row1">
            <td id="div-row1-1" class="cell"><div id="card1" class="card story"><header>1</header></div></td>
            <td id="div-row1-2" class="cell task"><div id="card2" class="card task" draggable="true"><header>2</header></div><div id="card3" class="card task" draggable="true"><header>3</header></div></td>
            <td id="div-row1-3" class="cell task"><div id="card4" class="card task" draggable="true"><header>4</header></div></td>
            <td id="div-row1-4" class="cell task"><div id="card5" class="card task" draggable="true"><header>5</header></div></td>
        </tr>
        <tr id="taskboard-row2">
            <td id="div-row2-1" class="cell"><div id="card6" class="card story"><header>6</header></div></td>
            <td id="div-row2-2" class="cell task"><div id="card7" class="card task" draggable="true"><header>7</header></div><div id="card8" class="card task" draggable="true"><header>8</header></div></td>
            <td id="div-row2-3" class="cell task"><div id="card9" class="card task" draggable="true"><header>9</header></div><div id="card10" class="card task" draggable="true"><header>10</header></div></td>
            <td id="div-row2-4" class="cell task"></td>
        </tr>
    </table>
    </div>
</td><td style="width: 200px; vertical-align: top;">
    <p>Increments</p>
        </td>
    </tr>
    </table>
';

        $this->mLayout .= "<script>
(function() {
var dragSrcEl = null;

function handleBacklogDragStart(e) {
  this.style.opacity = '0.4';  // this / e.target is the source node.
  dragSrcEl = this;

  e.dataTransfer.effectAllowed = 'move';
  e.dataTransfer.setData('text/html', this.innerHTML);

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

function handleTaskboardDragOver(ev) {
    ev.preventDefault();
}

function handleTaskboardDragLeave(e) {
    this.classList.remove('over');  // this / e.target is previous target element.
}

function handleTaskboardDragStart(e) {
  dragSrcEl = this;
    e.dataTransfer.setData('Text', e.target.id);
}

function handleTaskboardDrop(ev) {
    ev.preventDefault();
    if (dragSrcEl != this && this.parentNode.id == dragSrcEl.parentNode.parentNode.id) {
        var data = ev.dataTransfer.getData('Text');
        this.appendChild(document.getElementById(data));
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
  });
}

var backlogCards = document.querySelectorAll('#backlog .card');
[].forEach.call(backlogCards, function(col) {
  col.setAttribute('draggable', 'true');  // Enable backlog cards to be draggable.
  col.addEventListener('dragstart', handleBacklogDragStart, false);
  col.addEventListener('dragenter', handleBacklogDragEnter, false);
  col.addEventListener('dragover', handleBacklogDragOver, false);
  col.addEventListener('dragleave', handleBacklogDragLeave, false);
  col.addEventListener('drop', handleBacklogDrop, false);
  col.addEventListener('dragend', handleBacklogDragEnd, false);
});

var taskboardCells = document.querySelectorAll('#taskboard .cell.task');
[].forEach.call(taskboardCells, function(col) {
  col.addEventListener('drop', handleTaskboardDrop, false);
  col.addEventListener('dragover', handleTaskboardDragOver, false);
  col.addEventListener('dragenter', handleTaskboardDragEnter, false);
  col.addEventListener('dragleave', handleTaskboardDragLeave, false);
  col.addEventListener('dragend', handleTaskboardDragEnd, false);
});

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

        $xml = '<taskboard><args><taskboardid>'.$taskBoardId.'</taskboardid></args></taskboard>';
        $html = WuiXml::getContentFromXml('', $xml);
        $objResponse->addAssign('taskboard_widget', 'innerHTML', $html);

        return $objResponse;
    }
}
