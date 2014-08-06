<?php

/**
 * Taskboard panel controller
 *
 */
class InnoworktaskboardPanelController extends \Innomatic\Desktop\Panel\PanelController
{
    /* public update($observable, $arg = '') {{{ */
    /**
     * Observing update method.
     *
     * @param Observable $observable
     * @param string $arg
     * @access public
     * @return void
     */
    public function update($observable, $arg = '')
    {
    }
    /* }}} */

    /* public getProjectsListXml($taskboardId) {{{ */
    /**
     * Gets the WUI xml for the taskboard projects list.
     *
     * @param integer $taskboardId Taskboard id
     * @static
     * @access public
     * @return string
     */
    public static function getProjectsListXml($taskboardId)
    {
        require_once('innowork/core/InnoworkCore.php');

        $innomaticCore = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');

        $taskboard = InnoworkCore::getItem('taskboard', $taskboardId);
        $projectsList = $taskboard->getProjectsList();

        $localeCatalog = new LocaleCatalog(
            'innowork-projects-taskboard::taskboard_panel',
            $innomaticCore->getCurrentUser()->getLanguage()
        );

        // Populate list of available projects that can be added
        $projectItem = InnoworkCore::getItem('project');
        $projectSearch = $projectItem->search(array('done' => $innomaticCore->getCurrentDomain()->getDataAccess()->fmtfalse));

        $availableProjects = array();
        foreach ($projectSearch as $id => $values) {
            // Filter out project already added to the current taskboard
            if (!isset($projectsList[$id])) {
                $availableProjects[$id] = $values['name'];
            }
        }

        $headers[0]['label'] = $localeCatalog->getStr('project_header');

        $xml = '<vertgroup><children>
            <table><args><headers type="array">'.\Shared\Wui\WuiXml::encode($headers).'</headers></args><children>';
        $row = 0;
        foreach ($projectsList as $projectId => $projectName) {
            $xml .= '<label row="'.$row.'" col="0"><args><label>'.\Shared\Wui\WuiXml::cdata($projectName).'</label></args></label>';
            $row++;
        }
        $xml .= '</children></table>
            <grid><children>
            <combobox row="0" col="0"><args>
                <id>add_project_id</id>
                <elements type="array">'.\Shared\Wui\WuiXml::encode($availableProjects).'</elements>
            </args></combobox>
<button row="0" col="1"><name>add</name>
                            <events><click>xajax_AddProject('.$taskboardId.', document.getElementById(\'add_project_id\').value);</click></events>
          <args>
            <themeimage>mathadd</themeimage>
            <horiz>true</horiz>
            <frame>false</frame>
<label>'.\Shared\Wui\WuiXml::cdata($localeCatalog->getStr('add_project_button')).'</label>
                            <action>javascript:void(0)</action>
          </args>
        </button>
            </children></grid>
            </children></vertgroup>';
        return $xml;
    }
    /* }}} */
}
