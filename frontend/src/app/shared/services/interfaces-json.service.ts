import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable, throwError, firstValueFrom } from 'rxjs';
import { catchError, map } from 'rxjs/operators';

// Meta information of setRelation and selectFrom
export interface SubObjectMeta {
  crud: string;
  conceptType: string;
  isTot: boolean;
  isUni: boolean;
}

@Injectable({
  providedIn: 'root'
})
export class InterfacesJsonService {
  private interfaces: any = null;

  constructor(private http: HttpClient) {}

  /**
   * Load interfaces.json file and store it
   * Throws error if file is not available
   */
  loadInterfaces(): Promise<void> {
    return firstValueFrom(
      this.http.get('/assets/interfaces.json').pipe(
        map((data) => {
          console.log('✅ Interfaces file loaded successfully:', data);
          this.interfaces = data;
          return;
        }),
        catchError((error) => {
          const errorMessage = `❌ Failed to load interfaces.json: ${error.status} ${error.statusText}`;
          console.error(errorMessage, error);
          throw new Error(errorMessage);
        })
      )
    );
  }

  /**
   * Get the loaded interfaces data
   */
  getInterfaces(): any {
    if (!this.interfaces) {
      throw new Error('Interfaces not loaded. Call loadInterfaces() first.');
    }
    return this.interfaces;
  }

  /**
   * Check if interfaces are loaded
   */
  isLoaded(): boolean {
    return this.interfaces !== null;
  }

  /**
   * Helper method to get interfaces with automatic loading if needed
   */
  private async getInterfacesWithLoading(): Promise<any[]> {
    if (!this.isLoaded()) {
      await this.loadInterfaces();
    }
    return this.getInterfaces();
  }

  /**
   * Find a subobject (selectFrom or setRelation) in interfaces.json and return its information
   * @param resourcePath The resource path from the component
   * @param objectName The name of the subobject to find ('selectFrom' or 'setRelation')
   * @returns Promise<SubObjectMeta | null> containing CRUD, conceptType, isTot, and isUni information
   */
  async findSubObject(resourcePath: string, objectName: 'selectFrom' | 'setRelation'): Promise<SubObjectMeta | null> {
    // Ensure interfaces are loaded and get them
    let interfaces;
    try {
      interfaces = await this.getInterfacesWithLoading();
    } catch (error) {
      console.error(`Error loading interfaces for ${objectName}:`, error);
      return null;
    }

    // Parse the resource path to extract key segments
    const pathSegments = resourcePath.split('/');
    console.log('pathSegmments: ', pathSegments);

    if (pathSegments.length < 3) {
      console.error('❌ Resource path too short:', pathSegments);
      return null;
    }

    // Determine path type based on structure
    const isSessionPath = pathSegments[1] === 'SESSION';
    const isDirectEntityPath = !isSessionPath && pathSegments.length >= 4;

    if (isSessionPath) {
      return this.findSubObjectInSessionPath(interfaces, pathSegments, objectName);
    } else if (isDirectEntityPath) {
      return this.findSubObjectInDirectEntityPath(interfaces, pathSegments, objectName);
    } else {
      console.error('❌ Unrecognized resource path format:', resourcePath);
      return null;
    }
  }

  /**
   * Handle SESSION-based paths like: resource/SESSION/1/InterfaceName/sessionId/Default/dataValue/objectName
   */
  private findSubObjectInSessionPath(interfaces: any[], pathSegments: string[], objectName: string): SubObjectMeta | null {
    if (pathSegments.length < 8) {
      console.error('❌ SESSION resource path too short:', pathSegments);
      return null;
    }

    const sessionId = pathSegments[4];
    const defaultName = pathSegments[5];
    const targetObjectName = pathSegments[7];
    const interfaceName = pathSegments[3];

    // Step 1: Find SESSION interface
    let sessionInterface = null;
    for (const ifc of interfaces) {
      const hasSessionPattern = this.matchesSessionId(ifc.ifcObject, sessionId);
      if (hasSessionPattern && ifc.name === interfaceName) {
        sessionInterface = ifc.ifcObject;
        break;
      }
    }

    if (!sessionInterface) {
      console.error('❌ Could not find SESSION interface');
      return null;
    }

    // Step 2: Find Default interface
    const defaultInterface = this.findInterfaceByName(sessionInterface, defaultName);
    if (!defaultInterface) {
      console.error('❌ Could not find Default interface');
      return null;
    }

    // Step 3: Find target object (e.g., the FILTEREDDROPDOWN object)
    const targetObject = this.findInterfaceByName(defaultInterface, targetObjectName);
    if (!targetObject) {
      console.error('❌ Could not find target object:', targetObjectName);
      return null;
    }

    return this.extractSubObjectMeta(targetObject, objectName);
  }

