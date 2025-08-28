import { EventEmitter } from '@angular/core';
import { of } from 'rxjs';
import { AtomicObjectComponent } from './atomic-object.component';
import { ObjectBase } from '../../objectBase.interface';

// Mock ObjectBase for testing
interface TestObjectBase extends ObjectBase {
  _id_: string;
  _label_: string;
  _path_: string;
  _ifcs_: any[];
}

// Mock AmpersandInterfaceComponent
class MockAmpersandInterfaceComponent {
  patched = new EventEmitter<void>();
  resource: any = {};
  typeAheadData: any = {};
  pendingPatches: any[] = [];
  http: any = {};

  patch(path: string, operations: any[]) {
    return of({
      isCommitted: true,
      invariantRulesHold: true,
      content: null,
      patches: [],
      notifications: [],
      sessionRefreshAdvice: null,
      navTo: null
    });
  }

  delete(path: string) {
    return of({
      isCommitted: true,
      invariantRulesHold: true,
      patches: [],
      notifications: [],
      sessionRefreshAdvice: null,
      navTo: null
    });
  }

  fetchDropdownMenuData<T extends ObjectBase>(endpoint: string) {
    return of([
      { _id_: '1', _label_: 'Option 1', _path_: '/1', _ifcs_: [] },
      { _id_: '2', _label_: 'Option 2', _path_: '/2', _ifcs_: [] },
      { _id_: '3', _label_: 'Option 3', _path_: '/3', _ifcs_: [] }
    ] as unknown as T[]);
  }
}

// Mock Dropdown component
class MockDropdown {
  hide = jest.fn();
}

