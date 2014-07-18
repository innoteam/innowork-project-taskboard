<?php

use \Innomatic\Core\InnomaticContainer;
use \Innomatic\Wui\Widgets;
use \Innomatic\Wui\Dispatch;
use \Innomatic\Locale\LocaleCatalog;
use \Innomatic\Domain\User;
use \Shared\Wui;

class InnoworktasksPanelActions extends \Innomatic\Desktop\Panel\PanelActions
{
    private $localeCatalog;
    public $status;

    public function __construct(\Innomatic\Desktop\Panel\PanelController $controller)
    {
        parent::__construct($controller);
    }

    public function beginHelper()
    {
        $this->localeCatalog = new LocaleCatalog(
            'innowork-projects::tasks_domain_main',
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getLanguage()
        );
    }

    public function endHelper()
    {
    }

    public function executeNewtask($eventData)
    {
    	require_once('innowork/projects/InnoworkTask.php');
    	$task = new InnoworkTask(
    		\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
    		\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()
    	);

    	if (isset($eventData['projectid_id'])) {
    	    $eventData['projectid'] = $eventData['projectid_id'];
    	    unset($eventData['projectid_id']);
    	}

        $eventData['openedby'] = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserId();
        $eventData['assignedto'] = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserId();

    	if ($task->Create($eventData)) {
    		$GLOBALS['innowork-tasks']['newtaskid'] = $task->mItemId;
    		$this->status = $this->localeCatalog->getStr('task_created.status');
    	} else {
    		$this->status = $this->localeCatalog->getStr('task_not_created.status');
    	}

    	$this->setChanged();
    	$this->notifyObservers('status');
    }

    public function executeEdittask($eventData)
    {
    	require_once('innowork/projects/InnoworkTask.php');

    	$task = new InnoworkTask(
    		\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
    		\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess(),
    		$eventData['id']
    	);

    	if ($task->Edit($eventData)) {
    		$this->status = $this->localeCatalog->getStr('task_updated.status');
    	} else {
    		$this->status = $this->localeCatalog->getStr('task_not_updated.status');
    	}

    	$this->setChanged();
    	$this->notifyObservers('status');
    }

    public function executeTrashtask($eventData)
    {
    	require_once('innowork/projects/InnoworkTask.php');

    	$task = new InnoworkTask(
    		\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
    		\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess(),
    		$eventData['id']
    	);

    	if ($task->trash(\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserId())) {
    		$this->status = $this->localeCatalog->getStr('task_trashed.status');
    	} else {
    		$this->status = $this->localeCatalog->getStr('task_not_trashed.status');
    	}

    	$this->setChanged();
    	$this->notifyObservers('status');
    }

    public function executeNewmessage($eventData)
    {
    	require_once('innowork/projects/InnoworkTask.php');

    	$task = new InnoworkTask(
    		\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
    		\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess(),
    		$eventData['taskid']
    	);

    	if ($task->addMessage(
    		\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getUserName(),
    		$eventData['content'])
		) {
    		$this->status = $this->localeCatalog->getStr('message_created.status');
    	} else {
    		$this->status = $this->localeCatalog->getStr('message_not_created.status');
    	}

    	$this->setChanged();
    	$this->notifyObservers('status');
    }

    public function executeRemovemessage($eventData)
    {
    	require_once('innowork/projects/InnoworkTask.php');

    	$task = new InnoworkTask(
    		\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
    		\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess(),
    		$eventData['taskid']
    	);

    	if ($task->removeMessage($eventData['messageid'])) $this->status = $this->localeCatalog->getStr('message_removed.status');
    	else $this->status = $this->localeCatalog->getStr('message_not_removed.status');

    	$this->setChanged();
    	$this->notifyObservers('status');
    }

    public function executeErasefilter($eventData) {
    	$filter_sk = new WuiSessionKey('customer_filter', array('value' => ''));
    	$filter_sk = new WuiSessionKey('project_filter', array('value' => ''));
    	$filter_sk = new WuiSessionKey('priority_filter', array('value' => ''));
    	$filter_sk = new WuiSessionKey('status_filter', array('value' => ''));
    	$filter_sk = new WuiSessionKey('resolution_filter', array('value' => ''));
    	$filter_sk = new WuiSessionKey('type_filter', array('value' => ''));
    	$filter_sk = new WuiSessionKey('year_filter', array('value' => ''));
    	$filter_sk = new WuiSessionKey('month_filter', array('value' => ''));
    	$filter_sk = new WuiSessionKey('day_filter', array('value' => ''));
    	$filter_sk = new WuiSessionKey('openedby_filter', array('value' => ''));
    	$filter_sk = new WuiSessionKey('assignedto_filter', array('value' => ''));
    }
}
