<?php

require_once('innowork/core/InnoworkItem.php');

class InnoworkTaskBoard extends InnoworkItem
{

    public $mTable         = 'innowork_taskboards';
    public $mNewDispatcher = 'view';
    public $mNewEvent      = 'newtaskboard';
    public $mNoTrash       = false;
    public $mConvertible   = false;
    const ITEM_TYPE        = 'taskboard';

    public function __construct($rrootDb, $rdomainDA, $storyId = 0)
    {
        parent::__construct($rrootDb, $rdomainDA, self::ITEM_TYPE, $storyId);

        $this->mKeys['title'] = 'text';
        $this->mKeys['done'] = 'boolean';

        $this->mSearchResultKeys[] = 'title';
        $this->mSearchResultKeys[] = 'done';

        $this->mViewableSearchResultKeys[] = 'title';

        $this->mSearchOrderBy = 'title';
        $this->mShowDispatcher = 'view';
        $this->mShowEvent = 'showtaskboard';
    }

    /* public doCreate($params, $userId) {{{ */
    /**
     * Creates a new taskboard item.
     *
     * @param array $params Field values
     * @param integer $userId User id
     * @access public
     * @return boolean
     */
    public function doCreate($params, $userId)
    {
        $result = false;

        if ($params['done'] == 'true') {
            $params['done'] = $this->mrDomainDA->fmttrue;
        } else {
            $params['done'] = $this->mrDomainDA->fmtfalse;
        }

        if (count($params)) {
            $item_id = $this->mrDomainDA->getNextSequenceValue($this->mTable . '_id_seq');

            $params['trashed'] = $this->mrDomainDA->fmtfalse;

            $key_pre = $value_pre = $keys = $values = '';

            while (list($key, $val) = each($params)) {
                $key_pre = ',';
                $value_pre = ',';

                switch ($key) {
                    case 'title':
                    case 'done':
                    case 'trashed':
                        $keys .= $key_pre . $key;
                        $values .= $value_pre . $this->mrDomainDA->formatText($val);
                        break;

                    default:
                        break;
                }
            }

            if (strlen($values)) {
                if ($this->mrDomainDA->execute(
                    'INSERT INTO ' . $this->mTable . ' ' .
                    '(id,ownerid' . $keys . ') ' .
                    'VALUES (' . $item_id . ',' .
                    $userId .
                    $values . ')')
                ) {
                    $result = $item_id;
                }
            }
        }

        return $result;
    }
    /* }}} */

    public function doEdit($params)
    {
        $result = false;

        if ($this->mItemId) {
            if (count($params)) {
                $start = 1;
                $update_str = '';

                if (isset($params['done'])) {
                    if ($params['done'] == 'true') {
                        $params['done'] = $this->mrDomainDA->fmttrue;
                    } else {
                        $params['done'] = $this->mrDomainDA->fmtfalse;
                    }
                }

                while (list($field, $value) = each($params)) {
                    if ($field != 'id') {
                        switch ($field) {
                            case 'title':
                            case 'done':
                            case 'trashed':
                                if (!$start) {
                                    $update_str .= ',';
                                }
                                $update_str .= $field . '=' . $this->mrDomainDA->formatText($value);
                                $start = 0;
                                break;

                            default:
                                break;
                        }
                    }
                }

                $query = $this->mrDomainDA->execute(
                    'UPDATE ' . $this->mTable . ' ' .
                    'SET ' . $update_str . ' ' .
                    'WHERE id=' . $this->mItemId
                    );

                if ($query) {
                    $result = true;
                }
            }
        }

        return $result;
    }

    public function doTrash()
    {
        return true;
    }

    public function doRemove($userId)
    {
        $result = false;

        $result = $this->mrDomainDA->execute(
            'DELETE FROM ' . $this->mTable . ' ' .
            'WHERE id=' . $this->mItemId
        );

        return $result;
    }

