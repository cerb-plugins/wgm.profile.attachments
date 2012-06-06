<?php
if (class_exists('Extension_ContextProfileTab')):
class ProfileTab_WgmTicketAttachments extends Extension_ContextProfileTab {
	function showTab($context, $context_id) {
		$tpl = DevblocksPlatform::getTemplateService();
		$active_worker = CerberusApplication::getActiveWorker();
		
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
		
		$defaults = new C4_AbstractViewModel();
		$defaults->id = 'ticket_attachments';
		$defaults->class_name = 'View_AttachmentLink';
		$defaults->is_ephemeral = true;
		
		if(null == ($view = C4_AbstractViewLoader::getView($defaults->id, $defaults))) {
			return;
		}
		
		$view->addParamsRequired(array(
			 new DevblocksSearchCriteria(SearchFields_AttachmentLink::LINK_CONTEXT, '=', CerberusContexts::CONTEXT_MESSAGE),
			 new DevblocksSearchCriteria(SearchFields_AttachmentLink::LINK_CONTEXT_ID, 'in', array_keys($messages)),
		), true);
		
		C4_AbstractViewLoader::setView($view->id, $view);
		
		$tpl->assign('view', $view);
		
		// Template
		
		$tpl->display('devblocks:wgm.ticket.attachments::tab.tpl');
	}
}
endif;