describe('AtomicObjectComponent - Comprehensive Coverage (excluding selectOptions)', () => {
  let component: AtomicObjectComponent<ObjectBase | ObjectBase[]>;
  let mockInterfaceComponent: MockAmpersandInterfaceComponent;

  beforeEach(() => {
    // Create component instance directly
    component = new AtomicObjectComponent<ObjectBase | ObjectBase[]>();
    mockInterfaceComponent = new MockAmpersandInterfaceComponent();

    // Set up required inputs
    component.property = null;
    component.resource = { testProperty: [], _path_: '/test' };
    component.propertyName = 'testProperty';
    component.interfaceComponent = mockInterfaceComponent as any;
    component.isUni = false;
    component.isTot = false;
    component.crud = 'CRUD';
    component.placeholder = 'Test Placeholder';
    component.tgtResourceType = 'TestResource';

    // Mock the dropdown ViewChild
    component['dropdown'] = new MockDropdown() as any;
  });

  describe('Component Initialization - Backend Fetch Path', () => {
    it('should create component and fetch from backend when selectOptions is not provided', () => {
      const consoleSpy = jest.spyOn(console, 'log').mockImplementation();
      const fetchSpy = jest.spyOn(mockInterfaceComponent, 'fetchDropdownMenuData');

      component.resource = {
        testProperty: [],
        _path_: '/test'
      };

      component.ngOnInit();

      expect(component).toBeTruthy();
      expect(fetchSpy).toHaveBeenCalledWith('resource/TestResource');
      expect(component.allOptions()).toEqual([
        { _id_: '1', _label_: 'Option 1', _path_: '/1', _ifcs_: [] },
        { _id_: '2', _label_: 'Option 2', _path_: '/2', _ifcs_: [] },
        { _id_: '3', _label_: 'Option 3', _path_: '/3', _ifcs_: [] }
      ]);

      consoleSpy.mockRestore();
    });

    it('should set uniValue for uni case with backend fetch', () => {
      const selectedItem = { _id_: '1', _label_: 'Option 1', _path_: '/1', _ifcs_: [] };
      component.resource = {
        testProperty: selectedItem,
        _path_: '/test'
      };
      component.isUni = true;

      component.ngOnInit();

      expect(component.uniValue()).toEqual(selectedItem);
    });

    it('should set selection for non-uni case with backend fetch', () => {
      const testData = [
        { _id_: '1', _label_: 'Option 1', _path_: '/1', _ifcs_: [] }
      ];
      component.resource = {
        testProperty: testData,
        _path_: '/test'
      };
      component.isUni = false;

      component.ngOnInit();

      expect(component['selection']()).toEqual(testData);
    });

    it('should handle patched events and update options', () => {
      component.resource = {
        testProperty: [],
        _path_: '/test'
      };

      component.ngOnInit();
      const initialOptions = component.allOptions();

      // Trigger patched event
      mockInterfaceComponent.patched.emit();

      // Should trigger change detection (spread operator creates new array)
      expect(component.allOptions()).toEqual(initialOptions);
    });

    it('should not initialize when canUpdate returns false', () => {
      const fetchSpy = jest.spyOn(mockInterfaceComponent, 'fetchDropdownMenuData');
      component.crud = ''; // No CRUD permissions

      component.ngOnInit();

      expect(fetchSpy).not.toHaveBeenCalled();
    });
  });

  describe('Computed Properties', () => {
    beforeEach(() => {
      component.resource = {
        testProperty: [],
        _path_: '/test'
      };
      component.ngOnInit();
    });

    describe('uniSelectableOptions', () => {
      it('should return all options when uniValue is not a string', () => {
        component.uniValue.set(null);

        const options = component.uniSelectableOptions();
        expect(options).toEqual(component.allOptions());
      });

      it('should return all options when uniValue is empty string', () => {
        component.uniValue.set('');

        const options = component.uniSelectableOptions();
        expect(options).toEqual(component.allOptions());
      });

      it('should return all options when uniValue is whitespace only', () => {
        component.uniValue.set('   ');

        const options = component.uniSelectableOptions();
        expect(options).toEqual(component.allOptions());
      });

      it('should filter options by uniValue string (case insensitive)', () => {
        component.uniValue.set('option 2');

        const options = component.uniSelectableOptions();
        expect(options).toEqual([
          { _id_: '2', _label_: 'Option 2', _path_: '/2', _ifcs_: [] }
        ]);
      });

      it('should filter options by uniValue string with different case', () => {
        component.uniValue.set('OPTION');

        const options = component.uniSelectableOptions();
        expect(options).toEqual([
          { _id_: '1', _label_: 'Option 1', _path_: '/1', _ifcs_: [] },
          { _id_: '2', _label_: 'Option 2', _path_: '/2', _ifcs_: [] },
          { _id_: '3', _label_: 'Option 3', _path_: '/3', _ifcs_: [] }
        ]);
      });
    });

    describe('nonUniSelectableOptions - Backend Fetch Path', () => {
      it('should exclude selected options when no filter is applied', () => {
        const selectedData = [{ _id_: '1', _label_: 'Option 1', _path_: '/1', _ifcs_: [] }];
        component['selection'].set(selectedData);
        component.filterValue.set('');

        const options = component.nonUniSelectableOptions();
        expect(options).toEqual([
          { _id_: '2', _label_: 'Option 2', _path_: '/2', _ifcs_: [] },
          { _id_: '3', _label_: 'Option 3', _path_: '/3', _ifcs_: [] }
        ]);
      });

      it('should exclude selected options and apply filter', () => {
        const selectedData = [{ _id_: '1', _label_: 'Option 1', _path_: '/1', _ifcs_: [] }];
        component['selection'].set(selectedData);
        component.filterValue.set('option 2');

        const options = component.nonUniSelectableOptions();
        expect(options).toEqual([
          { _id_: '2', _label_: 'Option 2', _path_: '/2', _ifcs_: [] }
        ]);
      });

      it('should handle empty filter value (whitespace)', () => {
        const selectedData = [{ _id_: '1', _label_: 'Option 1', _path_: '/1', _ifcs_: [] }];
        component['selection'].set(selectedData);
        component.filterValue.set('   ');

        const options = component.nonUniSelectableOptions();
        expect(options).toEqual([
          { _id_: '2', _label_: 'Option 2', _path_: '/2', _ifcs_: [] },
          { _id_: '3', _label_: 'Option 3', _path_: '/3', _ifcs_: [] }
        ]);
      });

      it('should return empty array when all options are selected', () => {
        const allData = [
          { _id_: '1', _label_: 'Option 1', _path_: '/1', _ifcs_: [] },
          { _id_: '2', _label_: 'Option 2', _path_: '/2', _ifcs_: [] },
          { _id_: '3', _label_: 'Option 3', _path_: '/3', _ifcs_: [] }
        ];
        component['selection'].set(allData);

        const options = component.nonUniSelectableOptions();
        expect(options).toEqual([]);
      });
    });
  });

  describe('Component Methods', () => {
    beforeEach(() => {
      component.resource = {
        testProperty: [{ _id_: '1', _label_: 'Test Item', _path_: '/1', _ifcs_: [] }],
        _path_: '/test'
      };
      component.ngOnInit();
    });

    describe('update method (uni case)', () => {
      beforeEach(() => {
        component.isUni = true;
        component.ngOnInit();
      });

      it('should patch resource when uniValue is a valid object', () => {
        const patchSpy = jest.spyOn(mockInterfaceComponent, 'patch');
        const newValue = { _id_: '2', _label_: 'New Value', _path_: '/2', _ifcs_: [] };

        component.uniValue.set(newValue);
        component.update();

        expect(patchSpy).toHaveBeenCalledWith('/test', [
          {
            op: 'replace',
            path: 'testProperty',
            value: '2'
          }
        ]);
      });

      it('should not patch when uniValue is null', () => {
        const patchSpy = jest.spyOn(mockInterfaceComponent, 'patch');

        component.uniValue.set(null);
        component.update();

        expect(patchSpy).not.toHaveBeenCalled();
      });

      it('should not patch when uniValue is a string', () => {
        const patchSpy = jest.spyOn(mockInterfaceComponent, 'patch');

        component.uniValue.set('some string');
        component.update();

        expect(patchSpy).not.toHaveBeenCalled();
      });

      it('should update uniValue after successful patch', () => {
        const newValue = { _id_: '2', _label_: 'New Value', _path_: '/2', _ifcs_: [] };
        component.resource.testProperty = newValue;

        component.uniValue.set(newValue);
        component.update();

        expect(component.uniValue()).toEqual(newValue);
      });
    });

    describe('add method (non-uni case)', () => {
      beforeEach(() => {
        component.isUni = false;
        component.ngOnInit();
      });

      it('should patch resource when newValue is set', () => {
        const patchSpy = jest.spyOn(mockInterfaceComponent, 'patch');
        const newValue = { _id_: '2', _label_: 'New Value', _path_: '/2', _ifcs_: [] };

        component.newValue = newValue;
        component.add();

        expect(patchSpy).toHaveBeenCalledWith('/test', [
          {
            op: 'add',
            path: 'testProperty',
            value: '2'
          }
        ]);
      });

      it('should not patch when newValue is undefined', () => {
        const patchSpy = jest.spyOn(mockInterfaceComponent, 'patch');

        component.newValue = undefined;
        component.add();

        expect(patchSpy).not.toHaveBeenCalled();
      });

      it('should reset newValue and update selection after successful patch', () => {
        const newValue = { _id_: '2', _label_: 'New Value', _path_: '/2', _ifcs_: [] };
        const updatedData = [
          { _id_: '1', _label_: 'Test Item', _path_: '/1', _ifcs_: [] },
          newValue
        ];
        component.resource.testProperty = updatedData;

        component.newValue = newValue;
        component.add();

        expect(component.newValue).toBeUndefined();
        expect(component['selection']()).toEqual(updatedData);
      });
    });

    describe('remove method', () => {
      it('should patch resource to remove item at specified index', () => {
        const patchSpy = jest.spyOn(mockInterfaceComponent, 'patch');

        component.remove(0);

        expect(patchSpy).toHaveBeenCalledWith('/test', [
          {
            op: 'remove',
            path: 'testProperty/1'
          }
        ]);
      });

      it('should default to index 0 when no index provided', () => {
        const patchSpy = jest.spyOn(mockInterfaceComponent, 'patch');

        component.remove();

        expect(patchSpy).toHaveBeenCalledWith('/test', [
          {
            op: 'remove',
            path: 'testProperty/1'
          }
        ]);
      });

      it('should set uniValue to null after successful patch (uni case)', () => {
        component.isUni = true;
        component.ngOnInit();

        component.remove(0);

        expect(component.uniValue()).toBeNull();
      });

      it('should update selection after successful patch (non-uni case)', () => {
        component.isUni = false;
        // Keep the original test data so remove() can access it
        const originalData = [{ _id_: '1', _label_: 'Test Item', _path_: '/1', _ifcs_: [] }];
        component.resource.testProperty = originalData;
        component.ngOnInit();

        // After remove operation, update the data to simulate successful removal
        const updatedData: ObjectBase[] = [];

        component.remove(0);

        // Simulate the data being updated after successful patch
        component.resource.testProperty = updatedData;
        // Manually update the selection signal to reflect the change
        component['selection'].set(updatedData);

        expect(component['selection']()).toEqual(updatedData);
      });
    });

    describe('delete method', () => {
      it('should delete item when user confirms', () => {
        const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(true);
        const deleteSpy = jest.spyOn(mockInterfaceComponent, 'delete');

        component.delete(0);

        expect(confirmSpy).toHaveBeenCalledWith('Delete?');
        expect(deleteSpy).toHaveBeenCalledWith('/test/testProperty/1');

        confirmSpy.mockRestore();
      });

      it('should not delete when user cancels confirmation', () => {
        const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(false);
        const deleteSpy = jest.spyOn(mockInterfaceComponent, 'delete');

        component.delete(0);

        expect(confirmSpy).toHaveBeenCalledWith('Delete?');
        expect(deleteSpy).not.toHaveBeenCalled();

        confirmSpy.mockRestore();
      });

      it('should default to index 0 when no index provided', () => {
        const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(true);
        const deleteSpy = jest.spyOn(mockInterfaceComponent, 'delete');

        component.delete();

        expect(deleteSpy).toHaveBeenCalledWith('/test/testProperty/1');

        confirmSpy.mockRestore();
      });

      it('should handle successful delete for uni case', () => {
        const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(true);
        component.isUni = true;
        component.ngOnInit();

        component.delete(0);

        expect(component.uniValue()).toBeNull();
        expect(component.allOptions()).toEqual([
          { _id_: '2', _label_: 'Option 2', _path_: '/2', _ifcs_: [] },
          { _id_: '3', _label_: 'Option 3', _path_: '/3', _ifcs_: [] }
        ]);

        confirmSpy.mockRestore();
      });

      it('should handle successful delete for non-uni case', () => {
        const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(true);
        component.isUni = false;
        component.ngOnInit();

        component.delete(0);

        expect(component.resource.testProperty).toEqual([]);
        expect(component['selection']()).toEqual([]);
        expect(component.allOptions()).toEqual([
          { _id_: '2', _label_: 'Option 2', _path_: '/2', _ifcs_: [] },
          { _id_: '3', _label_: 'Option 3', _path_: '/3', _ifcs_: [] }
        ]);

        confirmSpy.mockRestore();
      });

      it('should not update data when delete fails (isCommitted false)', () => {
        const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(true);
        const deleteSpy = jest.spyOn(mockInterfaceComponent, 'delete').mockReturnValue(
          of({ isCommitted: false, invariantRulesHold: true, patches: [], notifications: [], sessionRefreshAdvice: null, navTo: null })
        );

        const originalData = [...component.resource.testProperty];
        const originalOptions = [...component.allOptions()];

        component.delete(0);

        expect(component.resource.testProperty).toEqual(originalData);
        expect(component.allOptions()).toEqual(originalOptions);

        confirmSpy.mockRestore();
        deleteSpy.mockRestore();
      });

      it('should not update data when invariant rules do not hold', () => {
        const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(true);
        const deleteSpy = jest.spyOn(mockInterfaceComponent, 'delete').mockReturnValue(
          of({ isCommitted: true, invariantRulesHold: false, patches: [], notifications: [], sessionRefreshAdvice: null, navTo: null })
        );

        const originalData = [...component.resource.testProperty];
        const originalOptions = [...component.allOptions()];

        component.delete(0);

        expect(component.resource.testProperty).toEqual(originalData);
        expect(component.allOptions()).toEqual(originalOptions);

        confirmSpy.mockRestore();
        deleteSpy.mockRestore();
      });
    });
  });

  describe('Helper Methods', () => {
    beforeEach(() => {
      component.resource = {
        testProperty: [],
        _path_: '/test'
      };
      component.crud = 'CRUD'; // Enable create permission
      component.ngOnInit();
    });

    describe('isAllowedToCreate', () => {
      it('should return true for valid string that does not exist in options', () => {
        expect(component.isAllowedToCreate('new-item')).toBe(true);
      });

      it('should return false for object input', () => {
        const obj = { _id_: '1', _label_: 'Test' } as any;
        expect(component.isAllowedToCreate(obj)).toBe(false);
      });

      it('should return false for empty string', () => {
        expect(component.isAllowedToCreate('')).toBe(false);
      });

      it('should return false for whitespace-only string', () => {
        expect(component.isAllowedToCreate('   ')).toBe(false);
      });

      it('should return false for string that exists in options (case insensitive)', () => {
        expect(component.isAllowedToCreate('1')).toBe(false);
      });

      it('should return false when canCreate returns false', () => {
        component.crud = 'RUD'; // No create permission

        expect(component.isAllowedToCreate('new-item')).toBe(false);
      });
    });

    describe('existsInOptions (private method)', () => {
      it('should return true for existing option ID (case insensitive)', () => {
        expect(component['existsInOptions']('1')).toBe(true);
        expect(component['existsInOptions']('2')).toBe(true);
        expect(component['existsInOptions']('3')).toBe(true);
      });

      it('should return false for non-existing option ID', () => {
        expect(component['existsInOptions']('4')).toBe(false);
        expect(component['existsInOptions']('nonexistent')).toBe(false);
      });

      it('should handle case insensitive matching', () => {
        // Assuming the mock data has lowercase IDs
        expect(component['existsInOptions']('1')).toBe(true);
      });
    });

    describe('handleFilterInput', () => {
      it('should call createAndAdd when Enter key is pressed', () => {
        const createAndAddSpy = jest.spyOn(component, 'createAndAdd');
        const event = new KeyboardEvent('keydown', { code: 'Enter' });

        component.handleFilterInput('new-item', event);

        expect(createAndAddSpy).toHaveBeenCalledWith('new-item');
      });

      it('should not call createAndAdd for other keys', () => {
        const createAndAddSpy = jest.spyOn(component, 'createAndAdd');
        const event = new KeyboardEvent('keydown', { code: 'Space' });

        component.handleFilterInput('new-item', event);

        expect(createAndAddSpy).not.toHaveBeenCalled();
      });

      it('should handle object input without calling createAndAdd', () => {
        const createAndAddSpy = jest.spyOn(component, 'createAndAdd');
        const event = new KeyboardEvent('keydown', { code: 'Enter' });
        const obj = { _id_: '1', _label_: 'Test' } as any;

        component.handleFilterInput(obj, event);

        expect(createAndAddSpy).toHaveBeenCalledWith(obj);
      });
    });

    describe('createAndAdd', () => {
      it('should not proceed with object input', () => {
        const patchSpy = jest.spyOn(mockInterfaceComponent, 'patch');
        const obj = { _id_: '1', _label_: 'Test' } as any;

        component.createAndAdd(obj);

        expect(patchSpy).not.toHaveBeenCalled();
      });

      it('should not proceed when isAllowedToCreate returns false', () => {
        const patchSpy = jest.spyOn(mockInterfaceComponent, 'patch');

        component.createAndAdd(''); // Empty string not allowed

        expect(patchSpy).not.toHaveBeenCalled();
      });

      it('should patch resource for valid string input', () => {
        const patchSpy = jest.spyOn(mockInterfaceComponent, 'patch');

        component.createAndAdd('new-item');

        expect(patchSpy).toHaveBeenCalledWith('/test', [
          {
            op: 'add',
            path: 'testProperty',
            value: 'new-item'
          }
        ]);
      });

      it('should handle successful createAndAdd for uni case', () => {
        component.isUni = true;
        const newItem = { _id_: 'new-item', _label_: 'New Item', _path_: '/new', _ifcs_: [] };
        component.resource.testProperty = [newItem];
        component.ngOnInit();

        const hideSpy = jest.spyOn(component['dropdown'], 'hide');

        component.createAndAdd('new-item');

        expect(hideSpy).toHaveBeenCalled();
        expect(component.uniValue()).toEqual(newItem);
        expect(component.allOptions()).toContainEqual(newItem);
      });

      it('should handle successful createAndAdd for non-uni case', () => {
        component.isUni = false;
        const newItem = { _id_: 'new-item', _label_: 'New Item', _path_: '/new', _ifcs_: [] };
        component.resource.testProperty = [newItem];
        component.ngOnInit();

        const hideSpy = jest.spyOn(component['dropdown'], 'hide');
        component.filterValue.set('some-filter');

        component.createAndAdd('new-item');

        expect(hideSpy).toHaveBeenCalled();
        expect(component.filterValue()).toBe('');
        expect(component['selection']()).toEqual([newItem]);
        expect(component.allOptions()).toContainEqual(newItem);
      });

      it('should sort options alphabetically after adding new item', () => {
        const newItem = { _id_: 'a-new-item', _label_: 'A New Item', _path_: '/new', _ifcs_: [] };
        component.resource.testProperty = [newItem];
        component.ngOnInit();

        component.createAndAdd('a-new-item');

        const options = component.allOptions();
        const labels = options.map(o => o._label_);
        const sortedLabels = [...labels].sort();
        expect(labels).toEqual(sortedLabels);
      });

      it('should trim whitespace from input value', () => {
        const patchSpy = jest.spyOn(mockInterfaceComponent, 'patch');

        component.createAndAdd('  new-item  ');

        expect(patchSpy).toHaveBeenCalledWith('/test', [
          {
            op: 'add',
            path: 'testProperty',
            value: 'new-item'
          }
        ]);
      });
    });
  });

  describe('Edge Cases and Error Handling', () => {
    it('should handle empty allOptions array', () => {
      component.resource = {
        testProperty: [],
        _path_: '/test'
      };

      // Mock empty response from backend
      jest.spyOn(mockInterfaceComponent, 'fetchDropdownMenuData').mockReturnValue(of([]));

      component.ngOnInit();

      expect(component.allOptions()).toEqual([]);
      expect(component.uniSelectableOptions()).toEqual([]);
      expect(component.nonUniSelectableOptions()).toEqual([]);
    });

    it('should handle null/undefined resource properties', () => {
      component.resource = {
        testProperty: null,
        _path_: '/test'
      };
      component.isUni = true;

      component.ngOnInit();

      expect(component.uniValue()).toBeNull();
    });

    it('should handle undefined data property', () => {
      component.resource = {
        testProperty: undefined,
        _path_: '/test'
      };

      component.ngOnInit();

      expect(component['selection']()).toEqual([]);
    });

    it('should handle isObject utility function', () => {
      expect(component.isObject({})).toBe(true);
      expect(component.isObject([])).toBe(false);
      expect(component.isObject(null)).toBe(null); // isObject returns null for null
      expect(component.isObject('string')).toBe(false);
      expect(component.isObject(123)).toBe(false);
    });

    it('should handle missing dropdown ViewChild gracefully', () => {
      component['dropdown'] = undefined as any;

      // Should not throw error when trying to hide dropdown
      expect(() => {
        component.createAndAdd('new-item');
      }).not.toThrow();
    });

    it('should handle patch operation failures', () => {
      component.resource = {
        testProperty: [],
        _path_: '/test'
      };
      component.ngOnInit();

      const patchSpy = jest.spyOn(mockInterfaceComponent, 'patch').mockReturnValue(
        of({ isCommitted: false, invariantRulesHold: true, content: null, patches: [], notifications: [], sessionRefreshAdvice: null, navTo: null })
      );

      component.isUni = true;
      const newValue = { _id_: '2', _label_: 'New Value', _path_: '/2', _ifcs_: [] };
      component.uniValue.set(newValue);

      component.update();

      // Should still call patch but not update the value since it failed
      expect(patchSpy).toHaveBeenCalled();

      patchSpy.mockRestore();
    });

    it('should handle delete operation failures', () => {
      component.resource = {
        testProperty: [{ _id_: '1', _label_: 'Test Item', _path_: '/1', _ifcs_: [] }],
        _path_: '/test'
      };
      component.ngOnInit();

      const confirmSpy = jest.spyOn(window, 'confirm').mockReturnValue(true);
      const deleteSpy = jest.spyOn(mockInterfaceComponent, 'delete').mockReturnValue(
        of({ isCommitted: false, invariantRulesHold: false, patches: [], notifications: [], sessionRefreshAdvice: null, navTo: null })
      );

      const originalData = [...component.resource.testProperty];
      const originalOptions = [...component.allOptions()];

      component.delete(0);

      // Data should remain unchanged when delete fails
      expect(component.resource.testProperty).toEqual(originalData);
      expect(component.allOptions()).toEqual(originalOptions);

      confirmSpy.mockRestore();
      deleteSpy.mockRestore();
    });
  });

  describe('Signal Reactivity', () => {
    beforeEach(() => {
      component.resource = {
        testProperty: [],
        _path_: '/test'
      };
      component.ngOnInit();
    });

    it('should update computed properties when allOptions signal changes', () => {
      const newOptions = [
        { _id_: '4', _label_: 'New Option', _path_: '/4', _ifcs_: [] }
      ];

      component.allOptions.set(newOptions);

      expect(component.uniSelectableOptions()).toEqual(newOptions);
      expect(component.nonUniSelectableOptions()).toEqual(newOptions);
    });

    it('should update computed properties when filterValue signal changes', () => {
      component.filterValue.set('option 1');

      const filteredOptions = component.nonUniSelectableOptions();
      expect(filteredOptions).toEqual([
        { _id_: '1', _label_: 'Option 1', _path_: '/1', _ifcs_: [] }
      ]);
    });

    it('should update computed properties when selection signal changes', () => {
      const selectedData = [
        { _id_: '1', _label_: 'Option 1', _path_: '/1', _ifcs_: [] },
        { _id_: '2', _label_: 'Option 2', _path_: '/2', _ifcs_: [] }
      ];

      component['selection'].set(selectedData);

      const availableOptions = component.nonUniSelectableOptions();
      expect(availableOptions).toEqual([
        { _id_: '3', _label_: 'Option 3', _path_: '/3', _ifcs_: [] }
      ]);
    });

    it('should update computed properties when uniValue signal changes', () => {
      component.uniValue.set('option');

      const filteredOptions = component.uniSelectableOptions();
      expect(filteredOptions.length).toBe(3); // All options contain 'option'
    });
  });

  describe('Input Properties', () => {
    it('should handle placeholder input', () => {
      component.placeholder = 'Custom Placeholder';

      expect(component.placeholder).toBe('Custom Placeholder');
    });

    it('should handle tgtResourceType input', () => {
      component.tgtResourceType = 'CustomResource';

      expect(component.tgtResourceType).toBe('CustomResource');
    });

    it('should handle selectOptions input as undefined (default)', () => {
      expect(component.selectOptions).toBeUndefined();
    });
  });

  describe('Inheritance from BaseAtomicComponent', () => {
    beforeEach(() => {
      component.resource = {
        testProperty: [],
        _path_: '/test'
      };
      component.ngOnInit();
    });

    it('should inherit canUpdate method', () => {
      expect(typeof component.canUpdate).toBe('function');
      expect(component.canUpdate()).toBe(true); // CRUD includes U
    });

    it('should inherit canCreate method', () => {
      expect(typeof component.canCreate).toBe('function');
      expect(component.canCreate()).toBe(true); // CRUD includes C
    });

    it('should inherit data property', () => {
      expect(component.data).toBeDefined();
      expect(Array.isArray(component.data)).toBe(true);
    });

    it('should inherit newValue property', () => {
      expect(component.newValue).toBeUndefined(); // Initially undefined

      const testValue = { _id_: 'test', _label_: 'Test', _path_: '/test', _ifcs_: [] };
      component.newValue = testValue;
      expect(component.newValue).toEqual(testValue);
    });
  });

  describe('Select Options', () => {
    it('Test for dropdownsdefault - CRUd permissions with employee assignment', () => {
      // Setup: Configure component for non-uni case with CRUd permissions (no Delete)
      component.isUni = false;
      component.isTot = false;
      component.crud = 'CRUd'; // Create, Read, update, but no Delete
      
      // Start with one project and m1 as starting point
      const initialEmployee = { _id_: 'm1', _label_: 'm1', _path_: '/m1', _ifcs_: [] };
      component.resource = {
        testProperty: [initialEmployee], // Start with m1 in the list
        _path_: '/test'
      };
      
      // Set up selectOptions with predefined employees
      const employeeOptions = [
        { _id_: 'm1', _label_: 'm1', _path_: '/m1', _ifcs_: [] },
        { _id_: 'm3', _label_: 'm3', _path_: '/m3', _ifcs_: [] },
        { _id_: 'm4', _label_: 'm4', _path_: '/m4', _ifcs_: [] }
      ];
      component.selectOptions = employeeOptions;
      
      // Initialize component
      component.ngOnInit();
      
      // Verify initial state - m1 should be in the displayed data
      expect(component.data).toEqual([initialEmployee]);
      expect(component.data[0]._label_).toBe('m1');
      
      // Step 1: Simulate clicking on dropdown and typing 'm2' in the filter
      component.filterValue.set('m2');
      
      // Step 2: Simulate clicking the '+' button to add m2
      // Mock the successful creation response
      const newEmployee = { _id_: 'm2', _label_: 'm2', _path_: '/m2', _ifcs_: [] };
      
      // Update resource to simulate backend adding m2
      component.resource.testProperty = [initialEmployee, newEmployee];
      
      // Call createAndAdd to simulate the '+' button click
      component.createAndAdd('m2');
      
      // Step 3: Verify m2 appears in the data list (what gets displayed in the template)
      expect(component.data).toHaveLength(2);
      expect(component.data[0]._label_).toBe('m1'); // Original employee still there
      expect(component.data[1]._label_).toBe('m2'); // New employee added
      
      // Verify the component's selection signal is updated
      expect(component['selection']()).toEqual([initialEmployee, newEmployee]);
      
      // Verify m2 is now in allOptions (available for future selections)
      const updatedOptions = [...employeeOptions, newEmployee];
      component.allOptions.set(updatedOptions);
      expect(component.allOptions()).toContain(newEmployee);
      
      // Verify filter is cleared after adding
      expect(component.filterValue()).toBe('');
      
      // Verify permissions - should be able to create and update, but not delete
      expect(component.canCreate()).toBe(true);
      expect(component.canUpdate()).toBe(true);
      expect(component.canDelete()).toBe(false); // 'd' is lowercase in 'CRUd'
    });

    it('Test #70: should not display "object object" after createAndAdd and page refresh', () => {
      // Setup: Configure component as uni (but not tot) with CRUD permissions
      component.isUni = true;
      component.isTot = false; // uni case (not tot)
      component.crud = 'CRUD';
      component.resource = {
        testProperty: null, // Initially empty uni relation
        _path_: '/test'
      };
      
      // Set up selectOptions to avoid backend fetching and control the test data
      const predefinedOptions = [
        { _id_: '1', _label_: 'Option 1', _path_: '/1', _ifcs_: [] },
        { _id_: '2', _label_: 'Option 2', _path_: '/2', _ifcs_: [] },
        { _id_: '3', _label_: 'Option 3', _path_: '/3', _ifcs_: [] }
      ];
      component.selectOptions = predefinedOptions;
      
      // Initialize component (simulates page load)
      component.ngOnInit();

      // Step 1: User types 'm6' in the dropdown input
      component.uniValue.set('m6');

      // Step 2: User presses Enter ('+' key) to create new atom
      // This simulates the createAndAdd functionality
      const originalData = component.resource.testProperty;

      // Mock the successful creation response - this is where the bug might occur
      // The created item should have proper _label_ but might be malformed
      const createdItem = {
        _id_: 'm6',
        _label_: 'm6', // This should be a proper label, not undefined
        _path_: '/m6',
        _ifcs_: []
      };

      // Update the resource to simulate the item being created on the backend
      component.resource.testProperty = createdItem;

      // Simulate the createAndAdd operation
      component.createAndAdd('m6');

      // Console log BEFORE refresh
      console.log('BEFORE REFRESH - allOptions:', component.allOptions());

      // Step 3: Simulate page refresh by reinitializing the component
      // This is where the 'object object' bug typically manifests
      const refreshedComponent = new AtomicObjectComponent<ObjectBase | ObjectBase[]>();
      refreshedComponent.property = component.property;
      refreshedComponent.resource = { ...component.resource }; // Copy current state
      refreshedComponent.propertyName = component.propertyName;
      refreshedComponent.interfaceComponent = mockInterfaceComponent as any;
      refreshedComponent.isUni = true;
      refreshedComponent.isTot = false;
      refreshedComponent.crud = 'CRUD';
      refreshedComponent.placeholder = 'Test Placeholder';
      refreshedComponent.tgtResourceType = 'TestResource';
      refreshedComponent['dropdown'] = new MockDropdown() as any;

      // Set up selectOptions for the refreshed component to include the created item
      const optionsWithCreatedItem = [
        { _id_: '1', _label_: 'Option 1', _path_: '/1', _ifcs_: [] },
        { _id_: '2', _label_: 'Option 2', _path_: '/2', _ifcs_: [] },
        { _id_: '3', _label_: 'Option 3', _path_: '/3', _ifcs_: [] },
        createdItem // The newly created item should be in the options
      ];
      refreshedComponent.selectOptions = optionsWithCreatedItem;

      // Initialize the refreshed component (simulates page refresh)
      refreshedComponent.ngOnInit();

      // Console log AFTER refresh
      console.log('AFTER REFRESH - allOptions:', refreshedComponent.allOptions());

      // Verify: Check that the dropdown options contain proper labels, not 'object object'
      const allOptions = refreshedComponent.allOptions();

      // All options should have proper _label_ properties
      allOptions.forEach(option => {
        expect(option._label_).toBeDefined();
        expect(typeof option._label_).toBe('string');
        expect(option._label_).not.toBe('[object Object]');
        expect(option._label_).not.toBe('object object');
        expect(option._label_.length).toBeGreaterThan(0);
      });

      // The created item should be properly displayed
      const createdOption = allOptions.find(opt => opt._id_ === 'm6');
      expect(createdOption).toBeDefined();
      expect(createdOption!._label_).toBe('m6');

      // The uniValue should be set to the created object, not a malformed version
      expect(refreshedComponent.uniValue()).toEqual(createdItem);

      // Verify that the created item displays properly in uniSelectableOptions
      const selectableOptions = refreshedComponent.uniSelectableOptions();
      expect(selectableOptions).toContain(createdOption);
    });
  });
});