    /* public getProjectsList() {{{ */
    /**
     * Gets a list of the taskboard projects.
     *
     * @access public
     * @return array List of taskboard projects in array values.
     */
    public function getProjectsList()
    {
        $projectsQuery = $this->mrDomainDA->execute(
            "SELECT projectid"
            . " FROM innowork_taskboards_projects"
            . " WHERE taskboardid={$this->mItemId}"
            );

        $projectsList = array();

        while (!$projectsQuery->eof) {
            $projectsList[] = $projectsQuery->getFields('projectid');
            $projectsQuery->moveNext();
        }
        $projectsQuery->free();

        return $projectsList;
    }
    /* }}} */

    /* public addProject($projectId) {{{ */
    /**
     * Adds a project to the taskboard.
     *
     * @param integer $projectId Project id
     * @access public
     * @return boolean
     */
    public function addProject($projectId)
    {
        $projectCheckQuery = $this->mrDomainDA->execute(
            "SELECT projectid"
            . " FROM innowork_taskboards_projects"
            . " WHERE projectid=$projectId"
            );

        if ($projectCheckQuery->getNumberRows() == 0) {
            return $this->mrDomainDA->execute(
                "INSERT INTO innowork_taskboards_projects"
                . " (taskboardid, projectid)"
                . " VALUES({$this->mItemId}, $projectId)"
                );
        } else {
            return true;
        }
    }
    /* }}} */

    /* public removeProject($projectId) {{{ */
    /**
     * Removes a project from the taskboard.
     *
     * @param integer $projectId Project id
     * @access public
     * @return boolean
     */
    public function removeProject($projectId)
    {
        return $this->mrDomainDA->execute(
            "DELETE FROM innowork_taskboards_projects"
            . " WHERE projectid=$projectId"
            . " AND taskboardid={$this->mItemId}"
            );
    }
    /* }}} */