  /**
   * Handle direct entity paths like: resource/Product/product1/EditProduct
   */
  private findSubObjectInDirectEntityPath(interfaces: any[], pathSegments: string[], objectName: string): SubObjectMeta | null {
    const interfaceName = pathSegments[3]; // e.g., 'EditProduct'
    
    console.log('🔍 Looking for direct entity interface:', interfaceName);

    // Find the interface by name
    const targetInterface = interfaces.find(ifc => ifc.name === interfaceName);
    if (!targetInterface) {
      console.error('❌ Could not find direct entity interface:', interfaceName);
      return null;
    }

    console.log('✅ Found direct entity interface:', interfaceName);

    // Navigate through the nested structure to find FILTEREDDROPDOWN
    return this.findFilteredDropdownInInterface(targetInterface.ifcObject, objectName);
  }

  /**
   * Recursively search for FILTEREDDROPDOWN type and extract subobject
   */
  private findFilteredDropdownInInterface(ifcObject: any, objectName: string): SubObjectMeta | null {
    // Check if current level is FILTEREDDROPDOWN
    if (ifcObject.subinterfaces?.boxHeader?.type === 'FILTEREDDROPDOWN') {
      console.log('✅ Found FILTEREDDROPDOWN at current level');
      return this.extractSubObjectMeta(ifcObject, objectName);
    }

    // Recursively search in subinterfaces
    if (ifcObject.subinterfaces?.ifcObjects) {
      for (const subObj of ifcObject.subinterfaces.ifcObjects) {
        const result = this.findFilteredDropdownInInterface(subObj, objectName);
        if (result) {
          return result;
        }
      }
    }

    console.log('❌ FILTEREDDROPDOWN not found in this branch');
    return null;
  }

  /**
   * Extract subobject metadata from a FILTEREDDROPDOWN interface
   */
  private extractSubObjectMeta(targetObject: any, objectName: string): SubObjectMeta | null {
    // Find the specific subobject (selectFrom or setRelation)
    const ifcObjects = targetObject.subinterfaces?.ifcObjects || [];
    const subObject = ifcObjects.find((obj: any) => obj.label === objectName);

    if (!subObject) {
      console.error(`❌ Could not find ${objectName} object`);
      console.log('🔍 Available objects:', ifcObjects.map((obj: any) => obj.label));
      return null;
    }

    console.log(`✅ Found ${objectName} object:`, subObject);

    // Extract and format information
    const crudObj = subObject.crud;
    const expr = subObject.expr;

    if (!crudObj || !expr) {
      console.error(`❌ Missing crud or expr in ${objectName} object`);
      return null;
    }

    // Convert CRUD boolean values to string format
    const crudString =
      (crudObj.create ? 'C' : 'c') +
      (crudObj.read ? 'R' : 'r') +
      (crudObj.update ? 'U' : 'u') +
      (crudObj.delete ? 'D' : 'd');

    const result = {
      crud: crudString,
      conceptType: expr.tgtConceptName || '',
      isTot: expr.isTot || false,
      isUni: expr.isUni || false
    };

    console.log(`✅ Successfully extracted ${objectName} metadata:`, result);
    return result;
  }

