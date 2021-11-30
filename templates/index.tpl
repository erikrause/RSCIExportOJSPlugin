{**
 * plugins/importexport/rsciexport/templates/index.tpl
 *
 * List of operations this plugin can perform
 *}
{extends file="layouts/backend.tpl"}

{block name="page"}
	<h1 class="app__pageHeading">
		{$pageTitle}
	</h1>

	<script type="text/javascript">
		// Attach the JS file tab handler.
		$(function() {ldelim}
			$('#importExportTabs').pkpHandler('$.pkp.controllers.TabHandler');
			$('#exportIssuesXmlForm').pkpHandler('$.pkp.controllers.form.FormHandler');
			{rdelim});
	</script>

	<div id="importExportTabs">
		<ul>
			<li><a href="#settings-tab">{translate key="plugins.importexport.common.settings"}</a></li>
			<li><a href="#exportIssues-tab">{translate key="plugins.importexport.common.export.issues"}</a></li>
		</ul>

		<div id="settings-tab">
			{capture assign=rsciexportSettingsGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.settings.plugins.settingsPluginGridHandler" op="manage" plugin="RSCIExportPlugin" category="importexport" verb="index" escape=false}{/capture}
			{load_url_in_div id="rsciexportSettingsGridContainer" url=$rsciexportSettingsGridUrl}
		</div>

		<div id="exportIssues-tab">
			<form id="exportIssuesXmlForm" class="pkp_form" action="{plugin_url path="exportIssue"}" method="post">
				{csrf}
				{fbvFormArea id="issuesXmlForm"}
				{capture assign=issuesListGridUrl}{url router=$smarty.const.ROUTE_COMPONENT component="grid.issues.ExportableIssuesListGridHandler" op="fetchGrid" escape=false}{/capture}
				{load_url_in_div id="issuesListGridContainer" url=$issuesListGridUrl}
				{fbvFormButtons submitText="plugins.importexport.rsciexport.export.issue" hideCancel="true"}
				{/fbvFormArea}
			</form>
		</div>
	</div>
{/block}