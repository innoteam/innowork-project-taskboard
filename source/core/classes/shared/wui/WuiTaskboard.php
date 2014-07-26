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
        static $included;

        $dropzoneJs = '';

        $container = \Innomatic\Core\InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer');

        if (!isset($included)) {
            $dropzoneJs = '<script src="'.$container->getBaseUrl(false).'/shared/dropzone.js"></script>
                <link href="'.$container->getBaseUrl(false).'/shared/dropzone.css" type="text/css" rel="stylesheet">';
            $included = true;
        }

        $id           = isset($this->mArgs['id']) ? $this->mArgs['id'] : $this->mName;
        $pageModule   = $this->mArgs['pagemodule'];
        $pageName     = $this->mArgs['pagename'];
        $pageId       = strlen($this->mArgs['pageid']) ? $this->mArgs['pageid'] : '0';
        $blockModule  = $this->mArgs['blockmodule'];
        $blockName    = $this->mArgs['blockname'];
        $blockCounter = $this->mArgs['blockcounter'];
        $fileId       = $this->mArgs['fileid'];
        $maxFiles     = $this->mArgs['maxfiles'];

        // Handle case of image in site wide parameters
        if (!(strlen($pageModule) && strlen($pageName))) {
            $pageModule = 'site';
            $pageName   = 'global';
        }

        $fileParameters = $pageModule.'/'.$pageName.'/'.$pageId.'/'.$blockModule.'/'.$blockName.'/'.$blockCounter.'/'.$fileId;

        // Start Add thumbnail
        $page = $pageModule.'/'.$pageName;
        $block = $blockModule.'/'.$blockName;

        $containerDropzoneId = "container_$id";
        $this->mLayout = ($this->mComments ? '<!-- begin ' . $this->mName . ' dropzone -->' : '') .
        $this->mLayout .= $dropzoneJs.'<div id="'.$containerDropzoneId.'">
<div id="'.$id.'"></div>
<script>
var dropzone = new Dropzone("#'.$id.'", { url: "'.$container->getBaseUrl(false).'/dropzone/'.$fileParameters.'"';

        if (isset($maxFiles)) {
            $this->mLayout .= ', maxFiles: '.$maxFiles;
        }

        $this->mLayout .= ', addRemoveLinks: true,
            removedfile: function(file) {
                var mediaId = file.mediaid;
                var mediaName = file.name;

                if (mediaId != null) {
                    xajax_WuiDropzoneRemoveMedia(\''.$containerDropzoneId.'\', \''.$page.'\', \''.$pageId.'\', \''.$block.'\', \''.$blockCounter.'\', \''.$fileId.'\', \''.$maxFiles.'\', mediaId, mediaName);

                } else {

                    var _ref;
                    return (_ref = file.previewElement) != null ? _ref.parentNode.removeChild(file.previewElement) : void 0;
                }
            }
        });';

        $objectQuery = \Innomedia\Media::getMediaByParams($this->mArgs);

        $count = 0;
        while (!$objectQuery->eof) {
            $mediaid   = $objectQuery->getFields('id');
            $name      = $objectQuery->getFields('name');
            $path      = $objectQuery->getFields('path');

            $webappurl = $container->getCurrentDomain()->domaindata['webappurl'];
            $last_char = substr($webappurl, -1);
            $separetor = $last_char == '/' ? '' : '/';

            $filetype  = $objectQuery->getFields('filetype');
            $typepath  = \Innomedia\Media::getTypePath($filetype);

            $pathfull  = $webappurl.$separetor.'/storage/'.$typepath.'/'.$path;

            $size = filesize(
                $container->getHome().'/../'
                .$container->getCurrentDomain()->domaindata['domainid']
                .'/storage/'.$typepath.'/'.$path
            );

            $this->mLayout .='var mockFile = { name: "'.$name.'", size: "'.$size.'", mediaid: "'.$mediaid.'"};
                dropzone.options.addedfile.call(dropzone, mockFile);'
                .($filetype != 'file' ? 'dropzone.options.thumbnail.call(dropzone, mockFile, "'.$pathfull.'");' : '');
            $objectQuery->moveNext();
            $count++;
        }
        // End Add thumbnail

$this->mLayout .=
'document.querySelector("#'.$id.'").classList.add("dropzone");
var existingFileCount = '.$count.'; // The number of files already uploaded
dropzone.options.maxFiles = dropzone.options.maxFiles - existingFileCount;
</script>
</div>';

        $this->mLayout .= $this->mComments ? '<!-- end ' . $this->mName . " dropzone -->\n" : '';

        return true;
    }

    /**
     * Remove image selected
     * @param  string  $containerDropzoneId id of div container of the dropzone
     * @param  string  $page                name of page
     * @param  integer $pageId              id of page
     * @param  string  $block               name of block
     * @param  integer $blockCounter        if of block
     * @param  string  $fileId              type of media
     * @param  integer $maxFiles            number max of image for gallery
     * @param  integer $mediaId             id of media
     * @param  string  $mediaName           name of media
     * @return object                       return a object
     */
    public static function ajaxRemoveMedia($containerDropzoneId, $page, $pageId, $block, $blockCounter, $fileId, $maxFiles, $mediaId, $mediaName)
    {

        // Delete image from Innomedia_media
        $image = new \Innomedia\Media($mediaId);
        $image->retrieve();
        $image->delete();

        // Delete ref image from innomedia_blocks
        $domainDa = InnomaticContainer::instance('\Innomatic\Core\InnomaticContainer')
            ->getCurrentDomain()
            ->getDataAccess();

        $checkQuery = $domainDa->execute(
            "SELECT     id, params
                FROM    innomedia_blocks
                WHERE   block = '$block'
                    AND counter = $blockCounter
                    AND page = '$page'
                    AND pageid ".($pageId != 0 ? "= {$pageId}" : "is NULL")
        );

        if ($checkQuery->getNumberRows() > 0) {
            $row_id = $checkQuery->getFields('id');
            $json_params = $checkQuery->getFields('params');

            $params = json_decode($json_params, true);

            $key = @array_search($mediaId, $params[$fileId.'_id']);

            // remove id image selected
            unset($params[$fileId.'_id'][$key]);

            // convet array in a not-associative array
            $params[$fileId.'_id'] = @array_values($params[$fileId.'_id']);

            $domainDa->execute(
                "UPDATE innomedia_blocks
                SET params=".$domainDa->formatText(json_encode($params)).
                " WHERE id=$row_id"
            );

        }

        // Update widget Dropzone
        $objResponse = new XajaxResponse();

        list($pageModule, $pageName) = explode("/", $page);
        list($blockModule, $blockName) = explode("/", $block);

        $xml = '<dropzone><args>
                  <maxfiles>'.$maxFiles.'</maxfiles>
                  <pagemodule>'.$pageModule.'</pagemodule>
                  <pagename>'.$pageName.'</pagename>
                  <pageid>'.$pageId.'</pageid>
                  <blockmodule>'.$blockModule.'</blockmodule>
                  <blockname>'.$blockName.'</blockname>
                  <blockcounter>'.$blockCounter.'</blockcounter>
                  <fileid>'.$fileId.'</fileid>
                </args></dropzone>';

        $html = WuiXml::getContentFromXml('', $xml);

        $objResponse->addAssign($containerDropzoneId, "innerHTML", $html);

        return $objResponse;
    }
}