  /**
   * Check if an interface matches the session pattern by looking for "_SESSION"[SESSION] in NormalizationSteps
   */
  private matchesSessionId(ifcObject: any, sessionId: string): boolean {
    if (!ifcObject || !ifcObject.NormalizationSteps) return false;

    // Check if NormalizationSteps contains "_SESSION"[SESSION] pattern
    return ifcObject.NormalizationSteps.some((step: any) =>
      step.includes('"_SESSION"[SESSION]'));
  }

  /**
   * Find an interface by name within subinterfaces.ifcObjects
   */
  private findInterfaceByName(parentInterface: any, name: string): any {
    if (!parentInterface || !parentInterface.subinterfaces || !parentInterface.subinterfaces.ifcObjects) {
      return null;
    }

    return parentInterface.subinterfaces.ifcObjects.find((obj: any) => obj.name === name);
  }

  /**
   * Find a project interface by matching NormalizationSteps with I[Project]
   */
  private findProjectInterface(defaultInterface: any): any {
    if (!defaultInterface || !defaultInterface.subinterfaces || !defaultInterface.subinterfaces.ifcObjects) {
      return null;
    }

    return defaultInterface.subinterfaces.ifcObjects.find((obj: any) => {
      // Check if NormalizationSteps contains I[Project]
      const hasProjectStep = obj.NormalizationSteps &&
        obj.NormalizationSteps.some((step: any) => step.includes('I[Project]'));

      return hasProjectStep;
    });
  }

  /**
   * Find an interface that contains "I[Something]" pattern and has the target object
   */
  private findInterfaceWithTarget(defaultInterface: any, targetObjectName: string): any {
    if (!defaultInterface || !defaultInterface.subinterfaces || !defaultInterface.subinterfaces.ifcObjects) {
      console.log('🔍 No ifcObjects in defaultInterface');
      return null;
    }

    console.log('🔍 Searching through', defaultInterface.subinterfaces.ifcObjects.length, 'objects in Default interface');

    // Look through all ifcObjects in the defaultInterface
    for (let i = 0; i < defaultInterface.subinterfaces.ifcObjects.length; i++) {
      const obj = defaultInterface.subinterfaces.ifcObjects[i];
      console.log(`🔍 Checking object ${i}:`, obj.label || obj.name, 'NormalizationSteps:', obj.NormalizationSteps);

      // Check if this object has "I[Something]" pattern in NormalizationSteps
      const hasIdentityPattern = obj.NormalizationSteps &&
        obj.NormalizationSteps.some((step: any) => {
          // Look for I[Something] pattern with flexible spacing
          const matches = /I\s*\[\s*\w+\s*\]/.test(step);
          if (matches) {
            console.log('✅ Found I[Something] pattern in step:', step);
          }
          return matches;
        });

      if (hasIdentityPattern) {
        console.log('🎯 Object has I[Something] pattern, checking for target object...');
        console.log('🔍 subinterfaces:', obj.subinterfaces ? 'exists' : 'missing');
        console.log('🔍 ifcObjects:', obj.subinterfaces?.ifcObjects ? `exists (${obj.subinterfaces.ifcObjects.length})` : 'missing');

        if (obj.subinterfaces?.ifcObjects) {
          console.log('🔍 ifcObjects names:', obj.subinterfaces.ifcObjects.map((subObj: any) => subObj.name));
        }

        // Check if this interface contains our target object
        const containsTarget = obj.subinterfaces &&
          obj.subinterfaces.ifcObjects &&
          obj.subinterfaces.ifcObjects.some((subObj: any) => subObj.name === targetObjectName);

        if (containsTarget) {
          console.log('🎯 Found interface with I[Something] pattern that contains target:', obj.label || obj.name);
          return obj;
        } else {
          console.log('❌ Interface has I[Something] but does not contain target:', targetObjectName);
        }
      } else {
        console.log('❌ No I[Something] pattern found');
      }
    }

    console.log('❌ No container interface found for target:', targetObjectName);
    return null;
  }

