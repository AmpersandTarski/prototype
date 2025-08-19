import { TestBed } from '@angular/core/testing';
import { Router } from '@angular/router';
import { of } from 'rxjs';

import { RolesGuard } from './roles.guard';
import { RolesService } from './roles.service';

describe('RolesGuard', () => {
  let guard: RolesGuard;

  beforeEach(() => {
    const rolesServiceMock = {
      isRole: jest.fn().mockReturnValue(of(true))
    };

    const routerMock = {
      navigate: jest.fn()
    };

    TestBed.configureTestingModule({
      providers: [
        RolesGuard,
        { provide: RolesService, useValue: rolesServiceMock },
        { provide: Router, useValue: routerMock }
      ]
    });
    
    guard = TestBed.inject(RolesGuard);
  });

  it('should be created', () => {
    expect(guard).toBeTruthy();
  });
});
