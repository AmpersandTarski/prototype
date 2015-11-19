/*
Controller for interface "$interfaceName$" (context: "$contextName$"). Generated code, edit with care.
$if(verbose)$Generated using template: $usedTemplate$
Generated by $ampersandVersionStr$

INTERFACE "$interfaceName$" : $expAdl$ :: $source$ * $target$  ($if(!isRoot)$non-$endif$root interface)
Roles: [$roles;separator=", "$]
Editable relations: [$editableRelations;separator=", "$] 
$endif$*/
AmpersandApp.controller('$interfaceName$Controller', function (\$scope, \$rootScope, \$route, \$routeParams, Restangular, \$location, \$timeout, \$localStorage, \$sessionStorage) {
	\$scope.interfaceName = "$interfaceName$";
	\$scope.interfaceLabel = "$interfaceLabel$";
	\$scope.resourceLabel = ''; // label of requested interface source resource
	
	\$scope.val = {};
	\$scope.showSaveButton = {}; // initialize object for show/hide save button
	\$scope.showCancelButton = {}; // initialize object for show/hide cancel button
	\$scope.resourceStatus = {}; // initialize object for resource status colors
	\$scope.loadingInterface = []; // array for promises, used by angular-busy module (loading indicator)
	\$scope.loadingResources = {}; // initialize object for promises, used by angular-busy module (loading indicator)
	
	\$scope.\$localStorage = \$localStorage;
	\$scope.\$sessionStorage = \$sessionStorage;
	
	if(typeof \$routeParams.resourceId != 'undefined') srcAtomId = \$routeParams.resourceId;
	else srcAtomId = \$sessionStorage.session.id;
	
	// BaseURL to the API is already configured in AmpersandApp.js (i.e. 'http://pathToApp/api/v1/')
	\$scope.srcAtom = Restangular.one('resource/$source$', srcAtomId);
	\$scope.val['$interfaceName$'] = new Array();
	
	$if(verbose)$// Only insert code below if interface is allowed to create new atoms. This is not specified in interfaces yet, so add by default
	$endif$if(\$routeParams['new']){
		\$scope.loadingInterface.push(
			\$scope.srcAtom.all('$interfaceName$').post({})
				.then(function(data) { // POST
				\$rootScope.updateNotifications(data.notifications);
				\$scope.val['$interfaceName$'].push(Restangular.restangularizeElement(\$scope.srcAtom, data.content, '$interfaceName$')); // Add to collection
				showHideButtons(data.invariantRulesHold, data.requestType, data.content.id);
			})
		);
	}else{
	    \$scope.loadingInterface.push(
	    	\$scope.srcAtom.all('$interfaceName$').getList().then(function(data){
	    		if(\$.isEmptyObject(data.plain())){
	    			\$rootScope.addInfo('No results found');
	    		}else{
	    			\$scope.val['$interfaceName$'] = data;
	    			\$scope.resourceLabel = \$scope.val['$interfaceName$'][0]['@label'];
	    		}
	    	})
	    );
    }
	
	\$scope.\$on("\$locationChangeStart", function(event, next, current){
		$if(verbose)$console.log("location changing to:" + next);$endif$
		checkRequired = false; // default
		for(var item in \$scope.showSaveButton) { // iterate over all properties (resourceIds) in showSaveButton object
			if(\$scope.showSaveButton.hasOwnProperty( item ) ) { // only checks its own properties, not inherited ones
				if(\$scope.showSaveButton[item] == true) checkRequired = true; // if item is not saved, checkRequired before location change
			}
		}
		
		if(checkRequired){ // if checkRequired (see above)
			confirmed = confirm("You have unsaved edits. Do you wish to leave?");
			if (event && !confirmed) event.preventDefault();
		}
	});
	
	$if(verbose)$// The functions below are only necessary if the interface allows to add/delete the complete atom,
	// but since this cannot be specified yet in Ampersand we always include it.
	
	$endif$// Function to create a new Resource
	\$scope.newResource = function(){
		\$location.url('/$interfaceName$?new');
	}
	
	// Function to add a new Resource to the colletion
	\$scope.addNewResource = function (prepend){
		if(prepend === 'undefined') var prepend = false;
		
		\$scope.loadingResources['_new_'] = new Array();
		\$scope.loadingResources['_new_'].push(
			\$scope.srcAtom.all('$interfaceName$')
				.post({})
				.then(function(data){ // POST
					\$rootScope.updateNotifications(data.notifications);
					if(prepend) \$scope.val['$interfaceName$'].unshift(Restangular.restangularizeElement(\$scope.srcAtom, data.content, '$interfaceName$')); // Add to collection
					else \$scope.val['$interfaceName$'].push(Restangular.restangularizeElement(\$scope.srcAtom, data.content, '$interfaceName$')); // Add to collection
					showHideButtons(data.invariantRulesHold, data.requestType, data.content.id);
					\$scope.loadingResources['_new_'] = new Array(); // empty arr
				})
		);
	}
	
	// Delete function to delete a complete Resource
	\$scope.deleteResource = function (resourceId){
		if(confirm('Are you sure?')){
			var resourceIndex = _getResourceIndex(resourceId, \$scope.val['$interfaceName$']);
			
			// myPromise is used for busy indicator
			\$scope.loadingResources[resourceId] = new Array();
			\$scope.loadingResources[resourceId].push(
				\$scope.val['$interfaceName$'][resourceIndex]
					.remove({ 'requestType' : 'promise'})
					.then(function(data){
						\$rootScope.updateNotifications(data.notifications);
						\$scope.val['$interfaceName$'].splice(resourceIndex, 1); // remove from array
					})
			);
		}
	}
	
	// Put function to update a Resource
	\$scope.put = function(resourceId, requestType){
		var resourceIndex = _getResourceIndex(resourceId, \$scope.val['$interfaceName$']);
		requestType = requestType || \$rootScope.defaultRequestType; // set requestType. This does not work if you want to pass in a falsey value i.e. false, null, undefined, 0 or ""
		
		// myPromise is used for busy indicator
		\$scope.loadingResources[resourceId] = new Array();
	
		var location = \$location.search();
		// if ?new => POST
		if(location['new']){
			\$scope.loadingResources[resourceId].push(
				\$scope.srcAtom.all('$interfaceName$')
				.post(\$scope.val['$interfaceName$'][resourceIndex].plain(), {'requestType' : requestType})
				.then(function(data) { // POST
					\$rootScope.updateNotifications(data.notifications);
					\$scope.val['$interfaceName$'][resourceIndex] = \$.extend(\$scope.val['$interfaceName$'][resourceIndex], data.content);
					showHideButtons(data.invariantRulesHold, data.requestType, data.content.id);
					
					if(data.invariantRulesHold && data.requestType == 'promise'){
						// Resource is posted, change url
						\$location.url('/$interfaceName$/' + data.content.id);
					}
				})
			);
		// else => PUT
		}else{
			\$scope.loadingResources[resourceId].push( \$scope.val['$interfaceName$'][resourceIndex]
				.put({'requestType' : requestType})
				.then(function(data) {
					\$rootScope.updateNotifications(data.notifications);
					\$scope.val['$interfaceName$'][resourceIndex] = \$.extend(\$scope.val['$interfaceName$'][resourceIndex], data.content);
					showHideButtons(data.invariantRulesHold, data.requestType, resourceId);
				})
			);
		}
	}
	
	// Function to cancel edits and reset resource data
	\$scope.cancel = function(resourceId){
		var resourceIndex = _getResourceIndex(resourceId, \$scope.val['$interfaceName$']);
		
		// myPromise is used for busy indicator
		\$scope.loadingResources[resourceId] = new Array();
		\$scope.loadingResources[resourceId].push(
			\$scope.val['$interfaceName$'][resourceIndex]
				.get()
				.then(function(data){
					\$rootScope.getNotifications();
					\$scope.val['$interfaceName$'][resourceIndex] = \$.extend(\$scope.val['$interfaceName$'][resourceIndex], data.plain());
					
					setResourceStatus(resourceId, 'default');
					\$scope.showSaveButton[resourceId] = false;
					\$scope.showCancelButton[resourceId] = false;
				})
		);
	}
	
	$if(containsEditable)$$if(verbose)$// The interface contains at least 1 editable relation
	$endif$// Function to patch only the changed attributes of a Resource
	\$scope.patch = function(patches, resourceId, requestType){
		var resourceIndex = _getResourceIndex(resourceId, \$scope.val['$interfaceName$']);
		
		requestType = requestType || \$rootScope.defaultRequestType; // set requestType. This does not work if you want to pass in a falsey value i.e. false, null, undefined, 0 or ""
		
		// myPromise is used for busy indicator
		\$scope.loadingResources[resourceId] = new Array();
	
		\$scope.loadingResources[resourceId].push( \$scope.val['$interfaceName$'][resourceIndex]
			.patch(patches, {'requestType' : requestType})
			.then(function(data) {
				\$rootScope.updateNotifications(data.notifications);
				\$scope.val['$interfaceName$'][resourceIndex] = \$.extend(\$scope.val['$interfaceName$'][resourceIndex], data.content);
				showHideButtons(data.invariantRulesHold, data.requestType, resourceId);
			})
		);
	}
	
	// Function to save item
	\$scope.saveItem = function(obj, property, resourceId){
		if(obj[property] == '') value = null;
		else value = obj[property];
		
		// Patch!
		patches = [{ op : 'replace', path : obj['@path'] + property, value : value}];
		console.log(patches);
		\$scope.patch(patches, resourceId);
	};
	
	// Function to add item to array of scalar
	\$scope.addItem = function(obj, property, selected, resourceId){
		if(selected.value != ''){
			// Adapt in js model
			if(obj[property] === null) obj[property] = [];
			obj[property].push(selected.value);
			
			// Construct patch(es)
			patches = [{ op : 'add', path : obj['@path'] + property, value : selected.value}];
			console.log(patches);
			
			// Reset selected value
			selected.value = '';			
			
			// Patch!
			\$scope.patch(patches, resourceId);
		}else{
			console.log('Empty value selected');
		}
	}
	
	// Function to remove item from array of scalar
	\$scope.removeItem = function(obj, property, key, resourceId){
		// Adapt js model
		value = obj[property][key];
		obj[property].splice(key, 1);
		
		// Patch!
		patches = [{ op : 'remove', path : obj['@path'] + property + '/' + value}];
		console.log(patches);
		\$scope.patch(patches, resourceId);
		
	}$else$$if(verbose)$// The interface does not contain any editable relations$endif$$endif$
	
	$if(containsEditableObjects)$$if(verbose)$// The interface contains at least 1 editable relation to a concept with representation OBJECT
	$endif$// AddObject function to add a new item (val) to a certain property (property) of an object (obj)
	// Also needed by addModal function.
	\$scope.addObject = function(obj, property, item, resourceId){
		if(item.id === undefined || item.id == ''){
			console.log('selected id is undefined');
		}else{
			// Adapt js model
			if(obj[property] === null) obj[property] = {};
			try {
				obj[property][item.id] = item.plain() // plain is Restangular function
			}catch(e){
				obj[property][item.id] = item // when plain() does not exists (i.e. item is not restangular object) 
			}
			
			// Patch!
			patches = [{ op : 'add', path : obj['@path'] + property, value : item.id}];
			console.log(patches);
			\$scope.patch(patches, resourceId);
		}
	}
	
	// RemoveObject function to remove an item (key) from list (obj[property]).
	\$scope.removeObject = function(obj, property, key, resourceId){
		// Adapt js model
		delete obj[property][key];
		
		// Patch!
		patches = [{ op : 'remove', path : obj['@path'] + property + '/' + key}];
		console.log(patches);
		\$scope.patch(patches, resourceId);
	}
	
	// Typeahead functionality
	\$scope.typeahead = {}; // an empty object for typeahead
	
	\$scope.typeaheadOnSelect = function (\$item, \$model, \$label, obj, property, resourceId){
		\$scope.addObject(obj, property, \$item, resourceId);
	};
	
	$if(verbose)$// A property for every concept with representation OBJECT of the editable relations in this interface
	$endif$$editableObjects:{concept|\$scope.typeahead['$concept$'] = Restangular.all('resource/$concept$').getList().\$object;
	}$$else$$if(verbose)$// The interface does not contain editable relations to a concept with representation OBJECT$endif$$endif$
	
	function _getResourceIndex(itemId, items){
		var index;
		items.some(function(item, idx){
			return (item.id === itemId) && (index = idx)
		});
		return index;
	}
	
	//show/hide save button
	function showHideButtons(invariantRulesHold, requestType, resourceId){
		if(invariantRulesHold && requestType == 'feedback'){
			\$scope.showSaveButton[resourceId] = true;
			\$scope.showCancelButton[resourceId] = true;
			setResourceStatus(resourceId, 'warning');
		}else if(invariantRulesHold && requestType == 'promise'){
			\$scope.showSaveButton[resourceId] = false;
			\$scope.showCancelButton[resourceId] = false;
			setResourceStatus(resourceId, 'success');
			\$timeout(function(){
				setResourceStatus(resourceId, 'default');
			}, 3000);
		}else{
			setResourceStatus(resourceId, 'danger');
			\$scope.showSaveButton[resourceId] = false;
			\$scope.showCancelButton[resourceId] = true;
		}
	}
	
	function setResourceStatus(resourceId, status){
		\$scope.resourceStatus[resourceId] = { 'warning' : false
											 , 'danger'	 : false
											 , 'default' : false
											 , 'success' : false
											 };
		\$scope.resourceStatus[resourceId][status] = true; // set new status
	}
});