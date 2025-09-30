import { InterfacesJsonService } from './interfaces-json.service';
import { of, throwError } from 'rxjs';

describe('InterfacesJsonService', () => {
  let service: InterfacesJsonService;
  let mockHttp: any;

  beforeEach(() => {
    mockHttp = {
      get: jest.fn()
    };
    service = new InterfacesJsonService(mockHttp);
  });

  it('should be created', () => {
    expect(service).toBeTruthy();
  });

  describe('loadInterfaces', () => {
    it('should load interfaces successfully', async () => {
      const mockData = [
        {
          name: 'TestInterface',
          ifcObject: {
            NormalizationSteps: ['"_SESSION"[SESSION]'],
            subinterfaces: {
              ifcObjects: []
            }
          }
        }
      ];

      mockHttp.get.mockReturnValue(of(mockData));

      await service.loadInterfaces();
      
      expect(mockHttp.get).toHaveBeenCalledWith('/assets/interfaces.json');
      expect(service.isLoaded()).toBe(true);
      expect(service.getInterfaces()).toEqual(mockData);
    });

    it('should handle loading error', async () => {
      const mockError = {
        status: 404,
        statusText: 'Not Found'
      };
      
      mockHttp.get.mockReturnValue(throwError(() => mockError));

      await expect(service.loadInterfaces()).rejects.toThrow('❌ Failed to load interfaces.json: 404 Not Found');
      expect(service.isLoaded()).toBe(false);
    });
  });

  describe('getInterfaces', () => {
    it('should throw error when interfaces not loaded', () => {
      expect(() => service.getInterfaces()).toThrow('Interfaces not loaded. Call loadInterfaces() first.');
    });
  });

  describe('isLoaded', () => {
    it('should return false initially', () => {
      expect(service.isLoaded()).toBe(false);
    });
  });

  describe('findSubObject', () => {
    beforeEach(async () => {
      const mockData = [
        {
          name: 'BoxFilteredDropdownTests',
          ifcObject: {
            NormalizationSteps: ['"_SESSION"[SESSION]'],
            subinterfaces: {
              ifcObjects: [
                {
                  name: 'Default',
                  subinterfaces: {
                    ifcObjects: [
                      {
                        name: '_49__46__32_Assign_32_an_32_employee_32__40_cRud_41_',
                        subinterfaces: {
                          boxHeader: {
                            type: 'FILTEREDDROPDOWN'
                          },
                          ifcObjects: [
                            {
                              label: 'selectFrom',
                              crud: {
                                create: false,
                                read: true,
                                update: false,
                                delete: false
                              },
                              expr: {
                                tgtConceptName: 'Employee',
                                isTot: false,
                                isUni: true
                              }
                            },
                            {
                              label: 'setRelation',
                              crud: {
                                create: true,
                                read: true,
                                update: true,
                                delete: false
                              },
                              expr: {
                                tgtConceptName: 'Employee',
                                isTot: true,
                                isUni: false
                              }
                            }
                          ]
                        }
                      }
                    ]
                  }
                }
              ]
            }
          }
        }
      ];

      mockHttp.get.mockReturnValue(of(mockData));
      await service.loadInterfaces();
    });

    it('should find selectFrom subobject', async () => {
      const resourcePath = 'resource/SESSION/1/BoxFilteredDropdownTests/f5c2f7cebc6a00601ca15ab27cbe55d4/Default/project 1/_49__46__32_Assign_32_an_32_employee_32__40_cRud_41_';
      
      const result = await service.findSubObject(resourcePath, 'selectFrom');
      
      expect(result).not.toBeNull();
      expect(result?.crud).toBe('cRud');
      expect(result?.conceptType).toBe('Employee');
      expect(result?.isTot).toBe(false);
      expect(result?.isUni).toBe(true);
    });

    it('should find setRelation subobject', async () => {
      const resourcePath = 'resource/SESSION/1/BoxFilteredDropdownTests/f5c2f7cebc6a00601ca15ab27cbe55d4/Default/project 1/_49__46__32_Assign_32_an_32_employee_32__40_cRud_41_';
      
      const result = await service.findSubObject(resourcePath, 'setRelation');
      
      expect(result).not.toBeNull();
      expect(result?.crud).toBe('CRUd');
      expect(result?.conceptType).toBe('Employee');
      expect(result?.isTot).toBe(true);
      expect(result?.isUni).toBe(false);
    });

    it('should return null for invalid resource path', async () => {
      const invalidPath = 'resource/invalid/path';
      
      const result = await service.findSubObject(invalidPath, 'selectFrom');
      
      expect(result).toBeNull();
    });
  });
});
