<?php
if(!defined('PCLZIP_TEMPORARY_DIR'))
	define('PCLZIP_TEMPORARY_DIR', APP_TEMP_PATH);

if (class_exists('Extension_ContextProfileTab')):
class ProfileTab_WgmTicketAttachments extends Extension_ContextProfileTab {
	function showTab($context, $context_id) {
		$tpl = DevblocksPlatform::getTemplateService();
		$active_worker = CerberusApplication::getActiveWorker();
		
		$tpl->assign('context', $context);
		$tpl->assign('context_id', $context_id);
		
		// Check permissions on this ticket for this worker
		
		if(0 != strcasecmp($context, CerberusContexts::CONTEXT_TICKET))
			return;
		
		if(null == ($ticket = DAO_Ticket::get($context_id)))
			return;
		
		if(!$active_worker->isGroupMember($ticket->group_id))
			return;
		
		// Load the message IDs for this ticket
		
		$messages = DAO_Message::getMessagesByTicket($context_id);
		
		// Load an attachments view
		
		$defaults = C4_AbstractViewModel::loadFromClass('View_AttachmentLink');
		$defaults->id = 'ticket_attachments';
		$defaults->is_ephemeral = true;
		
		if(null == ($view = C4_AbstractViewLoader::getView($defaults->id, $defaults))) {
			return;
		}
		
		$view->addParamsRequired(array(
			 new DevblocksSearchCriteria(SearchFields_AttachmentLink::LINK_CONTEXT, '=', CerberusContexts::CONTEXT_MESSAGE),
			 new DevblocksSearchCriteria(SearchFields_AttachmentLink::LINK_CONTEXT_ID, 'in', array_keys($messages)),
		), true);
		
		$tpl->assign('view', $view);
		
		// Template
		
		$tpl->display('devblocks:wgm.profile.attachments::tab.tpl');
	}
}
endif;

class WgmProfileAttachmentsZipController extends DevblocksControllerExtension {
	const ID = 'wgm.profile.attachments';
	
	function isVisible() {
		// The current session must be a logged-in worker to use this page.
		if(null == ($worker = CerberusApplication::getActiveWorker()))
			return false;
		return true;
	}

	/*
	 * Request Overload
	 */
	function handleRequest(DevblocksHttpRequest $request) {
		$stack = $request->path;
		array_shift($stack); // example
		
	    @$action = array_shift($stack) . 'Action';

	    switch($action) {
	        case NULL:
	            break;
	            
	        default:
			    // Default action, call arg as a method suffixed with Action
				if(method_exists($this,$action)) {
					call_user_func(array(&$this, $action));
				}
	            break;
	    }
	    
	    exit;
	}

	function writeResponse(DevblocksHttpResponse $response) {
		return;
	}
	
	function downloadZipAction() {
		$context = DevblocksPlatform::importGPC($_REQUEST['context'],'string','');
		$context_id = DevblocksPlatform::importGPC($_REQUEST['context_id'],'integer',0);
		
		if(empty($context) || empty($context_id))
			DevblocksPlatform::dieWithHttpError(null, 404);
		
		// Security
		if(null == ($active_worker = CerberusApplication::getActiveWorker()))
			DevblocksPlatform::dieWithHttpError($translate->_('common.access_denied'), 403);

		// Ticket
		if(0 != strcasecmp($context, CerberusContexts::CONTEXT_TICKET))
			return;
		
		if(null == ($ticket = DAO_Ticket::get($context_id)))
			return;
		
		$messages = DAO_Message::getMessagesByTicket($context_id);
		
		if(empty($messages))
			return;
		
		$context = Extension_DevblocksContext::get(CerberusContexts::CONTEXT_MESSAGE);
		$links = DAO_AttachmentLink::getByContextIds($context->id, array_keys($messages));
		$zip_fp = DevblocksPlatform::getTempFile();
		$zip_filename = DevblocksPlatform::getTempFileInfo($zip_fp);
		
		$download_filename = sprintf("ticket-%s-attachments.zip",
			DevblocksPlatform::strAlphaNum($ticket->mask,'_-')
		);
		
		if(extension_loaded('zip')) { /* @var $zip ZipArchive */
			$zip = new ZipArchive();
			$zip->open($zip_filename, ZipArchive::OVERWRITE);
			
		} else { /* @var $zip PclZip */
			$zip = new PclZip($zip_filename);
		}
		
		foreach($links as $link) {
			// Security
			if(!$context->authorize($link->context_id, $active_worker))
				continue;
				
			$file = $link->getAttachment(); /* @var $file Model_Attachment */
			
			if(false === ($fp = DevblocksPlatform::getTempFile()))
				continue;
			
			if(false === $file->getFileContents($fp))
				continue;
			
			$fp_filename = DevblocksPlatform::getTempFileInfo($fp);

			if($zip instanceof ZipArchive) { /* @var $zip ZipArchive */
				$zip->addFile($fp_filename, $file->display_name);
				
			} else { /* @var $zip PclZip */
				$zip->add(array(
						array(
 							PCLZIP_ATT_FILE_NAME => $fp_filename,
							PCLZIP_ATT_FILE_NEW_FULL_NAME => $file->display_name,
						),
					),
					PCLZIP_OPT_REMOVE_ALL_PATH
				);
			}
			
			fclose($fp);
		}
		
		if(extension_loaded('zip')) {
			/* @var $zip ZipArchive */
			$zip->close();
		} else {
			/* @var $zip PclZip */
		}

		fclose($zip_fp);
		
		$zip_fp = fopen($zip_filename, 'rb');

		$file_stats = fstat($zip_fp);
		
		// Set headers
		header("Expires: Mon, 26 Nov 1979 00:00:00 GMT");
		header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
		header("Accept-Ranges: bytes");
		header("Content-disposition: attachment; filename=" . $download_filename);
		header("Content-Type: application/zip");
		header("Content-Length: " . $file_stats['size']);

		fpassthru($zip_fp);
		fclose($zip_fp);
		
		exit;
	}
};