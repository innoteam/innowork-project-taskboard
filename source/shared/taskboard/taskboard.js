var dragSrcEl = null;
var taskboardId = document.getElementById('taskboardid').value;

// ----------------------------------------------------------------------------
// Backlog reordering and send to taskboard
// ----------------------------------------------------------------------------

function handleBacklogDragStart(e) {
    this.style.opacity = '0.4';
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
        e.preventDefault();
    }

    e.dataTransfer.dropEffect = 'move';

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
    xajax_WuiTaskboardAddToTaskboard(taskboardId, dragSrcEl.id);
//        var data = ev.dataTransfer.getData('Text');
//        this.appendChild(document.getElementById(data));
}

function handleBacklogDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }

    e.dataTransfer.dropEffect = 'move';

    return false;
}

function handleBacklogDragEnter(e) {
    // this / e.target is the current hover target.
    if (this.parentNode.id == dragSrcEl.parentNode.id) {
        this.classList.add('over');
    }
}

function handleBacklogDragLeave(e) {
    this.classList.remove('over');
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
        e.preventDefault();
    }

    e.dataTransfer.dropEffect = 'move';

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
    xajax_WuiTaskboardBackToBacklog(taskboardId, dragSrcEl.id);
//        var data = ev.dataTransfer.getData('Text');
//        this.appendChild(document.getElementById(data));
}

function handleUserStoryDragOver(e) {
    if (e.preventDefault) {
        e.preventDefault();
    }

    e.dataTransfer.dropEffect = 'move';

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

    if (this.parentNode.parentNode.id == 'taskboard-userstory-row-0') {
        taskboard = document.getElementById('backlogtable');
        taskboard.addEventListener('drop', handleToBacklogDrop, false);
        taskboard.addEventListener('dragover', handleToBacklogDragOver, false);
        taskboard.addEventListener('dragenter', handleToBacklogDragEnter, false);
        taskboard.addEventListener('dragleave', handleToBacklogDragLeave, false);
        taskboard.classList.add('backlogtarget');
    }

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
        xajax_WuiTaskboardUpdateTaskStatus(taskboardId, dragSrcEl.id, statusCell[2]);
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

    if (dragSrcEl.parentNode.parentNode.id == 'taskboard-userstory-row-0') {
        taskboard = document.getElementById('backlogtable');
        taskboard.classList.remove('backlogtarget');
        taskboard.classList.remove('backlogover');
        taskboard.removeEventListener('drop', handleToBacklogDrop, false);
        taskboard.removeEventListener('dragover', handleToBacklogDragOver, false);
        taskboard.removeEventListener('dragenter', handleToBacklogDragEnter, false);
        taskboard.removeEventListener('dragleave', handleToBacklogDragLeave, false);
    }

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