    /* public getCurrentIterationId() {{{ */
    /**
     * Returns the identifier of the current iteration.
     *
     * If no iteration has been previously started, a new iteration is started,
     * so an iteration number is always returned.
     *
     * @access public
     * @return integer Iteration id
     */
    public function getCurrentIterationId()
    {
        $iterationQuery = $this->mrDomainDA->execute(
            'SELECT id
            FROM innowork_iterations
            WHERE taskboardid=' . $this->mItemId . '
            AND done=' . $this->mrDomainDA->formatText($this->mrDomainDA->fmtfalse));

        if ($iterationQuery->getNumberRows() == 0) {
            $iterationId = $this->mrDomainDA->getNextSequenceValue('innowork_iterations_id_seq');
            $this->mrDomainDA->execute(
                    'INSERT INTO innowork_iterations
                (id, done, startdate, enddate, taskboardid)
                VALUES (' .
                    $iterationId . ',' .
                    $this->mrDomainDA->formatText($this->mrDomainDA->fmtfalse) . ',' .
                    $this->mrDomainDA->formatText('') . ',' .
                    $this->mrDomainDA->formatText('') . ',' .
                    $this->mItemId .
                    ')');

            return $iterationId;
        } else {
            return $iterationQuery->getFields('id');
        }
    }
    /* }}} */

    public function addTaskToCurrentIteration($taskType, $taskId)
    {
        $innomaticCore = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');
        $iterationId = $this->getCurrentIterationId();

        $innoworkCore = InnoworkCore::instance(
            'innoworkcore',
            $innomaticCore->getDataAccess(),
            $innomaticCore->getCurrentDomain()->getDataAccess()
        );

        $summaries = $innoworkCore->getSummaries();

        $taskClassName = $summaries[$taskType]['classname'];
        if (!strlen($taskClassName)) {
            return false;
        }

        $tempObject = new $taskClassName(
                $innomaticCore->getDataAccess(), $innomaticCore->getCurrentDomain()->getDataAccess(), $taskId
        );

        $tempObject->edit(array('iterationid' => $iterationId));
    }

    public function removeTaskFromCurrentIteration($taskType, $taskId)
    {
        $innoworkCore = InnoworkCore::instance('innoworkcore', \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(), \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()
        );
        $summaries = $innoworkCore->getSummaries();

        $taskClassName = $summaries[$taskType]['classname'];
        if (!strlen($taskClassName)) {
            return false;
        }

        $tempObject = new $taskClassName(
                \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(), \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess(), $taskId
        );

        $tempObject->edit(array('iterationid' => 0));
    }

    /* public setTaskStatus($taskType, $taskId, $taskStatus) {{{ */

    /**
     * Updates the status of the given task.
     *
     * @param string $taskType Task type
     * @param integer $taskId Task id
     * @param integer $taskStatus New status id
     * @static
     * @access public
     * @return boolean
     */
    public static function setTaskStatus($taskType, $taskId, $taskStatus)
    {
        $item = InnoworkCore::getItem($taskType, $taskId);
        if (is_object($item)) {
            return $item->edit(array('statusid' => $taskStatus));
        } else {
            return false;
        }
    }

    /* }}} */

    public function getBoardStructure()
    {
        $board = array();

        // InnomaticCore
        $innomaticCore = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');

        // Taskboard projects list
        $projectIdList = self::getProjectsList();

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

        // Get current iteration id
        $currentIterationId = self::getCurrentIterationId();

        $userStoriesList = array();
        $taskList = array();
        $board['userstoriestasklist'] = array();

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

        // Build user stories task list
        foreach ($taskList as $id => $values) {
            if (strlen($values['userstoryid']) && $values['userstoryid'] != 0) {
                $board['userstoriestasklist'][$values['userstoryid']][$id] = $values;
                // Remove this task from the tasks list
                unset($taskList[$id]);
            }
        }

        // Backlog and iteration tasks
        $backlogTasks = array();
        $board['iterationtasks'] = array();

        foreach ($taskList as $id => $values) {
            if (!(strlen($values['iterationid']) > 0 && $values['iterationid'] != 0)) {
                $backlogTasks['task-'.$id] = $values;
            } elseif ($values['iterationid'] == $currentIterationId) {
                $board['iterationtasks'][$id] = $values;
            }
        }

        // Backlog and iteration user stories
        $backlogUserStories = array();
        $board['iterationuserstories'] = array();

        // This keeps track of whole product backlog story points
        $board['backlogstorypoints'] = 0;

        // This keeps track of whole iteration story points
        $board['iterationstorypoints'] = 0;

        foreach ($userStoriesList as $id => $values) {
            if (!(strlen($values['iterationid']) > 0 && $values['iterationid'] != 0)) {
                $backlogUserStories['userstory-'.$id] = $values;
                // Add user story points to the backlog story points total
                $board['backlogstorypoints'] += $values['storypoints'];
            } elseif ($values['iterationid'] == $currentIterationId) {
                $board['iterationuserstories'][$id] = $values;
                // Add user story points to the iteration story points total
                $board['iterationstorypoints'] += $values['storypoints'];
            }
        }

        if (!($board['iterationstorypoints'] > 0)) {
            $board['iterationstorypoints'] = 0;
        }

        if (!($board['backlogstorypoints'] > 0)) {
            $board['backlogstorypoints'] = 0;
        }

        // Merge backlog items
        $board['backlogitems'] = array_merge($backlogUserStories, $backlogTasks);

        // Task statuses
        $board['taskstatuslist'] = InnoworkTaskField::getFields(InnoworkTaskField::TYPE_STATUS);

        return $board;
    }

    public function doGetSummary()
    {
        $result = false;

        $userstories = new InnoworkTaskBoard(
                \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(), \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()
        );
        $userstories_search = $userstories->Search(
                array(
            'done' => \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()->fmtfalse
                ), \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserId(), false, false, 10, 0
        );

        $result = '<vertgroup>
  <children>';

        foreach ($userstories_search as $story) {
            $result .=
                    '<link>
  <args>
    <label type="encoded">' . urlencode('- ' . $story['id']) . '</label>
    <link type="encoded">' . urlencode(
                            WuiEventsCall::buildEventsCallString('innoworktaskboard', array(
                                array(
                                    'view',
                                    'showtaskboard',
                                    array('id' => $story['id'])
                                )
                            ))
                    ) . '</link>
    <compact>true</compact>
  </args>
</link>';
        }

        $result .=
                '  </children>
</vertgroup>';

        return $result;
    }

}
