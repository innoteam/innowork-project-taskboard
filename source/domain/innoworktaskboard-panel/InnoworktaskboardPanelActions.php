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
        if (!\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentUser()->hasPermission('add_taskboards')) {
            return;
        }

    	require_once('innowork/taskboard/InnoworkTaskBoard.php');
    	$board = new InnoworkTaskBoard(
    		\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getDataAccess(),
    		\Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')->getCurrentDomain()->getDataAccess()
    	);

    	if ($board->create($eventData)) {
    		$GLOBALS['innowork-projects-taskboard']['newboardid'] = $board->mItemId;
    		//$this->status = $this->localeCatalog->getStr('bug_created.status');
    	} else {
    		//$this->status = $this->localeCatalog->getStr('bug_not_created.status');
    	}

    	$this->setChanged();
    	$this->notifyObservers('status');
    }

}
