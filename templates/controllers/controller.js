/*
Controller for interface "$interfaceName$" (context: "$contextName$"). Generated code, edit with care.
$if(verbose)$Generated using template: $usedTemplate$
Generated by $ampersandVersionStr$

INTERFACE "$interfaceName$" : $expAdl$ :: $source$ * $target$  ($if(!isRoot)$non-$endif$root interface)
Roles: [$roles;separator=", "$]
$endif$*/
AmpersandApp.controller('$interfaceName$Controller', function (\$scope, \$rootScope, \$route, \$routeParams, Restangular, \$location, \$timeout, \$localStorage) {
    let resourceType = '$source$';
    
    if(resourceType == 'SESSION') resourceId = \$scope.\$sessionStorage.session.id;
    else if (resourceType == 'ONE') resourceId = '1';
	else resourceId = \$routeParams.resourceId;
    
    \$scope.navLabel = \$route.current.\$\$route.interfaceLabel; // interfaceLabel is specified in RouteProvider.js
	\$scope.updatedResources = []; // contains list with updated resource objects in this interface. Used to check if there are uncommmitted changes
    
	/**********************************************************************************************
	 * 
	 *	GET INTERFACE
	 * 
	 *********************************************************************************************/
	
	// Set requested resource
	if(\$routeParams['new'] && '$source$' == '$target$') resourceId = '_NEW'; // Set resourceId to special '_NEW' value in case new resource must be created 
	\$scope.resource = Restangular.one('resources').one(resourceType, resourceId); // BaseURL to the API is already configured in AmpersandApp.js (i.e. 'http://pathToApp/api/v1/')
	\$scope.resource['_path_'] = '/resources/' + resourceType + '/' + resourceId;
	\$scope.resource['_ifcEntryResource_'] = true;
    \$scope.resource.$interfaceName$ = []; // initialize resource interface object
    
    // watch and update navLabel (e.g. used by breadcrumb)
    \$scope.\$watchCollection('resource.$interfaceName$', function() {
		if(resourceId != \$scope.\$sessionStorage.session.id){
            \$scope.navLabel = (\$scope.resource.$interfaceName$[0] || {})._label_ ? \$scope.resource.$interfaceName$[0]._label_ : '...';
        }
	});
	
	// Create new resource and add data to \$scope.resource['$interfaceName$']
	if(\$routeParams['new']){
		
		\$scope.createResource(\$scope.resource, '$interfaceName$')
			.then(function(data){
				\$location.url('/$interfaceName$/'+ data.content['_id_'], false);
			},function(reason){
				console.log('Failed to create resource: ' + reason);
			});
	
	// Get resource and add data to \$scope.resource['$interfaceName$']
	}else{
		var forceList = true;
		\$scope.getResource(\$scope.resource, '$interfaceName$', forceList)
			.then(function(data){
				\$scope.\$broadcast('interfaceDataReceived', data);
			},function(reason){
				console.log('Failed to get resource: ' + reason);
			});	
	}
	
	// Function to change location to create a new resource
	\$scope.newResource = function(){
		\$location.url('/$interfaceName$?new');
	};
	
	/**********************************************************************************************
	 * 
	 *	CRUD functions on resources
	 * 
	 *********************************************************************************************/
	
	// Function to delete a resource
	\$scope.deleteResource = function (obj, ifc, resource, requestType){
		requestType = requestType || \$rootScope.defaultRequestType; // set requestType. This does not work if you want to pass in a falsey value i.e. false, null, undefined, 0 or ""
		
		if(confirm('Are you sure?')){
			if(!Array.isArray(resource['_loading_'])) resource['_loading_'] = new Array();
			resource['_loading_'].push( // shows loading indicator
				Restangular.one(resource['_path_'])
					.remove({requestType : requestType})
					.then(function(data){
						// Remove resource from collection/list
						index = _getListIndex(obj[ifc], '_id_', resource['_id_']);
						obj[ifc].splice(index, 1);
						
						// Update visual feedback (notifications and buttons)
						\$rootScope.updateNotifications(data.notifications);
					})
			);
		}
	};
	
	// Function to patch only the changed attributes of a Resource
	\$scope.patchResource = function(resource, patches, requestType){		
        $if(verbose)$console.log(patches);$endif$
		if(typeof resource['_patchesCache_'] === 'undefined') resource['_patchesCache_'] = []; // new array
		resource['_patchesCache_'] = resource['_patchesCache_'].concat(patches); // add new patches
		
		\$scope.saveResource(resource, requestType);
	};
	
	// Function to send all patches
	\$scope.saveResource = function(resource, requestType, save){
		requestType = requestType || \$rootScope.defaultRequestType; // set requestType. This does not work if you want to pass in a falsey value i.e. false, null, undefined, 0 or ""
        save = save || \$localStorage.switchAutoSave;
        
        // Add resource to \$scope.updatedResources
        if(\$scope.updatedResources.indexOf(resource) === -1) \$scope.updatedResources.push(resource);
		
        if(save){
    		if(!Array.isArray(resource['_loading_'])) resource['_loading_'] = new Array();
    		resource['_loading_'].push( // shows loading indicator
    			Restangular.one(resource['_path_'])
    				.patch(resource['_patchesCache_'], {'requestType' : requestType, 'topLevelIfc' : '$interfaceName$'})
    				.then(function(data) {
    					// Update resource data
    					if(resource['_ifcEntryResource_']){
    						resource['$interfaceName$'] = data.content;
    						//tlResource = resource;
    					}
    					else resource = \$.extend(resource, data.content);
    					
    					// Update visual feedback (notifications and buttons)
    					\$rootScope.updateNotifications(data.notifications);
    					processResponse(resource, data.invariantRulesHold, data.requestType);					
    				})
    		);
        }else{
            processResponse(resource, true, 'feedback');
        }
	};
	
	/**********************************************************************************************
	 * 
	 *	Edit functions on scalar
	 * 
	 *********************************************************************************************/
	
	// Function to save item (non-array)
	\$scope.saveItem = function(resource, ifc, patchResource){		
		if(typeof resource[ifc] === 'undefined' || resource[ifc] === '') value = null;
		else value = resource[ifc];
		
		// Construct path
		pathLength = patchResource['_path_'].length;
		path = resource['_path_'].substring(pathLength) + '/' + ifc;
		
		// Construct patch
		patches = [{ op : 'replace', path : path, value : value}];
		
		// Patch!
		\$scope.patchResource(patchResource, patches);
	};
	
	// Function to add item to array
	\$scope.addItem = function(resource, ifc, selected, patchResource){		
		if(typeof selected.value === 'undefined'){
			console.log('Value undefined');
		}else if(selected.value !== ''){
			// Adapt in js model
			if(typeof resource[ifc] === 'undefined' || resource[ifc] === null) resource[ifc] = [];
			resource[ifc].push(selected.value);
			
			// Construct path
			pathLength = patchResource['_path_'].length;
			path = resource['_path_'].substring(pathLength) + '/' + ifc;
			
			// Construct patch
			patches = [{ op : 'add', path : path, value : selected.value}];
			
			// Reset selected value
			selected.value = '';			
			
			// Patch!
			\$scope.patchResource(patchResource, patches);
		}else{
			console.log('Empty value selected');
		}
	};
	
	// Function to remove item from array
	\$scope.removeItem = function(resource, ifc, key, patchResource){		
		// Adapt js model
		value = resource[ifc][key];
		resource[ifc].splice(key, 1);
		
		// Construct path
		pathLength = patchResource['_path_'].length;
		path = resource['_path_'].substring(pathLength) + '/' + ifc;
		
		// Construct patch
		patches = [{ op : 'remove', path : path, value: value}];
		
		// Patch!
		\$scope.patchResource(patchResource, patches);
	};
	
	
	/**********************************************************************************************
	 * 
	 *	Edit functions on objects
	 * 
	 *********************************************************************************************/
	
	// Function to add an object to a certain interface (array) of a resource
	\$scope.addObject = function(resource, ifc, obj, patchResource){
		// If patchResource is undefined, the patchResource equals the resource
		if(typeof patchResource === 'undefined'){
			patchResource = resource
		}
		
		if(typeof obj['_id_'] === 'undefined' || obj['_id_'] == ''){
			console.log('Selected object id is undefined');
		}else{
            try {
                obj = obj.plain(); // plain is Restangular function
            }catch(e){} // when plain() does not exists (i.e. object is not restangular object)
            
            // Adapt js model
            if(resource[ifc] === null) resource[ifc] = obj;
            else if(Array.isArray(resource[ifc])) resource[ifc].push(obj);
            else console.log('Cannot add object. Resource[ifc] already set and/or not defined');
			
			// Construct path
			pathLength = patchResource['_path_'].length;
			path = resource['_path_'].substring(pathLength) + '/' + ifc;
			
			// Construct patch
			patches = [{ op : 'add', path : path, value : obj['_id_']}];
			
			// Patch!
			\$scope.patchResource(patchResource, patches);
		}
	};
	
	// Function to remove an object from a certain interface (array) of a resource
	\$scope.removeObject = function(resource, ifc, key, patchResource){		
		// Adapt js model
		id = resource[ifc][key]['_id_'];
		resource[ifc].splice(key, 1);
		
		// Construct path
		pathLength = patchResource['_path_'].length;
		path = resource['_path_'].substring(pathLength) + '/' + ifc + '/' + id;
		
		// Construct patch
		patches = [{ op : 'remove', path : path}];
		
		// Patch!
		\$scope.patchResource(patchResource, patches);
	};
	
	// Typeahead functionality
	\$scope.typeahead = {}; // an empty object for typeahead
	\$scope.getTypeahead = function(resourceType){
		// Only if not yet set
		if(typeof \$scope.typeahead[resourceType] === 'undefined'){
			\$scope.typeahead[resourceType] = Restangular.all('resources/' + resourceType).getList().\$object;
		}
	};
	
	/**********************************************************************************************
	 *
	 * Transaction status function
	 *
	 **********************************************************************************************/
	
	// TODO: change check on showSaveButton to check for unsaved patches
	\$scope.\$on("\$locationChangeStart", function(event, next, current){
		$if(verbose)$console.log("location changing to:" + next);$endif$
		checkRequired = \$scope.updatedResources.reduce(function(prev, item, index, arr){
            return prev || item['_patchesCache_'].length;
        }, false);
		
		if(checkRequired){ // if checkRequired (see above)
			confirmed = confirm("You have unsaved edits. Do you wish to leave?");
			if (event && !confirmed) event.preventDefault();
		}
	});
	
	/**********************************************************************************************
	 *
	 * Helper functions
	 *
	 **********************************************************************************************/
	
	function _getListIndex(list, attr, val){
		var index;
		list.some(function(item, idx){
			return (item[attr] === val) && (index = idx)
		});
		return index;
	};
	
	// Process response
	function processResponse(resource, invariantRulesHold, requestType){
		
		if(invariantRulesHold && requestType == 'feedback'){
			resource['_showButtons_'] = {'save' : true, 'cancel' : true};
			setResourceStatus(resource, 'warning');
			
		}else if(invariantRulesHold && requestType == 'promise'){
			resource['_showButtons_'] = {'save' : false, 'cancel' : false};
			resource['_patchesCache_'] = []; // empty patches cache
			
			setResourceStatus(resource, 'success'); // Set status to success
			\$timeout(function(){ // After 3 seconds, reset status to default
				setResourceStatus(resource, 'default');
			}, 3000);
		}else{
			resource['_showButtons_'] = {'save' : false, 'cancel' : true};
			setResourceStatus(resource, 'danger');
		}
	};
	
	function setResourceStatus(resource, status){
		// Reset all status properties
		resource['_status_'] = { 'warning' : false
							   , 'danger'  : false
							   , 'default' : false
							   , 'success' : false
							   };
		// Set new status property
		resource['_status_'][status] = true;
	};
});