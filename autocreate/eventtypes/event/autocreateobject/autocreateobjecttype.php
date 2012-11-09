<?php
//
// Created on: <2011-03-20 17:26:00>
//
// SOFTWARE NAME: autocreateobject extension for eZ Publish
// SOFTWARE RELEASE: 1.1.0
// COPYRIGHT NOTICE: Copyright (C) 1999-2010 eZ Systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
// This program is free software; you can redistribute it and/or
// modify it under the terms of version 2.0 of the GNU General
// Public License as published by the Free Software Foundation.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of version 2.0 of the GNU General
// Public License along with this program; if not, write to the Free
// Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
// MA 02110-1301, USA.
//
//
class autocreateObjectType extends eZWorkflowEventType
{
    const WORKFLOW_TYPE_STRING = 'autocreateobject';

    /*!
     Constructor
    */
    function autocreateObjectType()
    {
        $this->eZWorkflowEventType( autocreateObjectType::WORKFLOW_TYPE_STRING, ezi18n( 'kernel/workflow/event', "Autocreate Object" ) );
        $this->setTriggerTypes( array( 'content' => array( 'publish' => array ( 'after' ) ) ) );
    }

    function execute( $process, $event )
    {

        $ini = eZINI::instance( 'autocreateobject.ini' );
        $parameters = $process->attribute( 'parameter_list' );
        
        $createClasses = $ini->variable( "autocreateobject", "createClasses" );
        $parentNodeID = $ini->variable( "autocreateobject", "parentNode" );

        
        $object_id = $parameters["object_id"];
        $obj = eZContentObject::fetch($object_id);
        
        if ($parentNodeID == 'self')
        {
            $parentNodeID = $obj->attribute('main_node_id');
        }
        
        $parentNode = eZContentObjectTreeNode::fetch($parentNodeID);
        
        if (count($createClasses) && $parentNode )
        {
            foreach ($createClasses as $class => $name)
            {
                $result =  $this->createObject($class, $parentNodeID, $name); 
            }
        }
       
        return eZWorkflowType::STATUS_ACCEPTED;
    }
    
    
    
    function createObject($classIdentifier, $parentNodeID, $name)
    {
        $user = eZUser::currentUser();
        $Class = eZContentClass::fetchByIdentifier( $classIdentifier );
        if ( !$Class )
        {            
            eZDebug::writeError("No class with identifier $classIdentifier","classCreation");       
            return false;     
        }
        
        $contentObject = $Class->instantiate( $user->attribute('contentobject_id') );
        
        $nodeAssignment = eZNodeAssignment::create( array(
                                                     'contentobject_id' => $contentObject->attribute( 'id' ),
                                                     'contentobject_version' => $contentObject->attribute( 'current_version' ),
                                                     'parent_node' => $parentNodeID,
                                                     'is_main' => 1
                                                     )
                                                 );
        $nodeAssignment->store();
        
        $version = $contentObject->version( 1 );
        $version->setAttribute( 'modified', eZDateTime::currentTimeStamp() );
        $version->setAttribute( 'status', eZContentObjectVersion::STATUS_DRAFT );    
        $version->store();
        
        $contentObjectID = $contentObject->attribute( 'id' );
        $attributes = $contentObject->attribute( 'contentobject_attributes' );
        
        $attributes[0]->fromString($name);
        $attributes[0]->store();

        $operationResult = eZOperationHandler::execute( 'content', 'publish', array( 'object_id' => $contentObjectID,
                                                                                 'version' => 1 ) );
        
        return true;
        
    }
}

eZWorkflowEventType::registerEventType( autocreateObjectType::WORKFLOW_TYPE_STRING, "autocreateObjectType" );

?>
