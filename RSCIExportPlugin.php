<?php

/**
 * @file plugins/importexport/rsciexport/RSCIExportPlugin.php
 * @class RSCIExportPlugin
 * @ingroup plugins_importexport_rsci
 *
 * @brief RSCI XML export plugin.
 */

namespace APP\plugins\importexport\rsciexport;

use APP\facades\Repo;
use APP\journal\JournalDAO;
use APP\publication\Publication;
use APP\submission\Submission;
use APP\template\TemplateManager;
use PKP\config\Config;
use PKP\context\Context;
use PKP\core\JSONMessage;
use PKP\core\PKPRequest;
use PKP\core\Registry;
use PKP\db\DAORegistry;
use PKP\file\FileManager;
use PKP\galley\Galley;
use APP\notification\Notification;
use PKP\plugins\ImportExportPlugin;
use APP\notification\NotificationManager;
use APP\plugins\importexport\rsciexport\classes\form\RSCIExportSettingsForm;
use PKP\submission\PKPSubmission;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use ZipArchive;

class RSCIExportPlugin extends ImportExportPlugin
{
    /**
     * @copyDoc Plugin::register()
     */
    public function register($category, $path, $mainContextId = null)
    {
        $success = parent::register($category, $path, $mainContextId);
        $this->addLocaleData();
        return $success;
    }

