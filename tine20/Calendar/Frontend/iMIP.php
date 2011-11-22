<?php
/**
 * Tine 2.0
 * 
 * @package     Calendar
 * @subpackage  Frontend
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Cornelius Weiss <c.weiss@metaways.de>
 * @copyright   Copyright (c) 2011 Metaways Infosystems GmbH (http://www.metaways.de)
 */

/**
 * iMIP (RFC 6047) frontend for calendar
 * @package     Calendar
 * @subpackage  Frontend
 */
class Calendar_Frontend_iMIP
{
    /**
     * auto process given iMIP component 
     * 
     * @TODO autodelete REFRESH mails
     * 
     * @param  Calendar_Model_iMIP $_iMIP
     */
    public function autoProcess($_iMIP)
    {
        if ($_iMIP->method == Calendar_Model_iMIP::METHOD_COUNTER) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->DEBUG(__METHOD__ . '::' . __LINE__ . " skip auto processing of iMIP component with COUNTER method -> must always be processed manually");
            return;
        }
        
        $exitingEvent = Calendar_Controller_MSEventFacade::getInstance()->lookupExistingEvent($_iMIP->getEvent());
        
        if (! $exitingEvent) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->DEBUG(__METHOD__ . '::' . __LINE__ . " skip auto processing of iMIP component whose event is not in our db yet");
            return;
        }
        
        // update existing event details _WITHOUT_ status updates
        return $this->_process($_iMIP, $exitingEvent);
    }
    
    /**
     * manual process iMIP component and optionally set status
     * 
     * @param  Calendar_Model_iMIP   $_iMIP
     * @param  string                $_status
     */
    public function process($_iMIP, $_status = NULL)
    {
        // client spoofing protection
        $iMIP = Felamimail_Controller_Message::getInstance()->getiMIP($_iMIP->getId());
        
        $exitingEvent = Calendar_Controller_MSEventFacade::getInstance()->lookupExistingEvent($iMIP->getEvent());
        return $this->_process($_iMIP, $exitingEvent, $_status);
    }
    
    /**
     * prepares iMIP component for client
     * 
     * @TODO  move to Calendar_Frontend_Json / Model?
     *  
     * @param  Calendar_Model_iMIP $_iMIP
     * @return Calendar_Model_iMIP
     */
    public function prepareComponent($_iMIP)
    {
        Calendar_Model_Attender::resolveAttendee($_iMIP->event->attendee);
        Tinebase_Model_Container::resolveContainer($_iMIP->event);
        
        return $_iMIP;
    }
    
    /**
     * assemble an iMIP component in the notification flow
     * 
     * @todo implement
     */
    public function assembleComponent()
    {
        // cancel normal vs. recur instance
    }
    
    /**
     * process iMIP component and optionally set status
     * 
     * @param  Calendar_Model_iMIP   $_iMIP
     * @param  Calendar_Model_Event  $_event
     * @param  string                $_status
     * @return mixed
     * 
     * @todo what to do with obsolete check?
     * @todo call process method even if preconditions fail? 
     */
    protected function _process($_iMIP, $_existingEvent, $_status = NULL)
    {
        $method                  = ucfirst(strtolower($_iMIP->method));
        $processMethodName       = '_process'   . $method;
        $preconditionMethodName  = '_check'     . $method . 'Preconditions';
        
        if (! method_exists($this, $processMethodName)) {
            throw new Tinebase_Exception_UnexpectedValue("Method {$_iMIP->method} not supported");
        }
        
        if (method_exists($this, $preconditionMethodName)) {
            $preconditionCheckSuccessful = $this->{$preconditionMethodName}($_iMIP, $_existingEvent, $_status);
        } else {
            $preconditionCheckSuccessful = TRUE;
            if (Tinebase_Core::isLogLevel(Zend_Log::NOTICE)) Tinebase_Core::getLogger()->notice(__METHOD__ . '::' . __LINE__ . " No preconditions check fn found for method " . $method);
        }
        
        if ($preconditionCheckSuccessful) {
            $result = $this->{$processMethodName}($_iMIP, $_existingEvent, $_status);
        } else {
            $result = FALSE;
        }
        
        return $result;
        
        // not adequate for all methods
//         if ($_existingEvent && ! $_iMIP->obsoletes($_existingEvent->getEvent())) {
//             if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->DEBUG(__METHOD__ . '::' . __LINE__ . " skip processing of an old iMIP component");
//             return;
//         }
    }
    
    /**
     * publish precondition
     * 
     * @param  Calendar_Model_iMIP   $_iMIP
     * @param  Calendar_Model_Event  $_existingEvent
     * @return boolean
     */
    protected function _checkPublishPreconditions($_iMIP, $_existingEvent)
    {
        $_iMIP->addFailedPrecondition(Calendar_Model_iMIP::PRECONDITION_SUPPORTED, 'processing published events is not supported yet');
        
        return FALSE;
    }
    
    /**
     * add/update event (if outdated) / no status stuff / DANGER of duplicate UIDs
     * -  no notifications!
     * 
     * @param  Calendar_Model_iMIP   $_iMIP
     * @param  Calendar_Model_Event  $_existingEvent
     * 
     * @todo implement
     */
    protected function _processPublish($_iMIP, $_existingEvent)
    {
        throw new Tinebase_Exception_NotImplemented('processing published events is not supported yet');
    }
    
    /**
     * request precondition
     * 
     * @param  Calendar_Model_iMIP   $_iMIP
     * @param  Calendar_Model_Event  $_existingEvent
     * @return boolean
     */
    protected function _checkRequestPreconditions($_iMIP, $_existingEvent)
    {
        $result = $this->_assertAttender($_iMIP, $_existingEvent, TRUE, FALSE);
        $result = ($this->_assertOrganizer($_iMIP, $_existingEvent, TRUE, TRUE) && $result);
        
        return $result;
    }
    
    /**
    * returns and optionally asserts own attendee record
    *
    * @param  Calendar_Model_iMIP   $_iMIP
    * @param  string                $_status
    * @param  boolean               $_assertExistence
    * @param  boolean               $_assertOriginator
    * @return boolean
    */
    protected function _assertAttender($_iMIP, $_existingEvent, $_assertExistence, $_assertOriginator)
    {
        $result = TRUE;
        
        $ownAttender = Calendar_Model_Attender::getOwnAttender($_existingEvent ? $_existingEvent->attendee : $_iMIP->getEvent()->attendee);
        if ($_assertExistence && ! $ownAttender) {
            $_iMIP->addFailedPrecondition(Calendar_Model_iMIP::PRECONDITION_SUPPORTED, "processing {$_iMIP->method} for non attendee is not supported");
            return FALSE;
        }
        
        if ($_assertOriginator) {
            $result = $this->_assertOriginator($_iMIP, $ownAttender->getResolvedUser(), 'own attendee');
        }
        
        return $result;
    }
    
    /**
     * assert originator
     * 
     * @param Calendar_Model_iMIP $_iMIP
     * @param Addressbook_Model_Contact $_contact
     */
    protected function _assertOriginator(Calendar_Model_iMIP $_iMIP, Addressbook_Model_Contact $_contact, $_who)
    {
        $contactEmails = array($_contact->email, $_contact->email_home);
        if(! in_array($_iMIP->originator, $contactEmails)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->DEBUG(__METHOD__ . '::' . __LINE__
            . ' originator ' . $_iMIP->originator . ' ! in_array() '. print_r($contactEmails, TRUE));
        
            $_iMIP->addFailedPrecondition(Calendar_Model_iMIP::PRECONDITION_ORIGINATOR, $_who . " must be the same as originator of iMIP -> spoofing attempt?");
            return FALSE;
        } else {
            return TRUE;
        }
    }
    
    /**
    * returns and optionally asserts own attendee record
    *
    * @param  Calendar_Model_iMIP   $_iMIP
    * @param  string                $_status
    * @param  bool                  $_assertExistence
    * @param  bool                  $_assertOriginator
    * @return Addressbook_Model_Contact
    * @throws Calendar_Exception_iMIP
    */
    protected function _assertOrganizer($_iMIP, $_existingEvent, $_assertExistence, $_assertOriginator)
    {
        $organizer = $_existingEvent ? $_existingEvent->resolveOrganizer() : $_iMIP->getEvent()->resolveOrganizer();
        if ($_assertExistence && ! $organizer) {
            $_iMIP->addFailedPrecondition(Calendar_Model_iMIP::PRECONDITION_ORGANIZER, "processing {$_iMIP->method} without organizer is not possible");
            return FALSE;
        }
        
        if ($_assertOriginator) {
            $result = $this->_assertOriginator($_iMIP, $organizer, 'organizer');
        }
    
        return $organizer;
    }
    
    /**
     * process request
     * 
     * @param  Calendar_Model_iMIP   $_iMIP
     * @param  Calendar_Model_Event  $_existingEvent
     * @param  string                $_status
     * @throws Tinebase_Exception_NotImplemented
     */
    protected function _processRequest($_iMIP, $_existingEvent, $_status)
    {
        $ownAttender = Calendar_Model_Attender::getOwnAttender($_existingEvent ? $_existingEvent->attendee : $_iMIP->getEvent()->attendee);
        $organizer = $_existingEvent ? $_existingEvent->resolveOrganizer() : $_iMIP->getEvent()->resolveOrganizer();
        
        // internal organizer:
        //  - event is up to date
        //  - status change could also be done by calendar method
        //  - normal notifications
        if ($_existingEvent && $organizer->account_id) {
            if ($_status && $_status != $ownAttender->status) {
                $ownAttender->status = $_status;
                Calendar_Controller_Event::getInstance()->attenderStatusUpdate($_existingEvent, $ownAttender, $ownAttender->status_authkey);
            }
        }
        
        // external organizer:
        //  - update (might have acl problems)
        //  - set status
        //  - send reply to organizer
        else {
            throw new Tinebase_Exception_NotImplemented('processing external requests is not supported yet');
        }
    }
    
    /**
     * process reply
     * 
     * @param  Calendar_Model_iMIP   $_iMIP
     * @param  Calendar_Model_Event  $_existingEvent
     */
    protected function _processReply($_iMIP, $_existingEvent)
    {
        if (! $_existingEvent) {
            throw new Calendar_Exception_iMIP('cannot process REPLY to non existent/invisible event');
        }
        
        if ($_iMIP->getEvent()->obsoletes($_existingEvent)) {
            // old iMIP message
            return;
        }
        
        $iMIPAttenderIdx = array_search($_iMIP->originator, $_iMIP->getEvent()->attendee->getEmail());
        if ($iMIPAttenderIdx === FALSE) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->DEBUG(__METHOD__ . '::' . __LINE__
                . ' originator ' . $_iMIP->originator . ' != '. $_existingEvent->attendee->getEmail());
            throw new Calendar_Exception_iMIP('originator is not attendee in iMIP transaction-> spoofing attempt?');
        }
        $iMIPAttender = $_iMIP->getEvent()->attendee[$iMIPAttenderIdx];
        
        $existingAttenderIdx = array_search($_iMIP->originator, $_existingEvent->attendee->getEmail());
        if ($existingAttenderIdx === FALSE) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->DEBUG(__METHOD__ . '::' . __LINE__
                . ' originator ' . $_iMIP->originator . ' != '. $_existingEvent->attendee->getEmail());
            throw new Calendar_Exception_iMIP('originator is not attendee in existing event -> party crusher?');
        }
        $existingAttender = $_existingEvent->attendee[$existingAttenderIdx];
        
        $organizer = $this->_getOrganizer($_iMIP, $_existingEvent, TRUE, FALSE);
        if (! $organizer->account_id) {
            throw new Calendar_Exception_iMIP('cannot process reply to externals organizers event');
        }
        
        // status update 
        
        // some attender replied to my request (I'm Organizer) -> update status (seq++) / send notifications!
    }
    
    /**
    * process add
    *
    * @param  Calendar_Model_iMIP   $_iMIP
    * @param  Calendar_Model_Event  $_existingEvent
    * 
    * @todo implement
    */
    protected function _processAdd($_iMIP, $_existingEvent)
    {
        // organizer added a meeting/recurrance to an existing event -> update event
        // internal organizer:
        //  - event is up to date nothing to do
        // external organizer:
        //  - update event
        //  - the iMIP is already the notification mail!
        throw new Tinebase_Exception_NotImplemented('processing add requests is not supported yet');
    }
    
    /**
    * process cancel
    *
    * @param  Calendar_Model_iMIP   $_iMIP
    * @param  Calendar_Model_Event  $_existingEvent
    * 
    * @todo implement
    */
    protected function _processCancel($_iMIP, $_existingEvent)
    {
        // organizer caneled meeting/recurrence of an existing event -> update event
        // the iMIP is already the notification mail!
        throw new Tinebase_Exception_NotImplemented('processing CANCEL is not supported yet');
    }
    
    /**
    * process refresh
    *
    * @param  Calendar_Model_iMIP   $_iMIP
    * @param  Calendar_Model_Event  $_existingEvent
    *
    * @todo implement
    */
    protected function _processRefresh($_iMIP, $_existingEvent)
    {
        // always internal organizer
        //  - send message
        //  - mark iMIP message ANSWERED
        throw new Tinebase_Exception_NotImplemented('processing REFRESH is not supported yet');
    }
    
    /**
    * process counter
    *
    * @param  Calendar_Model_iMIP   $_iMIP
    * @param  Calendar_Model_Event  $_existingEvent
    *
    * @todo implement
    */
    protected function _processCounter($_iMIP, $_existingEvent)
    {
        // some attendee suggests to change the event
        // status: ACCEPT => update event, send notifications to all
        // status: DECLINE => send DECLINECOUNTER to originator
        // mark message ANSWERED
        throw new Tinebase_Exception_NotImplemented('processing COUNTER is not supported yet');
    }
    
    /**
    * process declinecounter
    *
    * @param  Calendar_Model_iMIP   $_iMIP
    * @param  Calendar_Model_Event  $_existingEvent
    *
    * @todo implement
    */
    protected function _processDeclinecounter($_iMIP, $_existingEvent)
    {
        // organizer declined my counter request of an existing event -> update event
        throw new Tinebase_Exception_NotImplemented('processing DECLINECOUNTER is not supported yet');
    }
    
    /**
     * returns and optionally asserts own attendee record
     * 
     * @param  Calendar_Model_iMIP   $_iMIP
     * @param  string                $_status
     * @param  bool                  $_assertExistence
     * @param  bool                  $_assertOriginator
     * @return Addressbook_Model_Contact
     * @throws Calendar_Exception_iMIP
     * 
     * @deprecated
     */
    protected function _getOrganizer($_iMIP, $_existingEvent, $_assertExistence, $_assertOriginator)
    {
        $organizer = $_existingEvent ? $_existingEvent->resolveOrganizer() : $_iMIP->getEvent()->resolveOrganizer();
        if ($_assertExistence && ! $organizer) {
            throw new Calendar_Exception_iMIP("processing {$_iMIP->method} without organizer is not possible");
        }
        
        $organizerEmails =  array($organizer->email, $organizer->email_home);
        if ($_assertOriginator && ! in_array($_iMIP->originator, $organizerEmails)) {
            if (Tinebase_Core::isLogLevel(Zend_Log::DEBUG)) Tinebase_Core::getLogger()->DEBUG(__METHOD__ . '::' . __LINE__
                . ' originator ' . $_iMIP->originator . ' ! in_array() '. print_r($organizerEmails, TRUE));
            
            throw new Calendar_Exception_iMIP("organizer of event must be the same as originator of iMIP -> spoofing attempt?");
        }
        
        return $organizer;
    }
}
