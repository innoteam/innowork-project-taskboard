<?php

use \Innomatic\Core\InnomaticContainer;
use \Innomatic\Wui\Widgets;
use \Innomatic\Wui\Dispatch;
use \Innomatic\Locale\LocaleCatalog;
use \Innomatic\Domain\User;
use \Shared\Wui;

class InnoworktaskboardPanelActions extends \Innomatic\Desktop\Panel\PanelActions
{
    private $localeCatalog;
    public $status;
    public $taskboardId;

    public function __construct(\Innomatic\Desktop\Panel\PanelController $controller)
    {
        parent::__construct($controller);
    }

    public function beginHelper()
    {
        $this->localeCatalog = new LocaleCatalog(
            'innowork-projects-taskboard::taskboard_panel',
            \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->getLanguage()
        );
    }

    public function endHelper()
    {
    }

    public function executeNewtaskboard($eventData)
    {
        $innomaticCore = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');

        if (!$innomaticCore->getCurrentUser()->hasPermission('add_taskboards')) {
            return;
        }

    	require_once('innowork/taskboard/InnoworkTaskBoard.php');
    	$board = new InnoworkTaskBoard(
    		$innomaticCore->getDataAccess(),
    		$innomaticCore->getCurrentDomain()->getDataAccess()
    	);

    	if ($board->create($eventData)) {
            $this->taskboardId = $board->mItemId;
    		//$this->status = $this->localeCatalog->getStr('bug_created.status');
    	} else {
    		//$this->status = $this->localeCatalog->getStr('bug_not_created.status');
    	}

    	$this->setChanged();
        $this->notifyObservers('taskboardid');
    	$this->notifyObservers('status');
    }

    public function executeEdittaskboard($eventData)
    {
        $innomaticCore = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');

        if (!$innomaticCore->getCurrentUser()->hasPermission('add_taskboards')) {
            return;
        }

        require_once('innowork/taskboard/InnoworkTaskBoard.php');
        $board = new InnoworkTaskBoard(
            $innomaticCore->getDataAccess(),
            $innomaticCore->getCurrentDomain()->getDataAccess(),
            $eventData['id']
        );

        $board->edit($eventData);
    }

    public function executeTrashtaskboard($eventData)
    {
        $innomaticCore = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');

        if (!$innomaticCore->getCurrentUser()->hasPermission('add_taskboards')) {
            return;
        }

        require_once('innowork/taskboard/InnoworkTaskBoard.php');
        $board = new InnoworkTaskBoard(
            $innomaticCore->getDataAccess(),
            $innomaticCore->getCurrentDomain()->getDataAccess(),
            $eventData['id']
        );

        $board->trash($innomaticCore->getCurrentUser()->getUserId());
    }

    public static function ajaxAddProject($taskboardId, $projectId)
    {
        $objResponse = new XajaxResponse();
        require_once('innowork/core/InnoworkCore.php');

        $innomaticCore = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');
        $taskboard = InnoworkCore::getItem('taskboard', $taskboardId);
        $taskboard->addProject($projectId);
        $html = WuiXml::getContentFromXml('', \InnoworktaskboardPanelController::getProjectsListXml($taskboardId));
        $objResponse->addAssign('settings_projects', 'innerHTML', $html);

        return $objResponse;
    }
}
