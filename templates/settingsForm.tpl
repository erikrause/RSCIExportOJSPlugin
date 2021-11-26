{**
 * plugins/importexport/resciexport/templates/settingsForm.tpl
 *
 * RSCI plugin settings
 *
 *}
<script type="text/javascript">
    $(function() {ldelim}
        // Attach the form handler.
        $('#rsciexportSettingsForm').pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
        {rdelim});
</script>
<form class="pkp_form" id="rsciexportSettingsForm" method="post" action="{url router=$smarty.const.ROUTE_COMPONENT op="manage" plugin="RSCIExportPlugin" category="importexport" verb="save"}">
    {csrf}
    {fbvFormArea id="rsciexportSettingsFormArea"}

    {fbvFormSection list="true"}
    {fbvElement type="text" id="journalRSCITitleId" value=$journalRSCITitleId label="plugins.importexport.rsciexport.settings.form.journalRSCITitleId"}
    {/fbvFormSection}
    {fbvFormSection list="true"}
    {fbvElement type="checkbox" id="exportSections" label="plugins.importexport.rsciexport.settings.form.exportSections" checked=$exportSections|compare:true}
    {/fbvFormSection}
    {fbvFormSection list="true"}
    {fbvElement type="checkbox" id="exportArtTypeFromSectionAbbrev" label="plugins.importexport.rsciexport.settings.form.exportArtTypeFromSectionAbbrev" checked=$exportArtTypeFromSectionAbbrev|compare:true}
    {/fbvFormSection}
    {/fbvFormArea}
    {fbvFormButtons submitText="common.save"}
</form>