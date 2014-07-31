<?php

require_once('innowork/core/InnoworkItem.php');

class InnoworkTaskBoard extends InnoworkItem
{
    public $mTable = 'innowork_taskboards';
    public $mNewDispatcher = 'view';
    public $mNewEvent = 'newtaskboard';
    public $mNoTrash = false;
    public $mConvertible = false;
    //public $mTypeTags = array('task');
    const ITEM_TYPE = 'taskboard';

    public function __construct($rrootDb, $rdomainDA, $storyId = 0)
    {
        parent::__construct($rrootDb, $rdomainDA, InnoworkUserStory::ITEM_TYPE, $storyId);

        $this->mKeys['title'] = 'text';
        $this->mKeys['done'] = 'boolean';

        $this->mSearchResultKeys[] = 'title';
        $this->mSearchResultKeys[] = 'done';

        $this->mViewableSearchResultKeys[] = 'title';

        $this->mSearchOrderBy = 'title';
        $this->mShowDispatcher = 'view';
        $this->mShowEvent = 'showtaskboard';
    }

    public function doCreate(
        $params,
        $userId
        )
    {
        $result = false;

        if ($params['done'] == 'true') $params['done'] = $this->mrDomainDA->fmttrue;
        else $params['done'] = $this->mrDomainDA->fmtfalse;

        if (count($params)) {
            $item_id = $this->mrDomainDA->getNextSequenceValue($this->mTable.'_id_seq');

            $params['trashed'] = $this->mrDomainDA->fmtfalse;

            $key_pre = $value_pre = $keys = $values = '';

            while (list($key, $val) = each($params)) {
                $key_pre = ',';
                $value_pre = ',';

                switch ($key) {
                case 'title':
                case 'done':
                case 'trashed':
                    $keys .= $key_pre.$key;
                    $values .= $value_pre.$this->mrDomainDA->formatText($val);
                    break;

                default:
                    break;
                }
            }

            if (strlen($values)) {
                if ($this->mrDomainDA->Execute('INSERT INTO '.$this->mTable.' '.
                                               '(id,ownerid'.$keys.') '.
                                               'VALUES ('.$item_id.','.
                                               $userId.
                                               $values.')'))
                {
                    $result = $item_id;
                }
            }
        }

        return $result;
    }

    public function doEdit(
        $params
        )
    {
        $result = false;

        if ($this->mItemId) {
            if (count($params)) {
                $start = 1;
                $update_str = '';

                if (isset($params['done'])) {
                    if ($params['done'] == 'true') $params['done'] = $this->mrDomainDA->fmttrue;
                    else $params['done'] = $this->mrDomainDA->fmtfalse;
                }

                while (list($field, $value) = each($params)) {
                    if ($field != 'id') {
                        switch ($field) {
                        case 'title':
                        case 'done':
                        case 'trashed':
                            if (!$start) $update_str .= ',';
                            $update_str .= $field.'='.$this->mrDomainDA->formatText($value);
                            $start = 0;
                            break;

                        default:
                            break;
                        }
                    }
                }

                $query = &$this->mrDomainDA->Execute(
                    'UPDATE '.$this->mTable.' '.
                    'SET '.$update_str.' '.
                    'WHERE id='.$this->mItemId);

                if ($query) $result = true;
            }
        }

        return $result;
    }

    public function doTrash()
    {
        return true;
    }

    public function doRemove(
        $userId
        )
    {
        $result = false;

        $result = $this->mrDomainDA->Execute(
            'DELETE FROM '.$this->mTable.' '.
            'WHERE id='.$this->mItemId
           );

        return $result;
    }

    public function getCurrentIterationId()
    {
        $iterationQuery = $this->mrDomainDA->execute(
            'SELECT id
            FROM innowork_iterations
            WHERE taskboardid='.$this->mItemId.'
            AND done='.$this->mrDomainDA->formatText($this->mrDomainDA->fmtfalse));

        if ($iterationQuery->getNumberRows() == 0) {
            $iterationId = $this->mrDomainDA->getNextSequenceValue('innowork_iterations_id_seq');
            $this->mrDomainDA->execute(
                'INSERT INTO innowork_iterations
                (id, done, startdate, enddate, taskboardid)
                VALUES ('.
                $iterationId.','.
                $this->mrDomainDA->formatText($this->mrDomainDA->fmtfalse).','.
                $this->mrDomainDA->formatText('').','.
                $this->mrDomainDA->formatText('').','.
                $this->mItemId.
                ')');

            return $iterationId;
        } else {
            return $iterationQuery->getFields('id');
        }
    }

    public function addTaskToCurrentIteration($taskType, $taskId)
    {
        $iterationId = $this->getCurrentIterationId();

        $innoworkCore = InnoworkCore::instance('innoworkcore',
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()
        );
        $summaries = $innoworkCore->getSummaries();

        $taskClassName = $summaries[$taskType]['classname'];
        if (!strlen($taskClassName)) {
            return false;
        }

        $tempObject = new $taskClassName(
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess(),
            $taskId
        );

        $tempObject->edit(array('iterationid' => $iterationId));
    }

    public function removeTaskFromCurrentIteration($taskType, $taskId)
    {
        $innoworkCore = InnoworkCore::instance('innoworkcore',
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()
        );
        $summaries = $innoworkCore->getSummaries();

        $taskClassName = $summaries[$taskType]['classname'];
        if (!strlen($taskClassName)) {
            return false;
        }

        $tempObject = new $taskClassName(
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess(),
            $taskId
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

    public function doGetSummary()
    {
        $result = false;

        $userstories = new InnoworkTaskBoard(
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()
            );
        $userstories_search = $userstories->Search(
            array(
                'done' => \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()->fmtfalse
                ),
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserId(),
            false,
            false,
            10,
            0
            );

        $result =
'<vertgroup>
  <children>';

        foreach ($userstories_search as $story) {
            $result .=
'<link>
  <args>
    <label type="encoded">'.urlencode('- '.$story['id']).'</label>
    <link type="encoded">'.urlencode(
        WuiEventsCall::buildEventsCallString('innoworktaskboard', array(
                array(
                    'view',
                    'showtaskboard',
                    array('id' => $story['id'])
                )
            ))
       ).'</link>
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
