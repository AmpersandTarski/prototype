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
   * Find a subobject (selectFrom or setRelation) in interfaces.json and return its information.
   *
   * Expects a path of the form:
   *   resource/{ConceptType}/{atomId}/{InterfaceName}/{subName1}/{subName2}/...
   *
   * Navigates through interfaces.json by following each sub-interface name segment,
   * then finds the requested subobject (selectFrom or setRelation) in the final node.
   *
   * @param resourcePath The _path_ value from the Angular resource object
   * @param objectName The name of the subobject to find ('selectFrom' or 'setRelation')
   * @returns Promise<SubObjectMeta | null> with CRUD, conceptType, isTot, and isUni
   */
  async findSubObject(resourcePath: string, objectName: string): Promise<SubObjectMeta | null> {
    let interfaces;
    try {
      interfaces = await this.getInterfacesWithLoading();
    } catch (error) {
      console.error(`Error loading interfaces for ${objectName}:`, error);
      return null;
    }

    // Path format: resource / {ConceptType} / {atomId} / {InterfaceName} / {sub}*
    // We need at least: resource, type, id, interfaceName, one sub-interface
    const pathSegments = resourcePath.split('/');
    if (pathSegments.length < 5) {
      console.error('❌ Resource path too short:', pathSegments);
      return null;
    }

    const interfaceName = pathSegments[3];
    const subPath = pathSegments.slice(4); // e.g. ["Organisme", "Organisme"] or ["Product", "Vorm"]

    // Find the top-level interface by name
    const topInterface = interfaces.find((ifc: any) => ifc.name === interfaceName);
    if (!topInterface) {
      console.error('❌ Could not find interface:', interfaceName);
      return null;
    }

    // Navigate through sub-interfaces by following each segment in subPath.
    // Segments that match an interface name are followed (navigated into).
    // Segments that do NOT match are skipped — they are runtime data atom IDs
    // (e.g. a session hash or a project name) and are not part of the interface structure.
    // This lenient approach supports both:
    //   - Direct-resource paths: resource/{concept}/{atomId}/{ifc}/{sub1}/{sub2}/...
    //   - SESSION-based paths:   resource/SESSION/1/{ifc}/{sessionId}/Default/{dataAtom}/{fddBox}
    let currentNode = topInterface.ifcObject;
    for (const segment of subPath) {
      const found = this.findInterfaceByName(currentNode, segment);
      if (found) {
        currentNode = found;
      }
      // If not found: skip — the segment is a runtime data atom ID, not an interface name
    }

    // currentNode is now the FILTEREDDROPDOWN box; find selectFrom or setRelation.
    // Match by label (human-readable, used by BOX<FILTEREDDROPDOWN>: 'setRelation', 'selectFrom')
    // OR by name (encoded JS identifier, used by standalone atomic components whose
    // propertyName comes from the $name$ template variable, e.g. '_50__46__32_setRelation').
    const ifcObjects = currentNode.subinterfaces?.ifcObjects || [];
    const subObject = ifcObjects.find(
      (obj: any) => obj.label === objectName || obj.name === objectName,
    );

    if (!subObject) {
      console.error(`❌ Could not find '${objectName}' in`, ifcObjects.map((o: any) => o.label));
      return null;
    }

    const crudObj = subObject.crud;
    const expr = subObject.expr;

    if (!crudObj || !expr) {
      console.error(`❌ Missing crud or expr in '${objectName}'`);
      return null;
    }

    const crudString =
      (crudObj.create ? 'C' : 'c') +
      (crudObj.read ? 'R' : 'r') +
      (crudObj.update ? 'U' : 'u') +
      (crudObj.delete ? 'D' : 'd');

    return {
      crud: crudString,
      conceptType: expr.tgtConceptName || '',
      isTot: expr.isTot || false,
      isUni: expr.isUni || false,
    };
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
