<?php

/**
 * @file plugins/importexport/rsciexport/RSCIExportPlugin.inc.php
 * @class RSCIExportPlugin
 * @ingroup plugins_importexport_rsci
 *
 * @brief RSCI XML export plugin.
 */

import('lib.pkp.classes.plugins.ImportExportPlugin');

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
                    $notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_ERROR, array('pluginName' => $this->getDisplayName(), 'contents' => "Choose one issue."));
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
                $dispatcher->handle404();
        }
    }

    /**
     * @copydoc Plugin::manage()
     */
    function manage($args, $request) {
        $user = $request->getUser();

        $this->import('classes.form.RSCIExportSettingsForm');
        $settingsForm = new RSCIExportSettingsForm($this, $request->getContext()->getId());
        $notificationManager = new NotificationManager();
        switch ($request->getUserVar('verb')) {
            case 'save':
                $settingsForm->readInputData();
                if ($settingsForm->validate()) {
                    $settingsForm->execute();
                    $notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS);
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

    private $_context;

    /**
     * Get the zip with XML for an issue.
     * @param $issueId int
     * @param $context Context
     * @return string XML contents representing the supplied issue IDs.
     */
    function exportIssue($issueId, $context)
    {
        $issueDao = DAORegistry::getDAO('IssueDAO');
        $issue = $issueDao->getById($issueId);
        //$publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
        //$submissionIds = array();

        //$publishedArticles = $publishedArticleDao->getPublishedArticles($issueId);
        //foreach ($publishedArticles as $publishedArticle) {
        //    $submissionIds[] = $publishedArticle->getId();
        //}

        //$submissionDao = Application::getSubmissionDAO();
        $xml = '';
        $filterDao = DAORegistry::getDAO('FilterDAO');
        $rsciExportFilters = $filterDao->getObjectsByGroup('issue=>rsci-xml');
        assert(count($rsciExportFilters) == 1); // Assert only a single serialization filter
        $exportFilter = array_shift($rsciExportFilters);
        $context = Application::getRequest()->getContext();
        $exportSettings = array ('isExportArtTypeFromSectionAbbrev' => $this->getSetting($context->getId(), 'exportArtTypeFromSectionAbbrev'),
                                    'isExportSections' => $this->getSetting($context->getId(), 'exportSections'),
                                    'journalRSCITitleId' => $this->getSetting($context->getId(), 'journalRSCITitleId'));
        $exportFilter->SetExportSettings($exportSettings);
        //$submissions = array();
        //foreach ($submissionIds as $submissionId) {
        //    $submission = $submissionDao->getById($submissionId, $context->getId());
        //    if ($submission) $submissions[] = $submission;
        //}
        libxml_use_internal_errors(true);
        $issueXml = $exportFilter->execute($issue, true);
        $xml = $issueXml->saveXml();
//        $errors = array_filter(libxml_get_errors(), function($a) {
//            return $a->level == LIBXML_ERR_ERROR || $a->level == LIBXML_ERR_FATAL;
//        });
//        if (!empty($errors)) {
//            $this->displayXMLValidationErrors($errors, $xml);
//        }
        return $xml;
    }

    /**
     * @param $issueId int
     * @param $xml string XML file content
     */
    protected function _uploadZip($issueId, $xml)
    {
        import('lib.pkp.classes.file.FileManager');
        $fileManager = new FileManager();
        $xmlFileName = $this->getExportPath() . 'Markup_unicode.xml';
        $fileManager->writeFile($xmlFileName, $xml);

        $issueDao = DAORegistry::getDAO('IssueDAO');
        $issue = $issueDao->getById($issueId);
        $coverUrl = $issue->getLocalizedCoverImageUrl();
        $coverUrlParts = explode('/', $coverUrl);
        $coverName = end($coverUrlParts);
        $fileManager->copyFile($coverUrl, $this->getExportPath() . $coverName);

        $request = Registry::get('request', false);
        $context = $request->getContext();
        $submissionsIterator = Services::get('submission')->getMany([
            'contextId' => $context->getId(),
            'issueId' => $issue->getId()
        ]);
        /** @var Submission[] $publiations */
        $submissions = iterator_to_array($submissionsIterator);
//        $publishedArticleDao = DAORegistry::getDAO('PublishedArticleDAO');
//        $articles = $publishedArticleDao->getPublishedArticles($issue->getId());
        $articleGalleyDAO = DAORegistry::getDAO('ArticleGalleyDAO');

        foreach ($submissions as $submission)
        {
            /** @var Publication $publication */
            $publication = $submission->getCurrentPublication();
            /** @var ArticleGalley $galley */
            $galley = $articleGalleyDAO->getByPublicationId($publication->getId())->next();
            $articleFilePath = $galley->getFile()->getData('path');
            $fileParts = explode('.', $articleFilePath);
            $fileExtension = end($fileParts);
            $pages = $publication->getData('pages');
            $fileManager->copyFile($articleFilePath, $this->getExportPath() . $pages . '.' . $fileExtension);
        }

        // ZIP:
        $zip = new ZipArchive();
        $zipPath = $this->getExportPath().'issue-'.$issue->getNumber().'-'.$issue->getYear() . '.zip';
        if ($zip->open($zipPath, ZipArchive::CREATE)!==TRUE) {
            exit('Невозможно создать архив ZIP (' . $zipPath . '\n');
        }
        $filesToArchive = scandir($this->getExportPath());

        foreach($filesToArchive as $file) {
            if (is_file($this->getExportPath(). $file)) {
                $zip->addFile($this->getExportPath() . $file, basename($file));
            }
        }
        $zip->close();

        // UPLOAD:
        $fileManager->downloadByPath($zipPath);
        $fileManager->rmtree($this->getExportPath());
    }

    /**
     * @var string
     */
    protected $_generatedTempPath = '';

    /**
     * @copydoc ImportExportPlugin::getExportPath()
     */
    function getExportPath()
    {
        if ($this->_generatedTempPath === '')
        {
            $exportPath = parent::getExportPath();
            $journal = Application::getRequest()->getJournal();
            $this->_generatedTempPath =  $exportPath . $this->getPluginSettingsPrefix() . 'Temp-' . date('Ymd-His'). $journal->getId() . '/';
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
        // TODO: Implement executeCLI() method.
    }

    /**
     * Display the command-line usage information
     * @param $scriptName string
     */
    function usage($scriptName)
    {
        // TODO: Implement usage() method.
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
