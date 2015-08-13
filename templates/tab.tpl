<form action="{devblocks_url}{/devblocks_url}" method="POST">
	<input type="hidden" name="c" value="wgm.profile.attachments">
	<input type="hidden" name="a" value="downloadZip">
	<input type="hidden" name="context" value="{$context}">
	<input type="hidden" name="context_id" value="{$context_id}">
	<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">
	
	<button type="submit"><span class="glyphicons glyphicons-file-import"></span></a> {'wgm.profile.attachments.download_zip'|devblocks_translate}</button>
</form>

{include file="devblocks:cerberusweb.core::internal/views/search_and_view.tpl" view=$view}