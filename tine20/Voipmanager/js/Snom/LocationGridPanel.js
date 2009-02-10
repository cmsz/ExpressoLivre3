/**
 * Tine 2.0
 * 
 * @package     Voipmanager
 * @license     http://www.gnu.org/licenses/agpl.html AGPL Version 3
 * @author      Thomas Wadewitz <t.wadewitz@metaways.de>
 * @copyright   Copyright (c) 2007-2009 Metaways Infosystems GmbH (http://www.metaways.de)
 * @version     $Id$
 *
 */
 
Ext.namespace('Tine.Voipmanager');

/**
 * Context grid panel
 */
Tine.Voipmanager.SnomLocationGridPanel = Ext.extend(Tine.Tinebase.widgets.app.GridPanel, {
    // model generics
    recordClass: Tine.Voipmanager.Model.SnomLocation,
    evalGrants: false,
    
    // grid specific
    defaultSortInfo: {field: 'description', direction: 'ASC'},
    gridConfig: {
        loadMask: true,
        autoExpandColumn: 'description'
    },
    
    initComponent: function() {
    
        this.recordProxy = Tine.Voipmanager.SnomLocationBackend;
                
        this.gridConfig.columns = this.getColumns();
        this.initFilterToolbar();
        this.actionToolbarItems = this.getToolbarItems();
        //this.initDetailsPanel();
        
        this.plugins = this.plugins || [];
        this.plugins.push(this.filterToolbar);
        
        Tine.Voipmanager.SnomLocationGridPanel.superclass.initComponent.call(this);
    },
    
    /**
     * initialises filter toolbar
     */
    initFilterToolbar: function() {
        this.filterToolbar = new Tine.widgets.grid.FilterToolbar({
            filterModels: [
                {label: this.app.i18n._('Location'),    field: 'query',    operators: ['contains']}
             ],
             defaultFilter: 'query',
             filters: []
        });
    },   
    
    /**
     * returns cm
     * @private
     * 
     */
    getColumns: function(){
        return [{ 
            	id: 'firmware_interval', 
            	header: this.app.i18n._('FW Interval'), 
            	dataIndex: 'firmware_interval', 
            	width: 10, 
            	hidden: true 
            },{ 
            	id: 'update_policy', 
            	header: this.app.i18n._('Update Policy'), 
            	dataIndex: 'update_policy', 
            	width: 30, 
            	hidden: true 
            },{ 
            	id: 'registrar', 
            	header: this.app.i18n._('Registrar'), 
            	dataIndex: 'registrar', 
            	width: 100, 
            	hidden: true  
            },{ 
            	id: 'admin_mode', 
            	header: this.app.i18n._('Admin Mode'), 
            	dataIndex: 'admin_mode', 
            	width: 10, 
            	hidden: true 
            },{ 
            	id: 'ntp_server', 
            	header: this.app.i18n._('NTP Server'), 
            	dataIndex: 'ntp_server', 
            	width: 50, 
            	hidden: true  
            },{ 
            	id: 'webserver_type', 
            	header: this.app.i18n._('Webserver Type'), 
            	dataIndex: 'webserver_type', 
            	width: 30, 
            	hidden: true 
            },{ 
            	id: 'https_port', 
            	header: this.app.i18n._('HTTPS Port'), 
            	dataIndex: 'https_port', 
            	width: 10, 
            	hidden: true  
            },{ 
            	id: 'http_user', 
            	header: this.app.i18n._('HTTP User'), 
            	dataIndex: 'http_user', 
            	width: 15, 
            	hidden: true 
            },{ 
            	id: 'id', 
            	header: this.app.i18n._('id'), 
            	dataIndex: 'id', 
            	width: 10, 
            	hidden: true 
            },{
                id: 'name',
                header: this.app.i18n._('Name'),
                dataIndex: 'name',
                width: 80
            },{ 
            	id: 'description', 
            	header: this.app.i18n._('Description'), 
            	dataIndex: 'description', 
            	width: 350 
            },{ 
            	id: 'filter_registrar', 
            	header: this.app.i18n._('Filter Registrar'), 
            	dataIndex: 'filter_registrar', 
            	width: 10, 
            	hidden: true 
            },{ 
            	id: 'callpickup_dialoginfo', 
            	header: this.app.i18n._('CP Dialoginfo'), 
            	dataIndex: 'callpickup_dialoginfo', 
            	width: 10, 
            	hidden: true 
            },{ 
            	id: 'pickup_indication',
            	header: this.app.i18n._('Pickup Indic.'), 
            	dataIndex: 'pickup_indication', 
            	width: 10, 
            	hidden: true 
            }];
    },
    
    initDetailsPanel: function() { return false; },
    
    /**
     * return additional tb items
     * 
     * @todo add duplicate button
     * @todo move export buttons to single menu/split button
     */
    getToolbarItems: function(){
       
        return [

        ];
    } 
});