    /**
     * Display the plugin.
     * @param $args array
     * @param $request PKPRequest
     */
    function display($args, $request)
    {
        parent::display($args, $request);
        $templateMgr = TemplateManager::getManager($request);
        $journal = $request->getJournal();

        switch (array_shift($args)) {
            case 'index':
            case '':
                $templateMgr->display($this->getTemplateResource('index.tpl'));
                break;
            case 'exportIssue':
                $issueIdsArr = (array) $request->getUserVar('selectedIssues');
                if (count($issueIdsArr) > 1 || count($issueIdsArr) < 1)
                {
                    $user = $request->getUser();
                    $notificationManager = new NotificationManager();
                    $notificationManager->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_ERROR, array('pluginName' => $this->getDisplayName(), 'contents' => "Choose one issue."));
                    $request->redirectUrl(str_replace("exportIssue", "", $request->getRequestPath()));
                    break;
                }
                else {
                    $issueId = $issueIdsArr[0];
                    $exportXml = $this->exportIssue(
                        $issueId,
                        $request->getContext()
                    );
                    $this->_uploadZip($issueId, $exportXml);
                    break;
                }
            default:
                $dispatcher = $request->getDispatcher();
                throw new NotFoundHttpException();
        }
    }

    /**
     * @copydoc Plugin::manage()
     */
    function manage($args, $request) {
        $user = $request->getUser();

        $settingsForm = new RSCIExportSettingsForm($this, $request->getContext()->getId());
        $notificationManager = new NotificationManager();
        switch ($request->getUserVar('verb')) {
            case 'save':
                $settingsForm->readInputData();
                if ($settingsForm->validate()) {
                    $settingsForm->execute([]);
                    $notificationManager->createTrivialNotification($user->getId(), Notification::NOTIFICATION_TYPE_SUCCESS);
                    return new JSONMessage();
                } else {
                    return new JSONMessage(true, $settingsForm->fetch($request));
                }
            case 'index':
                $settingsForm->initData();
                return new JSONMessage(true, $settingsForm->fetch($request));
        }
        return parent::manage($args, $request);
    }

    /**
     * Get the zip with XML for an issue.
     * @param $issueId int
     * @param $context Context
     * @return string XML contents representing the supplied issue IDs.
     */
    function exportIssue($issueId, $context)
    {
        $issue = Repo::issue()->get($issueId);
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $rsciExportFilters = $filterDao->getObjectsByGroup('issue=>rsci-xml');
        assert(count($rsciExportFilters) == 1); // Assert only a single serialization filter
        $exportFilter = array_shift($rsciExportFilters);
        $exportSettings = array ('isExportArtTypeFromSectionAbbrev' => $this->getSetting($context->getId(), 'exportArtTypeFromSectionAbbrev'),
                                    'isExportSections' => $this->getSetting($context->getId(), 'exportSections'),
                                    'journalRSCITitleId' => $this->getSetting($context->getId(), 'journalRSCITitleId'));
        $exportFilter->SetExportSettings($exportSettings);
        libxml_use_internal_errors(true);
        $issueXml = $exportFilter->execute($issue, true);
        $xml = $issueXml->saveXml();
        return $xml;
    }

    /**
     * @param $issueId int
     * @param $xml string XML file content
     */
    protected function _uploadZip($issueId, $xml)
    {
        $fileManager = new FileManager();
        $zipPath = $this->createIssueZip($issueId, $xml);

        // UPLOAD:
        $fileManager->downloadByPath($zipPath);
        $fileManager->rmtree($this->getExportPath());
    }

    /**
     * Create the zip with XML, cover image, and article files for an issue.
     * @param $issueId int
     * @param $xml string XML file content
     * @return string Path to the generated zip file.
     */
    protected function createIssueZip($issueId, $xml)
    {
        $fileManager = new FileManager();
        $xmlFileName = $this->getExportPath() . 'Markup_unicode.xml';
        $fileManager->writeFile($xmlFileName, $xml);

        $issue = Repo::issue()->get($issueId);
        $coverUrl = $issue->getLocalizedCoverImageUrl();
        $coverUrlParts = explode('/', $coverUrl);
        $coverName = end($coverUrlParts);
        if ($coverUrl != "")
            $fileManager->copyFile($coverUrl, $this->getExportPath() . $coverName);

        $submissionsIterator = Repo::submission()->getCollector()
            ->filterByContextIds([$issue->getData('journalId')])
            ->filterByIssueIds([$issue->getId()])
            ->filterByStatus([PKPSubmission::STATUS_PUBLISHED, PKPSubmission::STATUS_SCHEDULED])
            ->GetMany();
        /** @var Submission[] $publiations */
        $submissions = iterator_to_array($submissionsIterator);
//        $publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
//        $articles = $publishedArticleDao->getPublishedArticles($issue->getId());


        foreach ($submissions as $submission)
        {
            /** @var Publication $publication */
            $publication = $submission->getCurrentPublication();
            /** @var Galley $galley */
            $galleys = Repo::galley()->dao->getByPublicationId($publication->getId());
            $galley = array_shift($galleys);
            if (isset($galley))
            {
                $fileName = array_shift($galley->getFile()->getData('name'));
                $articleFilePath = $galley->getFile()->getData('path');

                if ($articleFilePath != "")
                    copy(Config::getVar('files', 'files_dir') . '/' . $articleFilePath, $this->getExportPath() . $fileName);
            }
        }

        // ZIP:
        $zip = new ZipArchive();
        $zipPath = $this->getExportPath().'issue-'.$issue->getNumber().'-'.$issue->getYear() . '.zip';
        if ($zip->open($zipPath, ZipArchive::CREATE)!==TRUE) {
            exit('Невозможно создать архив ZIP (' . $zipPath . '\n');
        }
        $filesToArchive = scandir($this->getExportPath());

        foreach($filesToArchive as $file) {
            if (is_file($this->getExportPath(). $file) && $this->getExportPath() . $file != $zipPath) {
                $zip->addFile($this->getExportPath() . $file, basename($file));
            }
        }
        $zip->close();

        return $zipPath;
    }

    /**
     * @var string
     */
    protected $_generatedTempPath = '';

    /**
     * @var int
     */
    protected $_exportContextId = 0;

    /**
     * @copydoc ImportExportPlugin::getExportPath()
     */
    function getExportPath()
    {
        if ($this->_generatedTempPath === '')
        {
            $exportPath = parent::getExportPath();
            $request = Registry::get('request', false);
            $journal = $request ? $request->getJournal() : null;
            $contextId = $journal ? $journal->getId() : $this->_exportContextId;
            $this->_generatedTempPath =  $exportPath . $this->getPluginSettingsPrefix() . 'Temp-' . date('Ymd-His'). $contextId . '/';
        }
        return $this->_generatedTempPath;
    }

    /**
     * @copydoc ImportExportPlugin::getPluginSettingsPrefix()
     */
    function getPluginSettingsPrefix() {
        return 'rsciexport';
    }

    /**
     * Execute import/export tasks using the command-line interface.
     * @param $scriptName The name of the command-line script (displayed as usage info)
     * @param $args Parameters to the plugin
     */
    function executeCLI($scriptName, &$args)
    {
        $command = array_shift($args);

        if ($command == 'export') {
            $zipFile = array_shift($args);
        } else {
            $zipFile = $command;
        }

        $journalPath = array_shift($args);
        $issueArg = array_shift($args);
        $issueId = $issueArg == 'issue' ? array_shift($args) : $issueArg;

        $journalDao = DAORegistry::getDAO('JournalDAO'); /** @var JournalDAO $journalDao */
        $journal = $journalDao->getByPath($journalPath);

        if (!$journal) {
            if ($journalPath != '') {
                echo __('plugins.importexport.common.cliError') . "\n";
                echo __('plugins.importexport.common.error.unknownContext', ['contextPath' => $journalPath]) . "\n\n";
            }
            $this->usage($scriptName);
            return;
        }

        if (!$zipFile || !$issueId) {
            $this->usage($scriptName);
            return;
        }

        if ($this->isRelativePath($zipFile) && !preg_match('/^[A-Za-z]:[\/\\\\]/', $zipFile) && substr($zipFile, 0, 2) !== '\\\\') {
            $zipFile = PWD . '/' . $zipFile;
        }

        $outputDir = dirname($zipFile);
        if (!is_writable($outputDir) || (file_exists($zipFile) && !is_writable($zipFile))) {
            echo __('plugins.importexport.common.cliError') . "\n";
            echo __('plugins.importexport.common.export.error.outputFileNotWritable', ['param' => $zipFile]) . "\n\n";
            $this->usage($scriptName);
            return;
        }

        $issue = Repo::issue()->getByBestId($issueId, $journal->getId());
        if (!$issue) {
            echo __('plugins.importexport.common.cliError') . "\n";
            echo __('plugins.importexport.rsciexport.export.error.issueNotFound', ['issueId' => $issueId]) . "\n\n";
            return;
        }

        $this->_exportContextId = $journal->getId();
        $exportXml = $this->exportIssue($issue->getId(), $journal);
        $zipPath = $this->createIssueZip($issue->getId(), $exportXml);

        $fileManager = new FileManager();
        if (!$fileManager->copyFile($zipPath, $zipFile)) {
            echo __('plugins.importexport.common.cliError') . "\n";
            echo __('plugins.importexport.common.export.error.outputFileNotWritable', ['param' => $zipFile]) . "\n\n";
            $fileManager->rmtree($this->getExportPath());
            return;
        }
        $fileManager->rmtree($this->getExportPath());

        return true;
    }

    /**
     * Display the command-line usage information
     * @param $scriptName string
     */
    function usage($scriptName)
    {
        echo __('plugins.importexport.rsciexport.cliUsage', [
            'scriptName' => $scriptName,
            'pluginName' => $this->getName()
        ]) . "\n";
    }

    /**
     * Get the name of this plugin. The name must be unique within
     * its category, and should be suitable for part of a filename
     * (ie short, no spaces, and no dependencies on cases being unique).
     *
     * @return string name of plugin
     */
    function getName(): string
    {
        return "RSCIExportPlugin";
    }

    /**
     * Get the display name for this plugin.
     *
     * @return string
     */
    function getDisplayName(): string
    {
        return __('plugins.importexport.rsciexport.displayName');
    }

    /**
     * Get a description of this plugin.
     *
     * @return string
     */
    function getDescription(): string
    {
        return __('plugins.importexport.rsciexport.description');
    }
}