  /**
   * Map the resource path 1:1 to interfaces.json structure by following each path segment
   * Example path: resource/SESSION/1/BoxFilteredDropdownTests/f5c2f7cebc6a00601ca15ab27cbe55d4/Default/project 1/_49__46__32_Assign_32_an_32_employee_32__40_cRud_41_
   * Note: "project 1" is a data value, not interface structure - we skip it and look directly in Project interface
   */
  mapResourcePathToInterfaces(resourcePath: string): void {
    const interfaces = this.getInterfaces();

    console.log('🔍 Mapping resource path to interfaces.json:', resourcePath);

    // Parse the resource path to extract key segments
    // Expected format: resource/SESSION/1/InterfaceName/sessionId/Default/projectDataValue/objectName
    const pathSegments = resourcePath.split('/');

    if (pathSegments.length < 8) {
      console.error('❌ Resource path too short:', pathSegments);
      return;
    }

    const sessionId = pathSegments[4]; // e.g., f5c2f7cebc6a00601ca15ab27cbe55d4
    const defaultName = pathSegments[5]; // e.g., Default
    const projectDataValue = pathSegments[6]; // e.g., project 1 (this is data, not interface structure)
    const objectName = pathSegments[7]; // e.g., _49__46__32_Assign_32_an_32_employee_32__40_cRud_41_

    console.log('📋 Path segments:', {
      sessionId,
      defaultName,
      projectDataValue,
      objectName
    });

    // Step 1: Find ifcObject with [SESSION] in normalisationSteps and matching interface name
    let sessionInterface = null;
    const interfaceName = pathSegments[3]; // e.g., "BoxFilteredDropdownTests"

    for (const ifc of interfaces) {
      const hasSessionPattern = this.matchesSessionId(ifc.ifcObject, sessionId);
      if (hasSessionPattern && ifc.name === interfaceName) {
        sessionInterface = ifc.ifcObject;
        console.log('✅ Found SESSION interface with ID:', sessionId, 'and name:', ifc.name);
        break;
      }
    }

    if (!sessionInterface) {
      console.error('❌ Could not find SESSION interface with ID:', sessionId);
      return;
    }

    // Step 2: Within session interface, find subinterfaces.ifcObjects with name 'Default'
    const defaultInterface = this.findInterfaceByName(sessionInterface, defaultName);
    if (!defaultInterface) {
      console.error('❌ Could not find Default interface');
      return;
    }
    console.log('✅ Found Default interface');

    // Step 3: Find the specific object with the exact objectName
    // Note: We skip the projectDataValue ("project 1") as it's runtime data, not interface structure
    const targetObject = this.findInterfaceByName(defaultInterface, objectName);
    if (!targetObject) {
      console.error('❌ Could not find target object:', objectName);
      console.log('🔍 Available objects in Default interface:',
        defaultInterface.subinterfaces?.ifcObjects?.map((obj: any) => obj.name) || 'No ifcObjects');
      return;
    }
    console.log('✅ Found target object:', objectName);

    // Step 4: Verify it's correct by checking subInterfaces.boxheader.type === 'FILTEREDDROPDOWN'
    if (targetObject.subinterfaces && targetObject.subinterfaces.boxHeader &&
        targetObject.subinterfaces.boxHeader.type === 'FILTEREDDROPDOWN') {
      console.log('✅ Verified FILTEREDDROPDOWN type:', targetObject.subinterfaces.boxHeader.type);

      // Step 5: Find selectFrom and setRelation objects within ifcObjects
      const ifcObjects = targetObject.subinterfaces.ifcObjects || [];

      const selectFromObj = ifcObjects.find((obj: any) => obj.label === 'selectFrom');
      const setRelationObj = ifcObjects.find((obj: any) => obj.label === 'setRelation');

      if (selectFromObj) {
        console.log('🎯 selectFrom object found:', selectFromObj);
      } else {
        console.error('❌ selectFrom object not found');
      }

      if (setRelationObj) {
        console.log('🎯 setRelation object found:', setRelationObj);
      } else {
        console.error('❌ setRelation object not found');
      }

    } else {
      console.error('❌ Not a FILTEREDDROPDOWN type or missing boxHeader');
    }
  }
}
